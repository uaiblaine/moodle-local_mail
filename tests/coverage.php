<?php
/*
 * SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

defined('MOODLE_INTERNAL') || die();

class local_mail_coverage extends phpunit_coverage_info {

    protected $includelistfolders = [
        'backup',
    ];

    protected $includelistfiles = [
        'db/upgrade.php',
    ];
}

return new local_mail_coverage;
