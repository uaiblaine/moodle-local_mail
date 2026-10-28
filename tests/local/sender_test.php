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

use local_mail\exception;
use local_mail\external;
use local_mail\message;
use local_mail\message_data;

/**
 * Tests for the shared send routine.
 *
 * Test metadata stays in doc-comments while the plugin supports 4.5 (see CLAUDE.md).
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\local\sender
 */
final class sender_test extends \local_mail\test\testcase {
    public function test_reachable_ids(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        $outsider = $this->getDataGenerator()->create_user();

        $ids = [
            $u['tutora']->id,
            $u['studentb']->id,
            (string) $u['teacher']->id,
            $u['tutora']->id,
            -1,
            (int) $outsider->id,
        ];

        // Only the users the composer would list, once each, in the order given.
        self::assertSame(
            [(int) $u['tutora']->id, (int) $u['teacher']->id],
            sender::reachable_ids($u['studenta1'], $course, $ids),
        );
        self::assertSame([], sender::reachable_ids($u['studenta1'], $course, []));
    }

    public function test_check_recipients(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();

        // Reachable recipients pass, a repeated id counts once.
        sender::check_recipients($u['studenta1'], $course, [$u['tutora']->id, $u['teacher']->id, $u['tutora']->id]);

        $cases = [
            'empty' => [[], 'erroremptyrecipients'],
            'other group' => [[$u['tutora']->id, $u['studentb']->id], 'errorinvalidrecipients'],
            'unknown user' => [[-1], 'errorinvalidrecipients'],
        ];
        foreach ($cases as $name => [$ids, $error]) {
            try {
                sender::check_recipients($u['studenta1'], $course, $ids);
                self::fail("No exception for: $name");
            } catch (exception $e) {
                self::assertEquals($error, $e->errorcode, $name);
            }
        }

        set_config('maxrecipients', 1, 'local_mail');
        sender::check_recipients($u['studenta1'], $course, [$u['tutora']->id]);
        try {
            sender::check_recipients($u['studenta1'], $course, [$u['tutora']->id, $u['teacher']->id]);
            self::fail('No exception for too many recipients');
        } catch (exception $e) {
            self::assertEquals('errortoomanyrecipients', $e->errorcode);
        }
    }

    public function test_send(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);
        $notificationsink = $this->redirectMessages();
        $eventsink = $this->redirectEvents();

        $message = $this->create_draft($u['studenta1'], [$u['tutora'], $u['teacher']], 'Subject');
        sender::send($u['studenta1'], $message);

        $message = message::get($message->id);
        self::assertFalse($message->draft);
        self::assertTrue($message->unread($u['tutora']));
        self::assertCount(2, $notificationsink->get_messages());
        self::assert_message_event('\local_mail\event\message_sent', $message, $eventsink);
    }

    public function test_send_rejects_recipient_in_another_group(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);
        $notificationsink = $this->redirectMessages();

        // The draft names a user of the other group, as a link or a request can.
        $message = $this->create_draft($u['studenta1'], [$u['studentb']], 'Subject');

        $sends = [
            'sender' => fn() => sender::send($u['studenta1'], $message),
            'service' => fn() => external::send_message($message->id),
        ];
        foreach ($sends as $name => $send) {
            try {
                $send();
                self::fail("No exception from the $name");
            } catch (exception $e) {
                self::assertEquals('errorinvalidrecipients', $e->errorcode, $name);
            }
        }

        self::assertTrue(message::get($message->id)->draft);
        self::assertCount(0, $notificationsink->get_messages());
    }

    public function test_send_rejects_a_draft_the_sender_cannot_edit(): void {
        ['users' => $u] = self::generate_groups_data();

        $draft = $this->create_draft($u['studenta1'], [$u['tutora']], 'Subject');
        $sent = $this->create_draft($u['studenta1'], [$u['tutora']], 'Subject');
        sender::send($u['studenta1'], $sent);

        // Another user's draft, and a message already sent.
        foreach ([[$u['tutora'], $draft], [$u['studenta1'], message::get($sent->id)]] as [$user, $message]) {
            try {
                sender::send($user, $message);
                self::fail('No exception for message ' . $message->id);
            } catch (exception $e) {
                self::assertEquals('errormessagenotfound', $e->errorcode);
            }
        }
        self::assertTrue(message::get($draft->id)->draft);
    }

    public function test_send_rejects_empty_subject(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);

        $message = $this->create_draft($u['studenta1'], [$u['tutora']], " \t");

        $this->expectExceptionObject(new exception('erroremptysubject'));
        sender::send($u['studenta1'], $message);
    }

    /**
     * Creates a draft in the course of the sender's first enrolment.
     *
     * @param \local_mail\user $sender Sender.
     * @param \local_mail\user[] $to Recipients.
     * @param string $subject Subject.
     * @return message
     */
    private function create_draft(\local_mail\user $sender, array $to, string $subject): message {
        $courses = \local_mail\course::get_by_user($sender);
        $data = message_data::new(reset($courses), $sender);
        $data->to = $to;
        $data->subject = $subject;
        $data->content = 'Content';
        return message::create($data);
    }

    public function test_sender_without_mail_reaches_nobody(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        $generator = $this->getDataGenerator();
        $outsider = new \local_mail\user($generator->create_user());
        $nomail = new \local_mail\user($generator->create_user());
        $generator->enrol_user($nomail->id, $course->id, $generator->create_role());
        $ids = [$u['tutora']->id, $u['teacher']->id];

        // The control: a sender who uses mail in the course reaches both.
        self::assertSame(array_map('intval', $ids), sender::reachable_ids($u['studenta1'], $course, $ids));

        foreach (['not enrolled' => $outsider, 'enrolled without local/mail:usemail' => $nomail] as $name => $sender) {
            self::assertSame([], sender::reachable_ids($sender, $course, $ids), $name);
            try {
                sender::check_recipients($sender, $course, $ids);
                self::fail("No exception for: $name");
            } catch (exception $e) {
                self::assertEquals('errorinvalidrecipients', $e->errorcode, $name);
            }
        }
    }
}
