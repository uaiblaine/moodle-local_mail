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

abstract class testcase extends \advanced_testcase {

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

    public function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Asserts that the table contains this number of records matching the conditions.
     *
     * @param int $expected Expected number of rows.
     * @param string $table Table name without the "local_mail_" prefix.
     * @param mixed[] $conditions Array of field => value.
     * @throws ExpectationFailedException
     */
    protected static function assert_record_count(int $expected, string $table, array $conditions = []) {
        global $DB;

        $actual = $DB->count_records('local_mail_' . $table, $conditions);

        self::assertEquals($expected, $actual);
    }

    /**
     * Asserts that the table contains a record matching the givem conditions and data.
     *
     * @param string $table Table name without the "local_mail_" prefix.
     * @param mixed[] $conditions Array of field => value.
     * @param mixed[] $data Array of field => value.
     * @throws ExpectationFailedException
     */
    protected static function assert_record_data($table, array $conditions, array $data): void {
        global $DB;

        $records = $DB->get_records('local_mail_' . $table, $conditions);

        self::assertCount(1, $records);

        foreach ($records as $record) {
            foreach ($data as $field => $value) {
                self::assertEquals($value, $record->$field);
            }
        }
    }

    /**
     * Creates a stored file of an attachment.
     *
     * @param int $courseid ID of the course.
     * @param int $messageid ID of the message.
     * @param string $filename File name.
     * @param string $content Content of the file.
     * @param int $time Creation time.
     * @return stored_file
     */
    protected static function create_attachment(int $courseid, int $messageid, string $filename, string $content): \stored_file {
        $fs = get_file_storage();

        $context = \context_course::instance($courseid);

        $record = [
            'contextid' => $context->id,
            'component' => 'local_mail',
            'filearea' => 'message',
            'itemid' => $messageid,
            'filepath' => '/',
            'filename' => $filename,
        ];

        return $fs->create_file_from_string($record, $content);
    }

    /**
     * Generates random courses, users, labels and messages.
     *
     * @return array Array with users and messages.
     */
    protected static function generate_search_data() {
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
                $sender = self::random_item($ref->users);
                $message = $ref->forward($sender, $time);
            } else {
                $sender = self::random_item($users);
                $message = message::create(self::random_item($courses), $sender, $time);
            }
            $messages[] = $message;

            if (self::random_bool(self::ATTACHMENT_FREQ)) {
                self::create_attachment($message->course->id, $message->id, 'file.txt', 'text');
            }

            $subject = self::random_item(self::WORDS);
            $content = self::random_item(self::WORDS);
            $message->update($subject, $content, FORMAT_HTML, $time);

            $message->set_starred($sender, self::random_bool(self::STARRED_FREQ));
            $message->set_labels($sender, self::random_items($userlabels[$sender->id]));

            foreach ($users as $user) {
                if ($user->id != $sender->id && self::random_bool(self::RECIPIENT_FREQ)) {
                    $role = self::random_item([message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC]);
                    $message->add_recipient($user, $role);
                }
            }

            if (self::random_bool(self::DRAFT_FREQ) || count($message->users) == 1) {
                continue;
            }

            $message->send($time);

            $sentmessages[] = $message;

            foreach ($message->users as $user) {
                $message->set_unread($user, self::random_bool(self::UNREAD_FREQ));
                if ($user->id != $sender->id) {
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
     * Insert multiple records to the table and return its IDs.
     *
     * @param string $table Table name without the "local_mail_" prefix.
     * @param string[] $fields Fields to insert.
     * @param mixed[] $records,... Records to insert.
     * @return int[] Record IDs.
     */
    protected static function insert_records(string $table, array $fields, array ...$records): array {
        global $DB;

        $ids = [];

        foreach ($records as $record) {
            $record = array_combine($fields, $record);
            $ids[] = $DB->insert_record('local_mail_' . $table, (object) $record);
        }

        return $ids;
    }

    /**
     * Returns a random item of an array.
     *
     * @param mixed[] $items Array of items
     * @return ?mixed
     */
    protected static function random_item(array $items): mixed {
        $items = array_values($items);
        return $items ? $items[rand(0, count($items) - 1)] : null;
    }

    /**
     * Returns random items of an array.
     *
     * @param mixed[] $items Array of items.
     * @param int $min Minimum number of items.
     * @param int $max Maximum number of items.
     * @return mixed[]
     */
    protected static function random_items(array $items, int $min = 0, int $max = PHP_INT_MAX): array {
        assert($min >= 0);
        $items = array_values($items);
        shuffle($items);
        $max = min(max($min, $max), count($items) - 1);
        return $items ? array_slice($items, 0, rand($min, $max)) : [];
    }

    /**
     * Returns a random boolean.
     *
     * @param float $truefreq Frequency of return true values.
     * @return bool
     */
    protected static function random_bool(float $truefreq): bool {
        return rand() / getrandmax() < $truefreq;
    }


    /**
     * Returns different search casses for the givem users and messages.
     *
     * @param user[] $users All users.
     * @param messages[] $messages All messages.
     * @return search[] Array of search parameters.
     */
    protected static function search_cases(array $users, array $messages): array {
        $result = [];

        foreach ($users as $user) {

            // All messages.
            $result[] = new search($user);

            // Inbox.
            $search = new search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $result[] = $search;

            // Unread.
            $search = new search($user);
            $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
            $search->unread = true;
            $result[] = $search;

            // Starred.
            $search = new search($user);
            $search->starred = true;
            $result[] = $search;

            // Sent.
            $search = new search($user);
            $search->draft = false;
            $search->roles = [message::ROLE_FROM];
            $result[] = $search;

            // Drafts.
            $search = new search($user);
            $search->draft = true;
            $search->roles = [message::ROLE_FROM];
            $result[] = $search;

            // Trash.
            $search = new search($user);
            $search->deleted = true;
            $result[] = $search;

            // Course.
            foreach ($user->get_courses() as $course) {
                $search = new search($user);
                $search->course = $course;
                $result[] = $search;
            }

            // Label.
            foreach (label::fetch_by_user($user) as $label) {
                $search = new search($user);
                $search->label = $label;
                $result[] = $search;
            }

            // Content.
            $search = new search($user);
            $search->content = self::random_item($messages)->subject;
            $result[] = $search;

            // Sender name.
            $search = new search($user);
            $search->sendername = self::random_item($users)->fullname();
            $result[] = $search;

            // Recipient name.
            $search = new search($user);
            $search->recipientname = self::random_item($users)->fullname();
            $result[] = $search;

            // With files only.
            $search = new search($user);
            $search->withfilesonly = true;
            $result[] = $search;

            // Max time.
            $search = new search($user);
            $search->maxtime = self::random_item($messages)->time;
            $result[] = $search;

            // Start message.
            $search = new search($user);
            $search->start = self::random_item($messages);
            $result[] = $search;

            // Stop message.
            $search = new search($user);
            $search->stop = self::random_item($messages);
            $result[] = $search;

            // Reverse.
            $search = new search($user);
            $search->reverse = true;
            $result[] = $search;

            // Start and reverse.
            $search = new search($user);
            $search->start = self::random_item($messages);
            $search->reverse = true;
            $result[] = $search;

            // Stop and reverse.
            $search = new search($user);
            $search->stop = self::random_item($messages);
            $search->reverse = true;
            $result[] = $search;

            // Impossible search, always results in no messages.
            $search = new search($user);
            $search->roles = [message::ROLE_TO];
            $search->draft = true;
            $result[] = $search;
        }

        return $result;
    }
}
