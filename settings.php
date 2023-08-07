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

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $defaults = settings::defaults();

    $settings = new admin_settingpage('local_mail', get_string('pluginname', 'local_mail'));

    // Backup.
    $settings->add(new admin_setting_heading('local_mail_backup', get_string('backup'), ''));

    $name = 'local_mail/enablebackup';
    $visiblename = get_string('configenablebackup', 'local_mail');
    $description = get_string('configenablebackupdesc', 'local_mail');
    $defaultsetting = $defaults->enablebackup;
    $settings->add(new admin_setting_configcheckbox($name, $visiblename, $description, $defaultsetting));

    // New mail.
    $settings->add(new admin_setting_heading('local_mail_newmail', get_string('newmail', 'local_mail'), ''));

    // Number of recipients.
    $name = 'local_mail/maxrecipients';
    $visiblename = get_string('configmaxrecipients', 'local_mail');
    $description = get_string('configmaxrecipientsdesc', 'local_mail');
    $defaultsetting = $defaults->maxrecipients;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    // User search limit.
    $name = 'local_mail/usersearchlimit';
    $visiblename = get_string('configusersearchlimit', 'local_mail');
    $description = get_string('configusersearchlimitdesc', 'local_mail');
    $defaultsetting = $defaults->usersearchlimit;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    // Number of attachments.
    $name = 'local_mail/maxfiles';
    $visiblename = get_string('configmaxattachments', 'local_mail');
    $description = get_string('configmaxattachmentsdesc', 'local_mail');
    $defaultsetting = $defaults->maxfiles;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    // Attachment size.
    $name = 'local_mail/maxbytes';
    $visiblename = get_string('configmaxattachmentsize', 'local_mail');
    $description = get_string('configmaxattachmentsizedesc', 'local_mail');
    $defaultsetting = $defaults->maxbytes;
    $paramtype = PARAM_INT;
    $choices = get_max_upload_sizes($CFG->maxbytes ?? 0, 0, 0, settings::fetch()->maxbytes);
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Trays.
    $settings->add(new admin_setting_heading('local_mail_trays', get_string('trays', 'local_mail'), ''));

    // Global trays.
    $name = 'local_mail/globaltrays';
    $visiblename = get_string('configglobaltrays', 'local_mail');
    $description = get_string('configglobaltraysdesc', 'local_mail');
    $defaultsetting = [];
    foreach ($defaults->globaltrays as $tray) {
        $defaultsetting[$tray] = 1;
    }
    $choices = [
        'starred' => get_string('starredmail', 'local_mail'),
        'sent' => get_string('sentmail', 'local_mail'),
        'drafts' => get_string('drafts', 'local_mail'),
        'trash' => get_string('trash', 'local_mail'),
    ];
    $settings->add(new admin_setting_configmulticheckbox($name, $visiblename, $description, $defaultsetting, $choices));

    // Course trays.
    $name = 'local_mail/coursetrays';
    $visiblename = get_string('configcoursetrays', 'local_mail');
    $description = get_string('configcoursetraysdesc', 'local_mail');
    $defaultsetting = $defaults->coursetrays;
    $choices = [
        'none' => get_string('none'),
        'unread' => get_string('courseswithunreadmessages', 'local_mail'),
        'all' => get_string('allcourses', 'search'),
    ];
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Course trays name.
    $name = 'local_mail/coursetraysname';
    $visiblename = get_string('configcoursetraysname', 'local_mail');
    $description = get_string('configcoursetraysnamedesc', 'local_mail');
    $defaultsetting = $defaults->coursetraysname;
    $choices = [
        'shortname' => get_string('shortname'),
        'fullname' => get_string('fullname'),
    ];
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Messages.
    $settings->add(new admin_setting_heading('local_mail_messages', get_string('messages', 'local_mail'), ''));

    // Course badge type.
    $name = 'local_mail/coursebadges';
    $visiblename = get_string('configcoursebadges', 'local_mail');
    $description = get_string('configcoursebadgesdesc', 'local_mail');
    $defaultsetting = $defaults->coursebadges;
    $choices = [
        'hidden' => get_string('hide'),
        'shortname' => get_string('shortname'),
        'fullname' => get_string('fullname'),
    ];
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    // Course badge length.
    $name = 'local_mail/coursebadgeslength';
    $visiblename = get_string('configcoursebadgeslength', 'local_mail');
    $description = get_string('configcoursebadgeslengthdesc', 'local_mail');
    $defaultsetting = $defaults->coursebadgeslength;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));

    // Course badge type.
    $name = 'local_mail/filterbycourse';
    $visiblename = get_string('configfilterbycourse', 'local_mail');
    $description = get_string('configfilterbycoursedesc', 'local_mail');
    $defaultsetting = $defaults->filterbycourse;
    $choices = [
        'hidden' => get_string('hide'),
        'shortname' => get_string('shortname'),
        'fullname' => get_string('fullname'),
    ];
    $settings->add(new admin_setting_configselect($name, $visiblename, $description, $defaultsetting, $choices));

    $ADMIN->add('localplugins', $settings);

    // Search.
    $settings->add(new admin_setting_heading('local_mail_search', get_string('search', 'local_mail'), ''));

    // Incremental search.
    $name = 'local_mail/incrementalsearch';
    $visiblename = get_string('configincrementalsearch', 'local_mail');
    $description = get_string('configincrementalsearchdesc', 'local_mail');
    $defaultsetting = $defaults->incrementalsearch;
    $settings->add(new admin_setting_configcheckbox($name, $visiblename, $description, $defaultsetting));

    // Incremental search limit.
    $name = 'local_mail/incrementalsearchlimit';
    $visiblename = get_string('configincrementalsearchlimit', 'local_mail');
    $description = get_string('configincrementalsearchlimitdesc', 'local_mail');
    $defaultsetting = $defaults->incrementalsearchlimit;
    $paramtype = PARAM_INT;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, $defaultsetting, $paramtype));
}
