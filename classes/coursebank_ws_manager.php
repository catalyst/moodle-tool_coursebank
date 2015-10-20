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

/**
 * Class that handles outgoing web service requests.
 *
 */
class coursebank_ws_manager {
    private $curlhandle;
    private $baseurl;
    private $proxyurl;
    private $proxyuser;
    private $proxypass;
    private $proxyport;

    // HTTP response codes.
    const WS_HTTP_BAD_REQUEST = 400;
    const WS_HTTP_UNAUTHORIZED = 401;
    const WS_HTTP_CONFLICT = 409;
    const WS_HTTP_NOTFOUND = 404;
    const WS_HTTP_OK = 200;
    const WS_HTTP_CREATED = 201;
    const WS_HTTP_INT_ERR = 500;
    const WS_HTTP_DEFAULT_TIMEOUT_SECS = 100;  // Default timeout for request duration.
    const WS_HTTP_DEFAULT_BACKUP_COMPLETE_TIMEOUT_SECS = 600;  // Request duration for backup complete (TODO: remove).

    const WS_AUTH_SESSION_KEY = 'sesskey';

    /**
     * @param string  $url            Target URL
     * @param int     $timeout        Request time out (seconds)
     */
    public function __construct($url, $timeout=10) {
        $this->baseurl = $url;
        $curlopts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_URL => $url
        );
        $this->curlhandle = curl_init();
        curl_setopt_array($this->curlhandle, $curlopts);

