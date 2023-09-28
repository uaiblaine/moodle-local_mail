<?php
/*
 * SPDX-FileCopyrightText: 2012-2013 Institut Obert de Catalunya <https://ioc.gencat.cat>
 * SPDX-FileCopyrightText: 2014-2017 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use local_mail\course;
use local_mail\message;
use local_mail\message_data;
use local_mail\user;

require_once('../../config.php');

$courseid = required_param('course', PARAM_INT);
$recipients = optional_param('recipients', '', PARAM_SEQUENCE);
$role = optional_param('role', 'to', PARAM_ALPHA);

require_login($courseid, false);
require_sesskey();

// Setup page.
$url = new moodle_url('/local/mail/create.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('base');

// Check permission.
$user = user::current();
$course = course::get($courseid);
if (!$user->can_use_mail($course)) {
    throw new exception('errorcoursenotfound', $courseid);
}

// Create message.
$data = message_data::new($course, $user);
if ($recipients) {
    $role = in_array($role, ['to', 'cc', 'bcc']) ? $role : 'to';
    $data->$role = user::get_many(explode(',', $recipients));
}
$message = message::create($data);

// Redirect to message form.
redirect(new moodle_url('/local/mail/view.php', ['t' => 'drafts', 'c' => $course->id, 'm' => $message->id]));
