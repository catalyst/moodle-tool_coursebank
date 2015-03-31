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

    // status
    const STATUS_NOTSTARTED = 0;
    const STATUS_INPROGRESS = 1;
    const STATUS_FINISHED = 2;
    const STATUS_ERROR = 99;
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

        if ($auth) {
            $checkresult = $wsman->send_test($auth);
            if ($checkresult['response']['http_code'] == coursestore_ws_manager::WS_STATUS_SUCCESS_UPDATED) {
                return true;
            }
        }

        // No sess key provided, or sesskey rejected. Try starting a new session
        $token = get_config('tool_coursestore', 'authtoken');
        if ($token) {
            if(!$wsman->start_session($token)) {
                return false;
            }
            $sesskey = tool_coursestore::get_session();
            $checkresult = $wsman->send_test($sesskey);

            if ($checkresult['response']['http_code'] == coursestore_ws_manager::WS_STATUS_SUCCESS_UPDATED) {
                return true;
            }
        }
        return false;
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

        $check = str_pad('', $testsize*1000, '0');
        $start = microtime(true);

        // Make $count requests with the dummy data
        for($i=0; $i<$count; $i++) {
            for($j=0; $j<=$retry; $j++) {
                $response = $wsman->send_test($auth, $check);
                if($response['response']['http_code'] == coursestore_ws_manager::WS_STATUS_SUCCESS_UPDATED) {
                    break;
                }
            }
            // If $maxhttps unsuccessful attempts have been made
            if($response['response']['http_code'] != coursestore_ws_manager::WS_STATUS_SUCCESS_UPDATED) {
                return 0;
            }
        }
        $elapsed = microtime(true) - $start;

        // Convert 'total kB transferred'/'total time' into kb/s
        return round(($testsize*$count*8)/$elapsed, 2);
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
    public static function get_summary_data() {
        global $CFG, $DB;

        $sql = "SELECT tcs.id, c.shortname, f.timemodified, f.filename,
                       f.filesize, tcs.status
                  FROM {tool_coursestore} tcs
            INNER JOIN {files} f ON (tcs.fileid = f.id)
            INNER JOIN {context} cx
                       ON (f.contextid = cx.id)
                       AND (cx.contextlevel = :contextcourse)
            INNER JOIN {course} c ON (cx.instanceid = c.id)";
        $params = array('contextcourse' => CONTEXT_COURSE);
        $results = $DB->get_records_sql($sql, $params);
        $notstarted   = get_string('statusnotstarted', 'tool_coursestore');
        $inprogress   = get_string('statusinprogress', 'tool_coursestore');
        $statuserror  = get_string('statuserror', 'tool_coursestore');
        $finished     = get_string('statusfinished', 'tool_coursestore');

        $statusmap = array(
            tool_coursestore::STATUS_NOTSTARTED => $notstarted,
            tool_coursestore::STATUS_INPROGRESS => $inprogress,
            tool_coursestore::STATUS_FINISHED => $finished,
            tool_coursestore::STATUS_ERROR => $statuserror
        );

        foreach($results as $result) {
            if(isset($statusmap[$result->status])) {
                $result->status = $statusmap[$result->status];
            }
        }

        return $results;
    }
    /**
     * Convenience function to handle sending a file along with the relevant
     * metatadata.
     *
     * @param object $backup    Course store database record object
     *
     */
    public static function send_backup($backup) {
        global $CFG, $DB;

        // Copy the backup file into our storage area so there are no changes to the file
        // during transfer, unless file already exists.
        if ($backup->isbackedup == 0) {
            $backup = self::copy_backup($backup);
        }

        if ($backup === false) {
            return false;
        }

        // Get required config variables
        $urltarget = get_config('tool_coursestore', 'url');
        $timeout = get_config('tool_coursestore', 'timeout');
        $retries = get_config('tool_coursestore', 'requestretries');
        $token = get_config('tool_coursestore', 'authtoken');
        $sessionkey = self::get_session();

        // Initialise, check connection
        $ws_manager = new coursestore_ws_manager($urltarget, $timeout);
        if (!self::check_connection($ws_manager, $sessionkey)) {
            $backup->status = self::STATUS_ERROR;
            $DB->update_record('tool_coursestore', $backup);
            $ws_manager-close();
            return false;
        }
        // Update again in case a new session key was given.
        $sessionkey = self::get_session();

        // Chunk size is set in kilobytes
        $chunksize = $backup->chunksize * 1000;

        // Open input file
        $coursestore_filepath = self::get_coursestore_filepath($backup);
        $file = fopen($coursestore_filepath, 'rb');

        // Set offset based on chunk number
        if ($backup->chunknumber != 0) {
            fseek($file, $backup->chunknumber * $chunksize);
        }
        else if ($backup->chunknumber == 0) {
            // Initial the backup record.
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
            if (!$ws_manager->create_backup($data, $sessionkey, $retries)) {
                $backup->status = self::STATUS_ERROR;
                $DB->update_record('tool_coursestore', $backup);
                $ws_manager->close();
                echo("create backup failed; chunknumber=" . $backup->chunknumber . ".\n");
                return false;
            }
            $backup->status = self::STATUS_INPROGRESS;
            $DB->update_record('tool_coursestore', $backup);
        }

        // Read the file in chunks, attempt to send them
        while ($contents = fread($file, $chunksize)) {

            // TODO: change the chunksize to the actual size being sent. The last chunk may not be the full size.
            $data = array(
                'data'          => base64_encode($contents),
                'chunksize'     => $chunksize,
                'original_data' => $contents,
            );
            if ($ws_manager->transfer_chunk($data, $backup->id, $backup->chunknumber, $sessionkey, $retries)) {
                $backup->timechunkcompleted = time();
                $backup->chunknumber++;
                if ($backup->status == self::STATUS_ERROR) {
                    $backup->chunkretries = 0;
                    $backup->status = self::STATUS_INPROGRESS;
                }
                if($backup->chunknumber == $backup->totalchunks) {
                    $backup->status = self::STATUS_FINISHED;
                }
                $DB->update_record('tool_coursestore', $backup);
            }
            else {
                if ($backup->status == self::STATUS_ERROR) {
                    $backup->chunkretries++;
                }
                else {
                    $backup->status = self::STATUS_ERROR;
                }
                $DB->update_record('tool_coursestore', $backup);
                return false;
            }
        }

        $ws_manager->close();
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

        // Construct the full path of the backup file
        $moodle_filepath = $CFG->dataroot . '/filedir/' .
                substr($backup->contenthash, 0, 2) . '/' .
                substr($backup->contenthash, 2,2) .'/' . $backup->contenthash;

        if (!is_readable($moodle_filepath)) {
            return false;
        }
        if (!is_writable(self::get_coursestore_data_dir())) {
            throw new invalid_dataroot_permissions();
        }

        $coursestore_filepath = self::get_coursestore_filepath($backup);
        copy($moodle_filepath, $coursestore_filepath);

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

        $coursestore_filepath = self::get_coursestore_filepath($backup);

        if (!is_readable($coursestore_filepath)) {
            return false;
        }
        if (!is_writable(self::get_coursestore_data_dir())) {
            return false;
        }

        unlink($coursestore_filepath);

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
     * - The first
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
        $params = array('statusnotstarted' => tool_coursestore::STATUS_NOTSTARTED,
                        'statuserror' => tool_coursestore::STATUS_ERROR,
                        'statusinprogress' => tool_coursestore::STATUS_INPROGRESS,
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
                $cs->chunksize = tool_coursestore::get_config_chunk_size();
                $cs->totalchunks = tool_coursestore::calculate_total_chunks($cs->chunksize, $coursebackup->filesize);
                $cs->chunknumber = 0;
                $cs->status = tool_coursestore::STATUS_NOTSTARTED;
                $cs->isbackedup = 0; // No copy has been created yet.
                foreach($insertfields as $field) {
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
            $result = tool_coursestore::send_backup($coursebackup);
            if ($result) {
                $delete = tool_coursestore::delete_backup($coursebackup);
                if (!$delete) {
                    $delfail = get_string(
                            'deletefailed',
                            'tool_coursestore',
                            $coursebackup->filename
                    );
                    mtrace($delfail . "\n");
                }
            } else {
                $bufail = get_string(
                        'backupfailed',
                        'tool_coursestore',
                        $coursebackup->filename
                );
                mtrace($bufail . "\n");
                // Stop sending backups until this one is resolved.
                break;
            }
        }
        $rs->close();
    }
}

