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

/**
 * @covers \local_mail\external
 */
class external_test extends testcase {

    public function test_get_settings() {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
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
        list($users) = self::generate_search_data();

        foreach ($users as $user) {
            $this->setUser($user->id);
            $expected = [];
            foreach ($user->get_courses() as $course) {
                $search = new search($user);
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
        list($users) = self::generate_search_data();

        foreach ($users as $user) {
            $this->setUser($user->id);

            $expected = [];
            foreach (label::fetch_by_user($user) as $label) {
                $search = new search($user);
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

        list($users, $messages) = self::generate_search_data();

        foreach (self::search_cases($users, $messages) as $search) {
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

        list($users, $messages) = self::generate_search_data();

        foreach (self::search_cases($users, $messages) as $search) {
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
            $this->assertEquals($expected, $result, $search . "\nOffset: 5\n Limit: 10");
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
        list($users, $messages) = self::generate_search_data();

        $user = $users[0];
        $this->setUser($user->id);

        foreach ($messages as $message) {
            if ($user->can_view_message($message)) {
                $result = external::get_message($message->id);
                \external_api::validate_parameters(external::get_message_returns(), $result);
                $this->assertEquals(external::get_message_response($user->id, $message), $result);
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

        $message = message::create($course, $user1, $time);
        $message->update('Subject 1', 'Content 1', FORMAT_HTML, $time);
        $message->add_recipient($user2, message::ROLE_TO);
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

        $draft = message::create($course, $user1, $time);
        $draft->add_recipient($user2, message::ROLE_TO);

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

        $message = message::create($course, $user1, $time);
        $message->update('Subject 1', 'Content 1', FORMAT_HTML, $time);
        $message->add_recipient($user2, message::ROLE_TO);
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

        $draft = message::create($course, $user1, $time);
        $draft->add_recipient($user2, message::ROLE_TO);

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

        $message = message::create($course, $user1, $time);
        $message->update('Subject 1', 'Content 1', FORMAT_HTML, $time);
        $message->add_recipient($user2, message::ROLE_TO);
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

        $draft = message::create($course, $user1, $time);
        $draft->add_recipient($user2, message::ROLE_TO);

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

        $message1 = message::create($course1, $user1, $time);
        $message1->update('Subject 1', 'Content 1', FORMAT_HTML, $time);
        $message1->add_recipient($user2, message::ROLE_TO);
        $message1->send($time);
        $message1->set_deleted($user1, message::DELETED);

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

        $message = message::create($course, $user1, $time);
        $message->update('Subject 1', 'Content 1', FORMAT_HTML, $time);
        $message->add_recipient($user2, message::ROLE_TO);
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

        $message = message::create($course, $user2, $time);
        $message->update('Subject 2', 'Content 2', FORMAT_HTML, $time);
        $message->add_recipient($user1, message::ROLE_TO);
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

        $message = message::create($course, $user1, $time);
        $message->update('Subject 1', 'Content 1', FORMAT_HTML, $time);
        $message->add_recipient($user2, message::ROLE_TO);

        $result = external::set_labels($message->id, [$label1->id, $label2->id]);
        $this->assertNull($result);
        $message = message::fetch($message->id);
        $this->assertEquals([$label1->id, $label2->id], array_keys($message->labels[$user1->id]));

        $result = external::set_labels($message->id, [$label2->id, $label3->id]);
        $this->assertNull($result);
        $message = message::fetch($message->id);
        $this->assertEquals([$label2->id, $label3->id], array_keys($message->labels[$user1->id]));

        // Draft to the user (no permission).

        $message = message::create($course, $user2, $time);
        $message->update('Subject 2', 'Content 2', FORMAT_HTML, $time);
        $message->add_recipient($user1, message::ROLE_TO);

        try {
            $result = external::set_labels($message->id, [$label1->id, $label2->id]);
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errormessagenotfound', $e->errorcode);
        }

        // Label of another user.

        $message = message::create($course, $user2, $time);
        $message->update('Subject 2', 'Content 2', FORMAT_HTML, $time);
        $message->add_recipient($user1, message::ROLE_TO);
        $message->send($time);
        try {
            external::set_labels($message->id, [$label4->id]);
            $this->fail();
        } catch (exception $e) {
            $this->assertEquals('errorlabelnotfound', $e->errorcode);
        }

        // Invalid label.

        $message = message::create($course, $user2, $time);
        $message->update('Subject 2', 'Content 2', FORMAT_HTML, $time);
        $message->add_recipient($user1, message::ROLE_TO);
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
}
