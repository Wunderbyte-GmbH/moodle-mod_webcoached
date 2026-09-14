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
 * Backup and restore tests for mod_webcoached.
 *
 * @package     mod_webcoached
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webcoached;

use advanced_testcase;
use backup;
use backup_controller;
use restore_controller;
use restore_dbops;

/**
 * Class backup_restore_test.
 *
 * @package     mod_webcoached
 * @category    test
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends advanced_testcase {
    /**
     * A course backup containing a webcoached activity restores into a new course.
     *
     * @covers \restore_webcoached_activity_structure_step
     * @covers \backup_webcoached_activity_structure_step
     */
    public function test_backup_and_restore_course(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $webcoached = $this->getDataGenerator()->create_module('webcoached', [
            'course'         => $course->id,
            'name'           => 'Restored Webcoached',
            'remotecourseid' => '4242',
            'grade'          => 100,
            'messagebody'    => 'Custom note for {name}: {link}',
            'popup'          => 1,
            'popupwidth'     => 800,
            'popupheight'    => 600,
        ]);
        $original = $DB->get_record('webcoached', ['id' => $webcoached->id], '*', MUST_EXIST);

        // Back up the course.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Restore it into a new course.
        $newcourseid = restore_dbops::create_new_course(
            $course->fullname . ' copy',
            $course->shortname . '_copy',
            $course->category
        );
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $restored = $DB->get_record('webcoached', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertNotEquals($original->id, $restored->id);
        foreach (['name', 'remotecourseid', 'grade', 'messagebody', 'popup', 'popupwidth', 'popupheight'] as $field) {
            $this->assertEquals($original->$field, $restored->$field, "Field {$field} was not restored");
        }

        // The restored instance is linked to a course module in the new course.
        $cm = get_coursemodule_from_instance('webcoached', $restored->id, $newcourseid, false, MUST_EXIST);
        $this->assertEquals($newcourseid, $cm->course);
    }
}
