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

namespace local_mail;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/testcase.php');

/**
 * @covers \local_mail\message_search
 */
class message_search_test extends testcase {

    /* Constants used for generating random mail data. */
    private const NUM_COURSES = 5;
    private const NUM_USERS = 10;
    private const NUM_COURSES_PER_USER = 4;
    private const NUM_LABELS_PER_USER = 3;
    private const NUM_MESSAGES = 1000;
    private const FORWARD_FREQ = 0.2;
    private const DRAFT_FREQ = 0.2;
    private const RECIPIENT_FREQ = 0.2;
    private const UNREAD_FREQ = 0.2;
    private const STARRED_FREQ = 0.2;
    private const DELETED_FREQ = 0.2;
    private const DELETED_FOREVER_FREQ = 0.1;
    private const ATTACHMENT_FREQ = 0.2;
    private const INC_TIME_FREQ = 0.9;
    private const WORDS = [
        'Xiuxiuejar', 'Aixopluc', 'Caliu', 'Tendresa', 'Llibertat',
        'Moixaina', 'Amanyagar', 'Enraonar', 'Ginesta', 'Atzavara'
    ];

    public function test_count() {
        list($users, $messages) = self::generate_data();
        foreach (self::cases($users, $messages) as $search) {
            $expected = count(self::search_result($messages, $search));
            self::assertEquals($expected, $search->count(), $search);
        }
    }

    public function test_count_per_course() {
        list($users, $messages) = self::generate_data();
        foreach (self::cases($users, $messages) as $search) {
            $expected = [];
            foreach (self::search_result($messages, $search) as $message) {
                $expected[$message->course->id] = ($expected[$message->course->id] ?? 0) + 1;
            }
            self::assertEquals($expected, $search->count_per_course(), $search);
        }
    }

    public function test_count_per_label() {
        list($users, $messages) = self::generate_data();
        foreach (self::cases($users, $messages) as $search) {
            $expected = [];
            foreach (self::search_result($messages, $search) as $message) {
                foreach ($message->labels[$search->user->id] as $label) {
                    if (!$search->label || $search->label->id == $label->id) {
                        $expected[$label->id] = ($expected[$label->id] ?? 0) + 1;
                    }
                }
            }
            self::assertEquals($expected, $search->count_per_label(), $search);
        }
    }

    public function test_fetch() {
        list($users, $messages) = self::generate_data();
        foreach (self::cases($users, $messages) as $search) {
            foreach ([0, 10, 10000] as $offset) {
                foreach ([0, 10, 10000] as $limit) {
                    $expected = self::search_result($messages, $search, $offset, $limit);
                    $desc = $search . "\noffset: " . $offset . "\nlimit: " . $limit;
                    $result = $search->fetch($offset, $limit);
                    self::assertEquals($expected, $result, $desc);
                    self::assertEquals(array_keys($expected), array_keys($result), $desc);
                }
            }
        }
    }


