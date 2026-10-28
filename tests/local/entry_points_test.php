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

use core\hook\output\before_footer_html_generation;
use core_user\output\myprofile\category;
use core_user\output\myprofile\tree;
use local_mail\hook_callbacks;

/**
 * Tests for the "Send mail" entry points on core pages.
 *
 * Test metadata stays in doc-comments while the plugin supports 4.5 (see CLAUDE.md).
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\local\entry_points
 * @covers \local_mail\hook_callbacks
 * @covers ::local_mail_myprofile_navigation
 */
final class entry_points_test extends \local_mail\test\testcase {
    public function test_enabled(): void {
        $points = [entry_points::PROFILE_NODE, entry_points::PROFILE_HEADER, entry_points::PARTICIPANTS];

        // Never saved: every entry point is on.
        unset_config('entrypoints', 'local_mail');
        foreach ($points as $point) {
            self::assertTrue(entry_points::enabled($point), $point);
        }

        set_config('entrypoints', entry_points::PROFILE_HEADER, 'local_mail');
        self::assertFalse(entry_points::enabled(entry_points::PROFILE_NODE));
        self::assertTrue(entry_points::enabled(entry_points::PROFILE_HEADER));
        self::assertFalse(entry_points::enabled(entry_points::PARTICIPANTS));

        set_config('entrypoints', '', 'local_mail');
        foreach ($points as $point) {
            self::assertFalse(entry_points::enabled($point), $point);
        }
    }

    public function test_mail_course_and_can_mail(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        $outsider = $this->getDataGenerator()->create_user();

        self::setUser($u['studenta1']->id);
        self::assertEquals($course->id, entry_points::mail_course($course->id)->id);
        self::assertNull(entry_points::mail_course(SITEID));
        self::assertTrue(entry_points::can_mail($course, $u['tutora']->id));
        self::assertFalse(entry_points::can_mail($course, $u['tutorb']->id));
        self::assertFalse(entry_points::can_mail($course, $u['studenta1']->id));

        self::setUser($outsider);
        self::assertNull(entry_points::mail_course($course->id));
    }

    public function test_profile_node(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        $record = get_course($course->id);
        self::setUser($u['studenta1']->id);

        $build = function ($user, bool $iscurrentuser, $course): array {
            $tree = new tree();
            $tree->add_category(new category('contact', 'Contact'));
            local_mail_myprofile_navigation($tree, $user, $iscurrentuser, $course);
            return $tree->nodes;
        };
        $teacher = \core_user::get_user($u['tutora']->id);

        // The control: a reachable user's course profile carries the node.
        $nodes = $build($teacher, false, $record);
        self::assertArrayHasKey('local_mail_sendmail', $nodes);
        self::assertEquals('local-mail-sendmail', $nodes['local_mail_sendmail']->classes);

        // The global profile, one's own profile, the site course and an unreachable user do not.
        // Core passes no course on the global profile, which must not be read as one.
        set_error_handler(function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        }, E_WARNING);
        try {
            self::assertArrayNotHasKey('local_mail_sendmail', $build($teacher, false, null));
        } finally {
            restore_error_handler();
        }
        self::assertArrayNotHasKey('local_mail_sendmail', $build(\core_user::get_user($u['studenta1']->id), true, $record));
        self::assertArrayNotHasKey('local_mail_sendmail', $build($teacher, false, get_site()));
        self::assertArrayNotHasKey('local_mail_sendmail', $build(\core_user::get_user($u['tutorb']->id), false, $record));

        set_config('entrypoints', entry_points::PROFILE_HEADER, 'local_mail');
        self::assertArrayNotHasKey('local_mail_sendmail', $build($teacher, false, $record));
    }

    public function test_profile_page(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['studenta1']->id);

        // The control: a reachable user's course profile loads the module, with the header button.
        $code = self::footer_code('/user/view.php', ['id' => $u['tutora']->id, 'course' => $course->id], $course->id);
        self::assertStringContainsString('initProfile', $code);
        self::assertStringContainsString('"header":true', $code);

        self::assertStringNotContainsString(
            'initProfile',
            self::footer_code('/user/view.php', ['id' => $u['tutorb']->id, 'course' => $course->id], $course->id),
        );
        self::assertStringNotContainsString(
            'initProfile',
            self::footer_code('/user/profile.php', ['id' => $u['tutora']->id], SITEID),
        );

        set_config('entrypoints', entry_points::PROFILE_NODE, 'local_mail');
        $code = self::footer_code('/user/view.php', ['id' => $u['tutora']->id, 'course' => $course->id], $course->id);
        self::assertStringContainsString('"header":false', $code);

        set_config('entrypoints', '', 'local_mail');
        self::assertStringNotContainsString(
            'initProfile',
            self::footer_code('/user/view.php', ['id' => $u['tutora']->id, 'course' => $course->id], $course->id),
        );
    }

    public function test_participants_page(): void {
        ['course' => $course, 'users' => $u] = self::generate_groups_data();
        self::setUser($u['teacher']->id);

        // The control: a course's participants page loads the module.
        self::assertStringContainsString(
            'initParticipants',
            self::footer_code('/user/index.php', ['id' => $course->id], $course->id),
        );
        self::assertStringNotContainsString(
            'initParticipants',
            self::footer_code('/user/index.php', ['id' => SITEID], SITEID),
        );

        set_config('entrypoints', entry_points::PROFILE_NODE, 'local_mail');
        self::assertStringNotContainsString(
            'initParticipants',
            self::footer_code('/user/index.php', ['id' => $course->id], $course->id),
        );
    }

    public function test_mtube_hook_without_the_seam(): void {
        // A format_mtube release that predates the messaging-provider extension point dispatches
        // the hook without register_messaging_provider(); the listener must leave such a hook alone.
        $hook = new \stdClass();
        hook_callbacks::register_mtube_extensions($hook);

        self::assertEquals(new \stdClass(), $hook);
    }

    /**
     * Runs the footer hook on a fresh page and returns the AMD code it queued.
     *
     * @param string $path Script of the page.
     * @param array $params URL parameters.
     * @param int $courseid Course of the page.
     * @return string
     */
    private static function footer_code(string $path, array $params, int $courseid): string {
        global $PAGE;

        $PAGE = new \moodle_page();
        $PAGE->set_url(new \moodle_url($path, $params));
        $PAGE->set_course(get_course($courseid));

        hook_callbacks::before_footer_html_generation(new before_footer_html_generation($PAGE->get_renderer('core')));

        $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        return implode("\n", $property->getValue($PAGE->requires));
    }
}
