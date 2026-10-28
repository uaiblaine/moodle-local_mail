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
 * SPDX-FileCopyrightText: 2026 Anderson Blaine
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail\local;

/**
 * Tests for Mail as the MooTube course format's messaging channel.
 *
 * Skipped where format_mtube with its messaging-provider extension point is not installed: the
 * provider implements that extension point's interface. Test metadata stays in doc-comments
 * while the plugin supports 4.5 (see CLAUDE.md); the coverage target below leaves out the
 * provider class, which cannot be loaded without format_mtube.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\hook_callbacks
 */
final class mtube_provider_test extends \local_mail\test\testcase {
    public function setUp(): void {
        parent::setUp();
        // A format_mtube release that predates the extension point has the hook but not the interface.
        if (!interface_exists(\format_mtube\local\extensions\messaging_provider::class)) {
            $this->markTestSkipped('format_mtube with the messaging provider seam is not installed.');
        }
    }

    public function test_registered_through_the_mtube_hook(): void {
        \format_mtube\local\extensions\manager::reset();

        $provider = \format_mtube\local\extensions\manager::instance()->get_messaging_provider();

        self::assertInstanceOf(mtube_provider::class, $provider);
        self::assertSame('local_mail', $provider->get_id());
        self::assertSame('local_mail/mtube_contacts', $provider->get_amd_module());
    }

    public function test_is_available(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        $record = get_course($course->id);
        $context = \context_course::instance($course->id);
        $provider = new mtube_provider();

        self::setUser($u['studenta1']->id);
        self::assertTrue($provider->is_available($record, $context));

        self::setUser($this->getDataGenerator()->create_user());
        self::assertFalse($provider->is_available($record, $context));
    }

    public function test_filter_reachable(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        $record = get_course($course->id);
        $context = \context_course::instance($course->id);
        $provider = new mtube_provider();
        self::setUser($u['studenta1']->id);

        $candidates = array_column([$u['tutorb'], $u['teacher'], $u['tutora'], $u['studentb'], $u['studenta1']], 'id');

        // Separate groups hold, the sender is never a recipient, and the given order is kept.
        self::assertSame(
            [(int) $u['teacher']->id, (int) $u['tutora']->id],
            $provider->filter_reachable($record, $context, $candidates),
        );
        self::assertSame([], $provider->filter_reachable($record, $context, []));

        // Someone who cannot use mail in the course reaches nobody.
        self::setUser($this->getDataGenerator()->create_user());
        self::assertSame([], $provider->filter_reachable($record, $context, $candidates));
    }

    public function test_course_page_offers_reachable_contacts(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'mtube', 'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $group = $generator->create_group(['courseid' => $course->id]);
        $other = $generator->create_group(['courseid' => $course->id]);
        $teacherroleid = (int) $this->get_role_id('teacher');
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacherroleid, \context_course::instance($course->id)->id);

        $teacher = $generator->create_user();
        $tutor = $generator->create_user();
        $student = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($tutor->id, $course->id, 'teacher');
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->create_group_member(['userid' => $tutor->id, 'groupid' => $other->id]);
        $generator->create_group_member(['userid' => $student->id, 'groupid' => $group->id]);

        course_get_format($course)->update_course_format_options(['formatoptionstext' => json_encode([
            'showcoursecontacts' => 1,
            'coursecontacts' => [
                ['userid' => (int) $tutor->id, 'description' => ''],
                ['userid' => (int) $teacher->id, 'description' => ''],
            ],
            'coursecontactsmessaging' => 'contactandteam',
        ])]);
        \format_mtube\local\extensions\manager::reset();
        self::setUser($student);

        $call = \format_mtube\local\course_contacts_messaging::get_page_config($course);

        self::assertSame('local_mail/mtube_contacts', $call['module']);
        // The tutor of the other group gets no envelope; the team is the reachable contacts.
        self::assertSame([(int) $teacher->id], $call['config']['contacts']);
        self::assertSame([(int) $teacher->id], $call['config']['team']);
    }

    /**
     * Id of a standard role.
     *
     * @param string $shortname
     * @return int
     */
    private function get_role_id(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
