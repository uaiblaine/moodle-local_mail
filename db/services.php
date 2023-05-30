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

$functions = array(
    'local_mail_get_info' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'get_info',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get settings and user preferences.',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_set_preferences' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'set_preferences',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the user preferences.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_get_unread_count' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'get_unread_count',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get the number of unread messages.',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_get_menu' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'get_menu',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get the list of courses and labels and the number of unread messages.',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_get_index' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'get_index',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get a list of messages from the index.',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_search_index' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'search_index',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Search messages from the index.',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_get_message' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'get_message',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Get the contents of a message.',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_find_offset' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'find_offset',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Find the offset of a message in the index.',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_set_unread' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'set_unread',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the unread status of a message.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_set_starred' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'set_starred',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the starred status of a message.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_set_deleted' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'set_deleted',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the deleted status of a message.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_empty_trash' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'empty_trash',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Empties the trash.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_create_label' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'create_label',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Creates a new label.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_update_label' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'update_label',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Updates a label.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_delete_label' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'delete_label',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Deletes a label.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
    'local_mail_set_labels' => array(
        'classname' => 'local_mail_external',
        'methodname' => 'set_labels',
        'classpath' => 'local/mail/externallib.php',
        'description' => 'Sets the labels of a message.',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'),
    ),
);
