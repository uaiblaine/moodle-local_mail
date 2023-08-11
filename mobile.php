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

use local_mail\external;
use local_mail\settings;

require_once('../../config.php');

global $PAGE;

$type = optional_param('t', '', PARAM_ALPHA);
$messageid = optional_param('m', 0, PARAM_INT);
$courseid = optional_param('c', SITEID, PARAM_INT);
$labelid = optional_param('l', 0, PARAM_INT);

if (!settings::is_installed()) {
    throw new moodle_exception('pluginnotinstalled', 'local_mail');
}

require_login(null, false);

// Check capabilities.
if ($courseid != SITEID) {
    $context = context_course::instance($courseid);
    require_capability('local/mail:usemail', $context);
}

// Set up page.
$url = new moodle_url('/local/mail/mobile.php', array('t' => $type));
if ($type == 'course') {
    $url->param('c', $courseid);
}
if ($type == 'label') {
    $url->param('l', $labelid);
}
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('pluginname', 'local_mail'));


// Initial data passed via a script tag.
$data = [
    'userid' => $USER->id,
    'settings' => (array) settings::fetch(),
    'preferences' => external::get_preferences_raw(),
    'strings' => external::get_strings_raw(),
    'mobile' => true,
];

$datascript = html_writer::script('window.local_mail_view_data = ' . json_encode($data));

$renderer = $PAGE->get_renderer('local_mail');
$sveltescript = $renderer->svelte_script('src/view.ts');

// Print content.
echo $OUTPUT->header();
echo html_writer::div('', '', ['id' => 'local-mail-view']);
echo $datascript;
echo $sveltescript;
echo $OUTPUT->footer();
