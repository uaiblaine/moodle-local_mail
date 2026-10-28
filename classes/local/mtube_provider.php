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

namespace local_mail\local;

use local_mail\output\strings;
use local_mail\user;
use local_mail\user_search;

/**
 * Mail as the channel to the course contacts of a MooTube course (format_mtube).
 *
 * Registered from {@see \local_mail\hook_callbacks::register_mtube_extensions()}, which runs only
 * when format_mtube dispatches its extensions hook, so this class, which implements that plugin's
 * interface, is never loaded on a site without it.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mtube_provider implements \format_mtube\local\extensions\messaging_provider {
    /**
     * Stable id of the channel.
     *
     * @return string
     */
    public function get_id(): string {
        return 'local_mail';
    }

    /**
     * Translated name of the channel.
     *
     * @return string
     */
    public function get_label(): string {
        return strings::get('pluginname');
    }

    /**
     * AMD module that renders the envelopes and the team button.
     *
     * @return string
     */
    public function get_amd_module(): string {
        return 'local_mail/mtube_contacts';
    }

    /**
     * Whether the current user can use mail in the course.
     *
     * @param \stdClass $course
     * @param \context_course $context
     * @return bool
     */
    public function is_available(\stdClass $course, \context_course $context): bool {
        return entry_points::mail_course((int) $course->id) !== null;
    }

    /**
     * The users the current user may mail in the course, by the composer's rules.
     *
     * @param \stdClass $course
     * @param \context_course $context
     * @param int[] $userids Candidates.
     * @return int[] The reachable ones, in the given order.
     */
    public function filter_reachable(\stdClass $course, \context_course $context, array $userids): array {
        $mailcourse = entry_points::mail_course((int) $course->id);
        $user = user::current();
        if (!$mailcourse || !$user || !$userids) {
            return [];
        }

        $search = new user_search($user, $mailcourse);
        $search->include = array_map('intval', $userids);
        $reachable = $search->get();

        return array_values(array_filter(array_map('intval', $userids), fn(int $id) => isset($reachable[$id])));
    }
}
