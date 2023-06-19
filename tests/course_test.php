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

/**
 * @covers \local_mail\course
 */
class course_test extends testcase {

    public function test_context() {
        $generator = self::getDataGenerator();
        $record1 = $generator->create_course();
        $record2 = $generator->create_course();

        $course1 = course::fetch($record1->id);
        $course2 = course::fetch($record2->id);

        self::assertEquals(\context_course::instance($record1->id), $course1->context());
        self::assertEquals(\context_course::instance($record2->id), $course2->context());
    }

    public function test_delete_messages() {
        $fs = get_file_storage();
        $generator = self::getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $context1 = \context_course::instance($course1->id);
        $context2 = \context_course::instance($course2->id);
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();

        list($labelid1, $labelid2) = self::insert_records(
            'labels',
            ['userid',   'name',    'color'],
            [$user1->id, 'Label 1', 'red'],
            [$user2->id, 'Label 2', 'blue'],
        );
        list($messageid1, $messageid2, $messageid3, $messageid4) = self::insert_records(
            'messages',
            ['courseid',   'subject',   'content',  'format', 'attachments', 'draft', 'time'],
            [$course1->id, 'Subject 1', 'Content 1', 0,        0,             0,       0],
            [$course1->id, 'Subject 2', 'Content 2', 0,        0,             0,       0],
            [$course2->id, 'Subject 3', 'Content 3', 0,        0,             0,       0],
            [$course2->id, 'Subject 4', 'Content 4', 0,        0,             0,       0],
        );
        self::insert_records(
            'message_refs',
            ['messageid', 'reference'],
            [$messageid1, $messageid2],
            [$messageid3, $messageid4],
        );
        $this->insert_records(
            'message_users',
            ['messageid', 'courseid',   'draft', 'time', 'userid',   'role', 'unread', 'starred',  'deleted'],
            [$messageid1,  $course1->id, 0,       0,      $user1->id, 0,      0,        0,          0],
            [$messageid2,  $course1->id, 0,       0,      $user2->id, 0,      0,        0,          0],
            [$messageid3,  $course2->id, 0,       0,      $user1->id, 0,      0,        0,          0],
            [$messageid4,  $course2->id, 0,       0,      $user2->id, 0,      0,        0,          0],
        );
        $this->insert_records(
            'message_labels',
            ['messageid', 'courseid',   'draft', 'time', 'labelid', 'role', 'unread', 'starred', 'deleted'],
            [$messageid1,  $course1->id, 0,       0,      $labelid1, 0,      0,        0,         0],
            [$messageid2,  $course1->id, 0,       0,      $labelid2, 0,      0,        0,         0],
            [$messageid3,  $course2->id, 0,       0,      $labelid1, 0,      0,        0,         0],
            [$messageid4,  $course2->id, 0,       0,      $labelid2, 0,      0,        0,         0],
        );

        self::create_attachment($course1->id, $messageid1, 'file1.txt', 'test');
        self::create_attachment($course2->id, $messageid3, 'file2.txt', 'test');

        course::delete_messages($course1->id);

        self::assert_record_count(0, 'messages', ['courseid' => $course1->id]);
        self::assert_record_count(2, 'messages', ['courseid' => $course2->id]);
        self::assert_record_count(0, 'message_refs', ['messageid' => $messageid1]);
        self::assert_record_count(1, 'message_refs', ['messageid' => $messageid3]);
        self::assert_record_count(0, 'message_users', ['courseid' => $course1->id]);
        self::assert_record_count(2, 'message_users', ['courseid' => $course2->id]);
        self::assert_record_count(0, 'message_labels', ['courseid' => $course1->id]);
        self::assert_record_count(2, 'message_labels', ['courseid' => $course2->id]);
        self::assertEmpty($fs->get_area_files($context1->id, 'local_mail', 'message'));
        self::assertNotEmpty($fs->get_area_files($context2->id, 'local_mail', 'message'));
    }

    public function test_fetch() {
        $generator = self::getDataGenerator();
        $record = $generator->create_course();

        self::assertNull(course::fetch(0));

        $course = course::fetch($record->id);

        self::assertInstanceOf(course::class, $course);
        self::assertEquals((int) $record->id, $course->id);
        self::assertEquals($record->shortname, $course->shortname);
        self::assertEquals($record->fullname, $course->fullname);
        self::assertEquals((bool) $record->visible, $course->visible);
        self::assertEquals((int) $record->groupmode, $course->groupmode);
    }

    public function test_fetch_many() {
        $generator = self::getDataGenerator();
        $record1 = $generator->create_course();
        $record2 = $generator->create_course();
        $record3 = $generator->create_course();

        self::assertEquals([], course::fetch_many([]));

        $courses = course::fetch_many([$record1->id, 0, $record2->id, $record1->id, $record3->id]);

        self::assertIsArray($courses);
        self::assertEquals([$record3->id, $record2->id, $record1->id], array_keys($courses));
        self::assertEquals(course::fetch($record1->id), $courses[$record1->id]);
        self::assertEquals(course::fetch($record2->id), $courses[$record2->id]);
        self::assertEquals(course::fetch($record3->id), $courses[$record3->id]);
    }
}
