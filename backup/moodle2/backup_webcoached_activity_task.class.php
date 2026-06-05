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
 * Backup task definition for mod_webcoached.
 *
 * @package     mod_webcoached
 * @category    backup
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/webcoached/backup/moodle2/backup_webcoached_stepslib.php');

/**
 * Backup activity task for mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_webcoached_activity_task extends backup_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the webcoached.xml file.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_webcoached_activity_structure_step('webcoached_structure', 'webcoached.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts.
     *
     * @param string $content HTML content to scan.
     * @return string Content with encoded links.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to list.
        $search = "/(" . "$base" . "\/mod\/webcoached\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@WEBCOACHEDINDEX*$2@$', $content);

        // Link to view.php.
        $search = "/(" . "$base" . "\/mod\/webcoached\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@WEBCOACHEDVIEWBYID*$2@$', $content);

        return $content;
    }
}
