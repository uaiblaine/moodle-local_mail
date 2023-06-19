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

use \local_mail\label;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot.'/group/lib.php');

define('MAIL_PAGESIZE', 10);
define('LOCAL_MAIL_MAXFILES', 5);
define('LOCAL_MAIL_MAXBYTES', 1048576);

function local_mail_is_installed() {
    global $CFG;

    $plugin = new stdClass;
    include("$CFG->dirroot/local/mail/version.php");

    $version = get_config('local_mail', 'version');

    return $version == $plugin->version;
}

function local_mail_attachments($message) {
    $context = $message->course->context();
    $fs = get_file_storage();
    return $fs->get_area_files($context->id, 'local_mail', 'message',
                               $message->id, 'filename', false);
}

function local_mail_format_content($message) {
    $context = $message->course->context();
    $content = file_rewrite_pluginfile_urls($message->content, 'pluginfile.php', $context->id,
                                            'local_mail', 'message', $message->id);
    return format_text($content, $message->format);
}

function local_mail_setup_page($course, $url) {
    global $PAGE;

    $PAGE->set_url($url);
    $PAGE->set_pagelayout('incourse');
    $PAGE->requires->css('/local/mail/styles.css');

    $PAGE->set_title(get_string('pluginname', 'local_mail'));
    $PAGE->set_context(context_course::instance($course->id));

    $PAGE->navbar->add(get_string('pluginname', 'local_mail'));

    $navtitle = null;
    $navurl = null;

    if ($url->compare(new moodle_url('/local/mail/compose.php'), URL_MATCH_BASE)) {
        $navtitle = get_string('compose', 'local_mail');
    } else if ($url->compare(new moodle_url('/local/mail/create.php'), URL_MATCH_BASE)) {
        $navtitle = get_string('compose', 'local_mail');
    } else if ($url->compare(new moodle_url('/local/mail/preferences.php'), URL_MATCH_BASE)) {
        $navtitle = get_string('preferences');
    } else if ($url->compare(new moodle_url('/local/mail/view.php'), URL_MATCH_BASE)) {
        $type = $url->param('t');
        $navurl = new moodle_url('/local/mail/view.php', ['t' => $type]);
        if (in_array($type, ['inbox', 'starred', 'drafts', 'trash'])) {
            $navtitle = get_string($url->param('t'), 'local_mail');
        } else if ($type == 'sent') {
            $navtitle = get_string('sentmail', 'local_mail');
        } else if ($type == 'course') {
            $navurl->param('c', $url->param('c'));
            $navtitle = $course->shortname;
        } else if ($type == 'label') {
            $label = label::fetch($url->param('l'));
            if ($label) {
                $navurl->param('l', $label->id);
                $navtitle = $label->name;
            }
        }
    }

    if ($navtitle !== null) {
        $PAGE->set_title(get_string('pluginname', 'local_mail') . ': ' . $navtitle);
        $PAGE->navbar->add($navtitle, $navurl);
    }
}

function local_mail_send_notifications($message) {
    global $CFG, $SITE;

    $plaindata = new stdClass;
    $htmldata = new stdClass;

    // Send the mail now!
    foreach ($message->recipients() as $userto) {

        $attachment = '';

        if ($message->attachments) {
            $attachment = get_string('hasattachments', 'local_mail');
        }
        $plaindata->user = $message->sender()->fullname();
        $plaindata->subject = $message->subject . ' ' . $attachment;
        $plaindata->content = $message->content;

        $htmldata->user = $message->sender()->fullname();
        $htmldata->subject = $message->subject . ' ' . $attachment;
        $url = new moodle_url('/local/mail/view.php', array('t' => 'inbox', 'm' => $message->id));
        $htmldata->url = $url->out(false);
        $htmldata->content = format_text($message->content, $message->format);

        $fullplainmessage = format_text_email(get_string('notificationbody', 'local_mail', $plaindata), $message->format);

        $eventdata = new \core\message\message();
        if ($CFG->branch >= 32) {
            $eventdata->courseid      = $message->course->id;
        }
        $eventdata->component         = 'local_mail';
        $eventdata->name              = 'mail';
        $eventdata->userfrom          = $message->sender();
        $eventdata->userto            = core_user::get_user($userto->id);
        $eventdata->subject           = get_string('notificationsubject', 'local_mail', $SITE->shortname);
        $eventdata->fullmessage       = $fullplainmessage;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = get_string('notificationbodyhtml', 'local_mail', $htmldata);
        $eventdata->notification      = 1;

        $smallmessagestrings = new stdClass();
        $smallmessagestrings->user = $message->sender()->fullname();
        $smallmessagestrings->message = $message->subject;
        $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'local_mail', $smallmessagestrings);

        $url = new moodle_url('/local/mail/view.php', array('t' => 'inbox', 'm' => $message->id));
        $eventdata->contexturl = $url->out(false);
        $eventdata->contexturlname = $message->subject;

        $mailresult = message_send($eventdata);
        if (!$mailresult) {
            mtrace("Error: local/mail/locallib.php local_mail_send_mail(): Could not send out mail for id {$message->id} " .
                    "to user {$message->sender()->id} ($userto->email) .. not trying again.");
        } else if (get_user_preferences('local_mail_markasread', false, $userto)) {
            // Set message as read depending on user preferences.
            $message->set_unread(user::fetch($userto->id), false);
        }
    }
}

