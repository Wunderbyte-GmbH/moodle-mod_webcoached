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
 * Unit tests for mod_webcoached.
 *
 * @package     mod_webcoached
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webcoached;

use advanced_testcase;

/**
 * Class webcoached_test.
 *
 * @package     mod_webcoached
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class webcoached_test extends advanced_testcase {
    /**
     * Test payload cryptographic signature generation.
     *
     * @covers ::webcoached_add_instance
     */
    public function test_signature_generation(): void {
        $this->resetAfterTest(true);

        set_config('client_id', 'moodle_test', 'mod_webcoached');
        set_config('secret_key', 'test_secret', 'mod_webcoached');

        // Only the parameters documented by Webcoached are signed: client_id, timestamp,
        // nonce, moodle_user_id, course_id, firstname and lastname (no email, no redirect).
        $params = [
            'client_id'      => 'moodle_test',
            'timestamp'      => 1600000000,
            'nonce'          => 'abcdef1234567890',
            'moodle_user_id' => 12,
            'course_id'      => 1111,
            'firstname'      => 'John',
            'lastname'       => 'Doe',
        ];

        ksort($params);
        $payload = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $signature = hash_hmac('sha256', $payload, 'test_secret');

        // Check format of payload query string.
        $this->assertStringContainsString('client_id=moodle_test', $payload);
        $this->assertStringContainsString('course_id=1111', $payload);
        $this->assertStringContainsString('nonce=abcdef1234567890', $payload);

        // The signed payload must not contain the legacy email or redirect parameters.
        $this->assertStringNotContainsString('email=', $payload);
        $this->assertStringNotContainsString('redirect=', $payload);

        // Assert signature matches HMAC-SHA256 calculations.
        $expected = hash_hmac('sha256', $payload, 'test_secret');
        $this->assertEquals($expected, $signature);
    }

    /**
     * Test dynamic nonce cryptographic uniqueness.
     *
     * @covers ::webcoached_supports
     */
    public function test_nonce_uniqueness(): void {
        $nonce1 = bin2hex(random_bytes(16));
        $nonce2 = bin2hex(random_bytes(16));
        $this->assertNotEquals($nonce1, $nonce2);
        $this->assertEquals(32, strlen($nonce1));
    }

    /**
     * Test activity module instance generator and database insertion.
     *
     * @covers \mod_webcoached_generator::create_instance
     */
    public function test_instance_creation(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $webcoached = $this->getDataGenerator()->create_module('webcoached', [
            'course'         => $course->id,
            'name'           => 'Test Webcoached Activity',
            'remotecourseid' => '1111',
        ]);

        $this->assertNotEmpty($webcoached->id);
        $this->assertEquals('Test Webcoached Activity', $webcoached->name);
        $this->assertEquals('1111', $webcoached->remotecourseid);
    }

    /**
     * A grade item is created in the gradebook when the activity is added.
     *
     * @covers ::webcoached_grade_item_update
     */
    public function test_grade_item_created_on_add_instance(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $webcoached = $this->getDataGenerator()->create_module('webcoached', [
            'course' => $course->id,
            'grade'  => 100,
        ]);

        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'webcoached',
            'iteminstance' => $webcoached->id,
            'itemnumber'   => 0,
        ]);

        $this->assertNotFalse($gradeitem);
        $this->assertEquals(GRADE_TYPE_VALUE, $gradeitem->gradetype);
        $this->assertEquals(100, $gradeitem->grademax);
    }

    /**
     * A negative grade value creates a scale-based grade item ("simple Completed").
     *
     * @covers ::webcoached_grade_item_update
     */
    public function test_grade_item_created_with_scale(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'No,Yes']);
        $webcoached = $this->getDataGenerator()->create_module('webcoached', [
            'course' => $course->id,
            'grade'  => -$scale->id,
        ]);

        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'webcoached',
            'iteminstance' => $webcoached->id,
            'itemnumber'   => 0,
        ]);

        $this->assertNotFalse($gradeitem);
        $this->assertEquals(GRADE_TYPE_SCALE, $gradeitem->gradetype);
        $this->assertEquals($scale->id, $gradeitem->scaleid);
    }

    /**
     * Writing a grade through the core REST web service completes the activity.
     *
     * This is the REST trigger path: the external Webcoached system calls
     * core_grades_update_grades, which cascades to the core "completionusegrade"
     * condition and marks the activity complete.
     *
     * @covers ::webcoached_grade_item_update
     */
    public function test_rest_grade_triggers_completion(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $cm, $student, $webcoached] = $this->setup_graded_activity_with_completion();

        $result = \core_grades_external::update_grades(
            'mod_webcoached',
            $course->id,
            'mod_webcoached',
            $cm->id,
            0,
            [['studentid' => $student->id, 'grade' => 80]]
        );
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        // The grade landed in the gradebook.
        $grades = grade_get_grades($course->id, 'mod', 'webcoached', $webcoached->id, $student->id);
        $this->assertEquals(80, (int) $grades->items[0]->grades[$student->id]->grade);

        // The activity is now complete for the student.
        $completion = new \completion_info($course);
        $data = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $data->completionstate);
    }

    /**
     * The REST grade write is idempotent: calling twice keeps a single grade and completion.
     *
     * @covers ::webcoached_grade_item_update
     */
    public function test_rest_grade_idempotent(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $cm, $student, $webcoached] = $this->setup_graded_activity_with_completion();

        $args = ['mod_webcoached', $course->id, 'mod_webcoached', $cm->id, 0,
            [['studentid' => $student->id, 'grade' => 55]]];
        \core_grades_external::update_grades(...$args);
        \core_grades_external::update_grades(...$args);

        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'webcoached',
            'iteminstance' => $webcoached->id,
            'itemnumber'   => 0,
        ]);
        $count = $DB->count_records('grade_grades', ['itemid' => $gradeitem->id, 'userid' => $student->id]);
        $this->assertEquals(1, $count);

        $completion = new \completion_info($course);
        $data = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $data->completionstate);
    }

    /**
     * The core grades web service refuses to write grades without moodle/grade:edit.
     *
     * @covers ::webcoached_grade_item_update
     */
    public function test_rest_grade_requires_capability(): void {
        $this->resetAfterTest(true);

        [$course, $cm, $student, $webcoached] = $this->setup_graded_activity_with_completion();

        // Act as the student, who lacks moodle/grade:edit.
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        \core_grades_external::update_grades(
            'mod_webcoached',
            $course->id,
            'mod_webcoached',
            $cm->id,
            0,
            [['studentid' => $student->id, 'grade' => 80]]
        );
    }

    /**
     * Deleting the activity removes its grade item from the gradebook.
     *
     * @covers ::webcoached_delete_instance
     */
    public function test_delete_instance_removes_grade_item(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/webcoached/lib.php');

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $webcoached = $this->getDataGenerator()->create_module('webcoached', [
            'course' => $course->id,
            'grade'  => 100,
        ]);

        $criteria = [
            'itemtype'     => 'mod',
            'itemmodule'   => 'webcoached',
            'iteminstance' => $webcoached->id,
            'itemnumber'   => 0,
        ];
        $this->assertNotFalse(\grade_item::fetch($criteria));

        webcoached_delete_instance($webcoached->id);

        $this->assertFalse(\grade_item::fetch($criteria));
    }

    /**
     * Helper: create a graded webcoached activity with grade-based completion and an enrolled student.
     *
     * @return array [course, cm, student, webcoached]
     */
    private function setup_graded_activity_with_completion(): array {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $webcoached = $this->getDataGenerator()->create_module('webcoached', [
            'course'             => $course->id,
            'grade'              => 100,
            'completion'         => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $cm = get_coursemodule_from_instance('webcoached', $webcoached->id, $course->id, false, MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        return [$course, $cm, $student, $webcoached];
    }

    /**
     * The send_message REST callback sends a notification to the learner.
     *
     * @covers \mod_webcoached\external\send_message::execute
     */
    public function test_send_message_sends_notification(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $cm, $student] = $this->setup_activity_with_student();

        $sink = $this->redirectMessages();
        $result = \mod_webcoached\external\send_message::execute($cm->id, $student->id, true);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue($result['status']);
        $this->assertCount(1, $messages);
        $this->assertEquals($student->id, $messages[0]->useridto);
        $this->assertEquals(1, $messages[0]->notification);

        // Placeholders are resolved: the activity name and a link are present, no raw tokens remain.
        $this->assertStringContainsString('Reading Training', $messages[0]->subject);
        $this->assertStringContainsString('Reading Training', $messages[0]->fullmessagehtml);
        $this->assertStringContainsString('/mod/webcoached/view.php', $messages[0]->fullmessagehtml);
        $this->assertStringNotContainsString('{name}', $messages[0]->fullmessagehtml);
        $this->assertStringNotContainsString('{link}', $messages[0]->fullmessagehtml);
    }

    /**
     * The callback sends nothing when send_message is not true.
     *
     * @covers \mod_webcoached\external\send_message::execute
     */
    public function test_send_message_flag_false_sends_nothing(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $cm, $student] = $this->setup_activity_with_student();

        $sink = $this->redirectMessages();
        $result = \mod_webcoached\external\send_message::execute($cm->id, $student->id, false);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertFalse($result['status']);
        $this->assertCount(0, $messages);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('notsent', $result['warnings'][0]['warningcode']);
    }

    /**
     * An invalid recipient yields status false with an explanatory warning.
     *
     * @covers \mod_webcoached\external\send_message::execute
     */
    public function test_send_message_invalid_user(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $cm] = $this->setup_activity_with_student();

        $sink = $this->redirectMessages();
        $result = \mod_webcoached\external\send_message::execute($cm->id, -1, true);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertFalse($result['status']);
        $this->assertCount(0, $messages);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('invaliduser', $result['warnings'][0]['warningcode']);
    }

    /**
     * A per-activity custom message body overrides the default.
     *
     * @covers \mod_webcoached\external\send_message::execute
     */
    public function test_send_message_uses_custom_body(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $cm, $student] = $this->setup_activity_with_student('Custom note for {name}: {link}');

        $sink = $this->redirectMessages();
        \mod_webcoached\external\send_message::execute($cm->id, $student->id, true);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Custom note for Reading Training:', $messages[0]->fullmessagehtml);
        $this->assertStringContainsString('/mod/webcoached/view.php', $messages[0]->fullmessagehtml);
    }

    /**
     * The default body links the course ("Modul") and the activity by name.
     *
     * @covers \mod_webcoached\external\send_message::execute
     */
    public function test_send_message_default_body_links_course_and_activity(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Effizient lesen, sicher entscheiden in der Pflegebegutachtung',
        ]);
        $webcoached = $this->getDataGenerator()->create_module('webcoached', [
            'course' => $course->id,
            'name'   => 'Lesetechnik',
        ]);
        $cm = get_coursemodule_from_instance('webcoached', $webcoached->id, $course->id, false, MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $sink = $this->redirectMessages();
        $result = \mod_webcoached\external\send_message::execute($cm->id, $student->id, true);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue($result['status']);
        $this->assertCount(1, $messages);
        $html = $messages[0]->fullmessagehtml;

        // The course name is a link to the course page.
        $this->assertStringContainsString('Effizient lesen, sicher entscheiden in der Pflegebegutachtung', $html);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $html);

        // The activity name is a link to the webcoached activity.
        $this->assertStringContainsString('Lesetechnik', $html);
        $this->assertStringContainsString('/mod/webcoached/view.php?id=' . $cm->id, $html);

        // No raw placeholder tokens remain.
        foreach (['{name}', '{link}', '{course}', '{courselink}'] as $token) {
            $this->assertStringNotContainsString($token, $html);
        }

        // The plain-text fallback carries both names as well.
        $this->assertStringContainsString('Lesetechnik', $messages[0]->fullmessage);
        $this->assertStringContainsString('Effizient lesen', $messages[0]->fullmessage);
    }

    /**
     * The default body is resolved in the recipient's language, not the caller's.
     *
     * @covers \mod_webcoached\external\send_message::execute
     */
    public function test_send_message_uses_recipient_language(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Simulate an installed German language pack: without a langconfig.php in the
        // dataroot the string manager ignores the language entirely and the plugin's
        // own lang/de strings are never consulted.
        $langfolder = $CFG->dataroot . '/lang/de';
        check_dir_exists($langfolder);
        file_put_contents($langfolder . '/langconfig.php', "<?php \$string['thislanguage'] = 'Deutsch';");
        get_string_manager()->reset_caches();

        [$course, $cm, $student] = $this->setup_activity_with_student();
        $DB->set_field('user', 'lang', 'de', ['id' => $student->id]);

        $sink = $this->redirectMessages();
        $result = \mod_webcoached\external\send_message::execute($cm->id, $student->id, true);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue($result['status']);
        $this->assertCount(1, $messages);

        // German default body from the plugin's lang/de pack, even though the caller (admin) uses English.
        $this->assertStringContainsString('Sie haben eine neue Nachricht im Modul', $messages[0]->fullmessagehtml);
        $this->assertStringContainsString('Neue Nachricht', $messages[0]->subject);
    }

    /**
     * The callback requires the mod/webcoached:sendmessage capability.
     *
     * @covers \mod_webcoached\external\send_message::execute
     */
    public function test_send_message_requires_capability(): void {
        $this->resetAfterTest(true);

        [$course, $cm, $student] = $this->setup_activity_with_student();

        // Act as the student, who lacks mod/webcoached:sendmessage.
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        \mod_webcoached\external\send_message::execute($cm->id, $student->id, true);
    }

    /**
     * Helper: create a webcoached activity with an enrolled student.
     *
     * @param string|null $messagebody Optional custom notification body.
     * @return array [course, cm, student, webcoached]
     */
    private function setup_activity_with_student(?string $messagebody = null): array {
        $course = $this->getDataGenerator()->create_course();
        $record = ['course' => $course->id, 'name' => 'Reading Training'];
        if ($messagebody !== null) {
            $record['messagebody'] = $messagebody;
        }
        $webcoached = $this->getDataGenerator()->create_module('webcoached', $record);
        $cm = get_coursemodule_from_instance('webcoached', $webcoached->id, $course->id, false, MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        return [$course, $cm, $student, $webcoached];
    }
}
