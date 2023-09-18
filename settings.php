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

use local_mail\settings;
use local_mail\output\strings;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $defaults = settings::defaults();

    $settings = new admin_settingpage('local_mail', strings::get('pluginname'));

    // Backup.
    $settings->add(new admin_setting_heading('local_mail_backup', get_string('backup'), ''));

    $name = 'local_mail/enablebackup';
    $visiblename = strings::get('configenablebackup');
    $description = strings::get('configenablebackupdesc');
    $defaultsetting = $defaults->enablebackup;
    $settings->add(new admin_setting_configcheckbox($name, $visiblename, $description, $defaultsetting));

    // New mail.
    $settings->add(new admin_setting_heading('local_mail_newmail', strings::get('newmail'), ''));

    // Number of recipients.
    $name = 'local_mail/maxrecipients';
    $visiblename = strings::get('configmaxrecipients');
    $description = strings::get('configmaxrecipientsdesc');
    $defaultsetting = $defaults->maxrecipients;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    // User search limit.
    $name = 'local_mail/usersearchlimit';
    $visiblename = strings::get('configusersearchlimit');
    $description = strings::get('configusersearchlimitdesc');
    $defaultsetting = $defaults->usersearchlimit;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    // Number of attachments.
    $name = 'local_mail/maxfiles';
    $visiblename = strings::get('configmaxattachments');
    $description = strings::get('configmaxattachmentsdesc');
    $defaultsetting = $defaults->maxfiles;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    // Attachment size.
    $name = 'local_mail/maxbytes';
    $visiblename = strings::get('configmaxattachmentsize');
    $description = strings::get('configmaxattachmentsizedesc');
    $defaultsetting = $defaults->maxbytes;
    $paramtype = PARAM_INT;
    $choices = get_max_upload_sizes($CFG->maxbytes ?? 0, 0, 0, settings::fetch()->maxbytes);
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Trays.
    $settings->add(new admin_setting_heading('local_mail_trays', strings::get('trays'), ''));

    // Global trays.
    $name = 'local_mail/globaltrays';
    $visiblename = strings::get('configglobaltrays');
    $description = strings::get('configglobaltraysdesc');
    $defaultsetting = [];
    foreach ($defaults->globaltrays as $tray) {
        $defaultsetting[$tray] = 1;
    }
    $choices = [
        'starred' => strings::get('starredmail'),
        'sent' => strings::get('sentmail'),
        'drafts' => strings::get('drafts'),
        'trash' => strings::get('trash'),
    ];
    $settings->add(new admin_setting_configmulticheckbox($name, $visiblename, $description, $defaultsetting, $choices));

    // Course trays.
    $name = 'local_mail/coursetrays';
    $visiblename = strings::get('configcoursetrays');
    $description = strings::get('configcoursetraysdesc');
    $defaultsetting = $defaults->coursetrays;
    $choices = [
        'none' => get_string('none'),
        'unread' => strings::get('courseswithunreadmessages'),
        'all' => get_string('allcourses', 'search'),
    ];
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Course trays name.
    $name = 'local_mail/coursetraysname';
    $visiblename = strings::get('configcoursetraysname');
    $description = strings::get('configcoursetraysnamedesc');
    $defaultsetting = $defaults->coursetraysname;
    $choices = [
        'shortname' => get_string('shortname'),
        'fullname' => get_string('fullname'),
    ];
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Filter by course.
    $name = 'local_mail/filterbycourse';
    $visiblename = strings::get('configfilterbycourse');
    $description = strings::get('configfilterbycoursedesc');
    $defaultsetting = $defaults->filterbycourse;
    $choices = [
        'hidden' => get_string('hide'),
        'shortname' => get_string('shortname'),
        'fullname' => get_string('fullname'),
    ];
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Messages.
    $settings->add(new admin_setting_heading('local_mail_messages', strings::get('messages'), ''));

    // Course badge type.
    $name = 'local_mail/coursebadges';
    $visiblename = strings::get('configcoursebadges');
    $description = strings::get('configcoursebadgesdesc');
    $defaultsetting = $defaults->coursebadges;
    $choices = [
        'hidden' => get_string('hide'),
        'shortname' => get_string('shortname'),
        'fullname' => get_string('fullname'),
    ];
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Course badge length.
    $name = 'local_mail/coursebadgeslength';
    $visiblename = strings::get('configcoursebadgeslength');
    $description = strings::get('configcoursebadgeslengthdesc');
    $defaultsetting = $defaults->coursebadgeslength;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    // Search.
    $settings->add(new admin_setting_heading('local_mail_search', strings::get('search'), ''));

    // Incremental search.
    $name = 'local_mail/incrementalsearch';
    $visiblename = strings::get('configincrementalsearch');
    $description = strings::get('configincrementalsearchdesc');
    $defaultsetting = $defaults->incrementalsearch;
    $settings->add(new admin_setting_configcheckbox($name, $visiblename, $description, $defaultsetting));

    // Incremental search limit.
    $name = 'local_mail/incrementalsearchlimit';
    $visiblename = strings::get('configincrementalsearchlimit');
    $description = strings::get('configincrementalsearchlimitdesc');
    $defaultsetting = $defaults->incrementalsearchlimit;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    $ADMIN->add('localplugins', $settings);
}
