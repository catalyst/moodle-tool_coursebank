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
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @author     Ghada El-Zoghbi <ghada@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

abstract class tool_coursestore {

    // Status.
    const STATUS_NOTSTARTED = 0;
    const STATUS_INPROGRESS = 1;
    const STATUS_FINISHED = 2;
    const STATUS_ERROR = 99;
    /**
     * Returns an array of available statuses
     * @return array of availble statuses
     */
    public static function get_statuses() {
        $notstarted   = get_string('statusnotstarted', 'tool_coursestore');
        $inprogress   = get_string('statusinprogress', 'tool_coursestore');
        $statuserror  = get_string('statuserror', 'tool_coursestore');
        $finished     = get_string('statusfinished', 'tool_coursestore');

        $statuses = array(
            self::STATUS_NOTSTARTED => $notstarted,
            self::STATUS_INPROGRESS => $inprogress,
            self::STATUS_FINISHED   => $finished,
            self::STATUS_ERROR      => $statuserror
        );

        return $statuses;
    }

    /**
     * Get the stored session key for use with the course bank REST API if one
     * exists.
     *
     * @return string or bool false  Session key
     */
    public static function get_session() {
        return get_config('tool_coursestore', 'sessionkey');

    }

    /**
     * Set the session key for use with the course bank REST API
     *
     * @return bool  Success/failure
     */
    public static function set_session($sessionkey) {
        if (set_config('sessionkey', $sessionkey, 'tool_coursestore')) {
            return true;
        }
        return false;
    }

