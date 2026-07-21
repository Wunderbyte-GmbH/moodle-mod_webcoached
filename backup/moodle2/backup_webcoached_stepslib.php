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
 * Backup stepslib definition for mod_webcoached.
 *
 * @package     mod_webcoached
 * @category    backup
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete webcoached structure for backup.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_webcoached_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        // Define root element.
        $webcoached = new backup_nested_element('webcoached', ['id'], [
            'name',
            'intro',
            'introformat',
            'remotecourseid',
            'grade',
            'messagebody',
            'messagebodyformat',
            'popup',
            'popupwidth',
            'popupheight',
            'timecreated',
            'timemodified',
        ]);

        // Map sources.
        $webcoached->set_source_table('webcoached', ['id' => backup::VAR_ACTIVITYID]);

        // Return standard structure.
        return $this->prepare_activity_structure($webcoached);
    }
}
