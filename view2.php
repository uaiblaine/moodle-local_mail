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

$type = required_param('t', PARAM_ALPHA);
$messageid = optional_param('m', 0, PARAM_INT);
$courseid = optional_param('c', SITEID, PARAM_INT);
$labelid = optional_param('l', 0, PARAM_INT);

require_login(null, false);

// Check capabilities.
if ($courseid != SITEID) {
    $context = context_course::instance($courseid);
    require_capability('local/mail:usemail', $context);
}

// Set up page.
$url = new moodle_url('/local/mail/view2.php', array('t' => $type));
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

$script = local_mail_svelte_script('src/view.ts');

// Print content.
echo $OUTPUT->header();
echo html_writer::div('', '', ['id' => 'local_mail_view']);
echo $script;
echo $OUTPUT->footer();
