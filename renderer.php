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

use local_mail\message;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/mail/locallib.php');

class local_mail_renderer extends plugin_renderer_base {

    private function custom_image_url($imagename, $component = 'moodle') {
        return $this->output->image_url($imagename, $component);
    }

    public function date(message $message, $viewmail = false) {
        $tz = core_date::get_user_timezone();
        $date = new DateTime('now', new DateTimeZone($tz));
        $offset = ($date->getOffset() - dst_offset_on(time(), $tz)) / (3600.0);
        $time = ($offset < 13) ? $message->time + $offset : $message->time;
        $now = ($offset < 13) ? time() + $offset : time();
        $daysago = floor($now / 86400) - floor($time / 86400);
        $yearsago = (int) date('Y', $now) - (int) date('Y', $time);
        $tooltip = userdate($time, get_string('strftimedatetime'));

        if ($viewmail) {
            $content = userdate($time, get_string('strftimedatetime'));
            $tooltip = '';
        } else if ($daysago == 0) {
            $content = userdate($time, get_string('strftimetime'));
        } else if ($yearsago == 0) {
            $content = userdate($time, get_string('strftimedateshort'));
        } else {
            $content = userdate($time, get_string('strftimedate'));
        }

        return html_writer::tag('span', s($content), array('class' => 'mail_date', 'title' => $tooltip));
    }

    public function recipientsform($courseid, $userid) {
        global $COURSE, $DB;

        $options = array();

        $owngroups = groups_get_user_groups($courseid, $userid);
        $attributes = array(
            'id' => 'local_mail_recipients_form',
            'class' => 'local_mail_form mail_hidden'
        );
        $content = html_writer::start_tag('div', $attributes);
        $context = context_course::instance($courseid);

        if (
            $COURSE->groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)
            && empty($owngroups[0])
        ) {
            return '';
        }
        $content .= html_writer::start_tag('div', array('class' => 'mail_recipients_toolbar'));

