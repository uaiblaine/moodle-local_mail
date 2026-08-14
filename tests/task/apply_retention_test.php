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

namespace local_mail\task;

use local_mail\course;
use local_mail\label;
use local_mail\message;
use local_mail\message_data;
use local_mail\user;

/**
 * Unit tests for the scheduled task that applies the retention policy.
 *
 * The one thing here with no test is the guard in apply() against a row changing
 * between being selected and being acted on. That race needs two requests and cannot be
 * reproduced in a single-threaded test; what is covered is that the state the guard
 * reads is the state the sweep depends on.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\task\apply_retention
 */
final class apply_retention_test extends \local_mail\test\testcase {
    /** @var course Course the fixture messages belong to. */
    private course $course;

    /** @var user Sender of the fixture messages. */
    private user $sender;

    /** @var user Recipient of the fixture messages. */
    private user $recipient;

    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $this->course = new course($generator->create_course());
        $this->sender = new user($generator->create_user());
        $this->recipient = new user($generator->create_user());
        $generator->enrol_user($this->sender->id, $this->course->id);
        $generator->enrol_user($this->recipient->id, $this->course->id);
    }

    /**
     * Sends a message dated far enough in the past to be past every threshold.
     *
     * @param ?string $component Component that generated it, or null if a person wrote it.
     * @return message Sent message.
     */
    private function send_old(?string $component): message {
        $time = time() - 400 * DAYSECS;
        $data = message_data::new($this->course, $this->sender);
        $data->to = [$this->recipient];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $time;
        $data->component = $component;
        $message = message::create($data);
        $message->send($time);

        return $message;
    }

    /**
     * Sends an old message carrying one attachment.
     *
     * @return message Sent message.
     */
    private function send_old_with_attachment(): message {
        $time = time() - 400 * DAYSECS;
        $data = message_data::new($this->course, $this->sender);
        $data->to = [$this->recipient];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $time;
        self::create_draft_file($data->draftitemid, 'file.txt', 'File');
        $message = message::create($data);
        $message->send($time);

        return $message;
    }

    /**
     * Puts a message in the trash and backdates the moment it got there.
     *
     * @param message $message Message to trash.
     * @param int $days How many days ago it should look like it was trashed.
     * @return void
     */
    private function trash_long_ago(message $message, int $days): void {
        global $DB;

        $message->set_deleted($this->recipient, message::DELETED);
        $DB->set_field(
            'local_mail_message_users',
            'timedeleted',
            time() - $days * DAYSECS,
            ['messageid' => $message->id, 'userid' => $this->recipient->id]
        );
    }

    /**
     * Configures the policy and runs the task, discarding its output.
     *
     * @param array $config Settings to store, keyed by name without the plugin prefix.
     * @return void
     */
    private function run_task(array $config): void {
        set_config('retentionenabled', '1', 'local_mail');
        foreach ($config as $name => $value) {
            set_config($name, (string) $value, 'local_mail');
        }

        ob_start();
        (new apply_retention())->execute();
        ob_end_clean();
    }

    /**
     * Returns the deleted status of a message for the recipient, read fresh.
     *
     * @param message $message Message to read.
     * @return int One of the message DELETED_* constants.
     */
    private function deleted_status(message $message): int {
        return message::get($message->id)->deleted($this->recipient);
    }

    public function test_nothing_happens_while_the_policy_is_off(): void {
        $message = $this->send_old('mod_forum');

        set_config('retentionenabled', '0', 'local_mail');
        set_config('retentionupdatesdays', '30', 'local_mail');
        ob_start();
        (new apply_retention())->execute();
        ob_end_clean();

        self::assertEquals(message::NOT_DELETED, $this->deleted_status($message));

        // Control: the same fixture, with the policy on.
        $this->run_task(['retentionupdatesdays' => 30]);
        self::assertEquals(message::DELETED, $this->deleted_status($message));
    }

    public function test_old_updates_are_moved_to_the_trash(): void {
        $swept = $this->send_old('mod_forum');
        $starred = $this->send_old('mod_forum');
        $starred->set_starred($this->recipient, true);
        $human = $this->send_old(null);

        $this->run_task(['retentionupdatesdays' => 30, 'retentionupdatestrashdays' => 0]);

        self::assertEquals(message::DELETED, $this->deleted_status($swept));
        self::assertEquals(message::NOT_DELETED, $this->deleted_status($starred));
        self::assertEquals(message::NOT_DELETED, $this->deleted_status($human));
    }

    public function test_updates_left_in_the_trash_stop_being_visible(): void {
        $expired = $this->send_old('mod_forum');
        $this->trash_long_ago($expired, 120);

        $recent = $this->send_old('mod_forum');
        $this->trash_long_ago($recent, 10);

        $this->run_task(['retentionupdatesdays' => 0, 'retentionupdatestrashdays' => 90]);

        self::assertEquals(message::DELETED_FOREVER, $this->deleted_status($expired));
        self::assertEquals(message::DELETED, $this->deleted_status($recent));
    }

    public function test_other_trashed_mail_follows_its_own_threshold(): void {
        $expired = $this->send_old(null);
        $this->trash_long_ago($expired, 60);

        $updates = $this->send_old('mod_forum');
        $this->trash_long_ago($updates, 60);

        // Only the clock for other mail is set, so the generated one has to stay put.
        $this->run_task(['retentionupdatesdays' => 0, 'retentionupdatestrashdays' => 0, 'retentiontrashdays' => 30]);

        self::assertEquals(message::DELETED_FOREVER, $this->deleted_status($expired));
        self::assertEquals(message::DELETED, $this->deleted_status($updates));
    }

    public function test_expiring_a_message_takes_its_label_rows_with_it(): void {
        global $DB;

        $message = $this->send_old(null);
        $label = label::create($this->recipient, 'Filed');
        $message->set_labels($this->recipient, [$label]);
        $this->trash_long_ago($message, 60);

        $conditions = ['messageid' => $message->id, 'labelid' => $label->id];
        self::assertTrue($DB->record_exists('local_mail_message_labels', $conditions));

        $this->run_task(['retentionupdatesdays' => 0, 'retentionupdatestrashdays' => 0, 'retentiontrashdays' => 30]);

        /*
         * The point of this assertion is not the label. It is that the sweep went
         * through set_deleted(), which is the only thing that keeps this table in step
         * with local_mail_message_users. A bulk update would leave the row here, and a
         * listing filtered by label reads this table rather than the other one, so the
         * message would still be sitting under its label with nothing to notice it.
         */
        self::assertEquals(message::DELETED_FOREVER, $this->deleted_status($message));
        self::assertFalse($DB->record_exists('local_mail_message_labels', $conditions));
    }

    public function test_a_message_nobody_holds_is_deleted_with_its_files(): void {
        global $DB;

        $fs = get_file_storage();
        $contextid = $this->course->get_context()->id;

        $gone = $this->send_old_with_attachment();
        $kept = $this->send_old_with_attachment();

        foreach ([$this->sender, $this->recipient] as $user) {
            $gone->set_deleted($user, message::DELETED_FOREVER);
        }

        // The control keeps one participant who never let go of it.
        $kept->set_deleted($this->recipient, message::DELETED_FOREVER);

        $this->run_task(['retentionpurge' => 1]);

        self::assertFalse($DB->record_exists('local_mail_messages', ['id' => $gone->id]));
        self::assertFalse($DB->record_exists('local_mail_message_users', ['messageid' => $gone->id]));
        self::assertEmpty($fs->get_area_files($contextid, 'local_mail', 'message', $gone->id, '', false));

        self::assertTrue($DB->record_exists('local_mail_messages', ['id' => $kept->id]));
        self::assertNotEmpty($fs->get_area_files($contextid, 'local_mail', 'message', $kept->id, '', false));
    }

    public function test_a_message_something_still_answers_is_kept(): void {
        global $DB;

        $answered = $this->send_old('mod_forum');
        $data = message_data::reply($answered, $this->recipient, false);
        $data->subject = 'Re: Subject';
        $data->content = 'Reply';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = time() - 400 * DAYSECS;
        $reply = message::create($data);
        $reply->send($data->time);

        foreach ([$this->sender, $this->recipient] as $user) {
            $answered->set_deleted($user, message::DELETED_FOREVER);
        }

        $this->run_task(['retentionpurge' => 1]);

        /*
         * Nobody can see it any more, but the reply still points at it. Deleting it now
         * would leave that thread reading as a non sequitur, and any draft pinned to its
         * course by the same reference would come unpinned.
         */
        self::assertTrue($DB->record_exists('local_mail_messages', ['id' => $answered->id]));

        // Control: once the reply is gone too, the message goes.
        foreach ([$this->sender, $this->recipient] as $user) {
            $reply->set_deleted($user, message::DELETED_FOREVER);
        }
        $this->run_task(['retentionpurge' => 1]);

        self::assertFalse($DB->record_exists('local_mail_messages', ['id' => $answered->id]));
        self::assertFalse($DB->record_exists('local_mail_messages', ['id' => $reply->id]));
        self::assertFalse($DB->record_exists('local_mail_message_refs', ['reference' => $answered->id]));
    }

    public function test_nothing_is_deleted_while_the_purge_is_off(): void {
        global $DB;

        $message = $this->send_old('mod_forum');
        foreach ([$this->sender, $this->recipient] as $user) {
            $message->set_deleted($user, message::DELETED_FOREVER);
        }

        $this->run_task(['retentionpurge' => 0]);
        self::assertTrue($DB->record_exists('local_mail_messages', ['id' => $message->id]));

        // Control: the same fixture, with the purge on.
        $this->run_task(['retentionpurge' => 1]);
        self::assertFalse($DB->record_exists('local_mail_messages', ['id' => $message->id]));
    }

    public function test_a_run_reports_what_it_did(): void {
        $message = $this->send_old('mod_forum');

        set_config('retentionenabled', '1', 'local_mail');
        set_config('retentionupdatesdays', '30', 'local_mail');

        $sink = $this->redirectEvents();
        ob_start();
        (new apply_retention())->execute();
        ob_end_clean();
        $events = $sink->get_events();
        $sink->close();

        self::assertCount(1, $events);
        self::assertInstanceOf(\local_mail\event\retention_applied::class, $events[0]);

        /*
         * Attributed to nobody on purpose. Every other event in this plugin reads the
         * current user, which under cron is whoever the task runs as.
         */
        self::assertEquals(0, $events[0]->userid);

        /*
         * Two rows for one message: the recipient's copy and the sender's. The sender of
         * a notification is the person the activity named, not a robot account, so one
         * forum post to a large course leaves that teacher a copy per student in their
         * sent mail. Sweeping those is most of the point, and it is also what eventually
         * lets a message be removed at all, since that needs every participant to have
         * let go of it.
         */
        self::assertEquals(2, $events[0]->other['counts'][\local_mail\local\retention::STAGE_TRASH_UPDATES]);
        self::assertEquals(message::DELETED, $this->deleted_status($message));
        self::assertEquals(message::DELETED, message::get($message->id)->deleted($this->sender));
    }
}
