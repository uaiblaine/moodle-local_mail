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

namespace local_mail\output;

use local_mail\course;
use local_mail\settings;
use local_mail\user;

class mobile {

    public static function init() {
        global $CFG;

        $user = user::current();

        if (!settings::is_installed() || !$user || !course::fetch_by_user($user)) {
            return ['disabled' => true];
        }

        return [
            'javascript' => file_get_contents("$CFG->dirroot/local/mail/classes/output/mobile-init.js"),
        ];
    }

    public static function view(array $args) {
        global $CFG;

        $url = new \moodle_url('/local/mail/view.php', $args);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '<core-iframe src="' . $url->out(false) . '"></core-iframe>'
                ],
            ],
            'javascript' => file_get_contents("$CFG->dirroot/local/mail/classes/output/mobile-view.js"),
        ];
    }

}