    /**
     * Test that a connection to the configured web service consumer can be
     * made successfully.
     *
     * @param coursestore_ws_manager $wsman  Web service manager object
     * @return bool                          True for success, false otherwise
     */
    public static function check_connection(coursestore_ws_manager $wsman, $auth=false) {
        global $USER;

        $success = false;
        if ($auth) {
            $checkresult = $wsman->get_test($auth);
            $success = $checkresult->httpcode == coursestore_ws_manager::WS_HTTP_OK;
        }

        // No sess key provided, or sesskey rejected. Try starting a new session.
        $token = get_config('tool_coursestore', 'authtoken');
        if ($token && !$success) {
            $sessresponse = $wsman->post_session($token);
            if ($sessresponse->httpcode != coursestore_ws_manager::WS_HTTP_CREATED) {
                $success = false;
            }
            $sesskey = self::get_session();
            $checkresult = $wsman->get_test($sesskey);
            $success = $checkresult->httpcode == coursestore_ws_manager::WS_HTTP_OK;
        }
        if(self::legacy_logging()) {
            $result = $success ? 'passed' : 'failed';
            $info = "Connection check $result.";
            add_to_log(SITEID, 'Course store', 'Connection check', '', $info, 0, $USER->id);
        } else {
            $otherdata = array(
                'conncheckaction' => 'conncheck',
                'status' => $success
                );
            $eventdata = array(
                'other' => $otherdata,
                'context' => context_system::instance()
            );
            $event = \tool_coursestore\event\connection_checked::create($eventdata);
            $event->trigger();
        }
        return $success;
    }
    /**
     * Test the speed of a transfer of $testsize kilobytes. A total
     * of $count HTTP requests will be sent. If the request fails, make
     * $retry number of subsequent attempts.
     *
     * @param coursestore_ws_manager $wsman  Web service manager object
     * @param int                 $testsize  Approximate size of test transfer
     *                                       in kB
     * @param int                    $count  Number of HTTP requests to make
     * @param int                    $retry  Number of retry attempts
     * @param string                  $auth  Session key
     *
     * @return int                  Approximate connection speed in kbps
     */
    public static function check_connection_speed(coursestore_ws_manager $wsman,
            $testsize, $count, $retry, $auth) {
        global $USER;

        $check = str_pad('', $testsize * 6, '0');
        $start = microtime(true);

        // Make $count requests with the dummy data.
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j <= $retry; $j++) {
                $response = $wsman->get_test($auth, $check);
                if ($response->httpcode == coursestore_ws_manager::WS_HTTP_OK) {
                    break;
                }
            }
            // If $maxhttps unsuccessful attempts have been made.
            if ($response->httpcode != coursestore_ws_manager::WS_HTTP_OK) {
                $speed = 0;
                break;
            }
        }
        if (!isset($speed)) {
            $elapsed = microtime(true) - $start;

            // Convert 'total kB transferred'/'total time' into kb/s.
            $speed = round(($testsize * $count * 8 ) / $elapsed, 2);
        }
        if(self::legacy_logging()) {
            $result = $speed === 0 ? 'failed' : "$speed kbps";
            $info = "Connection speed test: $result.";
            add_to_log(SITEID, 'Course store', 'Connection check', '', $info, 0, $USER->id);
        } else {
            $otherdata = array(
                'conncheckaction' => 'speedtest',
                'speed' => $speed
            );
            $eventdata = array(
                'other' => $otherdata,
                'context' => context_system::instance()
            );
            $event = \tool_coursestore\event\connection_checked::create($eventdata);
            $event->trigger();
        }
        return $speed;

    }
    public static function get_config_chunk_size() {
        return get_config('tool_coursestore', 'chunksize');
    }

    public static function calculate_total_chunks($chunksize, $filesize) {
        return ceil($filesize / ($chunksize * 1000));
    }
    /**
     * Function to fetch course store records for display on the summary
     * page.
     *
     */
    public static function get_summary_data($sort='status', $dir='ASC', $extraselect='',
                                            array $extraparams=null, $page=0, $recordsperpage=0) {
        global $DB;

        $fieldstosort = array('coursefullname', 'filetimemodified', 'backupfilename', 'filesize', 'status');

        if (in_array($sort, $fieldstosort)) {
            $sort = "$sort $dir";
        } else {
            $sort = '';
        }

        $results = $DB->get_records_select('tool_coursestore', $extraselect, $extraparams, $sort, '*', $page, $recordsperpage);
        $count = $DB->count_records_select('tool_coursestore', $extraselect, $extraparams);

        $statusmap = self::get_statuses();

        foreach ($results as $result) {
            if (isset($statusmap[$result->status])) {
                $result->status = $statusmap[$result->status];
            }
        }

        return array('results' => $results, 'count' => $count);
    }
    /**
     * Convenience function to handle sending a file along with the relevant
     * metatadata.
     *
     * @param object $backup    Course store database record object
     *
     */
    public static function send_backup($backup) {
        global $CFG, $DB, $USER;

        // Copy the backup file into our storage area so there are no changes to the file
        // during transfer, unless file already exists.
        if ($backup->isbackedup == 0) {
            $backup = self::copy_backup($backup);
        }

        if ($backup === false) {
            return false;
        }

        // Get required config variables.
        $urltarget = get_config('tool_coursestore', 'url');
        $timeout = get_config('tool_coursestore', 'timeout');
        $retries = get_config('tool_coursestore', 'requestretries');
        $token = get_config('tool_coursestore', 'authtoken');
        $sessionkey = self::get_session();

        // Initialise, check connection.
        $wsmanager = new coursestore_ws_manager($urltarget, $timeout);
        if (!self::check_connection($wsmanager, $sessionkey)) {
            $backup->status = self::STATUS_ERROR;
            $DB->update_record('tool_coursestore', $backup);
            $wsmanager->close();
            return false;
        }

        // Update again in case a new session key was given.
        $sessionkey = self::get_session();

        // Chunk size is set in kilobytes.
        $chunksize = $backup->chunksize * 1000;

        // Open input file.
        $coursestorefilepath = self::get_coursestore_filepath($backup);
        $file = fopen($coursestorefilepath, 'rb');

        // Log transfer_started/resumed event.
        $transferaction = $backup->chunknumber == 0 ? 'started' : 'resumed';
        if(self::legacy_logging()) {
            $info = "Transfer of backup with course store id $backup->id " .
                    "started. (Course ID: $backup->courseid)";

            add_to_log(SITEID, 'Course store', 'Transfer '.$transferaction, '', $info, 0, $USER->id);
        } else {
            $otherdata = array(
                'courseid' => $backup->courseid,
                'coursestoreid' => $backup->id
                );
            $eventdata = array(
                'other' => $otherdata,
                'context' => context_system::instance()
            );
            $eventclass = '\tool_coursestore\event\transfer_' . $transferaction;
            $event = $eventclass::create($eventdata);
            $event->trigger();
        }

        // Set offset based on chunk number.
        if ($backup->chunknumber != 0) {
            fseek($file, $backup->chunknumber * $chunksize);
        } else if ($backup->chunknumber == 0) {
            // Initialise the backup record.
            $coursedate = '';
            if ($backup->coursestartdate > 0) {
                $dt = new DateTime("@" . $backup->coursestartdate);
                $coursedate = $dt->format('Y-m-d H:i:s');
            }
            $data = array(
                'fileid'       => $backup->id,
                'filehash'     => $backup->contenthash,
                'filename'     => $backup->backupfilename,
                'filesize'     => $backup->filesize,
                'chunksize'    => $backup->chunksize,
                'totalchunks'  => $backup->totalchunks,
                'courseid'     => $backup->courseid,
                'coursename'   => $backup->courseshortname,
                'startdate'    => $coursedate,
                'categoryid'   => $backup->categoryid,
                'categoryname' => $backup->categoryname,
            );
            $coursebankid = $wsmanager->post_backup($data, $sessionkey, $retries);
            // Unexpected http response or none received.
            if (!is_int($coursebankid)) {
                $backup->status = self::STATUS_ERROR;
                $DB->update_record('tool_coursestore', $backup);
                $wsmanager->close();
                echo("create backup failed; chunknumber=" . $backup->chunknumber . ".\n");
                // Log a transfer interruption event.
                $coursebankid->log_http_error($backup->courseid, $backup->id);
                return false;
            }
            $backup->status = self::STATUS_INPROGRESS;
            $DB->update_record('tool_coursestore', $backup);
        }

        // Read the file in chunks, attempt to send them.
        while ($contents = fread($file, $chunksize)) {

            $data = array(
                'data'          => base64_encode($contents),
                'chunksize'     => $chunksize,
                'original_data' => $contents,
            );

            $response = $wsmanager->put_chunk(
                    $data,
                    $backup->id,
                    $backup->chunknumber,
                    $sessionkey,
                    $retries
            );

            if ($response === true) {
                $backup->timechunkcompleted = time();
                $backup->chunknumber++;
                if ($backup->status == self::STATUS_ERROR) {
                    $backup->chunkretries = 0;
                    $backup->status = self::STATUS_INPROGRESS;
                }
                $DB->update_record('tool_coursestore', $backup);
            } else {
                if ($backup->status == self::STATUS_ERROR) {
                    $backup->chunkretries++;
                } else {
                    $backup->status = self::STATUS_ERROR;
                }
                $DB->update_record('tool_coursestore', $backup);
                // Log a transfer interruption event.
                $response->log_http_error($backup->courseid, $backup->id);
                return false;
            }
        }

        if ($backup->chunknumber == $backup->totalchunks) {
            $data = array(
                'fileid' => $backup->id,
                'filename' => $backup->backupfilename,
                'filehash' => $backup->contenthash,
                'filesize' => $backup->filesize,
                'chunksize' => $backup->chunksize,
                'totalchunks' => $backup->totalchunks
            );
            if (!isset($coursebankid)) {
                $coursebankid = $backup->id;
            }
            // Confirm the backup file as complete.
            $completion = $wsmanager->put_backup_complete($sessionkey, $data, $coursebankid);
            if ($completion->httpcode != coursestore_ws_manager::WS_HTTP_OK) {
                // Log a transfer interruption event.
                $completion->log_http_error($backup->courseid, $backup->id);
                return false;
            }
            $backup->status = self::STATUS_FINISHED;
            $DB->update_record('tool_coursestore', $backup);

            // Log transfer_completed event.
            if(self::legacy_logging()) {
                $info = "Transfer of backup with course store id $backup->id " .
                        "completed. (Course ID: $backup->courseid)";

                add_to_log(SITEID, 'Course store', 'Transfer completed', '', $info, 0, $USER->id);
           } else {
                $otherdata = array(
                    'courseid' => $backup->courseid,
                    'coursestoreid' => $backup->id
                    );
                $eventdata = array(
                    'other' => $otherdata,
                    'context' => context_system::instance()
                );
                $event = \tool_coursestore\event\transfer_completed::create($eventdata);
                $event->trigger();
           }
        }

        $wsmanager->close();
        fclose($file);
        return true;
    }

    public static function get_coursestore_data_dir() {
        global $CFG;
        return $CFG->dataroot . "/coursestore";
    }

    public static function get_coursestore_filepath($backup) {
        return self::get_coursestore_data_dir() . "/" . $backup->contenthash;
    }

    /**
     * Convenience function to handle copying the backup file to the designated storage area.
     *
     * @param object $backup    Course store database record object
     *
     */
    public static function copy_backup($backup) {
        global $CFG, $DB;

        // Construct the full path of the backup file.
        $moodlefilepath = $CFG->dataroot . '/filedir/' .
                substr($backup->contenthash, 0, 2) . '/' .
                substr($backup->contenthash, 2, 2) . '/' . $backup->contenthash;

        if (!is_readable($moodlefilepath)) {
            throw new file_serving_exception();
        }
        if (!is_writable(self::get_coursestore_data_dir())) {
            throw new invalid_dataroot_permissions();
        }

        $coursestorefilepath = self::get_coursestore_filepath($backup);
        copy($moodlefilepath, $coursestorefilepath);

        $backup->isbackedup = 1; // We have created a copy.
        $DB->update_record('tool_coursestore', $backup);

        return $backup;
    }

    /**
     * Convenience function to handle copying the backup file to the designated storage area.
     *
     * @param object $backup    Course store database record object
     *
     */
    public static function delete_backup($backup) {
        global $DB;

        $coursestorefilepath = self::get_coursestore_filepath($backup);

        if (!is_readable($coursestorefilepath)) {
            return false;
        }
        if (!is_writable(self::get_coursestore_data_dir())) {
            return false;
        }

        unlink($coursestorefilepath);

        $backup->isbackedup = 0; // We have deleted the copy.
        $DB->update_record('tool_coursestore', $backup);

        return true;
    }
    /**
     * Function to fetch course backup records from the Moodle DB, add them
     * to the course store table, and then process the files before sending
     * via web service to the configured course bank instance.
     *
     * - The query consists of three parts which are combined with a UNION
     *
     */
    public static function fetch_backups() {
        global $CFG, $DB;

        // Get a list of the course backups.
        $sqlcommon = "SELECT tcs.id,
                       tcs.backupfilename,
                       tcs.fileid,
                       tcs.chunksize,
                       tcs.totalchunks,
                       tcs.chunknumber,
                       tcs.timecreated,
                       tcs.timecompleted,
                       tcs.timechunksent,
                       tcs.timechunkcompleted,
                       tcs.chunkretries,
                       tcs.status,
                       tcs.isbackedup,
                       f.id AS f_fileid,
                       f.contenthash,
                       f.pathnamehash,
                       f.filename,
                       f.userid,
                       f.filesize,
                       f.timecreated AS filetimecreated,
                       f.timemodified AS filetimemodified,
                       cr.id AS courseid,
                       cr.fullname AS coursefullname,
                       cr.shortname AS courseshortname,
                       cr.category,
                       cr.startdate AS coursestartdate,
                       cc.id as categoryid,
                       cc.name as categoryname
                FROM {files} f
                INNER JOIN {context} ct on f.contextid = ct.id
                INNER JOIN {course} cr on ct.instanceid = cr.id
                INNER JOIN {course_categories} cc on cr.category = cc.id";

        $sqlselect = "SELECT tcs.id,
                       tcs.backupfilename,
                       tcs.fileid,
                       tcs.chunksize,
                       tcs.totalchunks,
                       tcs.chunknumber,
                       tcs.timecreated,
                       tcs.timecompleted,
                       tcs.timechunksent,
                       tcs.timechunkcompleted,
                       tcs.chunkretries,
                       tcs.status,
                       tcs.isbackedup,
                       f.id AS f_fileid,
                       tcs.contenthash,
                       tcs.pathnamehash,
                       f.filename,
                       tcs.userid,
                       tcs.filesize,
                       tcs.filetimecreated,
                       tcs.filetimemodified,
                       tcs.courseid,
                       tcs.coursefullname,
                       tcs.courseshortname,
                       cr.category,
                       tcs.coursestartdate,
                       tcs.categoryid,
                       tcs.categoryname
                FROM {files} f
                INNER JOIN {context} ct on f.contextid = ct.id
                INNER JOIN {course} cr on ct.instanceid = cr.id
                INNER JOIN {course_categories} cc on cr.category = cc.id";

        $sql = $sqlcommon . "
                LEFT JOIN {tool_coursestore} tcs on tcs.fileid = f.id
                WHERE tcs.id IS NULL
                AND ct.contextlevel = :contextcourse1
                AND   f.mimetype IN ('application/vnd.moodle.backup', 'application/x-gzip')
                UNION
                " . $sqlselect . "
                INNER JOIN {tool_coursestore} tcs on tcs.fileid = f.id
                WHERE ct.contextlevel = :contextcourse2
                AND   f.mimetype IN ('application/vnd.moodle.backup', 'application/x-gzip')
                AND   tcs.status IN (:statusnotstarted, :statusinprogress, :statuserror)
                UNION
                " . $sqlselect . "
                RIGHT JOIN {tool_coursestore} tcs on tcs.fileid = f.id
                WHERE f.id IS NULL
                AND tcs.isbackedup = 1
                ORDER BY timecreated";
        $params = array('statusnotstarted' => self::STATUS_NOTSTARTED,
                        'statuserror' => self::STATUS_ERROR,
                        'statusinprogress' => self::STATUS_INPROGRESS,
                        'contextcourse1' => CONTEXT_COURSE,
                        'contextcourse2' => CONTEXT_COURSE
                        );
        $rs = $DB->get_recordset_sql($sql, $params);

        $insertfields = array('filesize', 'filetimecreated',
                'filetimemodified', 'courseid', 'contenthash',
                'pathnamehash', 'userid', 'coursefullname',
                'courseshortname', 'coursestartdate', 'categoryid',
                'categoryname'
        );
        foreach ($rs as $coursebackup) {
            if (!isset($coursebackup->status)) {
                // The record hasn't been input in the course store table yet.
                $cs = new stdClass();
                $cs->backupfilename = $coursebackup->filename;
                $cs->fileid = $coursebackup->f_fileid;
                $cs->chunksize = self::get_config_chunk_size();
                $cs->totalchunks = self::calculate_total_chunks($cs->chunksize, $coursebackup->filesize);
                $cs->chunknumber = 0;
                $cs->status = self::STATUS_NOTSTARTED;
                $cs->isbackedup = 0; // No copy has been created yet.
                foreach ($insertfields as $field) {
                    $cs->$field = $coursebackup->$field;
                }
                $backupid = $DB->insert_record('tool_coursestore', $cs);

                $coursebackup->id = $backupid;
                $coursebackup->backupfilename = $cs->backupfilename;
                $coursebackup->fileid = $cs->fileid;
                $coursebackup->chunksize = $cs->chunksize;
                $coursebackup->totalchunks = $cs->totalchunks;
                $coursebackup->chunknumber = $cs->chunknumber;
                $coursebackup->timecreated = 0;
                $coursebackup->timecompleted = 0;
                $coursebackup->timechunksent = 0;
                $coursebackup->timechunkcompleted = 0;
                $coursebackup->chunkretries = 0;
                $coursebackup->status = $cs->status;
                $coursebackup->isbackedup = 0;
                $coursebackup->contenthash = $cs->contenthash;
                $coursebackup->pathnamehash = $cs->pathnamehash;
                $coursebackup->userid = $cs->userid;
                $coursebackup->filesize = $cs->filesize;
                $coursebackup->filetimecreated = $cs->filetimecreated;
                $coursebackup->filetimemodified = $cs->filetimemodified;
                $coursebackup->courseid = $cs->courseid;
                $coursebackup->coursefullname = $cs->coursefullname;
                $coursebackup->courseshortname = $cs->courseshortname;
                $coursebackup->coursestartdate = $cs->coursestartdate;
                $coursebackup->categoryid = $cs->categoryid;
                $coursebackup->categoryname = $cs->categoryname;
            }
            $result = self::send_backup($coursebackup);
            if ($result) {
                $delete = self::delete_backup($coursebackup);
                if (!$delete) {
                    $delfail = get_string(
                            'deletefailed',
                            'tool_coursestore',
                            $coursebackup->backupfilename
                    );
                    mtrace($delfail . "\n");
                }
            } else {
                $bufail = get_string(
                        'backupfailed',
                        'tool_coursestore',
                        $coursebackup->backupfilename
                );
                mtrace($bufail . "\n");
                // Stop sending backups until this one is resolved.
                break;
            }
        }
        $rs->close();
    }
    public static function legacy_logging() {
        global $CFG;
        if ((float) $CFG->version < 2014051200) {
            return true;
        } else {
            return false;
        }
    }
}

