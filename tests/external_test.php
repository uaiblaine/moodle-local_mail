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

use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/testcase.php');
require_once(__DIR__ . '/message_search_test.php');
require_once(__DIR__ . '/user_search_test.php');

/**
 * @covers \local_mail\external
 */
class external_test extends testcase {

    public function test_get_settings() {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        set_config('maxrecipients', '20', 'local_mail');
        set_config('globaltrays', 'drafts,trash', 'local_mail');
        set_config('coursetrays', 'unread', 'local_mail');
        set_config('coursetraysname', 'shortname', 'local_mail');
        set_config('coursebadges', 'hidden', 'local_mail');
        set_config('coursebadgeslength', '10', 'local_mail');
        set_config('filterbycourse', 'hidden', 'local_mail');
        set_config('incrementalsearch', '1', 'local_mail');
        set_config('incrementalsearchlimit', '2000', 'local_mail');
        $this->setUser($user);

        $result = external::get_settings();

        \external_api::validate_parameters(external::get_settings_returns(), $result);

        $expected = [
            'maxrecipients' => 20,
            'globaltrays' => ['drafts', 'trash'],
            'coursetrays' => 'unread',
            'coursetraysname' => 'shortname',
            'coursebadges' => 'hidden',
            'coursebadgeslength' => 10,
            'filterbycourse' => 'hidden',
            'incrementalsearch' => true,
            'incrementalsearchlimit' => 2000,
        ];
        $this->assertEquals($expected, $result);

        // Default settings.

        unset_config('maxrecipients', 'local_mail');
        unset_config('globaltrays', 'local_mail');
        unset_config('coursetrays', 'local_mail');
        unset_config('coursetraysname', 'local_mail');
        unset_config('coursebadges', 'local_mail');
        unset_config('coursebadgeslength', 'local_mail');
        unset_config('filterbycourse', 'local_mail');
        unset_config('incrementalsearch', 'local_mail');
        unset_config('incrementalsearchlimit', 'local_mail');

        $result = external::get_settings();

        \external_api::validate_parameters(external::get_settings_returns(), $result);

        $expected = [
            'maxrecipients' => 100,
            'globaltrays' => ['starred', 'sent', 'drafts', 'trash'],
            'coursetrays' => 'all',
            'coursetraysname' => 'fullname',
            'coursebadges' => 'fullname',
            'coursebadgeslength' => 20,
            'filterbycourse' => 'fullname',
            'incrementalsearch' => false,
            'incrementalsearchlimit' => 1000,
        ];
        $this->assertEquals($expected, $result);
    }

    public function test_get_strings() {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $result = external::get_strings();

        \external_api::validate_parameters(external::get_strings_returns(), $result);

        $this->assertEquals(external::get_strings_raw(), $result);
    }

    public function test_get_preferences() {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);
        set_user_preference('local_mail_mailsperpage', 20);
        set_user_preference('local_mail_markasread', 1);

        $result = external::get_preferences();

        \external_api::validate_parameters(external::get_preferencs_returns(), $result);

        $expected = [
            'perpage' => 20,
            'markasread' => true,
        ];
        $this->assertEquals($expected, $result);

        // Default preferences.

        unset_user_preference('local_mail_mailsperpage');
        unset_user_preference('local_mail_markasread');

        $result = external::get_preferences();

        \external_api::validate_parameters(external::get_preferencs_returns(), $result);

        $expected = [
            'perpage' => 10,
            'markasread' => false,
        ];
        $this->assertEquals($expected, $result);

        // Invalid perpage preference.

        set_user_preference('local_mail_mailsperpage', 4);

        $result = external::get_preferences();

        \external_api::validate_parameters(external::get_preferencs_returns(), $result);
        $expected = [
            'perpage' => 5,
            'markasread' => false,
        ];
        $this->assertEquals($expected, $result);

        set_user_preference('local_mail_mailsperpage', 101);

        $result = external::get_preferences();

