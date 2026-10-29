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
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail;

/**
 * Criteria for searching the users of a course that a given user is allowed to message.
 *
 * @package    local_mail
 * @copyright  2023-2024 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
     * Search the users who can read mail in the course instead of those the user may mail.
     *
     * Reading needs "local/mail:viewmail" or "local/mail:usemail", and the same-role rule, which
     * decides who may be mailed, is not applied. Enrolment and separate groups still apply. Used to
     * name the recipients of a draft in a course the user can only read, a frozen one among them,
     * where nobody may be mailed.
     *
     * @var bool
     */
    public bool $readers = false;

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
            $str .= "\nfullname: " . $this->fullname;
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

        [$sql, $params] = $this->get_base_sql('COUNT(*)');

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Gets users that match the search parameters.
     *
     * @param int $offset Skip this number of users.
     * @param int $limit Maximum number of users, 0 means no limit.
     * @return user[] Found users, indexed by ID.
     */
    public function get(int $offset = 0, int $limit = 0): array {
        global $DB;

        $fields = \core_user\fields::get_picture_fields();

        [$sql, $params] = $this->get_base_sql(implode(',', $fields));

        [$sort, $sortparams] = users_order_by_sql();
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

        // Enrolled.
        $context = $this->course->get_context();
        $ejoin = get_enrolled_join($context, 'u.id', true);
        $joins = $ejoin->joins;
        $wheres = $ejoin->wheres;
        $params = $ejoin->params;

        // Capability.
        $capability = $this->readers ? ['local/mail:viewmail', 'local/mail:usemail'] : 'local/mail:usemail';
        $capjoin = get_with_capability_join($context, $capability, 'u.id');
        $joins .= ' ' . $capjoin->joins;
        $wheres .= ' AND ' . $capjoin->wheres;
        $params = array_merge($params, $capjoin->params);

        // Exclude user.
        $wheres .= ' AND u.id <> :userid';
        $params['userid'] = $this->user->id;

        /*
         * The roles a user holds in the course are the ones assigned in its context and in every
         * parent context (category, system), which the course inherits: the set core's
         * get_user_roles() returns. The same-role rule and the role filter both read that set, so
         * a teacher assigned in the category counts as a teacher of the course.
         */
        $rolecontextids = $context->get_parent_context_ids(true);

        // Exclude users with same role.
        if (!$this->readers && !has_capability('local/mail:mailsamerole', $context, $this->user->id)) {
            [$ctx1sql, $ctx1params] = $DB->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'samerolectx1');
            [$ctx2sql, $ctx2params] = $DB->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'samerolectx2');
            $samerolesql = 'SELECT ra2.userid'
                . ' FROM {role_assignments} ra1'
                . ' JOIN {role_assignments} ra2 ON ra1.roleid = ra2.roleid'
                . ' WHERE ra1.userid = :sameroleurserid'
                . " AND ra1.contextid $ctx1sql"
                . " AND ra2.contextid $ctx2sql";
            $wheres .= " AND u.id NOT IN ($samerolesql)";
            $params['sameroleurserid'] = $this->user->id;
            $params = array_merge($params, $ctx1params, $ctx2params);
        }

        // Separate groups: a sender who cannot see every group reaches the members of their own
        // groups and the users who can see every group. The send path validates recipients through
        // this same query, so what the composer lists and what a send accepts stay the same set.
        $restricted = $this->course->groupmode == SEPARATEGROUPS
            && !has_capability('moodle/site:accessallgroups', $context, $this->user->id);
        if ($restricted) {
            [$allgroupssql, $allgroupsparams] = get_with_capability_sql($context, 'moodle/site:accessallgroups');
            $reachsql = "u.id IN ($allgroupssql)";
            $params = array_merge($params, $allgroupsparams);
            $groupids = array_keys(groups_get_all_groups($this->course->id, $this->user->id));
            if ($groupids) {
                [$ownsql, $ownparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'owngroup');
                $reachsql .= " OR u.id IN (SELECT gm1.userid FROM {groups_members} gm1 WHERE gm1.groupid $ownsql)";
                $params = array_merge($params, $ownparams);
            }
            $wheres .= " AND ($reachsql)";
        }

        // Role.
        if ($this->roleid) {
            [$ctx3sql, $ctx3params] = $DB->get_in_or_equal($rolecontextids, SQL_PARAMS_NAMED, 'rolectx');
            $joins .= ' JOIN {role_assignments} ra3 ON ra3.userid = u.id';
            $wheres .= " AND ra3.contextid $ctx3sql AND ra3.roleid = :roleid";
            $params = array_merge($params, $ctx3params);
            $params['roleid'] = $this->roleid;
        }

        // Group.
        if ($this->groupid) {
            $groupsql = "u.id IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = :groupid)";
            $params['groupid'] = $this->groupid;
            if ($restricted) {
                // Users who see every group are reachable from every group, usually without being
                // members of any, so a restricted sender finds them under each of their groups.
                [$groupallsql, $groupallparams] = get_with_capability_sql($context, 'moodle/site:accessallgroups');
                $groupsql = "($groupsql OR u.id IN ($groupallsql))";
                $params = array_merge($params, $groupallparams);
            }
            $wheres .= " AND $groupsql";
        }

        // Full name.
        if ($this->fullname) {
            $fullnamefield = $DB->sql_fullname('u.firstname', 'u.lastname');
            $wheres .= ' AND ' . $DB->sql_like($fullnamefield, ':fullname', false, false);
            $params['fullname'] = '%' . $DB->sql_like_escape($this->fullname) . '%';
        }

        // IDs.
        if ($this->include) {
            [$includesql, $includeparams] = $DB->get_in_or_equal($this->include, SQL_PARAMS_NAMED, 'id');
            $wheres .= ' AND u.id ' . $includesql;
            $params = array_merge($params, $includeparams);
        }

        $sql = "SELECT $fields FROM {user} WHERE id IN (SELECT DISTINCT u.id FROM {user} u $joins WHERE $wheres)";

        return [$sql, $params];
    }
}
