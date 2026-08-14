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
 * SPDX-FileCopyrightText: 2012-2013 Institut Obert de Catalunya <https://ioc.gencat.cat>
 * SPDX-FileCopyrightText: 2017 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Uninstall script.
 *
 * @package    local_mail
 * @copyright  2012-2024 Institut Obert de Catalunya, Marc Català, Proyecto UNIMOODLE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/filelib.php');

/**
 * Deletes the data of the plugin that is not removed by the XMLDB uninstall.
 *
 * @return bool
 */
function xmldb_local_mail_uninstall() {
    global $DB;

    $fs = get_file_storage();

    $conditions = ['contextlevel' => CONTEXT_COURSE];
    $records = $DB->get_records('context', $conditions, '', 'id');

    foreach ($records as $record) {
        $fs->delete_area_files($record->id, 'local_mail');
    }

    /*
     * The preferences this plugin sets for itself. Core removes a component's config,
     * its files and the preferences belonging to its message provider, but nothing
     * removes preferences a plugin invented under its own name, so these would stay
     * behind as orphan rows. They are user data: the privacy provider declares both.
     */
    $names = ['local_mail_mailsperpage', 'local_mail_markasread'];
    [$sql, $params] = $DB->get_in_or_equal($names);
    $DB->delete_records_select('user_preferences', "name $sql", $params);

    return true;
}
