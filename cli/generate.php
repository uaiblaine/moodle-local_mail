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

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/mail/message.class.php');

const EMOJIS = ['😀', '😛', '😱', '👍'];
const CONSONANTS = ['b', 'c', 'ç', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'q', 'r', 's', 't', 'v', 'x', 'y', 'z'];
const VOWELS = ['a', 'e', 'i', 'o', 'u'];

const EMOJI_FREQ = 0.05;
const COMMA_FREQ = 0.1;
const QUESTION_FREQ = 0.2;
const DASH_FREQ = 0.1;
const SYLLABES_PER_WORD_EX = 2;
const SYLLABES_PER_WORD_SD = 0.5;
const WORD_PER_SENTENCE_EX = 8;
const WORD_PER_SENTENCE_SD = 3;
const SENTENCES_PER_PARAGRAPH_EX = 5;
const SENTENCES_PER_PARAGRAPH_SD = 3;
const PARAGRAPHS_PER_MESSAGE_EX = 3;
const PARAGRAPHS_PER_MESSAGE_SD = 1;

const MESSAGES_PER_USER_PER_COURSE = 25;
const LABELS_PER_USER_EX = 3;
const LABELS_PER_USER_SD = 2;
const REPLY_FREQ = 0.7;
const FORWARD_FREQ = 0.1;
const DRAFT_FREQ = 0.1;
const TO_RECIPIENTS_EX = 1;
const TO_RECIPIENTS_SD = 2;
const CC_RECIPIENTS_EX = 0;
const CC_RECIPIENTS_SD = 2;
const BCC_RECIPIENTS_EX = -10;
const BCC_RECIPIENTS_SD = 10;
const ATTACHMENTS_EX = -1;
const ATTACHMENTS_SD = 1;
const REPLY_ALL_FREQ = 0.5;
const UNREAD_FREQ_EXP = 4;
const STARRED_FREQ = 0.2;
const DELETED_FREQ = 0.1;
const LABEL_FREQ = 0.1;

set_debugging(DEBUG_DEVELOPER, true);

function main() {
    global $CFG, $DB;

    raise_memory_limit(MEMORY_HUGE);

    $countperuser = MESSAGES_PER_USER_PER_COURSE;
    $countperuser = (int) cli_input("Messages per user per course? [$countperuser]", $countperuser);
    if ($countperuser <= 0) {
        cli_error('Invalid number of messages.');
    }
    cli_writeln('');

    $adminid = 0;
    $adminname = trim(cli_input("Name of a user that will receive all mail as BCC [none]", ''));
    if ($adminname) {
        $adminid = $DB->get_field('user', 'id', ['username' => $adminname, 'deleted' => 0, 'confirmed' => 1]);
        if (!$adminid) {
            cli_error('User not found.');
        }
    }
    cli_writeln('');

    $confirm = cli_input('ALL EXISTING MAIL DATA WILL BE DELETED! Type "OK" to continue.');
    if ($confirm != 'OK') {
        cli_error('Canceled.');
    }
    cli_writeln('');

    $starttime = time();

    $fs = get_file_storage();
    $select = 'id <> :guestid AND deleted = 0';
    $params = ['guestid' => $CFG->siteguest];
    $userids = array_keys($DB->get_records_select('user', $select, $params, 'id ASC', 'id'));
    $courses = get_courses('all', 'c.id ASC', 'c.id');
    unset($courses[SITEID]);
    $courseids = array_keys($courses);

    delete_messages($fs, $courseids);
    generate_user_labels($userids);
    foreach ($courseids as $courseid) {
        generate_course_messages($fs, $courseid, $adminid, $countperuser);
    }

    $seconds = (int) (time() - $starttime);
    cli_writeln("\n\nFinished in $seconds seconds.");
}

function delete_messages(file_storage $fs, array $courseids) {
    global $DB;

    foreach ($courseids as $courseid) {
        print_progress("Deleting course mail", count($courseids));

        local_mail_message::delete_course($courseid);
        $context = context_course::instance($courseid);
        $transaction = $DB->start_delegated_transaction();
        $fs->delete_area_files($context->id, 'local_mail');
        $transaction->allow_commit();
    }
}

function add_random_attachments(file_storage $fs, local_mail_message $message, int $count) {
    $context = context_course::instance($message->course()->id);

    $filenames = [];

    for ($i = 0; $i < $count; $i++) {
        $filename = '';
        while (!$filename || in_array($filename, $filenames)) {
            $filename = random_word() . '.html';
        }
        $filenames[] = $filename;
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'local_mail',
            'filearea' => 'message',
            'itemid' => $message->id(),
            'filepath' => '/',
            'filename' => $filename,
            'timecreated' => (int) $message->time(),
            'timemodified' => (int) $message->time(),
            'userid' => $message->sender()->id,
            'mimetype' => 'text/html',
        ];
        $fs->create_file_from_string($filerecord, random_content());
    }
}

