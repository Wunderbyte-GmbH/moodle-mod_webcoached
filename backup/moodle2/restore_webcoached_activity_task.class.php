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
 * Restore task definition for mod_webcoached.
 *
 * @package     mod_webcoached
 * @category    backup
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/webcoached/backup/moodle2/restore_webcoached_stepslib.php');

/**
 * Restore activity task for mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_webcoached_activity_task extends restore_activity_task {
    /**
     * Define settings this activity can have.
     */
    protected function define_my_settings() {
    }

    /**
     * Define steps this activity can have.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_webcoached_activity_structure_step('webcoached_structure', 'webcoached.xml'));
    }

    /**
     * Define decode contents.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('webcoached', ['intro'], 'webcoached');
        return $contents;
    }

    /**
     * Define decode rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];
        $rules[] = new restore_decode_rule('WEBCOACHEDVIEWBYID', '/mod/webcoached/view.php?id=$1', 'course_module');
        return $rules;
    }

    /**
     * Define restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        $rules = [];
        $rules[] = new restore_log_rule('webcoached', 'view', 'view.php?id={course_module}', '{webcoached}');
        return $rules;
    }
}
