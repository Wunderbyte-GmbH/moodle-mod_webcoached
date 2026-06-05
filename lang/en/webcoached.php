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
 * English strings for mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['client_id'] = 'Client ID';
$string['client_id_desc'] = 'The OAuth/SSO Client ID configured globally.';
$string['endpoint_url'] = 'Endpoint URL';
$string['endpoint_url_desc'] = 'The remote Webcoached SSO entry API endpoint URL.';
$string['error_curl'] = 'Network communication error during redirection payload request.';
$string['error_emptyurl'] = 'The Webcoached API returned an empty redirection URL.';
$string['error_request'] = 'Failed to retrieve redirect URL from Webcoached API. Response: {$a}';
$string['launch_button'] = 'Zum Webcoached-Kurs';
$string['modulename'] = 'Webcoached Training';
$string['modulename_help'] = 'The Webcoached Training activity module enables single sign-on (SSO) and redirection of users to external Webcoached training modules.';
$string['modulenameplural'] = 'Webcoached Trainings';
$string['pluginadministration'] = 'Webcoached Training Administration';
$string['pluginname'] = 'Webcoached Training';
$string['privacy:metadata'] = 'The webcoached plugin does not store or process personal data locally; it only transmits a signed SSO query payload to redirect the user browser.';
$string['remotecourseid'] = 'Remote Course ID Configuration';
$string['remotecourseid_help'] = 'The remote targeting string (COURSE01, COURSE02, COURSE03, etc.) assigned in the activity module.';
$string['secret_key'] = 'Secret Key';
$string['secret_key_desc'] = 'The secret/key used to sign redirection payloads.';
$string['webcoached:addinstance'] = 'Add a new Webcoached activity';
$string['webcoached:view'] = 'View Webcoached activities';
$string['webcoachedname'] = 'Name / Title';
