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

/**
 * @package    local-mail
 * @copyright  Albert Gasset <albert.gasset@gmail.com>
 * @copyright  Marc Català <reskit@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mail;

defined('MOODLE_INTERNAL') || die();

global $CFG;

abstract class testcase extends \advanced_testcase {

    public static function assert_index($userid, $type, $item, $time, $messageid, $unread) {
        self::assert_records('index', array(
            'userid' => $userid,
            'type' => $type,
            'item' => $item,
            'time' => $time,
            'messageid' => $messageid,
            'unread' => $unread,
        ));
    }

    public static function assert_not_index($userid, $type, $item, $message) {
        self::assert_not_records('index', array(
            'userid' => $userid,
            'type' => $type,
            'item' => $item,
            'messageid' => $message,
        ));
    }

    public static function assert_not_records($table, array $conditions = array()) {
        global $DB;
        self::assertFalse($DB->record_exists('local_mail_' . $table, $conditions));
    }

    public static function assert_records($table, array $conditions = array()) {
        global $DB;
        self::assertTrue($DB->record_exists('local_mail_' . $table, $conditions));
    }

    public static function load_records($table, $rows) {
        global $DB;
        $columns = array_shift($rows);
        foreach ($rows as $row) {
            $record = (object) array_combine($columns, $row);
            if (empty($record->id)) {
                $DB->insert_record($table, $record);
            } else {
                $DB->import_record($table, $record);
            }
        }
    }

    public function setUp(): void {
        $this->resetAfterTest(false);
    }

    public function tearDown(): void {
        global $DB;
        $DB->delete_records_select('course', 'id > 100');
        $DB->delete_records_select('user', 'id > 200');
        $DB->delete_records('local_mail_labels');
        $DB->delete_records('local_mail_messages');
        $DB->delete_records('local_mail_message_refs');
        $DB->delete_records('local_mail_message_users');
        $DB->delete_records('local_mail_message_labels');
        $DB->delete_records('local_mail_index');
    }
}
