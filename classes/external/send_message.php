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
 * External function: notify a learner about a new Webcoached message.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_webcoached\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core\message\message;
use core_user;

/**
 * Sends a Moodle notification to a learner when triggered by the external Webcoached system.
 *
 * @package     mod_webcoached
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the webcoached activity'),
            'userid' => new external_value(PARAM_INT, 'Moodle user id of the recipient (the learner)'),
            'send_message' => new external_value(PARAM_BOOL, 'Whether to send the message', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Sends the notification to the recipient.
     *
     * @param int $cmid Course module id.
     * @param int $userid Recipient user id.
     * @param bool $sendmessage Whether to actually send.
     * @return array status and warnings
     */
    public static function execute(int $cmid, int $userid, bool $sendmessage = true): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'send_message' => $sendmessage,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'webcoached');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/webcoached:sendmessage', $context);

        // Nothing to do unless the flag is set.
        if (!$params['send_message']) {
            return [
                'status' => false,
                'warnings' => [[
                    'item' => 'send_message',
                    'itemid' => $cm->id,
                    'warningcode' => 'notsent',
                    'message' => 'The send_message flag was not true, no message sent.',
                ]],
            ];
        }

        $recipient = core_user::get_user($params['userid']);
        if (!$recipient || $recipient->deleted || $recipient->suspended || isguestuser($recipient)) {
            return [
                'status' => false,
                'warnings' => [[
                    'item' => 'user',
                    'itemid' => $params['userid'],
                    'warningcode' => 'invaliduser',
                    'message' => 'Recipient user not found or not active.',
                ]],
            ];
        }

        $webcoached = $DB->get_record('webcoached', ['id' => $cm->instance], '*', MUST_EXIST);

        $messageid = self::send($course, $cm, $context, $webcoached, $recipient);

        return [
            'status' => !empty($messageid),
            'warnings' => [],
        ];
    }

    /**
     * Builds and sends the notification.
     *
     * @param \stdClass $course Course record.
     * @param \cm_info $cm Course module.
     * @param \context_module $context Module context.
     * @param \stdClass $webcoached Activity instance record.
     * @param \stdClass $recipient User to notify.
     * @return mixed The message id, or false on failure.
     */
    protected static function send($course, $cm, $context, $webcoached, $recipient) {
        $url = new \moodle_url('/mod/webcoached/view.php', ['id' => $cm->id]);
        $name = format_string($webcoached->name, true, ['context' => $context]);

        // Resolve the body: per-activity text, or the language default when empty.
        $body = isset($webcoached->messagebody) ? trim($webcoached->messagebody) : '';
        if ($body === '') {
            $body = get_string('messagebodydefault', 'mod_webcoached');
        }

        // Substitute the {name} and {link} placeholders.
        $htmlbody = str_replace(['{name}', '{link}'], [s($name), \html_writer::link($url, $name)], $body);
        $plainbody = html_to_text(str_replace(['{name}', '{link}'], [$name, $url->out(false)], $body), 0, false);

        $message = new message();
        $message->component = 'mod_webcoached';
        $message->name = 'webcoachedmessage';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->subject = get_string('messagesubject', 'mod_webcoached', $name);
        $message->fullmessage = $plainbody;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $htmlbody;
        $message->smallmessage = $plainbody;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $name;
        $message->courseid = $course->id;

        return message_send($message);
    }

    /**
     * Describes the return value for execute.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True if the message was sent'),
            'warnings' => new external_warnings(),
        ]);
    }
}