    /**
     * Returns different search casses for the givem users and messages.
     *
     * @param user[] $users All users.
     * @param messages[] $messages All messages.
     * @return message_search[] Array of search parameters.
     */
    public static function cases(array $users, array $messages): array {
        $result = [];

        foreach ($users as $user) {

            // All messages.
            $result[] = new message_search($user);

            // Inbox.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $result[] = $search;

            // Unread.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $search->unread = true;
            $result[] = $search;

            // Starred.
            $search = new message_search($user);
            $search->starred = true;
            $result[] = $search;

            // Sent.
            $search = new message_search($user);
            $search->draft = false;
            $search->roles = [message::ROLE_FROM];
            $result[] = $search;

            // Drafts.
            $search = new message_search($user);
            $search->draft = true;
            $search->roles = [message::ROLE_FROM];
            $result[] = $search;

            // Trash.
            $search = new message_search($user);
            $search->deleted = true;
            $result[] = $search;

            // Course.
            foreach ($user->get_courses() as $course) {
                $search = new message_search($user);
                $search->course = $course;
                $result[] = $search;
            }

            // Label.
            foreach (label::fetch_by_user($user) as $label) {
                $search = new message_search($user);
                $search->label = $label;
                $result[] = $search;
            }

            // Content.
            $search = new message_search($user);
            $search->content = self::random_item($messages)->subject;
            $result[] = $search;

            // Sender name.
            $search = new message_search($user);
            $search->sendername = self::random_item($users)->fullname();
            $result[] = $search;

            // Recipient name.
            $search = new message_search($user);
            $search->recipientname = self::random_item($users)->fullname();
            $result[] = $search;

            // With files only.
            $search = new message_search($user);
            $search->withfilesonly = true;
            $result[] = $search;

            // Max time.
            $search = new message_search($user);
            $search->maxtime = self::random_item($messages)->time;
            $result[] = $search;

            // Start message.
            $search = new message_search($user);
            $search->start = self::random_item($messages);
            $result[] = $search;

            // Stop message.
            $search = new message_search($user);
            $search->stop = self::random_item($messages);
            $result[] = $search;

            // Reverse.
            $search = new message_search($user);
            $search->reverse = true;
            $result[] = $search;

            // Start and reverse.
            $search = new message_search($user);
            $search->start = self::random_item($messages);
            $search->reverse = true;
            $result[] = $search;

            // Stop and reverse.
            $search = new message_search($user);
            $search->stop = self::random_item($messages);
            $search->reverse = true;
            $result[] = $search;

            // Impossible search, always results in no messages.
            $search = new message_search($user);
            $search->roles = [message::ROLE_TO];
            $search->draft = true;
            $result[] = $search;
        }

        return $result;
    }

    /**
     * Generates random courses, users, labels and messages.
     *
     * @return array Array with users and messages.
     */
    public static function generate_data() {
        $generator = self::getDataGenerator();

        $courses = [];
        $users = [];
        $userlabels = [];
        $messages = [];
        $sentmessages = [];

        $time = make_timestamp(2021, 10, 11, 12, 0);

        for ($i = 0; $i < self::NUM_COURSES; $i++) {
            $courses[] = new course($generator->create_course());
        }

        for ($i = 0; $i < self::NUM_USERS; $i++) {
            $user = new user($generator->create_user());
            $users[] = $user;
            $userlabels[$user->id] = [];
            if ($i > 0) {
                foreach (self::random_items($courses, self::NUM_COURSES_PER_USER) as $course) {
                    $generator->enrol_user($user->id, $course->id, 'student');
                }
                foreach (self::random_items(self::WORDS, self::NUM_LABELS_PER_USER) as $name) {
                    $userlabels[$user->id][] = label::create($user, $name);
                }
            }
        }

        for ($i = 0; $i < self::NUM_MESSAGES; $i++) {
            if (self::random_bool(self::INC_TIME_FREQ)) {
                $time++;
            }

            if (self::random_bool(self::FORWARD_FREQ) && count($sentmessages) > 0) {
                $ref = self::random_item($sentmessages);
                $data = message_data::forward($ref, self::random_item($ref->users));
            } else {
                $data = message_data::new(self::random_item($courses), self::random_item($users));
            }

            if (self::random_bool(self::ATTACHMENT_FREQ)) {
                self::create_draft_file($data->draftitemid, 'file.txt', 'text');
            }

            $data->subject = self::random_item(self::WORDS);
            $data->content = self::random_item(self::WORDS);
            $data->time = $time;

            if ($data->course) {
                foreach ($users as $user) {
                    if ($user->id != $data->sender->id && self::random_bool(self::RECIPIENT_FREQ)) {
                        $rolename = self::random_item(['to', 'cc', 'bcc']);
                        $data->{$rolename}[] = $user;
                    }
                }
            }

            $message = message::create($data);

            $message->set_starred($data->sender, self::random_bool(self::STARRED_FREQ));
            $message->set_labels($data->sender, self::random_items($userlabels[$data->sender->id]));

            $messages[] = $message;

            if (self::random_bool(self::DRAFT_FREQ) || count($message->users) == 1) {
                continue;
            }

            $message->send($time);
            $sentmessages[] = $message;

            foreach ($message->users as $user) {
                $message->set_unread($user, self::random_bool(self::UNREAD_FREQ));
                if ($user->id != $data->sender->id) {
                    $message->set_starred($user, self::random_bool(self::STARRED_FREQ));
                    $message->set_labels($user, self::random_items($userlabels[$user->id]));
                }
                if (self::random_bool(self::DELETED_FREQ)) {
                    $message->set_deleted($user, message::DELETED);
                }
                if (self::random_bool(self::DELETED_FOREVER_FREQ)) {
                    $message->set_deleted($user, message::DELETED_FOREVER);
                }
            }
        }

        return [$users, $messages];
    }

