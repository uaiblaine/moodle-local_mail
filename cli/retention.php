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

/**
 * CLI script that reports what the retention policy would remove.
 *
 * Read-only. It exists so that the queries deciding what gets deleted can be run
 * against real data, and their answer read by a person, before the scheduled task is
 * ever allowed to act on them.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mail;

use local_mail\local\retention;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'dry-run' => false,
        'days' => '',
        'help' => false,
    ],
    ['h' => 'help', 'n' => 'dry-run']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help'] || !$options['dry-run']) {
    echo "Reports what the Local Mail retention policy would remove.\n\n";
    echo "This script never changes anything. --dry-run is required rather than\n";
    echo "optional, so that running it by mistake cannot be read as an instruction\n";
    echo "to delete: there is no other mode, and adding one is a deliberate change.\n\n";
    echo "Options:\n";
    echo "  -n, --dry-run   Report what would be removed. Required.\n";
    echo "      --days=A,B,C  Override the configured thresholds, in days, as\n";
    echo "                  updates-to-trash, updates-in-trash, other-in-trash. Use\n";
    echo "                  this to see the effect of a setting before saving it.\n";
    echo "  -h, --help      Print this help.\n\n";
    echo "Example:\n";
    echo "  php local/mail/cli/retention.php --dry-run --days=30,90,30\n";
    exit($options['help'] ? 0 : 1);
}

$settings = settings::get();

/*
 * The override exists because the honest way to choose a threshold is to see what it
 * would reach. Without it an administrator has to save a setting, and therefore arm the
 * policy, in order to find out whether the number was sensible.
 */
if ($options['days'] !== '') {
    $days = array_map('intval', explode(',', $options['days']));
    if (count($days) != 3) {
        cli_error('--days needs three numbers separated by commas.');
    }
    $settings->retentionenabled = true;
    [$settings->retentionupdatesdays, $settings->retentionupdatestrashdays, $settings->retentiontrashdays] = $days;
    cli_writeln('Using the thresholds given on the command line, not the stored settings.');
    cli_writeln('');
}

if (!$settings->retentionenabled) {
    cli_writeln('The retention policy is switched off, so nothing would be removed.');
    cli_writeln('Pass --days to see what a given set of thresholds would reach anyway.');
    exit(0);
}

$retention = new retention($settings);

$labels = [
    retention::STAGE_TRASH_UPDATES => 'Updates moved to the trash',
    retention::STAGE_EXPIRE_UPDATES => 'Updates removed from the trash',
    retention::STAGE_EXPIRE_TRASH => 'Other mail removed from the trash',
];

$total = 0;

foreach (retention::stages() as $stage) {
    $days = $retention->days($stage);
    $count = $retention->count($stage);
    $total += $count;
    $line = str_pad($labels[$stage], 36) . str_pad((string) $count, 10, ' ', STR_PAD_LEFT);
    if (!$retention->stage_enabled($stage)) {
        cli_writeln($line . '   (off)');
    } else {
        cli_writeln($line . '   after ' . $days . ' days, before ' . userdate($retention->cutoff($stage)));
    }
}

cli_writeln('');
cli_writeln(str_pad('Rows affected in total', 36) . str_pad((string) $total, 10, ' ', STR_PAD_LEFT));
cli_writeln('');

/*
 * Rows rather than messages, and the difference matters. Every count above is a
 * per-user copy: a message with three recipients contributes three rows and disappears
 * for each of them separately. Storage is only reclaimed once the last copy is gone.
 */
cli_writeln('Each row is one person\'s copy of a message, not a message.');
cli_writeln('Nothing was changed.');

exit(0);
