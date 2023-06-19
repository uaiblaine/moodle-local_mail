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
     * Deletes all messages from a course.
     *
     * @param int $courseid ID of the course.
     */
    public static function delete_messages(int $courseid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('local_mail_message_labels', ['courseid' => $courseid]);

        $DB->delete_records('local_mail_message_users', ['courseid' => $courseid]);

        $select = 'messageid IN (SELECT id FROM {local_mail_messages} WHERE courseid = :courseid)';
        $DB->delete_records_select('local_mail_message_refs', $select, ['courseid' => $courseid]);

        $DB->delete_records('local_mail_messages', ['courseid' => $courseid]);

        $transaction->allow_commit();

        $context = \context_course::instance($courseid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_mail');
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

        $ids = array_unique($ids);
        list($sqlid, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'courseid');
        $select = "id $sqlid AND id <> :siteid";
        $params['siteid'] = SITEID;
        $fields = 'id, shortname, fullname, visible, groupmode';
        $sort = \enrol_get_courses_sortingsql();
        $records = $DB->get_records_select('course', $select, $params, $sort, $fields);

        $courses = [];
        foreach ($records as $record) {
            $courses[$record->id] = new self($record);
        }

        return $courses;
    }
}
