<?php
/*
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024-2026 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail;

/**
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
