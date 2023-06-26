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

/**
 * @covers \local_mail\search
 */
class search_test extends testcase {

    public function test_count() {
        list($users, $messages) = self::generate_search_data();
        foreach (self::search_cases($users, $messages) as $search) {
            $expected = count(self::search_result($messages, $search));
            self::assertEquals($expected, $search->count(), $search);
        }
    }

    public function test_count_per_course() {
        list($users, $messages) = self::generate_search_data();
        foreach (self::search_cases($users, $messages) as $search) {
            $expected = [];
            foreach (self::search_result($messages, $search) as $message) {
                $expected[$message->course->id] = ($expected[$message->course->id] ?? 0) + 1;
            }
            self::assertEquals($expected, $search->count_per_course(), $search);
        }
    }

    public function test_count_per_label() {
        list($users, $messages) = self::generate_search_data();
        foreach (self::search_cases($users, $messages) as $search) {
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
        list($users, $messages) = self::generate_search_data();
        foreach (self::search_cases($users, $messages) as $search) {
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
     * Returns thee generated messages filtered by search parameters.
     *
     * @param message[] $message Array of messages.
     * @param search $search Search parameters.
     * @param int $offset Skip this number of messages.
     * @param int $limit Limit the number of messages.
     * @return message[] Found messages, ordered from newer to older and indexed by ID.
     */
    protected static function search_result(array $messages, search $search, int $offset = 0, int $limit = 0): array {
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
