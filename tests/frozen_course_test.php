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
 * Tests for mail in a course its users can read but not write.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mail;

/**
 * Mail in a frozen course, and in one where a role keeps viewmail without usemail.
 *
 * Context freezing denies every write capability, so usemail goes and viewmail, a read
 * capability, stays: the course is listed and its messages open, mark and delete, while
 * nothing composes there. Each frozen case has its unfrozen control.
 *
 * Test metadata stays in doc-comments while the plugin supports 4.5: that branch's phpcs
 * cannot read attributes and warns on every method.
 *
 * @covers \local_mail\course
 * @covers \local_mail\user
 * @covers \local_mail\external
 * @covers \local_mail\local\entry_points
 */
final class frozen_course_test extends test\testcase {
    /** @var course Course of the fixture. */
    private course $course;

    /** @var user Teacher, sender of the message. */
    private user $teacher;

    /** @var user Student, recipient of the message and author of the draft. */
    private user $student;

    /** @var message Message sent by the teacher to the student. */
    private message $message;

    /** @var message Draft of the student to the teacher. */
    private message $draft;

    /**
     * Creates a course with a sent message and a draft, and turns context freezing on.
     */
    public function setUp(): void {
        parent::setUp();

        $generator = self::getDataGenerator();
        $this->course = new course($generator->create_course());
        $this->teacher = new user($generator->create_user());
        $this->student = new user($generator->create_user());
        $generator->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $time = make_timestamp(2026, 10, 28, 12, 0);

        $data = message_data::new($this->course, $this->teacher);
        $data->subject = 'Subject';
        $data->to = [$this->student];
        $data->time = $time;
        $this->message = message::create($data);
        $this->message->send($time);

        $data = message_data::new($this->course, $this->student);
        $data->subject = 'Draft';
        $data->to = [$this->teacher];
        $data->time = $time;
        $this->draft = message::create($data);

        set_config('contextlocking', 1);
    }

    /**
     * Freezes or unfreezes the course of the fixture.
     *
     * @param bool $locked Whether the course is frozen.
     */
    private function freeze(bool $locked): void {
        $this->course->get_context()->set_locked($locked);
        course::cache()->purge();
    }

    /**
     * Asserts that a call throws the mail exception with the given code.
     *
     * @param string $errorcode Expected error code.
     * @param callable $call Call to make.
     */
    private static function assert_mail_error(string $errorcode, callable $call): void {
        try {
            $call();
            self::fail("Expected exception '$errorcode'");
        } catch (exception $e) {
            self::assertEquals($errorcode, $e->errorcode);
        }
    }

    public function test_access_checks(): void {
        // Control: the course is writable before it is frozen.
        self::assertTrue($this->student->can_read_mail($this->course));
        self::assertTrue($this->student->can_use_mail($this->course));
        self::assertTrue($this->student->can_edit_message($this->draft));

        $this->freeze(true);

        self::assertArrayHasKey($this->course->id, course::get_by_user($this->student));
        self::assertArrayNotHasKey($this->course->id, course::get_writable_by_user($this->student));
        self::assertTrue($this->student->can_read_mail($this->course));
        self::assertFalse($this->student->can_use_mail($this->course));
        self::assertTrue($this->student->can_view_message($this->message));
        self::assertTrue($this->student->can_view_files($this->message));
        self::assertTrue($this->student->can_view_message($this->draft));
        self::assertFalse($this->student->can_edit_message($this->draft));
        self::assertFalse($this->student->can_view_group($this->course, 0));
        self::assertEquals([], local\sender::reachable_ids($this->teacher, $this->course, [$this->student->id]));

        $this->freeze(false);

        self::assertTrue($this->student->can_use_mail($this->course));
        self::assertTrue($this->student->can_edit_message($this->draft));
    }

