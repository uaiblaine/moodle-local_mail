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

class restore_local_mail_plugin extends restore_local_plugin {

    protected function define_course_plugin_structure() {
        if (!get_config('local_mail', 'enablebackup')) {
            return array();
        }

        if (!$this->get_setting_value('users')) {
            return array();
        }

        $elements = array(
            'local_mail_message' => '/messages/message',
            'local_mail_message_ref' => '/messages/message/refs/ref',
            'local_mail_message_user' => '/messages/message/users/user',
            'local_mail_message_label' => '/messages/message/labels/label',
        );

        $paths = array();
        foreach ($elements as $name => $path) {
            $paths[] = new restore_path_element($name, $this->get_pathfor($path));
        }
        return $paths;
    }

    public function process_local_mail_message($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->courseid = $this->get_mappingid('course', $data->courseid);
        $data->time = $this->apply_date_offset($data->time);

        $newid = $DB->insert_record('local_mail_messages', $data);

        $this->set_mapping('local_mail_message', $oldid, $newid, true);
    }

    public function process_local_mail_message_ref($data) {
        global $DB;

        $data = (object) $data;

        $data->messageid = $this->get_new_parentid('local_mail_message');
        $data->reference = $this->get_mappingid('local_mail_message', $data->reference);

        $DB->insert_record('local_mail_message_refs', $data);
    }

    public function process_local_mail_message_user($data) {
        global $DB;

        $data = (object) $data;
        $data->messageid = $this->get_new_parentid('local_mail_message');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('local_mail_message_users', $data);
    }

    public function process_local_mail_message_label($data) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $data = (object) $data;

        $data->userid = $this->get_mappingid('user', $data->userid);

        $conditions = array('userid' => $data->userid, 'name' => $data->name);
        $labelid = $DB->get_field('local_mail_labels', 'id', $conditions);
        if (!$labelid) {
            $labelid = $DB->insert_record('local_mail_labels', $data);
        }

        $conditions = array('id' => $this->get_new_parentid('local_mail_message'));
        $message = $DB->get_record('local_mail_messages', $conditions, '*', MUST_EXIST);

        $DB->insert_record('local_mail_message_labels', array('messageid' => $message->id, 'labelid' => $labelid));

        $transaction->allow_commit();
    }

    protected function after_execute_course() {
        $this->add_related_files('local_mail', 'message', 'local_mail_message');
    }
}
