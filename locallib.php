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

use \local_mail\user;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/filelib.php');

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
