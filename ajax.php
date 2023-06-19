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
 * @author     Albert Gasset <albert.gasset@gmail.com>
 * @author     Marc Català <reskit@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mail\message;
use local_mail\user;

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

global $CFG, $USER;

require_once($CFG->dirroot . '/local/mail/lib.php');

$action     = required_param('action', PARAM_ALPHA);
$messageid  = required_param('msgs', PARAM_INT);
$sesskey    = required_param('sesskey', PARAM_RAW);
$search     = optional_param('search', '', PARAM_RAW);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);
$roleids    = optional_param('roleids', '', PARAM_SEQUENCE);
$recipients = optional_param('recipients', '', PARAM_SEQUENCE);

define('MAIL_MAXUSERS', 100);

require_login(SITEID, false);

if (!confirm_sesskey($sesskey)) {
    echo json_encode(array('msgerror' => get_string('invalidsesskey', 'error')));
    exit;
}

$message = \local_mail\message::fetch($messageid);
if (!$message || !\local_mail\user::current()->can_view_message($message)) {
    echo json_encode(array('msgerror' => get_string('errormessagenotfound', 'local_mail')));
    exit;
}

if ($action === 'getrecipients') {
    $result = local_mail_getrecipients($message, $search, $groupid, $roleid);
    echo json_encode($result);
    exit;
} else if ($action === 'updaterecipients') {
    $recipients = explode(',', $recipients);
    $roleids = explode(',', $roleids);
    $result = local_mail_updaterecipients($message, $recipients, $roleids);
    echo json_encode($result);
    exit;
} else {
    echo json_encode(array('msgerror' => 'Invalid data'));
    exit;
}

function local_mail_getrecipients($message, $search, $groupid, $roleid) {
    global $DB, $PAGE, $CFG;

    $participants = array();
    $recipients = array();
    $mailmaxusers = (isset($CFG->maxusersperpage) ? $CFG->maxusersperpage : MAIL_MAXUSERS);

    $context = context_course::instance($message->course->id);

    if ($message->course->groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
        $groups = groups_get_user_groups($message->course->id, $message->sender()->id);
        if (count($groups[0]) == 0) {
            $mailoutput = $PAGE->get_renderer('local_mail');
            return array(
                'msgerror' => '',
                'html' => $mailoutput->recipientslist($participants)
            );
        } else {
            if (!in_array($groupid, $groups[0])) {
                $groupid = $groups[0][0];
            }
        }
    }

    list($select, $from, $where, $sort, $params) = local_mail_getsqlrecipients($message->course->id, $search, $groupid, $roleid);

    $matchcount = $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) $from $where", $params);

    $getid = function ($recipient) {
        return $recipient->id;
    };

    if ($matchcount <= $mailmaxusers) {
        $to = array_map($getid, $message->recipients(message::ROLE_TO));
        $cc = array_map($getid, $message->recipients(message::ROLE_CC));
        $bcc = array_map($getid, $message->recipients(message::ROLE_BCC));
        // List of users.
        $rs = $DB->get_recordset_sql("$select $from $where $sort", $params);
        foreach ($rs as $rec) {
            if (!array_key_exists($rec->id, $participants)) {
                $rec->role = '';
                if (in_array($rec->id, $to)) {
                    $rec->role = 'to';
                    array_push($recipients, $rec->id);
                } else if (in_array($rec->id, $cc)) {
                    $rec->role = 'cc';
                    array_push($recipients, $rec->id);
                } else if (in_array($rec->id, $bcc)) {
                    $rec->role = 'bcc';
                    array_push($recipients, $rec->id);
                }
                $participants[$rec->id] = $rec;
            }
        }
        $rs->close();
    } else {
        $participants = false;
    }
    $mailoutput = $PAGE->get_renderer('local_mail');
    return array(
        'msgerror' => '',
        'info' => $recipients,
        'html' => $mailoutput->recipientslist($participants)
    );
}

function local_mail_updaterecipients($message, $recipients, $roles) {
    global $DB;

    $context = context_course::instance($message->course->id);
    $groupid = 0;
    $severalseparategroups = false;

    if ($message->course->groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
        $groups = groups_get_user_groups($message->course->id, $message->sender()->id);
        if (count($groups[0]) == 0) {
            return array(
                'msgerror' => '',
                'info' => '',
                'html' => '',
                'redirect' => 'ok'
            );
        } else if (count($groups[0]) == 1) { // Only one group.
            $groupid = $groups[0][0];
        } else {
            $severalseparategroups = true; // Several groups.
        }
    }

    // Make sure recipients ids are integers.
    $recipients = clean_param_array($recipients, PARAM_INT);

    foreach ($recipients as $key => $recipid) {
        $roleids[$recipid] = (isset($roles[$key]) ? clean_param($roles[$key], PARAM_INT) : false);
    }

    $participants = array();
    list($select, $from, $where, $sort, $params) = local_mail_getsqlrecipients(
        $message->course->id,
        '',
        $groupid,
        0,
        implode(',', $recipients)
    );
    $rs = $DB->get_recordset_sql("$select $from $where $sort", $params);

    foreach ($rs as $rec) {
        if (!array_key_exists($rec->id, $participants)) { // Avoid duplicated users.
            if ($severalseparategroups) {
                $valid = false;
                foreach ($groups[0] as $group) {
                    $valid = $valid || groups_is_member($group, $rec->id);
                }
                if (!$valid) {
                    continue;
                }
            }
            $role = false;
            if ($roleids[$rec->id] === 0) {
                $role = 'to';
            } else if ($roleids[$rec->id] === 1) {
                $role = 'cc';
            } else if ($roleids[$rec->id] === 2) {
                $role = 'bcc';
            }
            $user = user::fetch($rec->id);
            if ($message->has_recipient($user)) {
                $message->remove_recipient($user);
            }
            if ($role == 'to') {
                $message->add_recipient($user, message::ROLE_TO);
            } else if ($role == 'cc') {
                $message->add_recipient($user, message::ROLE_CC);
            } else if ($role == 'bcc') {
                $message->add_recipient($user, message::ROLE_BCC);
            }
            $participants[$rec->id] = true;
        }
    }

    $rs->close();
    return array(
        'msgerror' => '',
        'info' => '',
        'html' => '',
        'redirect' => 'ok'
    );
}