    /**
     * Returns thee generated messages filtered by search parameters.
     *
     * @param message[] $messages Array of messages.
     * @param message_search $search Search parameters.
     * @param int $offset Skip this number of messages.
     * @param int $limit Limit the number of messages.
     * @return message[] Found messages, ordered from newer to older and indexed by ID.
     */
    protected static function search_result(array $messages, message_search $search, int $offset = 0, int $limit = 0): array {
        $courseids = $search->course ? [$search->course->id] : array_keys($search->user->get_courses());

        $result = [];

        foreach (array_reverse($messages) as $message) {
            if (
                !in_array($message->course->id, $courseids) ||
                $search->user->id != $message->sender()->id && !$message->has_recipient($search->user) ||
                $search->user->id != $message->sender()->id && $message->draft ||
                $search->label && !isset($message->labels[$search->label->user->id][$search->label->id]) ||
                $search->draft !== null && $search->draft != $message->draft ||
                $search->roles && !in_array($message->roles[$search->user->id], $search->roles) ||
                $search->unread !== null && $message->unread[$search->user->id] != $search->unread ||
                $search->starred !== null && $message->starred[$search->user->id] != $search->starred ||
                !$search->deleted && $message->deleted[$search->user->id] != message::NOT_DELETED ||
                $search->deleted && $message->deleted[$search->user->id] != message::DELETED ||
                $search->withfilesonly && $message->attachments == 0 ||
                $search->maxtime && $message->time > $search->maxtime ||
                $search->start && !$search->reverse && $message->id >= $search->start->id ||
                $search->start && $search->reverse && $message->id <= $search->start->id ||
                $search->stop && !$search->reverse && $message->id <= $search->stop->id ||
                $search->stop && $search->reverse && $message->id >= $search->stop->id
            ) {
                continue;
            }
            if ($search->content != '') {
                $found = false;
                $pattern = message::normalize_text($search->content);
                if (\core_text::strpos(message::normalize_text($message->subject), $pattern) !== false) {
                    $found = true;
                }
                if (\core_text::strpos(message::normalize_text($message->content), $pattern) !== false) {
                    $found = true;
                }
                foreach ($message->users as $user) {
                    if ($message->roles[$user->id] != message::ROLE_BCC) {
                        if (\core_text::strpos($user->fullname(), $pattern) !== false) {
                            $found = true;
                        }
                    }
                }
                if (!$found) {
                    continue;
                }
            }
            if ($search->sendername != '') {
                $pattern = message::normalize_text($search->sendername);
                if (\core_text::strpos($message->sender()->fullname(), $pattern) === false) {
                    continue;
                }
            }
            if ($search->recipientname != '') {
                $found = false;
                $pattern = message::normalize_text($search->recipientname);
                foreach ($message->recipients(message::ROLE_TO, message::ROLE_CC) as $user) {
                    if (\core_text::strpos($user->fullname(), $pattern) !== false) {
                        $found = true;
                    }
                }
                if (!$found) {
                    continue;
                }
            }

            $result[$message->id] = $message;
        }

        if ($search->reverse) {
            $result = array_reverse($result, true);
        }

        return array_slice($result, $offset, $limit ?: null, true);
    }
}
