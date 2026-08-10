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
 * SPDX-FileCopyrightText: 2012-2013 Institut Obert de Catalunya <https://ioc.gencat.cat>
 * SPDX-FileCopyrightText: 2014-2017 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Creates a draft message with optional recipients and redirects to the message form.
 *
 * @package    local_mail
 * @copyright  2012-2024 Institut Obert de Catalunya, Marc Català, Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mail;

require_once('../../config.php');

$courseid = optional_param('course', 0, PARAM_INT);
$recipients = optional_param('recipients', '', PARAM_SEQUENCE);
$role = optional_param('role', 'to', PARAM_ALPHA);

require_login(null, false);
require_sesskey();

// Setup page.
$url = new \moodle_url('/local/mail/create.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('base');

// Get course and check permission.
$user = user::current();
if ($courseid) {
    $course = course::get($courseid);
    if (!$course || !$user->can_use_mail($course)) {
        throw new exception('errorcoursenotfound', $courseid);
    }
} else {
    $usercourses = course::get_by_user($user);
    if (!$usercourses) {
        throw new exception('errornocourses');
    }
    $course = reset($usercourses);
}

// Create message.
$data = message_data::new($course, $user);
if ($recipients) {
    $role = in_array($role, ['to', 'cc', 'bcc']) ? $role : 'to';
    $data->$role = user::get_many(explode(',', $recipients));
}
$message = message::create($data);

// Redirect to message form.
$redirecturl = new \moodle_url('/local/mail/view.php');
$redirecturl->param('t', 'drafts');
$redirecturl->param('m', $message->id);
if ($courseid && in_array(settings::get()->filterbycourse, ['fullname', 'shortname'])) {
    $redirecturl->param('c', $courseid);
}
redirect($redirecturl);
