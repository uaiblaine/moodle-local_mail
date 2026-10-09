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
}
