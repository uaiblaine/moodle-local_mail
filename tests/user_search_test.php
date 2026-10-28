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
 * SPDX-FileCopyrightText: 2024-2026 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail;

/**
 * Unit tests for the query that searches users available as message recipients.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\user_search
 */
final class user_search_test extends test\testcase {
    public function test_count(): void {
        $data = self::generate_data();

        foreach (self::user_search_cases($data) as [$search, $expected]) {
            self::assertEquals(count($expected), $search->count(), $search);
        }
    }

    public function test_get(): void {
        $data = self::generate_data();

        foreach (self::user_search_cases($data) as [$search, $expected]) {
            $result = $search->get();
            self::assert_array_of_objects($expected, $result, $search);

            // Offset and limit.
            $expected = array_slice($expected, 1, 2, true);
            $result = $search->get(1, 2);
            self::assert_array_of_objects($expected, $result, $search);
        }
    }

    public function test_separate_groups(): void {
        ['course' => $course, 'groups' => $groups, 'users' => $u] = self::generate_groups_data();

        $cases = [
            // A student reaches their own group and whoever sees every group, not the other group.
            [$u['studenta1'], 0, [$u['studenta2'], $u['tutora'], $u['teacher']]],
            [$u['studentb'], 0, [$u['tutorb'], $u['teacher']]],
            // A user in no group reaches only those who see every group.
            [$u['studentnone'], 0, [$u['teacher']]],
            // A tutor limited to a group is limited the same way.
            [$u['tutora'], 0, [$u['studenta1'], $u['studenta2'], $u['teacher']]],
            // Whoever sees every group reaches everybody.
            [
                $u['teacher'],
                0,
                [$u['tutora'], $u['tutorb'], $u['studenta1'], $u['studenta2'], $u['studentb'], $u['studentnone']],
            ],
            // Filtering by a group of one's own keeps the users who see every group.
            [$u['studenta1'], $groups['a']->id, [$u['studenta2'], $u['tutora'], $u['teacher']]],
            // An unrestricted sender filtering by a group gets that group's members only.
            [$u['teacher'], $groups['a']->id, [$u['tutora'], $u['studenta1'], $u['studenta2']]],
        ];

        foreach ($cases as [$sender, $groupid, $expected]) {
            $search = new user_search($sender, $course);
            $search->groupid = $groupid;
            self::assertEqualsCanonicalizing(array_column($expected, 'id'), array_keys($search->get()), $search);
            self::assertEquals(count($expected), $search->count(), $search);
        }

        // Recipient ids from outside the composer go through the same rule.
        $search = new user_search($u['studenta1'], $course);
        $search->include = [$u['studentb']->id, $u['tutorb']->id, $u['tutora']->id];
        self::assertEquals([$u['tutora']->id], array_keys($search->get()));
    }

    public function test_separate_groups_only(): void {
        // The control: the same people in visible groups reach each other across groups.
        ['course' => $course, 'users' => $u] = self::generate_groups_data(VISIBLEGROUPS);

        $search = new user_search($u['studenta1'], $course);
        $search->include = [$u['studentb']->id, $u['tutorb']->id];
        self::assertEqualsCanonicalizing([$u['studentb']->id, $u['tutorb']->id], array_keys($search->get()));
    }

    public function test_roles_assigned_in_a_parent_context(): void {
        global $DB;

        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = new course($generator->create_course(['category' => $category->id]));
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('local/mail:mailsamerole', CAP_PREVENT, $studentroleid, $course->get_context()->id);
        $users = [];
        foreach (['student', 'peer', 'categorystudent', 'teacher'] as $name) {
            $users[$name] = new user($generator->create_user());
        }
        $generator->enrol_user($users['student']->id, $course->id, 'student');
        $generator->enrol_user($users['peer']->id, $course->id, 'student');
        $generator->enrol_user($users['teacher']->id, $course->id, 'editingteacher');
        // Enrolled without a role in the course, a student through the category.
        $generator->enrol_user($users['categorystudent']->id, $course->id, 0);
        role_assign($studentroleid, $users['categorystudent']->id, \context_coursecat::instance($category->id)->id);
        $ids = array_map(fn(user $user) => $user->id, $users);

        // A student without local/mail:mailsamerole reaches neither student, wherever the role is
        // assigned; the teacher is the control.
        $search = new user_search($users['student'], $course);
        $search->include = $ids;
        self::assertEquals([$users['teacher']->id], array_keys($search->get()));

        // The role filter lists the student of the category among the students of the course.
        $search = new user_search($users['teacher'], $course);
        $search->roleid = $studentroleid;
        self::assertEqualsCanonicalizing(
            [$users['student']->id, $users['peer']->id, $users['categorystudent']->id],
            array_keys($search->get())
        );
    }
}
