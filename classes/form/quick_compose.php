<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * SPDX-FileCopyrightText: 2026 Anderson Blaine
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail\form;

use core_form\dynamic_form;
use local_mail\course;
use local_mail\exception;
use local_mail\local\sender;
use local_mail\message;
use local_mail\message_data;
use local_mail\settings;
use local_mail\user;
use local_mail\user_search;

/**
 * Subject and message to a fixed set of course users, sent straight from a modal.
 *
 * Opened by the `local_mail/quick_compose` AMD module with the arguments `courseid`,
 * `recipients` (comma-separated user ids) and, optionally, `role` (`to` or `bcc`). Nothing is
 * written until the form validates: a recipient the sender may not mail is reported on the form,
 * and no draft is left behind.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quick_compose extends dynamic_form {
    /**
     * Defines the form fields.
     */
    protected function definition() {
        global $OUTPUT;

        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'recipients');
        $mform->setType('recipients', PARAM_SEQUENCE);

        $ids = $this->get_requested_ids();

        $mform->addElement('static', 'recipientsummary', get_string('to', 'local_mail'), $this->recipient_summary($ids));

        if (count($ids) > 1) {
            $mform->addElement('select', 'role', get_string('quickcomposesendas', 'local_mail'), [
                'bcc' => get_string('bcc', 'local_mail'),
                'to' => get_string('to', 'local_mail'),
            ]);
            $mform->setDefault('role', 'bcc');
        } else {
            $mform->addElement('hidden', 'role', 'to');
        }
        $mform->setType('role', PARAM_ALPHA);

        $mform->addElement('text', 'subject', get_string('subject', 'local_mail'), ['size' => 60]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');
        // Message::update() cuts a longer subject to 100 characters.
        $mform->addRule('subject', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        $mform->addElement('editor', 'content', get_string('message', 'moodle'), null, [
            'context' => $this->get_context_for_dynamic_submission(),
            'maxfiles' => 0,
            'enable_filemanagement' => false,
            'autosave' => false,
        ]);
        $mform->setType('content', PARAM_RAW);

        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $fullurl = new \moodle_url('/local/mail/create.php', [
            'course' => $courseid,
            'recipients' => implode(',', $ids),
            'role' => $this->optional_param('role', '', PARAM_ALPHA) ?: (count($ids) > 1 ? 'bcc' : 'to'),
            'sesskey' => sesskey(),
        ]);
        $mform->addElement(
            'static',
            'openfull',
            '',
            $OUTPUT->action_link($fullurl, get_string('quickcomposeopenfull', 'local_mail')),
        );
    }

    /**
     * Validates the recipients with sender::check_recipients() and requires a non-empty subject,
     * the two checks sender::send() repeats at send time, and a subject of at most 100
     * characters, the limit the client-side rule shows.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors indexed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $user = user::current();
        try {
            sender::check_recipients($user, $this->get_course(), $this->get_recipient_ids($data['recipients'] ?? ''));
        } catch (exception $e) {
            $errors['recipientsummary'] = $e->getMessage();
        }

        $subjectlength = \core_text::strlen(trim($data['subject'] ?? ''));
        if (!$subjectlength) {
            $errors['subject'] = get_string('erroremptysubject', 'local_mail');
        } else if ($subjectlength > 100) {
            // A client-side rule never reaches the server, and message::update() would cut
            // the subject silently instead of telling the sender.
            $errors['subject'] = get_string('maximumchars', '', 100);
        }

        return $errors;
    }

    /**
     * Returns the course context the form works in.
     *
     * @return \context_course
     */
    protected function get_context_for_dynamic_submission(): \context {
        return $this->get_course()->get_context();
    }

    /**
     * Requires a user who can use mail in the course.
     *
     * @throws exception If mail is not installed or the user cannot use it in the course.
     */
    protected function check_access_for_dynamic_submission(): void {
        $user = user::current();
        if (!settings::is_installed()) {
            throw new exception('errorpluginnotinstalled');
        }
        $course = $this->get_course();
        if (!$user || !$user->can_use_mail($course)) {
            throw new exception('errorcoursenotfound', $course->id);
        }
    }

    /**
     * Fills the hidden fields from the arguments the modal was opened with.
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data([
            'courseid' => $this->get_course()->id,
            'recipients' => implode(',', $this->get_requested_ids()),
            'role' => $this->optional_param('role', '', PARAM_ALPHA) ?: null,
        ]);
    }

    /**
     * Creates the message and sends it in one transaction.
     *
     * @return array The id of the sent message, as `messageid`.
     */
    public function process_dynamic_submission(): array {
        global $DB;

        $formdata = $this->get_data();
        $user = user::current();
        $course = $this->get_course();
        $recipients = user::get_many($this->get_recipient_ids($formdata->recipients));
        $role = $formdata->role ?? '';
        if (!in_array($role, ['to', 'bcc'], true)) {
            $role = count($recipients) > 1 ? 'bcc' : 'to';
        }

        $data = message_data::new($course, $user);
        $data->$role = $recipients;
        $data->subject = $formdata->subject;
        $data->content = $formdata->content['text'];
        $data->format = (int) $formdata->content['format'];

        $transaction = $DB->start_delegated_transaction();
        try {
            $message = message::create($data);
            sender::send($user, $message);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return ['messageid' => $message->id];
    }

    /**
     * Returns the page the form belongs to when it is not in a modal.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->get_course()->id]);
    }

    /**
     * Returns the course of the form, from the submitted or the opening arguments.
     *
     * @return course
     * @throws exception If the course does not exist or is the site course.
     */
    private function get_course(): course {
        // Throws errorcoursenotfound for a missing course and for the site course alike.
        return course::get($this->optional_param('courseid', 0, PARAM_INT));
    }

    /**
     * Parses a comma-separated list of user ids, without the sender's own.
     *
     * @param string $recipients Comma-separated user ids.
     * @return int[]
     */
    private function get_recipient_ids(string $recipients): array {
        global $USER;

        $ids = array_filter(array_map('intval', explode(',', $recipients)));
        $ids = array_diff($ids, [(int) $USER->id]);

        return array_values(array_unique($ids));
    }

    /**
     * Returns the ids the modal was opened for, without the sender's own.
     *
     * @return int[]
     */
    private function get_requested_ids(): array {
        return $this->get_recipient_ids($this->optional_param('recipients', '', PARAM_SEQUENCE));
    }

    /**
     * Names the recipients the sender may mail, and only counts the others.
     *
     * The ids come from the request, so a name is shown only for a user the composer would list
     * to this sender ({@see user_search}); any other id, enrolled elsewhere or not at all, stays
     * a number.
     *
     * @param int[] $ids Requested recipient ids.
     * @return string HTML.
     */
    private function recipient_summary(array $ids): string {
        $sender = user::current();
        $reachable = [];
        if ($ids && $sender) {
            $search = new user_search($sender, $this->get_course());
            $search->include = $ids;
            $reachable = $search->get();
        }

        $override = has_capability('moodle/site:viewfullnames', $this->get_context_for_dynamic_submission());
        $parts = count($reachable) > 5
            ? [get_string('quickcomposerecipientcount', 'local_mail', count($reachable))]
            : array_map(fn(user $recipient) => $recipient->fullname($override), array_values($reachable));
        $unreachable = count($ids) - count($reachable);
        if ($unreachable) {
            $parts[] = get_string('quickcomposeunreachable', 'local_mail', $unreachable);
        }

        return implode(', ', array_map('s', $parts));
    }
}