function add_random_recipients(local_mail_message $message, array $userids): void {
    $counts = [
        'to' => random_count(1, TO_RECIPIENTS_EX, TO_RECIPIENTS_SD),
        'cc' => random_count(0, CC_RECIPIENTS_EX, CC_RECIPIENTS_SD),
        'bcc' => random_count(0, BCC_RECIPIENTS_EX, BCC_RECIPIENTS_SD)
    ];
    $counts['to'] = min($counts['to'], count($userids) - 1);
    $counts['cc'] = min($counts['cc'], count($userids) - 1 - $counts['to']);
    $counts['bcc'] = min($counts['bcc'], count($userids) - 1 - $counts['to'] - $counts['cc']);

    foreach ($counts as $role => $count) {
        while ($count > 0) {
            $userid = random_item($userids);
            if ($userid != $message->sender()->id && !$message->has_recipient($userid)) {
                $message->add_recipient($role, $userid);
                $count--;
            }
        }
    }
}

function generate_course_messages(file_storage $fs, int $courseid, int $adminid, int $countperuser): void {
    global $DB;

    $context = context_course::instance($courseid);
    $userids = array_keys(get_enrolled_users($context));
    if (count($userids) < 2) {
        return;
    }

    $count = $countperuser * count($userids);
    $endtime = time();
    $starttime = $endtime - 365 * 86400;
    $sentmessages = [];
    $transaction = null;

    for ($i = 0; $i < $count; $i++) {
        print_progress("Generating messages for course $courseid", $count);
        if ($i % 10 == 0) {
            $transaction?->allow_commit();
            $transaction = $DB->start_delegated_transaction();
        }
        $time = (int) (($endtime - $starttime) * $i / $count + $starttime);
        if ($i > 0 && random_bool(REPLY_FREQ)) {
            $message = generate_random_reply($fs, random_item($sentmessages), $time);
        } else if ($i > 0 && random_bool(FORWARD_FREQ / (1 - REPLY_FREQ))) {
            $message = generate_random_forward($fs, random_item($sentmessages), $userids, $time);
        } else {
            $message = generate_random_message($fs, $courseid, $userids, $adminid);
        }
        if ($i == 0 || !random_bool(DRAFT_FREQ)) {
            if ($adminid > 0 && $message->sender()->id != $adminid && !$message->has_recipient($adminid)) {
                $message->add_recipient('bcc', $adminid);
            }
            $message->send($time);
            $sentmessages[] = $message;
            // Only reply and forward recent messages.
            $countperweek = (int) ($count / 52);
            if (count($sentmessages) > $countperweek * 2) {
                $sentmessages = array_slice($sentmessages, $countperweek);
            }
        }
        set_random_unread($message, $starttime, $endtime);
        set_random_starred($message);
        set_random_labels($message);
        set_random_deleted($message);
    }

    $transaction?->allow_commit();
}

function generate_random_forward(file_storage $fs, local_mail_message $message, array $userids, int $time): local_mail_message {
    $users = array_merge($message->recipients('to'), $message->recipients('cc'));
    $user = random_item($users);
    $forward = $message->forward($user->id, $time);
    $attachments = random_count(0, ATTACHMENTS_EX, ATTACHMENTS_SD);
    $forward->save($forward->subject(), random_content(), FORMAT_HTML, $attachments, $time);
    add_random_attachments($fs, $message, $attachments);
    add_random_recipients($forward, $userids);
    return $forward;
}

function generate_random_message(file_storage $fs, int $courseid, array $userids, int $time): local_mail_message {
    $message = local_mail_message::create(random_item($userids), $courseid, $time);
    $attachments = random_count(0, ATTACHMENTS_EX, ATTACHMENTS_SD);
    $message->save(random_sentence(), random_content(), FORMAT_HTML, $attachments, $time);
    add_random_attachments($fs, $message, $attachments);
    add_random_recipients($message, $userids);
    return $message;
}

function generate_random_reply(file_storage $fs, local_mail_message $message, int $time): local_mail_message {
    $all = (rand() / getrandmax() < REPLY_ALL_FREQ);
    $users = array_merge($message->recipients('to'), $message->recipients('cc'));
    $user = random_item($users);
    $reply = $message->reply($user->id, $all, $time);
    $attachments = random_count(0, ATTACHMENTS_EX, ATTACHMENTS_SD);
    $reply->save($reply->subject(), random_content(), FORMAT_HTML, $attachments, $time);
    add_random_attachments($fs, $reply, $attachments);
    return $reply;
}

