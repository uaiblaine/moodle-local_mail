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
 * SPDX-FileCopyrightText: 2024-2025 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail;

/**
 * Unit tests for the draft and message events fired by the plugin.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\event\draft_created
 * @covers \local_mail\event\draft_deleted
 * @covers \local_mail\event\draft_updated
 * @covers \local_mail\event\draft_viewed
 * @covers \local_mail\event\label_created
 * @covers \local_mail\event\label_deleted
 * @covers \local_mail\event\label_updated
 * @covers \local_mail\event\message_deleted
 * @covers \local_mail\event\message_deleted_forever
 * @covers \local_mail\event\message_labels_updated
 * @covers \local_mail\event\message_restored
 * @covers \local_mail\event\message_sent
 * @covers \local_mail\event\message_viewed
 * @covers \local_mail\event\trash_emptied
 * @covers \local_mail\external
 */
final class event_test extends test\testcase {
    public function test_draft_created(): void {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        $data = message_data::new($course, $user);
        $draft = message::create($data);
        self::setUser($user->id);

        $event = event\draft_created::create_from_message($draft);

        self::assertEquals(output\strings::get('eventdraftcreated'), event\draft_created::get_name());
        self::assertEquals(
            "The user with id '$user->id' has created the draft with id '$draft->id'.",
            $event->get_description()
        );
        self::assertEquals(
            new \moodle_url('/local/mail/view.php', ['t' => 'drafts', 'm' => $draft->id]),
            $event->get_url()
        );
        self::assertEquals(
            ['db' => 'local_mail_messages', 'restore' => 'local_mail_message'],
            event\draft_created::get_objectid_mapping()
        );
    }

    public function test_draft_deleted(): void {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        $data = message_data::new($course, $user);
        $draft = message::create($data);
        self::setUser($user->id);

        $event = event\draft_deleted::create_from_message($draft);

        self::assertEquals(output\strings::get('eventdraftdeleted'), event\draft_deleted::get_name());
        self::assertEquals(
            "The user with id '$user->id' has deleted the draft with id '$draft->id'.",
            $event->get_description()
        );
        self::assertEquals(
            new \moodle_url('/local/mail/view.php', ['t' => 'drafts', 'm' => $draft->id]),
            $event->get_url()
        );
        self::assertEquals(
            ['db' => 'local_mail_messages', 'restore' => 'local_mail_message'],
            event\draft_deleted::get_objectid_mapping()
        );
    }

    public function test_draft_updated(): void {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        $data = message_data::new($course, $user);
        $draft = message::create($data);
        self::setUser($user->id);

        $event = event\draft_updated::create_from_message($draft);

        self::assertEquals(output\strings::get('eventdraftupdated'), event\draft_updated::get_name());
        self::assertEquals(
            "The user with id '$user->id' has updated the draft with id '$draft->id'.",
            $event->get_description()
        );
        self::assertEquals(
            new \moodle_url('/local/mail/view.php', ['t' => 'drafts', 'm' => $draft->id]),
            $event->get_url()
        );
        self::assertEquals(
            ['db' => 'local_mail_messages', 'restore' => 'local_mail_message'],
            event\draft_updated::get_objectid_mapping()
        );
    }

    public function test_draft_viewed(): void {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        $data = message_data::new($course, $user);
        $draft = message::create($data);
        self::setUser($user->id);

        $event = event\draft_viewed::create_from_message($draft);

        self::assertEquals(output\strings::get('eventdraftviewed'), event\draft_viewed::get_name());
        self::assertEquals(
            "The user with id '$user->id' has viewed the draft with id '$draft->id'.",
            $event->get_description()
        );
        self::assertEquals(
            new \moodle_url('/local/mail/view.php', ['t' => 'drafts', 'm' => $draft->id]),
            $event->get_url()
        );
        self::assertEquals(
            ['db' => 'local_mail_messages', 'restore' => 'local_mail_message'],
            event\draft_viewed::get_objectid_mapping()
        );
    }

    public function test_message_sent(): void {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send(time());
        self::setUser($user1->id);

        $event = event\message_sent::create_from_message($message);

        self::assertEquals(output\strings::get('eventmessagesent'), event\message_sent::get_name());
        self::assertEquals(
            "The user with id '$user1->id' has sent the message with id '$message->id'.",
            $event->get_description()
        );
        self::assertEquals(
            new \moodle_url('/local/mail/view.php', ['t' => 'course', 'c' => $message->course->id, 'm' => $message->id]),
            $event->get_url()
        );
        self::assertEquals(
            ['db' => 'local_mail_messages', 'restore' => 'local_mail_message'],
            event\message_sent::get_objectid_mapping()
        );
    }

