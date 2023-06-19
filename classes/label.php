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

class label {

    /** @var string[] List of valid colors. */
    const COLORS = ['gray', 'blue', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'green', 'teal', 'cyan'];

    /** @var int Label ID. */
    public int $id;

    /** @var user User. */
    public user $user;

    /** @var string Name. */
    public string $name;

    /** @var string Color. */
    public string $color;

    /**
     * Constructs a label instance from a database record.
     *
     * @param \stdClass $record A database record from table local_mail_labels.
     * @param user $user User of the label.
     */
    public function __construct(\stdClass $record, user $user) {
        assert($record->userid == $user->id);

        $this->id = (int) $record->id;
        $this->user = $user;
        $this->name = self::nromalized_name($record->name);
        $this->color = preg_replace('/(light|dark)/', '', $record->color);
    }

    /**
     * Creates a label.
     *
     * @param user $user User..
     * @param string $name Name of the label.
     * @param string $color Color of the label, optional.
     * @return self Created label.
     */
    public static function create(user $user, string $name, string $color = ''): self {
        global $DB;

        assert(\core_text::strlen(self::nromalized_name($name)) > 0);
        assert($color == '' || in_array($color, self::COLORS));

        $record = new \stdClass;
        $record->userid = $user->id;
        $record->name = self::nromalized_name($name);
        $record->color = $color;
        $record->id = $DB->insert_record('local_mail_labels', $record);

        return new self($record, $user);
    }

    /**
     * Deletes the label from the database.
     */
    public function delete(): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_mail_labels', array('id' => $this->id));
        $DB->delete_records('local_mail_message_labels', array('labelid' => $this->id));
        $transaction->allow_commit();
    }

    /**
     * Fetches a label from the database.
     *
     * @param int $id ID of the label to fetch.
     * @return ?self The fetched label or null if not found.
     */
    public static function fetch(int $id): ?self {
        $labels = self::fetch_many([$id]);
        return isset($labels[$id]) ? $labels[$id] : null;
    }

    /**
     * Fetches all labels of a user from the database.
     *
     * @param user User.
     * @return label[] The fetched labels, ordered by name and indexed by ID.
     */
    public static function fetch_by_user(user $user): array {
        global $DB;

        $labels = [];

        $records = $DB->get_records('local_mail_labels', array('userid' => $user->id));
        foreach ($records as $record) {
            $labels[$record->id] = new self($record, $user);
        }

        \core_collator::asort_objects_by_property($labels, 'name', \core_collator::SORT_NATURAL);

        return $labels;
    }

    /**
     * Fetches multiple labels from the database.
     *
     * @param int[] $id IDs of the labels to fetch.
     * @return self[] The fetched labels indexed by ID.
     */
    public static function fetch_many(array $ids): array {
        global $DB;

        if (!$ids) {
            return [];
        }

        $ids = array_unique($ids);
        list($sqlid, $params) = $DB->get_in_or_equal($ids);
        $sort = 'id';
        $records = $DB->get_records_select('local_mail_labels', "id $sqlid", $params, $sort);

        $userids = array_column($records, 'userid');
        $users = user::fetch_many($userids);

        $labels = [];
        foreach ($records as $record) {
            if (isset($users[$record->userid])) {
                $labels[$record->id] = new self($record, $users[$record->userid]);
            }
        }

        return $labels;
    }

    /**
     * Removes leading, trailing and repeated spaces of a label name.
     *
     * @param string $name A label name.
     * @return string The normalized name.
     */
    public static function nromalized_name(string $name): string {
        return preg_replace('/\s+/u', ' ', trim($name));
    }

    /**
     * Updates the name and color of the label.
     *
     * @param string $name New name of the label.
     * @param string $color New color of the label.
     */
    public function update(string $name, string $color): void {
        global $DB;

        assert(\core_text::strlen(self::nromalized_name($name)) > 0);
        assert($color == '' || in_array($color, self::COLORS));

        $this->name = self::nromalized_name($name);
        $this->color = $color;

        $record = new \stdClass;
        $record->id = $this->id;
        $record->name = $this->name;
        $record->color = $this->color;

        $DB->update_record('local_mail_labels', $record);
    }
}
