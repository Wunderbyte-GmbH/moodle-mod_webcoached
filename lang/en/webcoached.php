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
$string['client_id_desc'] = 'The SSO Client ID agreed upon with Webcoached (e.g. moodle_test).';
$string['continue_button'] = 'Continue to Webcoached';
$string['display'] = 'Display';
$string['display_help'] = 'How the Webcoached course is opened:

* Current window – the launch button replaces this page with the Webcoached course.
* New window (popup) – the launch button opens the Webcoached course in a separate, sized browser window.
* New tab – the launch button opens the Webcoached course in a new browser tab.';
$string['displaycurrent'] = 'Current window';
$string['displaynewtab'] = 'New tab';
$string['displaypopup'] = 'New window (popup)';
$string['endpoint_url'] = 'Endpoint URL';
$string['endpoint_url_desc'] = 'The Webcoached browser-based SSO login endpoint (directlogin). The signed parameters are posted to this URL from the user\'s browser.';
$string['launch_button'] = 'Zum Webcoached-Kurs';
$string['messagebody'] = 'Notification message';
$string['messagebody_help'] = 'The message sent to the learner when the external Webcoached system triggers the send_message REST call. Available placeholders: {name} – the activity name, {link} – a link to the activity, {course} – the course name, {courselink} – a link to the course. If left empty, a default message is used.';
$string['messagebodydefault'] = 'You have received a new message in the module "{courselink}". Click the link "{link}" to view the message on the training platform.';
$string['messageprovider:webcoachedmessage'] = 'Webcoached activity notifications';
$string['messagesubject'] = 'New message for "{$a}"';
$string['modulename'] = 'Webcoached Training';
$string['modulename_help'] = 'The Webcoached Training activity module enables single sign-on (SSO) and redirection of users to external Webcoached training modules.';
$string['modulenameplural'] = 'Webcoached Trainings';
$string['pluginadministration'] = 'Webcoached Training Administration';
$string['pluginname'] = 'Webcoached Training';
$string['popupheight'] = 'Window height (pixels)';
$string['popupwidth'] = 'Window width (pixels)';
$string['privacy:metadata'] = 'The webcoached plugin does not store or process personal data locally; it only transmits a signed SSO query payload to redirect the user browser.';
$string['redirecting'] = 'You are being signed in to Webcoached. Please wait...';
$string['remotecourseid'] = 'Webcoached course ID';
$string['remotecourseid_help'] = 'The numeric internal Webcoached course ID to send as course_id during login (e.g. 1111 for Lesetechnik, 1112 for Lesestrategie, 1113 for Nachhaltigkeit). These IDs are fixed by Webcoached and cannot be changed freely.';
$string['secret_key'] = 'Secret Key';
$string['secret_key_desc'] = 'The shared secret used to sign the SSO login payload (HMAC-SHA256). It is only used server-side and must never be exposed in the frontend or JavaScript.';
$string['webcoached:addinstance'] = 'Add a new Webcoached activity';
$string['webcoached:sendmessage'] = 'Trigger the send_message notification callback';
$string['webcoached:view'] = 'View Webcoached activities';
$string['webcoachedname'] = 'Name / Title';
