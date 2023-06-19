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

/**
 * @package    local-mail
 * @copyright  Albert Gasset <albert.gasset@gmail.com>
 * @copyright  Marc Català <reskit@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mail;

/**
 * @covers \local_mail\message
 */
class message_test extends testcase {

    public function test_add_recipient() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $message = message::create($course, $user1, $time);

        $message->add_recipient($user2, message::ROLE_TO);
        $message->add_recipient($user3, message::ROLE_CC);
        $message->add_recipient($user4, message::ROLE_BCC);

        self::assertEquals([
            $user1->id => message::ROLE_FROM,
            $user2->id => message::ROLE_TO,
            $user3->id => message::ROLE_CC,
            $user4->id => message::ROLE_BCC,
        ], $message->roles);

        self::assertEquals([
            $user1->id => false,
            $user2->id => true,
            $user3->id => true,
            $user4->id => true,
        ], $message->unread);

        self::assertEquals([
            $user1->id => false,
            $user2->id => false,
            $user3->id => false,
            $user4->id => false,
        ], $message->starred);

        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user2->id => message::NOT_DELETED,
            $user3->id => message::NOT_DELETED,
            $user4->id => message::NOT_DELETED,
        ], $message->deleted);

        self::assertEquals([
            $user1->id => [],
            $user2->id => [],
            $user3->id => [],
            $user4->id => [],
        ], $message->labels);

        self::assert_message($message);
    }

    public function test_create() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $message = message::create($course, $user, $time);

        self::assertGreaterThan(0, $message->id);
        self::assertEquals($course, $message->course);
        self::assertEquals('', $message->subject);
        self::assertEquals('', $message->content);
        self::assertEquals(FORMAT_HTML, $message->format);
        self::assertEquals(0, $message->attachments);
        self::assertEquals($time, $message->time);
        self::assertEquals([$user->id => message::ROLE_FROM], $message->roles);
        self::assertEquals([$user->id => false], $message->unread);
        self::assertEquals([$user->id => false], $message->starred);
        self::assertEquals([$user->id => message::NOT_DELETED], $message->deleted);
        self::assertEquals([$user->id => []], $message->labels);
        self::assert_message($message);
        self::assertEquals([], $message->fetch_references());
    }


    public function test_empty_trash() {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $course3 = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course1->id);
        $generator->enrol_user($user2->id, $course1->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $message1 = message::create($course1, $user1, $time);
        $message1->update('Subject 1', 'Content 1', FORMAT_HTML, $time);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->send($time);
        $message1->set_deleted($user1, message::DELETED);
        $message1->set_deleted($user2, message::DELETED);

        $message2 = message::create($course1, $user2, $time);
        $message2->update('Subject 2', 'Content 2', FORMAT_HTML, $time);
        $message2->add_recipient($user1, message::ROLE_TO);
        $message2->send($time);
        $message2->set_deleted($user1, message::DELETED);

        $message3 = message::create($course1, $user2, $time);
        $message3->update('Subject 3', 'Content 3', FORMAT_HTML, $time);
        $message3->add_recipient($user1, message::ROLE_TO);
        $message3->send($time);

        $message4 = message::create($course1, $user2, $time);
        $message4->update('Subject 4', 'Content 4', FORMAT_HTML, $time);
        $message4->add_recipient($user1, message::ROLE_TO);
        $message4->send($time);
        $message4->set_deleted($user1, message::DELETED_FOREVER);

        $message5 = message::create($course2, $user2, $time);
        $message5->update('Subject 5', 'Content 5', FORMAT_HTML, $time);
        $message5->add_recipient($user1, message::ROLE_TO);
        $message5->send($time);
        $message5->set_deleted($user1, message::DELETED);

        $message6 = message::create($course3, $user2, $time);
        $message6->update('Subject 6', 'Content 6', FORMAT_HTML, $time);
        $message6->add_recipient($user1, message::ROLE_TO);
        $message6->send($time);
        $message6->set_deleted($user1, message::DELETED);

        message::empty_trash($user1, [$course1, $course2]);

        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message1->id)->deleted[$user1->id]);
        $this->assertEquals(message::DELETED, message::fetch($message1->id)->deleted[$user2->id]);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message2->id)->deleted[$user1->id]);
        $this->assertEquals(message::NOT_DELETED, message::fetch($message2->id)->deleted[$user2->id]);
        $this->assertEquals(message::NOT_DELETED, message::fetch($message3->id)->deleted[$user1->id]);
        $this->assertEquals(message::NOT_DELETED, message::fetch($message3->id)->deleted[$user2->id]);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message4->id)->deleted[$user1->id]);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message5->id)->deleted[$user1->id]);
        $this->assertEquals(message::DELETED, message::fetch($message6->id)->deleted[$user1->id]);

        // No courses.

        message::empty_trash($user1, []);

        $this->assertEquals(message::DELETED, message::fetch($message6->id)->deleted[$user1->id]);
    }

    public function test_fetch() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 13, 0);
        $label1 = label::create($user1, 'label1');
        $label2 = label::create($user2, 'label2');
        $message1 = message::create($course, $user1, $time1);
        $message1->update('Subject 1', 'Content 1', FORMAT_PLAIN, $time1);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->add_recipient($user3, message::ROLE_CC);
        $message1->send($time1);
        $message2 = $message1->reply($user2, true, $time2);
        $message2->update('Subject 2', 'Content 2', FORMAT_PLAIN, $time2);
        $message2->send($time2);
        $message2->set_labels($user1, [$label1]);
        $message2->set_labels($user2, [$label2]);

        self::assertEquals($message1, message::fetch($message1->id));
        self::assertEquals($message2, message::fetch($message2->id));
        self::assertNull(message::fetch(0));
    }

    public function test_fetch_many() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 13, 0);
        $time3 = make_timestamp(2021, 10, 11, 14, 0);
        $time4 = make_timestamp(2021, 10, 11, 15, 0);
        $label1 = label::create($user1, 'label1');
        $label2 = label::create($user2, 'label2');
        $message1 = message::create($course, $user1, $time1);
        $message1->update('Subject 1', 'Content 1', FORMAT_PLAIN, $time1);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->add_recipient($user3, message::ROLE_CC);
        $message1->send($time1);
        $message2 = $message1->reply($user2, true, $time2);
        $message2->update('Subject 2', 'Content 2', FORMAT_PLAIN, $time2);
        $message2->send($time2);
        $message2->set_labels($user1, [$label1]);
        $message2->set_labels($user2, [$label2]);
        $message3 = message::create($course, $user2, $time3);
        $message4 = message::create($course, $user3, $time4);

        $messages = message::fetch_many([$message1->id, $message2->id, $message1->id, 0, $message4->id]);

        self::assertIsArray($messages);
        self::assertEquals([$message4->id, $message2->id, $message1->id], array_keys($messages));
        self::assertEquals(message::fetch($message1->id), $messages[$message1->id]);
        self::assertEquals(message::fetch($message2->id), $messages[$message2->id]);
        self::assertEquals(message::fetch($message4->id), $messages[$message4->id]);

        self::assertEquals([], message::fetch_many([]));
    }

    public function test_fetch_references() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 13, 0);
        $time3 = make_timestamp(2021, 10, 11, 14, 0);
        $time4 = make_timestamp(2021, 10, 11, 15, 0);
        $message1 = message::create($course, $user1, $time1);
        $message1->update('Subject 1', 'Content 1', FORMAT_PLAIN, $time1);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->send($time1);
        $message2 = $message1->reply($user2, true, $time2);
        $message2->update('Subject 2', 'Content 2', FORMAT_PLAIN, $time2);
        $message2->send($time2);
        $message3 = $message2->forward($user2, $time3);
        $message3->add_recipient($user3, message::ROLE_TO);
        $message3->send($time3);

        $this->assertEquals(message::fetch_many([]), $message1->fetch_references());
        $this->assertEquals(message::fetch_many([$message1->id]), $message2->fetch_references());
        $this->assertEquals(message::fetch_many([$message2->id, $message1->id]), $message3->fetch_references());
        $this->assertEquals(message::fetch_many([$message3->id, $message2->id]), $message1->fetch_references(true));
        $this->assertEquals(message::fetch_many([$message3->id]), $message2->fetch_references(true));
        $this->assertEquals(message::fetch_many([]), $message3->fetch_references(true));
    }

    public function test_forward() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 13, 0);
        $time3 = make_timestamp(2021, 10, 11, 14, 0);
        $label1 = label::create($user1, 'Label', 'blue');
        $label2 = label::create($user2, 'Label', 'blue');
        $message1 = message::create($course, $user1, $time1);
        $message1->update('subject', 'content', FORMAT_PLAIN, $time1);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->send($time1);
        $message1->set_labels($user1, [$label1]);
        $message1->set_labels($user2, [$label2]);

        $message2 = $message1->forward($user2, $time2);

        self::assertGreaterThan(0, $message2->id);
        self::assertEquals($course, $message2->course);
        self::assertEquals('FW: subject', $message2->subject);
        self::assertEquals('', $message2->content);
        self::assertEquals(FORMAT_HTML, $message2->format);
        self::assertEquals(0, $message2->attachments);
        self::assertEquals($time2, $message2->time);
        self::assertEquals([$user2->id => message::ROLE_FROM], $message2->roles);
        self::assertEquals([$user2->id => false], $message2->unread);
        self::assertEquals([$user2->id => false], $message2->starred);
        self::assertEquals([$user2->id => message::NOT_DELETED], $message2->deleted);
        self::assertEquals([$user2->id => [$label2->id => $label2]], $message2->labels);
        self::assert_message($message2);
        self::assertEquals(message::fetch_many([$message1->id]), $message2->fetch_references());

        // Forward forwarded message.

        $message2->add_recipient($user3, message::ROLE_TO);
        $message2->send($time2);

        $message3 = $message2->forward($user3, $time3);

        self::assertEquals('FW: subject', $message3->subject);
        self::assert_message($message3);
        self::assertEquals(message::fetch_many([$message2->id, $message1->id]), $message3->fetch_references());
    }

    public function test_has_recipient() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());

        $time = make_timestamp(2021, 10, 11, 12, 0);

        $message = message::create($course, $user1, $time);
        $message->add_recipient($user2, message::ROLE_TO);
        $message->add_recipient($user3, message::ROLE_CC);
        $message->add_recipient($user4, message::ROLE_BCC);

        $this->assertFalse($message->has_recipient($user1));
        $this->assertTrue($message->has_recipient($user2));
        $this->assertTrue($message->has_recipient($user3));
        $this->assertTrue($message->has_recipient($user4));
        $this->assertFalse($message->has_recipient($user5));
    }

    public function test_normalize_text() {
        self::assertEquals('', message::normalize_text(''));
        self::assertEquals('text', message::normalize_text('   text   '));
        self::assertEquals('text text', message::normalize_text('text     text'));
        self::assertEquals('text text', message::normalize_text('text😛😛text'));
    }

    public function test_recipients() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());

        $time = make_timestamp(2021, 10, 11, 12, 0);

        $message = message::create($course, $user1, $time);
        $message->add_recipient($user2, message::ROLE_TO);
        $message->add_recipient($user3, message::ROLE_TO);
        $message->add_recipient($user4, message::ROLE_CC);
        $message->add_recipient($user5, message::ROLE_BCC);

        $this->assertEquals([$user2, $user3, $user4, $user5], $message->recipients());
        $this->assertEquals([$user2, $user3], $message->recipients(message::ROLE_TO));
        $this->assertEquals([$user4], $message->recipients(message::ROLE_CC));
        $this->assertEquals([$user5], $message->recipients(message::ROLE_BCC));
        $this->assertEquals([$user2, $user3, $user4], $message->recipients(message::ROLE_TO, message::ROLE_CC));
    }

    public function test_remove_recipient() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $message = message::create($course, $user1, $time);
        $message->add_recipient($user2, message::ROLE_TO);
        $message->add_recipient($user3, message::ROLE_CC);
        $message->add_recipient($user4, message::ROLE_BCC);

        $message->remove_recipient($user2);
        $message->remove_recipient($user4);

        self::assertEquals([
            $user1->id => message::ROLE_FROM,
            $user3->id => message::ROLE_CC,
        ], $message->roles);

        self::assertEquals([
            $user1->id => false,
            $user3->id => true,
        ], $message->unread);

        self::assertEquals([
            $user1->id => false,
            $user3->id => false,
        ], $message->starred);

        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user3->id => message::NOT_DELETED,
        ], $message->deleted);

        self::assertEquals([
            $user1->id => [],
            $user3->id => [],
        ], $message->labels);

        self::assert_message($message);
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
        $time3 = make_timestamp(2021, 10, 11, 14, 0);
        $time4 = make_timestamp(2021, 10, 11, 15, 0);
        $time5 = make_timestamp(2021, 10, 11, 16, 0);
        $time6 = make_timestamp(2021, 10, 11, 17, 0);
        $label1 = label::create($user1, 'Label 1', 'blue');
        $label2 = label::create($user2, 'Label 2', 'red');
        $label3 = label::create($user3, 'Label 3', 'green');
        $label4 = label::create($user4, 'Label 4', 'yellow');
        $message1 = message::create($course, $user1, $time1);
        $message1->update('subject', 'content', FORMAT_PLAIN, $time1);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->add_recipient($user3, message::ROLE_TO);
        $message1->add_recipient($user4, message::ROLE_CC);
        $message1->add_recipient($user5, message::ROLE_BCC);
        $message1->send($time1);
        $message1->set_labels($user1, [$label1]);
        $message1->set_labels($user2, [$label2]);
        $message1->set_labels($user3, [$label3]);
        $message1->set_labels($user4, [$label4]);

        // Reply to sender.

        $message2 = $message1->reply($user2, false, $time2);

        self::assertGreaterThan(0, $message2->id);
        self::assertEquals($course, $message2->course);
        self::assertEquals('RE: subject', $message2->subject);
        self::assertEquals('', $message2->content);
        self::assertEquals(FORMAT_HTML, $message2->format);
        self::assertEquals(0, $message2->attachments);
        self::assertEquals($time2, $message2->time);
        self::assertEquals([
            $user1->id => message::ROLE_TO,
            $user2->id => message::ROLE_FROM,
        ], $message2->roles);
        self::assertEquals([
            $user1->id => true,
            $user2->id => false,
        ], $message2->unread);
        self::assertEquals([
            $user1->id => false,
            $user2->id => false,
        ], $message2->starred);
        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user2->id => message::NOT_DELETED,
        ], $message2->deleted);
        self::assertEquals([
            $user1->id => [],
            $user2->id => [$label2->id => $label2],
        ], $message2->labels);
        self::assert_message($message2);
        self::assertEquals(message::fetch_many([$message1->id]), $message2->fetch_references());

        // Reply to sender (all).

        $message3 = $message1->reply($user2, true, $time3);

        self::assertEquals([
            $user1->id => message::ROLE_TO,
            $user2->id => message::ROLE_FROM,
            $user3->id => message::ROLE_CC,
            $user4->id => message::ROLE_CC,
        ], $message3->roles);
        self::assertEquals([
            $user1->id => true,
            $user2->id => false,
            $user3->id => true,
            $user4->id => true,
        ], $message3->unread);
        self::assertEquals([
            $user1->id => false,
            $user2->id => false,
            $user3->id => false,
            $user4->id => false,
        ], $message3->starred);
        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user2->id => message::NOT_DELETED,
            $user3->id => message::NOT_DELETED,
            $user4->id => message::NOT_DELETED,
        ], $message3->deleted);
        self::assertEquals([
            $user1->id => [],
            $user2->id => [$label2->id => $label2],
            $user3->id => [],
            $user4->id => [],
        ], $message3->labels);
        self::assert_message($message3);

        // Reply to self.

        $message4 = $message1->reply($user1, false, $time4);

        self::assertEquals([
            $user1->id => message::ROLE_FROM,
            $user2->id => message::ROLE_TO,
            $user3->id => message::ROLE_TO,
        ], $message4->roles);
        self::assertEquals([
            $user1->id => false,
            $user2->id => true,
            $user3->id => true,
        ], $message4->unread);
        self::assertEquals([
            $user1->id => false,
            $user2->id => false,
            $user3->id => false,
        ], $message4->starred);
        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user2->id => message::NOT_DELETED,
            $user3->id => message::NOT_DELETED,
        ], $message4->deleted);
        self::assertEquals([
            $user1->id => [$label1->id => $label1],
            $user2->id => [],
            $user3->id => [],
        ], $message4->labels);
        self::assert_message($message4);

        // Reply to self (all).

        $message5 = $message1->reply($user1, true, $time5);

        self::assertEquals([
            $user1->id => message::ROLE_FROM,
            $user2->id => message::ROLE_TO,
            $user3->id => message::ROLE_TO,
            $user4->id => message::ROLE_CC,
        ], $message5->roles);
        self::assertEquals([
            $user1->id => false,
            $user2->id => true,
            $user3->id => true,
            $user4->id => true,
        ], $message5->unread);
        self::assertEquals([
            $user1->id => false,
            $user2->id => false,
            $user3->id => false,
            $user4->id => false,
        ], $message5->starred);
        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user2->id => message::NOT_DELETED,
            $user3->id => message::NOT_DELETED,
            $user4->id => message::NOT_DELETED,
        ], $message5->deleted);
        self::assertEquals([
            $user1->id => [$label1->id => $label1],
            $user2->id => [],
            $user3->id => [],
            $user4->id => [],
        ], $message5->labels);
        self::assert_message($message5);

        // Reply to replied message.

        $message2->send($time2);
        $message6 = $message2->reply($user1, false, $time6);

        self::assertEquals('RE: subject', $message6->subject);
        self::assertEquals([
            $user1->id => message::ROLE_FROM,
            $user2->id => message::ROLE_TO,
        ], $message6->roles);
        self::assertEquals([
            $user1->id => false,
            $user2->id => true,
        ], $message6->unread);
        self::assertEquals([
            $user1->id => false,
            $user2->id => false,
        ], $message6->starred);
        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user2->id => message::NOT_DELETED,
        ], $message6->deleted);
        self::assertEquals([
            $user1->id => [$label1->id => $label1],
            $user2->id => [],
        ], $message6->labels);
        self::assert_message($message6);
        self::assertEquals(message::fetch_many([$message2->id, $message1->id]), $message6->fetch_references());
    }

    public function test_send() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $label1 = label::create($user1, 'Label 1', 'blue');
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 13, 0);
        $time3 = make_timestamp(2021, 10, 11, 14, 0);
        $time4 = make_timestamp(2021, 10, 11, 15, 0);
        $message1 = message::create($course, $user1, $time1);
        $message1->update('subject', 'content', FORMAT_PLAIN, $time1);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->add_recipient($user3, message::ROLE_CC);
        $message1->add_recipient($user4, message::ROLE_BCC);

        // Send message without references.

        $message1->send($time2);

        self::assertFalse($message1->draft);
        self::assertEquals($time2, $message1->time);
        self::assert_message($message1);

        // Send message with references.

        $message1->set_labels($user1, [$label1]);
        $message2 = $message1->reply($user2, false, $time3);
        $message2->send($time4);

        self::assertFalse($message2->draft);
        self::assertEquals($time4, $message2->time);
        self::assertEquals([
            $user1->id => [$label1->id => $label1],
            $user2->id => [],
        ], $message2->labels);
        self::assert_message($message1);
    }

    public function test_sender() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());

        $time = make_timestamp(2021, 10, 11, 12, 0);

        $message = message::create($course, $user1, $time);
        $message->add_recipient($user2, message::ROLE_TO);

        $this->assertEquals($user1, $message->sender());
    }

    public function test_set_deleted() {
        $fs = \get_file_storage();
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $label1 = label::create($user1, 'Label 1');
        $label2 = label::create($user2, 'Label 2');
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $message = message::create($course, $user1, $time);
        $message->update('subject', 'content', FORMAT_PLAIN, $time);
        $message->add_recipient($user2, message::ROLE_TO);
        $message->send($time);
        $message->set_labels($user2, [$label2]);
        $draft = message::create($course, $user1, $time);
        $draft->add_recipient($user2, message::ROLE_TO);
        $draft->set_labels($user1, [$label1]);

        // Delete draft forever.

        $draft->set_deleted($user1, message::DELETED_FOREVER);

        self::assert_record_count(0, 'messages', ['id' => $draft->id]);
        self::assert_record_count(0, 'message_refs', ['messageid' => $draft->id]);
        self::assert_record_count(0, 'message_users', ['messageid' => $draft->id]);
        self::assert_record_count(0, 'message_labels', ['messageid' => $draft->id]);
        self::assertEquals([], $fs->get_area_files($course->context()->id, 'local_mail', 'message', $draft->id));
        self::assertEquals(message::DELETED_FOREVER, $draft->deleted[$user1->id]);
        self::assertEquals([], $draft->labels[$user1->id]);

        // Delete sent message.

        $message->set_deleted($user2, message::DELETED);

        self::assertEquals(message::DELETED, $message->deleted[$user2->id]);
        self::assert_message($message);

        // Restore deleted message.

        $message->set_deleted($user2, message::NOT_DELETED);

        self::assertEquals(message::NOT_DELETED, $message->deleted[$user2->id]);
        self::assert_message($message);

        // Delete sent message forever.

        $message->set_deleted($user2, message::DELETED_FOREVER);

        self::assertEquals(message::DELETED_FOREVER, $message->deleted[$user2->id]);
        self::assert_message($message);
    }

    public function test_set_labels() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $label1 = label::create($user1, 'Label 1', 'blue');
        $label2 = label::create($user1, 'Label 2', 'red');
        $label3 = label::create($user1, 'Label 3', 'green');
        $label4 = label::create($user2, 'Label 4', 'purple');
        $message = message::create($course, $user1, $time);
        $message->update('subject', 'content', FORMAT_PLAIN, $time);
        $message->add_recipient($user2, message::ROLE_TO);
        $message->send($time);

        $message->set_labels($user1, [$label1, $label2]);

        self::assertEquals([
            $user1->id => [$label1->id => $label1, $label2->id => $label2],
            $user2->id => [],
        ], $message->labels);
        self::assert_message($message);

        $message->set_labels($user1, [$label2, $label3]);
        self::assertEquals([
            $user1->id => [$label2->id => $label2, $label3->id => $label3],
            $user2->id => [],
        ], $message->labels);
        self::assert_message($message);

        $message->set_labels($user2, [$label4]);
        self::assertEquals([
            $user1->id => [$label2->id => $label2, $label3->id => $label3],
            $user2->id => [$label4->id => $label4]
        ], $message->labels);
        self::assert_message($message);

        $message->set_labels($user1, []);
        self::assertEquals([
            $user1->id => [],
            $user2->id => [$label4->id => $label4]
        ], $message->labels);
        self::assert_message($message);

        $message->set_labels($user2, []);
        self::assertEquals([
            $user1->id => [],
            $user2->id => []
        ], $message->labels);
        self::assert_message($message);
    }

    public function test_set_starred() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $label = label::create($user2, 'label');
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $message = message::create($course, $user1, $time);
        $message->update('subject', 'content', FORMAT_PLAIN, $time);
        $message->add_recipient($user2, message::ROLE_TO);
        $message->send($time);
        $message->set_labels($user2, [$label]);

        // Set starred.

        $message->set_starred($user2, true);

        self::assertTrue($message->starred[$user2->id]);
        self::assert_message($message);

        // Set unstarred.

        $message->set_starred($user2, false);

        self::assertFalse($message->starred[$user2->id]);
        self::assert_message($message);
    }

    public function test_set_unread() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $label = label::create($user2, 'label');
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $message = message::create($course, $user1, $time);
        $message->update('subject', 'content', FORMAT_PLAIN, $time);
        $message->add_recipient($user2, message::ROLE_TO);
        $message->send($time);
        $message->set_labels($user2, [$label]);

        // Set unread.

        $message->set_unread($user2, false);

        self::assertFalse($message->unread[$user2->id]);
        self::assert_message($message);

        // Set read.

        $message->set_unread($user2, true);

        self::assertTrue($message->unread[$user2->id]);
        self::assert_message($message);
    }

    public function test_update() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 13, 0);
        $message = message::create($course, $user, $time1);
        self::create_attachment($course->id, $message->id, 'file1.txt', 'File 1');
        self::create_attachment($course->id, $message->id, 'file2.txt', 'File 2');
        self::create_attachment($course->id, $message->id, 'file3.txt', 'File 3');

        $message->update('updated_subject', 'updated_content', FORMAT_PLAIN, $time2);

        self::assertEquals('updated_subject', $message->subject);
        self::assertEquals('updated_content', $message->content);
        self::assertEquals(FORMAT_PLAIN, $message->format);
        self::assertEquals(3, $message->attachments);
        self::assertEquals($time2, $message->time);
        self::assert_message($message);

        // Deleted message.

        $message->set_deleted($user, message::DELETED);

        $message->update('updated_subject', 'updated_content', FORMAT_PLAIN, $time2);

        self::assertEquals($message->deleted[$user->id], message::NOT_DELETED);
        self::assert_message($message);
    }

    /**
     * Asserts that a message is stored correctly in the database.
     *
     * @param message $message Message.
     * @throws ExpectationFailedException
     */
    protected static function assert_message(message $message): void {
        self::assert_record_data('messages', [
            'id' => $message->id,
        ], [
            'courseid' => $message->course->id,
            'subject' => $message->subject,
            'content' => $message->content,
            'format' => $message->format,
            'attachments' => $message->attachments,
            'draft' => (int) $message->draft,
            'time' => $message->time,
            'normalizedsubject' => message::normalize_text($message->subject),
            'normalizedcontent' => message::normalize_text($message->content),
        ]);

        $numusers = count($message->users);
        self::assert_record_count($numusers, 'message_users', ['messageid' => $message->id]);

        $numlabels = array_sum(array_map('count', $message->labels));
        self::assert_record_count($numlabels, 'message_labels', ['messageid' => $message->id]);

        foreach ($message->users as $user) {
            $data = [
                'courseid' => $message->course->id,
                'draft' => (int) $message->draft,
                'time' => $message->time,
                'role' => $message->roles[$user->id],
                'unread' => (int) $message->unread[$user->id],
                'starred' => (int) $message->starred[$user->id],
                'deleted' => $message->deleted[$user->id],
            ];
            self::assert_record_data('message_users', [
                'messageid' => $message->id,
                'userid' => $user->id
            ], $data);
            foreach ($message->labels[$user->id] as $label) {
                self::assert_record_data('message_labels', [
                    'messageid' => $message->id,
                    'labelid' => $label->id,
                ], $data);
            }
        }
    }
}
