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

namespace local_mail\event;

/**
 * Event fired once per run of the retention task, recording what it did.
 *
 * One event per run rather than one per message: a sweep touches thousands of rows and
 * a log entry for each would bury the log it is meant to explain. It is also the only
 * record that will survive the rows themselves, which is what makes it possible to
 * answer "where did my mail go" a month later.
 *
 * Unlike every other event in this plugin, nothing here reads the current user. Under
 * cron that is whoever the scheduled task runs as, which would attribute the removal to
 * a person who had nothing to do with it.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retention_applied extends \core\event\base {
    /**
     * Creates the event from the counts of a run.
     *
     * @param array $counts Number of rows acted on, indexed by retention stage.
     * @return self
     */
    public static function create_from_counts(array $counts): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid' => 0,
            'other' => ['counts' => $counts],
        ]);
    }

    /**
     * Returns the localised name of the event.
     *
     * @return string
     */
    public static function get_name(): string {
        return \local_mail\output\strings::get('eventretentionapplied');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $parts = [];
        foreach ($this->other['counts'] as $stage => $count) {
            $parts[] = $stage . '=' . $count;
        }

        return 'The mail retention policy ran and acted on these numbers of messages per user: '
            . (implode(', ', $parts) ?: 'none') . '.';
    }

    /**
     * Initialises the event data.
     *
     * No objecttable or objectid: a run is not one row, and the rows it acted on are
     * gone or changed by the time this is read.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
}
