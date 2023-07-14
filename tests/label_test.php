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
 * @covers \local_mail\label
 */
class label_test extends testcase {

    public function test_create() {
        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());

        $label = label::create($user, 'name', 'red');

        self::assertInstanceOf(label::class, $label);
        self::assertGreaterThan(0, $label->id);
        self::assertEquals($user, $label->user);
        self::assertEquals('name', $label->name);
        self::assertEquals('red', $label->color);
        self::assert_label($label);
    }

    public function test_delete() {
        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());

        $label1 = label::create($user, 'name 1', 'red');
        $label2 = label::create($user, 'name 2');

        self::insert_records(
            'message_labels',
            ['messageid', 'courseid', 'draft', 'time', 'labelid',   'role', 'unread', 'starred', 'deleted'],
            [0,            0,          0,       0,      $label1->id, 0,      0,        0,         0],
            [0,            0,          0,       0,      $label2->id, 0,      0,        0,         0],
            [0,            0,          0,       0,      $label2->id, 0,      0,        0,         0],
        );

        $label1->delete();

        self::assert_record_count(0, 'labels', ['id' => $label1->id]);
        self::assert_record_count(0, 'message_labels', ['labelid' => $label1->id]);
        self::assert_record_count(1, 'labels');
        self::assert_record_count(2, 'message_labels');
    }

    public function test_fetch() {
        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());
        $label = label::create($user, 'name 1', 'red');

        self::assertEquals($label, label::fetch($label->id));

        self::assertNull(label::fetch(0));
    }

    public function test_fetch_by_user() {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $label2 = label::create($user1, 'name 2', 'blue');
        $label4 = label::create($user1, 'name 4', 'purple');
        $label3 = label::create($user2, 'name 3', 'yellow');
        $label1 = label::create($user1, 'name 1', 'red');

        $labels = label::fetch_by_user($user1);
        self::assertEquals([$label1->id, $label2->id, $label4->id], array_keys($labels));
        self::assertEquals([$label1, $label2, $label4], array_values($labels));

        self::assertEquals([], label::fetch_by_user($user3));
    }

    public function test_fetch_many() {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $label1 = label::create($user1, 'name 1', 'red');
        $label2 = label::create($user2, 'name 2', 'blue');
        $label3 = label::create($user1, 'name 3', 'yellow');

        $labels = label::fetch_many([$label1->id, $label2->id, 0, $label1->id]);
        self::assertEquals([$label1->id, $label2->id], array_keys($labels));
        self::assertEquals([$label1, $label2], array_values($labels));

        self::assertEquals([], label::fetch_many([]));
    }

    public function test_update() {
        $generator = self::getDataGenerator();
        $user = new user($generator->create_user());
        $label = label::create($user, 'name 1', 'red');

        $label->update('new name', 'indigo');

        self::assertEquals('new name', $label->name);
        self::assertEquals('indigo', $label->color);
        self::assert_label($label);
    }

    public function test_normalized_name() {
        self::assertEquals('', label::nromalized_name(''));
        self::assertEquals('word', label::nromalized_name('word'));
        self::assertEquals('multiple words', label::nromalized_name('multiple words'));
        self::assertEquals('collapse space', label::nromalized_name('collapse     space'));
        self::assertEquals('replace line breaks', label::nromalized_name("replace\nline\rbreaks"));
        self::assertEquals('replace tab character', label::nromalized_name("replace\ttab\tcharacter"));
        self::assertEquals('trim text', label::nromalized_name('  trim text  '));
    }

    /**
     * Asserts that a label is stored correctly in the database.
     *
     * @param label $label Label.
     * @throws ExpectationFailedException
     */
    protected static function assert_label(label $label): void {
        self::assert_record_data('labels', [
            'id' => $label->id,
        ], [
            'userid' => $label->user->id,
            'name' => $label->name,
            'color' => $label->color,
        ]);
    }
}