        // Set up proxy configuration.
        $this->proxyurl = get_config('tool_coursebank', 'proxyurl');
        if (!empty($this->proxyurl)) {
            $this->proxyuser = get_config('tool_coursebank', 'proxyuser');
            $this->proxypass = get_config('tool_coursebank', 'proxypass');
            $this->proxyport = get_config('tool_coursebank', 'proxyport');
        }
    }
    /**
     * Close the associated curl handler object
     */
    public function close() {
        curl_close($this->curlhandle);
    }
    /**
     * Send the provided data in JSON encoding as an HTTP request
     *
     * @param string  $resource     URL fragment to append to the base URL
     * @param array   $data         Associative array of request data to send
     * @param string  $method       Request method. Defaults to POST.
     * @param int     $retries      Max number of attempts to make before
     *                              failing
     * @param mixed   $auth         string: Authorization string
     *                              array: 'sesskey' => Authorisation string
     *                                     'data'    => test data string
     * @param string  $timeoutsecs  Request timeout.
     *
     * @return array or bool false  Associative array of the form:
     *                                  'body' => <response body array>,
     *                                  'response => <response info array>
     *                              Or false if connection could not be made
     */
    protected function send($resource='', $data=array(), $method='POST', $auth=null, $retries=4,
                            $timeoutsecs=self::WS_HTTP_DEFAULT_TIMEOUT_SECS) {
        global $CFG;

        $data['moodle_version'] = $CFG->version;
        $data['plugin_version'] = get_config('tool_coursebank', 'version');

        $postdata = json_encode($data);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postdata)
        );
        if (isset($auth)) {
            if (is_array($auth)) {
                foreach ($auth as $k => $v) {
                    $header[] = $k . ': ' . $v;
                }
            } else {
                $header[] = self::WS_AUTH_SESSION_KEY . ': ' . $auth;
            }
        }
        $curlopts = array(
            CURLOPT_TIMEOUT => $timeoutsecs,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_URL => $this->baseurl.'/'.$resource
        );
        // Set proxy configuration if it is enabled.
        if (!empty($this->proxyurl)) {
            $curlopts[CURLOPT_PROXY] = $this->proxyurl;
            $curlopts[CURLOPT_PROXYUSERPWD] = "$this->proxyuser:$this->proxypass";
            $curlopts[CURLOPT_PROXYPORT] = $this->proxyport;
        }
        curl_setopt_array($this->curlhandle, $curlopts);
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $errno = null;
            try {
                $result = curl_exec($this->curlhandle);
            } catch (Exception $e) {
                $body = new stdClass();
                $body->error = $e->getCode();
                $body->error_desc = $e->getMessage();
                $response = new coursebank_http_response($body, false, $curlopts);
                break;
            }
            $info = curl_getinfo($this->curlhandle);
            $errno = curl_errno($this->curlhandle);
            if ($errno == CURLE_OPERATION_TIMEOUTED) { // Older versions of php use this.
                // We timed out - try again later, don't do any more retries.
                $response = new coursebank_http_response(false, false, $curlopts);
                break;
            }
            if ($result) {
                $body = json_decode($result);
                $response = new coursebank_http_response($body, $info, $curlopts);
                break;
            }
        }
        if (!isset($response)) {
            $response = new coursebank_http_response(false, false, $curlopts);
        }
        if ($errno) {
            $response->curlerrno = $errno;
        }
        $response->log_response();
        return $response;
    }

    /**
     * Send the provided data in JSON encoding as an HTTP request.
     *
     * Call "send" to handle sending a web service request. If the request
     * fails, attempt to reauthenticate with the site authentication token,
     * and use the resulting session key to reattempt the original request.
     * If this in turn fails, return the response to the caller.
     *
     * @param string  $resource     URL fragment to append to the base URL
     * @param array   $data         Associative array of request data to send
     * @param string  $method       Request method. Defaults to POST.
     * @param int     $retries      Max number of attempts to make before
     *                              failing
     * @param mixed   $auth         string: Authorization string
     *                              array: 'sesskey' => Authorisation string
     *                                     'data'    => test data string
     * @param string  $timeoutsecs  Request timeout.
     *
     * @return array or bool false  Associative array of the form:
     *                                  'body' => <response body array>,
     *                                  'response => <response info array>
     *                              Or false if connection could not be made
     */
    protected function send_authenticated($resource='', $data=array(), $method='POST', $auth=null, $retries=4,
                                          $timeoutsecs=self::WS_HTTP_DEFAULT_TIMEOUT_SECS) {

        // Don't try sending unless we already have a session key.
        $result = false;
        if ($auth) {
            $result = $this->send($resource, $data, $method, $auth, $retries, $timeoutsecs);
        }
        if (!$result || $result->httpcode == self::WS_HTTP_UNAUTHORIZED) {
            $token = get_config('tool_coursebank', 'authtoken');
            if ($this->post_session($token) === true) {
                $sesskey = tool_coursebank::get_session();
                return $this->send($resource, $data, $method, $sesskey, $retries, $timeoutsecs);
            }
        }
        return $result;
    }
    /**
     * Send a session start request.
     *
     * @param string    $hash      Authorization string
     */
    public function post_session($hash) {
        global $USER;
        $authdata = array(
            'hash' => $hash,
        );
        $response = $this->send('session', $authdata, 'POST');
        $sessionset = false;

        if ($response->httpcode == self::WS_HTTP_CREATED) {
            $tagsesskey = self::WS_AUTH_SESSION_KEY;
            $event = 'get_session';
            $info = get_string('eventgetsession', 'tool_coursebank');
            if (isset($response->body->$tagsesskey)) {
                $sesskey = trim((string) $response->body->$tagsesskey);
                $sessionset = tool_coursebank::set_session($sesskey);
            }
        } else {
            $event = 'get_session_failed';
            $info = get_string('eventgetsessionfailed', 'tool_coursebank');
        }

        coursebank_logging::log_event(
            $info,
            $event,
            'Create new session key',
            coursebank_logging::LOG_MODULE_COURSE_BANK,
            SITEID,
            '',
            $USER->id,
            array()
        );
        return $sessionset;
    }
    /**
     * Send a test request
     *
     * @param string  $auth         Authorization string
     * @param string  $data         Test data string
     * @param int     $count        Optional - applicable to speed test. Iteration number.
     * @param int     $testsize     Optional - applicable to speed test. Size of data getting sent.
     * @param int     $starttime    Optional - applicable to speed test. Start time of the test.
     * @param int     $endtime      Optional - applicable to speed test. End time - sent back.
     *
     * @return array or bool false  Associate array response
     */
    public function get_test($auth, $data='', $count=0, $testsize=0, $starttime=0, &$endtime=0) {
        $headers = array(
            self::WS_AUTH_SESSION_KEY => $auth
        );
        $json = array(
            'data' => base64_encode($data)
        );
        $result = $this->send_authenticated('test', $json, 'GET', $headers);
        $endtime = microtime(true);
        return $result;
    }
    /**
     * Get a backup resource.
     *
     * @param string $auth      Authorization string.
     * @param string $uniqueid  UUID referencing external course bank backup resource.
     * @param bool   $download  Whether or not to generate a download link.
     */
    public function get_backup($sesskey, $uniqueid, $download=false) {
        $headers = array(self::WS_AUTH_SESSION_KEY => $sesskey);
        if ($download) {
            $headers['download'] = 'true';
        }

        $result = $this->send_authenticated('backup/'. $uniqueid, array(), 'GET', $headers);
        return $result;
    }
    public static function get_backup_validated_hash($data) {
        return md5($data['fileid'] . ',' .$data['uuid'] . ',' . $data['filename'] . ',' . $data['filesize']);
    }
    public static function check_post_backup_data_is_same(coursebank_http_response $httpresponse, $data) {

        $fields = array(
                'uuid' => 'uuid',
                'fileid' => 'fileid',
                'filename' => 'filename',
                'filehash' => 'filehash',
                'filesize' => 'filesize',
                'chunksize' => 'chunksize',
                'totalchunks' => 'totalchunks',
                'courseid' => 'courseid',
                'coursename' => 'coursename',
                'categoryid' => 'categoryid',
                'categoryname' => 'categoryname'
        );
        // Check that each of the above fields matches, log an error if not.
        foreach ($fields as $datafield => $responsefield) {
            if (!isset($httpresponse->body->$responsefield) || $httpresponse->body->$responsefield != $data[$datafield]) {
                return false;
            }
        }

        $dtresponse = new DateTime($httpresponse->body->coursestartdate);
        $dtdata = new DateTime($data['startdate']);
        $responsedate = $dtresponse->format('Y-m-d H:i:s');
        $datadate = $dtdata->format('Y-m-d H:i:s');
        if ($responsedate != $datadate) {
            return false;
        }

        return true;
    }
    /**
     * Create a backup resource.
     *
     * @param array  $data            Array of data to post.
     * @param string $sessionkey      Session token
     * @param int    $retries         Number of retries to attempt sending
     *                                when an error occurs.
     *
     */
    public function post_backup($data, $sessionkey, $retries=4) {

        $response = $this->send_authenticated('backup', $data, 'POST', $sessionkey, $retries);

        if ($response->httpcode == self::WS_HTTP_CREATED) {
            // Make sure the hash is good.
            $returnhash = $response->body->hash;
            $validatehash = self::get_backup_validated_hash($data);
            if ($returnhash != $validatehash) {
                return $response;
            } else {
                return (int) $data['fileid'];
            }
        } else if ($response->httpcode == self::WS_HTTP_CONFLICT) {
            // The backup already exists.
            // Check the data coming back is the same as what we sent.
            if (!self::check_post_backup_data_is_same($response, $data)) {
                // Need to deal with this.
                return $response;
            }

            // Post data is the same (verified) and CourseBank says it is complete.
            //
            // This could happen if moodle times out doing /backupcomplete but Coursebank
            // completes the process.

            if (isset($response->body->is_completed) && $response->body->is_completed == true) {
                return $response;
            }

            // It's the same, continue.
            return (int) $data['fileid'];
        }
        // Unexpected response or no response received.
        // Should retry again later.
        return $response;
    }

    /**
     * Update a backup resource.
     *
     * @param string $sessionkey      Session token
     * @param array  $data            Array of data to update.
     * @param string $uniqueid        UUID of coursebank record.
     * @param int    $retries         Number of retries to attempt sending
     *                                when an error occurs.
     *
     */
    public function put_backup($sessionkey, $data, $uniqueid, $retries=4) {

        $response = $this->send_authenticated('backup/' . $uniqueid, $data, 'PUT', $sessionkey);

        if ($response->httpcode == self::WS_HTTP_OK) {
            // Make sure the hash is good.
            $returnhash = $response->body->hash;

            $validatehash = self::get_backup_validated_hash($data);

            if ($returnhash != $validatehash) {
                // Hashes don't match, possible network error.
                // Should try again later.
                return $response;
            } else {
                // All good!
                return true;
            }
        }
        // Unexpected response or no response received.
        // Should retry again later.
        return $response;
    }

    /**
     * Update a backup resource that it's complete.
     *
     * @param string $auth    Authorization string.
     * @param obj    $backup  tool_coursebank record object, including uniqueid.
     * @param int    $retries Number of retry attempts to make.
     *
     */
    public function put_backup_complete($sessionkey, $data, $backup, $retries=4) {
        $uniqueid = $backup->uniqueid;
        return $this->send_authenticated('backupcomplete/' . $uniqueid, $data, 'PUT', $sessionkey, $retries,
                                         self::WS_HTTP_DEFAULT_BACKUP_COMPLETE_TIMEOUT_SECS);
    }
    /**
     * Transfer chunk
     *
     * @param string $auth      Authorization string
     * @param string $uniqueid  UUID referencing external course bank backup resource
     * @param int    $chunk     Chunk number
     * @param array  $data      Data for transfer
     *
     */
    public function put_chunk($data, $uniqueid, $chunknumber, $sessionkey, $retries=4) {

        // Grab the original data so we don't have to decode it to check the hash.
        $originaldata = $data['original_data'];
        unset($data['original_data']);
        $response = $this->send_authenticated('chunks/' . $uniqueid . '/' . $chunknumber, $data, 'PUT', $sessionkey, $retries);

        if ($response->httpcode == self::WS_HTTP_OK) {
            // Make sure the hash is good.
            $returnhash = $response->body->chunkhash;
            $validatehash = md5($originaldata);
            if ($returnhash == $validatehash) {
                return true;
            }
        }
        // Unexpected response or no response received.
        return $response;
    }

    /**
     * Remove chunk
     *
     * @param string $auth           Authorization string
     * @param string $uniqueid       UUID referencing external course bank backup resource
     * @param int    $chunkiterator  Chunk number
     *
     */
    public function delete_chunk($sessionkey, $uniqueid, $chunkiterator, $retries=4) {
        $result = $this->send_authenticated('chunks/' . $uniqueid . '/' . $chunkiterator, array(), 'DELETE', $sessionkey, $retries);
        return $result;
    }
    /**
     * Get list of backup files available for download from external
     * course bank instance.
     *
     * One of the parameters is an Associative array $params
     * A simple example:
     *
     *       Array
     *       (
     *           [coursefullname] => Array
     *               (
     *                   [0] => Array
     *                       (
     *                           [operator] => LIKE
     *                           [value] => test
     *                       )
     *
     *               )
     *
     *           [filetimemodified] => Array
     *               (
     *                   [0] => Array
     *                       (
     *                           [operator] => >=
     *                           [value] => 1428415200
     *                       )
     *
     *               )
     *
     *       )
     *
     * The first level keys contain field names (e.g coursefullname,
     * backupfilename, filesize, filetimemodified, status)
     * The second level is an array of filters
     * Each filter is an associative array with following keys: operator, value
     * Operator may contain following values:
     * <>       - not equal
     * >=       - more or equal
     * <=       - less or equal
     * =        - equal
     * >        - more then
     * <        - less then
     * LIKE     - contains
     * NOT LIKE - does not contain
     * %LIKE    - starts with
     * LIKE%    - ends with
     * EMPTY    - empty
     *
     * @param string $sesskey  Session key authorization string
     * @param array $params Associative array of parameters
     * @param string $sort A field to sort by
     * @param string $dir The sort direction ASC|DESC
     * @param int $page The page or records to return
     * @param int $recordsperpage The number of records to return per page
     * @return std Class
     */
    public function get_downloads($sesskey, array $params = null, $sort ='', $dir='ASC', $page=0, $recordsperpage=0) {
        $url = 'downloads';
        $url .= '?page=' . (int) $page . '&perpage=' . (int) $recordsperpage;
        $url .= '&sort=' . $sort . '&order=' . $dir;

        $nonemptyarray = is_array($params) && !empty($params);
        $query = $nonemptyarray ? array('query' => $params) : array();
        $result = $this->send_authenticated($url, $query, 'GET', $sesskey);
        return $result;
    }
    /**
     * Get count of backup files available from external course bank instance.
     */
    public function get_downloadcount($sesskey, array $params = null) {
        $nonemptyarray = is_array($params) && !empty($params);
        $query = $nonemptyarray ? array('query' => $params) : array();
        $result = $this->send_authenticated('downloadcount', $query, 'GET', $sesskey);
        return $result;
    }
}

