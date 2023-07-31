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
    public int $attachments;

    /** @var bool Draft status. */
    public bool $draft;

    /** @var int Timestamp. */
    public int $time;

    /** @var user[] Message users, indexed by user ID. */
    private array $users = [];

    /** @var int[] Roles, indexed by user ID. */
    private array $roles = [];

    /** @var bool[] Unread status, indexed by user ID. */
    private array $unread = [];

    /** @var bool[] Starred status, indexed by user ID. */
    private array $starred = [];

    /** @var int[] Deleted status, indexed by user ID. */
    private array $deleted = [];

    /** @var label[][] Labels, indexed by user ID and label ID. */
    private array $labels = [];

    /**
     * Constructs a message instance from a database record.
     *
     * @param \stdClass $record Record of local_mail_messages.
     * @param course $course Course.
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
     * Creates a new empty draft.
     *
     * @param message_data $data Message data.
     * @return self Created message.
     */
    public static function create(message_data $data): self {
        global $DB;

        assert(!$data->reference || isset($data->reference->users[$data->sender->id]));
        assert(!$data->reference || $data->course->id == $data->reference->course->id);

        $transaction = $DB->start_delegated_transaction();

        $messagerecord = new \stdClass;
        $messagerecord->courseid = 0;
        $messagerecord->subject = '';
        $messagerecord->content = '';
        $messagerecord->format = FORMAT_HTML;
        $messagerecord->attachments = 0;
        $messagerecord->draft = 1;
        $messagerecord->time = 0;
        $messagerecord->normalizedsubject = '';
        $messagerecord->normalizedcontent = '';
        $messagerecord->id = $DB->insert_record('local_mail_messages', $messagerecord);
        $message = new self($messagerecord, $data->course);

        // Sender.
        $message->users[$data->sender->id] = $data->sender;
        $message->roles[$data->sender->id] = self::ROLE_FROM;
        $message->unread[$data->sender->id] = false;
        $message->starred[$data->sender->id] = false;
        $message->deleted[$data->sender->id] = self::NOT_DELETED;
        $message->labels[$data->sender->id] = [];
        $userrecord = new \stdClass;
        $userrecord->messageid = $message->id;
        $userrecord->courseid = 0;
        $userrecord->draft = 1;
        $userrecord->time = 0;
        $userrecord->userid = $data->sender->id;
        $userrecord->role = self::ROLE_FROM;
        $userrecord->unread = 0;
        $userrecord->starred = 0;
        $userrecord->deleted = self::NOT_DELETED;
        $DB->insert_record('local_mail_message_users', $userrecord);

        // References.
        if ($data->reference) {
            $records = [['messageid' => $message->id, 'reference' => $data->reference->id]];
            foreach ($data->reference->fetch_references() as $reference) {
                $records[] = ['messageid' => $message->id, 'reference' => $reference->id];
            }
            $DB->insert_records('local_mail_message_refs', $records);
        }

        // Labels.
        if ($data->reference) {
            $message->set_labels($data->sender, $data->reference->labels[$data->sender->id]);
        }

        $message->update($data);

        $transaction->allow_commit();

        return $message;
    }

    /**
     * Deletes all messages from a course.
     *
     * @param \context_course $context Context of the course.
     */
    public static function delete_course(\context_course $context): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('local_mail_message_labels', ['courseid' => $context->instanceid]);

        $DB->delete_records('local_mail_message_users', ['courseid' => $context->instanceid]);

        $select = 'messageid IN (SELECT id FROM {local_mail_messages} WHERE courseid = :courseid)';
        $DB->delete_records_select('local_mail_message_refs', $select, ['courseid' => $context->instanceid]);

        $DB->delete_records('local_mail_messages', ['courseid' => $context->instanceid]);

        $transaction->allow_commit();

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_mail');
    }

    /**
     * Returns the deleted status of the message.
     *
     * @param user $user User.
     * @return int
     */
    public function deleted(user $user): int {
        assert(isset($this->users[$user->id]));

        return $this->deleted[$user->id];
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

        $labelsql = 'SELECT l.id FROM {local_mail_labels} l WHERE l.userid = :userid';
        $sql = 'UPDATE {local_mail_message_labels}'
            . ' SET deleted = :deletedforever'
            . ' WHERE courseid ' . $sqlcourseid
            . ' AND deleted = :deleted'
            . ' AND labelid IN (' . $labelsql . ')';
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
            if (isset($allcourses[$r->courseid])) {
                $courses[$r->id] = $allcourses[$r->courseid];
            }
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
            if (isset($allusers[$r->userid])) {
                $users[$r->messageid][$r->userid] = $allusers[$r->userid];
                $roles[$r->messageid][$r->userid] = (int) $r->role;
                $unread[$r->messageid][$r->userid] = (bool) $r->unread;
                $starred[$r->messageid][$r->userid] = (bool) $r->starred;
                $deleted[$r->messageid][$r->userid] = (int) $r->deleted;
            }
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

                // Sort roles so sender method has constant time complexity.
                asort($message->roles);
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
     * Returns whether the given label is set for the message.
     *
     * @param label $label Label.
     * @return bool
     */
    public function has_label(label $label): bool {
        assert(isset($this->users[$label->user->id]));

        return isset($this->labels[$label->user->id][$label->id]);
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
     * Returns the labels of the message.
     *
     * @param user $user User.
     * @return label[] Labels sorted by name.
     */
    public function labels(user $user): array {
        assert(isset($this->users[$user->id]));

        $labels = $this->labels[$user->id];

        \core_collator::asort_objects_by_property($labels, 'name', \core_collator::SORT_NATURAL);

        return array_values($labels);
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
     * @return user[] Sorted users.
     */
    public function recipients(int ...$roles): array {
        foreach ($roles as $role) {
            assert(in_array($role, [self::ROLE_TO, self::ROLE_CC, self::ROLE_BCC]));
        }
        $recipients = [];
        foreach ($this->users as $user) {
            $role = $this->roles[$user->id];
            if ($role != self::ROLE_FROM && (!$roles || in_array($role, $roles))) {
                $recipients[] = $user;
            }
        }

        \core_collator::asort_objects_by_method($recipients, 'sortorder');

        return array_values($recipients);
    }

    /**
     * Returns the role of a user.
     *
     * @param user $user User.
     * @return int message::ROLE_FROM, message::ROLE_TO, message::ROLE_CC or message::ROLE_BCC
     */
    public function role(user $user): int {
        assert(isset($this->users[$user->id]));

        return $this->roles[$user->id];
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
     * Returns the starred status of the message.
     *
     * @param user $user User.
     * @return bool
     */
    public function starred(user $user): bool {
        assert(isset($this->users[$user->id]));

        return $this->starred[$user->id];
    }

    /**
     * Returns the unread status of the message.
     *
     * @param user $user User.
     * @return bool
     */
    public function unread(user $user): bool {
        assert(isset($this->users[$user->id]));

        return $this->unread[$user->id];
    }

    /**
     * Updates the message.
     *
     * @param message_data $data Message data.
     */
    public function update(message_data $data): void {
        global $DB;

        assert($this->draft);

        $transaction = $DB->start_delegated_transaction();

        $fs = get_file_storage();

        $oldcontext = $this->course->context();
        $newcontext = $data->course->context();

        // Course.
        $this->course = $data->course;

        // Subject.
        $this->subject = trim($data->subject);
        if (\core_text::strlen($this->subject) > 100) {
            $this->subject = \core_text::substr($this->subject, 0, 97) . '...';
        }

        // Content and attachments.
        $this->content = file_save_draft_area_files(
            $data->draftitemid,
            $newcontext->id,
            'local_mail',
            'message',
            $this->id,
            message_data::file_options(),
            $data->content
        );
        $this->format = $data->format;
        $this->attachments = count($fs->get_area_files($newcontext->id, 'local_mail', 'message', $this->id, '', false));

        // Time.
        $this->time = (int) $data->time;

        // Message record.
        $messagerecord = new \stdClass;
        $messagerecord->id = $this->id;
        $messagerecord->courseid = $this->course->id;
        $messagerecord->subject = $this->subject;
        $messagerecord->content = $this->content;
        $messagerecord->format = $this->format;
        $messagerecord->attachments = $this->attachments;
        $messagerecord->time = $this->time;
        $messagerecord->normalizedsubject = self::normalize_text($this->subject);
        $messagerecord->normalizedcontent = self::normalize_text($this->content);
        $DB->update_record('local_mail_messages', $messagerecord);

        // User records.
        foreach ($this->users as $user) {
            $this->deleted[$user->id] = self::NOT_DELETED;
        }
        $sql = 'UPDATE {local_mail_message_users}'
            . ' SET courseid = :courseid, deleted = :deleted, time = :time'
            . ' WHERE messageid = :messageid';
        $params = [
            'messageid' => $this->id,
            'courseid' => $this->course->id,
            'deleted' => self::NOT_DELETED,
            'time' => $this->time,
        ];
        $DB->execute($sql, $params);

        // Label records.
        $sql = 'UPDATE {local_mail_message_labels}'
            . ' SET courseid = :courseid, deleted = :deleted, time = :time'
            . ' WHERE messageid = :messageid';
        $params = [
            'messageid' => $this->id,
            'courseid' => $this->course->id,
            'deleted' => self::NOT_DELETED,
            'time' => $this->time,
        ];
        $DB->execute($sql, $params);

        // Added and modified recipients.
        $ignored = [$this->sender()->id => true];
        foreach (['to', 'cc', 'bcc'] as $rolename) {
            $role = $rolename == 'to' ? self::ROLE_TO : ($rolename == 'cc' ? self::ROLE_CC : self::ROLE_BCC);

            foreach ($data->$rolename as $user) {
                if (!empty($ignored[$user->id])) {
                    // Ignore duplicated user.
                    continue;
                }

                $ignored[$user->id] = true;

                if (!isset($this->users[$user->id])) {
                    $this->users[$user->id] = $user;
                    $this->roles[$user->id] = $role;
                    $this->unread[$user->id] = true;
                    $this->starred[$user->id] = false;
                    $this->deleted[$user->id] = self::NOT_DELETED;
                    $this->labels[$user->id] = [];

                    $userrecord = new \stdClass;
                    $userrecord->messageid = $this->id;
                    $userrecord->courseid = $this->course->id;
                    $userrecord->draft = 1;
                    $userrecord->time = $this->time;
                    $userrecord->userid = $user->id;
                    $userrecord->role = $role;
                    $userrecord->unread = 1;
                    $userrecord->starred = 0;
                    $userrecord->deleted = self::NOT_DELETED;
                    $DB->insert_record('local_mail_message_users', $userrecord);
                } else if ($role != $this->roles[$user->id]) {
                    $this->roles[$user->id] = $role;

                    $sql = 'UPDATE {local_mail_message_users}'
                        . ' SET role = :role'
                        . ' WHERE messageid = :messageid AND userid = :userid';
                    $params = [
                        'messageid' => $this->id,
                        'userid' => $user->id,
                        'role' => $role,
                    ];
                    $DB->execute($sql, $params);
                }
            }
        }

        // Removed recipients.
        foreach ($this->users as $user) {
            if ($this->roles[$user->id] != self::ROLE_FROM && empty($ignored[$user->id])) {
                unset($this->users[$user->id]);
                unset($this->roles[$user->id]);
                unset($this->unread[$user->id]);
                unset($this->starred[$user->id]);
                unset($this->deleted[$user->id]);
                unset($this->labels[$user->id]);
                $DB->delete_records('local_mail_message_users', ['messageid' => $this->id, 'userid' => $user->id]);
            }
        }

        // Delete old files.
        if ($newcontext->id != $oldcontext->id) {
            $fs->delete_area_files($oldcontext->id, 'local_mail', 'message', $this->id);
        }

        $transaction->allow_commit();
    }
}
