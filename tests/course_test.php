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
 * Unit tests for fetching courses and their viewable groups and roles.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\course
 */
final class course_test extends test\testcase {
    public function test_get(): void {
        $generator = self::getDataGenerator();
        $record = $generator->create_course(['groupmode' => SEPARATEGROUPS]);

        $course = course::get($record->id);

        self::assertInstanceOf(course::class, $course);
        self::assertEquals((int) $record->id, $course->id);
        self::assertEquals((int) $record->category, $course->category);
        self::assertEquals($record->shortname, $course->shortname);
        self::assertEquals($record->fullname, $course->fullname);
        self::assertEquals((bool) $record->visible, $course->visible);
        self::assertEquals((int) $record->groupmode, $course->groupmode);
        self::assertFalse(course::cache()->get($course->id));

        // Missing course.
        try {
            course::get(123);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
            self::assertEquals(123, $e->a);
        }

        // Get from cache.
        $course = new course((object) [
            'id' => 123,
            'category' => 1,
            'shortname' => 'Course 123',
            'fullname' => 'Course 123',
            'visible' => '1',
            'groupmode' => NOGROUPS,
        ]);
        course::cache()->set($course->id, $course);
        self::assertEquals($course, course::get($course->id));
    }

    public function test_get_by_user(): void {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $course3 = new course($generator->create_course());
        $course4 = new course($generator->create_course());
        $course5 = new course($generator->create_course(['visible' => false]));
        $course6 = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course1->id);
        $generator->enrol_user($user1->id, $course2->id);
        $generator->enrol_user($user2->id, $course3->id);
        $generator->enrol_user($user1->id, $course4->id);
        $generator->enrol_user($user1->id, $course5->id);
        $generator->enrol_user($user1->id, $course6->id, 'guest');

        $result = course::get_by_user($user1);

        self::assert_array_of_objects([$course4, $course2, $course1], $result);
        self::assertEquals($user1->id, course::cache()->get('userid'));
        self::assertEquals([$course4->id, $course2->id, $course1->id], course::cache()->get('courseids'));
        self::assertEquals($course1, course::cache()->get($course1->id));
        self::assertEquals($course2, course::cache()->get($course2->id));
        self::assertEquals($course4, course::cache()->get($course4->id));
        self::assertFalse(course::cache()->has($course3->id));
        self::assertFalse(course::cache()->has($course5->id));
        self::assertFalse(course::cache()->has($course6->id));

        // The control for the hidden course: a teacher, who can see it, mails there.

        $generator->enrol_user($user2->id, $course5->id, 'editingteacher');

        self::assertArrayHasKey($course5->id, course::get_by_user($user2));

        // User with no courses.

        $result = course::get_by_user($user3);

        self::assertEquals([], $result);
        self::assertEquals($user3->id, course::cache()->get('userid'));
        self::assertEquals([], course::cache()->get('courseids'));
        self::assertFalse(course::cache()->has($course1->id));
        self::assertFalse(course::cache()->has($course2->id));
        self::assertFalse(course::cache()->has($course4->id));

        // Get from cache.

        delete_course($course1->id, false);
        delete_course($course2->id, false);
        course::cache()->set('userid', $user1->id);
        course::cache()->set('courseids', [$course1->id, $course2->id]);
        course::cache()->set($course1->id, $course1);
        course::cache()->set($course2->id, $course2);

        $result = course::get_by_user($user1);

