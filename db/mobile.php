<?php
/*
 * SPDX-FileCopyrightText: 2017 Albert Gasset <albertgasset@fsfe.org>
 * SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'local_mail' => [
        'handlers' => [
            'view' => [
                'delegate' => 'CoreMainMenuDelegate',
                'method' => 'view',
                'init' => 'init',
                'displaydata' => [
                    'title' => 'pluginname',
                    'icon' => 'far-envelope',
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'local_mail'],
        ],
    ],
];
