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
 * Event triggered when a user creates a label.
 *
 * Labels belong to their owner, so the event is in the owner's user context.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class label_created extends \core\event\base {
    /**
     * Builds the event for a label of the current user.
     *
     * @param \local_mail\label $label The label that has just been created.
     * @return \core\event\base The event, ready to be triggered.
     */
    public static function create_from_label(\local_mail\label $label): \core\event\base {
        global $USER;

        return self::create([
            'userid' => $USER->id,
            'objectid' => $label->id,
            'context' => \context_user::instance($USER->id),
        ]);
    }

    /**
     * Sets the table, CRUD type and educational level of the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'local_mail_labels';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised name of the event, displayed in the log reports.
     *
     * @return string
     */
    public static function get_name() {
        return \local_mail\output\strings::get('eventlabelcreated');
    }

    /**
     * Returns a description of what happened, stored in the log.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has created the label with id '$this->objectid'.";
    }

    /**
     * Returns the database table and restore item that the object ID refers to.
     *
     * Labels are not part of a course backup, so a restored log entry cannot map the id.
     *
     * @return array Mapping with the "db" and "restore" keys.
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'local_mail_labels', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Returns the URL of the label's tray.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/mail/view.php', ['t' => 'label', 'l' => $this->objectid]);
    }
}
