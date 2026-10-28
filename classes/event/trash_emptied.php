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

namespace local_mail\event;

/**
 * Event triggered when a user empties their trash, of one course or of every course.
 *
 * One event per action rather than one per message, as the action is one click however many
 * messages it removes. No objectid or objecttable: the action names no single row. The number of
 * messages deleted forever is in "other", under "count".
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trash_emptied extends \core\event\base {
    /**
     * Builds the event for the current user.
     *
     * @param \local_mail\course|null $course Course whose trash was emptied, or null for every course.
     * @param int $count Number of messages deleted forever.
     * @return \core\event\base The event, ready to be triggered.
     */
    public static function create_from_course(?\local_mail\course $course, int $count): \core\event\base {
        global $USER;

        return self::create([
            'userid' => $USER->id,
            'context' => $course ? $course->get_context() : \context_user::instance($USER->id),
            'other' => ['count' => $count],
        ]);
    }

    /**
     * Sets the CRUD type and educational level of the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised name of the event, displayed in the log reports.
     *
     * @return string
     */
    public static function get_name() {
        return \local_mail\output\strings::get('eventtrashemptied');
    }

    /**
     * Returns a description of what happened, stored in the log.
     *
     * @return string
     */
    public function get_description() {
        $where = $this->contextlevel == CONTEXT_COURSE ? " of the course with id '$this->courseid'" : '';
        $count = (int) $this->other['count'];

        return "The user with id '$this->userid' has emptied the trash$where, deleting $count messages forever.";
    }

    /**
     * Returns the mapping of the "other" data for restore: a count needs none.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [];
    }

    /**
     * Returns the URL of the trash, of the course when there is one.
     *
     * @return \moodle_url
     */
    public function get_url() {
        if ($this->contextlevel == CONTEXT_COURSE) {
            return new \moodle_url('/local/mail/view.php', ['t' => 'trash', 'c' => $this->courseid]);
        }
        return new \moodle_url('/local/mail/view.php', ['t' => 'trash']);
    }
}