/**
 * Class to keep errors specific to this plugin.
 *
 */
abstract class tool_coursestore_error {
    // Errors.
    const ERROR_TIMEOUT              = 100;
    const ERROR_MAX_ATTEMPTS_REACHED = 101;
    // Etc. populate as needed.
}

/**
 * Class that handles outgoing web service requests.
 *
 */
class coursestore_ws_manager {
    private $curlhandle;
    private $baseurl;

    // HTTP response codes.
    const WS_HTTP_BAD_REQUEST = 400;
    const WS_HTTP_UNAUTHORIZED = 401;
    const WS_HTTP_CONFLICT = 409;
    const WS_HTTP_NOTFOUND = 404;
    const WS_HTTP_OK = 200;
    const WS_HTTP_CREATED = 201;
    const WS_HTTP_INT_ERR = 500;

    const WS_STATUS_SUCCESS_UPDATED = 200;
    const WS_STATUS_SUCCESS_CREATED = 201;
    const WS_STATUS_SUCCESS_READ = 202;
    const WS_STATUS_SUCCESS_DELETED = 203;
    const WS_STATUS_ERROR_INVALID_JSON_DATA = 400;
    const WS_STATUS_ERROR_HTTP_AUTHORISATION = 401;
    const WS_STATUS_ERROR_BACKUP_ALREADY_EXISTS = 409;
    const WS_STATUS_ERROR_INVALID_JSON_HASH = 420;
    const WS_STATUS_ERROR_DB_CONNECTION = 500;
    const WS_STATUS_ERROR_GENERATING_TOKEN = 422;
    const WS_STATUS_ERROR_SITE_REMOVED_FOR_TOKEN = 423;
    const WS_STATUS_ERROR_INVALID_USER_CREDENTIALS = 425;
    const WS_STATUS_ERROR_INACTIVE_SITE = 426;
    const WS_STATUS_ERROR_SAVING_DATA = 427;
    const WS_STATUS_ERROR_ENCODED_CHUNK_SIZE = 428;
    const WS_STATUS_ERROR_UNEXPECTED = 999;

