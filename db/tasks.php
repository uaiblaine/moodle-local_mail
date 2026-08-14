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
 * SPDX-FileCopyrightText: 2026 Anderson Blaine
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Scheduled tasks of the plugin.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * Nightly, at a quarter past four. The task does nothing at all unless an administrator
 * has switched the retention policy on, so a site that never configures one carries a
 * single cheap check per night.
 */
$tasks = [
    [
        'classname' => 'local_mail\task\apply_retention',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '4',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