function local_mail_get_my_courses() {
    static $courses = null;

    if ($courses === null) {
        $courses = enrol_get_my_courses();

        foreach ($courses as $course) {
            $context = context_course::instance($course->id, IGNORE_MISSING);
            if (!has_capability('local/mail:usemail', $context)) {
                unset($courses[$course->id]);
            }
        }
    }

    return $courses;
}

function local_mail_valid_recipient($recipient) {
    global $COURSE, $USER;

    if (!$recipient || $recipient == $USER->id) {
        return false;
    }

    $context = context_course::instance($COURSE->id);

    if (!is_enrolled($context, $recipient)) {
        return false;
    }

    if ($COURSE->groupmode == SEPARATEGROUPS &&
            !has_capability('moodle/site:accessallgroups', $context)) {
        $ugroups = groups_get_all_groups($COURSE->id, $USER->id,
                                         $COURSE->defaultgroupingid, 'g.id');
        $rgroups = groups_get_all_groups($COURSE->id, $recipient,
                                         $COURSE->defaultgroupingid, 'g.id');
        if (!array_intersect(array_keys($ugroups), array_keys($rgroups))) {
            return false;
        }
    }

    return true;
}

function local_mail_add_recipients($message, $recipients, $role) {
    global $DB;

    $context = $message->course->context();
    $groupid = 0;
    $severalseparategroups = false;
    $roles = array('to', 'cc', 'bcc');
    $role = ($role >= 0 && $role < 3) ? $role : 0;

    if ($message->course->groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
        $groups = groups_get_user_groups($message->course->id, $message->sender()->id);
        if (count($groups[0]) == 0) {
            return;
        } else if (count($groups[0]) == 1) {// Only one group.
            $groupid = $groups[0][0];
        } else {
            $severalseparategroups = true;// Several groups.
        }
    }

    // Make sure recipients ids are integers.
    $recipients = clean_param_array($recipients, PARAM_INT);

    $participants = array();
    list($select, $from, $where, $sort, $params) = local_mail_getsqlrecipients($message->course->id, '',
                                                                               $groupid, 0, implode(',', $recipients));
    $rs = $DB->get_recordset_sql("$select $from $where $sort", $params);

    foreach ($rs as $rec) {
        if (!array_key_exists($rec->id, $participants)) {// Avoid duplicated users.
            if ($severalseparategroups) {
                $valid = false;
                foreach ($groups[0] as $group) {
                    $valid = $valid || groups_is_member($group, $rec->id);
                }
                if (!$valid) {
                    continue;
                }
            }
            $message->add_recipient($roles[$role], $rec->id);
            $participants[$rec->id] = true;
        }
    }

    $rs->close();
}

