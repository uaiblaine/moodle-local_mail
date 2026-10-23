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
 * SPDX-FileCopyrightText: 2026 Anderson Blaine
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use local_mail\label;
use local_mail\user;

/**
 * Unit tests for the labels a user keeps in their own user context, under the Privacy API.
 *
 * Labels are the plugin's only data in a user context. {@see provider::get_contexts_for_userid()}
 * reports that context, so the user list, the context-wide deletion and the per-user deletion
 * must all reach it as well. Every test that asserts a label survived also deletes or lists a
 * label it is required to, so none of them passes by doing nothing.
 *
 * Coverage is declared in this docblock rather than with attributes because the moodle-cs of the
 * Moodle 4.5 leg cannot read attributes.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\privacy\provider
 */
final class provider_labels_test extends \local_mail\test\testcase {
    /**
     * Creates a user with the given number of labels.
     *
     * @param int $count Number of labels.
     * @return user The user.
     */
    private function create_user_with_labels(int $count): user {
        $user = user::get((int) $this->getDataGenerator()->create_user()->id);
        for ($i = 1; $i <= $count; $i++) {
            label::create($user, "Label $i");
        }
        return $user;
    }

    /**
     * Counts the labels of a user in the database.
     *
     * @param user $user The user.
     * @return int Number of label rows.
     */
    private function count_labels(user $user): int {
        global $DB;
        return $DB->count_records('local_mail_labels', ['userid' => $user->id]);
    }

    /**
     * The plugin passes core's privacy compliance check.
     *
     * Core's own compliance test sweeps every component but is not in the plugin's test suite,
     * which is all moodle-plugin-ci runs, so the check is repeated here
     * ({@see \core_privacy\manager::component_is_compliant()}).
     *
     * @return void
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('local_mail'));
    }

    /**
     * The owner of a user context with labels is in its user list; a user without labels is not.
     *
     * @return void
     */
    public function test_the_label_owner_is_listed_in_their_user_context(): void {
        $owner = $this->create_user_with_labels(2);
        $other = $this->create_user_with_labels(1);
        $nolabels = user::get((int) $this->getDataGenerator()->create_user()->id);

        $userlist = new userlist(\context_user::instance($owner->id), 'local_mail');
        provider::get_users_in_context($userlist);
        $this->assertSame([$owner->id], array_map('intval', $userlist->get_userids()));

        // Control: the same lookup finds nobody in the context of a user without labels.
        $userlist = new userlist(\context_user::instance($nolabels->id), 'local_mail');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());

        // The context reported for the owner is the one the user list now answers for.
        $this->assertContains(
            \context_user::instance($owner->id)->id,
            provider::get_contexts_for_userid($owner->id)->get_contextids()
        );
        $this->assertSame(1, $this->count_labels($other));
    }

    /**
     * Deleting all the data of a user context removes the labels of its owner only.
     *
     * @return void
     */
    public function test_deleting_a_user_context_removes_its_owners_labels(): void {
        global $DB;

        $owner = $this->create_user_with_labels(2);
        $other = $this->create_user_with_labels(1);
        $labelids = array_keys(label::get_by_user($owner));
        foreach ($labelids as $labelid) {
            $DB->insert_record('local_mail_message_labels', (object) [
                'messageid' => 1,
                'courseid' => 1,
                'draft' => 0,
                'time' => 0,
                'labelid' => $labelid,
                'role' => 0,
                'unread' => 0,
                'starred' => 0,
                'deleted' => 0,
            ]);
        }

        provider::delete_data_for_all_users_in_context(\context_user::instance($owner->id));

        $this->assertSame(0, $this->count_labels($owner));
        [$sql, $params] = $DB->get_in_or_equal($labelids);
        $this->assertSame(0, $DB->count_records_select('local_mail_message_labels', "labelid $sql", $params));
        $this->assertSame(1, $this->count_labels($other));
    }

    /**
     * A deletion for one user in another user's context leaves both users' labels alone.
     *
     * The control deletes the same user's labels from their own context.
     *
     * @return void
     */
    public function test_deleting_for_a_user_in_another_users_context_keeps_the_labels(): void {
        $owner = $this->create_user_with_labels(2);
        $subject = $this->create_user_with_labels(1);

        provider::delete_data_for_user(new approved_contextlist(
            \core_user::get_user($subject->id),
            'local_mail',
            [\context_user::instance($owner->id)->id]
        ));

        $this->assertSame(2, $this->count_labels($owner));
        $this->assertSame(1, $this->count_labels($subject));

        // Control: the subject's own context does lose the subject's labels.
        provider::delete_data_for_user(new approved_contextlist(
            \core_user::get_user($subject->id),
            'local_mail',
            [\context_user::instance($subject->id)->id]
        ));

        $this->assertSame(0, $this->count_labels($subject));
        $this->assertSame(2, $this->count_labels($owner));
    }

    /**
     * A user list deletion in a user context removes the owner's labels and no one else's.
     *
     * @return void
     */
    public function test_deleting_a_user_list_in_a_user_context_removes_only_the_owners_labels(): void {
        $owner = $this->create_user_with_labels(2);
        $other = $this->create_user_with_labels(1);
        $context = \context_user::instance($owner->id);

        provider::delete_data_for_users(new approved_userlist($context, 'local_mail', [$owner->id, $other->id]));

        $this->assertSame(0, $this->count_labels($owner));
        $this->assertSame(1, $this->count_labels($other));
    }
}
