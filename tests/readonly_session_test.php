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

/**
 * Tests that the services declared read-only leave the session as they found it.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mail;

use core_external\external_api;

/**
 * A service declared 'readonlysession' runs without the session lock, so nothing it writes to
 * $SESSION survives the request.
 *
 * Core's contract for a read-only session is the one write_close() checks
 * (lib/classes/session/manager.php, the array_session_diff() of $SESSION taken at session start
 * and at close): no $SESSION key added, removed or changed. That is what is asserted here, per
 * service and per argument set, around a call made the way the browser makes it. $USER is not
 * compared, as core does not compare it.
 *
 * The metadata stays in docblocks rather than attributes because the plugin still supports
 * Moodle 4.5, whose moodle-cs cannot see attributes and warns on every method.
 *
 * @package    local_mail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_mail\external
 */
final class readonly_session_test extends test\testcase {
    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant in $_POST.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * The services that must hold no session lock, each with the argument sets that reach every
     * branch of its path in the fixture below.
     *
     * The first set of local_mail_count_messages is the query amd/src/navbar.js sends on every page;
     * the inbox query is the one the mailbox's list batch sends (svelte/src/lib/store.ts).
     *
     * @param course $course The fixture's course.
     * @param label $label The fixture's label.
     * @return array Service name => list of argument arrays.
     */
    private static function read_only_calls(course $course, label $label): array {
        $navbar = ['roles' => ['to', 'cc', 'bcc'], 'category' => 'primary', 'unread' => true];
        $inbox = ['roles' => ['to', 'cc', 'bcc'], 'category' => 'primary', 'deleted' => false];

        return [
            'local_mail_count_messages' => [
                ['query' => $navbar],
                ['query' => ['courseid' => $course->id] + $navbar],
                ['query' => ['labelid' => $label->id]],
            ],
            'local_mail_get_courses' => [[]],
            'local_mail_get_labels' => [[]],
            'local_mail_search_messages' => [
                ['query' => $inbox, 'offset' => 0, 'limit' => 20],
                ['query' => ['courseid' => $course->id] + $inbox, 'offset' => 0, 'limit' => 20],
                ['query' => ['labelid' => $label->id, 'content' => 'Probe'], 'offset' => 0, 'limit' => 20],
            ],
            'local_mail_search_users' => [
                ['query' => ['courseid' => $course->id], 'offset' => 0, 'limit' => 20],
                ['query' => ['courseid' => $course->id, 'fullname' => 'Probe'], 'offset' => 0, 'limit' => 20],
            ],
        ];
    }

    /**
     * The control of each call: what it returned shows it found the fixture, so its whole path ran
     * and an unchanged session is not the session of a call that stopped early.
     *
     * @param string $name The service.
     * @param mixed $data What it returned.
     * @param course $course The fixture's course.
     * @param label $label The fixture's label.
     * @param user $sender The fixture's sender, whom the recipient can find in the course.
     * @param string $case The failure message prefix.
     * @return void
     */
    private function assert_found(string $name, $data, course $course, label $label, user $sender, string $case): void {
        match ($name) {
            'local_mail_count_messages' => $this->assertSame(1, $data, $case),
            'local_mail_get_courses' => $this->assertSame(
                [[$course->id, 1]],
                array_map(fn(array $row): array => [$row['id'], $row['unread']], $data),
                $case
            ),
            'local_mail_get_labels' => $this->assertSame(
                [[$label->id, 1]],
                array_map(fn(array $row): array => [$row['id'], $row['unread']], $data),
                $case
            ),
            'local_mail_search_messages' => $this->assertSame(
                [$sender->id],
                array_map(fn(array $row): int => $row['sender']['id'], $data),
                $case
            ),
            'local_mail_search_users' => $this->assertContains($sender->id, array_column($data, 'id'), $case),
        };
    }

    /**
     * Read the plugin's db/services.php as core does.
     *
     * @return array The $functions array it declares.
     */
    private static function declared_functions(): array {
        $functions = null;
        include(\core_component::get_component_directory('local_mail') . '/db/services.php');

        return $functions;
    }

    /**
     * $SESSION as core compares it: one entry per key, the value serialised so a change inside a
     * nested object is seen too.
     *
     * @return array Key => serialised value.
     */
    private static function session_snapshot(): array {
        global $SESSION;

        return array_map('serialize', (array) $SESSION);
    }

