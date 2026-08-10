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
 * Unit tests for the helper that exposes the plugin language strings to the user interface.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\output\strings
 */
final class output_strings_test extends test\testcase {
    public function test_get(): void {
        self::assertEquals('{$a->index} of {$a->total}', output\strings::get('pagingsingle'));
        self::assertEquals('3 of 14', output\strings::get('pagingsingle', ['index' => '3', 'total' => '14']));
    }

    public function test_get_all(): void {
        self::assertEquals(self::load_strings(), output\strings::get_all());
    }

    public function test_get_ids(): void {
        $ids = array_keys(self::load_strings());
        self::assertEquals($ids, output\strings::get_ids());
    }

    public function test_get_many(): void {
        $strings = self::load_strings();
        $ids = self::random_items(array_keys($strings), 10);
        self::assertEquals(
            array_intersect_key($strings, array_combine($ids, $ids)),
            output\strings::get_many($ids)
        );
    }

    /**
     * Reads the English language file directly to obtain the expected strings.
     *
     * @return array Strings of the plugin, indexed by string identifier.
     */
    private static function load_strings(): array {
        global $CFG;

        $string = [];
        include("$CFG->dirroot/local/mail/lang/en/local_mail.php");

        return $string;
    }
}
