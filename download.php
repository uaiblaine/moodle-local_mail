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

use local_mail\message;
use local_mail\settings;
use local_mail\user;

require_once('../../config.php');
require_once("$CFG->libdir/filelib.php");

$messageid = required_param('m', PARAM_INT);

require_login(null, false);

if (!settings::is_installed()) {
    throw new moodle_exception('errorpluginnotinstalled', 'local_mail');
}

$user = user::current();
$message = message::fetch($messageid);

if (!$user || !$message || !$user->can_view_files($message)) {
    send_file_not_found();
}

$files = get_file_storage()->get_area_files(
    $message->course->context()->id,
    'local_mail',
    'message',
    $message->id,
    'filepath, filename',
    false
);

$zipfiles = [];
foreach ($files as $file) {
    $filename = clean_filename($file->get_filepath() . $file->get_filename());
    $zipfiles[$filename] = $file;
}

$zipper = new zip_packer();
$tempzip = tempnam($CFG->tempdir . '/', 'local_mail_');

if ($zipper->archive_to_pathname($zipfiles, $tempzip)) {
    $filename = clean_filename($message->sender()->fullname() . ' - ' . $message->subject . '.zip');
    send_temp_file($tempzip, $filename);
} else {
    send_file_not_found();
}
