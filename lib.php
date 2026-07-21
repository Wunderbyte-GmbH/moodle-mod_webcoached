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
 * Library of interface functions and constants for mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Launch the Webcoached course in the current window (the launch button replaces this page). */
define('WEBCOACHED_DISPLAY_CURRENT', 0);
/** Launch the Webcoached course in a new popup window. */
define('WEBCOACHED_DISPLAY_POPUP', 1);

/**
 * Returns the display mode menu options, like scorm_get_popup_display_array().
 *
 * @return array Menu of display mode options keyed by WEBCOACHED_DISPLAY_* constant.
 */
function webcoached_get_display_options() {
    return [
        WEBCOACHED_DISPLAY_CURRENT => get_string('displaycurrent', 'mod_webcoached'),
        WEBCOACHED_DISPLAY_POPUP => get_string('displaypopup', 'mod_webcoached'),
    ];
}

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true|null True if the feature is supported, null otherwise.
 */
function webcoached_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of mod_webcoached into the database.
 *
 * @param stdClass $formdata Data from mod_form.php
 * @param mod_webcoached_mod_form|null $mform The form object
 * @return int The new instance ID
 */
function webcoached_add_instance(stdClass $formdata, ?mod_webcoached_mod_form $mform = null) {
    global $DB;

    $data = new stdClass();
    $data->course = $formdata->course;
    $data->name = $formdata->name;
    $data->intro = $formdata->intro;
    $data->introformat = $formdata->introformat;
    $data->remotecourseid = isset($formdata->remotecourseid) ? trim($formdata->remotecourseid) : '';
    $data->grade = isset($formdata->grade) ? (int) $formdata->grade : 0;
    webcoached_set_messagebody($data, $formdata);
    webcoached_set_display($data, $formdata);
    $data->timecreated = time();
    $data->timemodified = time();

    $data->id = $DB->insert_record('webcoached', $data);

    // Carry the course-module idnumber over so the grade item is linked correctly.
    $data->cmidnumber = $formdata->cmidnumber ?? null;

    // Create the grade item in the gradebook so REST and manual grades have a target.
    webcoached_grade_item_update($data);

    return $data->id;
}

/**
 * Updates an instance of mod_webcoached in the database.
 *
 * @param stdClass $formdata Data from mod_form.php
 * @param mod_webcoached_mod_form|null $mform The form object
 * @return bool True if successful, false otherwise
 */
function webcoached_update_instance(stdClass $formdata, ?mod_webcoached_mod_form $mform = null) {
    global $DB;

    $data = new stdClass();
    $data->id = $formdata->instance;
    $data->name = $formdata->name;
    $data->intro = $formdata->intro;
    $data->introformat = $formdata->introformat;
    $data->remotecourseid = isset($formdata->remotecourseid) ? trim($formdata->remotecourseid) : '';
    $data->course = $formdata->course;
    $data->grade = isset($formdata->grade) ? (int) $formdata->grade : 0;
    webcoached_set_messagebody($data, $formdata);
    webcoached_set_display($data, $formdata);
    $data->timemodified = time();

    $result = $DB->update_record('webcoached', $data);

    // Carry the course-module idnumber over so the grade item is linked correctly.
    $data->cmidnumber = $formdata->cmidnumber ?? null;

    // Keep the grade item in sync (e.g. grade type / scale changes).
    webcoached_grade_item_update($data);

    return $result;
}

/**
 * Removes an instance of mod_webcoached from the database.
 *
 * @param int $id ID of the module instance.
 * @return bool True if successful, false on failure.
 */
function webcoached_delete_instance($id) {
    global $DB;

    if (!$webcoached = $DB->get_record('webcoached', ['id' => $id])) {
        return false;
    }

    // Remove the grade item (and any grades) from the gradebook.
    webcoached_grade_item_delete($webcoached);

    return $DB->delete_records('webcoached', ['id' => $id]);
}

/**
 * Copies the display mode and popup dimensions from the form data onto the instance record.
 *
 * @param stdClass $data Target instance record being built.
 * @param stdClass $formdata Submitted form data.
 */
