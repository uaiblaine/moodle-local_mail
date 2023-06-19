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

class message {

    // Deleted stataus constants.
    const NOT_DELETED = 0;
    const DELETED = 1;
    const DELETED_FOREVER = 2;

    // Role constants.
    const ROLE_FROM = 1;
    const ROLE_TO = 2;
    const ROLE_CC = 3;
    const ROLE_BCC = 4;

    /** @var int Message ID. */
    public int $id;

    /** @var course Course. */
    public course $course;

    /** @var string Subject. */
    public string $subject;

    /** @var string Body content. */
    public string $content;

    /** @var int Body format. */
    public int $format;

    /** @var int Number of attachments. */
    public int $attachments = 0;

    /** @var bool Draft status. */
    public bool $draft;

    /** @var int Timestamp. */
    public int $time;

    /** @var user[] Message users, indexed by user ID. */
    public array $users = [];

    /** @var int[] Roles, indexed by user ID. */
    public array $roles = [];

    /** @var bool[] Unread status, indexed by user ID. */
    public array $unread = [];

    /** @var bool[] Starred status, indexed by user ID. */
    public array $starred = [];

    /** @var int[] Deleted status, indexed by user ID. */
    public array $deleted = [];

    /** @var label[][] Labels, indexed by user ID and label ID. */
    public array $labels = [];

    /**
     * Constructs a message instance from a database record.
     *
     * @param \stdClass $record Record of local_mail_messages.
     * @param course $course Course of the message.
     */
    private function __construct(\stdClass $record, course $course) {
        $this->id = (int) $record->id;
        $this->course = $course;
        $this->subject = $record->subject;
        $this->content = $record->content;
        $this->format = (int) $record->format;
        $this->attachments = (int) $record->attachments;
        $this->draft = (bool) $record->draft;
        $this->time = (int) $record->time;
    }

    /**
     * Adds a recipient to the message.
     *
     * @param user $user Recipient user.
     * @param int $role Recipient role.
     */
    public function add_recipient(user $user, int $role): void {
        global $DB;

        assert($this->draft);
        assert(!isset($this->users[$user->id]));
        assert(in_array($role, [self::ROLE_TO, self::ROLE_CC, self::ROLE_BCC]));

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('local_mail_message_users', ['messageid' => $this->id, 'userid' => $user->id]);

        $record = new \stdClass;
        $record->messageid = $this->id;
        $record->courseid = $this->course->id;
        $record->draft = $this->draft;
        $record->time = $this->time;
        $record->userid = $user->id;
        $record->role = $role;
        $record->unread = 1;
        $record->starred = 0;
        $record->deleted = self::NOT_DELETED;
        $DB->insert_record('local_mail_message_users', $record);

        $transaction->allow_commit();

        $this->users[$user->id] = $user;
        $this->roles[$user->id] = $role;
        $this->unread[$user->id] = true;
        $this->starred[$user->id] = false;
        $this->deleted[$user->id] = self::NOT_DELETED;
        $this->labels[$user->id] = [];
    }

    /**
     * Creates a new empty draft.
     *
     * @param course $course Course of the mssage.
     * @param user $user Sender of the message.
     * @param int $time Timestamp.
     * @return self Created message.
     */
    public static function create(course $course, user $user, int $time): self {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $messagerecord = new \stdClass;
        $messagerecord->courseid = $course->id;
        $messagerecord->subject = '';
        $messagerecord->content = '';
        $messagerecord->format = FORMAT_HTML;
        $messagerecord->attachments = 0;
        $messagerecord->draft = 1;
        $messagerecord->time = $time;
        $messagerecord->normalizedsubject = '';
        $messagerecord->normalizedcontent = '';
        $messagerecord->id = $DB->insert_record('local_mail_messages', $messagerecord);

        $userrecord = new \stdClass;
        $userrecord->messageid = $messagerecord->id;
        $userrecord->courseid = $course->id;
        $userrecord->draft = 1;
        $userrecord->time = $time;
        $userrecord->userid = $user->id;
        $userrecord->role = self::ROLE_FROM;
        $userrecord->unread = 0;
        $userrecord->starred = 0;
        $userrecord->deleted = self::NOT_DELETED;
        $DB->insert_record('local_mail_message_users', $userrecord);

        $transaction->allow_commit();

        $message = new self($messagerecord, $course);
        $message->users[$user->id] = $user;
        $message->roles[$user->id] = self::ROLE_FROM;
        $message->unread[$user->id] = false;
        $message->starred[$user->id] = false;
        $message->deleted[$user->id] = self::NOT_DELETED;
        $message->labels[$user->id] = [];

        return $message;
    }