    public function test_external_reads_in_frozen_course(): void {
        global $DB;

        $this->freeze(true);
        self::setUser($this->student->id);

        $courses = external::get_courses();
        external::validate_parameters(external::get_courses_returns(), $courses);
        self::assertCount(1, $courses);
        self::assertEquals($this->course->id, $courses[0]['id']);
        self::assertFalse($courses[0]['canwrite']);
        self::assertEquals(1, $courses[0]['unread']);
        self::assertEquals(1, $courses[0]['drafts']);

        self::assertEquals(1, external::count_messages(['courseid' => $this->course->id, 'roles' => ['to']]));
        self::assertEquals(1, external::count_messages(['roles' => ['to']]));
        $found = external::search_messages(['courseid' => $this->course->id, 'roles' => ['to']]);
        self::assertEquals([$this->message->id], array_column($found, 'id'));
        self::assertEquals($this->message->id, external::get_message($this->message->id)['id']);

        // Organising includes discarding one's own draft, which nobody else can read.
        external::set_deleted($this->draft->id, message::DELETED_FOREVER);
        self::assertFalse($DB->record_exists('local_mail_messages', ['id' => $this->draft->id]));

        external::set_unread($this->message->id, false);
        external::set_starred($this->message->id, true);
        $message = message::get($this->message->id);
        self::assertFalse($message->unread($this->student));
        self::assertTrue($message->starred($this->student));

        external::set_deleted($this->message->id, message::DELETED);
        self::assertEquals(message::DELETED, message::get($this->message->id)->deleted($this->student));
        external::empty_trash($this->course->id);
        self::assertEquals(message::DELETED_FOREVER, message::get($this->message->id)->deleted($this->student));

        // Control: the same course is listed as writable once unfrozen.
        $this->freeze(false);
        self::assertTrue(external::get_courses()[0]['canwrite']);
    }

    public function test_external_writes_in_frozen_course(): void {
        $this->freeze(true);
        self::setUser($this->student->id);

        $courseid = $this->course->id;
        $messageid = $this->message->id;
        $draftid = $this->draft->id;
        self::assert_mail_error('errorcoursenotfound', fn() => external::create_message($courseid));
        self::assert_mail_error('errorcoursenotfound', fn() => external::reply_message($messageid, false));
        self::assert_mail_error('errorcoursenotfound', fn() => external::forward_message($messageid));
        self::assert_mail_error('errormessagenotfound', fn() => external::get_message_form($draftid));
        self::assert_mail_error('errormessagenotfound', fn() => external::send_message($draftid));
        self::assert_mail_error('errorcoursenotfound', fn() => external::get_roles($courseid));
        self::assert_mail_error('errorcoursenotfound', fn() => external::get_groups($courseid));
        self::assert_mail_error('errorcoursenotfound', fn() => external::search_users(['courseid' => $courseid]));
        self::assertTrue(message::get($draftid)->draft);

        // Control: the same calls succeed once the course is unfrozen.
        $this->freeze(false);
        self::assertIsInt(external::create_message($courseid));
        self::assertIsInt(external::reply_message($messageid, false));
        self::assertIsInt(external::forward_message($messageid));
    }

    public function test_entry_points_in_frozen_course(): void {
        self::setUser($this->teacher->id);
        self::assertEquals($this->course->id, local\entry_points::mail_course($this->course->id)?->id);

        $this->freeze(true);

        self::assertNull(local\entry_points::mail_course($this->course->id));
        self::assertFalse(local\entry_points::can_mail($this->course, $this->student->id));
    }

    public function test_viewmail_without_usemail(): void {
        global $DB;

        $context = $this->course->get_context();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);

        // A role that keeps viewmail and loses usemail reads without writing.
        assign_capability('local/mail:usemail', CAP_PROHIBIT, $roleid, $context->id);
        course::cache()->purge();
        self::assertTrue($this->student->can_read_mail($this->course));
        self::assertFalse($this->student->can_use_mail($this->course));

        // Usemail counts as read access too: losing viewmail alone changes nothing.
        unassign_capability('local/mail:usemail', $roleid, $context->id);
        assign_capability('local/mail:viewmail', CAP_PROHIBIT, $roleid, $context->id);
        course::cache()->purge();
        self::assertTrue($this->student->can_read_mail($this->course));
        self::assertTrue($this->student->can_use_mail($this->course));

