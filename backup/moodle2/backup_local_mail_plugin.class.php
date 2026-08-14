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
 * SPDX-FileCopyrightText: 2016 Albert Gasset <albertgasset@fsfe.org>
 * SPDX-FileCopyrightText: 2017 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Adds the messages of a course to its backup file.
 *
 * @package    local_mail
 * @copyright  2016-2024 Albert Gasset, Marc Català, Proyecto UNIMOODLE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_mail_plugin extends backup_local_plugin {
    /**
     * Builds the backup tree of the messages of the course, with their references, users and labels.
     *
     * @return backup_plugin_element|null Root element, or null if backups are disabled, user data is
     *                                    not included, or the backup is anonymized.
     */
    protected function define_course_plugin_structure() {
        if (!get_config('local_mail', 'enablebackup')) {
            return;
        }

        if (!$this->get_setting_value('users') || $this->get_setting_value('anonymize')) {
            return;
        }

        $plugin = $this->get_plugin_element(null);

        // Elements.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $messages = new backup_nested_element('messages');
        $elements = ['courseid', 'subject', 'content', 'format', 'attachments', 'draft', 'time', 'component'];
        $message = new backup_nested_element('message', ['id'], $elements);
        $refs = new backup_nested_element('refs');
        $ref = new backup_nested_element('ref', ['id'], ['reference']);
        $users = new backup_nested_element('users');
        $user = new backup_nested_element('user', ['id'], ['userid', 'role', 'unread', 'starred', 'deleted']);
        $labels = new backup_nested_element('labels');
        $label = new backup_nested_element('label', ['id'], ['userid', 'name', 'color']);

        // Tree.
        $plugin->add_child($pluginwrapper);
        $pluginwrapper->add_child($messages);
        $messages->add_child($message);
        $message->add_child($refs);
        $refs->add_child($ref);
        $message->add_child($users);
        $users->add_child($user);
        $message->add_child($labels);
        $labels->add_child($label);

        // Messages source.
        $message->set_source_table('local_mail_messages', ['courseid' => backup::VAR_COURSEID], 'id');

        // Users source.
        // Roles are stored by name, for compatibility with older versions of the plugin.
        $ref->set_source_table('local_mail_message_refs', ['messageid' => '../../id'], 'id');
        $rolesql = 'CASE';
        foreach (\local_mail\message::role_names() as $role => $name) {
            $rolesql .= " WHEN role = $role THEN '$name'";
        }
        $rolesql .= ' END';
        $sql = "SELECT id, userid, $rolesql AS role, unread, starred, deleted
                FROM {local_mail_message_users}
                WHERE messageid = ?
                ORDER BY id";

        // Labels source.
        $user->set_source_sql($sql, ['messageid' => '../../id']);
        $sql = 'SELECT ml.id, l.userid, l.name, l.color
                FROM {local_mail_message_labels} ml
                JOIN {local_mail_labels} l ON l.id = ml.labelid
                WHERE ml.messageid = ?
                ORDER BY ml.id';
        $label->set_source_sql($sql, ['messageid' => '../../id']);

        // ID annotations.
        $user->annotate_ids('user', 'userid');
        $label->annotate_ids('user', 'userid');

        // File annotations.
        $message->annotate_files('local_mail', 'message', 'id');

        return $plugin;
    }
}
