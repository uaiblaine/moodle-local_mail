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

namespace local_mail;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/testcase.php');

/**
 * @covers \local_mail\output\strings
 */
class output_strings_test extends testcase {

    public function test_get() {
        global $SESSION;

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        foreach (['en', 'ca', 'es'] as $lang) {
            $SESSION->forcelang = $lang;

            $strings = self::setup_strings($lang);

            foreach ($strings as $id => $string) {
                self::assertEquals($string, output\strings::get($id));
            }

            // Parameter replacement.
            self::assertEquals(
                str_replace(['{$a->index}', '{$a->total}'], ['3', '14'], $strings['pagingsingle']),
                output\strings::get('pagingsingle', ['index' => '3', 'total' => '14'])
            );
        }
    }

    public function test_get_all() {
        global $SESSION;

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        foreach (['en', 'ca', 'es'] as $lang) {
            $strings = self::setup_strings($lang);

            $SESSION->forcelang = $lang;

            self::assertEquals($strings, output\strings::get_all());
        }
    }

    public function test_get_ids() {
        global $SESSION;

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        $strings = self::setup_strings('en');

        $SESSION->forcelang = 'en';

        self::assertEquals(array_keys($strings), output\strings::get_ids());
    }

    public function test_get_many() {
        global $SESSION;

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->setUser($user);

        foreach (['en', 'ca', 'es'] as $lang) {
            $strings = self::setup_strings($lang);

            $SESSION->forcelang = $lang;

            $ids = self::random_items(array_keys($strings), 10);
            self::assertEquals(
                array_intersect_key($strings, array_combine($ids, $ids)),
                output\strings::get_many($ids)
            );
        }
    }

    private static function setup_strings(string $lang): array {
        global $CFG;

        make_writable_directory("$CFG->langlocalroot/{$lang}_local");
        $content = "<?php
            defined('MOODLE_INTERNAL') || die();
            \$string['forward'] = 'LOCAL';
        ";
        file_put_contents("$CFG->langlocalroot/{$lang}_local/local_mail.php", $content);

        $string = [];
        include("$CFG->dirroot/local/mail/lang/$lang/local_mail.php");
        $string['forward'] = 'LOCAL';

        return $string;
    }
}
