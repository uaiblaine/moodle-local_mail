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

namespace local_mail;

use core\hook\output\before_footer_html_generation;
use local_mail\local\entry_points;

/**
 * Hook callbacks of the plugin.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Loads the "Send mail" entry points on a course profile and on a course's participants page.
     *
     * Neither page has an extension point for a button or a bulk action, so the AMD module adds
     * them; the page is matched by its script, not its page type, because a course profile takes
     * the page type of its course format.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;

        if (during_initial_install() || !$PAGE->has_set_url()) {
            return;
        }

        if ($PAGE->url->compare(new \moodle_url('/user/view.php'), URL_MATCH_BASE)) {
            self::init_profile();
        } else if ($PAGE->url->compare(new \moodle_url('/user/index.php'), URL_MATCH_BASE)) {
            self::init_participants();
        }
    }

    /**
     * Registers Mail as the channel to a MooTube course's contacts.
     *
     * Only a MooTube build carrying the messaging provider seam (the fleet's 5.3 checkout) has
     * register_messaging_provider(); the vendor's builds and the 5.2 checkout dispatch the same hook
     * without it, so the method is checked before it is called. The parameter is untyped because the
     * hook class exists only where format_mtube is installed. Constructs the provider only: the hook
     * fires while modinfo is being built.
     *
     * @param object $hook A \format_mtube\hook\extensions_registration.
     */
    public static function register_mtube_extensions(object $hook): void {
        if (!method_exists($hook, 'register_messaging_provider')) {
            return;
        }
        $hook->register_messaging_provider(new local\mtube_provider());
    }

    /**
     * Wires the profile node and adds the header button on `/user/view.php?id=<user>&course=<course>`.
     */
    private static function init_profile(): void {
        global $PAGE;

        $node = entry_points::enabled(entry_points::PROFILE_NODE);
        $header = entry_points::enabled(entry_points::PROFILE_HEADER);
        if (!$node && !$header) {
            return;
        }

        $course = entry_points::mail_course((int) $PAGE->course->id);
        $userid = (int) $PAGE->url->param('id');
        if (!$course || !$userid || !entry_points::can_mail($course, $userid)) {
            return;
        }

        $PAGE->requires->js_call_amd('local_mail/entry_points', 'initProfile', [[
            'courseid' => (int) $course->id,
            'userid' => $userid,
            'header' => $header,
            'url' => entry_points::compose_url($course, $userid)->out(false),
        ]]);
    }

    /**
     * Adds "Send mail" to the bulk action menu of `/user/index.php?id=<course>`.
     */
    private static function init_participants(): void {
        global $PAGE;

        if (!entry_points::enabled(entry_points::PARTICIPANTS)) {
            return;
        }

        $course = entry_points::mail_course((int) $PAGE->course->id);
        if (!$course) {
            return;
        }

        $PAGE->requires->js_call_amd('local_mail/entry_points', 'initParticipants', [[
            'courseid' => (int) $course->id,
        ]]);
    }
}
