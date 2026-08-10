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

/*
 * SPDX-FileCopyrightText: 2017 Albert Gasset <albertgasset@fsfe.org>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Web service function definitions.
 *
 * @package    local_mail
 * @copyright  2017-2024 Albert Gasset, Proyecto UNIMOODLE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mail_get_settings' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_settings',
        'description' => 'Get site settings.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_strings' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_strings',
        'description' => 'Get language strings.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_preferences' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_preferences',
        'description' => 'Get user preferences.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_set_preferences' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_preferences',
        'description' => 'Set user preferences.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_get_courses' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_courses',
        'description' => 'Get user courses.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_labels' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_labels',
        'description' => 'Get user labels.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_count_messages' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'count_messages',
        'description' => 'Count the number of messages.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_search_messages' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'search_messages',
        'description' => 'Search messages.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_message' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_message',
        'description' => 'Get the contents of a message.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_view_message' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'view_message',
        'description' => 'Marks a message as read and triggers a viewed event.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_set_unread' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_unread',
        'description' => 'Sets the unread status of a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_set_starred' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_starred',
        'description' => 'Sets the starred status of a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_set_deleted' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_deleted',
        'description' => 'Sets the deleted status of a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_create_label' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'create_label',
        'description' => 'Creates a new label.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_update_label' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'update_label',
        'description' => 'Updates a label.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_delete_label' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'delete_label',
        'description' => 'Deletes a label.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_set_labels' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'set_labels',
        'description' => 'Sets the labels of a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_empty_trash' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'empty_trash',
        'description' => 'Empties the trash.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_get_roles' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_roles',
        'description' => 'Get course roles.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_groups' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_groups',
        'description' => 'Get course groups.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_search_users' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'search_users',
        'description' => 'Search course users.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_mail_get_message_form' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'get_message_form',
        'description' => 'Creates a forwarded message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_create_message' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'create_message',
        'description' => 'Creates a new message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_reply_message' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'reply_message',
        'description' => 'Creates a message reply.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_forward_message' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'forward_message',
        'description' => 'Creates a forwarded message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_update_message' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'update_message',
        'description' => 'Updates a message.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_mail_send_message' => [
        'classname' => 'local_mail\\external',
        'methodname' => 'send_message',
        'description' => 'Sends a message.',
        'type' => 'write',
        'ajax' => true,
    ],
];
