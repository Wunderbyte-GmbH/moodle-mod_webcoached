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

    if (empty($endpointurl)) {
        $endpointurl = 'https://www.webcoachedtraining.de/app/moodle/directlogin';
    }

    // Build the login payload for the current user. Only the parameters documented by
    // Webcoached are included and signed: client_id, timestamp, nonce, moodle_user_id,
    // course_id, firstname and lastname.
    $params = [
        'client_id'      => $clientid ? $clientid : 'moodle_test',
        'timestamp'      => time(),
        'nonce'          => bin2hex(random_bytes(16)),
        'moodle_user_id' => (int)$USER->id,
        'course_id'      => $webcoached->remotecourseid,
        'firstname'      => $USER->firstname,
        'lastname'       => $USER->lastname,
    ];

    // Canonical payload: parameters alphabetically sorted and RFC3986 encoded.
    ksort($params);
    $canonical = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    // Sign the canonical payload server-side with HMAC-SHA256. The secret key is only
    // ever used here on the server and is never exposed to the browser or JavaScript.
    $params['signature'] = hash_hmac('sha256', $canonical, $secret);

    // This is a browser-based SSO login flow, not a JSON API call: the Webcoached session
    // cookie must be created in the user's own browser. The signed parameters are therefore
    // submitted via an auto-posting form from the browser rather than a server-side request.
    $fields = [];
    foreach ($params as $name => $value) {
        $fields[] = ['name' => $name, 'value' => $value];
    }

    $PAGE->set_pagelayout('embedded');
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_webcoached/autopost', [
        'endpoint' => $endpointurl,
        'fields'   => $fields,
    ]);
    $PAGE->requires->js_amd_inline("
        require([], function() {
            var form = document.getElementById('webcoached_sso_form');
            if (form) {
                form.submit();
            }
        });
    ");
    echo $OUTPUT->footer();
    exit;
}

// Render normal launch page.
echo $OUTPUT->header();

$intro = format_module_intro('webcoached', $webcoached, $cm->id);
$launchurl = new moodle_url('/mod/webcoached/view.php', ['id' => $id, 'launch' => 1]);

if ((int) $webcoached->popup === WEBCOACHED_DISPLAY_EMBED) {
    // Embedded mode: load the SSO launch directly in an iframe on this page.
    echo $OUTPUT->render_from_template('mod_webcoached/embedded', [
        'name' => format_string($webcoached->name),
        'intro' => $intro,
        'launchurl' => $launchurl->out(false),
        'height' => (int) $webcoached->popupheight,
    ]);
} else {
    $templatecontext = [
        'id' => $id,
        'name' => format_string($webcoached->name),
        'intro' => $intro,
        'url' => $PAGE->url->out(false),
    ];

    echo $OUTPUT->render_from_template('mod_webcoached/launchpad', $templatecontext);

    if ((int) $webcoached->popup === WEBCOACHED_DISPLAY_POPUP) {
        // Popup mode: intercept the launch form and open a sized popup window instead.
        // Without JavaScript the form still submits and launches in the current window.
        $popupoptions = 'width=' . (int) $webcoached->popupwidth . ',height=' . (int) $webcoached->popupheight
            . ',resizable=yes,scrollbars=yes';
        $PAGE->requires->js_amd_inline("
            require([], function() {
                var form = document.querySelector('.mod_webcoached_launchpad form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        var popup = window.open(" . json_encode($launchurl->out(false)) . ",
                            'webcoached_popup', " . json_encode($popupoptions) . ");
                        if (popup) {
                            e.preventDefault();
                            popup.focus();
                        }
                    });
                }
            });
        ");
    }
}

echo $OUTPUT->footer();