/**
 * Class to keep errors specific to this plugin.
 *
 */
abstract class tool_coursestore_error {
    // errors
    const ERROR_TIMEOUT              = 100;
    const ERROR_MAX_ATTEMPTS_REACHED = 101;
    // etc. populate as needed.
}

/**
 * Class that handles outgoing web service requests.
 *
 */
class coursestore_ws_manager {
    private $curlhandle;
    private $baseurl;

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

    /**
     * @param string  $url            Target URL
     * @param int     $timeout        Request time out (seconds)
     */
    function __construct($url, $timeout) {
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
    function close() {
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
     * @param string  $auth         Authorization string
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
            $header[] = 'sesskey: ' . $auth;
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
            $response = curl_getinfo($this->curlhandle);
            if ($result) {
                $body = json_decode($result);
                return array('body' => $body, 'response' => $response);
            }
        }
        return false;
    }
    /**
     * Send a test request
     *
     * @param string  $auth         Authorization string
     * @param string  $data         Test data string
     * @return array or bool false  Associate array response
     */
    function send_test($auth, $data='') {
        $testdata = array('data' => $data);
        $result = $this->send('test', $testdata, 'GET', $auth);

        return $result;
    }
    /**
     * Send a session start request.
     *
     * @param string    $hash      Authorization string
     */
    function start_session($hash) {
        $authdata = array(
            'hash' => $hash,
        );
        $response = $this->send('session', $authdata, 'POST');
        if ($response !== false && $response['response']['http_code'] == coursestore_ws_manager::WS_STATUS_SUCCESS_CREATED) {
            $body = $response['body'];
            if (isset($body->sesskey)) {
                $sesskey = trim((string) $body->sesskey);
                return tool_coursestore::set_session($sesskey);
            }
        }
        // TODO: Log error
        return false;
    }
    /**
     * Get a backup resource.
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     */
    function get_backup($auth, $backupid) {
        return $this->send('backup'. $backupid, array(), 'GET', $auth);

    }
    /**
     * Create a backup resource.
     *
     * @param string $auth      Authorization string
     *
     */
     function create_backup($data, $sessionkey, $retries) {

         $result = $this->send('backup', $data, 'POST', $sessionkey, $retries);
         if ($result === false) {
             //echo("just false.\n");
             return false;
         }

         $http_code = $result['response']['http_code'];
         $body = $result['body'];
         if ($http_code == self::WS_STATUS_SUCCESS_CREATED) {
             // Make sure the hash is good.
             $return_hash = $body->hash;
             $validate_hash = md5($data['fileid'] . ',' . $data['filename'] . ',' . $data['filesize']);
             if ($return_hash != $validate_hash) {
                 //echo("hash doesn't match\n");
                 return false;
             }
             else {
                 return true;
             }
         }
         if (isset($body->error) && $body->error == self::WS_STATUS_ERROR_BACKUP_ALREADY_EXISTS) {
             // the backup already exists, continue.
             return true;
         }
         //echo("something else: " . print_r($body, true) . "\n");
         return false;
     }

