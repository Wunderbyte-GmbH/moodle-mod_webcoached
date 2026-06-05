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
 * Prints an instance of mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/filelib.php');

defined('MOODLE_INTERNAL') || die();

global $CFG, $PAGE, $USER, $DB, $OUTPUT;

$id = required_param('id', PARAM_INT); // Course Module ID.
$launch = optional_param('launch', 0, PARAM_INT); // Action to launch SSO.

$cm = get_coursemodule_from_id('webcoached', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$webcoached = $DB->get_record('webcoached', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/webcoached:view', $context);

// Trigger course_module_viewed event.
$event = \mod_webcoached\event\course_module_viewed::create([
    'objectid' => $webcoached->id,
    'context'  => $context,
]);
$event->trigger();

$PAGE->set_url('/mod/webcoached/view.php', ['id' => $id]);
$PAGE->set_title(format_string($webcoached->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($launch) {
    // Collect settings.
    $clientid = get_config('mod_webcoached', 'client_id');
    $secret = get_config('mod_webcoached', 'secret_key');
    $endpointurl = get_config('mod_webcoached', 'endpoint_url');

    // Build payload.
    $params = [
        'client_id'      => $clientid ? $clientid : 'moodle_test',
        'timestamp'      => time(),
        'nonce'          => bin2hex(random_bytes(16)),
        'moodle_user_id' => (int)$USER->id,
        'course_id'      => $webcoached->remotecourseid,
        'email'          => $USER->email,
        'firstname'      => $USER->firstname,
        'lastname'       => $USER->lastname,
        'redirect'       => 1,
    ];

    // Canonical payload sorting.
    ksort($params);

    // Build query.
    $payload = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    // Hash payload using HMAC-SHA256.
    $signature = hash_hmac('sha256', $payload, $secret);

    // Prepare HTTP request.
    $curl = new curl();
    $postparams = $params;
    $postparams['signature'] = $signature;

    $response = $curl->post($endpointurl, $postparams);

    if ($curl->get_errno()) {
        throw new moodle_exception('error_curl', 'mod_webcoached');
    }

    $redirecturl = null;
    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['url'])) {
        $redirecturl = $decoded['url'];
    } else if (preg_match('/^https?:\/\//i', trim($response))) {
        $redirecturl = trim($response);
    }

    if (empty($redirecturl)) {
        throw new moodle_exception('error_request', 'mod_webcoached', '', s($response));
    }

    // Redirect user to the external course.
    redirect($redirecturl);
}

// Render normal launch page.
echo $OUTPUT->header();

$intro = format_module_intro('webcoached', $webcoached, $cm->id);

$templatecontext = [
    'id' => $id,
    'name' => format_string($webcoached->name),
    'intro' => $intro,
    'url' => $PAGE->url->out(false),
];

echo $OUTPUT->render_from_template('mod_webcoached/launchpad', $templatecontext);

echo $OUTPUT->footer();
