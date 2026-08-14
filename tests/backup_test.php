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
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024-2025 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail;

/**
 * Unit tests for backing up and restoring plugin messages within a course.
 *
 * @package    local_mail
 * @copyright  2023-2025 Proyecto UNIMOODLE, Albert Gasset
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \backup_local_mail_plugin
 * @covers \restore_local_mail_plugin
 */
final class backup_test extends test\testcase {
    public function setUp(): void {
        global $CFG;

        parent::setUp();

        require_once("$CFG->dirroot/backup/util/includes/backup_includes.php");
        require_once("$CFG->dirroot/backup/util/includes/restore_includes.php");
    }

    public function test_backup_and_restore(): void {
        global $DB;

        set_config('enablebackup', 1, 'local_mail');

        self::generate_random_data(true);
        self::setAdminUser();

        $trashedrestored = 0;
        $notificationsexcluded = 0;
        $refstonotifications = 0;

        foreach (array_keys(get_courses()) as $oldcourseid) {
            $fs = get_file_storage();

            if ($oldcourseid == SITEID) {
                continue;
            }

            // Fetch old records.
            $oldlabels = $DB->get_records('local_mail_labels', [], 'userid, name');
            /*
             * Generated mail is excluded from the backup, so the expected set is the
             * human correspondence only, and every row hanging off a notification goes
             * with it. Filtering here rather than asserting a smaller count keeps the
             * whole-record comparison below intact.
             */
            $allmessages = $DB->get_records('local_mail_messages', ['courseid' => $oldcourseid], 'id');
            $oldmessages = array_filter($allmessages, fn($record) => $record->component === null);
            $notificationsexcluded += count($allmessages) - count($oldmessages);

            $allrefs = $DB->get_records_list('local_mail_message_refs', 'messageid', array_keys($oldmessages), 'id');
            $oldmessagerefs = array_filter($allrefs, fn($record) => isset($oldmessages[$record->reference]));
            $refstonotifications += count($allrefs) - count($oldmessagerefs);

            $carried = fn($record) => isset($oldmessages[$record->messageid]);
            $oldmessageusers = array_filter(
                $DB->get_records('local_mail_message_users', ['courseid' => $oldcourseid], 'id'),
                $carried
            );
            $oldmessagelabels = array_filter(
                $DB->get_records('local_mail_message_labels', ['courseid' => $oldcourseid], 'id'),
                $carried
            );
            $oldfiles = array_filter(
                $fs->get_area_files(\context_course::instance($oldcourseid)->id, 'local_mail', 'message'),
                fn($file) => isset($oldmessages[$file->get_itemid()])
            );

            // Backup course.
            $backupid = self::backup_course($oldcourseid, true);

            // Delete the course and a random label.
            delete_course($oldcourseid, false);
            if ($oldmessagelabels) {
                label::get(self::random_item($oldmessagelabels)->labelid)->delete();
            }

            // Restore course.
            $restorestarted = time();
            $newcourseid = self::restore_course($backupid, true);

            // Fetch new records.
            $idmap = ['courseid' => [$oldcourseid => $newcourseid]];
            $newlabels = $DB->get_records('local_mail_labels', [], 'userid, name');
            $newmessages = $DB->get_records('local_mail_messages', ['courseid' => $newcourseid], 'id');
            $newmessagerefs = $DB->get_records_list('local_mail_message_refs', 'messageid', array_keys($newmessages), 'id');
            $newmessageusers = $DB->get_records('local_mail_message_users', ['courseid' => $newcourseid], 'id');
            $newmessagelabels = $DB->get_records('local_mail_message_labels', ['courseid' => $newcourseid], 'id');
            $newfiles = $fs->get_area_files(\context_course::instance($newcourseid)->id, 'local_mail', 'message');

            // Check restored records and files.
            self::assert_restored_records($oldlabels, $newlabels, $idmap, ['labelid']);
            self::assert_restored_records($oldmessages, $newmessages, $idmap, ['messageid', 'reference']);
            self::assert_restored_records($oldmessagerefs, $newmessagerefs, $idmap);
            self::assert_restored_records($oldmessageusers, $newmessageusers, $idmap, [], ['timedeleted']);
            self::assert_restored_records($oldmessagelabels, $newmessagelabels, $idmap);
            self::assert_restored_files($oldfiles, $newfiles, $idmap);
            $trashedrestored += self::assert_restored_trash_timestamps($newmessageusers, $restorestarted);

            // No generated mail crossed over, and no reference points at anything missing.
            foreach ($newmessages as $record) {
                self::assertNull($record->component);
            }
            foreach ($newmessagerefs as $record) {
                self::assertArrayHasKey($record->reference, $newmessages);
            }
        }

        /*
         * Controls. Each of the three assertions above runs against a set that could be
         * empty, and an empty set proves nothing: without these the test would keep
         * passing if the fixture stopped producing notifications, if restore stopped
         * stamping the trash clock, or if the backup started carrying everything again.
         */
        self::assertGreaterThan(0, $trashedrestored);
        self::assertGreaterThan(0, $notificationsexcluded);
        self::assertGreaterThan(0, $refstonotifications);
    }

