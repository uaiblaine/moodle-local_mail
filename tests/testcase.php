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

    public function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Asserts stored attachments.
     *
     * @param string[] $expected Files: filename => content.
     * @param message $message Message.
     * @param string $component Component.
     * @param string $filearea File area.
     * @param string $itemid Item ID.
     * @throws ExpectationFailedException
     */
    protected static function assert_attachments(array $expected, message $message) {
        $fs = get_file_storage();
        $contextid = $message->course->context()->id;
        $files = $fs->get_area_files($contextid, 'local_mail', 'message', $message->id, 'id', false);
        $actual = [];
        foreach ($files as $file) {
            $actual[$file->get_filename()] = $file->get_content();
        }
        self::assertEquals($expected, $actual);
    }

    /**
     * Asserts stored files.
     *
     * @param string[] $expected Files: filename => content.
     * @param int $userid Draft item ID.
     * @throws ExpectationFailedException
     */
    protected static function assert_draft_files(array $expected, int $draftitemid) {
        global $USER;

        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $actual = [];
        foreach ($fs->get_area_files($context->id, 'user', 'draft', $draftitemid, 'id', false) as $file) {
            $actual[$file->get_filename()] = $file->get_content();
        }
        self::assertEquals($expected, $actual);
    }


    /**
     * Asserts that a message is stored correctly in the database.
     *
     * @param message $message Message.
     * @throws ExpectationFailedException
     */
    protected static function assert_message(message $message): void {
        self::assert_record_data('messages', [
            'id' => $message->id,
        ], [
            'courseid' => $message->course->id ?? 0,
            'subject' => $message->subject,
            'content' => $message->content,
            'format' => $message->format,
            'attachments' => $message->attachments,
            'draft' => (int) $message->draft,
            'time' => $message->time,
            'normalizedsubject' => message::normalize_text($message->subject),
            'normalizedcontent' => message::normalize_text($message->content),
        ]);

        $numusers = count($message->recipients()) + 1;
        self::assert_record_count($numusers, 'message_users', ['messageid' => $message->id]);

        $numlabels = count($message->labels($message->sender()));
        foreach ($message->recipients() as $user) {
            $numlabels += count($message->labels($user));
        }
        self::assert_record_count($numlabels, 'message_labels', ['messageid' => $message->id]);

        foreach ([$message->sender(), ...$message->recipients()] as $user) {
            $data = [
                'courseid' => $message->course->id ?? 0,
                'draft' => (int) $message->draft,
                'time' => $message->time,
                'role' => $message->role($user),
                'unread' => (int) $message->unread($user),
                'starred' => (int) $message->starred($user),
                'deleted' => $message->deleted($user),
            ];
            self::assert_record_data('message_users', [
                'messageid' => $message->id,
                'userid' => $user->id
            ], $data);
            foreach ($message->labels($user) as $label) {
                self::assert_record_data('message_labels', [
                    'messageid' => $message->id,
                    'labelid' => $label->id,
                ], $data);
            }
        }
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
     * Creates a draft stored file.
     *
     * @param int $draftitemid Draft item ID.
     * @param string $filename File name.
     * @param string $content Content of the file.
     * @return stored_file
     */
    protected static function create_draft_file(int $draftitemid, string $filename, string $content): \stored_file {
        global $USER;

        $fs = get_file_storage();

        $context = \context_user::instance($USER->id);

        $record = [
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ];

        return $fs->create_file_from_string($record, $content);
    }

    /**
     * Deletes draft stored files.
     *
     * @param int $draftitemid Draft item ID.
     */
    protected static function delete_draft_files(int $draftitemid) {
        global $USER;

        $fs = get_file_storage();

        $context = \context_user::instance($USER->id);

        return $fs->delete_area_files($context->id, 'user', 'draft', $draftitemid);
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
    protected static function random_items(array $items, int $min = 0, int $max = 0): array {
        assert($min >= 0 && $max >= 0 && (!$max || $max >= $min));
        $items = array_values($items);
        shuffle($items);
        $min = min($min, count($items) - 1);
        $max = $max ?: count($items) - 1;
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
}
