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
 * Database upgrade steps for mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_webcoached upgrade from the given old version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Always true on success.
 */
function xmldb_webcoached_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061700) {
        // Define field grade to be added to webcoached.
        $table = new xmldb_table('webcoached');
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '100', 'remotecourseid');

        // Conditionally launch add field grade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Webcoached savepoint reached.
        upgrade_mod_savepoint(true, 2026061700, 'webcoached');
    }

    if ($oldversion < 2026061701) {
        $table = new xmldb_table('webcoached');

        // Define field messagebody to be added to webcoached.
        $field = new xmldb_field('messagebody', XMLDB_TYPE_TEXT, null, null, null, null, null, 'grade');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field messagebodyformat to be added to webcoached.
        $field = new xmldb_field('messagebodyformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'messagebody');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Webcoached savepoint reached.
        upgrade_mod_savepoint(true, 2026061701, 'webcoached');
    }

    if ($oldversion < 2026072100) {
        $table = new xmldb_table('webcoached');

        // Define field popup (display mode) to be added to webcoached.
        $field = new xmldb_field('popup', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'messagebodyformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field popupwidth to be added to webcoached.
        $field = new xmldb_field('popupwidth', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1180', 'popup');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field popupheight to be added to webcoached.
        $field = new xmldb_field('popupheight', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '800', 'popupwidth');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Webcoached savepoint reached.
        upgrade_mod_savepoint(true, 2026072100, 'webcoached');
    }

    return true;
}
