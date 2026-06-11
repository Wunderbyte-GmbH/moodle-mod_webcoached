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
 * CLI script to test Webcoached API response using config credentials.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');

// Bootstrap Moodle CLI shell output.
[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'secret' => null,
        'clientid' => null,
        'endpoint' => null,
    ],
    [
        'h' => 'help',
        's' => 'secret',
        'c' => 'clientid',
        'e' => 'endpoint',
    ]
);

if ($options['help']) {
    $help = "Diagnostic script for the mod_webcoached SSO signature and endpoint.

Note: production uses a browser-based POST so the Webcoached session is created in
the user's browser. This CLI performs a server-side POST only to inspect signature
validation and the endpoint response; it cannot establish a real browser session.

Options:
-h, --help      Print this help.
-s, --secret    Override secret key.
-c, --clientid  Override client ID.
-e, --endpoint  Override endpoint URL.
";
    cli_writeln($help);
    exit(0);
}

// Retrieve credentials.
$clientid = $options['clientid'] ?? get_config('mod_webcoached', 'client_id');
if (empty($clientid)) {
    $clientid = 'moodle_test';
}

$secretkey = $options['secret'] ?? get_config('mod_webcoached', 'secret_key');
$iskeywarning = false;
if (empty($secretkey)) {
    $iskeywarning = true;
    $secretkey = '';
}

$endpointurl = $options['endpoint'] ?? get_config('mod_webcoached', 'endpoint_url');
if (empty($endpointurl)) {
    $endpointurl = 'https://www.webcoachedtraining.de/app/moodle/directlogin';
}

// Fetch a test user (admin or any active user).
$user = $DB->get_record('user', ['username' => 'admin']);
if (!$user) {
    $user = $DB->get_record_select('user', 'username != :guest AND deleted = 0', ['guest' => 'guest'], '*', IGNORE_MULTIPLE);
}
if (!$user) {
    $user = (object)[
        'id' => 123,
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'testuser@example.com',
    ];
}

cli_writeln('========================================');
cli_writeln('Webcoached API Response Testing Tool');
cli_writeln('========================================');
cli_writeln("Endpoint URL: {$endpointurl}");
cli_writeln("Client ID:    {$clientid}");
if ($iskeywarning) {
    cli_writeln("Secret Key:   [NOT SET / EMPTY] (Warning: Calculations may fail validation)");
} else {
    cli_writeln("Secret Key:   " . str_repeat('*', 8) . substr($secretkey, -4));
}
cli_writeln("Test User:    {$user->firstname} {$user->lastname} (ID: {$user->id})");

$courses = [1111, 1112, 1113];
foreach ($courses as $courseid) {
    cli_writeln("\n----------------------------------------");
    cli_writeln("Testing Course ID: {$courseid}");
    cli_writeln("----------------------------------------");

    $nonce = bin2hex(random_bytes(16));
    $timestamp = time();

    // Prepare parameters (only the parameters documented by Webcoached are signed).
    $params = [
        'client_id'      => $clientid,
        'timestamp'      => $timestamp,
        'nonce'          => $nonce,
        'moodle_user_id' => (int)$user->id,
        'course_id'      => $courseid,
        'firstname'      => $user->firstname,
        'lastname'       => $user->lastname,
    ];

    // Sort parameters lexicographically by key.
    ksort($params);

    // Build the query string representation of the parameters.
    $payload = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    // Compute HMAC-SHA256 signature.
    $signature = hash_hmac('sha256', $payload, $secretkey);

    // Prepare HTTP POST fields.
    $postparams = $params;
    $postparams['signature'] = $signature;

    cli_writeln("Parameters sent:");
    foreach ($postparams as $key => $val) {
        cli_writeln("  - {$key}: {$val}");
    }
    cli_writeln("Calculated signature: {$signature}");

    cli_writeln("Sending request...");

    $curl = new curl();
    $response = $curl->post($endpointurl, $postparams);

    if ($curl->get_errno()) {
        cli_writeln("cURL Error ({$curl->get_errno()}): {$curl->error}");
    } else {
        cli_writeln("Response from server:");
        cli_writeln($response);
    }
}

cli_writeln("\n========================================");
cli_writeln('Testing complete.');
cli_writeln('========================================');
