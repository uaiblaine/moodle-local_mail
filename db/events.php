<?php
/*
 * SPDX-FileCopyrightText: 2012-2013 Institut Obert de Catalunya <https://ioc.gencat.cat>
 * SPDX-FileCopyrightText: 2014-2015 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

defined('MOODLE_INTERNAL') || die;

$observers = array(
    array(
        'eventname' => 'core\event\course_deleted',
        'callback'  => 'local_mail\observer::course_deleted',
    ),
);
