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

class user {

    /** @var int User ID. */
    public int $id;

    /** @var string First name. */
    public string $firstname;

    /** @var string Last name. */
    public string $lastname;

    /** @var string Email address. */
    public string $email;

    /** @var int Picture file ID. */
    public int $picture;

    /** @var ?string Picture description. */
    public ?string $imagealt;

    /** @var string Phonetic spelling of first name. */
    public string $firstnamephonetic;

    /** @var string Phonetic spelling of last name. */
    public string $lastnamephonetic;

    /** @var string Middle name. */
    public string $middlename;

    /** @var string Alternate name. */
    public string $alternatename;

    /**
     * Constructs a user instance from a database record.
     *
     * @param \stdClass $record Database record from table user.
     */
    public function __construct(\stdClass $record) {
        $this->id = (int) $record->id;
        $this->firstname = $record->firstname;
        $this->lastname = $record->lastname;
        $this->email = $record->email;
        $this->picture = (int) $record->picture;
        $this->imagealt = $record->imagealt ?? '';
        $this->firstnamephonetic = $record->firstnamephonetic ?? '';
        $this->lastnamephonetic = $record->lastnamephonetic ?? '';
        $this->middlename = $record->middlename ?? '';
        $this->alternatename = $record->alternatename ?? '';
    }

    /**
     * Returns whether the user can edit the message.
     *
     * @param message $message Message.
     * @return bool
     */
    public function can_edit_message(message $message): bool {
        return $message->draft &&
            $this->id == $message->sender()->id &&
            $message->deleted($this) != message::DELETED_FOREVER &&
            $this->can_use_mail($message->course);
    }

    /**
     * Returns whether the user can use mail in a course.
     *
     * @param course $course Course.
     * @return bool
     */
    public function can_use_mail(course $course) {
        return is_enrolled($course->context(), $this->id, 'local/mail:usemail', true) &&
            ($course->visible || has_capability('moodle/course:viewhiddencourses', $course->context(), $this->id, false));
    }

    /**
     * Returns whether the user can view the attachments of a message.
     *
     * @param message $message Message.
     * @return bool
     */
    public function can_view_files(message $message): bool {
        if ($this->can_view_message($message)) {
            return true;
        }
        foreach ($message->fetch_references(true) as $reference) {
            if ($this->can_view_message($reference)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns whether the user can a message.
     *
     * @param message $message Message.
     * @return bool
     */
    public function can_view_message(message $message): bool {
        return ($message->sender()->id == $this->id || !$message->draft && $message->has_recipient($this)) &&
            $message->deleted($this) != message::DELETED_FOREVER &&
            $this->can_use_mail($message->course);
    }

    /**
     * Returns the current logged in user.
     *
     * @return ?self The current or null if not logged in or is guest.
     */
    public static function current(): ?self {
        global $USER;

        return isloggedin() && !isguestuser() && !\core_user::awaiting_action() ? new self($USER) : null;
    }

    /**
     * Fetches a user from the database.
     *
     * @param int $id ID of the user to fetch.
     * @return ?self The fetched user or null if not found.
     */
    public static function fetch(int $id): ?self {
        $users = self::fetch_many([$id]);
        return isset($users[$id]) ? $users[$id] : null;
    }

    /**
     * Fetches multiple users from the database.
     *
     * @param int[] $ids IDs of the users to fetch.
     * @return self[] The fetched users, indexed by ID.
     */
    public static function fetch_many(array $ids): array {
        global $CFG, $DB;

        if (!$ids) {
            return [];
        }

        $ids = array_unique($ids);
        list($sqlid, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'userid');
        $select = "id $sqlid AND id <> :guestid AND deleted = 0";
        $params['guestid'] = $CFG->siteguest;
        $fields = implode(',', \core_user\fields::get_picture_fields());

        $records = $DB->get_records_select('user', $select, $params, '', $fields);

        $users = [];
        foreach ($ids as $id) {
            if (isset($records[$id])) {
                $users[$id] = new self($records[$id]);
            }
        }

        return $users;
    }

    /**
     * Full name of the user.
     *
     * @return string
     */
    public function fullname(): string {
        return fullname((object) $this);
    }

    /**
     * URL of the picture of the user.
     *
     * @return string
     */
    public function picture_url(): string {
        global $PAGE;
        $userpicture = new \user_picture((object) (array) $this);
        return $userpicture->get_url($PAGE)->out(false);
    }

    /**
     * URL of the profile of the user in a course.
     *
     * @param course $course Course.
     * @return string
     */
    public function profile_url(course $course): string {
        $params = ['id' => $this->id];
        if ($course) {
            $params['course'] = $course->id;
        }
        $url = new \moodle_url('/user/view.php', $params);
        return $url->out(false);
    }

    /**
     * Sort order of the user.
     *
     * @return string
     */
    public function sortorder(): string {
        return sprintf("%s\n%s\n%010d", $this->lastname, $this->firstname, $this->id);
    }
}
