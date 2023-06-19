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

use local_mail\external;
use local_mail\message;
use local_mail\user;
use local_mail\search;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/mail/locallib.php');

function local_mail_extend_navigation($root) {
    global $COURSE, $PAGE;

    if (!local_mail_is_installed()) {
        return;
    }

    $context = context_course::instance($COURSE->id);

    // User profile.

    if (
        $PAGE->url->compare(new moodle_url('/user/view.php'), URL_MATCH_BASE) &&
        has_capability('local/mail:usemail', $context)
    ) {
        $userid = optional_param('id', false, PARAM_INT);
        if (local_mail_valid_recipient($userid)) {
            $vars = array('course' => $COURSE->id, 'recipient' => $userid);
            $PAGE->requires->string_for_js('sendmessage', 'local_mail');
            $PAGE->requires->js_init_code('M.local_mail = ' . json_encode($vars));
            $PAGE->requires->js('/local/mail/user.js');
        }
    }

    // Users list.

    if (
        $PAGE->url->compare(new moodle_url('/user/index.php'), URL_MATCH_BASE) &&
        has_capability('local/mail:usemail', $context)
    ) {
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

    if (
        $PAGE->url->compare(new moodle_url('/blocks/completion_progress/overview.php'), URL_MATCH_BASE) &&
        has_capability('local/mail:usemail', $context)
    ) {
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

function local_mail_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = array()
) {
    global $SITE, $USER;

    require_login($SITE, false);

    $user = user::current();

    // Check message.

    $messageid = (int) array_shift($args);
    $message = message::fetch($messageid);
    if ($filearea != 'message' || !$message || !$user->can_view_files($message)) {
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
    global $PAGE;

    if (!local_mail_is_installed()) {
        return '';
    }

    $user = user::current();
    if (!$user) {
        return;
    }

    // Fallback link to avoid layout changes during page load.
    $url = new moodle_url('/local/mail/view.php', ['t' => 'inbox']);
    $title = get_string('pluginname', 'local_mail');
    $class = 'btn h-100 d-flex align-items-center px-2 py-0';

    $viewurl = new moodle_url('/local/mail/view.php');
    if ($PAGE->url->compare($viewurl, URL_MATCH_BASE)) {
        // Menu is handled from the view page.
        $icon = html_writer::tag('i', '', ['class' => 'fa fa-fw fa-spinner fa-pulse', 'style' => "font-size: 16px"]);
        $spinner = html_writer::tag('div', $icon, ['class' => $class]);
        $container = html_writer::div($spinner, '', ['id' => 'local-mail-navbar']);
        return $container;
    } else {
        // Other page in the site.
        $icon = html_writer::tag('i', '', ['class' => 'fa fa-fw fa-envelope-o', 'style' => "font-size: 16px"]);
        $attributes = ['href' => $url, 'class' => $class, 'title' => $title];
        $link = html_writer::tag('a', $icon, $attributes);
        $container = html_writer::div($link, '', ['id' => 'local-mail-navbar']);

        // Pass all data via a script tag to avoid web service requests.
        $strings = external::get_strings_raw();

        $search = new search($user);
        $search->unread = true;
        $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
        $unread = $search->count();
        $search = new search($user);
        $search->draft = true;
        $drafts = $search->count();

        $data = [
            'settings' => external::get_settings_raw(),
            'strings' => [
                'togglemailmenu' => $strings['togglemailmenu'],
                'compose' => $strings['compose'],
                'preferences' => $strings['preferences'],
                'inbox' => $strings['inbox'],
                'starredmail' => $strings['starredmail'],
                'sentmail' => $strings['sentmail'],
                'drafts' => $strings['drafts'],
                'trash' => $strings['trash'],
            ],
            'unread' => $unread,
            'drafts' => $drafts,
            'courses' => external::get_courses_raw(),
            'labels' => external::get_labels_raw(),
        ];
        $datascript = html_writer::script('window.local_mail_navbar_data = ' . json_encode($data));
        $sveltescript = local_mail_svelte_script('src/navbar.ts');
        return  $container . $datascript . $sveltescript;
    }
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
