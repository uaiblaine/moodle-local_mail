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

namespace local_mail\event;

/**
 * Event triggered when a user sends a message to its recipients.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_sent extends \core\event\base {
    /**
     * Builds the event for a sent message, in the context of the course of the message.
     *
     * @param \local_mail\message $message The message that has just been sent.
     * @return \core\event\base The event, ready to be triggered.
     */
    public static function create_from_message(\local_mail\message $message): \core\event\base {
        global $USER;

        return self::create([
            'userid' => $USER->id,
            'objectid' => $message->id,
            'context' => $message->course->get_context(),
        ]);
    }

    /**
     * Sets the table, CRUD type and educational level of the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'local_mail_messages';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised name of the event, displayed in the log reports.
     *
     * @return string
     */
    public static function get_name() {
        return \local_mail\output\strings::get('eventmessagesent');
    }

    /**
     * Returns a description of what happened, stored in the log.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has sent the message with id '$this->objectid'.";
    }

    /**
     * Returns the database table and restore item that the object ID refers to.
     *
     * @return array Mapping with the "db" and "restore" keys.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_mail_messages', 'restore' => 'local_mail_message'];
    }

    /**
     * Returns the URL of the message in the tray of its course.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/mail/view.php', ['t' => 'course', 'c' => $this->courseid, 'm' => $this->objectid]);
    }
}