    const WS_AUTH_SESSION_KEY = 'sesskey';

    /**
     * @param string  $url            Target URL
     * @param int     $timeout        Request time out (seconds)
     */
    public function __construct($url, $timeout) {
        $this->baseurl = $url;
        $curlopts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_URL => $url
        );
        $this->curlhandle = curl_init();
        curl_setopt_array($this->curlhandle, $curlopts);
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
     *
     * @return array or bool false  Associative array of the form:
     *                                  'body' => <response body array>,
     *                                  'response => <response info array>
     *                              Or false if connection could not be made
     */
    protected function send($resource='', $data=array(), $method='POST', $auth=null, $retries = 5) {
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
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_URL => $this->baseurl.'/'.$resource
        );
        curl_setopt_array($this->curlhandle, $curlopts);
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $result = curl_exec($this->curlhandle);
            $info = curl_getinfo($this->curlhandle);
            if ($result) {
                $body = json_decode($result);
                return new coursestore_http_response($body, $info, $curlopts);
            }
        }
        return new coursestore_http_response(false, false, $curlopts);
    }
    /**
     * Send a test request
     *
     * @param string  $auth         Authorization string
     * @param string  $data         Test data string
     * @return array or bool false  Associate array response
     */
    public function get_test($auth, $data=' ') {
        $headers = array(
            self::WS_AUTH_SESSION_KEY => $auth
        );
        $json = array(
            'data' => base64_encode($data)
        );
        $result = $this->send('test', $json, 'GET', $headers);
        return $result;
    }
    /**
     * Send a session start request.
     *
     * @param string    $hash      Authorization string
     */
    public function post_session($hash) {
        $authdata = array(
            'hash' => $hash,
        );
        $response = $this->send('session', $authdata, 'POST');
        if ($response->httpcode == self::WS_HTTP_CREATED) {
            if (isset($response->body->sesskey)) {
                $tagsesskey = self::WS_AUTH_SESSION_KEY;
                $sesskey = trim((string) $response->body->$tagsesskey);
                return tool_coursestore::set_session($sesskey);
            }
        } else {
            // Unexpected response or no response received.
            return $response;
        }
    }
    /**
     * Get a backup resource.
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     */
    public function get_backup($auth, $backupid) {
        return $this->send('backup'. $backupid, array(), 'GET', $auth);

    }
    /**
     * Create a backup resource.
     *
     * @param string $auth      Authorization string
     *
     */
    public function post_backup($data, $sessionkey, $retries) {

        $response = $this->send('backup', $data, 'POST', $sessionkey, $retries);

        if ($response->httpcode == self::WS_HTTP_CREATED) {
            // Make sure the hash is good.
            $returnhash = $response->body->hash;
            $validatehash = md5($data['fileid'] . ',' . $data['filename'] . ',' . $data['filesize']);
            if ($returnhash != $validatehash) {
                return $response;
            } else {
                return (int) $data['fileid'];
            }
        } else if ($response->httpcode == self::WS_HTTP_CONFLICT) {
            // The backup already exists, continue.
            return (int) $data['fileid'];
        }
        // Unexpected response or no response received.
        return $response;
    }

    /**
     * Update a backup resource.
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     *
     */
    public function put_backup_complete($sessionkey, $data, $backupid, $retries=4) {

        return $this->send('backupcomplete/' . $backupid, $data, 'PUT', $sessionkey);
    }
    /**
     * Get most recent chunk transferred for specific backup.
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     *
     */
    public function get_chunk($auth, $backupid) {
        return $this->send('chunks/' . $backupid, array(), 'GET', $auth);
    }
    /**
     * Transfer chunk
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     * @param int    $chunk     Chunk number
     * @param array  $data      Data for transfer
     *
     */
    public function put_chunk($data, $backupid, $chunknumber, $sessionkey, $retries) {

        // Grab the original data so we don't have to decode it to check the hash.
        $originaldata = $data['original_data'];
        unset($data['original_data']);
        $response = $this->send('chunks/' . $backupid . '/' . $chunknumber, $data, 'PUT', $sessionkey, $retries);

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
     * Update chunk status to confirmed
     *
     * NOTE: This function is not currently in use due to architectural changes
     *       and will likely be deprecated.
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     * @param int    $chunk     Chunk number
     *
     */
    public function put_chunk_confirm($auth, $backupid, $chunk) {
        return $this->send('chunks/' . $backupid . '/' . $chunk, array(), 'PUT', $auth);
    }
    /**
     * Remove chunk
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     * @param int    $chunk     Chunk number
     *
     */
    public function delete_chunk($auth, $backupid, $chunk) {
        return $this->send('chunks/' . $backupid . '/' . $chunk, array(), 'DELETE', $auth);
    }
    /**
     * Get list of backup files available for download from course bank instance.
     *
     * One of the parameters is an Associative array $params
     * A simple example:
     *
            Array
            (
                [coursefullname] => Array
                    (
                        [0] => Array
                            (
                                [operator] => LIKE
                                [value] => test
                            )

                    )

                [filetimemodified] => Array
                    (
                        [0] => Array
                            (
                                [operator] => >=
                                [value] => 1428415200
                            )

                    )

            )
     *
     * The firs level keys contain field names (e.g coursefullname, backupfilename, filesize, filetimemodified, status)
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
        return $this->send('downloads', array(), 'GET', $sesskey);
    }
    /**
     * Get count of backup files available from course bank instance.
     */
    public function get_downloadcount($sesskey, array $params = null) {
        return $this->send('downloadcount', array(), 'GET', $sesskey);
    }
}
/**
 * HTTP response class, used to pass around request responses
 *
 */
