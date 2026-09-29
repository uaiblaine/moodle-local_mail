<?php
/*
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024-2026 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace local_mail;

/**
 * @covers \local_mail\observer
 */
final class observer_test extends test\testcase {
    public function test_course_deleted(): void {
        $generator = self::getDataGenerator();
        $course1 = new course($generator->create_course());
        $course2 = new course($generator->create_course());
        $user1 = new user($generator->create_user());
        $user2 = new user($generator->create_user());
        $user3 = new user($generator->create_user());
        $label1 = label::create($user1, 'Label 1');
        $label2 = label::create($user2, 'Label 2');
        $time = make_timestamp(2021, 10, 11, 12, 0);

        // Messages in course to be deleted.

        $data1 = message_data::new($course1, $user1);
        $data1->subject = 'Subject 1';
        $data1->content = 'Content 1';
        $data1->to = [$user2, $user3];
        $data1->time = $time;
        self::create_draft_file($data1->draftitemid, 'file1.txt', 'File 1');
        $message1 = message::create($data1);
        $message1->send($time);
        $message1->set_labels($user2, [$label2]);

        $data2 = message_data::reply($message1, $user2, false);
        $data2->time = $time;
        $message2 = message::create($data2);
        $message2->send($time);

        $data3 = message_data::new($course1, $user3);
        $data3->subject = 'Subject 3';
        $data3->to = [$user1];
        $message3 = message::create($data3);

        // Messages in other course.

        $data4 = message_data::new($course2, $user2);
        $data4->subject = 'Subject 4';
        $data4->content = 'Content 4';
        $data4->to = [$user1];
        $data4->time = $time;
        self::create_draft_file($data4->draftitemid, 'file4.txt', 'File 4');
        $message4 = message::create($data4);
        $message4->send($time);
        $message4->set_labels($user1, [$label1]);

        $course = $course1;
        $context = $course->get_context();

        $fs = get_file_storage();

        delete_course($course->id, false);

        self::assert_record_count(0, 'messages', ['courseid' => $course->id]);
        self::assert_record_count(0, 'message_users', ['courseid' => $course->id]);
        self::assert_record_count(0, 'message_labels', ['courseid' => $course->id]);
        foreach ([$message1, $message2, $message3] as $message) {
            self::assert_record_count(0, 'message_refs', ['messageid' => $message->id]);
            self::assert_record_count(0, 'message_refs', ['reference' => $message->id]);
        }
        self::assert_message($message4);
        self::assert_attachments(['file4.txt' => 'File 4'], $message4);
        self::assertEmpty($fs->get_area_files($context->id, 'local_mail', 'message'));
    }
}
