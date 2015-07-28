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
 *
 * @package    tool_coursebank
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @author     Ghada El-Zoghbi <ghada@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * HTTP response class, used to pass around request responses
 *
 */
class coursebank_http_response {
     public $body;
     public $info;
     public $httpcode;
     public $request;
     public $error;
     public $errordesc;
    /**
     * Constructor method for http_response object.
     *
     * @param obj   $body
     * @param array $info
     * @param array $request
     */
    public function __construct($body=false, $info=false, $request=null) {

        $responsereceived = isset($info['http_code']);
        $this->httpcode = $responsereceived ? $info['http_code'] : false;
        $this->body = $body;
        $this->info = $info;
        $this->request = $request;
        if (isset($body->error)) {
            $this->error = $body->error;
        }
        if (isset($body->error_desc)) {
            $this->errordesc = $body->error_desc;
        }

    }
    /**
     * Method to log the http response as a Moodle event. This is intended
     * for scenarios where an unexpected http response is encountered, and
     * data about this response and the initial request may be helpful for
     * debugging.
     *
     * @param int    $courseid        Moodle course ID.
     * @param int    $coursebankid   Course bank ID.
     * @param string $info            Additional information.
     */
    public function log_http_error($courseid, $coursebankid, $info='') {
        global $CFG;

        if (tool_coursebank::legacy_logging()) {
            return $this->log_http_error_legacy($courseid, $coursebankid);
        }
        $info = $this->info;
        $body = (array)$this->body;
        $request = $this->request;
        $request[CURLOPT_POSTFIELDS] = (array) json_decode($request[CURLOPT_POSTFIELDS]);

        // Don't include data in event unless debugging is turned up.
        if ($CFG->debug >= DEBUG_DEVELOPER) {
            unset($request[CURLOPT_POSTFIELDS]['data']);
            unset($body->data);
        }

        $otherdata = array(
            'courseid'      => $courseid,
            'coursebankid' => $coursebankid,
            'body'          => $body,
            'info'          => $info,
            'httpcode'      => $this->httpcode,
            'request'       => $request,
            );
        if (isset($this->error)) {
            $otherdata['error'] = $this->error;
        }
        if (isset($this->errordesc)) {
            $otherdata['error_desc'] = $this->errordesc;
        }
        $eventdata = array(
            'other'     => $otherdata,
            'context'   => context_system::instance(),
        );
        $event = \tool_coursebank\event\transfer_interrupted::create($eventdata);
        $event->trigger();
        return true;
    }
    private function log_http_error_legacy($courseid, $coursebankid) {
        global $USER;

        $info = "Transfer of backup with moodle course bank id $coursebankid " .
                "interrupted: URL: " . $this->request[CURLOPT_URL] .
                "METHOD: " . $this->request[CURLOPT_CUSTOMREQUEST] . " ";

        if (!isset($this->httpcode)) {
            $info .= "No http response received";
        } else {
            $info .= "HTTP RESPONSE: " . $this->httpcode . " ";
        }

        if (isset($this->error)) {
            $info .= " ERROR: " . $this->error;
        }
        if (isset($this->errordesc)) {
            $info .= " ERROR DESC: " . $this->errordesc;
        }

        add_to_log(SITEID, 'Course bank', 'Transfer error', '', $info, 0, $USER->id);
        return true;
    }

    /**
     * Log the HTTP response if appropriate.
     *
     * First, check if the response should be logged based on the currently
     * configured debug level, then log the response using either legacy
     * logging for older versions of Moodle, or trigger an event for newer
     * Moodle versions.
     */
    public function log_response() {
        if (!$this->should_response_be_logged()) {
            return false;
        }
        if (tool_coursebank::legacy_logging()) {
            $this->log_response_legacy();
            return true;
        } else {
            $eventdata = $this->generate_event_data();
            $event = \tool_coursebank\event\http_request::create($eventdata);
            $event->trigger();
            return true;
        }
    }

    /**
     * Log the response using the legacy "add_to_log" function.
     */
    private function log_response_legacy() {
        global $USER;
        // Handle response time-out.
        if ($this->info === false) {
            if ($this->request[CURLOPT_URL]) {
                $description = 'Request to "' . s($this->request[CURLOPT_URL]) .
                    '" timed out.';
            } else {
                $description = 'HTTP request timed out';
            }
        } else {
            $description = $this->httpcode . ' response received from "' .
                    s($this->request[CURLOPT_URL]) . '"';

            if (isset($this->error_desc)) {
                $description .= ' ('.s($this->error_desc).')';
            }
        }

        add_to_log(
                SITEID,
                'Coursebank',
                'HTTP response',
                '',
                $description,
                0,
                $USER->id
        );
    }
    /**
     * Method to determine if this HTTP response should be logged to the
     * Moodle log, based on the current debugging level set in the Moodle
     * config and the original request method (We make a lot of chunk PUT
     * method requests, so we only log these if debugging is turned up).
     *
     * @return boolean True if response should be logged.
     */
    private function should_response_be_logged() {
        global $CFG;

        // Test if the HTTP code is a success or redirection (2** or 3**).
        $issuccess = preg_match(
                '/^[23][0-9]{2}$/',
                (string) $this->httpcode
        );
        // Test if the the request was a chunk PUT method.
        $ischunkmethod = preg_match(
                '/^.*\/chunk/',
                $this->request[CURLOPT_URL]
        );
        $ischunkput = $ischunkmethod &&
                ($this->request[CURLOPT_CUSTOMREQUEST] == 'PUT');

        switch ($CFG->debug) {
            case DEBUG_NONE:
                return false;
            break;
            case DEBUG_MINIMAL:
                return !$issuccess;
            break;
            case DEBUG_NORMAL:
                return !$issuccess || !$ischunkput;
            break;
            case DEBUG_ALL:
            case DEBUG_DEVELOPER:
            default:
                return true;
        }
    }
    /**
     * This method generates the necessary data to log an event for this response.
     *
     * @return array $eventdata  Array suitable for inclusion in an http_request
     *                           event object. i.e. of the form:
     *
     *                           array('other'   => array(...),
     *                                 'context' => <valid Moodle context>)
     */
    public function generate_event_data() {
        global $CFG;

        // We don't want to include the binary data in our logging.
        $request = (array) $this->request;
        $body = json_encode((array) $this->body);
        $request[CURLOPT_POSTFIELDS] = (array) json_decode($request[CURLOPT_POSTFIELDS]);
        unset($request[CURLOPT_POSTFIELDS]['data']);

        // Handle response time-out.
        if ($this->info === false) {
            if ($this->request[CURLOPT_URL]) {
                $description = 'Request to "' . s($this->request[CURLOPT_URL]) .
                    '" timed out.';
            } else {
                $description = 'HTTP request timed out';
            }
            $otherdata = array(
                'info' => $description,
                'request'     => $request
            );
        } else {
            $description = $this->httpcode . ' response received from "' .
                    s($this->request[CURLOPT_URL]) . '"';
            $otherdata = array(
                'courseid'      => SITEID,
                'body'          => $body,
                'responseinfo'  => json_encode($this->info),
                'httpcode'      => $this->httpcode,
                'request'       => $request,
                'info'          => $description
                );
            if (isset($this->error)) {
                $otherdata['error'] = $this->error;
            }
            if (isset($this->error_desc)) {
                $otherdata['error_desc'] = $this->error_desc;
                $otherdata['info'] .= ' - '. $this->error_desc;
            }
        }

        $eventdata = array(
            'other'     => $otherdata,
            'context'   => context_system::instance()
        );

        return $eventdata;
    }
}