class coursestore_http_response {
     public $body;
     public $info;
     public $httpcode;
     public $request;
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

    }
    /**
     * Method to log the http response as a Moodle event. This is intended
     * for scenarios where an unexpected http response is encountered, and
     * data about this response and the initial request may be helpful for
     * debugging.
     *
     * @param int $courseid
     * @param int $coursestoreid
     */
    public function log_http_error($courseid, $coursestoreid) {
        if(tool_coursestore::legacy_logging()) {
            return $this->log_http_error_legacy($courseid, $coursestoreid);
        }
        $info = $this->info;
        $body = (array)$this->body;
        $request = $this->request;
        $request[CURLOPT_POSTFIELDS] = (array) json_decode($request[CURLOPT_POSTFIELDS]);

        // Don't include data in event unless loghttpdata is set.
        if (!get_config('tool_coursestore', 'loghttpdata')) {
            unset($request[CURLOPT_POSTFIELDS]['data']);
            unset($body->data);
        }

        $otherdata = array(
            'courseid' => $courseid,
            'coursestoreid' => $coursestoreid,
            'body' => $body,
            'info' => $info,
            'httpcode' => $this->httpcode,
            'request' => $request
            );
        $eventdata = array(
            'other' => $otherdata,
            'context' => context_system::instance()
        );
        $event = \tool_coursestore\event\transfer_interrupted::create($eventdata);
        $event->trigger();
        return true;
    }
    private function log_http_error_legacy($courseid, $coursestoreid) {
        global $USER;

        $info = "Transfer of backup with course store id $coursestoreid " .
                "interrupted: URL: " . $this->request[CURLOPT_URL] .
                "METHOD: " . $this->request[CURLOPT_CUSTOMREQUEST] . " ";

        if (!isset($this->httpcode)) {
            $info .= "No http response received";
        } else {
            $info .= "HTTP RESPONSE: " . $this->httpcode . " ";
        }

        if(isset($this->body->error_desc)) {
            $info .= "ERROR: " . $this->body->error_desc;
        }

        add_to_log(SITEID, 'Course store', 'Transfer error', '', $info, 0, $USER->id);
        return true;
    }
}
