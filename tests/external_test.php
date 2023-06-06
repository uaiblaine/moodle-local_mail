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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/local/mail/message.class.php');

/**
 * @covers \local_mail_external
 */
class external_test extends \advanced_testcase {

    private $course1;
    private $course2;
    private $course3;
    private $user1;
    private $user2;
    private $user3;
    private $user4;
    private $user5;

    public function setUp(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $this->course3 = $generator->create_course(['shortname' => 'C3', 'fullname' => 'Course 3']);
        $this->course2 = $generator->create_course(['shortname' => 'C2', 'fullname' => 'Course 2']);
        $this->course1 = $generator->create_course(['shortname' => 'C1', 'fullname' => 'Course 1']);
        $this->user1 = $generator->create_user(['username' => 'u1', 'lastbane' => 'Afeninas']);
        $this->user2 = $generator->create_user(['username' => 'u2', 'lastname' => 'Buristaki']);
        $this->user3 = $generator->create_user(['username' => 'u3', 'lastname' => 'Combitti']);
        $this->user4 = $generator->create_user(['username' => 'u4', 'lastname' => 'Dupsal']);
        $this->user5 = $generator->create_user(['username' => 'u5', 'lastname' => 'Emferz']);
        $generator->enrol_user($this->user1->id, $this->course1->id);
        $generator->enrol_user($this->user1->id, $this->course2->id);
        $generator->enrol_user($this->user1->id, $this->course3->id);
        $generator->enrol_user($this->user2->id, $this->course1->id);
        $generator->enrol_user($this->user2->id, $this->course2->id);
        $generator->enrol_user($this->user2->id, $this->course3->id);
        $generator->enrol_user($this->user3->id, $this->course1->id);
        $generator->enrol_user($this->user3->id, $this->course2->id);
        $generator->enrol_user($this->user3->id, $this->course3->id);
    }

    public function test_get_info() {
        $this->setUser($this->user3->id);
        set_config('globaltrays', 'drafts,trash', 'local_mail');
        set_config('coursetrays', 'unread', 'local_mail');
        set_config('coursetraysname', 'shortname', 'local_mail');
        set_config('coursebadges', 'none', 'local_mail');
        set_config('coursebadgeslength', 10, 'local_mail');
        set_user_preference('local_mail_mailsperpage', 20);
        set_user_preference('local_mail_markasread', 1);
        $result = \local_mail_external::get_info();

        \external_api::validate_parameters(\local_mail_external::get_info_returns(), $result);
        $this->assertEquals($this->user3->id, $result['userid']);
        $settings = [
            'globaltrays' => ['drafts', 'trash'],
            'coursetrays' => 'unread',
            'coursetraysname' => 'shortname',
            'coursebadges' => 'none',
            'coursebadgeslength' => 10,
        ];
        $this->assertEquals($settings, $result['settings']);
        $preferences = [
            'perpage' => 20,
            'markasread' => true,
        ];
        $this->assertEquals($preferences, $result['preferences']);
        $this->assertEquals(local_mail_get_strings(), $result['strings']);

        // Default settings and preferences.

        unset_config('globaltrays', 'local_mail');
        unset_config('coursetrays', 'local_mail');
        unset_config('coursetraysname', 'local_mail');
        unset_config('coursebadges', 'local_mail');
        unset_config('coursebadgeslength', 'local_mail');
        unset_user_preference('local_mail_mailsperpage');
        unset_user_preference('local_mail_markasread');

        $result = \local_mail_external::get_info();

        \external_api::validate_parameters(\local_mail_external::get_info_returns(), $result);
        $preferences = [
            'perpage' => 10,
            'markasread' => false,
        ];
        $this->assertEquals($preferences, $result['preferences']);
        $settings = [
            'globaltrays' => ['starred', 'sent', 'drafts', 'trash'],
            'coursetrays' => 'all',
            'coursetraysname' => 'fullname',
            'coursebadges' => 'fullname',
            'coursebadgeslength' => 20,
        ];
        $this->assertEquals($settings, $result['settings']);

        // Invalid perpage preference.

        set_user_preference('local_mail_mailsperpage', 4);

        $result = \local_mail_external::get_info();

        \external_api::validate_parameters(\local_mail_external::get_info_returns(), $result);
        $preferences = [
            'perpage' => 5,
            'markasread' => false,
        ];
        $this->assertEquals($preferences, $result['preferences']);

        set_user_preference('local_mail_mailsperpage', 101);

        $result = \local_mail_external::get_info();

        \external_api::validate_parameters(\local_mail_external::get_info_returns(), $result);
        $preferences = [
            'perpage' => 100,
            'markasread' => false,
        ];
        $this->assertEquals($preferences, $result['preferences']);

    }

    public function test_set_preferences() {
        $this->setUser($this->user3->id);
        set_user_preference('local_mail_mailsperpage', 10);
        set_user_preference('local_mail_markasread', 0);

        $result = \local_mail_external::set_preferences(['perpage' => '20', 'markasread' => true]);

        $this->assertNull($result);
        $this->assertEquals('20', get_user_preferences('local_mail_mailsperpage'));
        $this->assertEquals('1', get_user_preferences('local_mail_markasread'));

        // Optional preferences.

        $result = \local_mail_external::set_preferences(['perpage' => '50']);

        $this->assertNull($result);
        $this->assertEquals('50', get_user_preferences('local_mail_mailsperpage'));
        $this->assertEquals('1', get_user_preferences('local_mail_markasread'));

        // Invalid perpage.

        try {
            \local_mail_external::set_preferences(['perpage' => '4']);
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \invalid_parameter_exception('"perpage" must be between 5 and 100'), $exception);
        }