    /**
     * Checks that the trash clock restarts when a course is restored.
     *
     * A backup records that a message was in the trash but not when it got there, so
     * restored rows are stamped with the time of the restore instead of an older value
     * that a retention sweep could act on immediately.
     *
     * @param \stdClass[] $records Restored records of local_mail_message_users.
     * @param int $starttime Timestamp taken before the restore started.
     * @return int Number of restored records that were in the trash.
     */
    private static function assert_restored_trash_timestamps(array $records, int $starttime): int {
        $trashed = 0;

        foreach ($records as $record) {
            if ($record->deleted == message::DELETED) {
                $trashed++;
                self::assertGreaterThanOrEqual($starttime, (int) $record->timedeleted);
            } else {
                self::assertEquals(0, $record->timedeleted);
            }
        }

        return $trashed;
    }

    public function test_backup_disabled(): void {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $course = new course($generator->create_course());
        $label1 = label::create($user1, 'Label', 'blue');
        $label2 = label::create($user2, 'Label', 'blue');
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $time;

        $message = message::create($data);
        $message->send(time());
        $message->set_labels($user1, [$label1]);
        $message->set_labels($user2, [$label2]);

        // Backup course with mail backup disabled.
        set_config('enablebackup', 0, 'local_mail');
        $backupid = self::backup_course($course->id, true);

        // Delete labels and courses.
        delete_course($course->id, false);
        $label1->delete();
        $label2->delete();

        // Restore course with mail backup enabled.
        set_config('enablebackup', 1, 'local_mail');
        self::restore_course($backupid, true);

        // Check nothing is restored.
        self::assert_record_count(0, 'messages');
        self::assert_record_count(0, 'message_refs');
        self::assert_record_count(0, 'message_users');
        self::assert_record_count(0, 'message_labels');
        self::assert_record_count(0, 'labels');
    }

    public function test_backup_without_users(): void {
        set_config('enablebackup', 1, 'local_mail');

        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $course = new course($generator->create_course());
        $label1 = label::create($user1, 'Label', 'blue');
        $label2 = label::create($user2, 'Label', 'blue');
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $time;

        $message = message::create($data);
        $message->send(time());
        $message->set_labels($user1, [$label1]);
        $message->set_labels($user2, [$label2]);

        // Backup course without users.
        $backupid = self::backup_course($course->id, false);

        // Delete labels and courses.
        delete_course($course->id, false);
        $label1->delete();
        $label2->delete();

        // Restore course.
        self::restore_course($backupid, false);

        // Check nothing is restored.
        self::assert_record_count(0, 'messages');
        self::assert_record_count(0, 'message_refs');
        self::assert_record_count(0, 'message_users');
        self::assert_record_count(0, 'message_labels');
        self::assert_record_count(0, 'labels');
    }

    public function test_restore_disabled(): void {
        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $course = new course($generator->create_course());
        $label1 = label::create($user1, 'Label', 'blue');
        $label2 = label::create($user2, 'Label', 'blue');
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $time;

        $message = message::create($data);
        $message->send(time());
        $message->set_labels($user1, [$label1]);
        $message->set_labels($user2, [$label2]);

        // Backup course with mail backup enabled.
        set_config('enablebackup', 1, 'local_mail');
        $backupid = self::backup_course($course->id, true);

        // Delete labels and courses.
        delete_course($course->id, false);
        $label1->delete();
        $label2->delete();

        // Restore course with mail backup disabled.
        set_config('enablebackup', 0, 'local_mail');
        self::restore_course($backupid, true);

        // Check nothing is restored.
        self::assert_record_count(0, 'messages');
        self::assert_record_count(0, 'message_refs');
        self::assert_record_count(0, 'message_users');
        self::assert_record_count(0, 'message_labels');
        self::assert_record_count(0, 'labels');
    }

    public function test_restore_drops_a_reference_whose_target_is_missing(): void {
        global $DB;

        set_config('enablebackup', 1, 'local_mail');

        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $messages = [];
        foreach ([$course1, $course2] as $course) {
            $data = message_data::new($course, $user1);
            $data->to = [$user2];
            $data->subject = 'Subject';
            $data->content = 'Content';
            $data->format = (int) FORMAT_PLAIN;
            $data->time = $time;
            $message = message::create($data);
            $message->send($time);
            $messages[] = $message;
        }

        /*
         * A reference across courses. These have existed in the wild — db/upgrade.php
         * carries a step that deletes them — and they are the case the backup cannot
         * filter at source: the join there finds the target row and exports the
         * reference, but restoring one course alone leaves nothing to map it to.
         */
        $DB->insert_record('local_mail_message_refs', [
            'messageid' => $messages[0]->id,
            'reference' => $messages[1]->id,
        ]);

        $backupid = self::backup_course($course1->id, true);
        delete_course($course1->id, false);
        $newcourseid = self::restore_course($backupid, true);

        // Control: the message itself came across, so the reference really was exported.
        $newmessages = $DB->get_records('local_mail_messages', ['courseid' => $newcourseid]);
        self::assertCount(1, $newmessages);

        $newrefs = $DB->get_records_list(
            'local_mail_message_refs',
            'messageid',
            array_keys($newmessages)
        );

        self::assertEquals([], $newrefs);
    }

