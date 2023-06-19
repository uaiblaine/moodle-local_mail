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

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mail_get_settings' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_settings',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get site settings.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_strings' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_strings',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get language strings.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_preferences' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_preferences',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get user preferences.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_set_preferences' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_preferences',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Set user preferences.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_get_courses' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_courses',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get user courses.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_labels' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_labels',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get user labels.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_count_messages' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'count_messages',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Count the number of messages.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_search_messages' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'search_messages',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Search messages.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_message' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_message',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get the contents of a message.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_set_unread' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_unread',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the unread status of a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_set_starred' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_starred',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the starred status of a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_set_deleted' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_deleted',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the deleted status of a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_create_label' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'create_label',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Creates a new label.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_update_label' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'update_label',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Updates a label.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_delete_label' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'delete_label',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Deletes a label.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_set_labels' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_labels',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the labels of a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_empty_trash' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'empty_trash',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Empties the trash.',
        'type' => 'write',
        'ajax' => true,
    ],
];
