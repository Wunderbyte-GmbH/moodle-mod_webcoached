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
 * Editing form definition for mod_webcoached.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_webcoached_mod_form extends moodleform_mod {
    /**
     * Defines form elements.
     */
    public function definition() {
        $mform = $this->_form;

        // Adding the "general" fieldset.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('webcoachedname', 'mod_webcoached'), [
            'size' => '64',
        ]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();

        // Adding the custom "remotecourseid" field.
        $mform->addElement('text', 'remotecourseid', get_string('remotecourseid', 'mod_webcoached'), [
            'size' => '64',
        ]);
        $mform->setType('remotecourseid', PARAM_TEXT);
        $mform->addRule('remotecourseid', null, 'required', null, 'client');
        $mform->addHelpButton('remotecourseid', 'remotecourseid', 'mod_webcoached');

        // Notification body sent to the learner when the send_message REST call is triggered.
        // Supports the placeholders {name} (activity name) and {link} (link to the activity).
        $mform->addElement('editor', 'messagebody', get_string('messagebody', 'mod_webcoached'), ['rows' => 8]);
        $mform->addHelpButton('messagebody', 'messagebody', 'mod_webcoached');

        // Add the grade type selector (None / Point / Scale). Choosing a Yes/No scale
        // gives a simple "Completed" mark; a point value gives a real score. Either way,
        // core's "completionusegrade" condition completes the activity once a grade exists.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements (this also renders the activity completion section,
        // including the core "Student must receive a grade" completion condition).
        $this->standard_coursemodule_elements();

        // Add standard buttons.
        $this->add_action_buttons();
    }

    /**
     * Prepares the form data for display.
     *
     * Converts the stored message body into the array shape the editor element
     * expects and pre-fills the language default for new instances or empty bodies.
     *
     * @param array $defaultvalues Reference to the default form values.
     */
    public function data_preprocessing(&$defaultvalues) {
        $text = $defaultvalues['messagebody'] ?? '';
        $format = $defaultvalues['messagebodyformat'] ?? FORMAT_HTML;

        if (trim((string) $text) === '') {
            $text = get_string('messagebodydefault', 'mod_webcoached');
            $format = FORMAT_HTML;
        }

        $defaultvalues['messagebody'] = ['text' => $text, 'format' => $format];
    }
}