    public function test_message_viewed(): void {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send(time());
        self::setUser($user2->id);

        $event = event\message_viewed::create_from_message($message);

        self::assertEquals(output\strings::get('eventmessageviewed'), event\message_viewed::get_name());
        self::assertEquals(
            "The user with id '$user2->id' has viewed the message with id '$message->id'.",
            $event->get_description()
        );
        self::assertEquals(
            new \moodle_url('/local/mail/view.php', ['t' => 'course', 'c' => $message->course->id, 'm' => $message->id]),
            $event->get_url()
        );
        self::assertEquals(
            ['db' => 'local_mail_messages', 'restore' => 'local_mail_message'],
            event\message_viewed::get_objectid_mapping()
        );
    }

    public function test_label_events(): void {
        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());
        self::setUser($user->id);
        $context = \context_user::instance($user->id);
        $sink = $this->redirectEvents();

        $labelid = external::create_label('Work', 'red');
        external::update_label($labelid, 'Office', 'blue');
        external::delete_label($labelid);

        $events = $sink->get_events();
        $sink->close();
        self::assertCount(3, $events);
        $expected = [
            [event\label_created::class, 'eventlabelcreated', 'created', 'c'],
            [event\label_updated::class, 'eventlabelupdated', 'updated', 'u'],
            [event\label_deleted::class, 'eventlabeldeleted', 'deleted', 'd'],
        ];
        foreach ($expected as $i => [$class, $string, $verb, $crud]) {
            $event = $events[$i];
            self::assertInstanceOf($class, $event);
            self::assertEquals(output\strings::get($string), $class::get_name());
            self::assertEquals($user->id, $event->userid);
            self::assertEquals($labelid, $event->objectid);
            self::assertEquals('local_mail_labels', $event->objecttable);
            self::assertEquals($crud, $event->crud);
            self::assertEquals($context->id, $event->contextid);
            self::assertEquals(
                "The user with id '$user->id' has $verb the label with id '$labelid'.",
                $event->get_description()
            );
            self::assertEquals(
                ['db' => 'local_mail_labels', 'restore' => \core\event\base::NOT_MAPPED],
                $class::get_objectid_mapping()
            );
        }
        self::assertEquals(new \moodle_url('/local/mail/view.php', ['t' => 'label', 'l' => $labelid]), $events[0]->get_url());
        self::assertEquals(new \moodle_url('/local/mail/view.php', ['t' => 'label', 'l' => $labelid]), $events[1]->get_url());
        self::assertNull($events[2]->get_url());
    }

    public function test_message_labels_updated(): void {
        [$course, $user1, $user2, $message] = self::sent_message();
        self::setUser($user2->id);
        $label = label::create($user2, 'Label');
        $sink = $this->redirectEvents();

        external::set_labels($message->id, [$label->id]);
        // A new label put on messages records each message it went on, after the label itself.
        $labelid = external::create_label('Other', '', [$message->id]);

        $events = $sink->get_events();
        $sink->close();
        self::assertCount(3, $events);
        self::assertInstanceOf(event\label_created::class, $events[1]);
        self::assertEquals($labelid, $events[1]->objectid);
        foreach ([$events[0], $events[2]] as $event) {
            self::assertInstanceOf(event\message_labels_updated::class, $event);
            self::assert_message_event_data($event, $message, $user2, 'u');
            self::assertEquals(
                "The user with id '$user2->id' has changed the labels of the message with id '$message->id'.",
                $event->get_description()
            );
        }
        self::assertEquals(output\strings::get('eventmessagelabelsupdated'), event\message_labels_updated::get_name());
        self::assertEquals(
            new \moodle_url('/local/mail/view.php', ['t' => 'course', 'c' => $course->id, 'm' => $message->id]),
            $events[0]->get_url()
        );
    }

    public function test_message_deleted_restored_and_deleted_forever(): void {
        [$course, $user1, $user2, $message] = self::sent_message();
        self::setUser($user2->id);
        $sink = $this->redirectEvents();

        external::set_deleted($message->id, message::DELETED);
        // The control for "nothing changed, nothing recorded": the same status again.
        external::set_deleted($message->id, message::DELETED);
        external::set_deleted($message->id, message::NOT_DELETED);
        external::set_deleted($message->id, message::DELETED_FOREVER);

        $events = $sink->get_events();
        $sink->close();
        self::assertCount(3, $events);
        $expected = [
            [event\message_deleted::class, 'eventmessagedeleted', 'u', 'moved the message with id \'%d\' to the trash',
                ['t' => 'trash', 'm' => $message->id]],
            [event\message_restored::class, 'eventmessagerestored', 'u', 'restored the message with id \'%d\' from the trash',
                ['t' => 'course', 'c' => $course->id, 'm' => $message->id]],
            [event\message_deleted_forever::class, 'eventmessagedeletedforever', 'd', 'deleted the message with id \'%d\' forever',
                null],
        ];
        foreach ($expected as $i => [$class, $string, $crud, $description, $url]) {
            $event = $events[$i];
            self::assertInstanceOf($class, $event);
            self::assertEquals(output\strings::get($string), $class::get_name());
            self::assert_message_event_data($event, $message, $user2, $crud);
            self::assertEquals(
                "The user with id '$user2->id' has " . sprintf($description, $message->id) . '.',
                $event->get_description()
            );
            self::assertEquals($url ? new \moodle_url('/local/mail/view.php', $url) : null, $event->get_url());
            self::assertEquals(
                ['db' => 'local_mail_messages', 'restore' => 'local_mail_message'],
                $class::get_objectid_mapping()
            );
        }
    }

    public function test_draft_deleted_forever(): void {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        $draft = message::create(message_data::new($course, $user));
        self::setUser($user->id);
        $sink = $this->redirectEvents();

        external::set_deleted($draft->id, message::DELETED_FOREVER);

        // A draft goes for everyone, so it keeps its own event and not the one of a sent message.
        $events = $sink->get_events();
        $sink->close();
        self::assertCount(1, $events);
        self::assertInstanceOf(event\draft_deleted::class, $events[0]);
        self::assertEquals($draft->id, $events[0]->objectid);
    }

    public function test_trash_emptied(): void {
        [$course, $user1, $user2, $message] = self::sent_message();
        $othercourse = new course(self::getDataGenerator()->create_course());
        self::getDataGenerator()->enrol_user($user2->id, $othercourse->id);
        $message->set_deleted($user2, message::DELETED);
        self::setUser($user2->id);
        $sink = $this->redirectEvents();

        external::empty_trash($othercourse->id);
        external::empty_trash();

        $events = $sink->get_events();
        $sink->close();
        self::assertCount(2, $events);
        self::assertEquals(output\strings::get('eventtrashemptied'), event\trash_emptied::get_name());
        foreach ($events as $event) {
            self::assertInstanceOf(event\trash_emptied::class, $event);
            self::assertEquals($user2->id, $event->userid);
            self::assertEquals('d', $event->crud);
            self::assertNull($event->objectid);
            self::assertNull($event->objecttable);
        }

        // One course: its context, and nothing of it was in the trash.
        self::assertEquals($othercourse->get_context()->id, $events[0]->contextid);
        self::assertEquals(0, $events[0]->other['count']);
        self::assertEquals(
            "The user with id '$user2->id' has emptied the trash of the course with id '$othercourse->id', "
                . 'deleting 0 messages forever.',
            $events[0]->get_description()
        );
        self::assertEquals(
            new \moodle_url('/local/mail/view.php', ['t' => 'trash', 'c' => $othercourse->id]),
            $events[0]->get_url()
        );

        // Every course: the user's context.
        self::assertEquals(\context_user::instance($user2->id)->id, $events[1]->contextid);
        self::assertEquals(1, $events[1]->other['count']);
        self::assertEquals(
            "The user with id '$user2->id' has emptied the trash, deleting 1 messages forever.",
            $events[1]->get_description()
        );
        self::assertEquals(new \moodle_url('/local/mail/view.php', ['t' => 'trash']), $events[1]->get_url());
        self::assertEquals([], event\trash_emptied::get_other_mapping());
    }

    /**
     * Creates a message sent by one enrolled user to another.
     *
     * @return array The course, the sender, the recipient and the message.
     */
    private static function sent_message(): array {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send(time());

        return [$course, $user1, $user2, $message];
    }

    /**
     * Asserts the data every event about one message of a course carries.
     *
     * @param \core\event\base $event The event.
     * @param message $message The message.
     * @param user $user The user who acted.
     * @param string $crud The expected CRUD type.
     */
    private static function assert_message_event_data(\core\event\base $event, message $message, user $user, string $crud): void {
        self::assertEquals($user->id, $event->userid);
        self::assertEquals($message->id, $event->objectid);
        self::assertEquals('local_mail_messages', $event->objecttable);
        self::assertEquals($crud, $event->crud);
        self::assertEquals($message->course->id, $event->courseid);
        self::assertEquals($message->course->get_context()->id, $event->contextid);
    }
}
