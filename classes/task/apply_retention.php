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

namespace local_mail\task;

use local_mail\event\retention_applied;
use local_mail\local\retention;
use local_mail\message;
use local_mail\output\strings;
use local_mail\user;

/**
 * Scheduled task that applies the retention policy.
 *
 * Moves generated mail to the trash once it is old enough, and takes mail out of the
 * trash once it has been there long enough. It never removes a row or a file: those are
 * separate work, and everything this task does is still reversible from the trash until
 * the second stage runs.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_retention extends \core\task\scheduled_task {
    /** Rows fetched per query. Each one costs a transaction of its own, see execute(). */
    const BATCH = 100;

    /** Maximum rows acted on per stage per run, so one night cannot swallow a backlog whole. */
    const LIMIT = 10000;

    /**
     * Returns the localised name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return strings::get('task_apply_retention');
    }

    /**
     * Applies every enabled stage of the policy.
     *
     * @return void
     */
    public function execute(): void {
        $retention = new retention();
        $counts = [];

        foreach (retention::stages() as $stage) {
            if (!$retention->stage_enabled($stage)) {
                continue;
            }
            $counts[$stage] = $this->run_stage($retention, $stage);
            mtrace('local_mail: ' . $stage . ': ' . $counts[$stage] . ' messages per user, '
                . 'threshold ' . $retention->days($stage) . ' days');
        }

        if (!$counts) {
            mtrace('local_mail: the retention policy is switched off or every threshold is zero');
            return;
        }

        retention_applied::create_from_counts($counts)->trigger();
    }

    /**
     * Applies one stage.
     *
     * @param retention $retention Selection to read the rows from.
     * @param string $stage One of the retention STAGE_* constants.
     * @return int Number of rows acted on.
     */
    private function run_stage(retention $retention, string $stage): int {
        $status = $stage == retention::STAGE_TRASH_UPDATES ? message::DELETED : message::DELETED_FOREVER;
        $done = 0;
        $lastid = 0;

        while ($done < self::LIMIT) {
            $records = $retention->batch($stage, $lastid, self::BATCH);
            if (!$records) {
                break;
            }

            /*
             * Walked by id rather than paged by offset. Every row acted on here stops
             * matching the query that found it, so re-reading from the start -- which is
             * what empty_trash does -- would work only as long as every row transitions.
             * A row an exemption spares would sit at the front of the result set for
             * ever and the loop would never end.
             */
            foreach ($records as $record) {
                $lastid = (int) $record->id;
                if ($this->apply($record, $status)) {
                    $done++;
                }
            }
        }

        return $done;
    }

    /**
     * Moves one person's copy of one message to the next state.
     *
     * @param \stdClass $record Row of local_mail_message_users.
     * @param int $status Deleted status to write, a message DELETED_* constant.
     * @return bool Whether the row was acted on.
     */
    private function apply(\stdClass $record, int $status): bool {
        /*
         * Through set_deleted() and never with raw SQL. That method is the only thing
         * that keeps local_mail_message_labels in step with local_mail_message_users,
         * and message_search reads whichever of the two a listing happens to use -- so a
         * bulk update here would leave a message the inbox has trashed still sitting
         * under its label, with no error and nothing to notice it.
         */
        try {
            $message = message::get((int) $record->messageid);
            $user = user::get((int) $record->userid);
        } catch (\local_mail\exception $e) {
            // The message or the user went away between the query and here.
            return false;
        }

        if (!$message->has_participant($user) || $message->deleted($user) != $record->deleted) {
            /*
             * Somebody changed this row after it was selected -- restored it, or emptied
             * their own trash. Their action is the more recent one, so it stands.
             */
            return false;
        }

        $message->set_deleted($user, $status);

        return true;
    }
}
