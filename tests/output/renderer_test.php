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

use moodle_exception;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/../testcase.php');

/**
 * @covers \local_mail_renderer
 */
class renderer_test extends testcase {

    public function test_file_url() {
        global $CFG, $PAGE;

        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());
        self::setUser($user->id);
        $course = new course($generator->create_course());
        $data = message_data::new($course, $user);
        $file = self::create_draft_file($data->draftitemid, 'file.txt', 'File content');
        $context = \context_user::instance($user->id);

        $renderer = $PAGE->get_renderer('local_mail');

        $expected = "$CFG->wwwroot/pluginfile.php/$context->id/user/draft/$data->draftitemid/file.txt";
        self::assertEquals($expected, $renderer->file_url($file));
    }

    public function test_file_icon_url() {
        global $CFG, $PAGE;

        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());
        self::setUser($user->id);
        $course = new course($generator->create_course());
        $data = message_data::new($course, $user);
        $file1 = self::create_draft_file($data->draftitemid, 'file1.txt', 'File content');
        $file2 = self::create_draft_file($data->draftitemid, 'file2.html', 'File content');

        $renderer = $PAGE->get_renderer('local_mail');

        self::assertEquals("$CFG->wwwroot/theme/image.php/_s/boost/core/1/f/text-24", $renderer->file_icon_url($file1));
        self::assertEquals("$CFG->wwwroot/theme/image.php/_s/boost/core/1/f/html-24", $renderer->file_icon_url($file2));
    }

    public function test_formatted_time() {
        global $PAGE;

        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());
        self::setUser($user->id);

        $tz = \core_date::get_user_timezone();

        $renderer = $PAGE->get_renderer('local_mail');

        $now = new \DateTime('2021-10-11 12:13:14', new \DateTimeZone($tz));

        // Today.
        $date = new \DateTime('2021-10-11 01:02:03', new \DateTimeZone($tz));
        $shorttime = userdate($date->getTimestamp(), get_string('strftimetime', 'langconfig'));
        $fulltime = userdate($date->getTimestamp(), get_string('strftimedatetime', 'langconfig'));
        self::assertEquals($shorttime, $renderer->formatted_time($date->getTimestamp(), false, $now->getTimestamp()));
        self::assertEquals($fulltime, $renderer->formatted_time($date->getTimestamp(), true, $now->getTimestamp()));

        // Yesterday.
        $date = new \DateTime('2021-10-10 23:59:58', new \DateTimeZone($tz));
        $shorttime = userdate($date->getTimestamp(), get_string('strftimedateshortmonthabbr', 'langconfig'));
        $fulltime = userdate($date->getTimestamp(), get_string('strftimedatetime', 'langconfig'));
        self::assertEquals($shorttime, $renderer->formatted_time($date->getTimestamp(), false, $now->getTimestamp()));
        self::assertEquals($fulltime, $renderer->formatted_time($date->getTimestamp(), true, $now->getTimestamp()));

        // Previous year.
        $date = new \DateTime('2020-12-31 23:59:58', new \DateTimeZone($tz));
        $shorttime = userdate($date->getTimestamp(), get_string('strftimedatefullshort', 'langconfig'));
        $fulltime = userdate($date->getTimestamp(), get_string('strftimedatetime', 'langconfig'));
        self::assertEquals($shorttime, $renderer->formatted_time($date->getTimestamp(), false, $now->getTimestamp()));
        self::assertEquals($fulltime, $renderer->formatted_time($date->getTimestamp(), true, $now->getTimestamp()));

        // Future.
        $date = new \DateTime('2021-10-11 12:13:15', new \DateTimeZone($tz));
        $shorttime = userdate($date->getTimestamp(), get_string('strftimedatefullshort', 'langconfig'));
        $fulltime = userdate($date->getTimestamp(), get_string('strftimedatetime', 'langconfig'));
        self::assertEquals($shorttime, $renderer->formatted_time($date->getTimestamp(), false, $now->getTimestamp()));
        self::assertEquals($fulltime, $renderer->formatted_time($date->getTimestamp(), true, $now->getTimestamp()));
    }

    public function test_notification() {
        global $PAGE, $SITE;

        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $user4 = new user($generator->create_user());
        $user5 = new user($generator->create_user());
        $course = new course($generator->create_course());
        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->cc = [$user3, $user4];
        $data->bcc = [$user5];
        $data->subject = 'Subject';
        $data->content = '<p>Content</p>';
        self::create_draft_file($data->draftitemid, 'file1.txt', 'File content');
        self::create_draft_file($data->draftitemid, 'file2.html', 'File content');
        $message = message::create($data);
        $message->send(time());
        $url = new \moodle_url('local/mail/view.php', ['t' => 'inbox', 'm' => $message->id]);

        $renderer = $PAGE->get_renderer('local_mail');
        $notification = $renderer->notification($message, $user2);

        self::assertEquals($course->id, $notification->courseid);
        self::assertEquals('local_mail', $notification->component);
        self::assertEquals('mail', $notification->name);
        self::assertEquals($user1->id, $notification->userfrom);
        self::assertEquals($user2->id, $notification->userto);
        self::assertEquals(get_string('notificationsubject', 'local_mail', $SITE->shortname), $notification->subject);
        self::assertStringContainsString($url->out(false), $notification->fullmessage);
        self::assertStringContainsString($course->fullname, $notification->fullmessage);
        self::assertStringContainsString($user1->fullname(), $notification->fullmessage);
        self::assertStringContainsString($renderer->formatted_time($message->time), $notification->fullmessage);
        self::assertStringContainsString('Subject', $notification->fullmessage);
        self::assertStringContainsString('Content', $notification->fullmessage);
        self::assertStringNotContainsString('<p>', $notification->fullmessage);
        self::assertStringContainsString('file1.txt', $notification->fullmessagehtml);
        self::assertStringContainsString('file2.html', $notification->fullmessagehtml);
        self::assertEquals(FORMAT_PLAIN, $notification->fullmessageformat);
        self::assertStringContainsString($url->out(true), $notification->fullmessagehtml);
        self::assertStringContainsString($course->fullname, $notification->fullmessagehtml);
        self::assertStringContainsString($user1->fullname(), $notification->fullmessagehtml);
        self::assertStringContainsString($renderer->formatted_time($message->time), $notification->fullmessagehtml);
        self::assertStringContainsString('Subject', $notification->fullmessagehtml);
        self::assertStringContainsString('<p>Content</p>', $notification->fullmessagehtml);
        self::assertStringContainsString('file1.txt', $notification->fullmessagehtml);
        self::assertStringContainsString('file2.html', $notification->fullmessagehtml);
        self::assertEquals(1, $notification->notification);
        $a = ['user' => $user1->fullname(), 'course' => $course->fullname];
        self::assertEquals(get_string('notificationsmallmessage', 'local_mail', $a), $notification->smallmessage);
        $contexturl = new \moodle_url('/local/mail/view.php', array('t' => 'inbox', 'm' => $message->id));
        self::assertEquals($contexturl->out(false), $notification->contexturl);
        self::assertEquals('Subject', $notification->contexturlname);
    }

    public function test_svelte_script() {
        global $CFG, $PAGE, $OUTPUT;

        $renderer = $PAGE->get_renderer('local_mail');

        // Head not written.

        $html = $renderer->svelte_script('src/view.ts');
        $head = $OUTPUT->standard_head_html();

        $url = preg_quote($CFG->wwwroot . '/local/mail/svelte/build/', '/') . 'view-[0-9a-f]+\\.js';
        $pattern = '/^<script type="module" src="' . $url . '"><\/script>$/';
        self::assertMatchesRegularExpression($pattern, $html);

        $url = preg_quote($CFG->wwwroot . '/local/mail/svelte/build/', '/') . 'view-[0-9a-f]+\\.css';
        $pattern = '/<link rel="stylesheet" type="text\/css" href="' . $url . '" \/>/';
        self::assertMatchesRegularExpression($pattern, $head);

        // Head already written.

        $html = $renderer->svelte_script('src/view.ts');

        $head = $OUTPUT->standard_head_html();
        $jsurl = preg_quote($CFG->wwwroot . '/local/mail/svelte/build/', '/') . 'view-[0-9a-f]+\\.js';
        $cssurl = preg_quote($CFG->wwwroot . '/local/mail/svelte/build/', '/') . 'view-[0-9a-f]+\\.css';
        $pattern = '/^<script>.*"' . $cssurl . '".*<\/script>\s*<script type="module" src="' . $jsurl . '"><\/script>$/s';
        self::assertMatchesRegularExpression($pattern, $html);

        // Invalid script name.

        try {
            $renderer->svelte_script('src/inexistent.ts');
            $this->fail();
        } catch (moodle_exception $e) {
            $this->assertEquals('codingerror', $e->errorcode);
        }

        // Developement server.

        set_config('local_mail_devserver', 'http://localhost:5173');
        $url = preg_quote('http://localhost:5173/src/view.ts', '/');
        $pattern = '/^<script type="module" src="' . $url . '"><\/script>$/';
        $result = $renderer->svelte_script('src/view.ts');
        self::assertMatchesRegularExpression($pattern, $result);
    }
}
