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
 * SPDX-FileCopyrightText: 2012-2013 Institut Obert de Catalunya <https://ioc.gencat.cat>
 * SPDX-FileCopyrightText: 2014-2021 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024-2026 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail\test;

use local_mail\course;
use local_mail\label;
use local_mail\message;
use local_mail\message_data;
use local_mail\message_search;
use local_mail\user;
use local_mail\user_search;

/**
 * Base class of the unit tests of the plugin, with shared assertions and data generators.
 *
 * @package    local_mail
 * @copyright  2012-2025 Institut Obert de Catalunya, Marc Català, Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class testcase extends \advanced_testcase {
    /**
     * Logs in as admin and empties the caches of the plugin before each test.
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        course::cache()->purge();
        label::cache()->purge();
    }

    /**
     * Asserts that an array of objects has the correct values and is indexed by the id property.
     *
     * @param mixed[] $expected Expected array of objects in the given order.
     * @param mixed[] $actual Actual array.
     * @param string $message Extra text appended to the failure message.
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    protected static function assert_array_of_objects(array $expected, array $actual, string $message = '') {
        $ids = array_column($expected, 'id');

        self::assertEquals(
            array_combine($ids, $expected),
            $actual,
            'Array of objects with incorrect items.' . ($message ? "\n$message" : ''),
        );
        self::assertEquals(
            $ids,
            array_keys($actual),
            'Array of objects with incorrect order.' . ($message ? "\n$message" : ''),
        );
    }

    /**
     * Asserts stored attachments.
     *
     * @param string[] $expected Files: filename => content.
     * @param message $message Message.
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    protected static function assert_attachments(array $expected, message $message) {
        $fs = get_file_storage();
        $contextid = $message->course->get_context()->id;
        $files = $fs->get_area_files($contextid, 'local_mail', 'message', $message->id, 'id', false);
        $actual = [];
        foreach ($files as $file) {
            $actual[$file->get_filename()] = $file->get_content();
        }
        self::assertEquals($expected, $actual);
    }

    /**
     * Asserts stored files.
     *
     * @param string[] $expected Files: filename => content.
     * @param int $draftitemid Draft item ID.
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    protected static function assert_draft_files(array $expected, int $draftitemid) {
        global $USER;

        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $actual = [];
        foreach ($fs->get_area_files($context->id, 'user', 'draft', $draftitemid, 'id', false) as $file) {
            $actual[$file->get_filename()] = $file->get_content();
        }
        self::assertEquals($expected, $actual);
    }

    /**
     * Asserts that a message is stored correctly in the database.
     *
     * @param message $message Message.
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    protected static function assert_message(message $message): void {
        $deleted = $message->deleted($message->sender()) == message::DELETED_CONTENT;
        self::assert_record_data('messages', [
            'id' => $message->id,
        ], [
            'courseid' => $message->course->id,
            'subject' => $deleted ? '' : $message->subject,
            'content' => $deleted ? '' : $message->content,
            'format' => $message->format,
            'attachments' => $message->attachments,
            'draft' => (int) $message->draft,
            'time' => $message->time,
            'normalizedsubject' => $deleted ? '' : message::normalize_text($message->subject, FORMAT_PLAIN),
            'normalizedcontent' => $deleted ? '' : message::normalize_text($message->content, $message->format),
        ]);

        $numusers = count($message->recipients()) + 1;
        self::assert_record_count($numusers, 'message_users', ['messageid' => $message->id]);

        $numlabels = count($message->get_labels($message->sender()));
        foreach ($message->recipients() as $user) {
            $numlabels += count($message->get_labels($user));
        }
        self::assert_record_count($numlabels, 'message_labels', ['messageid' => $message->id]);

        foreach ([$message->sender(), ...$message->recipients()] as $user) {
            $data = [
                'courseid' => $message->course->id,
                'draft' => (int) $message->draft,
                'time' => $message->time,
                'role' => $message->role($user),
                'unread' => (int) $message->unread($user),
                'starred' => (int) $message->starred($user),
                'deleted' => $message->deleted($user),
            ];
            self::assert_record_data('message_users', [
                'messageid' => $message->id,
                'userid' => $user->id,
            ], $data);
            foreach ($message->get_labels($user) as $label) {
                self::assert_record_data('message_labels', [
                    'messageid' => $message->id,
                    'labelid' => $label->id,
                ], $data);
            }
        }
    }

    /**
     * Asserts that sink eventc contains an event that matches a name and message.
     *
     * @param string $eventname Expected event name.
     * @param message $message Expected Message.
     * @param \phpunit_event_sink $sink Event sink.
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    protected static function assert_message_event(string $eventname, message $message, \phpunit_event_sink $sink): void {
        global $USER;

        $events = array_filter(
            $sink->get_events(),
            fn (\core\event\base $event) =>  $event->eventname != '\core\event\notification_viewed'
        );

        self::assertEquals(1, count($events));
        self::assertEquals($eventname, $events[0]->eventname);
        self::assertEquals($USER->id, $events[0]->userid);
        self::assertEquals($message->id, $events[0]->objectid);
        if ($message->draft) {
            self::assertEquals(0, $events[0]->courseid);
            self::assertEquals(\context_user::instance($USER->id)->id, $events[0]->contextid);
        } else {
            self::assertEquals($message->course->id, $events[0]->courseid);
            self::assertEquals($message->course->get_context()->id, $events[0]->contextid);
        }

        $sink->close();
    }

    /**
     * Asserts that the table contains this number of records matching the conditions.
     *
     * @param int $expected Expected number of rows.
     * @param string $table Table name without the "local_mail_" prefix.
     * @param mixed[] $conditions Array of field => value.
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    protected static function assert_record_count(int $expected, string $table, array $conditions = []) {
        global $DB;

        $actual = $DB->count_records('local_mail_' . $table, $conditions);

        self::assertEquals($expected, $actual);
    }

    /**
     * Asserts that the table contains a record matching the givem conditions and data.
     *
     * @param string $table Table name without the "local_mail_" prefix.
     * @param mixed[] $conditions Array of field => value.
     * @param mixed[] $data Array of field => value.
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    protected static function assert_record_data($table, array $conditions, array $data): void {
        global $DB;

        $records = $DB->get_records('local_mail_' . $table, $conditions);

        self::assertCount(1, $records);

        foreach ($records as $record) {
            $actualdata = [];
            foreach (array_keys($data) as $field) {
                $actualdata[$field] = $record->$field;
            }
            self::assertEquals($data, $actualdata);
        }
    }

    /**
     * Assert that a language strings exists.
     *
     * @param string $identifier String identifier.
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    protected static function assert_string_exists(string $identifier): void {
        self::assertTrue(
            get_string_manager()->string_exists($identifier, 'local_mail'),
            "String '$identifier' does not exist."
        );
    }

    /**
     * Creates a draft stored file.
     *
     * @param int $draftitemid Draft item ID.
     * @param string $filename File name.
     * @param string $content Content of the file.
     * @return \stored_file
     */
    protected static function create_draft_file(int $draftitemid, string $filename, string $content): \stored_file {
        global $USER;

        $fs = get_file_storage();

        $context = \context_user::instance($USER->id);

        $record = [
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ];

        return $fs->create_file_from_string($record, $content);
    }

    /**
     * Deletes draft stored files.
     *
     * @param int $draftitemid Draft item ID.
     */
    protected static function delete_draft_files(int $draftitemid) {
        global $USER;

        $fs = get_file_storage();

        $context = \context_user::instance($USER->id);

        return $fs->delete_area_files($context->id, 'user', 'draft', $draftitemid);
    }

    /**
     * Creates a message and returns it.
     *
     * The content of the message is "Content of" followed by the subject. The message is sent,
     * unless it has no recipients or $send is false, in which case it is left as a draft.
     *
     * @param course $course Course of the message.
     * @param user $sender Sender of the message.
     * @param user[] $to Users with role "to".
     * @param user[] $cc Users with role "cc".
     * @param user[] $bcc Users with role "bcc".
     * @param string $subject Subject.
     * @param int $time Timestamp.
     * @param string[] $files Files to attach, indexed by file name.
     * @param bool $send Whether to send the message.
     * @param ?string $component Component that generated the message, or null if a person composed it.
     * @return message Created message.
     */
    protected static function create_message(
        course $course,
        user $sender,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        int $time,
        array $files = [],
        bool $send = true,
        ?string $component = null,
    ): message {
        $data = message_data::new($course, $sender);
        $data->component = $component;
        $data->to = $to;
        $data->cc = $cc;
        $data->bcc = $bcc;
        $data->subject = $subject;
        $data->content = " <p> Content of $subject </p> ";
        $data->format = (int) FORMAT_HTML;
        $data->time = $time;
        foreach ($files as $filename => $content) {
            self::create_draft_file($data->draftitemid, $filename, $content);
        }

        $message = message::create($data);

        if ($send && ($to || $cc || $bcc)) {
            $message->send($time);
        }

        return $message;
    }

    /**
     * Creates users, courses, labels and messages for the tests.
     *
     * Data:
     *
     * - Role 1, without capabilities. Role 2, with "local/mail:usemail". Role 3, with
     *   "local/mail:usemail" and "local/mail:mailsamerole".
     * - Course 1, with visible groups: group 1 (Bob, Henry, Frank and Gus) and group 2 (Anna
     *   Kelly and John). Courses 2 and 3, with separate groups. Group 3 in course 3 (Henry).
     *   Courses 4 and 5, without groups. No messages in course 4.
     * - User 1 (Alice Anderson), with no courses, no labels and no messages.
     * - User 2 (Bob Brown), enrolled in courses 1, 2 and 4 with role 2, with labels "Work",
     *   "Family" and "Other". Label "Other" has no messages. Participates in most messages.
     *   Member of group 1.
     * - User 3 (Carol Clark), enrolled in course 1 with role 3 and in course 2 with role 2,
     *   with label "Work".
     * - User 4 (David Davis), enrolled in course 1 with role 3 and in course 2 with role 2,
     *   with labels "Work" and "Friends", both without messages.
     * - User 5 (Eve Evans), enrolled in course 2 with role 2, without labels.
     * - User 6 (Frank Foster), enrolled in course 1 with role 1, so he has no
     *   "local/mail:usemail" capability and course 1 is not included in his searches.
     *   With label "Misc". Member of group 1.
     * - User 7 (Grace Green), enrolled in courses 1 and 3 with role 2, without labels.
     * - User 8 (Henry Hill), enrolled in courses 1 and 3 with role 3, with label "Work".
     *   Member of groups 1 and 3.
     * - User 9 (Ivy Irving), enrolled in courses 2 and 3 with role 2, with labels "Work" and
     *   "Friends".
     * - User 10 (John Jones), enrolled in course 1 with role 2, deleted. Sender of message 6
     *   and BCC recipient of messages 5 and 25. Member of group 2.
     * - User 11 (Karen King), enrolled in course 5 with role 2, with label "Archive". Sender
     *   of message 45, with deleted content, so course 5 is not included in her contexts.
     * - User 12 (Liam Lane), enrolled in course 5 with role 2, without labels. Recipient of
     *   messages 45 and 46. Message 45 is deleted forever, so it is not included in his
     *   privacy data.
     * - User 13 (Martin Moore), enrolled in course 5 with role 2, without labels. Sender of
     *   message 46, deleted forever, which is included in his privacy data because he is the
     *   sender.
     * - User 14 (Gus Novak), enrolled in course 1 with role 2 and suspended enrolment, without
     *   labels and without messages. Member of group 1.
     * - User 15 (Anna Garcia), enrolled in course 1 with role 2, without labels and without
     *   messages.
     * - User 16 (Anna Kelly), enrolled in course 1 with role 3, without labels and without
     *   messages. Member of group 2.
     * - Messages 1 to 46, in courses 1, 2, 3 and 5, ordered from older to newer, each one with a
     *   different time. Messages 7 to 10, 21, 26 and 44 are drafts. Message 14 is a reply to
     *   message 3. Messages 3, 24, 35, 37 and 41 are generated by a component, so they belong
     *   to the "updates" category; every other message belongs to "primary", so Bob's reply to
     *   message 3 is a person's mail referencing a generated one.
     *   See the code below for further details.
     *
     * @return mixed[] Array with keys: users, courses, groups, roles, labels and messages.
     */
    public static function generate_data(): array {
        global $DB;

        $generator = self::getDataGenerator();

        // Roles.

        foreach (get_roles_with_capability('local/mail:usemail') as $role) {
            unassign_capability('local/mail:usemail', $role->id);
            unassign_capability('local/mail:mailsamerole', $role->id);
        }
        $rolenone = $generator->create_role();
        $generator->create_role_capability($rolenone, [], \context_system::instance());
        $roleusemail = $generator->create_role();
        $generator->create_role_capability(
            $roleusemail,
            ['local/mail:usemail' => 'allow'],
            \context_system::instance(),
        );
        $roleboth = $generator->create_role();
        $generator->create_role_capability(
            $roleboth,
            ['local/mail:usemail' => 'allow', 'local/mail:mailsamerole' => 'allow'],
            \context_system::instance(),
        );

        // Courses.

        $course1 = new course($generator->create_course(['groupmode' => VISIBLEGROUPS]));
        $course2 = new course($generator->create_course(['groupmode' => SEPARATEGROUPS]));
        $course3 = new course($generator->create_course(['groupmode' => SEPARATEGROUPS]));
        $course4 = new course($generator->create_course());
        $course5 = new course($generator->create_course());

        // Groups.

        $group1 = $generator->create_group(['courseid' => $course1->id]);
        $group2 = $generator->create_group(['courseid' => $course1->id]);
        $group3 = $generator->create_group(['courseid' => $course3->id]);

        // Users.

        $user1 = new user($generator->create_user(['firstname' => 'Alice', 'lastname' => 'Anderson']));
        $user2 = new user($generator->create_user(['firstname' => 'Bob', 'lastname' => 'Brown']));
        $user3 = new user($generator->create_user(['firstname' => 'Carol', 'lastname' => 'Clark']));
        $user4 = new user($generator->create_user(['firstname' => 'David', 'lastname' => 'Davis']));
        $user5 = new user($generator->create_user(['firstname' => 'Eve', 'lastname' => 'Evans']));
        $user6 = new user($generator->create_user(['firstname' => 'Frank', 'lastname' => 'Foster']));
        $user7 = new user($generator->create_user(['firstname' => 'Grace', 'lastname' => 'Green']));
        $user8 = new user($generator->create_user(['firstname' => 'Henry', 'lastname' => 'Hill']));
        $user9 = new user($generator->create_user(['firstname' => 'Ivy', 'lastname' => 'Irving']));
        $user10 = new user($generator->create_user(['firstname' => 'John', 'lastname' => 'Jones']));
        $user11 = new user($generator->create_user(['firstname' => 'Karen', 'lastname' => 'King']));
        $user12 = new user($generator->create_user(['firstname' => 'Liam', 'lastname' => 'Lane']));
        $user13 = new user($generator->create_user(['firstname' => 'Martin', 'lastname' => 'Moore']));
        $user14 = new user($generator->create_user(['firstname' => 'Gus', 'lastname' => 'Novak']));
        $user15 = new user($generator->create_user(['firstname' => 'Anna', 'lastname' => 'Garcia']));
        $user16 = new user($generator->create_user(['firstname' => 'Anna', 'lastname' => 'Kelly']));

        $generator->enrol_user($user2->id, $course1->id, $roleusemail);
        $generator->enrol_user($user2->id, $course2->id, $roleusemail);
        $generator->enrol_user($user2->id, $course4->id, $roleusemail);
        $generator->enrol_user($user3->id, $course1->id, $roleboth);
        $generator->enrol_user($user3->id, $course2->id, $roleusemail);
        $generator->enrol_user($user4->id, $course1->id, $roleboth);
        $generator->enrol_user($user4->id, $course2->id, $roleusemail);
        $generator->enrol_user($user5->id, $course2->id, $roleusemail);
        $generator->enrol_user($user6->id, $course1->id, $rolenone);
        $generator->enrol_user($user7->id, $course3->id, $roleusemail);
        $generator->enrol_user($user7->id, $course1->id, $roleusemail);
        $generator->enrol_user($user14->id, $course1->id, $roleusemail, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->enrol_user($user15->id, $course1->id, $roleusemail);
        $generator->enrol_user($user16->id, $course1->id, $roleboth);
        $generator->enrol_user($user8->id, $course1->id, $roleboth);
        $generator->enrol_user($user8->id, $course3->id, $roleboth);
        $generator->enrol_user($user9->id, $course2->id, $roleusemail);
        $generator->enrol_user($user9->id, $course3->id, $roleusemail);
        $generator->enrol_user($user10->id, $course1->id, $roleusemail);
        $generator->enrol_user($user11->id, $course5->id, $roleusemail);
        $generator->enrol_user($user12->id, $course5->id, $roleusemail);
        $generator->enrol_user($user13->id, $course5->id, $roleusemail);

        // Group members.

        $generator->create_group_member(['userid' => $user2->id, 'groupid' => $group1->id]);
        $generator->create_group_member(['userid' => $user8->id, 'groupid' => $group1->id]);
        $generator->create_group_member(['userid' => $user6->id, 'groupid' => $group1->id]);
        $generator->create_group_member(['userid' => $user14->id, 'groupid' => $group1->id]);
        $generator->create_group_member(['userid' => $user16->id, 'groupid' => $group2->id]);
        $generator->create_group_member(['userid' => $user10->id, 'groupid' => $group2->id]);
        $generator->create_group_member(['userid' => $user8->id, 'groupid' => $group3->id]);

        // Mark user 10 as deleted.

        $DB->set_field('user', 'deleted', 1, ['id' => $user10->id]);
        $user10 = new user((object) ['id' => $user10->id, 'deleted' => 1]);

        // Labels.

        $labelwork2 = label::create($user2, 'Work', 'blue');
        $labelfamily2 = label::create($user2, 'Family', 'green');
        $labelother2 = label::create($user2, 'Other');
        $labelwork3 = label::create($user3, 'Work', 'blue');
        $labelwork4 = label::create($user4, 'Work', 'blue');
        $labelfriends4 = label::create($user4, 'Friends', 'orange');
        $labelmisc6 = label::create($user6, 'Misc');
        $labelwork8 = label::create($user8, 'Work', 'blue');
        $labelwork9 = label::create($user9, 'Work', 'blue');
        $labelfriends9 = label::create($user9, 'Friends', 'orange');
        $labelarchive11 = label::create($user11, 'Archive', 'gray');

        // Messages.

        $time = make_timestamp(2021, 10, 11, 12, 0);
        $messages = [];

        // Message 1: From Bob to Carol, in course 1. Starred by Carol.
        $messages[] = $message = self::create_message($course1, $user2, [$user3], [], [], 'Subject 1', $time++);
        $message->set_starred($user3, true);

        // Message 2: From Bob to Carol and David, CC to Henry, in course 1.
        // Read by David and starred by Bob.
        $messages[] = $message = self::create_message(
            $course1,
            $user2,
            [$user3, $user4],
            [$user8],
            [],
            'Subject 2',
            $time++
        );
        $message->set_unread($user4, false);
        $message->set_starred($user2, true);

        // Message 3: From Carol to Bob, in course 1, generated by mod_forum. Starred by Bob, with
        // label "Work" for Bob and Carol.
        $messages[] = $message3 = self::create_message(
            $course1,
            $user3,
            [$user2],
            [],
            [],
            'Subject 3',
            $time++,
            component: 'mod_forum'
        );
        $message3->set_starred($user2, true);
        $message3->set_labels($user2, [$labelwork2]);
        $message3->set_labels($user3, [$labelwork3]);

        // Message 4: From David to Bob, CC to Carol, in course 1. With label "Work" for Bob.
        $messages[] = $message = self::create_message($course1, $user4, [$user2], [$user3], [], 'Subject 4', $time++);
        $message->set_labels($user2, [$labelwork2]);

        // Message 5: From Bob to David, BCC to John, in course 1.
        $messages[] = self::create_message($course1, $user2, [$user4], [], [$user10], 'Subject 5', $time++);

        // Message 6: From John to Bob, in course 1. Read by Bob.
        $messages[] = $message = self::create_message($course1, $user10, [$user2], [], [], 'Subject 6', $time++);
        $message->set_unread($user2, false);

        // Message 7: Draft from Bob, without recipients, in course 1.
        $messages[] = self::create_message($course1, $user2, [], [], [], 'Draft 1', $time++, [], false);

        // Message 8: Draft from Bob to Carol, in course 1. Starred by Bob, with label "Family".
        $messages[] = $message = self::create_message(
            $course1,
            $user2,
            [$user3],
            [],
            [],
            'Draft 2',
            $time++,
            [],
            false
        );
        $message->set_starred($user2, true);
        $message->set_labels($user2, [$labelfamily2]);

        // Message 9: Draft from Frank to Bob, in course 1.
        $messages[] = self::create_message($course1, $user6, [$user2], [], [], 'Draft 3', $time++, [], false);

        // Message 10: Draft from Carol, without recipients, with a file, in course 1.
        $messages[] = self::create_message(
            $course1,
            $user3,
            [],
            [],
            [],
            'Draft 4',
            $time++,
            ['file.txt' => 'File'],
            false
        );

        // Message 11: From Henry to Bob and Frank, in course 1. With label "Family" for Bob.
        // Deleted by Frank, with label "Misc".
        $messages[] = $message = self::create_message(
            $course1,
            $user8,
            [$user2, $user6],
            [],
            [],
            'Subject 11',
            $time++
        );
        $message->set_labels($user2, [$labelfamily2]);
        $message->set_labels($user6, [$labelmisc6]);
        $message->set_deleted($user6, message::DELETED);

        // Message 12: From Bob to Henry, with two files, in course 1.
        $messages[] = self::create_message(
            $course1,
            $user2,
            [$user8],
            [],
            [],
            'Subject 12',
            $time++,
            ['file1.txt' => 'File 1', 'file2.txt' => 'File 2']
        );

        // Message 13: From Carol to Bob, in course 1. Deleted by Bob.
        $messages[] = $message = self::create_message($course1, $user3, [$user2], [], [], 'Subject 13', $time++);
        $message->set_deleted($user2, message::DELETED);

        // Message 14: Reply from Bob to Carol of message 3, in course 1. With label "Work" for
        // Bob and Carol, copied from message 3.
        $replydata = message_data::reply($message3, $user2, false);
        $replydata->content = " <p> Content of {$replydata->subject} </p> ";
        $replydata->format = (int) FORMAT_HTML;
        $replydata->time = $time++;
        $messages[] = $reply = message::create($replydata);
        $reply->send($replydata->time);

        // Message 15: From David to Bob, in course 1. Read, starred and with label "Work" by Bob.
        $messages[] = $message = self::create_message($course1, $user4, [$user2], [], [], 'Subject 15', $time++);
        $message->set_unread($user2, false);
        $message->set_starred($user2, true);
        $message->set_labels($user2, [$labelwork2]);

        // Message 16: From Bob to Carol, in course 1. Content deleted by the sender.
        $messages[] = $message = self::create_message($course1, $user2, [$user3], [], [], 'Subject 16', $time++);
        $message->set_deleted($user2, message::DELETED_CONTENT);

        // Message 17: From Bob to Eve, in course 2. With label "Work" for Bob.
        $messages[] = $message = self::create_message($course2, $user2, [$user5], [], [], 'Subject 17', $time++);
        $message->set_labels($user2, [$labelwork2]);

        // Message 18: From Eve to Bob, in course 2. Deleted forever by Bob.
        $messages[] = $message = self::create_message($course2, $user5, [$user2], [], [], 'Subject 18', $time++);
        $message->set_deleted($user2, message::DELETED_FOREVER);

        // Message 19: From Ivy to Eve, CC to Bob, in course 2.
        $messages[] = self::create_message($course2, $user9, [$user5], [$user2], [], 'Subject 19', $time++);

        // Message 20: From Bob to Ivy, BCC to Eve, in course 2. Starred by Bob, with label "Work"
        // for Ivy.
        $messages[] = $message = self::create_message(
            $course2,
            $user2,
            [$user9],
            [],
            [$user5],
            'Subject 20',
            $time++
        );
        $message->set_starred($user2, true);
        $message->set_labels($user9, [$labelwork9]);

        // Message 21: Draft from Ivy, without recipients, in course 2.
        $messages[] = self::create_message($course2, $user9, [], [], [], 'Draft 5', $time++, [], false);

        // Message 22: From Carol to Eve, BCC to Bob, with a file, in course 2.
        // With label "Family" for Bob.
        $messages[] = $message = self::create_message(
            $course2,
            $user3,
            [$user5],
            [],
            [$user2],
            'Subject 22',
            $time++,
            ['file.txt' => 'File']
        );
        $message->set_labels($user2, [$labelfamily2]);

        // Message 23: From Grace to Bob and Henry, in course 3. Bob is not enrolled in course 3.
        // With label "Work" for Henry.
        $messages[] = $message = self::create_message($course3, $user7, [$user2, $user8], [], [], 'Subject 23', $time++);
        $message->set_labels($user8, [$labelwork8]);

        // Message 24: From Henry to Grace and Ivy, in course 3, generated by mod_forum. Starred and
        // with label "Friends" for Ivy.
        $messages[] = $message = self::create_message(
            $course3,
            $user8,
            [$user7, $user9],
            [],
            [],
            'Subject 24',
            $time++,
            component: 'mod_forum'
        );
        $message->set_starred($user9, true);
        $message->set_labels($user9, [$labelfriends9]);

        // Message 25: From Ivy to Henry, BCC to John, in course 3. Deleted by Henry, with label
        // "Work".
        $messages[] = $message = self::create_message(
            $course3,
            $user9,
            [$user8],
            [],
            [$user10],
            'Subject 25',
            $time++
        );
        $message->set_labels($user8, [$labelwork8]);
        $message->set_deleted($user8, message::DELETED);

        // Message 26: Draft from Grace to Ivy, in course 3.
        $messages[] = self::create_message($course3, $user7, [$user9], [], [], 'Draft 6', $time++, [], false);

        // Messages 27 to 34: From Bob to Carol and David, alternately, in course 1.
        for ($i = 27; $i <= 34; $i++) {
            $messages[] = self::create_message(
                $course1,
                $user2,
                $i % 2 ? [$user3] : [$user4],
                [],
                [],
                "Subject $i",
                $time++
            );
        }

        // Messages 35 to 38: From Carol and David to Bob, alternately, in course 1.
        // Message 35 is read by Bob, message 36 is starred, message 37 has label "Work" and
        // message 38 has a file. Messages 35 and 37 are generated by mod_assign and mod_forum,
        // so Bob has a read and an unread update, the latter with a label.
        $messages[] = $message = self::create_message(
            $course1,
            $user3,
            [$user2],
            [],
            [],
            'Subject 35',
            $time++,
            component: 'mod_assign'
        );
        $message->set_unread($user2, false);
        $messages[] = $message = self::create_message($course1, $user4, [$user2], [], [], 'Subject 36', $time++);
        $message->set_starred($user2, true);
        $messages[] = $message = self::create_message(
            $course1,
            $user3,
            [$user2],
            [],
            [],
            'Subject 37',
            $time++,
            component: 'mod_forum'
        );
        $message->set_labels($user2, [$labelwork2]);
        $messages[] = self::create_message(
            $course1,
            $user4,
            [$user2],
            [],
            [],
            'Subject 38',
            $time++,
            ['file.txt' => 'File']
        );

        // Message 39: From Bob to Carol, in course 2.
        $messages[] = self::create_message($course2, $user2, [$user3], [], [], 'Subject 39', $time++);

        // Message 40: From Bob to Eve, with a file, in course 2.
        $messages[] = self::create_message($course2, $user2, [$user5], [], [], 'Subject 40', $time++, ['file.txt' => 'File']);

        // Message 41: From Frank to Bob, in course 1, generated by core.
        $messages[] = self::create_message(
            $course1,
            $user6,
            [$user2],
            [],
            [],
            'Subject 41',
            $time++,
            component: 'moodle'
        );

        // Message 42: From Bob to Carol, CC to David, BCC to Henry, in course 1.
        $messages[] = self::create_message($course1, $user2, [$user3], [$user4], [$user8], 'Subject 42', $time++);
        // Message 43: From Carol to Bob and Ivy, in course 2. With label "Work" for Bob.
        $messages[] = $message = self::create_message($course2, $user3, [$user2, $user9], [], [], 'Subject 43', $time++);
        $message->set_labels($user2, [$labelwork2]);

        // Message 44: Draft from Bob, without recipients, in course 2. With label "Work" for Bob.
        $messages[] = $message = self::create_message($course2, $user2, [], [], [], 'Draft 7', $time++, [], false);
        $message->set_labels($user2, [$labelwork2]);

        // Message 45: From Karen to Liam, in course 5. Content deleted by the sender (Karen)
        // and deleted forever by the recipient (Liam). Neither of them has other data in
        // course 5, so the course is not included in the privacy contexts of Karen.
        $messages[] = $message = self::create_message($course5, $user11, [$user12], [], [], 'Subject 45', $time++);
        $message->set_deleted($user11, message::DELETED_CONTENT);
        $message->set_deleted($user12, message::DELETED_FOREVER);

        // Message 46: From Martin to Liam, in course 5. Deleted forever by the sender (Martin),
        // who is still included in the privacy data of course 5 because he is the sender.
        $messages[] = $message = self::create_message($course5, $user13, [$user12], [], [], 'Subject 46', $time++);
        $message->set_deleted($user13, message::DELETED_FOREVER);

        course::cache()->purge();
        label::cache()->purge();

        return [
            'users' => [
                $user1, $user2, $user3, $user4, $user5, $user6, $user7, $user8, $user9,
                $user10, $user11, $user12, $user13, $user14, $user15, $user16,
            ],
            'courses' => [$course1, $course2, $course3, $course4, $course5],
            'groups' => [$group1, $group2, $group3],
            'roles' => [$rolenone, $roleusemail, $roleboth],
            'labels' => [
                $labelwork2, $labelfamily2, $labelother2, $labelwork3, $labelwork4, $labelfriends4,
                $labelmisc6, $labelwork8, $labelwork9, $labelfriends9, $labelarchive11,
            ],
            'messages' => $messages,
        ];
    }

    /**
     * Returns message search cases for the given data.
     *
     * The first cases are common to all users. The rest are specific cases for user 2 (Bob).
     *
     * @param mixed[] $data Data returned by generate_data().
     * @return message_search[] Array of search parameters.
     */
    public static function messages_search_cases(array $data): array {
        $users = $data['users'];
        $messages = $data['messages'];
        $maxtimemessage = $messages[15];
        $startmessage = $messages[19];
        $stopmessage = $messages[29];
        $cases = [];

        foreach ($users as $user) {
            // All messages.
            $cases[] = new message_search($user);

            // Inbox.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $cases[] = $search;

            // Unread inbox.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $search->unread = true;
            $cases[] = $search;

            // Primary, the inbox once generated mail is separated out.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $search->category = message::CATEGORY_PRIMARY;
            $cases[] = $search;

            // Updates.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $search->category = message::CATEGORY_UPDATES;
            $cases[] = $search;

            // Unread updates, the count behind the tray badge.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $search->category = message::CATEGORY_UPDATES;
            $search->unread = true;
            $cases[] = $search;

            /*
             * A category combined with a label. Nothing in the interface asks for this,
             * but it is the one combination that reaches the message table instead of
             * the denormalized column, so it needs to agree with the rest.
             */
            foreach (label::get_by_user($user) as $label) {
                $search = new message_search($user);
                $search->category = message::CATEGORY_UPDATES;
                $search->label = $label;
                $cases[] = $search;
            }

            // Starred.
            $search = new message_search($user);
            $search->starred = true;
            $cases[] = $search;

            // Sent.
            $search = new message_search($user);
            $search->draft = false;
            $search->roles = [message::ROLE_FROM];
            $cases[] = $search;

            // Drafts.
            $search = new message_search($user);
            $search->draft = true;
            $search->roles = [message::ROLE_FROM];
            $cases[] = $search;

            // Trash.
            $search = new message_search($user);
            $search->deleted = true;
            $cases[] = $search;

            // Course.
            foreach (course::get_by_user($user) as $course) {
                $search = new message_search($user);
                $search->course = $course;
                $cases[] = $search;
            }

            // Label.
            foreach (label::get_by_user($user) as $label) {
                $search = new message_search($user);
                $search->label = $label;
                $cases[] = $search;
            }

            // Content.
            $search = new message_search($user);
            $search->content = 'Subject';
            $cases[] = $search;

            // Sender name.
            $search = new message_search($user);
            $search->sendername = 'Carol Clark';
            $cases[] = $search;

            // Recipient name.
            $search = new message_search($user);
            $search->recipientname = 'Bob Brown';
            $cases[] = $search;

            // With files only.
            $search = new message_search($user);
            $search->withfilesonly = true;
            $cases[] = $search;

            // Max time.
            $search = new message_search($user);
            $search->maxtime = $maxtimemessage->time;
            $cases[] = $search;

            // Start message.
            $search = new message_search($user);
            $search->startid = $startmessage->id;
            $cases[] = $search;

            // Stop message.
            $search = new message_search($user);
            $search->stopid = $stopmessage->id;
            $cases[] = $search;

            // Reverse.
            $search = new message_search($user);
            $search->reverse = true;
            $cases[] = $search;

            // Start message and reverse.
            $search = new message_search($user);
            $search->startid = $startmessage->id;
            $search->reverse = true;
            $cases[] = $search;

            // Stop message and reverse.
            $search = new message_search($user);
            $search->stopid = $stopmessage->id;
            $search->reverse = true;
            $cases[] = $search;

            // Impossible search: drafts cannot be received. No results.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO];
            $search->draft = true;
            $cases[] = $search;
        }

        // Specific cases for user 2 (Bob).

        $user2 = $users[1];

        // Content, matching the subject and the content of several messages.
        $search = new message_search($user2);
        $search->content = 'Subject 1';
        $cases[] = $search;

        // Content, matching the subject of the reply to message 3.
        $search = new message_search($user2);
        $search->content = 'RE: Subject 3';
        $cases[] = $search;

        // Content, matching the original subject of the message with deleted content.
        // No results.
        $search = new message_search($user2);
        $search->content = 'Subject 16';
        $cases[] = $search;

        // Content, matching drafts.
        $search = new message_search($user2);
        $search->content = 'Draft';
        $cases[] = $search;

        // Content, matching the name of a recipient.
        $search = new message_search($user2);
        $search->content = 'Carol';
        $cases[] = $search;

        // Content, matching the name of a deleted user. No results.
        $search = new message_search($user2);
        $search->content = 'Jones';
        $cases[] = $search;

        // Content, with no matches. No results.
        $search = new message_search($user2);
        $search->content = 'xyz';
        $cases[] = $search;

        // Sender name, of a user with no messages. No results.
        $search = new message_search($user2);
        $search->sendername = 'Alice Anderson';
        $cases[] = $search;

        // Sender name, partial.
        $search = new message_search($user2);
        $search->sendername = 'Bob';
        $cases[] = $search;

        // Sender name, of a deleted user. No results.
        $search = new message_search($user2);
        $search->sendername = 'John Jones';
        $cases[] = $search;

        // Recipient name, partial.
        $search = new message_search($user2);
        $search->recipientname = 'Henry';
        $cases[] = $search;

        // Recipient name.
        $search = new message_search($user2);
        $search->recipientname = 'Eve Evans';
        $cases[] = $search;

        // Recipient name, of a deleted user who is only a BCC recipient. No results.
        $search = new message_search($user2);
        $search->recipientname = 'John Jones';
        $cases[] = $search;

        // Recipient name, with no matches. No results.
        $search = new message_search($user2);
        $search->recipientname = 'xyz';
        $cases[] = $search;

        // Course and trash.
        $search = new message_search($user2);
        $search->course = $data['courses'][0];
        $search->deleted = true;
        $cases[] = $search;

        // Course and label.
        $search = new message_search($user2);
        $search->course = $data['courses'][0];
        $search->label = $data['labels'][0];
        $cases[] = $search;

        // Course, starred and content.
        $search = new message_search($user2);
        $search->course = $data['courses'][1];
        $search->starred = true;
        $search->content = 'Subject';
        $cases[] = $search;

        // Label and reverse.
        $search = new message_search($user2);
        $search->label = $data['labels'][0];
        $search->reverse = true;
        $cases[] = $search;

        // Unread and with files only.
        $search = new message_search($user2);
        $search->unread = true;
        $search->withfilesonly = true;
        $cases[] = $search;

        return $cases;
    }

    /**
     * Returns user search cases for the given data.
     *
     * The searchers are user 2 (Bob Brown), without "local/mail:mailsamerole" capability, and
     * user 8 (Henry Hill), with it. Both search in course 1. See generate_data() for the data.
     *
     * @param mixed[] $data Data returned by generate_data().
     * @return mixed[] Array of tuples with a user search and the expected users.
     */
    public static function user_search_cases(array $data): array {
        ['users' => $users, 'courses' => $courses, 'groups' => $groups, 'roles' => $roles] = $data;

        [$alice, $bob, $carol, $david, $eve, $frank, $grace, $henry, $ivy, $john, $karen, $liam,
            $martin, $gus, $annagarcia, $annakelly] = $users;
        [$course1, $course2, $course3, $course4, $course5] = $courses;
        [$group1, $group2, $group3] = $groups;
        [$rolenone, $roleusemail, $roleboth] = $roles;

        $cases = [];

        // All users, searcher without "local/mail:mailsamerole" capability.
        $search = new user_search($bob, $course1);
        $cases[] = [$search, [$carol, $david, $henry, $annakelly]];

        // All users, searcher with "local/mail:mailsamerole" capability.
        $search = new user_search($henry, $course1);
        $cases[] = [$search, [$bob, $carol, $david, $annagarcia, $grace, $annakelly]];

        // Role with "local/mail:usemail" only, so users with the same role are excluded.
        $search = new user_search($bob, $course1);
        $search->roleid = $roleusemail;
        $cases[] = [$search, []];

        // Role with "local/mail:usemail" and "local/mail:mailsamerole".
        $search = new user_search($bob, $course1);
        $search->roleid = $roleboth;
        $cases[] = [$search, [$carol, $david, $henry, $annakelly]];

        // Role, searcher with "local/mail:mailsamerole" capability.
        $search = new user_search($henry, $course1);
        $search->roleid = $roleusemail;
        $cases[] = [$search, [$bob, $annagarcia, $grace]];

        // Role without "local/mail:usemail" capability.
        $search = new user_search($henry, $course1);
        $search->roleid = $rolenone;
        $cases[] = [$search, []];

        // Group, searcher without "local/mail:mailsamerole" capability.
        $search = new user_search($bob, $course1);
        $search->groupid = $group1->id;
        $cases[] = [$search, [$henry]];

        // Group, searcher with "local/mail:mailsamerole" capability.
        $search = new user_search($henry, $course1);
        $search->groupid = $group1->id;
        $cases[] = [$search, [$bob]];

        // Group with a deleted user.
        $search = new user_search($bob, $course1);
        $search->groupid = $group2->id;
        $cases[] = [$search, [$annakelly]];

        $search = new user_search($henry, $course1);
        $search->groupid = $group2->id;
        $cases[] = [$search, [$annakelly]];

        // Full name.
        $search = new user_search($bob, $course1);
        $search->fullname = 'Anna';
        $cases[] = [$search, [$annakelly]];

        $search = new user_search($henry, $course1);
        $search->fullname = 'Anna';
        $cases[] = [$search, [$annagarcia, $annakelly]];

        // Full name, case-insensitive.
        $search = new user_search($bob, $course1);
        $search->fullname = 'anna';
        $cases[] = [$search, [$annakelly]];

        // Partial full name.
        $search = new user_search($bob, $course1);
        $search->fullname = 'Anna K';
        $cases[] = [$search, [$annakelly]];

        // Full name with no matches.
        $search = new user_search($bob, $course1);
        $search->fullname = 'xyz';
        $cases[] = [$search, []];

        // Include, with a user that has the same role as the searcher.
        $search = new user_search($bob, $course1);
        $search->include = [$carol->id, $grace->id];
        $cases[] = [$search, [$carol]];

        // Include, with a user without "local/mail:usemail" capability.
        $search = new user_search($henry, $course1);
        $search->include = [$carol->id, $frank->id];
        $cases[] = [$search, [$carol]];

        // Include, with a user of another course.
        $search = new user_search($bob, $course1);
        $search->include = [$ivy->id];
        $cases[] = [$search, []];

        // Other course.
        $search = new user_search($henry, $course3);
        $cases[] = [$search, [$grace, $ivy]];

        // All filters combined.
        $search = new user_search($henry, $course1);
        $search->roleid = $roleusemail;
        $search->groupid = $group1->id;
        $search->fullname = 'Bob';
        $search->include = [$bob->id, $frank->id];
        $cases[] = [$search, [$bob]];

        // Group in a course with separate groups.
        $search = new user_search($ivy, $course3);
        $search->groupid = $group3->id;
        $cases[] = [$search, [$henry]];

        return $cases;
    }
}