    /**
     * Update a backup resource.
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     *
     */
     function update_backup($auth, $backupid) {
         return $this->send('backup/' . $backupid, array(), 'PUT', $auth);
     }
    /**
     * Get most recent chunk transferred for specific backup.
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     *
     */
     function get_chunk($auth, $backupid) {
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
     function transfer_chunk($data, $backupid, $chunk_number, $sessionkey, $retries) {

         // Grab the original data so we don't have to decode it to check the hash.
         $original_data = $data['original_data'];
         unset($data['original_data']);
         $result = $this->send('chunks/' . $backupid . '/' . $chunk_number, $data, 'PUT', $sessionkey, $retries);
         if ($result === false) {
             // echo("transfer_chunk: returned false.\n");
             return false;
         }

         $http_code = $result['response']['http_code'];
         $body = $result['body'];
         if ($http_code == self::WS_STATUS_SUCCESS_UPDATED) {
             // Make sure the hash is good.
             $return_hash = $body->chunkhash;
             $validate_hash = md5($original_data);
             if ($return_hash != $validate_hash) {
                // echo("transfer_chunk: hash doesn't match. http_code= $http_code; return_hash=$return_hash; validate_hash=$validate_hash; body=" . print_r($body, true) . ".\n");
                 return false;
             }
             else {
                 return true;
             }
         }
         // echo("transfer_chunk: something else http_code= $http_code; sessionkey=$sessionkey; body=" . print_r($body, true) . ".\n");
         return false;
     }

    /**
     * Update chunk status to confirmed
     *
     * @param string $auth      Authorization string
     * @param int    $backupid  ID referencing course bank backup resource
     * @param int    $chunk     Chunk number
     *
     */
     function confirm_chunk($auth, $backupid, $chunk) {
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
     function remove_chunk($auth, $backupid, $chunk) {
         return $this->send('chunks/' . $backupid . '/' . $chunk, array(), 'DELETE', $auth);
     }
}
