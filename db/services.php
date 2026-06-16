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
 * Web service definitions for mod_webcoached.
 *
 * @package     mod_webcoached
 * @category    webservice
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_webcoached_send_message' => [
        'classname'   => 'mod_webcoached\external\send_message',
        'methodname'  => 'execute',
        'description' => 'Notify a learner about a new Webcoached message when triggered by the external system.',
        'type'        => 'write',
        'capabilities' => 'mod/webcoached:sendmessage',
        'ajax'        => false,
        'services'    => [],
    ],
];

$services = [
    'Webcoached external' => [
        'functions'       => ['mod_webcoached_send_message'],
        'restrictedusers' => 1,
        'shortname'       => 'mod_webcoached_external',
        'enabled'         => 1,
    ],
];
