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
 * SPDX-FileCopyrightText: 2025 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Selects the plugin files that PHPUnit measures when generating code coverage reports.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_mail_coverage extends phpunit_coverage_info {
    /** @var array Plugin folders whose files are all measured for coverage. */
    protected $includelistfolders = [
        'backup',
    ];

    /** @var array Individual plugin files measured for coverage. */
    protected $includelistfiles = [
        'db/upgrade.php',
    ];

    /** @var array Plugin folders left out of coverage, such as test-only helpers. */
    protected $excludelistfolders = [
        'classes/test',
    ];
}

return new local_mail_coverage();
