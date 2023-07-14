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
        $record = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $grouping = $generator->create_grouping(['courseid' => $record->id]);
        $record->defaultgroupingid = $grouping->id;
        update_course($record);

        self::assertNull(course::fetch(0));

        $course = course::fetch($record->id);

        self::assertInstanceOf(course::class, $course);
        self::assertEquals((int) $record->id, $course->id);
        self::assertEquals($record->shortname, $course->shortname);
        self::assertEquals($record->fullname, $course->fullname);
        self::assertEquals((bool) $record->visible, $course->visible);
        self::assertEquals((int) $record->groupmode, $course->groupmode);
        self::assertEquals((int) $record->defaultgroupingid, $course->defaultgroupingid);
    }

    public function test_fetch_by_course() {
        $generator = self::getDataGenerator();
        $record1 = $generator->create_course();
        $record2 = $generator->create_course();
        $record3 = $generator->create_course();
        $record4 = $generator->create_course();
        $record5 = $generator->create_course(['visible' => false]);
        $record6 = $generator->create_course();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());

        $generator->enrol_user($user1->id, $record1->id);
        $generator->enrol_user($user1->id, $record2->id);
        $generator->enrol_user($user2->id, $record3->id);
        $generator->enrol_user($user1->id, $record4->id);
        $generator->enrol_user($user1->id, $record5->id);
        $generator->enrol_user($user1->id, $record6->id, 'guest');

        $courses = course::fetch_by_user($user1);

        self::assertEquals(course::fetch_many([$record4->id, $record2->id, $record1->id]), $courses);
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

    public function test_get_viewable_groups() {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course(['groupmode' => NOGROUPS]));
        $course2 = new course($generator->create_course(['groupmode' => VISIBLEGROUPS]));
        $course3 = new course($generator->create_course(['groupmode' => SEPARATEGROUPS]));
        $group1 = $generator->create_group(['courseid' => $course1->id]);
        $group2 = $generator->create_group(['courseid' => $course2->id]);
        $group3 = $generator->create_group(['courseid' => $course2->id]);
        $group4 = $generator->create_group(['courseid' => $course3->id]);
        $group5 = $generator->create_group(['courseid' => $course3->id]);

        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course1->id, 'student');
        $generator->enrol_user($user1->id, $course2->id, 'student');
        $generator->enrol_user($user1->id, $course3->id, 'student');
        $generator->enrol_user($user2->id, $course1->id, 'editingteacher');
        $generator->enrol_user($user2->id, $course2->id, 'editingteacher');
        $generator->enrol_user($user2->id, $course3->id, 'editingteacher');
        $generator->create_group_member(['userid' => $user1->id, 'groupid' => $group2->id]);
        $generator->create_group_member(['userid' => $user1->id, 'groupid' => $group4->id]);
        $generator->create_group_member(['userid' => $user2->id, 'groupid' => $group4->id]);

        // Student in course with no groups.
        self::assertEquals([], $course1->get_viewable_groups($user1));

        // Teacher in course with no groups.
        self::assertEquals([], $course1->get_viewable_groups($user2));

        // Student in course with visible groups.
        $expected = [$group2->id => $group2->name, $group3->id => $group3->name];
        self::assertEquals($expected, $course2->get_viewable_groups($user1));

        // Teacher in course with visible groups.
        $expected = [$group2->id => $group2->name, $group3->id => $group3->name];
        self::assertEquals($expected, $course2->get_viewable_groups($user2));

        // Student in course with separate groups.
        $expected = [$group4->id => $group4->name];
        self::assertEquals($expected, $course3->get_viewable_groups($user1));

        // Teacher in course with separate groups.
        $expected = [$group4->id => $group4->name, $group5->id => $group5->name];
        self::assertEquals($expected, $course3->get_viewable_groups($user2));
    }

    public function test_get_viewable_roles() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        self::setUser($user->id);

        $roles = get_roles_with_capability('local/mail:usemail');
        $expected = [];
        foreach (get_viewable_roles($course->context(), $user->id) as $roleid => $rolename) {
            if (isset($roles[$roleid])) {
                $expected[$roleid] = $rolename;
            }
        }

        self::assertEquals($expected, $course->get_viewable_roles($user));
    }
}
