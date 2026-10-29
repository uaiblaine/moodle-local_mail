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

use local_mail\course;
use local_mail\settings;
use local_mail\user;

/**
 * Decides where a "Send mail" entry point appears on core pages.
 *
 * Mail is course-scoped, so an entry point exists only inside a course in which the viewer can use
 * mail, and only towards a user the composer would list to the viewer there. The global profile
 * (`/user/profile.php`) and the site course get none.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_points {
    /** @var string Node in the contact section of a user's course profile. */
    public const PROFILE_NODE = 'profilenode';

    /** @var string Button in the header of a user's course profile. */
    public const PROFILE_HEADER = 'profileheader';

    /** @var string Option of the "With selected users..." menu of the participants page. */
    public const PARTICIPANTS = 'participants';

    /**
     * Returns whether the administrator left an entry point on (setting "local_mail/entrypoints").
     *
     * @param string $point One of the constants of this class.
     * @return bool
     */
    public static function enabled(string $point): bool {
        $config = get_config('local_mail', 'entrypoints');

        // Not saved yet (an upgrade still to run): the setting's default, every entry point on.
        if ($config === false) {
            return true;
        }

        return in_array($point, explode(',', $config), true);
    }

    /**
     * Returns the course if the current user can write mail in it.
     *
     * Every entry point composes, so a course whose mail the user can only read (a frozen
     * one) offers none.
     *
     * @param int $courseid ID of the course.
     * @return course|null Null for the site course, a missing course or a course without mail.
     */
    public static function mail_course(int $courseid): ?course {
        $user = user::current();
        if (!$user || !settings::is_installed()) {
            return null;
        }

        // The courses of a user's enrolments, which never include the site course.
        return course::get_writable_by_user($user)[$courseid] ?? null;
    }

    /**
     * Returns whether the current user may mail a user in a course, by the composer's rules.
     *
     * @param course $course Course.
     * @param int $userid ID of the user to mail.
     * @return bool False for the current user themself.
     */
    public static function can_mail(course $course, int $userid): bool {
        $user = user::current();
        if (!$user) {
            return false;
        }

        // The send's own test, which also requires the viewer to use mail in the course.
        return sender::reachable_ids($user, $course, [$userid]) === [$userid];
    }

    /**
     * Returns the URL of the full editor with a draft to one user, for pages without JavaScript.
     *
     * The link carries the viewer's sesskey and create.php writes a draft on a GET, the pattern
     * core uses for its own action links: a page without JavaScript needs a plain link, and the
     * key, checked by require_sesskey(), is what keeps another site from creating drafts in the
     * viewer's name. The key goes only to this same-origin URL. create.php drops any recipient
     * the viewer cannot mail.
     *
     * @param course $course Course.
     * @param int $userid ID of the recipient.
     * @return \moodle_url
     */
    public static function compose_url(course $course, int $userid): \moodle_url {
        return new \moodle_url('/local/mail/create.php', [
            'course' => $course->id,
            'recipients' => $userid,
            'role' => 'to',
            'sesskey' => sesskey(),
        ]);
    }
}
