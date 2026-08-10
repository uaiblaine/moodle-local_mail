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
 * Unit tests for the query that searches users available as message recipients.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\user_search
 */
final class user_search_test extends test\testcase {
    public function test_count(): void {
        [$users] = self::generate_random_data(false);

        foreach (self::user_search_cases($users) as $search) {
            $expected = count(self::filter_users($users, $search));
            self::assertEquals($expected, $search->count(), $search);
        }
    }

    public function test_get(): void {
        [$users] = self::generate_random_data(false);

        foreach (self::user_search_cases($users) as $search) {
            $expected = self::filter_users($users, $search);
            $result = $search->get(0, 0);
            self::assert_array_of_objects($expected, $result, $search);

            // Offset and limit.
            $expected = array_slice($expected, 5, 10, true);
            $result = $search->get(5, 10);
            self::assert_array_of_objects($expected, $result, $search);
        }
    }

    /**
     * Returns the generated users filtered by search parameters.
     *
     * @param user[] $users Array of generated users.
     * @param user_search $search Search parameters.
     * @return user[] Found users, indexed by ID.
     */
    protected static function filter_users(array $users, user_search $search): array {
        global $DB;

        $context = $search->course->get_context();

        $excludedroleids = [];
        if (!has_capability('local/mail:mailsamerole', $context, $search->user->id, false)) {
            $excludedroleids = array_column(get_user_roles($context, $search->user->id, false), 'roleid');
        }

        $fullnamematches = [];
        if ($search->fullname) {
            $select = $DB->sql_like($DB->sql_fullname(), '?', false, false);
            $params = ['%' . $DB->sql_like_escape($search->fullname) . '%'];
            $fullnamematches = $DB->get_records_select('user', $select, $params);
        }

        $result = [];

        foreach ($users as $user) {
            if (
                $user->id == $search->user->id ||
                $user->deleted ||
                !is_enrolled($context, $user, 'local/mail:usemail', true) ||
                $excludedroleids && array_intersect(
                    $excludedroleids,
                    array_column(get_user_roles($context, $user->id, false), 'roleid')
                ) ||
                $search->roleid && !user_has_role_assignment($user->id, $search->roleid, $context->id) ||
                $search->groupid && !groups_is_member($search->groupid, $user->id) ||
                $search->fullname && !isset($fullnamematches[$user->id]) ||
                $search->include && !in_array($user->id, $search->include)
            ) {
                continue;
            }

            $result[$user->id] = $user;
        }

        return $result;
    }
}
