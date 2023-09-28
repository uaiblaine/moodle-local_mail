<?php
/*
 * SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'courses' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
    ],
    'labels' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
    ],
    'messages' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
    ],
    'usercourseids' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
    ],
    'userlabelids' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
    ],
    'users' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
    ],
];