function local_mail_getsqlrecipients($courseid, $search, $groupid, $roleid, $recipients = false) {
    global $CFG, $USER, $DB;

    $context = context_course::instance($courseid);

    $mailsamerole = has_capability('local/mail:mailsamerole', $context);

    list($esql, $params) = get_enrolled_sql($context, null, $groupid, true);
    $joins = array("FROM {user} u");
    $wheres = array();

    $userfields = \core_user\fields::for_userpic();
    $userfields->including(...array('username', 'city', 'country', 'lang', 'timezone', 'maildisplay'));
    $mainuserfields = $userfields->get_sql('u', false, '', '', false)->selects;

    $extrafields = \core_user\fields::for_identity($context, false)->excluding(...array('id', 'firstname', 'lastname'));
    $extrasql = $extrafields->get_sql('u')->selects;
    $select = "SELECT $mainuserfields$extrasql";
    $joins[] = "JOIN ($esql) e ON e.id = u.id";

    // Performance hacks - we preload user contexts together with accounts.
    $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
    $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)";
    $params['contextlevel'] = CONTEXT_USER;
    $select .= $ccselect;
    $joins[] = $ccjoin;

    if (!$mailsamerole) {
        $userroleids = local_mail_get_user_roleids($USER->id, $context);
        list($relctxsql, $reldctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relctx');
        list($samerolesql, $sameroleparams) = $DB->get_in_or_equal($userroleids, SQL_PARAMS_NAMED, 'samerole' , false);
        $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid $samerolesql AND contextid $relctxsql)";
        $params = array_merge($params, array('roleid' => $roleid), $sameroleparams, $reldctxparams);
    }

    if ($roleid) {
        // We want to query both the current context and parent contexts.
        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true),
                                                                       SQL_PARAMS_NAMED, 'relatedctx');
        $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid = :roleid AND contextid $relatedctxsql)";
        $params = array_merge($params, array('roleid' => $roleid), $relatedctxparams);
    }

    $from = implode("\n", $joins);

    if (!empty($search)) {
        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $wheres[] = "(". $DB->sql_like($fullname, ':search1', false, false) .") ";
        $params['search1'] = "%$search%";
    }

    $from = implode("\n", $joins);

    $wheres[] = 'u.id <> :guestid AND u.deleted = 0 AND u.confirmed = 1 AND u.id <> :userid';
    $params['userid'] = $USER->id;
    $params['guestid'] = $CFG->siteguest;

    if ($recipients) {
        $wheres[] = 'u.id IN ('.preg_replace('/^,|,$/', '', $recipients).')';
    }

    $where = "WHERE " . implode(" AND ", $wheres);

    $sort = 'ORDER BY u.lastname ASC, u.firstname ASC';

    return array($select, $from, $where, $sort, $params);
}

function local_mail_get_user_roleids($userid, $context) {
    $roles = get_user_roles($context, $userid);

    return array_map(
        function ($role) {
            return $role->roleid;
        }, $roles);
}

/**
 * Returns the script tags needed for a svelte entry script.
 *
 * CSS files are included in the head.
 *
 * @param $file Source file name, e.g. "src/view.ts"
 */
function local_mail_svelte_script(string $file): string {
    global $CFG, $PAGE;

    $html = '';

    if (!empty($CFG->local_mail_devserver)) {
        $jsurl = $CFG->local_mail_devserver . '/' . $file;
    } else {
        $manifestpath = $CFG->dirroot . '/local/mail/svelte/dist/manifest.json';
        $manifest = json_decode(file_get_contents($manifestpath), true);
        if (!$manifest) {
            throw new coding_exception('local_mail: "svelte/dist/manifest.json" not found');
        }
        if (empty($manifest[$file])) {
            throw new coding_exception('local_mail: invalid svelte script name "' . $file . '"');
        }
        $jsurl = $CFG->wwwroot . '/local/mail/svelte/dist/' . $manifest[$file]['file'];
        $chunks = [$file];
        $cssurls = [];
        while ($file = array_pop($chunks)) {
            foreach ($manifest[$file]['imports'] ?? [] as $jsfile) {
                $chunks[] = $jsfile;
            }
            foreach ($manifest[$file]['css'] ?? [] as $cssfile) {
                $cssurls[] = new moodle_url('/local/mail/svelte/dist/' . $cssfile);
            }
        }
        foreach ($cssurls as $cssurl) {
            if ($PAGE->requires->is_head_done()) {
                // Head already written, add CSS using javascript.
                $html .= html_writer::script('(function() {
                    var doc = document.getElementsByTagName("head")[0];
                    var link = document.createElement("link");
                    link.rel = "stylesheet";
                    link.href = "' . $cssurl->out(false) . '";
                    doc.appendChild(link);
                })();');
            } else {
                $PAGE->requires->css($cssurl);
            }
        }
    }

     $html .= html_writer::tag('script', '', ['type' => 'module', 'src' => $jsurl]);

     return $html;
}
