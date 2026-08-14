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
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024-2025 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail;

/**
 * Unit tests for the database upgrade steps of the plugin.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \xmldb_local_mail_upgrade
 */
final class upgrade_test extends test\testcase {
    public function setUp(): void {
        global $CFG;

        parent::setUp();

        require_once("$CFG->libdir/upgradelib.php");
        require_once("$CFG->dirroot/local/mail/db/upgrade.php");
    }

    public function test_upgrade(): void {
        global $CFG, $DB;

        $dbman = $DB->get_manager();
        $fs = get_file_storage();

        // Create original database schema.

        $dbman->delete_tables_from_xmldb_file("$CFG->dirroot/local/mail/db/install.xml");
        $dbman->install_from_xmldb_file("$CFG->dirroot/local/mail/tests/upgrade_test.xml");

        // Set message processor settings.

        set_config('message_provider_local_mail_mail_enabled', 'popup,email', 'message');
        set_config('popup_provider_local_mail_mail_locked', '0', 'message');

        // Add some data.

        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $labelid1 = $DB->insert_record('local_mail_labels', ['userid' => $user1->id, 'name' => 'Label 1']);
        $labelid2 = $DB->insert_record('local_mail_labels', ['userid' => $user2->id, 'name' => 'Label 2']);
        $labelid3 = $DB->insert_record('local_mail_labels', ['userid' => $user3->id, 'name' => 'Label 3']);
        $labelid4 = $DB->insert_record('local_mail_labels', ['userid' => $user4->id, 'name' => 'Label 4']);
        $messageid1 = $DB->insert_record('local_mail_messages', [
            'courseid' => $course1->id,
            'subject' => ' Subject    1  ',
            'content' => '  <p> Content     of message 1 </p> ',
            'format' => FORMAT_HTML,
            'draft' => 0,
            'time' => 2000000001,
        ]);
        $DB->insert_record('local_mail_message_users', [
            'messageid' => $messageid1,
            'userid' => $user1->id,
            'role' => 'from',
            'unread' => 0,
            'starred' => 0,
            'deleted' => 0,
        ]);
        $DB->insert_record('local_mail_message_users', [
            'messageid' => $messageid1,
            'userid' => $user2->id,
            'role' => 'to',
            'unread' => 0,
            'starred' => 1,
            'deleted' => 0,
        ]);
        $DB->insert_record('local_mail_message_users', [
            'messageid' => $messageid1,
            'userid' => $user3->id,
            'role' => 'cc',
            'unread' => 0,
            'starred' => 0,
            'deleted' => 1,
        ]);
        $DB->insert_record('local_mail_message_users', [
            'messageid' => $messageid1,
            'userid' => $user4->id,
            'role' => 'bcc',
            'unread' => 1,
            'starred' => 0,
            'deleted' => 0,
        ]);
        $DB->insert_record('local_mail_message_labels', [
            'messageid' => $messageid1,
            'labelid' => $labelid1,
        ]);
        $DB->insert_record('local_mail_message_labels', [
            'messageid' => $messageid1,
            'labelid' => $labelid2,
        ]);
        $DB->insert_record('local_mail_message_labels', [
            'messageid' => $messageid1,
            'labelid' => $labelid3,
        ]);
        $DB->insert_record('local_mail_message_labels', [
            'messageid' => $messageid1,
            'labelid' => $labelid4,
        ]);
        $fs->create_file_from_string([
            'contextid' => \context_course::instance($course1->id)->id,
            'component' => 'local_mail',
            'filearea' => 'message',
            'itemid' => $messageid1,
            'filepath' => '/',
            'filename' => 'file1.txt',
        ], 'file 1');
        $fs->create_file_from_string([
            'contextid' => \context_course::instance($course1->id)->id,
            'component' => 'local_mail',
            'filearea' => 'message',
            'itemid' => $messageid1,
            'filepath' => '/',
            'filename' => 'file2.txt',
        ], 'file 2');
        $messageid2 = $DB->insert_record('local_mail_messages', [
            'courseid' => $course1->id,
            'subject' => ' Subject    2  ',
            'content' => '  <p> Content     of message 2 </p> ',
            'format' => FORMAT_HTML,
            'draft' => 1,
            'time' => 2000000002,
        ]);
        $DB->insert_record('local_mail_message_refs', [
            'messageid' => $messageid2,
            'reference' => $messageid1,
        ]);
        $DB->insert_record('local_mail_message_users', [
            'messageid' => $messageid2,
            'userid' => $user2->id,
            'role' => 'from',
            'unread' => 0,
            'starred' => 0,
            'deleted' => 0,
        ]);
        $DB->insert_record('local_mail_message_users', [
            'messageid' => $messageid2,
            'userid' => $user1->id,
            'role' => 'to',
            'unread' => 1,
            'starred' => 0,
            'deleted' => 0,
        ]);
        $DB->insert_record('local_mail_message_labels', [
            'messageid' => $messageid2,
            'labelid' => $labelid2,
        ]);
        $messageid3 = $DB->insert_record('local_mail_messages', [
            'courseid' => $course2->id,
            'subject' => ' Subject    3  ',
            'content' => '  <p> Content     of message 3 </p> ',
            'format' => FORMAT_HTML,
            'draft' => 0,
            'time' => 2000000003,
        ]);
        $DB->insert_record('local_mail_message_refs', [
            'messageid' => $messageid3,
            'reference' => $messageid1,
        ]);
        $DB->insert_record('local_mail_message_refs', [
            'messageid' => $messageid3,
            'reference' => $messageid2,
        ]);
        $DB->insert_record('local_mail_message_users', [
            'messageid' => $messageid3,
            'userid' => $user1->id,
            'role' => 'from',
            'unread' => 0,
            'starred' => 0,
            'deleted' => 1,
        ]);
        $DB->insert_record('local_mail_message_users', [
            'messageid' => $messageid3,
            'userid' => $user3->id,
            'role' => 'to',
            'unread' => 0,
            'starred' => 1,
            'deleted' => 0,
        ]);
        $DB->insert_record('local_mail_message_labels', [
            'messageid' => $messageid3,
            'labelid' => $labelid1,
        ]);
        $DB->insert_record('local_mail_message_labels', [
            'messageid' => $messageid3,
            'labelid' => $labelid3,
        ]);
        $fs->create_file_from_string([
            'contextid' => \context_course::instance($course2->id)->id,
            'component' => 'local_mail',
            'filearea' => 'message',
            'itemid' => $messageid3,
            'filepath' => '/',
            'filename' => 'file1.txt',
        ], 'file 1');

        // Run upgrade.

        set_config('version', 2013121100, 'local_mail');
        xmldb_local_mail_upgrade(2013121100);

        // Check upgraded schema.

        $xmldbfile = new \xmldb_file("$CFG->dirroot/local/mail/db/install.xml");
        $xmldbfile->loadXMLStructure();

        /*
         * The return value has to be asserted: check_database_schema() collects its
         * findings into an array and never throws, so calling it bare made this check
         * vacuous and a column added to install.xml without a matching upgrade step
         * used to pass. Only this plugin's tables are compared, so extratables is
         * turned off — the method unsets the tables of the structure it is given and
         * reports every remaining table in the database, which here means all of core.
         */
        $options = ['extratables' => false];
        $errors = $dbman->check_database_schema($xmldbfile->getStructure(), $options);
        self::assertEquals([], $errors);

        // Check web message processor is disabled.

        self::assertEquals('email', get_config('message', 'message_provider_local_mail_mail_enabled'));
        self::assertEquals('1', get_config('message', 'popup_provider_local_mail_mail_locked'));

        // Check upgraded data.

        self::assert_record_data('messages', ['id' => $messageid1], [
            'courseid' => $course1->id,
            'subject' => ' Subject    1  ',
            'content' => '  <p> Content     of message 1 </p> ',
            'format' => FORMAT_HTML,
            'attachments' => 2,
            'draft' => 0,
            'time' => 2000000001,
            'normalizedsubject' => 'Subject 1',
            'normalizedcontent' => 'Content of message 1',
        ]);
        self::assert_record_data('message_users', ['messageid' => $messageid1, 'userid' => $user1->id], [
            'courseid' => $course1->id,
            'draft' => 0,
            'time' => 2000000001,
            'role' => 1,
            'unread' => 0,
            'starred' => 0,
            'deleted' => 0,
        ]);
        self::assert_record_data('message_users', ['messageid' => $messageid1, 'userid' => $user2->id], [
            'courseid' => $course1->id,
            'draft' => 0,
            'time' => 2000000001,
            'role' => 2,
            'unread' => 0,
            'starred' => 1,
            'deleted' => 0,
        ]);
        self::assert_record_data('message_users', ['messageid' => $messageid1, 'userid' => $user3->id], [
            'courseid' => $course1->id,
            'draft' => 0,
            'time' => 2000000001,
            'role' => 3,
            'unread' => 0,
            'starred' => 0,
            'deleted' => 1,
        ]);
        self::assert_record_data('message_users', ['messageid' => $messageid1, 'userid' => $user4->id], [
            'courseid' => $course1->id,
            'draft' => 0,
            'time' => 2000000001,
            'role' => 4,
            'unread' => 1,
            'starred' => 0,
            'deleted' => 0,
        ]);
        self::assert_record_data('message_labels', ['messageid' => $messageid1, 'labelid' => $labelid1], [
            'courseid' => $course1->id,
            'draft' => 0,
            'time' => 2000000001,
            'role' => 1,
            'unread' => 0,
            'starred' => 0,
            'deleted' => 0,
        ]);
        self::assert_record_data('message_labels', ['messageid' => $messageid1, 'labelid' => $labelid2], [
            'courseid' => $course1->id,
            'draft' => 0,
            'time' => 2000000001,
            'role' => 2,
            'unread' => 0,
            'starred' => 1,
            'deleted' => 0,
        ]);
        self::assert_record_data('message_labels', ['messageid' => $messageid1, 'labelid' => $labelid3], [
            'courseid' => $course1->id,
            'draft' => 0,
            'time' => 2000000001,
            'role' => 3,
            'unread' => 0,
            'starred' => 0,
            'deleted' => 1,
        ]);
        self::assert_record_data('message_labels', ['messageid' => $messageid1, 'labelid' => $labelid4], [
            'courseid' => $course1->id,
            'draft' => 0,
            'time' => 2000000001,
            'role' => 4,
            'unread' => 1,
            'starred' => 0,
            'deleted' => 0,
        ]);
        self::assert_record_data('messages', ['id' => $messageid2], [
            'courseid' => $course1->id,
            'subject' => ' Subject    2  ',
            'content' => '  <p> Content     of message 2 </p> ',
            'format' => FORMAT_HTML,
            'attachments' => 0,
            'draft' => 1,
            'time' => 2000000002,
            'normalizedsubject' => 'Subject 2',
            'normalizedcontent' => 'Content of message 2',
        ]);
        self::assert_record_data('message_refs', ['messageid' => $messageid2, 'reference' => $messageid1], []);
        self::assert_record_data('message_users', ['messageid' => $messageid2, 'userid' => $user2->id], [
            'courseid' => $course1->id,
            'draft' => 1,
            'time' => 2000000002,
            'role' => 1,
            'unread' => 0,
            'starred' => 0,
            'deleted' => 0,
        ]);
        self::assert_record_data('message_users', ['messageid' => $messageid2, 'userid' => $user1->id], [
            'courseid' => $course1->id,
            'draft' => 1,
            'time' => 2000000002,
            'role' => 2,
            'unread' => 1,
            'starred' => 0,
            'deleted' => 0,
        ]);
        self::assert_record_data('message_labels', ['messageid' => $messageid2, 'labelid' => $labelid2], [
            'courseid' => $course1->id,
            'draft' => 1,
            'time' => 2000000002,
            'role' => 1,
            'unread' => 0,
            'starred' => 0,
            'deleted' => 0,
        ]);
        self::assert_record_data('messages', ['id' => $messageid3], [
            'courseid' => $course2->id,
            'subject' => ' Subject    3  ',
            'content' => '  <p> Content     of message 3 </p> ',
            'format' => FORMAT_HTML,
            'attachments' => 1,
            'draft' => 0,
            'time' => 2000000003,
            'normalizedsubject' => 'Subject 3',
            'normalizedcontent' => 'Content of message 3',
        ]);
        self::assert_record_count(0, 'message_refs', ['messageid' => $messageid3]);
        self::assert_record_data('message_users', ['messageid' => $messageid3, 'userid' => $user1->id], [
            'courseid' => $course2->id,
            'draft' => 0,
            'time' => 2000000003,
            'role' => 1,
            'unread' => 0,
            'starred' => 0,
            'deleted' => 1,
        ]);
        self::assert_record_data('message_users', ['messageid' => $messageid3, 'userid' => $user3->id], [
            'courseid' => $course2->id,
            'draft' => 0,
            'time' => 2000000003,
            'role' => 2,
            'unread' => 0,
            'starred' => 1,
            'deleted' => 0,
        ]);
        self::assert_record_data('message_labels', ['messageid' => $messageid3, 'labelid' => $labelid1], [
            'courseid' => $course2->id,
            'draft' => 0,
            'time' => 2000000003,
            'role' => 1,
            'unread' => 0,
            'starred' => 0,
            'deleted' => 1,
        ]);
        self::assert_record_data('message_labels', ['messageid' => $messageid3, 'labelid' => $labelid3], [
            'courseid' => $course2->id,
            'draft' => 0,
            'time' => 2000000003,
            'role' => 2,
            'unread' => 0,
            'starred' => 1,
            'deleted' => 0,
        ]);
    }
}
