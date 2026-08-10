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
 * SPDX-FileCopyrightText: 2012-2014 Institut Obert de Catalunya <https://ioc.gencat.cat>
 * SPDX-FileCopyrightText: 2014-2020 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2016-2025 Albert Gasset <albertgasset@fsfe.org>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Callbacks called by Moodle core.
 *
 * @package    local_mail
 * @copyright  2012-2025 Institut Obert de Catalunya, Marc Català, Albert Gasset, Proyecto UNIMOODLE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mail\course;
use local_mail\exception;
use local_mail\message;
use local_mail\output\strings;
use local_mail\settings;
use local_mail\user;

/**
 * Serves the files attached to a message.
 *
 * @param stdClass $course Course object.
 * @param stdClass $cm Course module object.
 * @param context $context Context of the file.
 * @param string $filearea File area.
 * @param array $args Remaining components of the file path.
 * @param bool $forcedownload Whether to force the download of the file.
 * @param array $options Additional options affecting the file serving.
 * @return bool|stored_file False if the file cannot be served.
 */
function local_mail_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    global $SITE;

    require_login($SITE, false);

    $user = user::current();

    if (!settings::is_installed() || !$user || $filearea != 'message') {
        return false;
    }

    // Check message.
    $messageid = (int) array_shift($args);
    try {
        $message = message::get($messageid);
    } catch (exception $e) {
        return false;
    }
    if (!$user->can_view_files($message)) {
        return false;
    }

    // Get file.
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/local_mail/$filearea/$messageid/$relativepath";
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }

    if (PHPUNIT_TEST) {
        return $file;
    }

    // @codeCoverageIgnoreStart
    send_stored_file($file, null, 0, true, $options);
    // @codeCoverageIgnoreEnd
}

/**
 * Renders the navigation bar envelope icon with an asynchronous unread-count badge.
 *
 * The badge is filled in by the `local_mail/navbar` AMD module, which calls the
 * existing `local_mail_count_messages` web service. The icon is a direct link to
 * the inbox; no popover is opened on click.
 *
 * Not reimplemented here: the Svelte "Send mail" button that the now-removed
 * svelte/src/navigation.ts entry point injected on /user/view.php (profile),
 * /user/index.php (participants) and /blocks/completion_progress/overview.php.
 * Any future version of it must be scoped to those specific pages, so that the
 * heavy courses/labels payload is not loaded on every page render.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_mail_render_navbar_output(\renderer_base $renderer) {
    global $PAGE;

    $user = user::current();

    if (!settings::is_installed() || WS_SERVER || AJAX_SCRIPT || !$user || !course::get_by_user($user)) {
        return '';
    }

    $url = new moodle_url('/local/mail/view.php', ['t' => 'inbox']);
    $ismailpage = $PAGE->url->compare($url, URL_MATCH_BASE);

    $mailicon = html_writer::tag('i', '', [
        'class' => 'fa fa-fw fa-envelope-o icon m-0',
        'style' => 'font-size: 16px',
    ]);
    $badge = '';
    if (!$ismailpage) {
        $badge = html_writer::span('', 'local-mail-navbar-count count-container', [
            'hidden' => 'hidden',
        ]);
    }
    $link = html_writer::tag('a', $mailicon . $badge, [
        'href' => $url,
        'class' => 'nav-link btn h-100 position-relative d-flex align-items-center px-2 py-0',
        'title' => strings::get('pluginname'),
    ]);
    $output = html_writer::div($link, 'popover-region', ['id' => 'local-mail-navbar']);

    if (!$ismailpage) {
        $PAGE->requires->js_call_amd('local_mail/navbar', 'init');
    }

    return $output;
}