    /**
     * The $SESSION keys added, removed or changed between two snapshots.
     *
     * @param array $before Snapshot taken before.
     * @param array $after Snapshot taken after.
     * @return array The keys, sorted.
     */
    private static function changed_keys(array $before, array $after): array {
        $keys = array_keys(array_diff_assoc($before, $after) + array_diff_assoc($after, $before));
        sort($keys);

        return $keys;
    }

    /**
     * The services this test exercises are exactly the ones declared read-only, all of them reads,
     * and core's own reader of db/services.php sees the flag.
     *
     * Reading through external_function_info() is the point: it is the value
     * external_api::call_external_function() decides the session lock from.
     *
     * @return void
     */
    public function test_exactly_the_exercised_services_are_declared_read_only(): void {
        $functions = self::declared_functions();
        $declared = array_keys(array_filter($functions, fn(array $function): bool => !empty($function['readonlysession'])));
        sort($declared);
        $course = new course($this->getDataGenerator()->create_course());
        $label = label::create(user::current(), 'Probe');
        $exercised = array_keys(self::read_only_calls($course, $label));
        sort($exercised);

        $this->assertSame($exercised, $declared);
        foreach ($declared as $name) {
            $this->assertSame('read', $functions[$name]['type'], $name);
            $this->assertTrue(external_api::external_function_info($name)->readonlysession, $name);
        }
    }

    /**
     * The comparison sees a write, so an empty result below is not an empty comparison.
     *
     * @return void
     */
    public function test_the_snapshot_sees_a_write(): void {
        global $SESSION;

        $before = self::session_snapshot();
        $SESSION->local_mail_probe = (object) ['seen' => 1];
        $added = self::session_snapshot();
        $SESSION->local_mail_probe->seen = 2;

        $this->assertSame(['local_mail_probe'], self::changed_keys($before, $added));
        $this->assertSame(['local_mail_probe'], self::changed_keys($added, self::session_snapshot()));
    }

    /**
     * Every read-only service leaves $SESSION exactly as it found it, with every argument set.
     *
     * The recipient has one unread message in the course, from a sender named Probe, with subject
     * Probe and carrying a label, so each call finds something: the course list, the course and
     * label checks, the message listing with its renderer and user pictures, and the user search
     * all run. The plugin's
     * request caches are purged before every call so the course list is rebuilt inside it. The
     * read-only branch of external_api::call_external_function() is taken, as on a site with
     * $CFG->enable_read_only_sessions on.
     *
     * One require_login() runs before each snapshot. Core's after_require_login callbacks run
     * inside every service of every component, and under PHPUnit tool_mfa's writes a marker
     * ($SESSION->mfa_login_hook_test, admin/tool/mfa/lib.php); paying it first keeps that core
     * write out of the comparison without exempting any key from it.
     *
     * @return void
     */
    public function test_read_only_services_leave_the_session_untouched(): void {
        global $CFG;

        $generator = $this->getDataGenerator();
        $course = new course($generator->create_course());
        $sender = new user($generator->create_user(['firstname' => 'Probe', 'lastname' => 'Sender']));
        $recipient = new user($generator->create_user());
        $generator->enrol_user($sender->id, $course->id);
        $generator->enrol_user($recipient->id, $course->id);
        $message = self::create_message($course, $sender, [$recipient], [], [], 'Probe', time() - 3600);
        $this->setUser($recipient->id);
        $label = label::create($recipient, 'Probe');
        $message->set_labels($recipient, [$label]);
        $CFG->enable_read_only_sessions = true;

        foreach (self::read_only_calls($course, $label) as $name => $argsets) {
            foreach ($argsets as $index => $args) {
                course::cache()->purge();
                label::cache()->purge();
                require_login();
                $_POST['sesskey'] = sesskey();

                $before = self::session_snapshot();
                $result = external_api::call_external_function($name, $args, true);
                $after = self::session_snapshot();

                $this->assertFalse($result['error'], "$name #$index: " . json_encode($result['exception'] ?? null));
                $this->assert_found($name, $result['data'], $course, $label, $sender, "$name #$index");
                $this->assertSame([], self::changed_keys($before, $after), "$name #$index changed \$SESSION");
            }
        }
    }
}