    public function test_restore_without_users(): void {
        set_config('enablebackup', 1, 'local_mail');

        $generator = self::getDataGenerator();
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $course = new course($generator->create_course());
        $label1 = label::create($user1, 'Label', 'blue');
        $label2 = label::create($user2, 'Label', 'blue');
        $time = make_timestamp(2021, 10, 11, 12, 0);

        $data = message_data::new($course, $user1);
        $data->to = [$user2];
        $data->subject = 'Subject';
        $data->content = 'Content';
        $data->format = (int) FORMAT_PLAIN;
        $data->time = $time;

        $message = message::create($data);
        $message->send(time());
        $message->set_labels($user1, [$label1]);
        $message->set_labels($user2, [$label2]);

        // Backup course with users.
        $backupid = self::backup_course($course->id, true);

        // Delete labels and courses.
        delete_course($course->id, false);
        $label1->delete();
        $label2->delete();

        // Restore course without users.
        self::restore_course($backupid, false);

        // Check nothing is restored.
        self::assert_record_count(0, 'messages');
        self::assert_record_count(0, 'message_refs');
        self::assert_record_count(0, 'message_users');
        self::assert_record_count(0, 'message_labels');
        self::assert_record_count(0, 'labels');
    }

    /**
     * Checks that restored files match original files.
     *
     * @param \stored_file[] $oldfiles Original files.
     * @param \stored_file[] $newfiles Restored files, with the same order as original files.
     * @param int[][] $idmap Map of fields to arrays of old IDs to new IDs.
     */
    private static function assert_restored_files(array $oldfiles, array $newfiles, array $idmap) {
        self::assertCount(count($oldfiles), $newfiles);

        foreach ($oldfiles as $oldfile) {
            $newfile = current($newfiles);
            $messageid = $idmap['messageid'][$oldfile->get_itemid()];
            self::assertEquals($messageid, $newfile->get_itemid());
            self::assertEquals($oldfile->get_filename(), $newfile->get_filename());
            self::assertEquals($oldfile->get_content(), $newfile->get_content());
            next($newfiles);
        }
    }

    /**
     * Checks that restored records match original records.
     *
     * @param \stdClass[] $oldrecords Original records.
     * @param \stdClass[] $newrecords Restored records, with the same order as original records.
     * @param int[][] $idmap Map of fields to arrays of old IDs to new IDs.
     * @param string[] $idmapfields Fields to add to the ID map with the IDs of the new records.
     * @param string[] $skipfields Fields the restore is expected to change, compared separately.
     */
    private static function assert_restored_records(
        array $oldrecords,
        array $newrecords,
        array &$idmap,
        array $idmapfields = [],
        array $skipfields = []
    ) {
        self::assertCount(count($oldrecords), $newrecords);

        foreach ($oldrecords as $oldrecord) {
            $newrecord = current($newrecords);
            foreach ($idmapfields as $field) {
                $idmap[$field][$oldrecord->id] = $newrecord->id;
            }

            // Clone before unsetting: these are the caller's own records, not copies.
            $oldrecord = clone $oldrecord;
            $newrecord = clone $newrecord;

            unset($oldrecord->id);
            unset($newrecord->id);
            foreach ($skipfields as $field) {
                unset($oldrecord->$field);
                unset($newrecord->$field);
            }
            foreach ($oldrecord as $field => $value) {
                if (isset($idmap[$field])) {
                    $oldrecord->$field = $idmap[$field][$value];
                }
            }
            self::assertEquals($oldrecord, $newrecord);
            next($newrecords);
        }
    }

    /**
     * Makes a backup of the course.
     *
     * @param int $courseid Course ID.
     * @param bool $userdata Include user data.
     * @return string Unique identifier for this backup.
     */
    private static function backup_course(int $courseid, bool $userdata): string {
        global $CFG, $USER;

        // Workaround for bug introduced in MDL-81119.
        $CFG->forced_plugin_settings['backup'] ??= [];

        // Do backup with default settings. MODE_IMPORT means it will just
        // create the directory and not zip it.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        $backupid = $bc->get_backupid();

        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restores a backup that has been made earlier.
     *
     * @param string $backupid The unique identifier of the backup.
     * @param bool $userdata Include user data.
     * @return int The new course id.
     */
    private static function restore_course(string $backupid, bool $userdata) {
        global $DB, $USER;

        static $coursenumber = 0;

        $coursenumber++;
        $fullname = "Restored course $coursenumber";
        $shortname = "Restored $coursenumber";
        $categoryid = $DB->get_field_select('course_categories', "MIN(id)", "parent=0");

        $newcourseid = \restore_dbops::create_new_course($fullname, $shortname, $categoryid);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value($userdata);

        self::assertTrue($rc->execute_precheck());

        ob_start();
        $rc->execute_plan();
        ob_end_clean();

        $rc->destroy();

        return $newcourseid;
    }
}
