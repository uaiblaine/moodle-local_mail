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
use local_mail\label;
use local_mail\message;
use local_mail\message_data;
use local_mail\settings;
use local_mail\user;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the selection behind the retention policy.
 *
 * Every test that asserts something is spared also seeds a row the same stage is
 * required to select, and asserts it was. Without that control each of them would pass
 * by selecting nothing at all, and would go on passing after the rule it guards was
 * deleted.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(retention::class)]
final class retention_test extends \local_mail\test\testcase {
    /** @var course Course the fixture messages belong to. */
    private course $course;

    /** @var user Sender of the fixture messages. */
    private user $sender;

    /** @var user Recipient of the fixture messages. */
    private user $recipient;

    /** @var int Timestamp the fixture messages are sent at. */
    private int $sent;

    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $this->course = new course($generator->create_course());
        $this->sender = new user($generator->create_user());
        $this->recipient = new user($generator->create_user());
        $generator->enrol_user($this->sender->id, $this->course->id);
        $generator->enrol_user($this->recipient->id, $this->course->id);
        $this->sent = make_timestamp(2026, 1, 1, 12, 0);
    }

    /**
     * Sends one message and returns it.
     *
     * @param ?string $component Component that generated it, or null if a person wrote it.
     * @return message Sent message.
     */
    private function send(?string $component): message {
        $data = message_data::new($this->course, $this->sender);
        $data->to = [$this->recipient];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $this->sent;
        $data->component = $component;
        $message = message::create($data);
        $message->send($this->sent);

        return $message;
    }

    /**
     * Returns a retention selection that is enabled and measured from a given moment.
     *
     * The moment is moved rather than the data, so that rows reach the trash through
     * set_deleted() and carry the timestamp it really writes.
     *
     * @param int $daysfromsending How many days after the fixture was sent to measure from.
     * @return retention Selection to query.
     */
    private function retention(int $daysfromsending): retention {
        $settings = settings::defaults();
        $settings->retentionenabled = true;
        $settings->retentionupdatesdays = 30;
        $settings->retentionupdatestrashdays = 90;
        $settings->retentiontrashdays = 30;

        return new retention($settings, $this->sent + $daysfromsending * DAYSECS);
    }

    /**
     * Returns the message ids a stage would act on.
     *
     * @param retention $retention Selection to query.
     * @param string $stage One of the retention STAGE_* constants.
     * @return int[] Message ids, sorted.
     */
    private function selected(retention $retention, string $stage): array {
        $ids = array_map(fn($record) => (int) $record->messageid, $retention->batch($stage));
        sort($ids);

        return $ids;
    }

    public function test_nothing_is_selected_while_the_policy_is_off(): void {
        $this->send('mod_forum');

        $settings = settings::defaults();
        $settings->retentionenabled = false;
        $settings->retentionupdatesdays = 30;
        $off = new retention($settings, $this->sent + 60 * DAYSECS);

        self::assertEquals(0, $off->count(retention::STAGE_TRASH_UPDATES));
        self::assertEquals([], $this->selected($off, retention::STAGE_TRASH_UPDATES));

        // Control: the same fixture and the same moment, with the policy on.
        self::assertEquals(2, $this->retention(60)->count(retention::STAGE_TRASH_UPDATES));
    }

    public function test_a_threshold_of_zero_keeps_mail_indefinitely(): void {
        $this->send('mod_forum');

        $settings = settings::defaults();
        $settings->retentionenabled = true;
        $settings->retentionupdatesdays = 0;
        $never = new retention($settings, $this->sent + 3650 * DAYSECS);

        self::assertFalse($never->stage_enabled(retention::STAGE_TRASH_UPDATES));
        self::assertEquals(0, $never->count(retention::STAGE_TRASH_UPDATES));
    }

    public function test_only_generated_mail_reaches_the_trash_stage(): void {
        $updates = $this->send('mod_forum');
        $this->send(null);

        $selected = $this->selected($this->retention(60), retention::STAGE_TRASH_UPDATES);

        self::assertEquals([$updates->id], array_unique($selected));
    }

    public function test_mail_younger_than_the_threshold_is_left_alone(): void {
        $this->send('mod_forum');

        self::assertEquals(0, $this->retention(29)->count(retention::STAGE_TRASH_UPDATES));

        // Control: the same message, one day past the threshold.
        self::assertEquals(2, $this->retention(31)->count(retention::STAGE_TRASH_UPDATES));
    }

    public function test_starred_mail_is_spared(): void {
        $starred = $this->send('mod_forum');
        $starred->set_starred($this->recipient, true);
        $plain = $this->send('mod_forum');

        $selected = $this->recipient_selection();

        self::assertNotContains($starred->id, $selected);
        self::assertContains($plain->id, $selected);
    }

    public function test_labelled_mail_is_spared(): void {
        $labelled = $this->send('mod_forum');
        $label = label::create($this->recipient, 'Keep');
        $labelled->set_labels($this->recipient, [$label]);
        $plain = $this->send('mod_forum');

        $selected = $this->recipient_selection();

        self::assertNotContains($labelled->id, $selected);
        self::assertContains($plain->id, $selected);
    }

    public function test_mail_that_was_replied_to_is_spared(): void {
        $answered = $this->send('mod_forum');
        $data = message_data::reply($answered, $this->recipient, false);
        $data->subject = 'Re: Subject';
        $data->content = 'Reply';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $this->sent;
        message::create($data)->send($this->sent);

        $plain = $this->send('mod_forum');

        $selected = $this->selected($this->retention(60), retention::STAGE_TRASH_UPDATES);

        self::assertNotContains($answered->id, $selected);
        self::assertContains($plain->id, $selected);
    }

    public function test_drafts_are_never_selected(): void {
        $data = message_data::new($this->course, $this->sender);
        $data->to = [$this->recipient];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $this->sent;
        $data->component = 'mod_forum';
        $draft = message::create($data);

        $sent = $this->send('mod_forum');

        $selected = $this->selected($this->retention(60), retention::STAGE_TRASH_UPDATES);

        self::assertNotContains($draft->id, $selected);
        self::assertContains($sent->id, $selected);
    }

    public function test_trashed_generated_mail_expires_on_its_own_clock(): void {
        $message = $this->send('mod_forum');
        $message->set_deleted($this->recipient, message::DELETED);

        /*
         * The trash clock is stamped by set_deleted() at the real current time, so the
         * moment the selection measures from has to be counted from now rather than
         * from the date the fixture claims it was sent.
         */
        $settings = settings::defaults();
        $settings->retentionenabled = true;
        $settings->retentionupdatestrashdays = 90;
        $settings->retentiontrashdays = 30;

        $early = new retention($settings, time() + 89 * DAYSECS);
        self::assertEquals(0, $early->count(retention::STAGE_EXPIRE_UPDATES));

        $late = new retention($settings, time() + 91 * DAYSECS);
        self::assertEquals([$message->id], $this->selected($late, retention::STAGE_EXPIRE_UPDATES));

        // Generated mail is not picked up by the stage meant for everything else.
        self::assertEquals(0, $late->count(retention::STAGE_EXPIRE_TRASH));
    }

    public function test_other_trashed_mail_expires_on_the_other_clock(): void {
        $message = $this->send(null);
        $message->set_deleted($this->recipient, message::DELETED);

        $settings = settings::defaults();
        $settings->retentionenabled = true;
        $settings->retentionupdatestrashdays = 90;
        $settings->retentiontrashdays = 30;

        $early = new retention($settings, time() + 29 * DAYSECS);
        self::assertEquals(0, $early->count(retention::STAGE_EXPIRE_TRASH));

        $late = new retention($settings, time() + 31 * DAYSECS);
        self::assertEquals([$message->id], $this->selected($late, retention::STAGE_EXPIRE_TRASH));

        // And it is not picked up by the stage meant for generated mail.
        self::assertEquals(0, $late->count(retention::STAGE_EXPIRE_UPDATES));
    }

    public function test_mail_trashed_before_the_clock_existed_never_expires(): void {
        global $DB;

        $message = $this->send(null);
        $message->set_deleted($this->recipient, message::DELETED);

        // What an upgraded site looks like: in the trash, with no record of when.
        $DB->set_field('local_mail_message_users', 'timedeleted', 0, ['messageid' => $message->id]);

        $settings = settings::defaults();
        $settings->retentionenabled = true;
        $settings->retentiontrashdays = 30;
        $late = new retention($settings, time() + 3650 * DAYSECS);

        self::assertEquals(0, $late->count(retention::STAGE_EXPIRE_TRASH));

        // Control: the same row, once something stamps it.
        $DB->set_field('local_mail_message_users', 'timedeleted', time(), ['messageid' => $message->id]);
        self::assertEquals(1, $late->count(retention::STAGE_EXPIRE_TRASH));
    }

    /**
     * Returns the message ids the trash stage selects among the recipient's own rows.
     *
     * Starring and labelling are per user, so the sender's copy of a message the
     * recipient spared is still selectable. Comparing whole selections would therefore
     * report a message as unspared when only the other participant's row was picked.
     *
     * @return int[] Message ids of the recipient's selected rows, sorted.
     */
    private function recipient_selection(): array {
        $ids = [];

        foreach ($this->retention(60)->batch(retention::STAGE_TRASH_UPDATES) as $record) {
            if ($record->userid == $this->recipient->id) {
                $ids[] = (int) $record->messageid;
            }
        }

        sort($ids);

        return $ids;
    }
}
