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
 * @covers \local_mail\exception
 */
class exception_test extends testcase {

    public function test_construct() {
        $exception = new exception('errortoomanyrecipients', 123, 'debug info');

        self::assertEquals('errortoomanyrecipients', $exception->errorcode);
        self::assertEquals('local_mail', $exception->module);
        self::assertEquals(123, $exception->a);
        self::assertEquals('', $exception->link);
        self::assertEquals('debug info', $exception->debuginfo);
    }
}
