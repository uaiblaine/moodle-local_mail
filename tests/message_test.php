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

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/testcase.php');

/**
 * @covers \local_mail\message
 */
class message_test extends testcase {

    public function test_create() {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $course = new course($generator->create_course());
        $label1 = label::create($user1, 'Label', 'blue');
        $label2 = label::create($user2, 'Label', 'blue');
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $data = message_data::new($course, $user1);
        $data->to = [$user2, $user3];
        $data->cc = [$user4];
        $data->bcc = [$user5];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $time;
        self::create_draft_file($data->draftitemid, 'file1.txt', 'File 1');
        self::create_draft_file($data->draftitemid, 'file2.txt', 'File 2');

        $message = message::create($data);

        self::assertGreaterThan(0, $message->id);
        self::assertEquals($data->course, $message->course);
        self::assertEquals($data->subject, $message->subject);
        self::assertEquals($data->content, $message->content);
        self::assertEquals($data->format, $message->format);
        self::assertEquals(2, $message->attachments);
        self::assertEquals($data->time, $message->time);
        self::assertEquals([
            $user1->id => message::ROLE_FROM,
            $user2->id => message::ROLE_TO,
            $user3->id => message::ROLE_TO,
            $user4->id => message::ROLE_CC,
            $user5->id => message::ROLE_BCC,
        ], $message->roles);
        self::assertEquals([
            $user1->id => false,
            $user2->id => true,
            $user3->id => true,
            $user4->id => true,
            $user5->id => true,
        ], $message->unread);
        self::assertEquals([
            $user1->id => false,
            $user2->id => false,
            $user3->id => false,
            $user4->id => false,
            $user5->id => false,
        ], $message->starred);
        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user2->id => message::NOT_DELETED,
            $user3->id => message::NOT_DELETED,
            $user4->id => message::NOT_DELETED,
            $user5->id => message::NOT_DELETED,
        ], $message->deleted);
        self::assertEquals([
            $user1->id => [],
            $user2->id => [],
            $user3->id => [],
            $user4->id => [],
            $user5->id => [],
        ], $message->labels);
        self::assert_message($message);
        self::assert_attachments(['file1.txt' => 'File 1', 'file2.txt' => 'File 2'], $message);
        self::assertEquals([], $message->fetch_references());

        // Reference.

        $message->send($time);
        $message->set_labels($user1, [$label1]);
        $message->set_labels($user2, [$label2]);
        $data = message_data::new($message->course, $user2);
        $data->reference = $message;
        $data->to = [$user1];
        $data->time = make_timestamp(2021, 10, 11, 13, 0);

        $message = message::create($data);
        self::assertEquals([
            $user1->id => [],
            $user2->id => [$label2->id => $label2],
        ], $message->labels);
        self::assertEquals([$data->reference->id => $data->reference], $message->fetch_references());
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

        $data1 = message_data::new($course1, $user1);
        $data1->subject = 'Subject 1';
        $data1->to = [$user2];
        $message1 = message::create($data1);
        $message1->send($time);
        $message1->set_deleted($user1, message::DELETED);
        $message1->set_deleted($user2, message::DELETED);

        $data2 = message_data::new($course1, $user2);
        $data2->subject = 'Subject 2';
        $data2->to = [$user1];
        $message2 = message::create($data2);
        $message2->send($time);
        $message2->set_deleted($user1, message::DELETED);

        $data3 = message_data::new($course1, $user2);
        $data3->subject = 'Subject 3';
        $data3->to = [$user1];
        $message3 = message::create($data3);
        $message3->send($time);

        $data4 = message_data::new($course1, $user2);
        $data4->subject = 'Subject 4';
        $data4->to = [$user1];
        $message4 = message::create($data4);
        $message4->send($time);
        $message4->set_deleted($user1, message::DELETED_FOREVER);

        $data5 = message_data::new($course2, $user2);
        $data5->subject = 'Subject 5';
        $data5->to = [$user1];
        $message5 = message::create($data5);
        $message5->send($time);
        $message5->set_deleted($user1, message::DELETED);

        $data6 = message_data::new($course3, $user2);
        $data6->subject = 'Subject 5';
        $data6->to = [$user1];
        $message6 = message::create($data6);
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
        $time3 = make_timestamp(2021, 10, 11, 14, 0);
        $label1 = label::create($user1, 'label1');
        $label2 = label::create($user2, 'label2');
        $data1 = message_data::new($course, $user1);
        $data1->subject = 'Subject 1';
        $data1->content = 'Content 1';
        $data1->format = FORMAT_PLAIN;
        $data1->to = [$user2];
        $data1->cc = [$user3];
        $message1 = message::create($data1);
        $message1->send($time1);
        $data2 = message_data::reply($message1, $user2, true);
        $data2->subject = 'Subject 2';
        $data2->content = 'Content 2';
        $data2->format = FORMAT_MOODLE;
        $message2 = message::create($data2);
        $message2->send($time2);
        $message2->set_labels($user1, [$label1]);
        $message2->set_labels($user2, [$label2]);
        $message2 = message::create($data2);

        self::assertEquals($message1, message::fetch($message1->id));
        self::assertEquals(
            array_keys(user::fetch_many(array_keys($message1->users))),
            array_keys(message::fetch($message1->id)->users),
        );
        self::assertEquals($message2, message::fetch($message2->id));
        self::assertEquals(
            array_keys(user::fetch_many(array_keys($message2->users))),
            array_keys(message::fetch($message2->id)->users),
        );
        self::assertNull(message::fetch(0));
    }

    public function test_fetch_many() {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 13, 0);
        $time3 = make_timestamp(2021, 10, 11, 14, 0);
        $time4 = make_timestamp(2021, 10, 11, 15, 0);
        $label1 = label::create($user1, 'label1');
        $label2 = label::create($user2, 'label2');
        $data1 = message_data::new($course1, $user1);
        $data1->subject = 'Subject 1';
        $data1->content = 'Content 1';
        $data1->format = FORMAT_PLAIN;
        $data1->to = [$user2];
        $data1->cc = [$user3];
        $message1 = message::create($data1);
        $message1->send($time1);
        $data2 = message_data::reply($message1, $user2, true);
        $data2->subject = 'Subject 2';
        $data2->content = 'Content 2';
        $data2->format = FORMAT_MOODLE;
        $message2 = message::create($data2);
        $message2->send($time2);
        $message2->set_labels($user1, [$label1]);
        $message2->set_labels($user2, [$label2]);
        $data3 = message_data::new($course2, $user2);
        $data3->subject = 'Subject 3';
        $data3->content = 'Content 3';
        $data3->time = $time3;
        $message3 = message::create($data3);
        $data4 = message_data::new($course2, $user3);
        $data4->subject = 'Subject 4';
        $data4->content = 'Content 4';
        $data4->time = $time4;
        $message4 = message::create($data4);

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

        $data1 = message_data::new($course, $user1);
        $data1->subject = 'Subject 1';
        $data1->content = 'Content 1';
        $data1->to = [$user2];
        $message1 = message::create($data1);
        $message1->send($time1);

        $data2 = message_data::reply($message1, $user2, true);
        $data2->subject = 'Subject 2';
        $data2->content = 'Content 2';
        $message2 = message::create($data2);
        $message2->send($time2);

        $data3 = message_data::forward($message2, $user2);
        $data3->to = [$user3];
        $message3 = message::create($data3);
        $message3->send($time3);

        $this->assertEquals(message::fetch_many([]), $message1->fetch_references());
        $this->assertEquals(message::fetch_many([$message1->id]), $message2->fetch_references());
        $this->assertEquals(message::fetch_many([$message2->id, $message1->id]), $message3->fetch_references());
        $this->assertEquals(message::fetch_many([$message3->id, $message2->id]), $message1->fetch_references(true));
        $this->assertEquals(message::fetch_many([$message3->id]), $message2->fetch_references(true));
        $this->assertEquals(message::fetch_many([]), $message3->fetch_references(true));
    }

    public function test_has_recipient() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->cc = [$user3];
        $data->bcc = [$user4];
        $message = message::create($data);

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

        $data = message_data::new($course, $user1);
        $data->to = [$user2, $user3];
        $data->cc = [$user4];
        $data->bcc = [$user5];
        $message = message::create($data);

        $this->assertEquals([$user2, $user3, $user4, $user5], $message->recipients());
        $this->assertEquals([$user2, $user3], $message->recipients(message::ROLE_TO));
        $this->assertEquals([$user4], $message->recipients(message::ROLE_CC));
        $this->assertEquals([$user5], $message->recipients(message::ROLE_BCC));
        $this->assertEquals([$user2, $user3, $user4], $message->recipients(message::ROLE_TO, message::ROLE_CC));
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
        $data1 = message_data::new($course, $user1);
        $data1->subject = 'subject';
        $data1->content = 'content';
        $data1->format = FORMAT_PLAIN;
        $data1->time = $time1;
        $data1->to = [$user2];
        $data1->cc = [$user3];
        $data1->bcc = [$user4];
        $message1 = message::create($data1);

        // Send message without references.

        $message1->send($time2);

        self::assertFalse($message1->draft);
        self::assertEquals($time2, $message1->time);
        self::assert_message($message1);

        // Send message with references.

        $message1->set_labels($user1, [$label1]);
        $data2 = message_data::reply($message1, $user2, false);
        $message2 = message::create($data2);
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

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $message = message::create($data);

        $this->assertEquals($user1, $message->sender());
    }

    public function test_set_deleted() {
        $fs = get_file_storage();
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $label1 = label::create($user1, 'Label 1');
        $label2 = label::create($user2, 'Label 2');
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $data = message_data::new($course, $user1);
        $data->subject = 'subject';
        $data->to = [$user2];
        $message = message::create($data);
        $message->send($time);
        $message->set_labels($user2, [$label2]);
        $draft = message::create($data);
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
        $data = message_data::new($course, $user1);
        $data->subject = 'subject';
        $data->to = [$user2];
        $message = message::create($data);
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
        $data = message_data::new($course, $user1);
        $data->subject = 'subject';
        $data->to = [$user2];
        $message = message::create($data);
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
        $data = message_data::new($course, $user1);
        $data->subject = 'subject';
        $data->to = [$user2];
        $message = message::create($data);
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
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $label1 = label::create($user1, 'Label 1');
        $label2 = label::create($user1, 'Label 2');

        $data = message_data::new($course1, $user1);
        $data->to = [$user2, $user3];
        $data->cc = [$user4];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = make_timestamp(2021, 10, 11, 12, 0);
        self::create_draft_file($data->draftitemid, 'file1.txt', 'File 1');
        self::create_draft_file($data->draftitemid, 'file2.txt', 'File 2');
        $message = message::create($data);
        $message->set_labels($user1, [$label1, $label2]);
        $message->set_deleted($user1, message::DELETED);

        $data = message_data::draft($message);
        $data->course = $course2;
        $data->to = [$user2];
        $data->cc = [$user3];
        $data->bcc = [$user5, $user2, $user3];
        $data->subject = 'Updated subject';
        $data->content = 'Updated content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = make_timestamp(2021, 10, 11, 13, 0);

        self::delete_draft_files($data->draftitemid);
        self::create_draft_file($data->draftitemid, 'file3.txt', 'File 3');

        $message->update($data);

        self::assertGreaterThan(0, $message->id);
        self::assertEquals($data->course, $message->course);
        self::assertEquals($data->subject, $message->subject);
        self::assertEquals($data->content, $message->content);
        self::assertEquals($data->format, $message->format);
        self::assertEquals(1, $message->attachments);
        self::assertEquals($data->time, $message->time);
        self::assertEquals([
            $user1->id => message::ROLE_FROM,
            $user2->id => message::ROLE_TO,
            $user3->id => message::ROLE_CC,
            $user5->id => message::ROLE_BCC,
        ], $message->roles);
        self::assertEquals([
            $user1->id => false,
            $user2->id => true,
            $user3->id => true,
            $user5->id => true,
        ], $message->unread);
        self::assertEquals([
            $user1->id => false,
            $user2->id => false,
            $user3->id => false,
            $user5->id => false,
        ], $message->starred);
        self::assertEquals([
            $user1->id => message::NOT_DELETED,
            $user2->id => message::NOT_DELETED,
            $user3->id => message::NOT_DELETED,
            $user5->id => message::NOT_DELETED,
        ], $message->deleted);
        self::assertEquals([
            $user1->id => [$label1->id => $label1, $label2->id => $label2],
            $user2->id => [],
            $user3->id => [],
            $user5->id => [],
        ], $message->labels);
        self::assert_message($message);
        self::assert_attachments(['file3.txt' => 'File 3'], $message);
        self::assertEquals([], $message->fetch_references());
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
            'courseid' => $message->course->id ?? 0,
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
                'courseid' => $message->course->id ?? 0,
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
