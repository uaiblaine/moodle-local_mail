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

use local_mail\message;
use local_mail\settings;

/**
 * Selects the rows a retention policy applies to.
 *
 * This class only ever reads. It exists so that the queries deciding what gets deleted
 * can be run and inspected on real data through cli/retention.php before anything acts
 * on them, and so that the report an administrator sees and the sweep that does the
 * work cannot drift apart by being written twice.
 *
 * Everything here queries local_mail_message_users directly rather than through
 * message_search, which cannot serve this: its constructor takes a user and its SQL
 * hardcodes that user's id and the courses they are currently enrolled in. A sweep
 * built on it would never see rows belonging to somebody who was unenrolled, and those
 * are exactly the rows worth reclaiming.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retention {
    /** Stage: generated mail old enough to be moved to the trash. */
    const STAGE_TRASH_UPDATES = 'trashupdates';

    /** Stage: generated mail that has been in the trash long enough to be removed. */
    const STAGE_EXPIRE_UPDATES = 'expireupdates';

    /** Stage: any other mail that has been in the trash long enough to be removed. */
    const STAGE_EXPIRE_TRASH = 'expiretrash';

    /** @var settings Stored settings of the plugin. */
    private settings $settings;

    /** @var int Timestamp the thresholds are measured against. */
    private int $now;

    /**
     * Constructs the selection for the given settings.
     *
     * @param ?settings $settings Settings to apply, or null to read the stored ones.
     * @param ?int $now Timestamp to measure the thresholds against, or null for the current time.
     */
    public function __construct(?settings $settings = null, ?int $now = null) {
        $this->settings = $settings ?? settings::get();
        $this->now = $now ?? time();
    }

    /**
     * Returns the identifiers of every stage, in the order a sweep has to apply them.
     *
     * @return string[] Array of STAGE_* constants.
     */
    public static function stages(): array {
        return [self::STAGE_TRASH_UPDATES, self::STAGE_EXPIRE_UPDATES, self::STAGE_EXPIRE_TRASH];
    }

    /**
     * Returns whether a stage is configured to do anything.
     *
     * A threshold of zero means "keep indefinitely", and the whole policy is off unless
     * it has been switched on, so both are checked here rather than at each call site.
     *
     * @param string $stage One of the STAGE_* constants.
     * @return bool
     */
    public function stage_enabled(string $stage): bool {
        if (!$this->settings->retentionenabled) {
            return false;
        }

        return $this->days($stage) > 0;
    }

    /**
     * Returns the threshold of a stage, in days.
     *
     * @param string $stage One of the STAGE_* constants.
     * @return int Number of days, or 0 if the stage keeps mail indefinitely.
     */
    public function days(string $stage): int {
        switch ($stage) {
            case self::STAGE_TRASH_UPDATES:
                return $this->settings->retentionupdatesdays;
            case self::STAGE_EXPIRE_UPDATES:
                return $this->settings->retentionupdatestrashdays;
            case self::STAGE_EXPIRE_TRASH:
                return $this->settings->retentiontrashdays;
            default:
                throw new \coding_exception('unknown retention stage: ' . $stage);
        }
    }

    /**
     * Returns the cutoff timestamp of a stage.
     *
     * @param string $stage One of the STAGE_* constants.
     * @return int Timestamp before which a row qualifies.
     */
    public function cutoff(string $stage): int {
        return $this->now - $this->days($stage) * DAYSECS;
    }

    /**
     * Returns how many rows a stage would act on.
     *
     * @param string $stage One of the STAGE_* constants.
     * @return int Number of per-user rows.
     */
    public function count(string $stage): int {
        global $DB;

        if (!$this->stage_enabled($stage)) {
            return 0;
        }

        [$sql, $params] = $this->select($stage);

        return $DB->count_records_sql('SELECT COUNT(1) ' . $sql, $params);
    }

    /**
     * Returns a batch of the rows a stage would act on, ordered by id.
     *
     * Ordered and bounded by id rather than paged by offset, so that a sweep acting on
     * each batch as it goes does not skip rows when the ones before them stop matching.
     *
     * @param string $stage One of the STAGE_* constants.
     * @param int $afterid Return rows after this id only.
     * @param int $limit Maximum number of rows to return.
     * @return \stdClass[] Records with id, messageid, userid, courseid and deleted.
     */
    public function batch(string $stage, int $afterid = 0, int $limit = 100): array {
        global $DB;

        if (!$this->stage_enabled($stage)) {
            return [];
        }

        [$sql, $params] = $this->select($stage, $afterid);
        $fields = 'SELECT i.id, i.messageid, i.userid, i.courseid, i.deleted ';

        return $DB->get_records_sql($fields . $sql . ' ORDER BY i.id', $params, 0, $limit);
    }

    /**
     * Builds the FROM and WHERE of a stage.
     *
     * @param string $stage One of the STAGE_* constants.
     * @param int $afterid Restrict to rows after this id, or 0 for all of them.
     * @return array Array with the SQL fragment and its parameters.
     */
    private function select(string $stage, int $afterid = 0): array {
        $params = ['cutoff' => $this->cutoff($stage), 'afterid' => $afterid];

        /*
         * Drafts are never touched. Their time is the moment they were last edited
         * rather than a send date, so an abandoned draft looks exactly as old as the
         * policy is looking for -- and set_deleted() physically deletes a draft rather
         * than trashing it, which is not what any of these stages mean.
         */
        $where = ['i.draft = 0', 'i.id > :afterid'];

        if ($stage == self::STAGE_TRASH_UPDATES) {
            /*
             * The deleted status is matched exactly, never with an inequality. A row at
             * DELETED_CONTENT satisfies no clause of the assert in set_deleted(), and
             * that assert is compiled out in production, so a sweep that caught one
             * would write over the sender's erasure and leave recipients looking at a
             * blank message with no placeholder.
             */
            $where[] = 'i.category = :category';
            $where[] = 'i.deleted = :notdeleted';
            $where[] = 'i.time < :cutoff';
            $params['category'] = message::CATEGORY_UPDATES;
            $params['notdeleted'] = message::NOT_DELETED;
        } else {
            $where[] = 'i.deleted = :deleted';
            $where[] = 'i.timedeleted > 0';
            $where[] = 'i.timedeleted < :cutoff';
            $params['deleted'] = message::DELETED;

            if ($stage == self::STAGE_EXPIRE_UPDATES) {
                $where[] = 'i.category = :category';
                $params['category'] = message::CATEGORY_UPDATES;
            } else {
                $where[] = 'i.category <> :category';
                $params['category'] = message::CATEGORY_UPDATES;
            }
        }

        if ($stage == self::STAGE_TRASH_UPDATES) {
            /*
             * Anything the user acted on is left alone. Starring is a column of the
             * covering index, so it costs nothing; the other two are indexed lookups.
             *
             * Note that "replied to" is a property of the message rather than of one
             * person's copy, because references carry no user. That is the right answer
             * for the mail this stage targets: generated messages have exactly one
             * recipient, so the only person who could have replied is the only person
             * whose copy is at stake.
             */
            $where[] = 'i.starred = 0';
            $where[] = 'NOT EXISTS ('
                . 'SELECT 1 FROM {local_mail_message_labels} ml'
                . ' JOIN {local_mail_labels} l ON l.id = ml.labelid'
                . ' WHERE ml.messageid = i.messageid AND l.userid = i.userid)';
            $where[] = 'NOT EXISTS (SELECT 1 FROM {local_mail_message_refs} mr WHERE mr.reference = i.messageid)';
        }

        $sql = 'FROM {local_mail_message_users} i WHERE ' . implode(' AND ', $where);

        return [$sql, $params];
    }
}
