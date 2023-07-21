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

namespace local_mail;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/testcase.php');
require_once(__DIR__ . '/message_search_test.php');

/**
 * @covers \local_mail\observer
 */
class observer_test extends testcase {

    public function test_course_deleted() {
        list($users, $messages) = message_search_test::generate_data();
        $course = $messages[0]->course;
        $context = $course->context();

        $fs = get_file_storage();

        delete_course($course->id, false);

        self::assert_record_count(0, 'messages', ['courseid' => $course->id]);
        self::assert_record_count(0, 'message_users', ['courseid' => $course->id]);
        self::assert_record_count(0, 'message_labels', ['courseid' => $course->id]);
        foreach ($messages as $message) {
            if ($message->course->id == $course->id) {
                self::assert_record_count(0, 'message_refs', ['messageid' => $message->id]);
                self::assert_record_count(0, 'message_refs', ['reference' => $message->id]);
            } else {
                self::assert_message($message);
            }
        }
        self::assertEmpty($fs->get_area_files($context->id, 'local_mail', 'message'));
    }
}
