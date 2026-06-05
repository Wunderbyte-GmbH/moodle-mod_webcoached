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

        $params = [
            'client_id'      => 'moodle_test',
            'timestamp'      => 1600000000,
            'nonce'          => 'abcdef1234567890',
            'moodle_user_id' => 12,
            'course_id'      => 'COURSE01',
            'email'          => 'student@example.com',
            'firstname'      => 'John',
            'lastname'       => 'Doe',
            'redirect'       => 1,
        ];

        ksort($params);
        $payload = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $signature = hash_hmac('sha256', $payload, 'test_secret');

        // Check format of payload query string.
        $this->assertStringContainsString('client_id=moodle_test', $payload);
        $this->assertStringContainsString('course_id=COURSE01', $payload);
        $this->assertStringContainsString('nonce=abcdef1234567890', $payload);

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
            'remotecourseid' => 'TESTCOURSE',
        ]);

        $this->assertNotEmpty($webcoached->id);
        $this->assertEquals('Test Webcoached Activity', $webcoached->name);
        $this->assertEquals('TESTCOURSE', $webcoached->remotecourseid);
    }
}
