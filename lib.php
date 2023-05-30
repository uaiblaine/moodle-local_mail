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

/**
 * @package    local-mail
 * @copyright  Albert Gasset <albert.gasset@gmail.com>
 * @copyright  Marc Català <reskit@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/mail/locallib.php');


function local_mail_extend_navigation($root) {
    global $CFG, $COURSE, $PAGE, $SESSION, $SITE, $USER;

    if (!get_config('local_mail', 'version')) {
        return;
    }

    $context = context_course::instance($COURSE->id);

    // User profile.

    if ($PAGE->url->compare(new moodle_url('/user/view.php'), URL_MATCH_BASE) &&
            has_capability('local/mail:usemail', $context)) {
        $userid = optional_param('id', false, PARAM_INT);
        if (local_mail_valid_recipient($userid)) {
            $vars = array('course' => $COURSE->id, 'recipient' => $userid);
            $PAGE->requires->string_for_js('sendmessage', 'local_mail');
            $PAGE->requires->js_init_code('M.local_mail = ' . json_encode($vars));
            $PAGE->requires->js('/local/mail/user.js');
        }
    }

    // Users list.

    if ($PAGE->url->compare(new moodle_url('/user/index.php'), URL_MATCH_BASE) &&
            has_capability('local/mail:usemail', $context)) {
        $userid = optional_param('id', false, PARAM_INT);
        $vars = array('course' => $COURSE->id);
        $PAGE->requires->string_for_js('choosedots', 'moodle');
        $PAGE->requires->strings_for_js(array(
                'bulkmessage',
                'to',
                'cc',
                'bcc',
                ), 'local_mail');
        $PAGE->requires->js_init_code('M.local_mail = ' . json_encode($vars));
        $PAGE->requires->js('/local/mail/users.js');
    }

    // Block completion_progress.

    if ($PAGE->url->compare(new moodle_url('/blocks/completion_progress/overview.php'), URL_MATCH_BASE) &&
            has_capability('local/mail:usemail', $context)) {
        $userid = optional_param('id', false, PARAM_INT);
        $vars = array('course' => $COURSE->id);
        $PAGE->requires->string_for_js('choosedots', 'moodle');
        $PAGE->requires->strings_for_js(array(
                'bulkmessage',
                'to',
                'cc',
                'bcc',
                ), 'local_mail');
        $PAGE->requires->js_init_code('M.local_mail = ' . json_encode($vars));
        $PAGE->requires->js('/local/mail/users.js');
    }
}

function local_mail_pluginfile($course, $cm, $context, $filearea, $args,
                               $forcedownload, array $options=array()) {
    global $SITE, $USER;

    require_login($SITE, false);

    // Check message.

    $messageid = (int) array_shift($args);
    $message = local_mail_message::fetch($messageid);
    if ($filearea != 'message' || !$message || !$message->viewable($USER->id, true)) {
        return false;
    }

    // Fetch file info.

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/local_mail/$filearea/$messageid/$relativepath";
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, true, $options);
}

/**
 * Renders the navigation bar popover.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_mail_render_navbar_output(\renderer_base $renderer) {
    global $PAGE, $USER;

    $menu = local_mail_message::get_menu($USER->id);

    // Fallback link to avoid layout changes during page load.
    $url = new moodle_url('/local/mail/view.php', ['t' => 'inbox']);
    $title = get_string('pluginname', 'local_mail');
    $icon = html_writer::tag('i', '', ['class' => 'fa fa-envelope-o']);
    $class = 'btn h-100 position-relative d-flex align-items-center px-2 py-0';
    $attributes = ['href' => $url, 'class' => $class, 'title' => $title];
    $count = html_writer::div($menu['unread'] ?: '', 'count-container');
    $link = html_writer::tag('a', $icon . $count, $attributes);

    $container = html_writer::div($link, '', ['id' => 'local-mail-navbar']);

    $viewurl = new moodle_url('/local/mail/view2.php');
    if ($PAGE->url->compare($viewurl, URL_MATCH_BASE)) {
        // Menu is handled from the view page.
        return $container;
    } else {
        // Other page in the site, we use an Svelte interface which does not use web services.
        $data = [
            'strings' => [
                'togglemailmenu' => get_string('togglemailmenu', 'local_mail'),
                'compose' => get_string('compose', 'local_mail'),
                'preferences' => get_string('preferences', 'local_mail'),
                'inbox' => get_string('inbox', 'local_mail'),
                'starredmail' => get_string('starredmail', 'local_mail'),
                'sentmail' => get_string('sentmail', 'local_mail'),
                'drafts' => get_string('drafts', 'local_mail'),
                'trash' => get_string('trash', 'local_mail'),
            ],
            'menu' => $menu,
        ];
        $datascript = html_writer::script('window.local_mail_navbar_data = '. json_encode($data));
        $sveltescript = local_mail_svelte_script('src/navbar.ts');
        return  $container . $datascript . $sveltescript;
    }
}

/**
 * Context of the navigation bar popover template.
 *
 * @return array|null
 */
