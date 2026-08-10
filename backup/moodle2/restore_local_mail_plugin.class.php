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
 * SPDX-FileCopyrightText: 2016 Albert Gasset <albertgasset@fsfe.org>
 * SPDX-FileCopyrightText: 2017 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Restores the messages of a course from its backup file.
 *
 * @package    local_mail
 * @copyright  2016-2024 Albert Gasset, Marc Català, Proyecto UNIMOODLE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_mail_plugin extends restore_local_plugin {
    /**
     * Declares the paths of the backup file that are processed by this plugin.
     *
     * @return restore_path_element[] Paths to process, empty if backups are disabled or user data is not included.
     */
    protected function define_course_plugin_structure() {
        if (!get_config('local_mail', 'enablebackup')) {
            return [];
        }

        if (!$this->get_setting_value('users')) {
            return [];
        }

        return [
            new restore_path_element('local_mail_message', $this->get_pathfor('/messages/message')),
            new restore_path_element('local_mail_message_ref', $this->get_pathfor('/messages/message/refs/ref')),
            new restore_path_element('local_mail_message_user', $this->get_pathfor('/messages/message/users/user')),
            new restore_path_element('local_mail_message_label', $this->get_pathfor('/messages/message/labels/label')),
        ];
    }

    /**
     * Inserts a restored message and maps its old identifier to the new one.
     *
     * @param array $data Contents of the message element of the backup file.
     * @return void
     */
    public function process_local_mail_message($data) {
        global $DB;

        $record = new \stdClass();
        $record->courseid = $this->get_mappingid('course', $data['courseid']);
        $record->subject = $data['subject'];
        $record->content = $data['content'];
        $record->format = $data['format'];
        $record->attachments = $data['attachments'];
        $record->draft = $data['draft'];
        $record->time = $this->apply_date_offset($data['time']);
        $record->normalizedsubject = \local_mail\message::normalize_text($data['subject'], FORMAT_PLAIN);
        $record->normalizedcontent = \local_mail\message::normalize_text($data['content'], $data['format']);
        $newid = $DB->insert_record('local_mail_messages', $record);

        $this->set_mapping('local_mail_message', $data['id'], $newid, true);
    }

    /**
     * Inserts one reference of a restored message: a link to an earlier message in the same
     * conversation, not necessarily the one it answers directly.
     *
     * @param array $data Contents of the ref element of the backup file.
     * @return void
     */
    public function process_local_mail_message_ref($data) {
        global $DB;

        $record = new \stdClass();
        $record->messageid = $this->get_new_parentid('local_mail_message');
        $record->reference = $this->get_mappingid('local_mail_message', $data['reference']);
        $DB->insert_record('local_mail_message_refs', $record);
    }

    /**
     * Inserts a sender or recipient of a restored message, converting the role name back to its number.
     *
     * @param array $data Contents of the user element of the backup file.
     * @return void
     */
    public function process_local_mail_message_user($data) {
        global $DB;

        $roles = array_flip(\local_mail\message::role_names());

        $messageid = $this->get_new_parentid('local_mail_message');
        $userid = $this->get_mappingid('user', $data['userid']);
        $message = $DB->get_record('local_mail_messages', ['id' => $messageid], '*', MUST_EXIST);

        $record = new \stdClass();
        $record->messageid = $message->id;
        $record->courseid = $message->courseid;
        $record->draft = $message->draft;
        $record->time = $message->time;
        $record->userid = $userid;
        $record->role = isset($roles[$data['role']]) ? $roles[$data['role']] : 0;
        $record->unread = $data['unread'];
        $record->starred = $data['starred'];
        $record->deleted = $data['deleted'];
        $DB->insert_record('local_mail_message_users', $record);
    }

    /**
     * Assigns a restored message to a label of the user, creating the label if it does not exist yet.
     *
     * @param array $data Contents of the label element of the backup file.
     * @return void
     */
    public function process_local_mail_message_label($data) {
        global $DB;

        $messageid = $this->get_new_parentid('local_mail_message');
        $userid = $this->get_mappingid('user', $data['userid']);
        $conditions = ['userid' => $userid, 'name' => $data['name']];
        $labelid = $DB->get_field('local_mail_labels', 'id', $conditions);
        $conditions = ['messageid' => $messageid, 'userid' => $userid];
        $messageuser = $DB->get_record('local_mail_message_users', $conditions, '*', MUST_EXIST);

        if (!$labelid) {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->name = $data['name'];
            $record->color = $data['color'];
            $labelid = $DB->insert_record('local_mail_labels', $record);
        }

        $record = new \stdClass();
        $record->messageid = $messageid;
        $record->courseid = $messageuser->courseid;
        $record->draft = $messageuser->draft;
        $record->time = $messageuser->time;
        $record->labelid = $labelid;
        $record->role = $messageuser->role;
        $record->unread = $messageuser->unread;
        $record->starred = $messageuser->starred;
        $record->deleted = $messageuser->deleted;
        $DB->insert_record('local_mail_message_labels', $record);
    }

    /**
     * Restores the files attached to the messages, once all the messages of the course have been inserted.
     *
     * @return void
     */
    protected function after_execute_course() {
        $this->add_related_files('local_mail', 'message', 'local_mail_message');
    }
}
