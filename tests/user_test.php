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

/**
 * @covers \local_mail\user
 */
class user_test extends testcase {

    public function test_can_view_files() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $generator->enrol_user($user4->id, $course->id);
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 12, 0);

        // Draft.

        $message1 = message::create($course, $user1, $time1);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->add_recipient($user3, message::ROLE_TO);

        self::assertTrue($user1->can_view_files($message1));
        self::assertFalse($user2->can_view_files($message1));
        self::assertFalse($user3->can_view_files($message1));
        self::assertFalse($user4->can_view_files($message1));

        // Sent message.

        $message1->update('Subject', 'Content', FORMAT_HTML, $time1);
        $message1->send($time1);

        self::assertTrue($user1->can_view_files($message1));
        self::assertTrue($user2->can_view_files($message1));
        self::assertFalse($user3->can_view_files($message1));
        self::assertFalse($user4->can_view_files($message1));

        // Deleted message.

        $message1->set_deleted($user1, message::DELETED_FOREVER);
        $message1->set_deleted($user2, message::DELETED_FOREVER);

        self::assertFalse($user1->can_view_files($message1));
        self::assertFalse($user2->can_view_files($message1));

        // Reference of a draft.

        $message2 = $message1->forward($user2, $time2);
        $message2->add_recipient($user4, message::ROLE_TO);

        self::assertFalse($user4->can_view_files($message1));

        // Reference of a sent message.

        $message2->send($time2);

        self::assertTrue($user4->can_view_files($message1));
    }

    public function test_can_edit_message() {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course1->id);
        $generator->enrol_user($user2->id, $course1->id);
        $time1 = make_timestamp(2021, 10, 11, 12, 0);
        $time2 = make_timestamp(2021, 10, 11, 12, 0);

        // Draft.

        $message1 = message::create($course1, $user1, $time1);
        $message1->add_recipient($user2, message::ROLE_TO);
        self::assertTrue($user1->can_edit_message($message1));
        self::assertFalse($user2->can_edit_message($message1));

        // Sent message.

        $message1->update('Subject', 'Content', FORMAT_HTML, $time1);
        $message1->send($time1);
        self::assertFalse($user1->can_edit_message($message1));
        self::assertFalse($user2->can_edit_message($message1));

        // Draft of a course the sender is not enrolled in.

        $message2 = message::create($course2, $user1, $time2);
        $message2->add_recipient($user2, message::ROLE_TO);
        self::assertFalse($user1->can_edit_message($message2));
        self::assertFalse($user2->can_edit_message($message2));
    }

    public function test_can_use_mail() {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $course3 = new course($generator->create_course(['visible' => false]));
        $course4 = new course($generator->create_course());
        $user = new user($generator->create_user());

        $generator->enrol_user($user->id, $course1->id);
        $generator->enrol_user($user->id, $course3->id, 'student');
        $generator->enrol_user($user->id, $course4->id, 'guest');

        self::assertTrue($user->can_use_mail($course1));
        self::assertFalse($user->can_use_mail($course2));
        self::assertFalse($user->can_use_mail($course3));
        self::assertFalse($user->can_use_mail($course4));
    }

    public function test_can_view_message() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $generator->enrol_user($user4->id, $course->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);

        // Draft.

        $message = message::create($course, $user1, $time);
        $message->add_recipient($user2, message::ROLE_TO);
        $message->add_recipient($user3, message::ROLE_TO);
        self::assertTrue($user1->can_view_message($message));
        self::assertFalse($user2->can_view_message($message));
        self::assertFalse($user3->can_view_message($message));
        self::assertFalse($user4->can_view_message($message));

        // Sent message.

        $message->update('Subject', 'Content', FORMAT_HTML, $time);
        $message->send($time);
        self::assertTrue($user1->can_view_message($message));
        self::assertTrue($user2->can_view_message($message));
        self::assertFalse($user3->can_view_message($message));
        self::assertFalse($user4->can_view_message($message));

        // Deleted message.

        $message->set_deleted($user1, message::DELETED_FOREVER);
        $message->set_deleted($user2, message::DELETED_FOREVER);
        self::assertFalse($user1->can_view_message($message));
        self::assertFalse($user2->can_view_message($message));
    }

    public function test_fetch() {
        $generator = self::getDataGenerator();
        $record = $generator->create_user();

        self::assertNull(user::fetch(0));

        $user = user::fetch($record->id);

        self::assertInstanceOf(user::class, $user);
        self::assertEquals((int) $record->id, $user->id);
        self::assertEquals($record->firstname, $user->firstname);
        self::assertEquals($record->lastname, $user->lastname);
        self::assertEquals($record->email, $user->email);
        self::assertEquals((int) $record->picture, $user->picture);
        self::assertEquals($record->imagealt, $user->imagealt);
        self::assertEquals($record->firstnamephonetic, $user->firstnamephonetic);
        self::assertEquals($record->lastnamephonetic, $user->lastnamephonetic);
        self::assertEquals($record->middlename, $user->middlename);
        self::assertEquals($record->alternatename, $user->alternatename);
    }

    public function test_fetch_many() {
        $generator = self::getDataGenerator();
        $record1 = $generator->create_user(['firstname' => 'Pau', 'lastname' => 'Rossell']);
        $record2 = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Garcia']);
        $record3 = $generator->create_user(['firstname' => 'Manel', 'lastname' => 'Porta']);

        self::assertEquals([], user::fetch_many([]));

        $users = user::fetch_many([$record1->id, 0, $record2->id, $record1->id, $record3->id]);

        self::assertIsArray($users);
        self::assertEquals([$record2->id, $record3->id, $record1->id], array_keys($users));
        self::assertEquals(user::fetch($record1->id), $users[$record1->id]);
        self::assertEquals(user::fetch($record2->id), $users[$record2->id]);
        self::assertEquals(user::fetch($record3->id), $users[$record3->id]);
    }

    public function test_fullname() {
        $generator = self::getDataGenerator();
        $record = $generator->create_user();
        $user = user::fetch($record->id);

        self::assertEquals(fullname($record), $user->fullname());
    }

    public function test_get_courses() {
        $generator = self::getDataGenerator();
        $record1 = $generator->create_course();
        $record2 = $generator->create_course();
        $record3 = $generator->create_course();
        $record4 = $generator->create_course();
        $record5 = $generator->create_course(['visible' => false]);
        $record6 = $generator->create_course();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());

        $generator->enrol_user($user1->id, $record1->id);
        $generator->enrol_user($user1->id, $record2->id);
        $generator->enrol_user($user2->id, $record3->id);
        $generator->enrol_user($user1->id, $record4->id);
        $generator->enrol_user($user1->id, $record5->id);
        $generator->enrol_user($user1->id, $record6->id, 'guest');

        $courses = $user1->get_courses();

        self::assertEquals(course::fetch_many([$record4->id, $record2->id, $record1->id]), $courses);
    }

    public function test_picture_url() {
        global $PAGE;
        $generator = self::getDataGenerator();
        $record = $generator->create_user();
        $user = user::fetch($record->id);

        $userpicture = new \user_picture($record);
        $url = $userpicture->get_url($PAGE);
        self::assertEquals($url->out(false), $user->picture_url());
    }

    public function test_profile_url() {
        $generator = self::getDataGenerator();
        $record = $generator->create_user();
        $user = user::fetch($record->id);

        $url = new \moodle_url('/user/profile.php', ['id' => $record->id]);
        self::assertEquals($url->out(false), $user->profile_url());
    }
}
