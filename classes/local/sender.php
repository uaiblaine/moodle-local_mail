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

use local_mail\course;
use local_mail\event\message_sent;
use local_mail\exception;
use local_mail\message;
use local_mail\output\renderer;
use local_mail\user;
use local_mail\user_search;

/**
 * Sends a draft on behalf of a user, the one path every way of sending mail goes through.
 *
 * The mailbox's send button ({@see \local_mail\external::send_message()}) and the quick-compose
 * modal ({@see \local_mail\form\quick_compose}) both call it, so the recipient rules, the
 * recipient limit and the notifications cannot differ between them.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sender {
    /**
     * Checks that a sender may mail every one of the given users in a course.
     *
     * A recipient is valid when the composer would list them: enrolled with
     * "local/mail:usemail", not of the sender's role unless "local/mail:mailsamerole" allows it,
     * and reachable under separate groups ({@see user_search}). No recipient is valid when the
     * sender cannot use mail in the course ({@see self::reachable_ids()}).
     *
     * @param user $sender Sender.
     * @param course $course Course of the message.
     * @param int[] $recipientids IDs of the recipients.
     * @throws exception If there are no recipients, too many, or any of them is not valid.
     */
    public static function check_recipients(user $sender, course $course, array $recipientids): void {
        $recipientids = array_unique(array_map('intval', $recipientids));

        if (!$recipientids) {
            throw new exception('erroremptyrecipients');
        }

        if (count(self::reachable_ids($sender, $course, $recipientids)) != count($recipientids)) {
            throw new exception('errorinvalidrecipients');
        }

        $maxrecipients = (int) get_config('local_mail', 'maxrecipients') ?: 100;
        if (count($recipientids) > $maxrecipients) {
            throw new exception('errortoomanyrecipients', $maxrecipients);
        }
    }

    /**
     * Returns which of the given users a sender may mail in a course.
     *
     * The test is the one {@see self::check_recipients()} applies at send. Draft writes use it
     * too, because a draft hands back the name and picture of every recipient it holds. Nobody
     * is reachable for a sender who cannot use mail in the course: user_search lists the
     * course's users without checking the sender's own access, so this API checks it itself.
     *
     * @param user $sender Sender.
     * @param course $course Course of the message.
     * @param int[] $ids IDs of the users.
     * @return int[] The reachable IDs, without duplicates, in the given order.
     */
    public static function reachable_ids(user $sender, course $course, array $ids): array {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids || !$sender->can_use_mail($course)) {
            return [];
        }

        $search = new user_search($sender, $course);
        $search->include = $ids;
        $reachable = $search->get();

        return array_values(array_filter($ids, fn(int $id): bool => isset($reachable[$id])));
    }

    /**
     * Sends a draft and notifies each of its recipients.
     *
     * @param user $sender Sender.
     * @param message $message Draft to send.
     * @throws exception If the message is not a draft the sender may edit, its recipients are not
     *     valid or its subject is empty.
     */
    public static function send(user $sender, message $message): void {
        if (!$sender->can_edit_message($message)) {
            throw new exception('errormessagenotfound', $message->id);
        }

        self::check_recipients($sender, $message->course, array_column($message->recipients(), 'id'));

        if (!\core_text::strlen(trim($message->subject))) {
            throw new exception('erroremptysubject');
        }

        /*
         * Sent first, notified after: the message is in every recipient's mailbox once send()
         * returns, and a notification is only a pointer to it. A processor failing part way
         * through the loop must not undo a send the recipients can already read.
         */
        $message->send(time());

        message_sent::create_from_message($message)->trigger();

        $renderer = renderer::get_instance();
        foreach ($message->recipients() as $recipient) {
            $notificationid = message_send($renderer->notification($message, $recipient));
            if ($notificationid && get_user_preferences('local_mail_markasread', false, $recipient->id)) {
                $message->set_unread($recipient, false);
            }
        }
    }
}
