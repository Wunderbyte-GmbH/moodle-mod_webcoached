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
 * German strings for mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['client_id'] = 'Client-ID';
$string['client_id_desc'] = 'Die mit Webcoached vereinbarte SSO-Client-ID (z. B. moodle_test).';
$string['continue_button'] = 'Weiter zu Webcoached';
$string['endpoint_url'] = 'Endpoint-URL';
$string['endpoint_url_desc'] = 'Der browserbasierte SSO-Login-Endpunkt von Webcoached (directlogin). Die signierten Parameter werden aus dem Browser der Nutzer/innen an diese URL gesendet.';
$string['launch_button'] = 'Zum Webcoached-Kurs';
$string['modulename'] = 'Webcoached Training';
$string['modulename_help'] = 'Das Aktivitätsmodul Webcoached Training ermöglicht Single Sign-on (SSO) und die Weiterleitung von Nutzer/innen zu externen Webcoached-Trainingsmodulen.';
$string['modulenameplural'] = 'Webcoached Trainings';
$string['pluginadministration'] = 'Verwaltung Webcoached Training';
$string['pluginname'] = 'Webcoached Training';
$string['privacy:metadata'] = 'Das Plugin webcoached speichert oder verarbeitet keine personenbezogenen Daten lokal; es übermittelt lediglich eine signierte SSO-Anfrage, um den Browser der Nutzer/innen weiterzuleiten.';
$string['redirecting'] = 'Sie werden bei Webcoached angemeldet. Bitte warten ...';
$string['remotecourseid'] = 'Webcoached-Kurs-ID';
$string['remotecourseid_help'] = 'Die numerische interne Webcoached-Kurs-ID, die beim Login als course_id gesendet wird (z. B. 1111 für Lesetechnik, 1112 für Lesestrategie, 1113 für Nachhaltigkeit). Diese IDs werden von Webcoached vorgegeben und können nicht frei geändert werden.';
$string['secret_key'] = 'Secret Key';
$string['secret_key_desc'] = 'Der gemeinsame geheime Schlüssel zur Signierung der SSO-Login-Daten (HMAC-SHA256). Er wird ausschließlich serverseitig verwendet und darf niemals im Frontend oder in JavaScript offengelegt werden.';
$string['webcoached:addinstance'] = 'Eine neue Webcoached-Aktivität hinzufügen';
$string['webcoached:view'] = 'Webcoached-Aktivitäten ansehen';
$string['webcoachedname'] = 'Name / Titel';
