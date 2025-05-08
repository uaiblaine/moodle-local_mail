<?php
/*
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2025 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail;

/**
 * @covers \local_mail\message_search
 */
final class message_search_test extends test\testcase {
    public function test_count(): void {
        [$users, $messages] = self::generate_random_data(true);

        foreach ($this->messages_search_cases($users, $messages) as $search) {
            $expected = count(self::search_result($messages, $search));
            self::assertEquals($expected, $search->count(), $search);
        }
    }

    public function test_count_per_course(): void {
        [$users, $messages] = self::generate_random_data(true);

        foreach ($this->messages_search_cases($users, $messages) as $search) {
            $expected = [];
            foreach (self::search_result($messages, $search) as $message) {
                $expected[$message->courseid] = ($expected[$message->courseid] ?? 0) + 1;
            }
            self::assertEquals($expected, $search->count_per_course(), $search);
        }
    }

    public function test_count_per_label(): void {
        [$users, $messages] = self::generate_random_data(true);

        foreach ($this->messages_search_cases($users, $messages) as $search) {
            $expected = [];
            foreach (self::search_result($messages, $search) as $message) {
                foreach ($message->get_labels($search->user) as $label) {
                    if (!$search->label || $search->label->id == $label->id) {
                        $expected[$label->id][$message->courseid] = ($expected[$label->id][$message->courseid] ?? 0) + 1;
                    }
                }
            }
            self::assertEquals($expected, $search->count_per_label(), $search);
        }
    }

    public function test_get(): void {
        [$users, $messages] = self::generate_random_data(true);

        foreach ($this->messages_search_cases($users, $messages) as $search) {
            $expected = self::search_result($messages, $search);
            $result = $search->get(0, 0);
            self::assert_array_of_objects($expected, $result, $search);

            // Offset and limit.
            $expected = array_slice($expected, 5, 20, true);
            $result = $search->get(5, 20);
            self::assert_array_of_objects($expected, $result, $search);
        }
    }


    /**
     * Returns thee generated messages filtered by search parameters.
     *
     * @param message[] $messages Array of messages.
     * @param message_search $search Search parameters.
     * @return message[] Found messages, ordered from newer to older and indexed by ID.
     */
    protected static function search_result(array $messages, message_search $search): array {
        $courseids = $search->course ? [$search->course->id] : array_keys(course::get_by_user($search->user));

        $result = [];

        foreach (array_reverse($messages) as $message) {
            if (
                !in_array($message->courseid, $courseids) ||
                $search->user->id != $message->get_sender()->id && !$message->has_recipient($search->user) ||
                $search->user->id != $message->get_sender()->id && $message->draft ||
                $search->label && !$message->has_label($search->label) ||
                $search->draft !== null && $search->draft != $message->draft ||
                $search->roles && !in_array($message->role($search->user), $search->roles) ||
                $search->unread !== null && $message->unread($search->user) != $search->unread ||
                $search->starred !== null && $message->starred($search->user) != $search->starred ||
                !$search->deleted && $message->deleted($search->user) != message::NOT_DELETED ||
                $search->deleted && $message->deleted($search->user) != message::DELETED ||
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
                $pattern = message::normalize_text($search->content, FORMAT_PLAIN);
                if (\core_text::strpos(message::normalize_text($message->subject, FORMAT_PLAIN), $pattern) !== false) {
                    $found = true;
                }
                if (\core_text::strpos(message::normalize_text($message->content, FORMAT_PLAIN), $pattern) !== false) {
                    $found = true;
                }
                foreach ([$message->get_sender(), ...$message->get_recipients(message::ROLE_TO, message::ROLE_CC)] as $user) {
                    if (\core_text::strpos($user->fullname(), $pattern) !== false) {
                        $found = true;
                    }
                }
                if (!$found) {
                    continue;
                }
            }
            if ($search->sendername != '') {
                $pattern = message::normalize_text($search->sendername, FORMAT_PLAIN);
                if (\core_text::strpos($message->get_sender()->fullname(), $pattern) === false) {
                    continue;
                }
            }
            if ($search->recipientname != '') {
                $found = false;
                $pattern = message::normalize_text($search->recipientname, FORMAT_PLAIN);
                foreach ($message->get_recipients(message::ROLE_TO, message::ROLE_CC) as $user) {
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

        return $result;
    }
}
