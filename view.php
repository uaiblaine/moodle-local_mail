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

require_once('../../config.php');
require_once('locallib.php');

global $PAGE;

$type = optional_param('t', 'inbox', PARAM_ALPHA);
$messageid = optional_param('m', 0, PARAM_INT);
$courseid = optional_param('c', SITEID, PARAM_INT);
$labelid = optional_param('l', 0, PARAM_INT);

$reply = optional_param('reply', false, PARAM_BOOL);
$replyall = optional_param('replyall', false, PARAM_BOOL);
$forward = optional_param('forward', false, PARAM_BOOL);

if ($reply || $replyall || $forward) {
    $messageid = required_param('m', PARAM_INT);

    $message = local_mail_message::fetch($messageid);
    if (!$message || !$message->viewable($USER->id)) {
        throw new \moodle_exception('invalidmessage', 'local_mail');
    }

    require_login($message->course()->id, false);
    require_sesskey();
    require_capability('local/mail:usemail', $PAGE->context);

    if ($forward) {
        $newmessage = $message->forward($USER->id);
    } else {
        $newmessage = $message->reply($USER->id, $replyall);
    }

    $url = new moodle_url('/local/mail/compose.php', array('m' => $newmessage->id()));
    redirect($url);
}

require_login(null, false);

// Check capabilities.
if ($courseid != SITEID) {
    $context = context_course::instance($courseid);
    require_capability('local/mail:usemail', $context);
}

// Set up page.
$url = new moodle_url('/local/mail/view.php', array('t' => $type));
if ($type == 'course') {
    $url->param('c', $courseid);
}
if ($type == 'label') {
    $url->param('l', $labelid);
}
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('pluginname', 'local_mail'));


// Initial data passed via a script tag.
$data = [
    'userid' => $USER->id,
    'settings' => local_mail_get_settings(),
    'preferences' => local_mail_get_preferences(),
    'strings' => local_mail_get_strings(),
];

$datascript = html_writer::script('window.local_mail_view_data = '. json_encode($data));

$sveltescript = local_mail_svelte_script('src/view.ts');

// Print content.
echo $OUTPUT->header();
echo html_writer::div('', '', ['id' => 'local-mail-view']);
echo $datascript;
echo $sveltescript;
echo $OUTPUT->footer();
