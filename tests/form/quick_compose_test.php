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

use local_mail\exception;
use local_mail\message;

/**
 * Tests for the quick-compose modal form.
 *
 * Test metadata stays in doc-comments while the plugin supports 4.5 (see CLAUDE.md).
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\form\quick_compose
 */
final class quick_compose_test extends \local_mail\test\testcase {
    public function test_submit_sends(): void {
        global $DB;

        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);
        $notificationsink = $this->redirectMessages();

        $form = self::submit([
            'courseid' => $course->id,
            'recipients' => $u['tutora']->id . ',' . $u['studenta1']->id,
            'role' => 'to',
            'subject' => 'Question',
            'content' => ['text' => '<p>Hello</p>', 'format' => FORMAT_HTML],
        ]);
        self::assertTrue($form->is_validated());
        $result = $form->process_dynamic_submission();

        $message = message::get($result['messageid']);
        self::assertFalse($message->draft);
        self::assertEquals($course->id, $message->course->id);
        self::assertEquals('Question', $message->subject);
        self::assertEquals('<p>Hello</p>', $message->content);
        // The sender's own id is dropped from the recipients.
        self::assertEquals([$u['tutora']->id], array_column($message->recipients(message::ROLE_TO), 'id'));
        self::assertTrue($message->unread($u['tutora']));
        self::assertCount(1, $notificationsink->get_messages());
        self::assertEquals(1, $DB->count_records('local_mail_messages'));
    }

    public function test_role(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['teacher']->id);
        $recipients = $u['studenta1']->id . ',' . $u['studentb']->id;

        $cases = [
            // Several recipients go to Bcc unless To is chosen.
            [$recipients, 'bcc', message::ROLE_BCC],
            [$recipients, 'to', message::ROLE_TO],
            [$recipients, '', message::ROLE_BCC],
            // One recipient goes to To.
            [(string) $u['studenta1']->id, '', message::ROLE_TO],
        ];
        foreach ($cases as [$ids, $role, $expected]) {
            $form = self::submit([
                'courseid' => $course->id,
                'recipients' => $ids,
                'role' => $role,
                'subject' => 'Notice',
                'content' => ['text' => 'Text', 'format' => FORMAT_HTML],
            ]);
            self::assertTrue($form->is_validated(), "$ids $role");
            $message = message::get($form->process_dynamic_submission()['messageid']);
            self::assertEqualsCanonicalizing(
                array_map('intval', explode(',', $ids)),
                array_column($message->recipients($expected), 'id'),
                "$ids $role",
            );
        }
    }

    public function test_rejects_recipient_in_another_group(): void {
        global $DB;

        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);

        $form = self::submit([
            'courseid' => $course->id,
            'recipients' => $u['tutora']->id . ',' . $u['studentb']->id,
            'role' => 'to',
            'subject' => 'Question',
            'content' => ['text' => 'Hello', 'format' => FORMAT_HTML],
        ]);

        self::assertFalse($form->is_validated());
        self::assertEquals(0, $DB->count_records('local_mail_messages'));
        self::assertEquals(0, $DB->count_records('local_mail_message_users'));
    }

    public function test_names_only_reachable_recipients(): void {
        global $DB;

        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        $DB->set_field('user', 'firstname', 'Reachable', ['id' => $u['tutora']->id]);
        $DB->set_field('user', 'firstname', 'Hidden', ['id' => $u['studentb']->id]);
        $outsider = $this->getDataGenerator()->create_user(['firstname' => 'Outsider']);
        self::setUser($u['studenta1']->id);

        $form = self::submit([
            'courseid' => $course->id,
            'recipients' => implode(',', [$u['tutora']->id, $u['studentb']->id, $outsider->id]),
        ]);
        $html = $form->render();

        // The control: a recipient the sender may mail is named.
        self::assertStringContainsString('Reachable', $html);
        // A user of another group, or of no course of the sender's, stays a number.
        self::assertStringNotContainsString('Hidden', $html);
        self::assertStringNotContainsString('Outsider', $html);
        self::assertStringContainsString(get_string('quickcomposeunreachable', 'local_mail', 2), $html);
    }

    public function test_failed_send_leaves_nothing(): void {
        global $DB;

        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);
        $notificationsink = $this->redirectMessages();

        $form = self::submit([
            'courseid' => $course->id,
            'recipients' => $u['tutora']->id . ',' . $u['teacher']->id,
            'role' => 'to',
            'subject' => 'Question',
            'content' => ['text' => 'Hello', 'format' => FORMAT_HTML],
        ]);
        self::assertTrue($form->is_validated());

        // The limit drops between validation and sending, so the send itself fails.
        set_config('maxrecipients', 1, 'local_mail');
        try {
            $form->process_dynamic_submission();
            self::fail('No exception from the send');
        } catch (exception $e) {
            self::assertEquals('errortoomanyrecipients', $e->errorcode);
        }

        self::assertEquals(0, $DB->count_records('local_mail_messages'));
        self::assertEquals(0, $DB->count_records('local_mail_message_users'));
        self::assertCount(0, $notificationsink->get_messages());
    }

    public function test_rejects_empty_subject(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);

        $data = [
            'courseid' => $course->id,
            'recipients' => (string) $u['tutora']->id,
            'role' => 'to',
            'subject' => 'Question',
            'content' => ['text' => 'Hello', 'format' => FORMAT_HTML],
        ];
        // The control: the same submission with a subject validates.
        self::assertTrue(self::submit($data)->is_validated());

        $data['subject'] = '   ';
        self::assertFalse(self::submit($data)->is_validated());
    }

    public function test_rejects_long_subject(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);

        $data = [
            'courseid' => $course->id,
            'recipients' => (string) $u['tutora']->id,
            'role' => 'to',
            'subject' => str_repeat('é', 100),
            'content' => ['text' => 'Hello', 'format' => FORMAT_HTML],
        ];
        // The control: 100 characters, multibyte ones included, validate.
        self::assertTrue(self::submit($data)->is_validated());

        $data['subject'] .= 'x';
        $form = self::submit($data);
        self::assertFalse($form->is_validated());
        self::assertEquals(
            ['subject' => get_string('maximumchars', '', 100)],
            $form->validation((array) $form->get_submitted_data() + $data, [])
        );
    }

    public function test_access(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        $generator = $this->getDataGenerator();
        $outsider = new \local_mail\user($generator->create_user());
        $nomail = new \local_mail\user($generator->create_user());
        $generator->enrol_user($nomail->id, $course->id, $generator->create_role());

        $cases = [
            'site course' => [$u['studenta1'], SITEID, 'errorcoursenotfound'],
            'missing course' => [$u['studenta1'], -1, 'errorcoursenotfound'],
            'not enrolled' => [$outsider, $course->id, 'requireloginerror'],
            'enrolled without local/mail:usemail' => [$nomail, $course->id, 'errorcoursenotfound'],
        ];
        foreach ($cases as $name => [$user, $courseid, $error]) {
            self::setUser($user->id);
            try {
                self::submit(['courseid' => $courseid, 'recipients' => (string) $u['tutora']->id]);
                self::fail("No exception for: $name");
            } catch (\moodle_exception $e) {
                self::assertEquals($error, $e->errorcode, $name);
            }
        }

        // The control: an enrolled user opens the form in the course.
        self::setUser($u['studenta1']->id);
        self::submit(['courseid' => $course->id, 'recipients' => (string) $u['tutora']->id]);
    }

    /**
     * Builds the form as the dynamic form service does for a submission.
     *
     * @param array $data Submitted data.
     * @return quick_compose
     */
    private static function submit(array $data): quick_compose {
        $data = quick_compose::mock_ajax_submit($data);
        $form = new quick_compose(null, null, 'post', '', null, true, $data, true);
        $form->set_data_for_dynamic_submission();
        return $form;
    }
}
