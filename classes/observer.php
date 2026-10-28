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
 * SPDX-FileCopyrightText: 2014 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

 namespace local_mail;

/**
 * Event observer for local_mail.
 *
 * @package    local_mail
 * @copyright  2014-2024 Marc Català, Proyecto UNIMOODLE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Triggered via course_deleted event.
     *
     * @param \core\event\course_deleted $event The course deletion event.
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        message::delete_course_data($event->get_context());
    }

    /**
     * Triggered via user_deleted event: removes what belonged to the user alone.
     *
     * Their drafts, their labels, and their place in every message they did not send
     * ({@see message::delete_recipient_rows()}). The messages they sent stay, signed by a
     * deleted user, as core keeps a deleted user's posts: their recipients still hold them. An
     * erasure request through the privacy API erases those too.
     *
     * @param \core\event\user_deleted $event The user deletion event.
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;

        $user = user::get((int) $event->objectid);

        // One transaction, so a failure part-way leaves none of the user's mail behind half-erased.
        $transaction = $DB->start_delegated_transaction();

        $select = 'userid = :userid AND role = :role AND draft = 1';
        $params = ['userid' => $user->id, 'role' => message::ROLE_FROM];
        $draftids = $DB->get_fieldset_select('local_mail_message_users', 'messageid', $select, $params);
        foreach (array_chunk($draftids, 100) as $ids) {
            foreach (message::get_many($ids) as $draft) {
                $draft->set_deleted($user, message::DELETED_FOREVER);
            }
        }

        message::delete_recipient_rows($user->id);

        foreach (label::get_by_user($user) as $label) {
            $label->delete();
        }

        $transaction->allow_commit();
    }
}