        self::assert_array_of_objects([$course1, $course2], $result);
    }

    public function test_exists_for_user(): void {
        global $DB;

        $generator = self::getDataGenerator();
        $visible = $generator->create_course();
        $hidden = $generator->create_course(['visible' => false]);
        $other = $generator->create_course();
        $now = time();

        $cases = [];
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $visible->id);
        $cases['student'] = [$user, true];

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $hidden->id);
        $cases['student in a hidden course'] = [$user, false];

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $hidden->id, 'editingteacher');
        $cases['teacher in a hidden course'] = [$user, true];

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $visible->id, 'guest');
        $cases['role without mail'] = [$user, false];

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $visible->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $cases['suspended'] = [$user, false];

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $visible->id, 'student', 'manual', $now - 2 * DAYSECS, $now - DAYSECS);
        $cases['ended'] = [$user, false];

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $visible->id, 'student', 'manual', $now + DAYSECS);
        $cases['not started'] = [$user, false];

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $other->id, 'student', 'self');
        $cases['disabled instance'] = [$user, false];

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $visible->id, 'student', 'self');
        $cases['enabled instance'] = [$user, true];

        $user = $generator->create_user();
        $cases['no enrolments'] = [$user, false];

        $cases['guest'] = [guest_user(), false];

        // A site admin is judged by role, not by doanything: enrolled without mail roles, no mail.
        $admin = get_admin();
        $generator->enrol_user($admin->id, $other->id, 'guest');
        $cases['admin with a role without mail'] = [$admin, false];

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $other->id, 'enrol' => 'self']);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $visible->id, 'enrol' => 'self']);

        // Read only: usemail prohibited, viewmail kept.
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $context = \context_course::instance($visible->id);
        assign_capability('local/mail:usemail', CAP_PROHIBIT, $roleid, $context->id);
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $visible->id, 'teacher');
        $cases['viewmail only'] = [$user, true];

        foreach ($cases as $name => [$record, $expected]) {
            $user = new user($record);
            course::cache()->purge();
            self::assertSame($expected, course::exists_for_user($user), $name);
            // The oracle: the list the rest of the plugin uses.
            course::cache()->purge();
            self::assertSame($expected, (bool) course::get_by_user($user), "$name, get_by_user()");
        }
    }

    public function test_exists_for_user_probe(): void {
        global $DB;

        $generator = self::getDataGenerator();
        $courses = [];
        for ($i = 0; $i < course::PROBE_SIZE + 2; $i++) {
            $courses[] = $generator->create_course();
        }
        $first = reset($courses);
        $last = end($courses);

        // Mail only in the first course, only in the last one, and in none, among the same enrolments.
        $users = [];
        foreach (['first' => $first, 'last' => $last, 'none' => null] as $name => $mailcourse) {
            $users[$name] = new user($generator->create_user());
            foreach ($courses as $course) {
                $role = $course === $mailcourse ? 'student' : 'guest';
                $generator->enrol_user($users[$name]->id, $course->id, $role);
            }
        }

        // Measured as the logged-in user, as the navbar asks: for any other user, accesslib reads the
        // dirty-user flag on every capability check under CLI, which a web request does not.
        $reads = function (user $user) use ($DB): array {
            self::setUser($user->id);
            course::exists_for_user($user);
            course::cache()->purge();
            $before = $DB->perf_get_reads();
            $result = course::exists_for_user($user);
            return [$result, $DB->perf_get_reads() - $before];
        };

        // A qualifying course in the first page decides it with one query.
        self::assertSame([true, 1], $reads($users['first']));
        // The first query is bounded: a qualifying course after it costs a second one.
        self::assertSame([true, 2], $reads($users['last']));
        self::assertSame([false, 2], $reads($users['none']));

        // The probe writes nothing to the cache of courses.
        self::assertFalse(course::cache()->get('userid'));
        self::assertFalse(course::cache()->get('courseids'));
        self::assertFalse(course::cache()->has($first->id));

        // Once this request has loaded the user's courses, they answer with no query.
        course::get_by_user($users['first']);
        $before = $DB->perf_get_reads();
        self::assertTrue(course::exists_for_user($users['first']));
        self::assertSame(0, $DB->perf_get_reads() - $before);

        // ...but only for that user: the control, with the same request cache loaded.
        self::assertFalse(course::exists_for_user($users['none']));
        self::assertGreaterThan(0, $DB->perf_get_reads() - $before);
    }

    public function test_get_context(): void {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());

        self::assertEquals(\context_course::instance($course1->id), $course1->get_context());
        self::assertEquals(\context_course::instance($course2->id), $course2->get_context());
    }

    public function test_get_many(): void {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $course3 = new course($generator->create_course());

        $result = course::get_many([$course2->id, $course3->id, $course1->id, $course3->id]);

        self::assert_array_of_objects([$course2, $course3, $course1], $result);
        self::assertFalse(course::cache()->get($course1->id));
        self::assertFalse(course::cache()->get($course2->id));
        self::assertFalse(course::cache()->get($course3->id));

        // Missing course.
        try {
            course::get_many([$course1->id, 123, $course2->id]);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
            self::assertEquals(123, $e->a);
        }

        // No IDs.
        self::assertEquals([], course::get_many([]));

        // Get from cache.
        $course = new course((object) [
            'id' => 123,
            'category' => 1,
            'shortname' => 'Course 123',
            'fullname' => 'Course 123',
            'visible' => '1',
            'groupmode' => NOGROUPS,
        ]);
        course::cache()->set($course->id, $course);
        self::assert_array_of_objects([$course], course::get_many([$course->id]));
    }

    public function test_get_viewable_groups(): void {
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
        $generator->create_group_member(['userid' => $user1->id, 'groupid' => $group1->id]);
        $generator->create_group_member(['userid' => $user2->id, 'groupid' => $group1->id]);
        $generator->create_group_member(['userid' => $user1->id, 'groupid' => $group2->id]);
        $generator->create_group_member(['userid' => $user1->id, 'groupid' => $group4->id]);
        $generator->create_group_member(['userid' => $user2->id, 'groupid' => $group5->id]);

        // Student in course with no groups.
        self::assertSame([], $course1->get_viewable_groups($user1));

        // Teacher in course with no groups.
        self::assertSame([], $course1->get_viewable_groups($user2));

        // Student in course with visible groups.
        $expected = [
            0 => get_string('allgroups', 'local_mail'),
            $group2->id => $group2->name,
            $group3->id => $group3->name,
        ];
        self::assertSame($expected, $course2->get_viewable_groups($user1));

        // Teacher in course with visible groups.
        $expected = [
            0 => get_string('allgroups', 'local_mail'),
            $group2->id => $group2->name,
            $group3->id => $group3->name,
        ];
        self::assertSame($expected, $course2->get_viewable_groups($user2));

        // Student in course with separate groups.
        $expected = [$group4->id => $group4->name];
        self::assertSame($expected, $course3->get_viewable_groups($user1));

        // Teacher in course with separate groups (access all groups capability).
        $expected = [
            0 => get_string('allgroups', 'local_mail'),
            $group4->id => $group4->name,
            $group5->id => $group5->name,
        ];
        self::assertSame($expected, $course3->get_viewable_groups($user2));
    }

    public function test_get_viewable_roles(): void {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        self::setUser($user->id);

        $roles = get_roles_with_capability('local/mail:usemail');
        $expected = [];
        foreach (get_viewable_roles($course->get_context(), $user->id) as $roleid => $rolename) {
            if (isset($roles[$roleid])) {
                $expected[$roleid] = $rolename;
            }
        }

        self::assertSame($expected, $course->get_viewable_roles($user));
    }

    public function test_url(): void {
        global $CFG;

        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());

        self::assertEquals("$CFG->wwwroot/course/view.php?id={$course->id}", $course->url());
    }
}