        // Neither: the course is not listed at all.
        assign_capability('local/mail:usemail', CAP_PROHIBIT, $roleid, $context->id);
        course::cache()->purge();
        self::assertFalse($this->student->can_read_mail($this->course));
        self::assertArrayNotHasKey($this->course->id, course::get_by_user($this->student));
    }

    public function test_viewmail_cloned_from_usemail_on_upgrade(): void {
        global $DB;

        $context = $this->course->get_context();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('local/mail:usemail', CAP_PROHIBIT, $roleid, $context->id);

        // Simulates the upgrade that adds viewmail to a site where usemail already exists.
        $DB->delete_records('role_capabilities', ['capability' => 'local/mail:viewmail']);
        $DB->delete_records('capabilities', ['name' => 'local/mail:viewmail']);
        update_capabilities('local_mail');

        $fields = $DB->sql_concat('roleid', "'/'", 'contextid') . ' AS rolecontext, permission';
        $usemail = $DB->get_records_menu('role_capabilities', ['capability' => 'local/mail:usemail'], '', $fields);
        $viewmail = $DB->get_records_menu('role_capabilities', ['capability' => 'local/mail:viewmail'], '', $fields);
        self::assertNotEmpty($usemail);
        self::assertEquals($usemail, $viewmail);
        self::assertEquals(CAP_PROHIBIT, $viewmail["$roleid/$context->id"]);
    }

    public function test_frozen_draft_names_its_recipients(): void {
        $generator = self::getDataGenerator();
        $classmate = new user($generator->create_user());
        $leaver = new user($generator->create_user());
        $generator->enrol_user($classmate->id, $this->course->id, 'student');
        $generator->enrol_user($leaver->id, $this->course->id, 'student');
        self::setUser($this->student->id);
        $data = message_data::new($this->course, $this->student);
        $data->subject = 'Group draft';
        $data->to = [$this->teacher, $classmate, $leaver];
        $draft = message::create($data);
        $enrol = enrol_get_plugin('manual');
        $instance = enrol_get_instances($this->course->id, true);
        $enrol->unenrol_user(reset($instance), $leaver->id);

        $this->freeze(true);

        $result = external::get_message($draft->id);
        external::validate_parameters(external::get_message_returns(), $result);
        $recipients = array_column($result['recipients'], null, 'id');

        // Nobody may be mailed in a frozen course, yet the recipients still in it are named:
        // a teacher, and a classmate whose role the same-role rule would weigh.
        self::assertEquals($this->teacher->fullname(), $recipients[$this->teacher->id]['fullname']);
        self::assertFalse($recipients[$this->teacher->id]['isvalid']);
        self::assertEquals($classmate->fullname(), $recipients[$classmate->id]['fullname']);
        self::assertFalse($recipients[$classmate->id]['isvalid']);

        // One who left the course stays withheld.
        self::assertEquals(get_string('unavailablerecipient', 'local_mail'), $recipients[$leaver->id]['fullname']);
        self::assertSame('', $recipients[$leaver->id]['pictureurl']);
    }

    public function test_writable_draft_keeps_withholding_unreachable_recipients(): void {
        global $DB;

        $generator = self::getDataGenerator();
        $classmate = new user($generator->create_user());
        $generator->enrol_user($classmate->id, $this->course->id, 'student');
        self::setUser($this->student->id);
        $data = message_data::new($this->course, $this->student);
        $data->subject = 'Classmate draft';
        $data->to = [$classmate];
        $draft = message::create($data);

        // The course stays writable; students lose the right to mail their own role.
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('local/mail:mailsamerole', CAP_PROHIBIT, $roleid, $this->course->get_context()->id);
        course::cache()->purge();
        self::assertTrue($this->student->can_use_mail($this->course));

        $result = external::get_message($draft->id);
        $recipients = array_column($result['recipients'], null, 'id');

        // Writable course: the may-mail rule still decides, so the classmate is withheld.
        self::assertEquals(get_string('unavailablerecipient', 'local_mail'), $recipients[$classmate->id]['fullname']);

        // Control: frozen, the same classmate is named.
        $this->freeze(true);
        $recipients = array_column(external::get_message($draft->id)['recipients'], null, 'id');
        self::assertEquals($classmate->fullname(), $recipients[$classmate->id]['fullname']);
    }
}
