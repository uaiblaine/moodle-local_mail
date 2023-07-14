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

namespace local_mail;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/testcase.php');

/**
 * @covers \local_mail\message_data
 */
class message_data_test extends testcase {

    public function test_draft() {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $course = new course($generator->create_course());

        $data = message_data::new($course, $user1);
        $data->to = [$user2, $user3];
        $data->cc = [$user4];
        $data->bcc = [$user5];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = make_timestamp(2021, 10, 11, 12, 0);
        self::create_draft_file($data->draftitemid, 'file1.txt', 'File 1');
        self::create_draft_file($data->draftitemid, 'file2.txt', 'File 2');

        $message = message::create($data);

        $data = message_data::draft($message);
        self::assertEquals($message->sender(), $data->sender);
        self::assertNull($data->reference);
        self::assertEquals($message->course, $data->course);
        self::assertEquals([$user2, $user3], $data->to);
        self::assertEquals([$user4], $data->cc);
        self::assertEquals([$user5], $data->bcc);
        self::assertEquals('Subject', $data->subject);
        self::assertEquals('Content', $data->content);
        self::assertEquals((int) FORMAT_PLAIN, $data->format);
        self::assert_draft_files(['file1.txt' => 'File 1', 'file2.txt' => 'File 2'], $data->draftitemid);
        self::assertEquals($message->time, $data->time);
    }

    public function test_forward() {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $course = new course($generator->create_course());
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $now = time();

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        self::create_draft_file($data->draftitemid, 'file1.txt', 'File 1');
        self::create_draft_file($data->draftitemid, 'file2.txt', 'File 2');
        $message = message::create($data);
        $message->send($time);

        $data = message_data::forward($message, $user2);
        self::assertEquals($user2, $data->sender);
        self::assertEquals($message, $data->reference);
        self::assertEquals($message->course, $data->course);
        self::assertEquals([], $data->to);
        self::assertEquals([], $data->cc);
        self::assertEquals([], $data->bcc);
        self::assertEquals('FW: Subject', $data->subject);
        self::assertEquals('', $data->content);
        self::assertEquals((int) FORMAT_HTML, $data->format);
        self::assertGreaterThan(0, $data->draftitemid);
        self::assert_draft_files([], $data->draftitemid);
        self::assertGreaterThanOrEqual($now, $data->time);

        // Forward forwarded message.
        $data->to = [$user1];
        $message = message::create($data);
        $message->send($time);
        $data = message_data::forward($message, $user2);
        self::assertEquals('FW: Subject', $data->subject);
    }

    public function test_reply() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 13, 0);
        $now = time();
        $data = message_data::new($course, $user1);
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = FORMAT_PLAIN;
        $data->to = [$user2, $user3];
        $data->cc = [$user4];
        $data->bcc = [$user5];
        $message = message::create($data);
        $message->send($time1);

        // Reply to sender.

        $data = message_data::reply($message, $user2, false);

        self::assertEquals($user2, $data->sender);
        self::assertEquals($message, $data->reference);
        self::assertEquals($message->course, $data->course);
        self::assertEquals([$user1], $data->to);
        self::assertEquals([], $data->cc);
        self::assertEquals([], $data->bcc);
        self::assertEquals('RE: Subject', $data->subject);
        self::assertEquals('', $data->content);
        self::assertEquals((int) FORMAT_HTML, $data->format);
        self::assertGreaterThan(0, $data->draftitemid);
        self::assert_draft_files([], $data->draftitemid);
        self::assertGreaterThanOrEqual($now, $data->time);

        // Reply to sender (all).

        $data = message_data::reply($message, $user2, true);

        self::assertEquals($user2, $data->sender);
        self::assertEquals([$user1], $data->to);
        self::assertEquals([$user3, $user4], $data->cc);
        self::assertEquals([], $data->bcc);

        // Reply to self.

        $data = message_data::reply($message, $user1, false);

        self::assertEquals($user1, $data->sender);
        self::assertEquals([$user2, $user3], $data->to);
        self::assertEquals([], $data->cc);
        self::assertEquals([], $data->bcc);

        // Reply to self (all).

        $data = message_data::reply($message, $user1, true);

        self::assertEquals($user1, $data->sender);
        self::assertEquals([$user2, $user3], $data->to);
        self::assertEquals([$user4], $data->cc);
        self::assertEquals([], $data->bcc);

        // Reply to replied message.

        $data = message_data::reply($message, $user2, false);
        $message = message::create($data);
        $message->send($time2);

        $data = message_data::reply($message, $user1, false);

        self::assertEquals($user1, $data->sender);
        self::assertEquals([$user2], $data->to);
        self::assertEquals([], $data->cc);
        self::assertEquals([], $data->bcc);
        self::assertEquals('RE: Subject', $data->subject);
    }

    public function test_new() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $now = time();

        $data = message_data::new($course, $user);
        self::assertEquals($user, $data->sender);
        self::assertNull($data->reference);
        self::assertEquals($course, $data->course);
        self::assertEmpty($data->to);
        self::assertEmpty($data->cc);
        self::assertEmpty($data->bcc);
        self::assertEquals('', $data->subject);
        self::assertEquals('', $data->content);
        self::assertEquals((int) FORMAT_HTML, $data->format);
        self::assertGreaterThan(0, $data->draftitemid);
        self::assert_draft_files([], $data->draftitemid);
        self::assertGreaterThanOrEqual($now, $data->time);
    }
}
