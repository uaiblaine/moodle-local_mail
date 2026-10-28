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
 * Behat steps of the plugin.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps of the plugin.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_mail extends behat_base {
    /**
     * Sets the course contacts of a MooTube course and how its course page offers to mail them.
     *
     * MooTube keeps its course options in one JSON format option, which no generator writes.
     *
     * @Given the MooTube course :shortname lists :usernames as contacts with mail :mode
     * @param string $shortname Course short name.
     * @param string $usernames Comma-separated usernames, in display order.
     * @param string $mode "none", "contact" or "contactandteam".
     */
    public function the_mtube_course_lists_as_contacts_with_mail(string $shortname, string $usernames, string $mode): void {
        global $DB;

        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $contacts = [];
        foreach (array_filter(array_map('trim', explode(',', $usernames))) as $username) {
            $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
            $contacts[] = ['userid' => (int) $userid, 'description' => ''];
        }

        $format = course_get_format($course);
        $options = json_decode((string) ($format->get_format_options()['formatoptionstext'] ?? ''), true) ?: [];
        $options['showcoursecontacts'] = 1;
        $options['coursecontacts'] = $contacts;
        $options['coursecontactsmessaging'] = $mode;
        $format->update_course_format_options(['formatoptionstext' => json_encode($options)]);
    }
}