    /**
     * Empties the trash of a user.
     *
     * @param user $user User.
     * @param course[] $courses Courses.
     */
    public static function empty_trash(user $user, array $courses): void {
        global $DB;

        if (!$courses) {
            return;
        }

        $courseids = array_column($courses, 'id');
        list($sqlcourseid, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $params['userid'] = $user->id;
        $params['deleted'] = self::DELETED;
        $params['deletedforever'] = self::DELETED_FOREVER;

        $transaction = $DB->start_delegated_transaction();

        $sql = 'UPDATE {local_mail_message_users}'
            . ' SET deleted = :deletedforever'
            . ' WHERE userid = :userid AND courseid ' . $sqlcourseid
            . ' AND deleted = :deleted';
        $DB->execute($sql, $params);

        $sql = 'UPDATE {local_mail_message_labels} ml'
            . ' JOIN {local_mail_labels} l ON l.id = ml.labelid'
            . ' SET ml.deleted = :deletedforever'
            . ' WHERE l.userid = :userid AND ml.courseid ' . $sqlcourseid
            . ' AND ml.deleted = :deleted';
        $DB->execute($sql, $params);

        $transaction->allow_commit();
    }

    /**
     * Fetches a message from the database.
     *
     * @param int $id ID of the message to fetch.
     * @return ?self Fetched message or null if not found.
     */
    public static function fetch(int $id): ?self {
        $messages = self::fetch_many([$id]);
        return isset($messages[$id]) ? $messages[$id] : null;
    }

    /**
     * Fetches messages from the database.
     *
     * @param int[] $ids IDs of the messages to fetch.
     * @return self[] Fetched messages, ordered from newer to older and indexed by ID.
     */
    public static function fetch_many(array $ids): array {
        global $DB;

        if (!$ids) {
            return [];
        }

        $ids = array_unique($ids);

        // Fetch records.
        list($sqlid, $params) = $DB->get_in_or_equal($ids);
        $fields = 'id, courseid, subject, content, format, attachments, draft, time';
        $sort = 'time DESC, id DESC';
        $messagerecords = $DB->get_records_select('local_mail_messages', "id $sqlid", $params, $sort, $fields);

        // Fetch courses.
        $courseids = array_column($messagerecords, 'courseid');
        $allcourses = course::fetch_many($courseids);
        $courses = [];
        foreach ($messagerecords as $r) {
            $courses[$r->id] = $allcourses[$r->courseid];
        }

        // Fetch users.
        $messageuserrecords = $DB->get_records_select('local_mail_message_users', "messageid $sqlid", $params);
        $userids = array_column($messageuserrecords, 'userid');
        $allusers = user::fetch_many($userids);
        $users = [];
        $roles = [];
        $unread = [];
        $starred = [];
        $deleted = [];
        foreach ($messageuserrecords as $r) {
            $users[$r->messageid][$r->userid] = $allusers[$r->userid];
            $roles[$r->messageid][$r->userid] = (int) $r->role;
            $unread[$r->messageid][$r->userid] = (bool) $r->unread;
            $starred[$r->messageid][$r->userid] = (bool) $r->starred;
            $deleted[$r->messageid][$r->userid] = (int) $r->deleted;
        }

        // Fetch labels.
        $messagelabelrecords = $DB->get_records_select('local_mail_message_labels', "messageid $sqlid", $params);
        $labelids = array_column($messagelabelrecords, 'labelid');
        $alllabels = label::fetch_many($labelids);
        $labels = [];
        foreach ($messagelabelrecords as $r) {
            $label = $alllabels[$r->labelid];
            $labels[$r->messageid][$label->user->id][$label->id] = $label;
        }

        // Construct messages.
        $messages = [];
        foreach ($messagerecords as $id => $messagerecord) {
            if (isset($courses[$id]) && isset($users[$id]) && array_search(self::ROLE_FROM, $roles[$id]) > 0) {
                $message = new self($messagerecord, $courses[$id]);
                $message->users = $users[$id];
                $message->roles = $roles[$id];
                $message->unread = $unread[$id];
                $message->starred = $starred[$id];
                $message->deleted = $deleted[$id];
                foreach ($users[$id] as $user) {
                    $message->labels[$user->id] = $labels[$id][$user->id] ?? [];
                }
                $messages[$id] = $message;
            }
        }

        return $messages;
    }

    /**
     * Fetches the message references from the database.
     *
     * @param bool $reverse Return forward references instead of backward references.
     * @return self[] Fetched references indexed by ID.
     */
    public function fetch_references(bool $forward = false): array {
        global $DB;

        if ($forward) {
            $conditions = ['reference' => $this->id];
            $field = 'messageid';
        } else {
            $conditions = ['messageid' => $this->id];
            $field = 'reference';
        }

        $records = $DB->get_records('local_mail_message_refs', $conditions, '', $field);

        return self::fetch_many(array_keys($records));
    }

    /**
     * Creates a new draft that containts this message as a forwarded reference.
     *
     * @param user $user User who forwards the message.
     * @param int $time Timestamp.
     * @return self The new message draft.
     */
    public function forward(user $user, int $time): self {
        global $DB;

        assert(!$this->draft);
        assert(isset($this->users[$user->id]));

        $transaction = $DB->start_delegated_transaction();

        $message = self::create($this->course, $user, $time);

        $prefix = 'FW:';
        if (\core_text::strpos($this->subject, $prefix) === 0) {
            $message->update($this->subject, '', FORMAT_HTML, $time);
        } else {
            $message->update($prefix . ' ' . $this->subject, '', FORMAT_HTML, $time);
        }

        $records = [['messageid' => $message->id, 'reference' => $this->id]];
        foreach ($this->fetch_references() as $reference) {
            $records[] = ['messageid' => $message->id, 'reference' => $reference->id];
        }
        $DB->insert_records('local_mail_message_refs', $records);

        $message->set_labels($user, $this->labels[$user->id]);

        $transaction->allow_commit();

        return $message;
    }

    /**
     * Returns whether the given user is a recipient of a message.
     *
     * @param user $user User.
     * @return bool
     */
    public function has_recipient(user $user): bool {
        $recipientroles = [self::ROLE_TO, self::ROLE_CC, self::ROLE_BCC];
        return isset($this->roles[$user->id]) && in_array($this->roles[$user->id], $recipientroles);
    }

    /**
     * Normalizes text for searching.
     *
     * Replaces non-alphanumeric characters with a space.
     *
     * @param string $text Text to normalize.
     * @return string
     */
    public static function normalize_text(string $text): string {
        // Replaces non-alphanumeric characters with a space.
        return trim(preg_replace('/(*UTF8)[^\p{L}\p{N}]+/', ' ', $text));
    }

    /**
     * Returns the recipients of the message.
     *
     * @param int $roles Roles to include or all if empty.
     * @return user[]
     */
    public function recipients(int ...$roles): array {
        foreach ($roles as $role) {
            assert(in_array($role, [self::ROLE_TO, self::ROLE_CC, self::ROLE_BCC]));
        }
        $result = [];
        foreach ($this->users as $user) {
            $role = $this->roles[$user->id];
            if ($role != self::ROLE_FROM && (!$roles || in_array($role, $roles))) {
                $result[] = $user;
            }
        }
        return $result;
    }

    /**
     * Removes a recipient from the message.
     *
     * @param user $user Recipient user.
     */
    public function remove_recipient(user $user): void {
        global $DB;

        assert($this->draft);
        assert(isset($this->users[$user->id]));
        assert($this->roles[$user->id] != self::ROLE_FROM);

        $DB->delete_records('local_mail_message_users', ['messageid' => $this->id, 'userid' => $user->id]);

        unset($this->users[$user->id]);
        unset($this->roles[$user->id]);
        unset($this->unread[$user->id]);
        unset($this->starred[$user->id]);
        unset($this->deleted[$user->id]);
        unset($this->labels[$user->id]);
    }

    /**
     * Creates a new draft that containts this message as a replied reference.
     *
     * @param user $user User who replies the message.
     * @param int $time Timestamp.
     * @param bool $all Reply to all users.
     * @return self The new message draft.
     */
    public function reply(user $user, bool $all, int $time): self {
        global $DB;

        assert(!$this->draft);
        assert(isset($this->users[$user->id]));
        assert(!$all || $this->roles[$user->id] != self::ROLE_BCC);

        $transaction = $DB->start_delegated_transaction();

        $message = self::create($this->course, $user, $time);

        $prefix = 'RE:';
        if (\core_text::strpos($this->subject, $prefix) === 0) {
            $message->update($this->subject, '', FORMAT_HTML, $time);
        } else {
            $message->update($prefix . ' ' . $this->subject, '', FORMAT_HTML, $time);
        }

        $records = [['messageid' => $message->id, 'reference' => $this->id]];
        foreach ($this->fetch_references() as $reference) {
            $records[] = ['messageid' => $message->id, 'reference' => $reference->id];
        }
        $DB->insert_records('local_mail_message_refs', $records);

        $message->set_labels($user, $this->labels[$user->id]);

        foreach (array_keys($this->users) as $id) {
            if ($id == $user->id || $this->roles[$id] == self::ROLE_BCC) {
                // Ignore user who replies and BCC recipients.
                continue;
            }
            if ($this->roles[$user->id] == self::ROLE_FROM) {
                if ($this->roles[$id] == self::ROLE_TO) {
                    $message->add_recipient($this->users[$id], self::ROLE_TO);
                } else if ($all) {
                    $message->add_recipient($this->users[$id], self::ROLE_CC);
                }
            } else {
                if ($this->roles[$id] == self::ROLE_FROM) {
                    $message->add_recipient($this->users[$id], self::ROLE_TO);
                } else if ($all) {
                    $message->add_recipient($this->users[$id], self::ROLE_CC);
                }
            }
        }

        $transaction->allow_commit();

        return $message;
    }

    /**
     * Sends the message.
     *
     * @param int $time Timestamp.
     */
    public function send(int $time): void {
        global $DB;

        assert($this->draft);
        assert(\core_text::strlen($this->subject) > 0);
        assert(count($this->users) >= 2);

        $transaction = $DB->start_delegated_transaction();

        $DB->set_field('local_mail_messages', 'draft', 0, ['id' => $this->id]);
        $DB->set_field('local_mail_messages', 'time', $time, ['id' => $this->id]);
        $DB->set_field('local_mail_message_users', 'draft', 0, ['messageid' => $this->id]);
        $DB->set_field('local_mail_message_users', 'time', $time, ['messageid' => $this->id]);
        $DB->set_field('local_mail_message_labels', 'draft', 0, ['messageid' => $this->id]);
        $DB->set_field('local_mail_message_labels', 'time', $time, ['messageid' => $this->id]);

        $this->draft = false;
        $this->time = $time;

        // Set labels from first reference.
        foreach ($this->fetch_references() as $ref) {
            foreach ($this->recipients() as $user) {
                if (isset($ref->labels[$user->id])) {
                    $this->set_labels($user, $ref->labels[$user->id]);
                }
            }
            break;
        }

        $transaction->allow_commit();
    }


    /**
     * Returns the sender of the message.
     *
     * @return user
     */
    public function sender(): user {
        $userid = array_search(self::ROLE_FROM, $this->roles);
        return $this->users[$userid];
    }

    /**
     * Set the delete status of the message.
     *
     * Drafts are always removed from the database.
     *
     * @param user $user User.
     * @param int $status New deleted status.
     */
    public function set_deleted(user $user, int $status): void {
        global $DB;

        assert(isset($this->users[$user->id]));
        assert(in_array($status, [self::NOT_DELETED, self::DELETED, self::DELETED_FOREVER]));
        assert(!$this->draft || $this->roles[$user->id] == self::ROLE_FROM);

        $transaction = $DB->start_delegated_transaction();

        if ($this->draft && $status == self::DELETED_FOREVER) {
            $DB->delete_records('local_mail_messages', ['id' => $this->id]);
            $DB->delete_records('local_mail_message_refs', ['messageid' => $this->id]);
            $DB->delete_records('local_mail_message_users', ['messageid' => $this->id]);
            $DB->delete_records('local_mail_message_labels', ['messageid' => $this->id]);
        } else {
            $conditions = ['messageid' => $this->id, 'userid' => $user->id];
            $DB->set_field('local_mail_message_users', 'deleted', $status, $conditions);

            foreach ($this->labels[$user->id] as $label) {
                $conditions = ['messageid' => $this->id, 'labelid' => $label->id];
                if ($status == self::DELETED_FOREVER) {
                    $DB->delete_records('local_mail_message_labels', $conditions);
                } else {
                    $DB->set_field('local_mail_message_labels', 'deleted', $status, $conditions);
                }
            }
        }

        $transaction->allow_commit();

        if ($this->draft && $status == self::DELETED_FOREVER) {
            // Delete files after the transaction, in case it is rolled back.
            $fs = get_file_storage();
            $context = \context_course::instance($this->course->id);
            $fs->delete_area_files($context->id, 'local_mail', 'message', $this->id);
        }

        $this->deleted[$user->id] = $status;
        if ($status == self::DELETED_FOREVER) {
            $this->labels[$user->id] = [];
        }
    }

    /**
     * Sets the labels for a user.
     *
     * @param user $user User.
     * @param label[] $labels Labels.
     */
    public function set_labels(user $user, array $labels): void {
        global $DB;

        assert(isset($this->users[$user->id]));
        assert(!$this->draft || $this->roles[$user->id] == self::ROLE_FROM);
        assert($this->deleted[$user->id] != self::DELETED_FOREVER);
        foreach ($labels as $label) {
            assert($label->user->id == $user->id);
        }

        $transaction = $DB->start_delegated_transaction();

        $labelids = array_column($labels, 'id');
        foreach ($this->labels[$user->id] as $label) {
            if (!in_array($label->id, $labelids)) {
                $DB->delete_records('local_mail_message_labels', ['messageid' => $this->id, 'labelid' => $label->id]);
            }
        }

        foreach ($labels as $label) {
            if (!isset($this->labels[$user->id][$label->id])) {
                $DB->delete_records('local_mail_message_labels', ['messageid' => $this->id, 'labelid' => $label->id]);

                $record = new \stdClass;
                $record->messageid = $this->id;
                $record->courseid = $this->course->id;
                $record->draft = $this->draft;
                $record->time = $this->time;
                $record->labelid = $label->id;
                $record->role = $this->roles[$label->user->id];
                $record->unread = $this->unread[$label->user->id];
                $record->starred = $this->starred[$label->user->id];
                $record->deleted = $this->deleted[$label->user->id];
                $DB->insert_record('local_mail_message_labels', $record);
            }
        }

        $transaction->allow_commit();

        $this->labels[$user->id] = [];
        foreach ($labels as $label) {
            $this->labels[$user->id][$label->id] = $label;
        }
    }

    /**
     * Set the starred status of the message.
     *
     * @param user $user User.
     * @param bool $status New starred status.
     */
    public function set_starred(user $user, bool $status): void {
        global $DB;

        assert(isset($this->users[$user->id]));
        assert(!$this->draft || $this->roles[$user->id] == self::ROLE_FROM);
        assert($this->deleted[$user->id] != self::DELETED_FOREVER);

        $transaction = $DB->start_delegated_transaction();

        $conditions = ['messageid' => $this->id, 'userid' => $user->id];
        $DB->set_field('local_mail_message_users', 'starred', $status, $conditions);

        foreach ($this->labels[$user->id] as $label) {
            $conditions = ['messageid' => $this->id, 'labelid' => $label->id];
            $DB->set_field('local_mail_message_labels', 'starred', $status, $conditions);
        }

        $transaction->allow_commit();

        $this->starred[$user->id] = $status;
    }

    /**
     * Sets the unread status of the message.
     *
     * @param user $user User.
     * @param bool $status New unread status.
     */
    public function set_unread(user $user, bool $status): void {
        global $DB;

        assert(isset($this->users[$user->id]));
        assert(!$this->draft || $this->roles[$user->id] == self::ROLE_FROM);
        assert($this->deleted[$user->id] != self::DELETED_FOREVER);

        $transaction = $DB->start_delegated_transaction();

        $conditions = ['messageid' => $this->id, 'userid' => $user->id];
        $DB->set_field('local_mail_message_users', 'unread', $status, $conditions);

        foreach ($this->labels[$user->id] as $label) {
            $conditions = ['messageid' => $this->id, 'labelid' => $label->id];
            $DB->set_field('local_mail_message_labels', 'unread', $status, $conditions);
        }

        $transaction->allow_commit();

        $this->unread[$user->id] = $status;
    }

    /**
     * Updates the message.
     *
     * @param string $subject New subject.
     * @param string $content New content.
     * @param int $format New content format.
     * @param int $time New timestamp.
     */
    public function update(string $subject, string $content, int $format, int $time): void {
        global $DB;

        assert($this->draft);

        // Trim and truncate subject.
        $subject = trim($subject);
        if (\core_text::strlen($subject) > 100) {
            $subject = \core_text::substr($subject, 0, 97) . '...';
        }

        // Number of attachments.
        $fs = get_file_storage();
        $context = $this->course->context();
        $files = $fs->get_area_files($context->id, 'local_mail', 'message', $this->id, '', false);
        $attachments = count($files);

        // Normalized content.
        $content = file_rewrite_pluginfile_urls(
            $content,
            'pluginfile.php',
            $context->id,
            'local_mail',
            'message',
            $this->id
        );
        $content = format_text($content, $format, ['filter' => false, 'nocache' => true]);
        $normalizedsubject = self::normalize_text($subject);
        $normalizedcontent = self::normalize_text(html_to_text($content, 0, false));

        $transaction = $DB->start_delegated_transaction();

        $record = new \stdClass;
        $record->id = $this->id;
        $record->subject = $subject;
        $record->content = $content;
        $record->format = $format;
        $record->attachments = $attachments;
        $record->time = $time;
        $record->normalizedsubject = $normalizedsubject;
        $record->normalizedcontent = $normalizedcontent;
        $DB->update_record('local_mail_messages', $record);

        $DB->set_field('local_mail_message_users', 'time', $time, ['messageid' => $this->id]);
        $DB->set_field('local_mail_message_labels', 'time', $time, ['messageid' => $this->id]);
        $DB->set_field('local_mail_message_users', 'deleted', self::NOT_DELETED, ['messageid' => $this->id]);
        $DB->set_field('local_mail_message_labels', 'deleted', self::NOT_DELETED, ['messageid' => $this->id]);

        $transaction->allow_commit();

        $this->subject = $subject;
        $this->content = $content;
        $this->format = $format;
        $this->attachments = $attachments;
        $this->time = $time;
        foreach ($this->users as $user) {
            $this->deleted[$user->id] = self::NOT_DELETED;
        }
    }
}