function local_mail_render_navbar_context() {
    global $CFG, $COURSE, $PAGE, $USER;

    if (!isloggedin() || isguestuser() || user_not_fully_set_up($USER) ||
            get_user_preferences('auth_forcepasswordchange') ||
            ($CFG->sitepolicy && !$USER->policyagreed && !is_siteadmin())) {
        return null;
    }

    $composeurl = new moodle_url('/local/mail/compose.php');
    if ($PAGE->url->compare($composeurl, URL_MATCH_BASE)) {
        $composeurl->param('m', $PAGE->url->param('m'));
    } else {
        $composeurl = new moodle_url('/local/mail/create.php');
        if ($COURSE->id != SITEID) {
            $composeurl->param('c', $COURSE->id);
            $composeurl->param('sesskey', sesskey());
        }
    }

    $preferencesurl = new moodle_url('/local/mail/preferences.php');
    $viewurl = new moodle_url('/local/mail/view.php');

    $activetype = null;
    $activecourseid = null;
    $activelabelid = null;
    if ($PAGE->url->compare($viewurl, URL_MATCH_BASE)) {
        $activetype = $PAGE->url->param('t');
        if ($activetype == 'course') {
            $activecourseid = $PAGE->url->param('c');
        } else if ($activetype == 'label') {
            $activelabelid = $PAGE->url->param('l');
        }
    }

    $count = local_mail_message::count_menu($USER->id);

    $context = [
        'activetype' => $activetype,
        'activecourseid' => $activecourseid,
        'activelabelid' => $activelabelid,
        'composeurl' => $composeurl->out(),
        'preferencesurl' => $preferencesurl->out(),
        'viewurl' => $viewurl->out(),
        'count' => isset($count->inbox) ? $count->inbox : 0,
        'items' => [
            [
                'url' => (string) new moodle_url($viewurl, ['t' => 'inbox']),
                'icon' => 'inbox',
                'text' => get_string('inbox', 'local_mail'),
                'unread' => isset($count->inbox) ? $count->inbox : 0,
                'active' => ($activetype == 'inbox'),
            ],
            [
                'url' => (string) new moodle_url($viewurl, ['t' => 'starred']),
                'icon' => 'starred',
                'text' => get_string('starred', 'local_mail'),
                'active' => ($activetype == 'starred'),
            ],
            [
                'url' => (string) new moodle_url($viewurl, ['t' => 'drafts']),
                'icon' => 'drafts',
                'text' => get_string('drafts', 'local_mail'),
                'drafts' => isset($count->drafts) ? $count->drafts : 0,
                'active' => ($activetype == 'drafts'),
            ],
            [
                'url' => (string) new moodle_url($viewurl, ['t' => 'sent']),
                'icon' => 'sent',
                'text' => get_string('sentmail', 'local_mail'),
                'active' => ($activetype == 'sent'),
            ],
            [
                'url' => (string) new moodle_url($viewurl, ['t' => 'trash']),
                'icon' => 'trash',
                'text' => get_string('trash', 'local_mail'),
                'active' => ($activetype == 'trash'),
            ]
        ],
    ];

    foreach (local_mail_label::fetch_user($USER->id) as $label) {
        $context['items'][] = [
            'url' => (string) new moodle_url($viewurl, ['t' => 'label', 'l' => $label->id()]),
            'icon' => 'label',
            'text' => $label->name(),
            'unread' => isset($count->labels[$label->id()]) ? $count->labels[$label->id()] : 0,
            'active' => ($activelabelid == $label->id()),
        ];
    }

    foreach (local_mail_get_my_courses() as $course) {
        $context['items'][] = [
            'url' => (string) new moodle_url($viewurl, ['t' => 'course', 'c' => $course->id]),
            'icon' => 'course',
            'text' => $course->shortname,
            'title' => $course->fullname,
            'unread' => isset($count->courses[$course->id]) ? $count->courses[$course->id] : 0,
            'dimmed' => !$course->visible,
            'active' => ($activecourseid == $course->id),
        ];
    }

    return $context;
}

/**
 * Get icon mapping for font-awesome.
 */
function local_mail_get_fontawesome_icon_map() {
    return [
        'local_mail:compose' => 'fa-pencil-square-o',
        'local_mail:course' => 'fa-university',
        'local_mail:drafts' => 'fa-file',
        'local_mail:icon' => 'fa-envelope',
        'local_mail:inbox' => 'fa-inbox',
        'local_mail:label' => 'fa-tag',
        'local_mail:sent' => 'fa-paper-plane',
        'local_mail:starred' => 'fa-star',
        'local_mail:trash' => 'fa-trash',
    ];
}
