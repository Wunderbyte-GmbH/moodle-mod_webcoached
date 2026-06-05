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
 * Global settings for mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'mod_webcoached/client_id',
        get_string('client_id', 'mod_webcoached'),
        get_string('client_id_desc', 'mod_webcoached'),
        'moodle_test',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_webcoached/secret_key',
        get_string('secret_key', 'mod_webcoached'),
        get_string('secret_key_desc', 'mod_webcoached'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_webcoached/endpoint_url',
        get_string('endpoint_url', 'mod_webcoached'),
        get_string('endpoint_url_desc', 'mod_webcoached'),
        'https://www.webcoachedtraining.de/api/external/moodle.php',
        PARAM_URL
    ));
}