        \external_api::validate_parameters(external::get_preferencs_returns(), $result);
        $expected = [
            'perpage' => 100,
            'markasread' => false,
        ];
        $this->assertEquals($expected, $result);
    }

    public function test_set_preferences() {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        set_user_preference('local_mail_mailsperpage', 10);
        set_user_preference('local_mail_markasread', 0);

        $result = external::set_preferences(['perpage' => '20', 'markasread' => true]);

        $this->assertNull($result);
        $this->assertEquals('20', get_user_preferences('local_mail_mailsperpage'));
        $this->assertEquals('1', get_user_preferences('local_mail_markasread'));

        // Optional preferences.

        $result = external::set_preferences(['perpage' => '50']);

        $this->assertNull($result);
        $this->assertEquals('50', get_user_preferences('local_mail_mailsperpage'));
        $this->assertEquals('1', get_user_preferences('local_mail_markasread'));

        // Invalid perpage.

        try {
            external::set_preferences(['perpage' => '4']);
            $this->fail();
        } catch (\invalid_parameter_exception $e) {
            $this->assertEquals('"perpage" must be between 5 and 100', $e->debuginfo);
        }

        try {
            external::set_preferences(['perpage' => '101']);
            $this->fail();
        } catch (\invalid_parameter_exception $e) {
            $this->assertEquals('"perpage" must be between 5 and 100', $e->debuginfo);
        }
    }

    public function test_get_courses() {
        $generator = $this->getDataGenerator();
        list($users) = message_search_test::generate_data();

        foreach ($users as $user) {
            $this->setUser($user->id);
            $expected = [];
            foreach ($user->get_courses() as $course) {
                $search = new message_search($user);
                $search->course = $course;
                $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
                $search->unread = true;
                $expected[] = [
                    'id' => $course->id,
                    'shortname' => $course->shortname,
                    'fullname' => $course->fullname,
                    'visible' => $course->visible,
                    'unread' => $search->count(),
                ];
            }
            $result = external::get_courses();
            \external_api::validate_parameters(external::get_courses_returns(), $result);
            $this->assertEquals($expected, $result);
        }

        // User with no courses.

        $user = new user($generator->create_user());
        $this->setUser($user->id);
        $this->assertEquals([], external::get_courses());
    }

    public function test_get_labels() {
        $generator = $this->getDataGenerator();
        list($users) = message_search_test::generate_data();

        foreach ($users as $user) {
            $this->setUser($user->id);

            $expected = [];
            foreach (label::fetch_by_user($user) as $label) {
                $search = new message_search($user);
                $search->label = $label;
                $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
                $search->unread = true;
                $expected[] = [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                    'unread' => $search->count(),
                ];
            }
            $result = external::get_labels();
            \external_api::validate_parameters(external::get_labels_returns(), $result);
            $this->assertEquals($expected, $result);
        }

        // User with no labels.

        $user = new user($generator->create_user());
        $this->setUser($user->id);
        $this->assertEquals([], external::get_labels());
    }

    public function test_count_messages() {
        $generator = self::getDataGenerator();

        list($users, $messages) = message_search_test::generate_data();

        foreach (message_search_test::cases($users, $messages) as $search) {
            $this->setUser($search->user->id);
            $query = [];
            if ($search->course) {
                $query['courseid'] = $search->course->id;
            }
            if ($search->label) {
                $query['labelid'] = $search->label->id;
            }
            if ($search->draft !== null) {
                $query['draft'] = $search->draft;
            }
            if ($search->roles) {
                $query['roles'] = [];
                foreach ($search->roles as $role) {
                    $query['roles'][] = external::ROLES[$role];
                }
            }
            if ($search->unread !== null) {
                $query['unread'] = $search->unread;
            }
            if ($search->starred !== null) {
                $query['starred'] = $search->starred;
            }
            if ($search->deleted) {
                $query['deleted'] = true;
            }
            if ($search->content != '') {
                $query['content'] = $search->content;
            }
            if ($search->sendername != '') {
                $query['sendername'] = $search->sendername;
            }
            if ($search->recipientname != '') {
                $query['recipientname'] = $search->recipientname;
            }
            if ($search->withfilesonly) {
                $query['withfilesonly'] = true;
            }
            if ($search->maxtime) {
                $query['maxtime'] = $search->maxtime;
            }
            if ($search->start) {
                $query['startid'] = $search->start->id;
            }
            if ($search->stop) {
                $query['stopid'] = $search->stop->id;
            }
            if ($search->reverse) {
                $query['reverse'] = true;
            }

            $result = external::count_messages($query);
            \external_api::validate_parameters(external::count_messages_returns(), $result);
            $this->assertEquals($search->count(), $result, $search);
        }

        // Invalid course.
        self::setUser($users[0]->id);
        $course = $generator->create_course();
        $query = ['courseid' => $course->id];
        try {
            external::count_messages($query);
            self::fail();
        } catch (exception $e) {
            $this->assertEquals('errorcoursenotfound', $e->errorcode);
        }

        // Invalid label.
        self::setUser($users[0]->id);
        $labels = label::fetch_by_user($users[1]);
        $query = ['labelid' => reset($labels)->id];
        try {
            external::count_messages($query);
            self::fail();
        } catch (exception $e) {
            $this->assertEquals('errorlabelnotfound', $e->errorcode);
        }

        // Invalid role.
        self::setUser($users[0]->id);
        $query = ['roles' => ['xx']];
        try {
            external::count_messages($query);
            self::fail();
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals('invalid role: xx', $e->debuginfo);
        }

        // Invalid startid.
        self::setUser($users[0]->id);
        $query = ['startid' => '-1'];
        try {
            external::count_messages($query);
            self::fail();
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals('invalid startid: -1', $e->debuginfo);
        }

        // Invalid stopid.
        self::setUser($users[0]->id);
        $query = ['stopid' => '-1'];
        try {
            external::count_messages($query);
            self::fail();
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals('invalid stopid: -1', $e->debuginfo);
        }
    }

    public function test_search_messages() {
        $generator = self::getDataGenerator();

        list($users, $messages) = message_search_test::generate_data();

        foreach (message_search_test::cases($users, $messages) as $search) {
            $this->setUser($search->user->id);
            $query = [];
            if ($search->course) {
                $query['courseid'] = $search->course->id;
            }
            if ($search->label) {
                $query['labelid'] = $search->label->id;
            }
            if ($search->draft !== null) {
                $query['draft'] = $search->draft;
            }
            if ($search->roles) {
                $query['roles'] = [];
                foreach ($search->roles as $role) {
                    $query['roles'][] = external::ROLES[$role];
                }
            }
            if ($search->unread !== null) {
                $query['unread'] = $search->unread;
            }
            if ($search->starred !== null) {
                $query['starred'] = $search->starred;
            }
            if ($search->deleted) {
                $query['deleted'] = true;
            }
            if ($search->content != '') {
                $query['content'] = $search->content;
            }
            if ($search->sendername != '') {
                $query['sendername'] = $search->sendername;
            }
            if ($search->recipientname != '') {
                $query['recipientname'] = $search->recipientname;
            }
            if ($search->withfilesonly) {
                $query['withfilesonly'] = true;
            }
            if ($search->maxtime) {
                $query['maxtime'] = $search->maxtime;
            }
            if ($search->start) {
                $query['startid'] = $search->start->id;
            }
            if ($search->stop) {
                $query['stopid'] = $search->stop->id;
            }
            if ($search->reverse) {
                $query['reverse'] = true;
            }

            $expected = external::search_messages_response($search->user->id, $search->fetch());
            $result = external::search_messages($query);
            \external_api::validate_parameters(external::search_messages_returns(), $result);
            self::assertEquals($expected, $result, $search);

            // Offset and limit.
            $expected = external::search_messages_response($search->user->id, $search->fetch(5, 10));
            $result = external::search_messages($query, 5, 10);
            \external_api::validate_parameters(external::search_messages_returns(), $result);
            $this->assertEquals($expected, $result, $search . "\noffset: 5\n limit: 10");
        }

        // Invalid course.
        self::setUser($users[0]->id);
        $course = $generator->create_course();
        $query = ['courseid' => $course->id];
        try {
            external::search_messages($query);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }

        // Invalid label.
        self::setUser($users[0]->id);
        $labels = label::fetch_by_user($users[1]);
        $query = ['labelid' => reset($labels)->id];
        try {
            external::search_messages($query);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorlabelnotfound', $e->errorcode);
        }

        // Invalid startid.
        self::setUser($users[0]->id);
        $query = ['startid' => '-1'];
        try {
            external::search_messages($query);
            self::fail();
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals('invalid startid: -1', $e->debuginfo);
        }

        // Invalid stopid.
        self::setUser($users[0]->id);
        $query = ['stopid' => '-1'];
        try {
            external::search_messages($query);
            self::fail();
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals('invalid stopid: -1', $e->debuginfo);
        }
    }

    public function test_get_message() {
        list($users, $messages) = message_search_test::generate_data();

        $user = $users[0];
        $this->setUser($user->id);

        foreach ($messages as $message) {
            if ($user->can_view_message($message)) {
                $result = external::get_message($message->id);
                \external_api::validate_parameters(external::get_message_returns(), $result);
                $this->assertEquals(external::get_message_response($user, $message), $result);
            } else {
                try {
                    external::get_message($message->id);
                    self::fail();
                } catch (exception $e) {
                    self::assertEquals('errormessagenotfound', $e->errorcode);
                }
            }
        }

        // Inexistent message.
        try {
            external::get_message('-1');
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }
    }

    public function test_set_unread() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $this->setUser($user1->id);

        // Message from the user.

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($time);

        $result = external::set_unread($message->id, '1');
        $this->assertNull($result);
        $this->assertTrue(message::fetch($message->id)->unread[$user1->id]);

        $result = external::set_unread($message->id, '0');
        $this->assertNull($result);
        $this->assertFalse(message::fetch($message->id)->unread[$user1->id]);

        // Message sent to the user.

        $this->setUser($user2->id);

        $result = external::set_unread($message->id, '0');
        $this->assertNull($result);
        $this->assertFalse(message::fetch($message->id)->unread[$user2->id]);

        $result = external::set_unread($message->id, '1');
        $this->assertNull($result);
        $this->assertTrue(message::fetch($message->id)->unread[$user2->id]);

        $result = external::set_unread($message->id, '0');
        $this->assertNull($result);
        $this->assertFalse(message::fetch($message->id)->unread[$user2->id]);

        // Draft to the user (no permission).

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $draft = message::create($data);

        try {
            external::set_unread($draft->id, '0');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Invalid message.

        try {
            external::set_unread('-1', '1');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }
    }

    public function test_set_starred() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $this->setUser($user1->id);

        // Message from the user.

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($time);

        $result = external::set_starred($message->id, '1');
        $this->assertNull($result);
        $this->assertTrue(message::fetch($message->id)->starred[$user1->id]);

        $result = external::set_starred($message->id, '0');
        $this->assertNull($result);
        $this->assertFalse(message::fetch($message->id)->starred[$user1->id]);

        // Message sent to the user.

        $this->setUser($user2->id);

        $result = external::set_starred($message->id, '1');
        $this->assertNull($result);
        $this->assertTrue(message::fetch($message->id)->starred[$user2->id]);

        $result = external::set_starred($message->id, '0');
        $this->assertNull($result);
        $this->assertFalse(message::fetch($message->id)->starred[$user2->id]);

        $result = external::set_starred($message->id, '1');
        $this->assertNull($result);
        $this->assertTrue(message::fetch($message->id)->starred[$user2->id]);

        // Draft to the user (no permission).

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $draft = message::create($data);

        try {
            external::set_starred($draft->id, '1');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Invalid message.

        try {
            external::set_starred('-1', '1');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }
    }

    public function test_set_deleted() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $this->setUser($user1->id);

        // Message from the user.

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($time);

        $result = external::set_deleted($message->id, '1');
        $this->assertNull($result);
        $this->assertEquals(message::DELETED, message::fetch($message->id)->deleted[$user1->id]);

        $result = external::set_deleted($message->id, '0');
        $this->assertNull($result);
        $this->assertEquals(message::NOT_DELETED, message::fetch($message->id)->deleted[$user1->id]);

        $result = external::set_deleted($message->id, '2');
        $this->assertNull($result);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message->id)->deleted[$user1->id]);

        try {
            external::set_deleted($message->id, '0');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Message sent to the user.

        $this->setUser($user2->id);

        $result = external::set_deleted($message->id, '1');
        $this->assertNull($result);
        $this->assertEquals(message::DELETED, message::fetch($message->id)->deleted[$user2->id]);

        $result = external::set_deleted($message->id, '0');
        $this->assertNull($result);
        $this->assertEquals(message::NOT_DELETED, message::fetch($message->id)->deleted[$user2->id]);

        $result = external::set_deleted($message->id, '2');
        $this->assertNull($result);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message->id)->deleted[$user2->id]);

        try {
            external::set_deleted($message->id, '0');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Draft to the user (no permission).

        $this->setUser($user2->id);

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $draft = message::create($data);

        try {
            external::set_deleted($message->id, '1');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Draft from the user.

        $this->setUser($user1->id);

        $result = external::set_deleted($draft->id, '1');
        $this->assertNull($result);
        $this->assertEquals(message::DELETED, message::fetch($draft->id)->deleted[$user1->id]);

        $result = external::set_deleted($draft->id, '0');
        $this->assertNull($result);
        $this->assertEquals(message::NOT_DELETED, message::fetch($draft->id)->deleted[$user1->id]);

        $result = external::set_deleted($draft->id, '2');
        $this->assertNull($result);
        $this->assertNull(message::fetch($draft->id));

        // Invalid message.

        try {
            external::set_deleted('-1', '1');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }
    }

    public function test_empty_trash() {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $course3 = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course1->id);
        $generator->enrol_user($user1->id, $course2->id);
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

        $this->setUser($user1->id);

        $result = external::empty_trash();

        $this->assertNull($result);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message1->id)->deleted[$user1->id]);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message2->id)->deleted[$user1->id]);
        $this->assertEquals(message::NOT_DELETED, message::fetch($message3->id)->deleted[$user1->id]);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message4->id)->deleted[$user1->id]);
        $this->assertEquals(message::DELETED_FOREVER, message::fetch($message5->id)->deleted[$user1->id]);
        $this->assertEquals(message::DELETED, message::fetch($message6->id)->deleted[$user1->id]);
    }

    public function test_create_label() {
        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());
        self::setUser($user->id);

        $result = external::create_label('Label 1', 'blue');

        \external_api::validate_parameters(external::create_label_returns(), $result);
        $label = label::fetch($result);
        $this->assertNotNull($label);
        $this->assertEquals($user->id, $label->user->id);
        $this->assertEquals('Label 1', $label->name);
        $this->assertEquals('blue', $label->color);

        // Empty color.

        $result = external::create_label('Label 2');

        \external_api::validate_parameters(external::create_label_returns(), $result);
        $label = label::fetch($result);
        $this->assertNotNull($label);
        $this->assertEquals($user->id, $label->user->id);
        $this->assertEquals('Label 2', $label->name);
        $this->assertEquals('', $label->color);

        // Empty name.

        try {
            external::create_label('', 'blue');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('erroremptylabelname', $e->errorcode);
        }

        // Duplicated name.

        try {
            external::create_label('Label 1', 'blue');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorrepeatedlabelname', $e->errorcode);
        }

        // Invalid color.

        try {
            external::create_label('Label 3', 'invalid');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorinvalidcolor', $e->errorcode);
        }
    }

    public function test_update_label() {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        self::setUser($user1->id);

        $label1 = label::create($user1, 'Label 1', 'blue');
        $label2 = label::create($user1, 'Label 2', 'red');
        $label3 = label::create($user2, 'Label 3', 'yellow');

        $result = external::update_label($label1->id, 'Updated 1', 'green');

        $this->assertNull($result);
        $label1 = label::fetch($label1->id);
        $this->assertEquals($user1->id, $label1->user->id);
        $this->assertEquals('Updated 1', $label1->name);
        $this->assertEquals('green', $label1->color);

        // Unchaged name.

        $result = external::update_label($label1->id, 'Updated 1', 'yellow');

        $this->assertNull($result);
        $label1 = label::fetch($label1->id);
        $this->assertEquals($user1->id, $label1->user->id);
        $this->assertEquals('Updated 1', $label1->name);
        $this->assertEquals('yellow', $label1->color);

        // Empty color.

        $result = external::update_label($label1->id, 'Label 1');

        $this->assertNull($result);
        $label1 = label::fetch($label1->id);
        $this->assertEquals($user1->id, $label1->user->id);
        $this->assertEquals('Label 1', $label1->name);
        $this->assertEquals('', $label1->color);

        // Invalid label.

        try {
            external::update_label('-1', 'Label 1', 'blue');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorlabelnotfound', $e->errorcode);
        }

        // Label of another user.

        try {
            external::update_label($label3->id, 'Label 3', 'yellow');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorlabelnotfound', $e->errorcode);
        }

        // Empty name.

        try {
            external::update_label($label1->id, '', 'blue');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('erroremptylabelname', $e->errorcode);
        }

        // Duplicated name.

        try {
            external::update_label($label1->id, 'Label 2', 'blue');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorrepeatedlabelname', $e->errorcode);
        }

        // Invalid color.

        try {
            external::update_label($label1->id, 'Label 1', 'invalid');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorinvalidcolor', $e->errorcode);
        }
    }

    public function test_delete_label() {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $label1 = label::create($user1, 'Label 1', 'blue');
        $label2 = label::create($user2, 'Label 2', 'red');
        self::setUser($user1->id);

        $result = external::delete_label($label1->id);

        $this->assertNull($result);
        $label1 = label::fetch($label1->id);
        $this->assertNull($label1);

        // Invalid label.

        try {
            external::delete_label('-1');
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorlabelnotfound', $e->errorcode);
        }

        // Label of another user.

        try {
            external::delete_label($label2->id);
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorlabelnotfound', $e->errorcode);
        }
    }

    public function test_set_labels() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $label1 = label::create($user1, 'Label 1');
        $label2 = label::create($user1, 'Label 2');
        $label3 = label::create($user1, 'Label 3');
        $label4 = label::create($user2, 'Label 4');
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $this->setUser($user1->id);

        // Message from the user.

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($time);

        $result = external::set_labels($message->id, [$label1->id, $label2->id]);
        $this->assertNull($result);
        $message = message::fetch($message->id);
        $this->assertEquals([$label1->id, $label2->id], array_keys($message->labels[$user1->id]));

        $result = external::set_labels($message->id, [$label2->id, $label3->id]);
        $this->assertNull($result);
        $message = message::fetch($message->id);
        $this->assertEquals([$label2->id, $label3->id], array_keys($message->labels[$user1->id]));

        // Message sent to the user.

        $data = message_data::new($course, $user2);
        $data->to = [$user1];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($time);

        $result = external::set_labels($message->id, [$label1->id, $label2->id]);
        $this->assertNull($result);
        $message = message::fetch($message->id);
        $this->assertEquals([$label1->id, $label2->id], array_keys($message->labels[$user1->id]));

        $result = external::set_labels($message->id, [$label2->id, $label3->id]);
        $this->assertNull($result);
        $message = message::fetch($message->id);
        $this->assertEquals([$label2->id, $label3->id], array_keys($message->labels[$user1->id]));

        // Draft from the user.

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $message = message::create($data);

        $result = external::set_labels($message->id, [$label1->id, $label2->id]);
        $this->assertNull($result);
        $message = message::fetch($message->id);
        $this->assertEquals([$label1->id, $label2->id], array_keys($message->labels[$user1->id]));

        $result = external::set_labels($message->id, [$label2->id, $label3->id]);
        $this->assertNull($result);
        $message = message::fetch($message->id);
        $this->assertEquals([$label2->id, $label3->id], array_keys($message->labels[$user1->id]));

        // Draft to the user (no permission).

        $data = message_data::new($course, $user2);
        $data->to = [$user1];
        $data->subject = 'Subject';
        $message = message::create($data);

        try {
            $result = external::set_labels($message->id, [$label1->id, $label2->id]);
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Label of another user.

        $data = message_data::new($course, $user2);
        $data->to = [$user1];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($time);
        try {
            external::set_labels($message->id, [$label4->id]);
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorlabelnotfound', $e->errorcode);
        }

        // Invalid label.

        $data = message_data::new($course, $user2);
        $data->to = [$user1];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($time);
        try {
            external::set_labels($message->id, ['-1']);
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorlabelnotfound', $e->errorcode);
        }

        // Invalid message.

        try {
            external::set_labels('-1', ['1']);
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }
    }

    public function test_get_roles() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        $this->setUser($user->id);

        $expected = [];
        foreach ($course->get_viewable_roles($user) as $id => $name) {
            $expected[] = ['id' => $id, 'name' => $name];
        }
        $result = external::get_roles($course->id);
        \external_api::validate_parameters(external::get_roles_returns(), $result);
        self::assertEquals($expected, $result);

        // User not enrolled in course.

        $course = new course($generator->create_course());
        try {
            external::get_roles($course->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }

        // Inexistent course.

        try {
            external::get_roles(0);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }
    }

    public function test_get_groups() {
        $generator = self::getDataGenerator();
        $course = new course($generator->create_course(['groupmode' => SEPARATEGROUPS]));
        $group1 = $generator->create_group(['courseid' => $course->id]);
        $group2 = $generator->create_group(['courseid' => $course->id]);
        $group3 = $generator->create_group(['courseid' => $course->id]);
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        $generator->create_group_member(['userid' => $user->id, 'groupid' => $group1->id]);
        $generator->create_group_member(['userid' => $user->id, 'groupid' => $group2->id]);
        self::setUser($user->id);

        $expected = [
            ['id' => $group1->id, 'name' => $group1->name],
            ['id' => $group2->id, 'name' => $group2->name],
        ];
        $result = external::get_groups($course->id);
        \external_api::validate_parameters(external::get_groups_returns(), $result);
        self::assertEquals($expected, $result);

        // User not enrolled in course.

        $course = new course($generator->create_course());
        try {
            external::get_groups($course->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }

        // Inexistent course.

        try {
            external::get_groups(0);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }
    }

    public function test_search_users() {
        $generator = self::getDataGenerator();
        $users = user_search_test::generate_data();

        foreach (user_search_test::cases($users) as $search) {
            $this->setUser($search->user->id);
            $query = ['courseid' => $search->course->id];
            if ($search->roleid) {
                $query['roleid'] = $search->roleid;
            }
            if ($search->groupid) {
                $query['groupid'] = $search->groupid;
            }
            if (\core_text::strlen($search->fullname)) {
                $query['fullname'] = $search->fullname;
            }
            if ($search->include) {
                $query['include'] = $search->include;
            }

            $expected = external::search_users_response($search->fetch());
            $result = external::search_users($query);
            \external_api::validate_parameters(external::search_users_returns(), $result);
            self::assertEquals($expected, $result, $search);

            // Offset and limit.

            $expected = external::search_users_response($search->fetch(5, 10));
            $result = external::search_users($query, 5, 10);
            \external_api::validate_parameters(external::search_users_returns(), $result);
            $this->assertEquals($expected, $result, $search . "\noffset: 5\n limit: 10");
        }

        // User not enrolled in course.

        $course = new course($generator->create_course());
        $query = ['courseid' => $course->id];
        try {
            external::search_users($query);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }

        // Inexistent course.

        $query = ['courseid' => 0];
        try {
            external::search_users($query);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }
    }

    public function test_get_message_form() {
        $generator = $this->getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $data = message_data::new($course, $user1);
        $data->to = [$user2, $user3];
        $data->cc = [$user4];
        $data->bcc = [$user5];
        $data->subject = 'Subject';
        $data->content = 'Message content';
        $data->format = FORMAT_PLAIN;
        $data->time = $time;
        self::create_draft_file($data->draftitemid, 'file.txt', 'File content');
        $message = message::create($data);
        self::setUser($user1->id);

        $editortextpattern = '/.*<textarea[^>]* name="content\[text\]"[^>]*>([^<]*)<\/textarea>.*/';
        $editorformatpattern = '/.*<input[^>]* name="content\[format\]"[^>]* value="(\d+)".*/';
        $editoritemidpattern = '/.*<input[^>]* name="content\[itemid\]"[^>]* value="(\d+)".*/';
        $filemanagerpattern = '/.*<input[^>]* value="(\d+)"[^>]* name="attachments".*/';

        $result = external::get_message_form($message->id);

        \external_api::validate_parameters(external::get_message_form_returns(), $result);
        $html = format_text($data->content, $data->format, ['filter' => false, 'para' => false]);
        self::assertGreaterThan(0, $result['draftitemid']);
        self::assert_draft_files(['file.txt' => 'File content'], $result['draftitemid']);
        preg_match($editortextpattern, $result['editorhtml'], $matches);
        self::assertCount(2, $matches);
        self::assertEquals($html, $matches[1]);
        preg_match($editorformatpattern, $result['editorhtml'], $matches);
        self::assertCount(2, $matches);
        self::assertEquals(FORMAT_HTML, $matches[1]);
        preg_match($editoritemidpattern, $result['editorhtml'], $matches);
        self::assertCount(2, $matches);
        self::assertEquals($result['draftitemid'], $matches[1]);
        preg_match($filemanagerpattern, $result['filemanagerhtml'], $matches);
        self::assertCount(2, $matches);
        self::assertEquals($result['draftitemid'], $matches[1]);
        self::assertStringContainsString('<script', $result['javascript']);

        // Inexistent message.

        try {
            external::get_message_form(0);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Non-editable message.

        $message->send($time);
        try {
            external::get_message_form($message->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }
    }

    public function test_create_message() {
        $generator = $this->getDataGenerator();
        $course = new course($generator->create_course());
        $user = new user($generator->create_user());
        $generator->enrol_user($user->id, $course->id);
        $now = time();
        self::setUser($user->id);

        $result = external::create_message($course->id);

        \external_api::validate_parameters(external::create_message_returns(), $result);
        $draft = message::fetch($result);
        self::assertNotNull($draft);
        self::assertTrue($draft->draft);
        self::assertEquals($course, $draft->course);
        self::assertEquals('', $draft->subject);
        self::assertEquals('', $draft->content);
        self::assertEquals(FORMAT_HTML, $draft->format);
        self::assertEquals([$user->id => message::ROLE_FROM], $draft->roles);
        self::assertGreaterThanOrEqual($now, $draft->time);

        // User not enrolled in course.

        $course = new course($generator->create_course());
        try {
            external::create_message($course->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }

        // Inexistent course.

        try {
            external::create_message(0);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }
    }

    public function test_reply_message() {
        $generator = $this->getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $now = time();
        $data = message_data::new($course, $user1);
        $data->to = [$user2, $user3];
        $data->cc = [$user4];
        $data->bcc = [$user5];
        $data->subject = 'Subject';
        $data->content = 'Message content';
        $data->format = FORMAT_PLAIN;
        $data->time = $time;
        $message = message::create($data);
        $message->send($time);
        self::setUser($user2->id);

        // Reply to sender.

        $result = external::reply_message($message->id, false);

        \external_api::validate_parameters(external::reply_message_returns(), $result);
        $draft = message::fetch($result);
        self::assertNotNull($draft);
        self::assertTrue($draft->draft);
        self::assertEquals($data->course, $draft->course);
        self::assertEquals('RE: ' . $data->subject, $draft->subject);
        self::assertEquals('', $draft->content);
        self::assertEquals(FORMAT_HTML, $draft->format);
        self::assertEquals([
            $user1->id => message::ROLE_TO,
            $user2->id => message::ROLE_FROM,
        ], $draft->roles);
        self::assertGreaterThanOrEqual($now, $draft->time);

        // Reply to all.

        $result = external::reply_message($message->id, true);

        \external_api::validate_parameters(external::reply_message_returns(), $result);
        $draft = message::fetch($result);
        self::assertNotNull($draft);
        self::assertEquals([
            $user1->id => message::ROLE_TO,
            $user2->id => message::ROLE_FROM,
            $user3->id => message::ROLE_CC,
            $user4->id => message::ROLE_CC,
        ], $draft->roles);

        // User cannot view message.

        $course = new course($generator->create_course());
        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($data->time);
        try {
            external::reply_message($message->id, false);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Inexistent message.

        try {
            external::reply_message(0, false);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // User not enrolled in course.

        $course = new course($generator->create_course());
        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($data->time);
        try {
            external::reply_message($message->id, false);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }
    }

    public function test_forward_message() {
        $generator = $this->getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $now = time();
        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $data->content = 'Message content';
        $data->format = FORMAT_PLAIN;
        $data->time = $time;
        $message = message::create($data);
        $message->send($time);
        self::setUser($user2->id);

        $result = external::forward_message($message->id);

        \external_api::validate_parameters(external::forward_message_returns(), $result);
        $draft = message::fetch($result);
        self::assertNotNull($draft);
        self::assertTrue($draft->draft);
        self::assertEquals($data->course, $draft->course);
        self::assertEquals('FW: ' . $data->subject, $draft->subject);
        self::assertEquals('', $draft->content);
        self::assertEquals(FORMAT_HTML, $draft->format);
        self::assertEquals([$user2->id => message::ROLE_FROM], $draft->roles);
        self::assertGreaterThanOrEqual($now, $draft->time);

        // User cannot view message.

        $course = new course($generator->create_course());
        $data = message_data::new($course, $user1);
        $data->to = [$user3];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($data->time);
        try {
            external::forward_message($message->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Inexistent message.

        try {
            external::forward_message(0);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // User not enrolled in course.

        $course = new course($generator->create_course());
        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $message = message::create($data);
        $message->send($data->time);
        try {
            external::forward_message($message->id, false);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }
    }

    public function test_update_message() {
        $generator = $this->getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $course3 = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course1->id);
        $generator->enrol_user($user1->id, $course2->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $now = time();
        self::setUser($user1->id);

        $data = message_data::new($course1, $user1);
        $data->time = $time;
        $message = message::create($data);

        $data = [
            'courseid' => $course2->id,
            'to' => [$user2->id, $user3->id],
            'cc' => [$user4->id],
            'bcc' => [$user5->id],
            'subject' => 'Message subject',
            'content' => 'Message content',
            'format' => FORMAT_HTML,
            'draftitemid' => file_get_unused_draft_itemid(),
        ];
        self::create_draft_file($data['draftitemid'], 'file1.txt', 'File 1');
        self::create_draft_file($data['draftitemid'], 'file2.txt', 'File 2');

        $result = external::update_message($message->id, $data);
        self::assertNull($result);

        $message = message::fetch($message->id);
        self::assertEquals($course2, $message->course);
        self::assertEquals('Message subject', $message->subject);
        self::assertEquals('Message content', $message->content);
        self::assertEquals(FORMAT_HTML, $message->format);
        self::assertGreaterThanOrEqual($now, $message->time);
        self::assertEquals([
            $user1->id => message::ROLE_FROM,
            $user2->id => message::ROLE_TO,
            $user3->id => message::ROLE_TO,
            $user4->id => message::ROLE_CC,
            $user5->id => message::ROLE_BCC,
        ], $message->roles);
        self::assert_attachments([
            'file1.txt' => 'File 1',
            'file2.txt' => 'File 2'
        ], $message);

        // User cannot view message.

        $message = message::create(message_data::new($course3, $user1));
        try {
            external::update_message($message->id, $data);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Inexistent message.

        try {
            external::update_message(0, $data);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // User not enrolled in course.

        $message = message::create(message_data::new($course1, $user1));
        $data['courseid'] = $course3->id;
        try {
            external::update_message($message->id, $data);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorcoursenotfound', $e->errorcode);
        }
    }

    public function test_send_message() {
        $generator = $this->getDataGenerator();
        $course = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $user6 = new user($generator->create_user());
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);
        $generator->enrol_user($user3->id, $course->id);
        $generator->enrol_user($user4->id, $course->id);
        $generator->enrol_user($user5->id, $course->id);
        $time = make_timestamp(2021, 10, 11, 12, 0);
        $now = time();
        $data = message_data::new($course, $user1);
        $data->to = [$user2, $user3];
        $data->cc = [$user4];
        $data->bcc = [$user5];
        $data->subject = 'Subject';
        $data->content = 'Message content';
        $data->format = FORMAT_PLAIN;
        $data->time = $time;
        self::create_draft_file($data->draftitemid, 'file.txt', 'File content');
        $message = message::create($data);
        self::setUser($user1->id);

        $result = external::send_message($message->id);

        self::assertNull($result);
        $message = message::fetch($message->id);
        self::assertFalse($message->draft);
        self::assertGreaterThanOrEqual($now, $message->time);

        // User cannot edit message.

        try {
            external::send_message($message->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Inexistent message.

        try {
            external::send_message(0);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Empty subject.

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $message = message::create($data);
        try {
            external::send_message($message->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('erroremptysubject', $e->errorcode);
        }

        // No recipients.

        $data = message_data::new($course, $user1);
        $data->subject = 'Subject';
        $message = message::create($data);
        try {
            external::send_message($message->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('erroremptyrecipients', $e->errorcode);
        }

        // Invalid recipients.

        $data = message_data::new($course, $user1);
        $data->subject = 'Subject';
        $data->to = [$user6];
        $message = message::create($data);
        try {
            external::send_message($message->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errorinvalidrecipients', $e->errorcode);
        }

        // Too many recipients.

        set_config('maxrecipients', '3', 'local_mail');
        $data = message_data::new($course, $user1);
        $data->subject = 'Subject';
        $data->to = [$user2, $user3, $user4, $user5];
        $message = message::create($data);
        try {
            external::send_message($message->id);
            self::fail();
        } catch (exception $e) {
            self::assertEquals('errortoomanyrecipients', $e->errorcode);
            self::assertEquals(3, $e->a);
        }
    }
}