function generate_user_labels(array $userids) {
    global $DB;

    foreach ($userids as $userid) {
        print_progress('Generating user labels', count($userids));

        $transaction = $DB->start_delegated_transaction();
        foreach (local_mail_label::fetch_user($userid) as $label) {
            $label->delete();
        }
        $n = random_count(0, LABELS_PER_USER_EX, LABELS_PER_USER_SD);
        for ($i = 0; $i < $n; $i++) {
            $name = random_word(true);
            $color = random_item(local_mail_label::valid_colors());
            local_mail_label::create($userid, $name, $color);
        }
        $transaction->allow_commit();
    }
}

function print_progress(string $message = '', int $total = 0) {
    static $prevmessage = '';
    static $value = 0;
    static $printtime = 0;

    if ($message != $prevmessage) {
        if (strlen($prevmessage)) {
            cli_writeln('');
        }
        $prevmessage = $message;
        $value = 0;
        $printtime = 0;
    }

    $value++;

    if (strlen($message) && ($value == $total || time() - $printtime > 0.5)) {
        $message = "\r$message... ";
        if ($total > 0) {
            $message .= "$value/$total ";
        }
        cli_write($message);
        $printtime = time();
    }
}

function random_bool(float $truefreq): bool {
    return rand() / getrandmax() < $truefreq;
}

function random_content(): string {
    $s = '';
    $n = random_count(1, PARAGRAPHS_PER_MESSAGE_EX, PARAGRAPHS_PER_MESSAGE_SD);
    for ($i = 0; $i < $n; $i++) {
        $s .= "\n" . random_paragraph();
    }
    return $s;
}

function random_count(int $min, float $ex, float $sd): int {
    $x = rand() / getrandmax();
    $y = rand() / getrandmax();
    $r = sqrt(-2 * log($x)) * cos(2 * pi() * $y) * $sd + $ex;
    return max($min, (int) round($r));
}

function random_item(array $items): mixed {
    return array_values($items)[rand(0, count($items) - 1)];
}

function random_paragraph(): string {
    $s = '<p>' . random_sentence(true);
    $n = random_count(1, SENTENCES_PER_PARAGRAPH_EX, SENTENCES_PER_PARAGRAPH_SD) - 1;
    for ($i = 0; $i < $n; $i++) {
        $s .= ' ' . random_sentence(true);
    }
    $s .= '</p>';
    return $s;
}

function random_sentence($period=false): string {
    if (random_bool(EMOJI_FREQ)) {
        return random_item(EMOJIS);
    }

    $s = random_word(true);
    $n = random_count(1, WORD_PER_SENTENCE_EX, WORD_PER_SENTENCE_SD) - 1;

    for ($i = 0; $i < $n; $i++) {
        if (rand() / getrandmax() < COMMA_FREQ) {
            $s .= ',';
        }
        $s .= ' ' . random_word();
    }

    if ($period) {
        if (random_bool(QUESTION_FREQ)) {
            $s .= '?';
        } else {
            $s .= '.';
        }
    }

    return $s;
}

function random_word($capitalize=false): string {
    $s = '';
    $n = random_count(1, SYLLABES_PER_WORD_EX, SYLLABES_PER_WORD_SD);

    for ($i = 0; $i < $n; $i++) {
        $c = random_item(CONSONANTS);
        if ($i == 0 && $capitalize) {
            $c = mb_strtoupper($c);
        }
        $s .= $c . random_item(VOWELS);
        if ($i < $n - 1 && random_bool(DASH_FREQ)) {
            $s .= '-';
        }
    }

    return $s;
}

function set_random_deleted(local_mail_message $message): void {
    if (!$message->draft()) {
        $message->set_deleted($message->sender()->id, random_bool(DELETED_FREQ));
        foreach ($message->recipients() as $user) {
            $message->set_deleted($user->id, random_bool(DELETED_FREQ));
        }
    }
}

function set_random_labels(local_mail_message $message): void {
    $users = array_merge([$message->sender()], $message->recipients());
    foreach ($users as $user) {
        if (!$message->draft() || $user->id == $message->sender()->id) {
            $labels = local_mail_label::fetch_user($user->id);
            foreach ($labels as $label) {
                if (random_bool(LABEL_FREQ)) {
                    $message->add_label($label);
                }
            }
        }
    }
}

function set_random_starred(local_mail_message $message): void {
    $message->set_starred($message->sender()->id, random_bool(STARRED_FREQ));
    if (!$message->draft()) {
        foreach ($message->recipients() as $user) {
            $message->set_starred($user->id, random_bool(STARRED_FREQ));
        }
    }
}

function set_random_unread(local_mail_message $message, int $starttime, int $endtime): void {
    if (!$message->draft()) {
        $freq = pow(($message->time() - $starttime) / ($endtime - $starttime) , UNREAD_FREQ_EXP);
        foreach ($message->recipients() as $user) {
            $message->set_unread($user->id, random_bool($freq));
        }
    }
}

main();
