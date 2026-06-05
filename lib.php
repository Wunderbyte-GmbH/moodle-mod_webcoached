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
    $data->timecreated = time();
    $data->timemodified = time();

    return $DB->insert_record('webcoached', $data);
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
    $data->timemodified = time();

    return $DB->update_record('webcoached', $data);
}

/**
 * Removes an instance of mod_webcoached from the database.
 *
 * @param int $id ID of the module instance.
 * @return bool True if successful, false on failure.
 */
function webcoached_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('webcoached', ['id' => $id])) {
        return false;
    }

    return $DB->delete_records('webcoached', ['id' => $id]);
}