        try {
            \local_mail_external::set_preferences(['perpage' => '101']);
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \invalid_parameter_exception('"perpage" must be between 5 and 100'), $exception);
        }
    }

    public function test_get_unread_count() {
        $this->setUser($this->user3->id);

        $message1 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message1->add_recipient('to', $this->user3->id);
        $message1->send();

        $message2 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message2->add_recipient('to', $this->user3->id);
        $message2->send();
        $message2->set_unread($this->user3->id, false);

        $message3 = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message3->add_recipient('to', $this->user3->id);
        $message3->send();

        $message4 = \local_mail_message::create($this->user1->id, $this->course2->id);
        $message4->add_recipient('to', $this->user3->id);
        $message4->send();

        $result = \local_mail_external::get_unread_count();

        \external_api::validate_parameters(\local_mail_external::get_unread_count_returns(), $result);

        $this->assertEquals(3, $result);
    }

    public function test_get_menu() {
        course_change_visibility($this->course2->id, false);
        $this->setUser($this->user3->id);
        // Assign teacher role so it can view hidden courses.
        $roleid = key(get_archetype_roles('teacher'));
        role_assign($roleid, $this->user3->id, \context_system::instance());

        $label1 = \local_mail_label::create($this->user3->id, 'Label 1');
        $label2 = \local_mail_label::create($this->user3->id, 'Label 2');

        $message1 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message1->add_recipient('to', $this->user3->id);
        $message1->send();

        $message2 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message2->add_recipient('to', $this->user3->id);
        $message2->send();
        $message2->set_unread($this->user3->id, false);

        $message3 = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message3->add_recipient('to', $this->user3->id);
        $message3->send();
        $message3->add_label($label1);

        $message4 = \local_mail_message::create($this->user1->id, $this->course2->id);
        $message4->add_recipient('to', $this->user3->id);
        $message4->send();
        $message4->add_label($label1);

        $message5 = \local_mail_message::create($this->user3->id, $this->course1->id);

        $message5 = \local_mail_message::create($this->user3->id, $this->course2->id);

        $result = \local_mail_external::get_menu();

        \external_api::validate_parameters(\local_mail_external::get_menu_returns(), $result);

        $this->assertEquals(3, $result['unread']);
        $this->assertEquals(2, $result['drafts']);
        $courses = [[
            'id' => $this->course1->id,
            'shortname' => $this->course1->shortname,
            'fullname' => $this->course1->fullname,
            'unread' => 2,
            'visible' => true,
        ], [
            'id' => $this->course3->id,
            'shortname' => $this->course3->shortname,
            'fullname' => $this->course3->fullname,
            'unread' => 0,
            'visible' => true,
        ], [
            'id' => $this->course2->id,
            'shortname' => $this->course2->shortname,
            'fullname' => $this->course2->fullname,
            'unread' => 1,
            'visible' => false,
        ]];
        $this->assertEquals($courses, $result['courses']);
        $labels = [[
            'id' => $label1->id(),
            'name' => $label1->name(),
            'color' => $label1->color(),
            'unread' => 2,
        ], [
            'id' => $label2->id(),
            'name' => $label2->name(),
            'color' => $label2->color(),
            'unread' => 0,
        ]];
        $this->assertEquals($labels, $result['labels']);
    }

    public function test_get_index() {
        $this->setUser($this->user3->id);

        $label1 = \local_mail_label::create($this->user3->id, 'Label 1', 'red');

        $message1 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message1->add_recipient('to', $this->user3->id);
        $message1->send(1470000001);

        $message2 = \local_mail_message::create($this->user1->id, $this->course2->id);
        $message2->save('Subject 2', 'Content 2', FORMAT_HTML, 3);
        $message2->add_recipient('to', $this->user3->id);
        $message2->add_recipient('cc', $this->user4->id);
        $message2->add_recipient('bcc', $this->user5->id);
        $message2->send(1470000002);
        $message2->add_label($label1);

        $message3 = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message3->save('Subject 3', 'Content 3', FORMAT_HTML, 0);
        $message3->add_recipient('to', $this->user3->id);
        $message3->add_recipient('to', $this->user4->id);
        $message3->send(1470000003);
        $message3->set_unread($this->user3->id, false);
        $message3->set_starred($this->user3->id, true);

        $message4 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message4->save('Subject 4', 'Content 4', FORMAT_HTML, 0);
        $message4->add_recipient('to', $this->user3->id);
        $message4->send(1470000004);
        $message4->add_label($label1);

        $message5 = \local_mail_message::create($this->user1->id, $this->course2->id);
        $message5->save('Subject 5', 'Content 5', FORMAT_HTML, 0);
        $message5->add_recipient('to', $this->user3->id);
        $message5->send(1470000005);
        $message5->add_label($label1);

        $message6 = \local_mail_message::create($this->user3->id, $this->course1->id);
        $message6->save('Subject 6', 'Content 6', FORMAT_HTML, 2, 1470000005);

        // All mesages in the inbox of user 3.

        $result = \local_mail_external::get_index('inbox', 0, 0, 0);
        \external_api::validate_parameters(\local_mail_external::get_index_returns(), $result);
        $messages = [$message5, $message4, $message3, $message2, $message1];
        $this->assertEquals($this->index_response(5, $messages), $result);

        // Some messages in the inbox of user 3.

        $result = \local_mail_external::get_index('inbox', 0, 1, 3);
        \external_api::validate_parameters(\local_mail_external::get_index_returns(), $result);
        $messages = [$message4, $message3, $message2];
        $this->assertEquals($this->index_response(5, $messages), $result);

        // All Messages in the course 1 of user 3.

        $result = \local_mail_external::get_index('course', $this->course1->id, 0, 0);
        $messages = [$message6, $message4, $message3, $message1];
        $this->assertEquals($this->index_response(4, $messages), $result);

        // Some Messages in the course 1 of user 3.

        $result = \local_mail_external::get_index('course', $this->course1->id, 1, 2);
        $messages = [$message4, $message3];
        $this->assertEquals($this->index_response(4, $messages), $result);

        // All Messages in the label 1 of user 3.

        $result = \local_mail_external::get_index('label', $label1->id(), 0, 0);
        $messages = [$message5, $message4, $message2];
        $this->assertEquals($this->index_response(3, $messages), $result);

        // Empty result with offset and limit.

        $result = \local_mail_external::get_index('inbox', 0, 10, 20);
        $this->assertEquals($this->index_response(5, []), $result);
    }

    public function test_search_index() {
        $this->setUser($this->user3->id);

        $label1 = \local_mail_label::create($this->user3->id, 'Label 1', 'red');
        $label2 = \local_mail_label::create($this->user3->id, 'Label 2', 'blue');

        $message1 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message1->save('Subject 1', 'Content 1', FORMAT_HTML, 0);
        $message1->add_recipient('to', $this->user3->id);
        $message1->send(1470000001);
        $message1->set_unread($this->user3->id, false);
        $message1->set_starred($this->user3->id, true);

        $message2 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message2->save('Subject 2 (Test)', 'Content 2', FORMAT_HTML, 3);
        $message2->add_recipient('to', $this->user3->id);
        $message2->add_recipient('cc', $this->user4->id);
        $message2->add_recipient('bcc', $this->user5->id);
        $message2->send(1470000002);
        $message2->set_unread($this->user3->id, false);

        $message3 = \local_mail_message::create($this->user2->id, $this->course2->id);
        $message3->save('Subject 3', 'Content 3 (Test)', FORMAT_HTML, 0);
        $message3->add_recipient('to', $this->user3->id);
        $message3->add_recipient('to', $this->user4->id);
        $message3->send(1470000003);
        $message3->set_unread($this->user3->id, false);
        $message3->set_starred($this->user3->id, true);
        $message3->add_label($label1);

        $message4 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message4->save('Subject 4', 'Content 4', FORMAT_HTML, 0);
        $message4->add_recipient('to', $this->user3->id);
        $message4->send(1470000004);
        $message4->add_label($label1);

        $message5 = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message5->save('Subject 5', 'Content 5', FORMAT_HTML, 1);
        $message5->add_recipient('to', $this->user3->id);
        $message5->send(1470000004);
        $message5->set_unread($this->user3->id, false);

        $message6 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message6->save('Subject 6', 'Content 6 (Test)', FORMAT_HTML, 0);
        $message6->add_recipient('to', $this->user3->id);
        $message6->add_recipient('bcc', $this->user4->id);
        $message6->send(1470000005);
        $message6->add_label($label1);

        $message7 = \local_mail_message::create($this->user1->id, $this->course2->id);
        $message7->save('Subject 7', 'Content 7', FORMAT_HTML, 0);
        $message7->add_recipient('to', $this->user2->id);
        $message7->add_recipient('bcc', $this->user3->id);
        $message7->send(1470000006);

        $message8 = \local_mail_message::create($this->user3->id, $this->course2->id);
        $message8->save('Subject 8', 'Content 8', FORMAT_HTML, 2, 1470000007);

        $message9 = \local_mail_message::create($this->user1->id, $this->course2->id);
        $message9->save('Subject 9', 'Content 9', FORMAT_HTML, 0);
        $message9->add_recipient('to', $this->user3->id);
        $message9->send(1470000003);
        $message9->set_deleted($this->user3->id, \local_mail_message::DELETED);

        $message10 = \local_mail_message::create($this->user3->id, $this->course2->id);
        $message10->save('Subject 10', 'Content 10', FORMAT_HTML, 0);
        $message10->add_recipient('to', $this->user1->id);
        $message10->send(1470000008);

        // All messages in the inbox.
        $result = \local_mail_external::search_index('inbox', null, []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $messages = [$message7, $message6, $message5, $message4, $message3, $message2, $message1];
        $expected = $this->search_response(7, $messages, 0, 6, 0, 0);
        $this->assertEquals($expected, $result);

        // All messages in the inbox, backwards.
        $result = \local_mail_external::search_index('inbox', null, ['backwards' => true]);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $messages = [$message7, $message6, $message5, $message4, $message3, $message2, $message1];
        $expected = $this->search_response(7, $messages, 0, 6, 0, 0);
        $this->assertEquals($expected, $result);

        // Some messages in the inbox.
        $result = \local_mail_external::search_index('inbox', null, ['limit' => 3]);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message7, $message6, $message5], 0, 2, 0, $message4->id());
        $this->assertEquals($expected, $result);

        // Some messages in the inbox, backwards.
        $result = \local_mail_external::search_index('inbox', null, ['backwards' => true, 'limit' => 3]);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message3, $message2, $message1], 4, 6, $message4->id(), 0);
        $this->assertEquals($expected, $result);

        // Some messages in the inbox, starting from message 5.
        $query = ['startid' => $message5->id(), 'limit' => 3];
        $result = \local_mail_external::search_index('inbox', null, $query);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message5, $message4, $message3], 2, 4, $message6->id(), $message2->id());
        $this->assertEquals($expected, $result);

        // Some messages in the inbox, starting from message 3, backwards.
        $query = ['startid' => $message3->id(), 'backwards' => true, 'limit' => 3];
        $result = \local_mail_external::search_index('inbox', null, $query);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message5, $message4, $message3], 2, 4, $message6->id(), $message2->id());
        $this->assertEquals($expected, $result);

        // Starting from an inexistent message.
        $query = ['startid' => -1, 'limit' => 1];
        $result = \local_mail_external::search_index('inbox', null, $query);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [], 0, 0, 0, 0);
        $this->assertEquals($expected, $result);

        // Starting from message not present in the index.
        $query = ['startid' => $message9->id(), 'limit' => 1];
        $result = \local_mail_external::search_index('inbox', null, $query);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message3], 4, 4, $message4->id(), $message2->id());
        $this->assertEquals($expected, $result);

        // Starting from message not present in the index, backwards.
        $query = ['startid' => $message9->id(), 'limit' => 1, 'backwards' => true];
        $result = \local_mail_external::search_index('inbox', null, $query);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message4], 3, 3, $message5->id(), $message3->id());
        $this->assertEquals($expected, $result);

        // Unread messages in the inbox.
        $result = \local_mail_external::search_index('inbox', null, ['unread' => true]);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message7, $message6, $message4], 0, 3, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages with attachments in the inbox.
        $result = \local_mail_external::search_index('inbox', null, ['attachments' => true]);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message5, $message2], 2, 5, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages older than a timestamp in the inbox.
        $result = \local_mail_external::search_index('inbox', null, ['time' => 1470000003]);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message3, $message2, $message1], 4, 6, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages in the inbox that contain "test".
        $result = \local_mail_external::search_index('inbox', null, ['content' => 'test']);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message6, $message3, $message2], 1, 5, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages in the inbox send by "Buristaki".
        $result = \local_mail_external::search_index('inbox', null, ['sender' => 'Buristaki']);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message5, $message3], 2, 4, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages in the inbox send to "Dupsal".
        $result = \local_mail_external::search_index('inbox', null, ['recipients' => 'Dupsal']);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(7, [$message3, $message2], 4, 5, 0, 0);
        $this->assertEquals($expected, $result);

        // Starred messages.
        $result = \local_mail_external::search_index('starred', null, []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(2, [$message3, $message1], 0, 1, 0, 0);
        $this->assertEquals($expected, $result);

        // Drafts.
        $result = \local_mail_external::search_index('drafts', null, []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(1, [$message8], 0, 0, 0, 0);
        $this->assertEquals($expected, $result);

        // Sent messages.
        $result = \local_mail_external::search_index('sent', null, []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(1, [$message10], 0, 0, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages in trash.
        $result = \local_mail_external::search_index('trash', null, []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(1, [$message9], 0, 0, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages in course 2.
        $result = \local_mail_external::search_index('course', $this->course2->id, []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(4, [$message10, $message8, $message7, $message3], 0, 3, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages in course 3.
        $result = \local_mail_external::search_index('course', $this->course3->id, []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(0, [], 0, 0, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages in label 1.
        $result = \local_mail_external::search_index('label', $label1->id(), []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(3, [$message6, $message4, $message3], 0, 2, 0, 0);
        $this->assertEquals($expected, $result);

        // Messages in label 2.
        $result = \local_mail_external::search_index('label', $label2->id(), []);
        \external_api::validate_parameters(\local_mail_external::search_index_returns(), $result);
        $expected = $this->search_response(0, [], 0, 0, 0, 0);
        $this->assertEquals($expected, $result);
    }

    public function test_get_message() {
        $this->setUser($this->user1->id);

        $label1 = \local_mail_label::create($this->user1->id, 'Label 1', 'red');
        $label2 = \local_mail_label::create($this->user1->id, 'Label 2', 'blue');
        $label3 = \local_mail_label::create($this->user2->id, 'Label 3', 'green');

        // Message from the user with various recipients, attachments and labels.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 1', 'Content 1', FORMAT_HTML, 3);
        $message->add_recipient('to', $this->user2->id);
        $message->add_recipient('cc', $this->user3->id);
        $message->add_recipient('bcc', $this->user4->id);
        $file1 = $this->create_attachment($message, 'file1.txt', 'First file');
        $file2 = $this->create_attachment($message, 'file2.png', 'Second file');
        $file3 = $this->create_attachment($message, 'file3.pdf', 'Third file');
        $message->send();
        $message->set_starred($this->user1->id, true);
        $message->add_label($label1);
        $message->add_label($label2);
        $message->add_label($label3);

        $result = \local_mail_external::get_message($message->id());

        \external_api::validate_parameters(\local_mail_external::get_message_returns(), $result);

        $this->assertEquals($this->message_response($message, [$file1, $file2, $file3], []), $result);

        // Message to the user with a BCC recipient that is hidden from the user.

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_MOODLE, 0);
        $message->add_recipient('to', $this->user1->id);
        $message->add_recipient('bcc', $this->user3->id);
        $message->send();

        $result = \local_mail_external::get_message($message->id());

        \external_api::validate_parameters(\local_mail_external::get_message_returns(), $result);
        $this->assertEquals($this->message_response($message, [], []), $result);

        // Draft from the user.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 3', 'Content 3', FORMAT_HTML, 0);
        $message->add_recipient('to', $this->user2->id);

        $result = \local_mail_external::get_message($message->id());

        \external_api::validate_parameters(\local_mail_external::get_message_returns(), $result);
        $this->assertEquals($this->message_response($message, [], []), $result);

        // Message with references.

        $message1 = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message1->save('Subject 4', 'Content 4', FORMAT_HTML, 3);
        $message1->add_recipient('to', $this->user3->id);
        $message1->send(make_timestamp(2016, 12, 30, 14, 25, 59));

        $message2 = $message1->forward($this->user3->id);
        $message2->add_recipient('to', $this->user4->id);
        $message2->send(make_timestamp(2016, 12, 31, 9, 55, 17));

        $message3 = $message2->reply($this->user4->id);
        $file4 = $this->create_attachment($message3, 'file4.txt', 'Fourth file');
        $file5 = $this->create_attachment($message3, 'file5.png', 'Fifth file');
        $message3->send(make_timestamp(2016, 12, 31, 14, 23, 55));

        $message4 = $message3->forward($this->user3->id);
        $message4->add_recipient('to', $this->user1->id);
        $message4->send(make_timestamp(2017, 1, 2, 20, 33, 41));

        $result = \local_mail_external::get_message($message4->id());

        \external_api::validate_parameters(\local_mail_external::get_message_returns(), $result);

        $references = [
            $this->reference_response($message3, [$file4, $file5]),
            $this->reference_response($message2, []),
            $this->reference_response($message1, []),
        ];
        $this->assertEquals($this->message_response($message4, [], $references), $result);

        // Draft to the user (no permission).

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 4', 'Content 4', FORMAT_HTML, 0);
        $message->add_recipient('to', $this->user1->id);

        try {
            \local_mail_external::get_message($message->id());
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }

        // Invalid message.

        try {
            \local_mail_external::get_message('-1');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }
    }


    public function test_find_offset() {
        $this->setUser($this->user3->id);

        $label1 = \local_mail_label::create($this->user3->id, 'Label 1', 'red');

        $message1 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message1->add_recipient('to', $this->user3->id);
        $message1->send(1470000001);

        $message2 = \local_mail_message::create($this->user1->id, $this->course2->id);
        $message2->save('Subject 2', 'Content 2', FORMAT_HTML, 3);
        $message2->add_recipient('to', $this->user3->id);
        $message2->send(1470000002);
        $message2->add_label($label1);

        $message3 = \local_mail_message::create($this->user3->id, $this->course1->id);
        $message3->save('Subject 3', 'Content 3', FORMAT_HTML, 0);
        $message3->add_recipient('to', $this->user1->id);
        $message3->send(1470000003);
        $message3->set_starred($this->user3->id, true);

        $message4 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message4->save('Subject 4', 'Content 4', FORMAT_HTML, 0);
        $message4->add_recipient('to', $this->user3->id);
        $message4->send(1470000004);
        $message4->add_label($label1);

        $message5 = \local_mail_message::create($this->user1->id, $this->course2->id);
        $message5->save('Subject 5', 'Content 5', FORMAT_HTML, 0);
        $message5->add_recipient('to', $this->user3->id);
        $message5->send(1470000005);
        $message5->add_label($label1);

        $message6 = \local_mail_message::create($this->user3->id, $this->course1->id);
        $message6->save('Subject 6', 'Content 6', FORMAT_HTML, 2, 1470000006);

        $this->assertEquals(0, \local_mail_external::find_offset('inbox', 0, $message6->id()));
        $this->assertEquals(0, \local_mail_external::find_offset('inbox', 0, $message5->id()));
        $this->assertEquals(1, \local_mail_external::find_offset('inbox', 0, $message4->id()));
        $this->assertEquals(2, \local_mail_external::find_offset('inbox', 0, $message3->id()));
        $this->assertEquals(2, \local_mail_external::find_offset('inbox', 0, $message2->id()));
        $this->assertEquals(3, \local_mail_external::find_offset('inbox', 0, $message1->id()));
        $this->assertEquals(0, \local_mail_external::find_offset('course', $this->course1->id, $message6->id()));
        $this->assertEquals(1, \local_mail_external::find_offset('course', $this->course1->id, $message4->id()));
        $this->assertEquals(2, \local_mail_external::find_offset('course', $this->course1->id, $message3->id()));
        $this->assertEquals(3, \local_mail_external::find_offset('course', $this->course1->id, $message1->id()));
        $this->assertEquals(0, \local_mail_external::find_offset('label', $label1->id(), $message5->id()));
        $this->assertEquals(1, \local_mail_external::find_offset('label', $label1->id(), $message4->id()));
        $this->assertEquals(2, \local_mail_external::find_offset('label', $label1->id(), $message2->id()));

        // Draft to the user (no permission).

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 4', 'Content 4', FORMAT_HTML, 0);
        $message->add_recipient('to', $this->user1->id);

        try {
            \local_mail_external::find_offset('inbox', 0, $message->id());
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }

        // Invalid message.

        try {
            \local_mail_external::find_offset('inbox', 0, -1);
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }
    }

    public function test_set_unread() {
        $this->setUser($this->user1->id);

        // Message from the user.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 1', 'Content 1', FORMAT_HTML);
        $message->add_recipient('to', $this->user2->id);
        $message->send();

        $result = \local_mail_external::set_unread($message->id(), '1');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertTrue($message->unread($this->user1->id));

        $result = \local_mail_external::set_unread($message->id(), '0');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertFalse($message->unread($this->user1->id));

        // Message sent to the user.

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);
        $message->send();

        $result = \local_mail_external::set_unread($message->id(), '0');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertFalse($message->unread($this->user1->id));

        $result = \local_mail_external::set_unread($message->id(), '1');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertTrue($message->unread($this->user1->id));

        // Draft to the user (no permission).

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);

        try {
            \local_mail_external::set_unread($message->id(), '0');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }

        // Invalid message.

        try {
            \local_mail_external::set_unread('-1', '1');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }
    }

    public function test_set_starred() {
        $this->setUser($this->user1->id);

        // Message from the user.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 1', 'Content 1', FORMAT_HTML);
        $message->add_recipient('to', $this->user2->id);
        $message->send();

        $result = \local_mail_external::set_starred($message->id(), '1');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertTrue($message->starred($this->user1->id));

        $result = \local_mail_external::set_starred($message->id(), '0');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertFalse($message->starred($this->user1->id));

        // Message sent to the user.

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);
        $message->send();

        $result = \local_mail_external::set_starred($message->id(), '1');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertTrue($message->starred($this->user1->id));

        $result = \local_mail_external::set_starred($message->id(), '0');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertFalse($message->starred($this->user1->id));

        // Draft from the user.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 1', 'Content 1', FORMAT_HTML);
        $message->add_recipient('to', $this->user2->id);

        $result = \local_mail_external::set_starred($message->id(), '1');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertTrue($message->starred($this->user1->id));

        $result = \local_mail_external::set_starred($message->id(), '0');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertFalse($message->starred($this->user1->id));

        // Draft to the user (no permission).

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);

        try {
            \local_mail_external::set_starred($message->id(), '1');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }

        // Invalid message.

        try {
            \local_mail_external::set_starred('-1', '1');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }
    }

    public function test_set_deleted() {
        $this->setUser($this->user1->id);

        // Message from the user.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 1', 'Content 1', FORMAT_HTML);
        $message->add_recipient('to', $this->user2->id);
        $message->send();

        $result = \local_mail_external::set_deleted($message->id(), '1');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals(\local_mail_message::DELETED, $message->deleted($this->user1->id));

        $result = \local_mail_external::set_deleted($message->id(), '0');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals(\local_mail_message::NOT_DELETED, $message->deleted($this->user1->id));

        $result = \local_mail_external::set_deleted($message->id(), '2');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals(\local_mail_message::DELETED_FOREVER, $message->deleted($this->user1->id));

        try {
            \local_mail_external::set_deleted($message->id(), '0');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }

        // Message sent to the user.

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);
        $message->send();

        $result = \local_mail_external::set_deleted($message->id(), '1');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals(\local_mail_message::DELETED, $message->deleted($this->user1->id));

        $result = \local_mail_external::set_deleted($message->id(), '0');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals(\local_mail_message::NOT_DELETED, $message->deleted($this->user1->id));

        $result = \local_mail_external::set_deleted($message->id(), '2');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals(\local_mail_message::DELETED_FOREVER, $message->deleted($this->user1->id));

        try {
            \local_mail_external::set_deleted($message->id(), '0');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }

        // Draft from the user.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 1', 'Content 1', FORMAT_HTML);
        $message->add_recipient('to', $this->user2->id);

        $result = \local_mail_external::set_deleted($message->id(), '1');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals(\local_mail_message::DELETED, $message->deleted($this->user1->id));

        $result = \local_mail_external::set_deleted($message->id(), '0');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals(\local_mail_message::NOT_DELETED, $message->deleted($this->user1->id));

        $result = \local_mail_external::set_deleted($message->id(), '2');
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertFalse($message);

        // Draft to the user (no permission).

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);

        try {
            \local_mail_external::set_deleted($message->id(), '1');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }

        // Invalid message.

        try {
            \local_mail_external::set_deleted('-1', '1');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }
    }
    public function test_empty_trash() {
        $this->setUser($this->user1->id);

        $message1 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message1->save('Subject 1', 'Content 1', FORMAT_HTML);
        $message1->add_recipient('to', $this->user2->id);
        $message1->send();
        $message1->set_deleted($this->user1->id, \local_mail_message::DELETED);
        $message1->set_deleted($this->user2->id, \local_mail_message::DELETED);

        $message2 = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message2->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message2->add_recipient('to', $this->user1->id);
        $message2->send();
        $message2->set_deleted($this->user1->id, \local_mail_message::DELETED);

        $message3 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message3->save('Subject 3', 'Content 3', FORMAT_HTML);
        $message3->set_deleted($this->user1->id, \local_mail_message::DELETED);

        $message4 = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message4->save('Subject 4', 'Content 4', FORMAT_HTML);
        $message4->add_recipient('to', $this->user2->id);
        $message4->send();

        $result = \local_mail_external::empty_trash();
        $this->assertNull($result);

        $messages = \local_mail_message::fetch_index($this->user1->id, 'trash');
        $this->assertEquals([], $messages);
        $messages = \local_mail_message::fetch_index($this->user1->id, 'sent');
        $this->assertEquals([\local_mail_message::fetch($message4->id())], $messages);
        $messages = \local_mail_message::fetch_index($this->user2->id, 'trash');
        $this->assertEquals([\local_mail_message::fetch($message1->id())], $messages);
    }

    public function test_create_label() {
        $this->setUser($this->user1->id);

        $result = \local_mail_external::create_label('Label 1', 'blue');

        \external_api::validate_parameters(\local_mail_external::create_label_returns(), $result);
        $label = \local_mail_label::fetch($result);
        $this->assertNotFalse($label);
        $this->assertEquals($this->user1->id, $label->userid());
        $this->assertEquals('Label 1', $label->name());
        $this->assertEquals('blue', $label->color());

        // Empty color.

        $result = \local_mail_external::create_label('Label 2');

        \external_api::validate_parameters(\local_mail_external::create_label_returns(), $result);
        $label = \local_mail_label::fetch($result);
        $this->assertNotFalse($label);
        $this->assertEquals($this->user1->id, $label->userid());
        $this->assertEquals('Label 2', $label->name());
        $this->assertEquals('', $label->color());

        // Empty name.

        try {
            \local_mail_external::create_label('', 'blue');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('erroremptylabelname', 'local_mail'), $exception);
        }

        // Duplicated name.

        try {
            \local_mail_external::create_label('Label 1', 'blue');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('errorrepeatedlabelname', 'local_mail'), $exception);
        }

        // Invalid color.

        try {
            \local_mail_external::create_label('Label 3', 'invalid');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('errorinvalidcolor', 'local_mail'), $exception);
        }
    }

    public function test_update_label() {
        $this->setUser($this->user1->id);
        $label1 = \local_mail_label::create($this->user1->id, 'Label 1', 'blue');
        $label2 = \local_mail_label::create($this->user1->id, 'Label 2', 'red');
        $label3 = \local_mail_label::create($this->user2->id, 'Label 3', 'yellow');

        $result = \local_mail_external::update_label($label1->id(), 'Updated 1', 'green');

        $this->assertNull($result);
        $label1 = \local_mail_label::fetch($label1->id());
        $this->assertEquals($this->user1->id, $label1->userid());
        $this->assertEquals('Updated 1', $label1->name());
        $this->assertEquals('green', $label1->color());

        // Unchaged name.

        $result = \local_mail_external::update_label($label1->id(), 'Updated 1', 'yellow');

        $this->assertNull($result);
        $label1 = \local_mail_label::fetch($label1->id());
        $this->assertEquals($this->user1->id, $label1->userid());
        $this->assertEquals('Updated 1', $label1->name());
        $this->assertEquals('yellow', $label1->color());

        // Empty color.

        $result = \local_mail_external::update_label($label1->id(), 'Label 1');

        $this->assertNull($result);
        $label1 = \local_mail_label::fetch($label1->id());
        $this->assertEquals($this->user1->id, $label1->userid());
        $this->assertEquals('Label 1', $label1->name());
        $this->assertEquals('', $label1->color());

        // Invalid label.

        try {
            \local_mail_external::update_label('-1', 'Label 1', 'blue');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidlabel', 'local_mail'), $exception);
        }

        // Label of another user.

        try {
            \local_mail_external::update_label($label3->id(), 'Label 3', 'yellow');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidlabel', 'local_mail'), $exception);
        }

        // Empty name.

        try {
            \local_mail_external::update_label($label1->id(), '', 'blue');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('erroremptylabelname', 'local_mail'), $exception);
        }

        // Duplicated name.

        try {
            \local_mail_external::update_label($label1->id(), 'Label 2', 'blue');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('errorrepeatedlabelname', 'local_mail'), $exception);
        }

        // Invalid color.

        try {
            \local_mail_external::update_label($label1->id(), 'Label 1', 'invalid');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('errorinvalidcolor', 'local_mail'), $exception);
        }
    }


    public function test_delete_label() {
        $this->setUser($this->user1->id);
        $label1 = \local_mail_label::create($this->user1->id, 'Label 1', 'blue');
        $label2 = \local_mail_label::create($this->user2->id, 'Label 2', 'red');

        $result = \local_mail_external::delete_label($label1->id());

        $this->assertNull($result);
        $label1 = \local_mail_label::fetch($label1->id());
        $this->assertFalse($label1);

        // Invalid label.

        try {
            \local_mail_external::delete_label('-1');
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidlabel', 'local_mail'), $exception);
        }

        // Label of another user.

        try {
            \local_mail_external::delete_label($label2->id());
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidlabel', 'local_mail'), $exception);
        }
    }

    public function test_set_labels() {
        $this->setUser($this->user1->id);
        $label1 = \local_mail_label::create($this->user1->id, 'Label 1');
        $label2 = \local_mail_label::create($this->user1->id, 'Label 2');
        $label3 = \local_mail_label::create($this->user1->id, 'Label 3');
        $label4 = \local_mail_label::create($this->user2->id, 'Label 4');

        // Message from the user.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 1', 'Content 1', FORMAT_HTML);
        $message->add_recipient('to', $this->user2->id);
        $message->send();

        $result = \local_mail_external::set_labels($message->id(), [$label1->id(), $label2->id()]);
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals([$label1, $label2], $message->labels($this->user1->id));

        $result = \local_mail_external::set_labels($message->id(), [$label2->id(), $label3->id()]);
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals([$label2, $label3], $message->labels($this->user1->id));

        // Message sent to the user.

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);
        $message->send();

        $result = \local_mail_external::set_labels($message->id(), [$label1->id(), $label2->id()]);
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals([$label1, $label2], $message->labels($this->user1->id));

        $result = \local_mail_external::set_labels($message->id(), [$label2->id(), $label3->id()]);
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals([$label2, $label3], $message->labels($this->user1->id));

        // Draft from the user.

        $message = \local_mail_message::create($this->user1->id, $this->course1->id);
        $message->save('Subject 1', 'Content 1', FORMAT_HTML);
        $message->add_recipient('to', $this->user2->id);

        $result = \local_mail_external::set_labels($message->id(), [$label1->id(), $label2->id()]);
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals([$label1, $label2], $message->labels($this->user1->id));

        $result = \local_mail_external::set_labels($message->id(), [$label2->id(), $label3->id()]);
        $this->assertNull($result);
        $message = \local_mail_message::fetch($message->id());
        $this->assertEquals([$label2, $label3], $message->labels($this->user1->id));

        // Draft to the user (no permission).

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);

        try {
            $result = \local_mail_external::set_labels($message->id(), [$label1->id(), $label2->id()]);
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }

        // Label of another user.

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);
        $message->send();
        try {
            \local_mail_external::set_labels($message->id(), [$label4->id()]);
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidlabel', 'local_mail'), $exception);
        }

        // Invalid label.

        $message = \local_mail_message::create($this->user2->id, $this->course1->id);
        $message->save('Subject 2', 'Content 2', FORMAT_HTML);
        $message->add_recipient('to', $this->user1->id);
        $message->send();
        try {
            \local_mail_external::set_labels($message->id(), ['-1']);
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidlabel', 'local_mail'), $exception);
        }

        // Invalid message.

        try {
            \local_mail_external::set_labels('-1', ['1']);
            $this->fail();
        } catch (\moodle_exception $exception) {
            $this->assertEquals(new \moodle_exception('invalidmessage', 'local_mail'), $exception);
        }
    }

    private function create_attachment($message, $filename, $content) {
        $fs = get_file_storage();
        $record = [
            'contextid' => \context_course::instance($message->course()->id)->id,
            'component' => 'local_mail',
            'filearea' => 'message',
            'itemid' => $message->id(),
            'filepath' => '/',
            'filename' => $filename,
        ];
        return $fs->create_file_from_string($record, $content);
    }

    private function format_text($message) {
        $context = \context_course::instance($message->course()->id);
        return external_format_text($message->content(), $message->format(), $context->id, 'local_mail', 'message', $message->id());
    }

    private function picture_url($user) {
        global $PAGE;
        $userpicture = new \user_picture($user);
        return $userpicture->get_url($PAGE)->out(false);
    }

    private function profile_url($user) {
        $url = new \moodle_url('/user/profile.php', ['id' => $user->id]);
        return $url->out(false);
    }

    private function index_response($totalcount, array $messages) {
        global $USER;

        $result = [
            'totalcount' => $totalcount,
            'messages' => [],
        ];

        foreach ($messages as $message) {
            $sender = [
                'id' => $message->sender()->id,
                'fullname' => fullname($message->sender()),
                'pictureurl' => $this->picture_url($message->sender()),
                'profileurl' => $this->profile_url($message->sender())
            ];
            $recipients = [];
            foreach (['to', 'cc'] as $type) {
                foreach ($message->recipients($type) as $user) {
                    $userpicture = new \user_picture($user);
                    $userpicture->size = 1;
                    $recipients[] = [
                        'type' => $type,
                        'id' => $user->id,
                        'fullname' => fullname($user),
                        'pictureurl' => $this->picture_url($user),
                        'profileurl' => $this->profile_url($user),
                    ];
                }
            }
            $labels = [];
            foreach ($message->labels($USER->id) as $label) {
                $labels[] = [
                    'id' => $label->id(),
                    'name' => $label->name(),
                    'color' => $label->color(),
                ];
            }
            $result['messages'][] = [
                'id' => $message->id(),
                'subject' => $message->subject(),
                'numattachments' => $message->attachments(true),
                'draft' => $message->draft(),
                'time' => $message->time(),
                'shorttime' => \local_mail_external::format_time($message->time()),
                'fulltime' => \local_mail_external::format_time($message->time(), true),
                'unread' => $message->unread($USER->id),
                'starred' => $message->starred($USER->id),
                'deleted' => $message->deleted($USER->id) != \local_mail_message::NOT_DELETED,
                'course' => [
                    'id' => $message->course()->id,
                    'shortname' => $message->course()->shortname,
                    'fullname' => $message->course()->fullname,
                ],
                'sender' => $sender,
                'recipients' => $recipients,
                'labels' => $labels,
            ];
        }

        return $result;
    }

    private function search_response($totalcount, array $messages, $firstoffset, $lastoffset, $previousid, $nextid) {
        global $USER;

        $result = $this->index_response($totalcount, $messages);

        $result['firstoffset'] = $firstoffset;
        $result['lastoffset'] = $lastoffset;
        $result['previousid'] = $previousid;
        $result['nextid'] = $nextid;

        return $result;
    }

    private function message_response($message, array $files, array $references) {
        global $OUTPUT, $USER;

        list($content, $format) = $this->format_text($message);
        $sender = [
            'id' => $message->sender()->id,
            'fullname' => fullname($message->sender()),
            'pictureurl' => $this->picture_url($message->sender()),
            'profileurl' => $this->profile_url($message->sender()),
        ];
        $recipients = [];
        $types = ['to', 'cc'];
        if ($USER->id == $message->sender()->id) {
            $types[] = 'bcc';
        }
        foreach ($types as $type) {
            foreach ($message->recipients($type) as $user) {
                $userpicture = new \user_picture($user);
                $userpicture->size = 1;
                $recipients[] = [
                    'type' => $type,
                    'id' => $user->id,
                    'fullname' => fullname($user),
                    'pictureurl' => $this->picture_url($user),
                    'profileurl' => $this->profile_url($user)
                ];
            }
        }
        $attachments = [];
        foreach ($files as $file) {
            $fileurl = \moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename());
            $iconurl = $OUTPUT->image_url(file_file_icon($file, 24));
            $attachments[] = [
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
                'filesize' => (int) $file->get_filesize(),
                'mimetype' => $file->get_mimetype(),
                'fileurl' => $fileurl->out(false),
                'iconurl' => $iconurl->out(false),
            ];
        }
        $labels = [];
        foreach ($message->labels($USER->id) as $label) {
            $labels[] = [
                'id' => $label->id(),
                'name' => $label->name(),
                'color' => $label->color(),
            ];
        }
        return [
            'id' => $message->id(),
            'subject' => $message->subject(),
            'content' => $content,
            'format' => $format,
            'numattachments' => $message->attachments(true),
            'draft' => $message->draft(),
            'time' => $message->time(),
            'shorttime' => \local_mail_external::format_time($message->time()),
            'fulltime' => \local_mail_external::format_time($message->time(), true),
            'unread' => $message->unread($USER->id),
            'starred' => $message->starred($USER->id),
            'deleted' => $message->deleted($USER->id) != \local_mail_message::NOT_DELETED,
            'course' => [
                'id' => $message->course()->id,
                'shortname' => $message->course()->shortname,
                'fullname' => $message->course()->fullname,
            ],
            'sender' => $sender,
            'recipients' => $recipients,
            'attachments' => $attachments,
            'references' => $references,
            'labels' => $labels,
        ];
    }

    private function reference_response($message, array $files) {
        global $OUTPUT;

        list($content, $format) = $this->format_text($message);
        $sender = [
            'id' => $message->sender()->id,
            'fullname' => fullname($message->sender()),
            'pictureurl' => $this->picture_url($message->sender()),
            'profileurl' => $this->profile_url($message->sender()),
        ];
        $attachments = [];
        foreach ($files as $file) {
            $fileurl = \moodle_url::make_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename());
            $iconurl = $OUTPUT->image_url(file_file_icon($file, 24));
            $attachments[] = [
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
                'filesize' => (int) $file->get_filesize(),
                'mimetype' => $file->get_mimetype(),
                'fileurl' => $fileurl->out(false),
                'iconurl' => $iconurl->out(false),
            ];
        }
        return [
            'id' => $message->id(),
            'subject' => $message->subject(),
            'content' => $content,
            'format' => $format,
            'time' => $message->time(),
            'shorttime' => \local_mail_external::format_time($message->time()),
            'fulltime' => \local_mail_external::format_time($message->time(), true),
            'sender' => $sender,
            'attachments' => $attachments,
        ];
    }
}
