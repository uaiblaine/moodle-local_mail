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

class user_search {

    /** @var user Search users visible by this user. */
    public user $user;

    /** @var course Search users in this course. */
    public course $course;

    /** @var int If not zero, search users with this role. */
    public int $roleid = 0;

    /** @var int If not zero, search users in this group. */
    public int $groupid = 0;

    /** @var string If not empty, search users with a full name that contains this text. */
    public string $fullname = '';

    /** @var int[] If not empty, search users with one of these IDs. */
    public array $include = [];

    /**
     * Constructs the criteria for searching users.
     *
     * @param user $user Search users visible by this user.
     * @param course $course Search users in this course.
     */
    public function __construct(user $user, course $course) {
        $this->user = $user;
        $this->course = $course;
    }

    /**
     * Convert search parameters to a string.
     *
     * Used for debugging.
     */
    public function __toString(): string {
        $str = 'user: ' . $this->user->id;
        $str .= "\ncourse: " . $this->course->id;
        if ($this->roleid) {
            $str .= "\nrole: " . $this->roleid;
        }
        if ($this->groupid) {
            $str .= "\ngroup: " . $this->groupid;
        }
        if ($this->fullname) {
            $str .= "\fullname: " . $this->fullname;
        }
        return $str;
    }

    /**
     * Counts the number of users that match the search parameters.
     *
     * @return int
     */
    public function count(): int {
        global $DB;

        list($sql, $params) = $this->get_base_sql('COUNT(*)');

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Fetch users that match the search parameters.
     *
     * @param int $offset Start fetching from this offset.
     * @param int $limit Limit number of users to fetch, 0 means no limit.
     * @return user[] Found users, indexed by ID.
     */
    public function fetch(int $offset = 0, int $limit = 0): array {
        global $DB;

        $fields = [];
        foreach (\core_user\fields::get_picture_fields() as $field) {
            $fields[] = 'u.' . $field;
        }

        list($sql, $params) = $this->get_base_sql(implode(',', $fields));

        list($sort, $sortparams) = users_order_by_sql('u');
        $sql .= ' ORDER BY ' . $sort;
        $params = array_merge($params, $sortparams);

        $records = $DB->get_records_sql($sql, $params, $offset, $limit);

        $users = [];
        foreach ($records as $record) {
            $users[$record->id] = new user($record);
        }

        return $users;
    }

    /**
     * Returns the SQL for searching users.
     *
     * @param string $fields Fields to use in SELECT.
     * @return mixed[] Array with SQL and parameters.
     */
    private function get_base_sql(string $fields): array {
        global $DB;

        $from = [];
        $where = [];
        $params = [];

        // Enrolled.
        $context = $this->course->context();
        list($esql, $eparams) = get_enrolled_sql($context, 'local/mail:usemail', $this->groupid, true);
        $from[] = '{user} u';
        $from[] = "($esql) je ON je.id = u.id";
        $params = array_merge($params, $eparams);

        // Exclude user.
        $where[] = 'u.id <> :userid';
        $params['userid'] = $this->user->id;

        // Exclude users with same role.
        if (!has_capability('local/mail:mailsamerole', $context, $this->user->id)) {
            $samerolesql = 'SELECT ra2.userid'
                . ' FROM {role_assignments} ra1'
                . ' JOIN {role_assignments} ra2 ON ra1.roleid = ra2.roleid'
                . ' WHERE ra1.userid = :sameroleurserid'
                . ' AND ra1.contextid = :samerolecontextid1'
                . ' AND ra2.contextid = :samerolecontextid2';
            $where[] = "u.id NOT IN ($samerolesql)";
            $params['sameroleurserid'] = $this->user->id;
            $params['samerolecontextid1'] = $context->id;
            $params['samerolecontextid2'] = $context->id;
        }

        // Role.
        if ($this->roleid) {
            $from[] = '{role_assignments} ra ON ra.userid = u.id';
            $where[] = 'ra.contextid = :contextid AND ra.roleid = :roleid';
            $params['contextid'] = $context->id;
            $params['roleid'] = $this->roleid;
        }

        // Full name.
        if ($this->fullname) {
            $fullnamefield = $DB->sql_fullname('u.firstname', 'u.lastname');
            $where[] = $DB->sql_like($fullnamefield, ':fullname', false, false);;
            $params['fullname'] = '%' . $DB->sql_like_escape($this->fullname) . '%';
        }

        // IDs.
        if ($this->include) {
            list($includesql, $includeparams) = $DB->get_in_or_equal($this->include, SQL_PARAMS_NAMED, 'id');
            $where[] = 'u.id ' . $includesql;
            $params = array_merge($params, $includeparams);
        }

        $sql = 'SELECT ' . $fields
            . ' FROM ' . implode(' JOIN ', $from)
            . ' WHERE ' . implode(' AND ', $where);

        return [$sql, $params];
    }
}