function webcoached_set_display(stdClass $data, stdClass $formdata) {
    $data->popup = isset($formdata->popup) ? (int) $formdata->popup : WEBCOACHED_DISPLAY_CURRENT;
    if (!array_key_exists($data->popup, webcoached_get_display_options())) {
        $data->popup = WEBCOACHED_DISPLAY_CURRENT;
    }

    $data->popupwidth = isset($formdata->popupwidth) ? (int) $formdata->popupwidth : 0;
    if ($data->popupwidth <= 0) {
        $data->popupwidth = 1180;
    }

    $data->popupheight = isset($formdata->popupheight) ? (int) $formdata->popupheight : 0;
    if ($data->popupheight <= 0) {
        $data->popupheight = 800;
    }
}

/**
 * Copies the notification message body from the form data onto the instance record.
 *
 * Handles both the editor element shape (an array with 'text'/'format', from the
 * activity settings form) and a plain string (e.g. from the test data generator).
 *
 * @param stdClass $data Target instance record being built.
 * @param stdClass $formdata Submitted form data.
 */
function webcoached_set_messagebody(stdClass $data, stdClass $formdata) {
    $data->messagebody = '';
    $data->messagebodyformat = FORMAT_HTML;

    if (!isset($formdata->messagebody)) {
        return;
    }

    if (is_array($formdata->messagebody)) {
        $data->messagebody = $formdata->messagebody['text'] ?? '';
        $data->messagebodyformat = $formdata->messagebody['format'] ?? FORMAT_HTML;
    } else {
        $data->messagebody = (string) $formdata->messagebody;
        if (isset($formdata->messagebodyformat)) {
            $data->messagebodyformat = (int) $formdata->messagebodyformat;
        }
    }
}

/**
 * Creates or updates the grade item for the given webcoached instance.
 *
 * Needed for the gradebook and so that the core `completionusegrade` condition
 * can complete the activity automatically once a grade is written (whether via
 * the gradebook UI or the core `core_grades_update_grades` web service).
 *
 * @param stdClass $webcoached Instance object with extra cmidnumber property.
 * @param mixed $grades Optional array/object of grade(s); 'reset' resets grades in the gradebook.
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, etc.
 */
function webcoached_grade_item_update($webcoached, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => $webcoached->name,
    ];
    if (isset($webcoached->cmidnumber)) {
        $item['idnumber'] = $webcoached->cmidnumber;
    }

    if (!isset($webcoached->grade) || $webcoached->grade == 0) {
        $item['gradetype'] = GRADE_TYPE_NONE;
    } else if ($webcoached->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $webcoached->grade;
        $item['grademin']  = 0;
    } else {
        // Negative grade encodes a scale id.
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$webcoached->grade;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/webcoached', $webcoached->course, 'mod', 'webcoached', $webcoached->id, 0, $grades, $item);
}

/**
 * Updates activity grades. Called by the gradebook when it needs to (re)build grades.
 *
 * The webcoached module keeps no internal grade store of its own: single grades are
 * pushed straight into the gradebook (by the teacher or the REST callback), so here
 * we only ensure the grade item itself exists and is up to date.
 *
 * @param stdClass $webcoached Instance object with extra cmidnumber property.
 * @param int $userid Specific user only, 0 means all users (unused, no internal store).
 * @param bool $nullifnone Whether to set 0 grade to null when there is no grade (unused).
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, etc.
 */
function webcoached_update_grades($webcoached, $userid = 0, $nullifnone = true) {
    return webcoached_grade_item_update($webcoached);
}

/**
 * Removes the grade item for the given webcoached instance.
 *
 * @param stdClass $webcoached Instance object.
 * @return int GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, etc.
 */
function webcoached_grade_item_delete($webcoached) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/webcoached', $webcoached->course, 'mod', 'webcoached', $webcoached->id, 0, null, ['deleted' => true]);
}

/**
 * Checks whether a scale is being used by any webcoached instance.
 *
 * Used by the course reset and the scale deletion check so an in-use scale
 * cannot be removed.
 *
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by any webcoached instance.
 */
function webcoached_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid && $DB->record_exists('webcoached', ['grade' => -$scaleid])) {
        return true;
    }

    return false;
}
