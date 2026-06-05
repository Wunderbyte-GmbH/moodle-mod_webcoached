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
 * Seeding script for setting up mod_webcoached sandbox environment.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

// Bootstrap Moodle CLI shell output.
[$options, $unrecognized] = cli_get_params(
    [],
    []
);

cli_writeln('Initializing Webcoached Sandbox Setup...');

// 1. Create or fetch course.
$course = $DB->get_record('course', ['shortname' => 'webcoached_sandbox']);
if (!$course) {
    cli_writeln('Creating course "Webcoached Sandbox Course"...');
    $course = create_course((object)[
        'shortname' => 'webcoached_sandbox',
        'fullname' => 'Webcoached Sandbox Course',
        'category' => 1,
        'format' => 'topics',
        'numsections' => 1,
    ]);
} else {
    cli_writeln('Found existing course "Webcoached Sandbox Course".');
}

// 2. Create or fetch evaluator user.
$user = $DB->get_record('user', ['username' => 'evaluator']);
if (!$user) {
    cli_writeln('Creating user "evaluator" (password: Evaluator123!)...');
    $userobj = new stdClass();
    $userobj->username = 'evaluator';
    $userobj->password = 'Evaluator123!';
    $userobj->firstname = 'Webcoached';
    $userobj->lastname = 'Evaluator';
    $userobj->email = 'evaluator@webcoachedtraining.de';
    $userobj->confirmed = 1;
    $userobj->mnethostid = $CFG->mnet_localhost_id;
    $userid = user_create_user($userobj, true, false);
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
} else {
    cli_writeln('Found existing user "evaluator".');
}

// 3. Enroll evaluator user as student.
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if ($instance) {
    $enrol = enrol_get_plugin('manual');
    if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id])) {
        cli_writeln('Enrolling evaluator user in course...');
        $enrol->enrol_user($instance, $user->id, $studentrole->id);
    } else {
        cli_writeln('Evaluator user is already enrolled.');
    }
}

// 4. Create Webcoached module activities.
$targets = ['COURSE01', 'COURSE02', 'COURSE03'];
$module = $DB->get_record('modules', ['name' => 'webcoached']);

if (!$module) {
    cli_err('Module "webcoached" is not installed in Moodle. Please run upgradedb or visit notifications page first.');
    exit(1);
}

foreach ($targets as $target) {
    if (!$DB->record_exists('webcoached', ['course' => $course->id, 'remotecourseid' => $target])) {
        cli_writeln("Creating activity instance for remote course ID: {$target}...");

        // Insert webcoached record.
        $webcoached = new stdClass();
        $webcoached->course = $course->id;
        $webcoached->name = 'Webcoached Training ' . $target;
        $webcoached->intro = '<p>Click below to redirect to the external Webcoached training module for ' . $target . '.</p>';
        $webcoached->introformat = FORMAT_HTML;
        $webcoached->remotecourseid = $target;
        $webcoached->timecreated = time();
        $webcoached->timemodified = time();
        $instanceid = $DB->insert_record('webcoached', $webcoached);

        // Insert course_modules record.
        $cm = new stdClass();
        $cm->course = $course->id;
        $cm->module = $module->id;
        $cm->instance = $instanceid;
        $cm->section = 0;
        $cm->added = time();
        $cmid = add_course_module($cm);

        // Add to section.
        course_add_cm_to_section($course->id, $cmid, 0);
    } else {
        cli_writeln("Activity instance for remote course ID {$target} already exists.");
    }
}

rebuild_course_cache($course->id, true);
cli_writeln('Sandbox setup complete!');