        // Roles.
        $roles = role_get_names($context);
        $userroles = local_mail_get_user_roleids($userid, $context);
        $mailsamerole = has_capability('local/mail:mailsamerole', $context);
        foreach ($roles as $key => $role) {
            $count = $DB->count_records_select(
                'role_assignments',
                "contextid = :contextid AND roleid = :roleid AND userid <> :userid",
                array('contextid' => $context->id, 'roleid' => $role->id, 'userid' => $userid)
            );
            if (($count && $mailsamerole)
                || ($count && !$mailsamerole && !in_array($role->id, $userroles))
            ) {
                $options[$key] = $role->localname;
            }
        }
        $text = get_string('role', 'moodle');
        $content .= html_writer::start_tag('span', array('class' => 'roleselector'));
        $content .= html_writer::label($text, 'local_mail_roles');
        $text = get_string('all', 'local_mail');
        $content .= html_writer::select(
            $options,
            'local_mail_roles',
            '',
            array('' => $text),
            array('id' => 'local_mail_recipients_roles', 'class' => '')
        );
        $content .= html_writer::end_tag('span');
        // Groups.
        $groups = groups_get_all_groups($courseid);
        if ($COURSE->groupmode == NOGROUPS || ($COURSE->groupmode == VISIBLEGROUPS && empty($groups))) {
            $text = get_string('allparticipants', 'moodle');
            $content .= html_writer::tag('span', $text, array('class' => 'groupselector groupname'));
        } else {
            if ($COURSE->groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $context)) {
                $options = array();
                foreach ($groups as $key => $group) {
                    $options[$key] = $group->name;
                }
                $text = get_string('group', 'moodle');
                $content .= html_writer::start_tag('span', array('class' => 'groupselector'));
                $content .= html_writer::label($text, 'local_mail_recipients_groups');
                $text = get_string('allparticipants', 'moodle');
                $content .= html_writer::select(
                    $options,
                    'local_mail_recipients_groups',
                    '',
                    array('' => $text),
                    array('id' => 'local_mail_recipients_groups', 'class' => '')
                );
                $content .= html_writer::end_tag('span');
            } else if (count($owngroups[0]) == 1) { // SEPARATEGROUPS and user in only one group.
                $text = get_string('group', 'moodle');
                $content .= html_writer::start_tag('span', array('class' => 'groupselector'));
                $content .= html_writer::label("$text: ", null);
                $content .= html_writer::tag('span', groups_get_group_name($owngroups[0][0]), array('class' => 'groupname'));
                $content .= html_writer::end_tag('span');
            } else if (count($owngroups[0]) > 1) { // SEPARATEGROUPS and user in several groups.
                $options = array();
                foreach ($owngroups[0] as $key => $group) {
                    $options[$group] = groups_get_group_name($group);
                }
                $text = get_string('group', 'moodle');
                $content .= html_writer::start_tag('span', array('class' => 'groupselector'));
                $content .= html_writer::label($text, 'local_mail_recipients_groups');
                $text = get_string('allparticipants', 'moodle');
                $content .= html_writer::select(
                    $options,
                    'local_mail_recipients_groups',
                    '',
                    array(key($options) => current($options)),
                    array('id' => 'local_mail_recipients_groups', 'class' => '')
                );
                $content .= html_writer::end_tag('span');
            }
        }
        $content .= html_writer::tag('div', '', array('class' => 'mail_separator'));
        // Search.
        $content .= html_writer::start_tag('div', array('class' => 'mail_recipients_search'));
        $attributes = array(
            'type'  => 'text',
            'name'  => 'recipients_search',
            'value' => '',
            'maxlength' => '100',
            'class' => 'mail_search'
        );
        $text = get_string('search', 'local_mail');
        $content .= html_writer::label($text, 'recipients_search');
        $content .= html_writer::empty_tag('input', $attributes);
        // Select all recipients.
        $content .= html_writer::start_tag('span', array('class' => 'mail_all_recipients_actions'));
        $attributes = array(
            'type' => 'button',
            'name' => "to_all",
            'value' => get_string('to', 'local_mail')
        );
        $content .= html_writer::empty_tag('input', $attributes);
        $attributes = array(
            'type' => 'button',
            'name' => "cc_all",
            'value' => get_string('cc', 'local_mail')
        );
        $content .= html_writer::empty_tag('input', $attributes);
        $attributes = array(
            'type' => 'button',
            'name' => "bcc_all",
            'value' => get_string('bcc', 'local_mail')
        );
        $content .= html_writer::empty_tag('input', $attributes);
        $attributes = array(
            'type' => 'image',
            'name' => "remove_all",
            'src' => $this->custom_image_url('t/delete'),
            'alt' => get_string('remove')
        );
        $content .= html_writer::empty_tag('input', $attributes);
        $content .= html_writer::end_tag('span');
        $content .= html_writer::end_tag('div');

        $content .= html_writer::end_tag('div');
        $content .= html_writer::tag('div', '', array('id' => 'local_mail_recipients_list', 'class' => 'mail_form_recipients'));
        $content .= html_writer::start_tag('div', array('class' => 'mail_recipients_loading'));
        $content .= $this->output->pix_icon('i/loading', get_string('actions'), 'moodle', array('class' => 'loading_icon'));
        $content .= html_writer::end_tag('div');
        $content .= html_writer::end_tag('div');
        return $content;
    }

    public function recipientslist($participants) {
        $content = '';
        if ($participants === false) {
            return get_string('toomanyrecipients', 'local_mail');
        } else if (empty($participants)) {
            return '';
        }
        foreach ($participants as $key => $participant) {
            $selected = ($participant->role == 'to' || $participant->role == 'cc' || $participant->role == 'bcc');
            if ($selected) {
                $rolestring = get_string('shortadd' . $participant->role, 'local_mail') . ':';
                $hidden = '';
                $recipselected = ' mail_recipient_selected';
            } else {
                $rolestring = '';
                $hidden = ' mail_hidden';
                $recipselected = '';
            }
            $content .= html_writer::start_tag('div', array('class' => 'mail_form_recipient' . $recipselected));
            $attributes = array(
                'class' => 'mail_form_recipient_role' . $hidden,
                'data-role-recipient' => $participant->id
            );
            $content .= html_writer::tag('span', $rolestring, $attributes);
            $content .= $this->output->user_picture($participant, array('link' => false, 'alttext' => false));
            $content .= html_writer::tag('span', fullname($participant), array('class' => 'mail_form_recipient_name'));
            $content .= html_writer::start_tag('span', array('class' => 'mail_recipient_actions'));
            $attributes = array(
                'type' => 'button',
                'name' => "to[{$participant->id}]",
                'value' => get_string('to', 'local_mail')
            );
            if ($selected) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] = 'mail_hidden';
            }
            $content .= html_writer::empty_tag('input', $attributes);
            $attributes = array(
                'type' => 'button',
                'name' => "cc[{$participant->id}]",
                'value' => get_string('shortaddcc', 'local_mail')
            );
            if ($selected) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] = 'mail_hidden';
            }
            $content .= html_writer::empty_tag('input', $attributes);
            $attributes = array(
                'type' => 'button',
                'name' => "bcc[{$participant->id}]",
                'value' => get_string('shortaddbcc', 'local_mail')
            );
            if ($selected) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] = 'mail_hidden';
            }
            $content .= html_writer::empty_tag('input', $attributes);
            $attributes = array(
                'type' => 'image',
                'name' => "remove[{$participant->id}]",
                'src' => $this->custom_image_url('t/delete'),
                'alt' => get_string('remove')
            );
            if (!$selected) {
                $attributes['class'] = 'mail_novisible';
                $attributes['disabled'] = 'disabled';
            }
            $content .= html_writer::empty_tag('input', $attributes);
            $content .= html_writer::end_tag('span');
            $content .= html_writer::end_tag('div');
        }
        $content .= html_writer::end_tag('div');
        return $content;
    }

    public function references($references, $reply = false) {
        $class = 'mail_references';
        $header = 'h3';
        if ($reply) {
            $class = 'mail_reply';
            $header = 'h2';
        }
        $output = $this->output->container_start($class);
        $output .= html_writer::tag($header, get_string('references', 'local_mail'));
        foreach ($references as $ref) {
            $output .= $this->mail($ref);
        }
        $output .= $this->output->container_end();
        return $output;
    }

    public function mail(message $message) {
        $output = '';
        $output .= $this->output->container_start('mail_header');
        $output .= $this->output->container_start('left');
        $output .= $this->output->user_picture((object) (array) $message->sender());
        $output .= $this->output->container_end();
        $output .= $this->output->container_start('mail_info');
        $output .= html_writer::link(
            new moodle_url(
                '/user/view.php',
                array(
                    'id' => $message->sender()->id,
                    'course' => $message->course->id
                )
            ),
            fullname($message->sender()),
            array('class' => 'user_from')
        );
        $output .= $this->date($message, true);

        $output .= html_writer::tag('div', '', array('class' => 'mail_recipients'));

        $output .= $this->output->container_end();
        $output .= $this->output->container_end();

        $output .= $this->output->container_start('mail_body');
        $output .= $this->output->container_start('mail_content');
        $output .= local_mail_format_content($message);
        $attachments = local_mail_attachments($message);
        if ($attachments) {
            $output .= $this->output->container_start('mail_attachments');
            if (count($attachments) > 1) {
                $text = get_string('attachnumber', 'local_mail', count($attachments));
                $output .= html_writer::tag('span', $text, array('class' => 'mail_attachment_text'));
                $urlparams = array(
                    't' => 'course',
                    'm' => $message->id,
                    'downloadall' => '1',
                );
                $downloadurl = new moodle_url('/local/mail/view.php', $urlparams);
                $text = get_string('downloadall', 'local_mail');
                $iconimage = $this->output->pix_icon('a/download_all', $text, 'moodle', array('class' => 'icon'));
                $output .= html_writer::start_div('mail_attachment_downloadall');
                $output .= html_writer::link($downloadurl, $iconimage);
                $text = get_string('downloadall', 'local_mail');
                $output .= html_writer::link($downloadurl, $text, array('class' => 'mail_downloadall_text'));
                $output .= html_writer::end_div();
            }
            foreach ($attachments as $attach) {
                $filename = $attach->get_filename();
                $filepath = $attach->get_filepath();
                $iconimage = $this->output->pix_icon(
                    file_file_icon($attach),
                    get_mimetype_description($attach),
                    'moodle',
                    array('class' => 'icon')
                );
                $path = '/' . $attach->get_contextid() . '/local_mail/message/' . $attach->get_itemid() . $filepath . $filename;
                $fullurl = moodle_url::make_file_url('/pluginfile.php', $path, true);
                $output .= html_writer::start_tag('div', array('class' => 'mail_attachment'));
                $output .= html_writer::link($fullurl, $iconimage);
                $output .= html_writer::link($fullurl, s($filename));
                $output .= html_writer::tag(
                    'span',
                    display_size($attach->get_filesize()),
                    array('class' => 'mail_attachment_size')
                );
                $output .= html_writer::end_tag('div');
            }
            $output .= $this->output->container_end();
        }
        $output .= $this->output->container_end();
        $output .= $this->output->container_end();
        return $output;
    }
}
