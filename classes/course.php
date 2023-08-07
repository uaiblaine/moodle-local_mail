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

class course {

    /** @var int Course ID. */
    public int $id;

    /** @var string Short name. */
    public string $shortname;

    /** @var string Full name. */
    public string $fullname;

    /** @var bool Visible. */
    public bool $visible;

    /** @var int Group mode. */
    public int $groupmode;

    /** @var int Default grouping ID. */
    public int $defaultgroupingid;

    /**
     * Constructs a course instance from a database record.
     *
     * @param \stdClass $record A database record from table course.
     */
    public function __construct(\stdClass $record) {
        $this->id = (int) $record->id;
        $this->shortname = $record->shortname;
        $this->fullname = $record->fullname;
        $this->visible = $record->visible;
        $this->groupmode = (int) $record->groupmode;
        $this->defaultgroupingid = (int) $record->defaultgroupingid;
    }

    /**
     * Context of the course.
     *
     * @return \context_course
     */
    public function context(): \context_course {
        return \context_course::instance($this->id);
    }

    /**
     * Fetches a course from the database
     *
     * @param int $id ID of the course to fetch.
     * @return ?self The fetched course or null if not found.
     */
    public static function fetch(int $id): ?self {
        $courses = self::fetch_many([$id]);
        return isset($courses[$id]) ? $courses[$id] : null;
    }

    /**
     * Fetches courses in which the given user can use mail.
     *
     * @param user $user User.
     * @return self[] The fetched courses.
     */
    public static function fetch_by_user(user $user): array {
        $courses = [];

        foreach (enrol_get_users_courses($user->id, true) as $record) {
            $context = \context_course::instance($record->id);
            if (has_capability('local/mail:usemail', $context, $user->id, false)) {
                $courses[$record->id] = new self($record);
            }
        }

        return $courses;
    }

    /**
     * Fetches multiple courses from the database.
     *
     * @param int[] $ids IDs of the courses to fetch.
     * @return self[] The fetched courses, indexed by ID.
     */
    public static function fetch_many(array $ids): array {
        global $DB;

        if (!$ids) {
            return [];
        }

        list($sqlid, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'courseid');
        $select = "id $sqlid AND id <> :siteid";
        $params['siteid'] = SITEID;
        $fields = 'id, shortname, fullname, visible, groupmode, defaultgroupingid';
        $sort = \enrol_get_courses_sortingsql();
        $records = $DB->get_records_select('course', $select, $params, $sort, $fields);

        $courses = [];
        foreach ($records as $record) {
            $courses[$record->id] = new self($record);
        }

        return $courses;
    }

    /**
     * Returns the course groups visible by the user.
     *
     * @param user $user User.
     * @return string[] Array of group names, indexed by ID.
     */
    public function get_viewable_groups(user $user): array {
        if ($this->groupmode == NOGROUPS) {
            return [];
        }

        $userid = $this->groupmode == VISIBLEGROUPS ? 0 : $user->id;
        $groups = groups_get_all_groups($this->id, $userid, $this->defaultgroupingid);

        $result = [];
        foreach ($groups as $group) {
            $result[$group->id] = $group->name;
        }

        return $result;
    }

    /**
     * Returns the course roles with mail capability visible by the given user.
     *
     * @param user $user User.
     * @return string[] Array of role names, indexed by ID.
     */
    public function get_viewable_roles(user $user): array {
        $result = [];
        list($needed, $forbidden) = get_roles_with_cap_in_context($this->context(), 'local/mail:usemail');
        foreach (get_viewable_roles($this->context(), $user->id) as $roleid => $rolename) {
            if (isset($needed[$roleid]) && !isset($forbidden[$roleid])) {
                $result[$roleid] = $rolename;
            }
        }
        return $result;
    }

    /**
     * URL of the course.
     *
     * @return string
     */
    public function url(): string {
        $url = new \moodle_url('/course/view.php', ['id' => $this->id]);
        return $url->out(false);
    }
}
