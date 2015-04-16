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
    const STATUS_ONHOLD = 3;
    const STATUS_CANCELLED = 4;
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
        $onhold       = get_string('statusonhold', 'tool_coursestore');
        $cancelled    = get_string('statuscancelled', 'tool_coursestore');

        $statuses = array(
            self::STATUS_NOTSTARTED => $notstarted,
            self::STATUS_INPROGRESS => $inprogress,
            self::STATUS_FINISHED   => $finished,
            self::STATUS_ONHOLD     => $onhold,
            self::STATUS_CANCELLED  => $cancelled,
            self::STATUS_ERROR      => $statuserror,
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
     * @param  coursestore_ws_manager $wsman  Web service manager object.
     * @param  string               $sesskey  Course bank session key.
     *
     * @return bool                           True for success, false otherwise.
     */
    public static function check_connection(coursestore_ws_manager $wsman, $sesskey=false) {
        global $USER;

        $result = $wsman->get_test($sesskey);
        if (isset($result->httpcode)) {
            if ($result->httpcode == coursestore_ws_manager::WS_HTTP_OK) {
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
     * @param string                  $sesskey  Session key
     *
     * @return int                  Approximate connection speed in kbps
     */
    public static function check_connection_speed(coursestore_ws_manager $wsman,
            $testsize, $count, $retry, $sesskey) {
        global $USER;

        $check = str_pad('', $testsize * 1000, '0');
        $starttime = microtime(true);

        // Make $count requests with the dummy data.
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j <= $retry; $j++) {
                $response = $wsman->get_test(
                        $sesskey, $check, $count, $testsize, $starttime, $endtime);
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
            $speed = self::calculate_speed($count, $testsize, $starttime, $endtime);
        }

        return $speed;

    }
    /**
     * Calculate rough transfer speed based on request count, transfer size,
     * start time and end time.
     *
     * @param    int    $count      Number of requests sent.
     * @param    int    $testsize   Size of transfer data in bytes.
     * @param    float  $starttime  Epoch time of test start (microtime).
     * @param    float  $endtime    Epoch time of test completion (microtime).
     */
    public static function calculate_speed($count, $testsize, $starttime, $endtime) {
        $elapsed = $endtime - $starttime;

        // Convert 'total kB transferred'/'total time' into kb/s.
        $speed = round(($testsize * $count * 8 ) / $elapsed, 2);
        return $speed;
    }
    /**
     * Fetch configured chunk size in kB from Moodle config.
     *
     * @return int $chunksize
     */
    public static function get_config_chunk_size() {
        return get_config('tool_coursestore', 'chunksize');
    }
    /**
     * Calculate total number of chunks, given individual chunks size in kB and
     * overall filesize in bytes.
     *
     * @param int $chunksize  Individual chunk size in kilobytes.
     * @param int $filesize   Total file size in bytes.
     */
    public static function calculate_total_chunks($chunksize, $filesize) {
        return ceil($filesize / ($chunksize * 1000));
    }
    /**
     * Function to fetch course store records for display on the summary
     * page. Returns an array of the form:
     *
     *              array('results' => array(...),
     *                    'count '  => int $count)
     *
     * @param string $sort         Sort field.
     * @param string $dir          Direction of sort.
     * @param string $extraselect  Additional select SQL.
     * @param array  $extraparams  Additional parameters.
     * @param array $fieldstosort  Potential sort fields.
     *
     * @return array
     */
    public static function get_summary_data($sort='status', $dir='ASC', $extraselect='',
            array $extraparams=null, $page=0, $recordsperpage=0,
            $fieldstosort=array('coursefullname', 'filetimemodified', 'backupfilename', 'filesize', 'status')) {

        global $DB;

        if (is_array($fieldstosort) and in_array($sort, $fieldstosort)) {
            $sort = "$sort $dir";
        } else {
            $sort = '';
        }

        $results = $DB->get_records_select('tool_coursestore', $extraselect, $extraparams, $sort, '*', $page, $recordsperpage);
        $count = $DB->count_records_select('tool_coursestore', $extraselect, $extraparams);

        return array('results' => $results, 'count' => $count);
    }
    /**
     * 
     * @param coursestore_ws_manager $wsmanager
     * @param object $backup         Course store database record object
     * @param array  $data           Data array to post/put.
     * @param string $sessionkey     Session token
     * @param int    $retries        Number of retries to attempt sending
     *                               when an error occurs.
     * @return array of: int     result null = needs further investigation.
     *                                    -1 = some error, don't continue.
     *                                     1 = all good but don't continue 
     *                                         -> i.e. Course Bank already has the backup.
     *                   boolean deletechunks - whether or not chunks should be deleted.
     *                   int     highestiterator - highest iterator Cours Bank has received for this backup.
     *                   coursestore_http_response putresponse - the response from the put request.
     *
     */
    private static function update_backup(coursestore_ws_manager $wsmanager, $data, $backup, $sessionkey, $retries) {
        global $DB;

        $result = null;
        $deletechunks = false;
        $highestiterator = 0;
        $putresponse = $wsmanager->put_backup($sessionkey, $data, $backup->uniqueid, $retries);
        if ($putresponse !== true) {
            if ($putresponse->httpcode == coursestore_ws_manager::WS_HTTP_BAD_REQUEST) {
                if (isset($putresponse->body->chunksreceived)) {
                    // We've sent chunks to Course Bank already?
                    if ($putresponse->body->chunksreceived == 0) {
                        // Possible network error. Should retry again later.
                        $backup->status = self::STATUS_ERROR;
                        $DB->update_record('tool_coursestore', $backup);
                        // Log a transfer interruption event.
                        $putresponse->log_http_error($backup->courseid, $backup->id);
                        return array('result'          => -1,
                                     'deletechunks'    => $deletechunks,
                                     'highestiterator' => $highestiterator,
                                     'putresponse'     => $putresponse);
                    }
                    // Yes, we need to delete the chunks.
                    $deletechunks = true;
                    $highestiterator = $putresponse->body->highestchunkiterator;
                }
                else if (isset($putresponse->body->is_completed)) {
                    // We've sent chunks to Course Bank already?
                    if ($putresponse->body->is_completed) {
                        // Can skip this one as it's already been sent to Course Bank and is complete.
                        // Another process (?!?) may have completed this in the meantime.
                        $backup->status = self::STATUS_FINISHED;
                        $backup->timecompleted = time();
                        $DB->update_record('tool_coursestore', $backup);
                        // Don't let the send_backup() continue.
                        return array('result'          => 1,
                                     'deletechunks'    => $deletechunks,
                                     'highestiterator' => $highestiterator,
                                     'putresponse'     => $putresponse);
                    }
                    // Otherwise, something else is wrong. Try again later.
                    // Actually, if is_completed is set, it will always be true
                    // and we shouldn't be here at this point.
                }
            }
        }
        return array('result'          => null,
                     'deletechunks'    => $deletechunks,
                     'highestiterator' => $highestiterator,
                     'putresponse'     => $putresponse);
    }
    /**
     * Send the initial post_backup.
     * @param object $wsmanager Webservice class manager.
     * @param object $backup    Course store database record object
     *
     * @return int  0 = all good, continue.
     *             -1 = some error, don't continue.
     *              1 = all good but don't continue -> i.e. Course Bank already has the backup.
     */
    private static function initialise_backup(coursestore_ws_manager $wsmanager, $backup, $sessionkey, $retries) {
        global $DB;

        $coursedate = '';
        if ($backup->coursestartdate > 0) {
            $dt = new DateTime("@" . $backup->coursestartdate);
            $coursedate = $dt->format('Y-m-d H:i:s');
        }
        $data = array(
            'uuid'         => $backup->uniqueid,
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
        if (!isset($backup->timetransferstarted) || $backup->timetransferstarted == 0) {
            $backup->timetransferstarted = time();
        }
        $postresponse = $wsmanager->post_backup($data, $sessionkey, $retries);
        // Unexpected http response or none received.
        if (!is_int($postresponse)) {
            $deletechunks = false;
            $highestiterator = 0;
            $putresponse = null;
            if ($postresponse->httpcode == coursestore_ws_manager::WS_HTTP_CONFLICT) {
                // Course Bank already has some data for this backup.
                if ($postresponse->body->is_completed) {
                    // Can skip this one as it's already been sent to Course Bank and is complete.
                    // Data may not match due to chunk size settings changing.
                    $backup->status = self::STATUS_FINISHED;
                    $backup->timecompleted = time();
                    $DB->update_record('tool_coursestore', $backup);
                    // Don't let the send_backup() continue.
                    return 1;
                } else if ($postresponse->body->chunksreceived == 0) {
                    // Course Bank has some other data for this backup.
                    // But no chunks have been sent yet.
                    // Try to update it.
                    // Don't unset the fileid or the uuid fields.
                    list($result, $deletechunks, $highestiterator, $putresponse) =
                        self::update_backup($wsmanager, $data, $backup, $sessionkey, $retries);
                    if (!is_null($result)) {
                        return $result;
                    }
                } else {
                    // post_backup informs us that there's already data for this backup
                    // in Course Bank.  And, that it's already started receiving
                    // chunks.
                    // We need to delete the chunks, then update the backup, then continue.
                    if (isset($postresponse->body->chunksreceived)) {
                        $deletechunks = true;
                        $highestiterator = $postresponse->body->highestchunkiterator;
                    }
                }
            }
            if ($deletechunks) {
                // Delete chunks up to the highest iterator sent so far.
                for ($iterator = 0; $iterator < $highestiterator; $iterator++) {
                    $deleteresponse = $wsmanager->delete_chunk($sessionkey, $backup->uniqueid, $iterator, $retries);
                    if ($deleteresponse->httpcode != coursestore_ws_manager::WS_HTTP_OK) {
                        // something is wrong. Try again later.
                        $backup->status = self::STATUS_ERROR;
                        $DB->update_record('tool_coursestore', $backup);
                        // Log a transfer interruption event.
                        $deleteresponse->log_http_error($backup->courseid, $backup->id);
                        return -1;
                    }
                }
                list($result, $deletechunks, $highestiterator, $putresponse) =
                    self::update_backup($wsmanager, $data, $backup, $sessionkey, $retries);
                if (!is_null($result)) {
                    return $result;
                }
                if ($deletechunks || $highestiterator > 0) {
                    // Something is wrong. Try again later.
                    $backup->status = self::STATUS_ERROR;
                    $DB->update_record('tool_coursestore', $backup);
                    // Log a transfer interruption event.
                    $putresponse->log_http_error($backup->courseid, $backup->id);
                    return -1;
                }
                // continue.
            } else {
                $backup->status = self::STATUS_ERROR;
                $DB->update_record('tool_coursestore', $backup);
                // Log a transfer interruption event.
                $postresponse->log_http_error($backup->courseid, $backup->id);
                return -1;
            }
        }
        $backup->status = self::STATUS_INPROGRESS;
        $DB->update_record('tool_coursestore', $backup);
        return 0;
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

        // Log transfer_resumed event.
        if ($backup->chunknumber > 0) {
            // Don't need to log the start. It will be logged in the post_backup call.
            coursestore_logging::log_transfer_resumed($backup, null, 'course');
        }

        // Set offset based on chunk number.
        if ($backup->chunknumber != 0) {
            fseek($file, $backup->chunknumber * $chunksize);
        } else if ($backup->chunknumber == 0) {
            // Initialise the backup record.
            $result = self::initialise_backup($wsmanager, $backup, $sessionkey, $retries);
            switch ($result) {
                case 0:
                    // Continue on.
                    break;
                case 1:
                    $wsmanager->close();
                    return true;
                    break;
                case -1:
                    $wsmanager->close();
                    return false;
                    break;
            }
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
                    $backup->uniqueid,
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
            // All chunks have been sent. Need to flag this backup is complete.
            $data = array(
                'fileid' => $backup->id,
                'filename' => $backup->backupfilename,
                'filehash' => $backup->contenthash,
                'filesize' => $backup->filesize,
                'chunksize' => $backup->chunksize,
                'totalchunks' => $backup->totalchunks
            );
            // Confirm the backup file as complete.
            $completion = $wsmanager->put_backup_complete($sessionkey, $data, $backup);
            if ($completion->httpcode != coursestore_ws_manager::WS_HTTP_OK) {
                $backup->status = self::STATUS_ERROR;
                // Start from the beginning next time.
                $backup->chunknumber = 0;
                $backup->timechunkcompleted = 0;
                $DB->update_record('tool_coursestore', $backup);

                // Log a transfer interruption event.
                $completion->log_http_error($backup->courseid, $backup->id);
                return false;
            }
            $backup->status = self::STATUS_FINISHED;
            $backup->timecompleted = time();
            $DB->update_record('tool_coursestore', $backup);
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
     * Algorithm from stackoverflow: http://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
     * Generates 128 bits of random data.
     * Must have openSSL extension.
     *
     * @return string guid v4 string.
     */
    public static function generate_uuid() {
        $data = openssl_random_pseudo_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100.
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10.

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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
                       tcs.uniqueid,
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
                       tcs.uniqueid,
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
                $cs->uniqueid = self::generate_uuid();
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
                $coursebackup->uniqueid = $cs->uniqueid;
                $coursebackup->backupfilename = $cs->backupfilename;
                $coursebackup->fileid = $cs->fileid;
                $coursebackup->chunksize = $cs->chunksize;
                $coursebackup->totalchunks = $cs->totalchunks;
                $coursebackup->chunknumber = $cs->chunknumber;
                $coursebackup->timecreated = time();
                $coursebackup->timecompleted = 0;
                $coursebackup->timechunksent = 0;
                $coursebackup->timechunkcompleted = 0;
                $coursebackup->timetransferstarted = 0;
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
        }
        $rs->close();

        // Get all backups that are pending transfer, attempt to transfer them.
        $sql = 'SELECT * FROM {tool_coursestore}
                        WHERE status IN (:notstarted, :inprogress, :error)';
        $sqlparams = array(
            'notstarted' => self::STATUS_NOTSTARTED,
            'inprogress' => self::STATUS_INPROGRESS,
            'error'      => self::STATUS_ERROR
        );
        $transferrs = $DB->get_recordset_sql($sql, $sqlparams);

        foreach ($transferrs as $coursebackup) {
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
        $transferrs->close();

    }
    /**
     * Test the Moodle version number and return true if the Moodle version is
     * older than Moodle 2.7. If this is the case, we will need to use the
     * legacy "add_to_log" function to log events.
     *
     * @return bool  Whether or not legacy logging will be necessary.
     */
    public static function legacy_logging() {
        global $CFG;
        if ((float) $CFG->version < 2014051200) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * Updates status for coursebackup record
     *
     * @param object $coursebackup  An object with contents equal to fieldname=>fieldvalue.
     * @param int $status A new status
     * @return boolean
     */
    public static function user_update_status($coursebackup, $status) {
        global $DB;

        $statuses = self::get_statuses();
        $noactionstatuses = self::get_noaction_statuses();
        // Check if a new status exists.
        if (!array_key_exists($status, $statuses)) {
            coursestore_logging::log_status_update(
                    "Failed updating: status code \"$status\" does not exist."
            );
            return false;
        }
        // Check if record exists.
        if (!$DB->record_exists('tool_coursestore', array('id' => $coursebackup->id))) {
            coursestore_logging::log_status_update(
                    "Failed updating: course store record with ID: " .
                    "\"$coursebackup->id\" does not exist"
            );
            return false;
        }
        // If status is a "no action" status we can't update it.
        // This prevents us from changing the status mid-way through a transfer.
        if (in_array($coursebackup->status, $noactionstatuses)) {
            coursestore_logging::log_status_update(
                    "Failed updating: current status is " .
                    "$coursebackup->status for course store backup with " .
                    "ID $coursebackup->id. This status is in the " .
                    "\"no action\" list."
            );
            return false;
        }
        // Finally update.
        $oldstatus = $coursebackup->status;
        $coursebackup->status = $status;
        $DB->update_record('tool_coursestore', $coursebackup);
        coursestore_logging::log_status_update(
                "Updating status: successfully " .
                "updated status from $oldstatus to $status for backup with" .
                " ID $coursebackup->id."
        );

        return true;
    }
    /**
     * Returns a list of unchangeable statuses
     *
     * @return array
     */
    public static function get_noaction_statuses() {
        $noaction = array(
            self::STATUS_INPROGRESS,
            self::STATUS_FINISHED,
            self::STATUS_CANCELLED,
        );
        return $noaction;
    }
    /**
     * Returns a list of statuses wich may be stopped
     *
     * @return array
     */
    public static function get_canstop_statuses() {
        $canstop = array(
            self::STATUS_NOTSTARTED,
            self::STATUS_ERROR,
        );
        return $canstop;
    }
    /**
     * Returns a list of statuses wich are stopped but can be changed
     *
     * @return array
     */
    public static function get_stopped_statuses() {
        $stopped = array(
            self::STATUS_ONHOLD,
        );
        return $stopped;
    }
    /**
     * Returns a list of existing actions
     *
     * @return type
     */
    public static function get_actions() {
        $actions = array(
            'delete' => self::STATUS_CANCELLED,
            'stop'   => self::STATUS_ONHOLD,
            'go'     => self::STATUS_NOTSTARTED,
        );
        return $actions;
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

    /**
     * Checks if response body contains errors.
     *
     * @param type $httpresponse
     * @return boolean
     */
    public static function is_response_error($httpresponse) {
        if (isset($httpresponse->body->error) and isset($httpresponse->body->error_desc)) {
            return true;
        }

        return false;
    }
    /**
     * Checks if the response is valid and file mcan be downloaded.
     *
     * @param type $httpresponse
     * @return boolean
     */
    public static function is_response_backup_download_error($httpresponse) {

        if (self::is_response_error($httpresponse)) {
            return true;
        }
        if (!isset($httpresponse->body->url)) {
            return true;
        }
        if (!tool_coursestore_check_url($httpresponse->body->url)) {
            return true;
        }
        if (!tool_coursestore_is_url_available($httpresponse->body->url)) {
            return true;
        }

        return false;
    }
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
     *
     * @return array or bool false  Associative array of the form:
     *                                  'body' => <response body array>,
     *                                  'response => <response info array>
     *                              Or false if connection could not be made
     */
    protected function send_authenticated($resource='', $data=array(), $method='POST', $auth=null, $retries = 5) {
        // Don't try sending unless we already have a session key.
        $result = false;
        if ($auth) {
            $result = $this->send($resource, $data, $method, $auth, $retries);
        }
        if (!$result || $result->httpcode == self::WS_HTTP_UNAUTHORIZED) {
            $token = get_config('tool_coursestore', 'authtoken');
            if ($this->post_session($token) === true) {
                $sesskey = tool_coursestore::get_session();
                return $this->send($resource, $data, $method, $sesskey, $retries);
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
        $authdata = array(
            'hash' => $hash,
        );
        $response = $this->send('session', $authdata, 'POST');
        coursestore_logging::log_post_session($response);

        if ($response->httpcode == self::WS_HTTP_CREATED) {
            $tagsesskey = self::WS_AUTH_SESSION_KEY;
            if (isset($response->body->$tagsesskey)) {
                $sesskey = trim((string) $response->body->$tagsesskey);
                return tool_coursestore::set_session($sesskey);
            }
        } else {
            // Unexpected response.
            return $response;
        }
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
        if ($data == '') {
            coursestore_logging::log_check_connection($result);
        } else {
            // It's the speed test.
            $endtime = microtime(true);
            $speed = tool_coursestore::calculate_speed($count, $testsize, $starttime, $endtime);
            coursestore_logging::log_check_connection_speed($result, $speed);
        }
        return $result;
    }
    /**
     * Get a backup resource.
     *
     * @param string $auth      Authorization string.
     * @param string $uniqueid  UUID referencing course bank backup resource.
     * @param bool   $download  Whether or not to generate a download link.
     */
    public function get_backup($sesskey, $uniqueid, $download=false) {
        $headers = array(self::WS_AUTH_SESSION_KEY => $sesskey);
        if ($download) {
            $headers['download'] = 'true';
        }

        $result = $this->send_authenticated('backup/'. $uniqueid, array(), 'GET', $headers);
        coursestore_logging::log_get_backup($result);
        return $result;
    }
    public static function get_backup_validated_hash($data) {
        return md5($data['fileid'] . ',' .$data['uuid'] . ',' . $data['filename'] . ',' . $data['filesize']);
    }
    public static function check_post_backup_data_is_same(coursestore_http_response $httpresponse, $data) {

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
                $info = "Local value for $datafield does not match coursebank value.";
                $httpresponse->log_http_error(
                        $data['courseid'],
                        $data['fileid'],
                        $info
                );
                return false;
            }
        }

        $dtresonse = new DateTime($httpresponse->body->coursestartdate);
        $dtdata = new DateTime($data['startdate']);
        $responsedate = $dtresonse->format('Y-m-d H:i:s');
        $datadate = $dtdata->format('Y-m-d H:i:s');
        if ($responsedate != $datadate) {
            $info = "startdate: response=" . $httpresponse->body->coursestartdate .
                    "; data=" . $data['startdate'];
            $httpresponse->log_http_error(
                    $data['courseid'],
                    $data['fileid'],
                    $info
            );
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
    public function post_backup($data, $sessionkey, $retries=5) {

        $response = $this->send_authenticated('backup', $data, 'POST', $sessionkey, $retries);
        coursestore_logging::log_transfer_started($data, $response, 'course');

        if ($response->httpcode == self::WS_HTTP_CREATED) {
            // Make sure the hash is good.
            $returnhash = $response->body->hash;
            $validatehash = self::get_backup_validated_hash($data);
            if ($returnhash != $validatehash) {
                return $response;
            } else {
                // Possible network error. Should retry again later.
                return (int) $data['fileid'];
            }
        } else if ($response->httpcode == self::WS_HTTP_CONFLICT) {
            // The backup already exists.
            // Check the data coming back is the same as what we sent.
            if (!self::check_post_backup_data_is_same($response, $data)) {
                // Need to deal with this.
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
     * @param string $uniqueid        UUID of coursestore record.
     * @param int    $retries         Number of retries to attempt sending
     *                                when an error occurs.
     *
     */
    public function put_backup($sessionkey, $data, $uniqueid, $retries=5) {

        //debugging(__FUNCTION__ . ": data=" . print_r($data, true), DEBUG_DEVELOPER);
        $response = $this->send_authenticated('backup/' . $uniqueid, $data, 'PUT', $sessionkey);
        coursestore_logging::log_backup_updated($data, $response);

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
     * @param obj    $backup  tool_coursestore record object, including uniqueid.
     * @param int    $retries Number of retry attempts to make.
     *
     */
    public function put_backup_complete($sessionkey, $data, $backup, $retries=5) {
        // Log transfer_completed event.
        coursestore_logging::log_transfer_completed($backup);
        $uniqueid = $backup->uniqueid;
        return $this->send_authenticated('backupcomplete/' . $uniqueid, $data, 'PUT', $sessionkey);
    }
    /**
     * Get most recent chunk transferred for specific backup.
     *
     * @param string $auth      Authorization string
     * @param string $uniqueid  UUID referencing course bank backup resource
     *
     */
    public function get_chunk($auth, $uniqueid) {
        $result = $this->send_authenticated('chunks/' . $uniqueid, array(), 'GET', $auth);
        coursestore_logging::log_get_chunk($result);
        return $result;
    }
    /**
     * Transfer chunk
     *
     * @param string $auth      Authorization string
     * @param string $uniqueid  UUID referencing course bank backup resource
     * @param int    $chunk     Chunk number
     * @param array  $data      Data for transfer
     *
     */
    public function put_chunk($data, $uniqueid, $chunknumber, $sessionkey, $retries=5) {

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
     * @param string $uniqueid       UUID referencing course bank backup resource
     * @param int    $chunkiterator  Chunk number
     *
     */
    public function delete_chunk($sessionkey, $uniqueid, $chunkiterator, $retries=5) {
        $result = $this->send_authenticated('chunks/' . $uniqueid . '/' . $chunkiterator, array(), 'DELETE', $sessionkey, $retries);
        coursestore_logging::log_delete_chunk($result);
        return $result;
    }
    /**
     * Get list of backup files available for download from course bank
     * instance.
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
        coursestore_logging::log_get_downloads($result);
        return $result;
    }
    /**
     * Get count of backup files available from course bank instance.
     */
    public function get_downloadcount($sesskey, array $params = null) {
        $result = $this->send_authenticated('downloadcount', array(), 'GET', $sesskey);
        coursestore_logging::log_get_downloadcount($result);
        return $result;
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
     public $error;
     public $error_desc;
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
            $this->error_desc = $body->error_desc;
        }

    }
    /**
     * Method to log the http response as a Moodle event. This is intended
     * for scenarios where an unexpected http response is encountered, and
     * data about this response and the initial request may be helpful for
     * debugging.
     *
     * @param int    $courseid        Moodle course ID.
     * @param int    $coursestoreid   Course store ID.
     * @param string $info            Additional information.
     */
    public function log_http_error($courseid, $coursestoreid, $info='') {
        global $CFG;

        // First log information for debugging purposes.
        if ($CFG->debug >= DEBUG_ALL && !empty($info)) {
            error_log($info);
        }

        if (tool_coursestore::legacy_logging()) {
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
            'courseid'      => $courseid,
            'coursestoreid' => $coursestoreid,
            'body'          => $body,
            'info'          => $info,
            'httpcode'      => $this->httpcode,
            'request'       => $request,
            );
        if (isset($this->error)) {
            $otherdata['error'] = $this->error;
        }
        if (isset($this->error_desc)) {
            $otherdata['error_desc'] = $this->error_desc;
        }
        $eventdata = array(
            'other'     => $otherdata,
            'context'   => context_system::instance(),
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

        if (isset($this->error)) {
            $info .= " ERROR: " . $this->error;
        }
        if (isset($this->error_desc)) {
            $info .= " ERROR DESC: " . $this->error_desc;
        }

        add_to_log(SITEID, 'Course store', 'Transfer error', '', $info, 0, $USER->id);
        return true;
    }
}

/**
 * Basic logging class
 *
 */
class coursestore_logging {
    const LOG_MODULE_COURSE_STORE = 'Course store';

    /**
     * Method to log course store events, using either the modern events 2
     * functionality for Moodle 2.7+, or legacy ("add_to_log") logging for
     * earlier Moodle versions.
     *
     * @global type $USER
     * @global type $CFG
     * @param string $eventname The name of event class
     * @param string $info Text to log
     * @param string $action Action
     * @param string $module Moodle module name
     * @param int $courseid Moodle course ID
     * @param string $url URL
     * @param int $userid Moodle user ID
     * @param array $other Other data we may want to use
     * @return boolean
     */
    protected static function log_event($info='', $eventname='coursestore_logging', $action='',
            $module=self::LOG_MODULE_COURSE_STORE, $courseid=SITEID, $url='', $userid=0, $other = array()) {
        global $USER, $CFG;

        // First log information for debugging purposes.
        if ($CFG->debug >= DEBUG_ALL) {
            error_log($info);
        }

        if ($userid == 0) {
            $userid = $USER->id;
        }

        $url = str_replace($CFG->wwwroot, "/", $url);

        if (!tool_coursestore::legacy_logging()) {
            $otherdata = array_merge(
                array(
                    'courseid' => $courseid,
                    'module'   => $module,
                    'action'   => $action,
                    'url'      => $url,
                    'info'     => $info,
                    'userid'   => $userid
                ),
                $other
            );

            $eventdata = array(
                'other'    => $otherdata,
                'context' => context_system::instance()
            );

            $classname = '\tool_coursestore\event\\' .  $eventname;

            if (class_exists($classname)) {
                $event = $classname::create($eventdata);
                $event->trigger();

                return true;
            }
        }
        // Legacy logging.
        add_to_log($courseid, $module, $action, $url, $info, 0, $userid);
        return true;
    }
    /** Log simple generic event for an http request.
     *
     * @param coursestore_http_responsehttpresponse Response object.
     * @param string                   eventname    Event class name.
     * @param string                   eventdesc    Event description.
     * @param string                   action       Action description.
     */
    protected static function log_generic_request($httpresponse, $eventname, $eventdesc, $action) {
        global $USER;

        if ($httpresponse->httpcode == coursestore_ws_manager::WS_HTTP_OK) {
            $otherdata = array('status' => true);
        } else {
            $otherdata = array(
                'status' => false,
                'error'  => $httpresponse->error_desc,
                'error'  => $httpresponse->error
            );
        }
        $status = $otherdata['status'] ? 'Succeeded' : 'Failed';
        $info = $eventdesc . ' ' . $status . '.';

        self::log_event(
            $info,
            $eventname,
            $action,
            self::LOG_MODULE_COURSE_STORE,
            SITEID,
            '',
            $USER->id,
            $otherdata
        );
    }
    /**
     * Log an event for a coursestore backup status update.
     *
     * @param string $info  Information about update
     */
    public static function log_status_update($info) {
        self::log_event($info, 'coursestore_logging', 'update status');
    }
    /** Log a connection check event.
     *
     * @param coursestore_http_response $httpresponse Response object.
     */
    public static function log_check_connection($httpresponse) {
        global $USER;

        $otherdata = array('conncheckaction' => 'conncheck');
        if (($httpresponse instanceof coursestore_http_response)
            && $httpresponse->httpcode == coursestore_ws_manager::WS_HTTP_OK) {
            $info = "Connection check passed.";
            $otherdata['status'] = true;
        } else {
            $info = "Connection check failed.";
            $otherdata['status'] = false;
            if ($httpresponse instanceof coursestore_http_response) {
                // Log the failure.
                if (isset($httpresponse->error)) {
                    $otherdata['error'] = $httpresponse->error;
                }
                if (isset($httpresponse->error_desc)) {
                    $otherdata['error_desc'] = $httpresponse->error_desc;
                }
            }
        }
        self::log_event(
            $info,
            'connection_checked',
            'Connection check',
            self::LOG_MODULE_COURSE_STORE,
            SITEID,
            '',
            $USER->id,
            $otherdata
        );
    }
    public static function log_check_connection_speed($httpresponse, $speed) {
        global $USER;

        // Log connection speed test.
        $otherdata = array(
            'conncheckaction' => 'speedtest',
        );

        if (($httpresponse instanceof coursestore_http_response)
            && $httpresponse->httpcode == coursestore_ws_manager::WS_HTTP_OK) {
            $info = "Connection speed test passed. Approximate speed: " . $speed . " kbps.";
            $otherdata['speed'] = $speed;
        } else {
            $info = "Connection speed test failed.";
            $otherdata['speed'] = 0;
            if ($httpresponse instanceof coursestore_http_response) {
                // Log the failure.
                if (isset($httpresponse->error)) {
                    $otherdata['error'] = $httpresponse->error;
                }
                if (isset($httpresponse->error_desc)) {
                    $otherdata['error_desc'] = $httpresponse->error_desc;
                }
            }
        }
        self::log_event(
            $info,
            'connection_checked',
            'Connection check',
            self::LOG_MODULE_COURSE_STORE,
            SITEID,
            '',
            $USER->id,
            $otherdata
        );
    }

    /**
     * Log session creation event.
     *
     * @param coursestore_http_response $httpresponse  HTTP response object.
     */
    public static function log_post_session($httpresponse) {
        global $USER;

        $otherdata = array();
        if (($httpresponse instanceof coursestore_http_response)
             && $httpresponse->httpcode == coursestore_ws_manager::WS_HTTP_CREATED) {
            // We got a new session key. Log the event.
            $info = "Get new session key succeeded.";
        } else {
            // Couldn't get a session key.
            $info = "Get new session key failed.";
            if ($httpresponse instanceof coursestore_http_response) {
                // Log the session key failure.
                if (isset($httpresponse->error)) {
                    $otherdata['error'] = $httpresponse->error;
                }
                if (isset($httpresponse->error_desc)) {
                    $otherdata['error_desc'] = $httpresponse->error_desc;
                }
            }
        }
        self::log_event(
            $info,
            'get_session',
            'Get session key',
            self::LOG_MODULE_COURSE_STORE,
            SITEID,
            '',
            $USER->id,
            $otherdata
        );
    }
    /** Log event for get_backup request.
     *
     * @param coursestore_http_response $httpresponse Response object.
     */
    public static function log_get_backup($httpresponse) {
        global $USER;

        self::log_generic_request(
               $httpresponse, 'http_request', 'GET backup request',
                'Get backup information.'
        );
    }
    /** Log event for get_chunk request.
     *
     * @param coursestore_http_responsehttpresponse Response object.
     */
    public static function log_get_chunk($httpresponse) {
        global $USER;

        self::log_generic_request(
               $httpresponse, 'http_request', 'GET chunk request',
                'Get chunk information.'
        );
    }
    /** Log event for delete_chunk request.
     *
     * @param coursestore_http_responsehttpresponse Response object.
     */
    public static function log_delete_chunk($httpresponse) {
        global $USER;

        self::log_generic_request(
                $httpresponse, 'http_request', 'DELETE chunk request',
                'Delete chunk information.'
        );
    }
    /** Log event for get_download request.
     *
     * @param coursestore_http_responsehttpresponse Response object.
     */
    public static function log_get_downloads($httpresponse) {
        global $USER;

        self::log_generic_request(
                $httpresponse, 'http_request', 'GET download request',
                'Get Course Bank backups available for download.'
        );
    }
    /** Log event for get_downloadcount request.
     *
     * @param coursestore_http_responsehttpresponse Response object.
     */
    public static function log_get_downloadcount($httpresponse) {
        global $USER;

        self::log_generic_request(
                $httpresponse, 'get_downloadcount_request', 'GET download count request',
                'Get count of available Course Bank backups.'
        );
    }
    /**
     * Log the fact that transfers for a course backup or chunk have started.
     *
     * @param mixed $backup    if object: Course store database record object
     *                         if array: data getting sent to webservice.
     * @param object $httpresponse
     * @param string $level    either 'course' or 'chunk'.
     */
    public static function log_transfer_started($backup, $httpresponse=null, $level='course') {
        global $USER;

        if (is_object($backup)) {
            // This is the backup object.
            $coursestoreid = $backup->id;
            $courseid = $backup->courseid;
        } else if (is_array($backup)) {
            // This is the data that is getting sent to the webservice.
            $coursestoreid = $backup['fileid'];
            $courseid = $backup['courseid'];
        }

        $otherdata = array(
            'level'         => $level,
            'coursestoreid' => $coursestoreid,
        );
        if (($httpresponse instanceof coursestore_http_response)
            && $httpresponse->httpcode == coursestore_ws_manager::WS_HTTP_CREATED) {
            // At this stage, $backup is an array.
            $validatehash = coursestore_ws_manager::get_backup_validated_hash($backup);
            if ($validatehash != $httpresponse->body->hash) {
                $info = "Transfer of " . ($level == 'course' ? 'backup' : 'chunk') . " for course store id $coursestoreid " .
                        "failed. (Course ID: $courseid)";
                $otherdata['error_desc'] = "Returned hash ({$httpresponse->body->hash}) " .
                                           "does not match validated hash ($validatehash).";
            } else {
                $info = "Transfer of " . ($level == 'course' ? 'backup' : 'chunk') . " for course store id $coursestoreid " .
                        "started. (Course ID: $courseid)";
            }
        } else {
            $info = "Transfer of " . ($level == 'course' ? 'backup' : 'chunk') . " for course store id $coursestoreid " .
                    "failed. (Course ID: $courseid)";
            if ($httpresponse instanceof coursestore_http_response) {
                if ($httpresponse->httpcode == coursestore_ws_manager::WS_HTTP_CONFLICT && $level == 'course') {
                    // The course was already created.
                    // Check if Course Bank has the same data as us.
                    if (!coursestore_ws_manager::check_post_backup_data_is_same($httpresponse, $backup)) {
                        $info .= " The backup already exists.";
                        if ($httpresponse->body->is_completed) {
                            // Course Bank already has a complete copy of this backup.
                            $info .= " The backup is complete in Course Bank.";
                        }
                    } else {
                        // It's ok, will continue.
                        $info = "Transfer of " .
                                ($level == 'course' ? 'backup' : 'chunk') .
                                " for course store id $coursestoreid " .
                        "started. (Course ID: $courseid) It already exists " .
                        "in Course Bank.  Will continue.";
                    }
                }
                // Log the session key failure.
                if (isset($httpresponse->error)) {
                    $otherdata['error'] = $httpresponse->error;
                }
                if (isset($httpresponse->error_desc)) {
                    $otherdata['error_desc'] = $httpresponse->error_desc;
                }
            }
        }

        self::log_event(
            $info,
            'transfer_started',
            'Transfer started',
            self::LOG_MODULE_COURSE_STORE,
            $courseid,
            '',
            $USER->id,
            $otherdata
        );
    }
    /**
     * Log the fact that transfers for a backup have resumed.
     *
     * @param object $backup    Course store database record object
     */
    public static function log_transfer_resumed($backup) {
        global $USER;

        $info = "Transfer of backup with course store id $backup->id " .
                "resumed. (Course ID: $backup->courseid)";
        $otherdata = array(
            'coursestoreid' => $backup->id
        );
        self::log_event(
            $info,
            'transfer_resumed',
            'Transfer resumed',
            self::LOG_MODULE_COURSE_STORE,
            $backup->courseid,
            '',
            $USER->id,
            $otherdata
        );
    }
    /**
     * Log transfer completion event.
     *
     * @param object $backup    Course store database record object
     */
    public static function log_transfer_completed($backup) {
        global $USER;

        // Log transfer_completed event.
        $info = "Transfer of backup with course store id $backup->id " .
                "completed. (Course ID: $backup->courseid)";
        $otherdata = array(
            'coursestoreid' => $backup->id
            );
        self::log_event(
                $info,
                'transfer_completed',
                'Transfer completed',
                self::LOG_MODULE_COURSE_STORE,
                $backup->courseid,
                '',
                $USER->id,
                $otherdata
        );
    }
    public static function log_backup_updated($data, $response) {
        // TODO: log backup updated event.
    }
    /**
     * Log transfer backup download event.
     *
     * @param http_response $httpresponse   HTTP response object generated.
     * @param int           $coursestoreid  Course store ID
     *
     * @return bool                         Success/failure of download.
     */
    public static function log_backup_download($httpresponse, $coursestoreid) {
        global $USER;

        $error = false;
        $info = "Downloading backup file coursestoreid $coursestoreid userid $USER->id: ";

        if (isset($httpresponse->body->error) and isset($httpresponse->body->error_desc)) {
            // Log it.
            $infoadd = "ERROR: error code {$httpresponse->body->error}, error desc: {$httpresponse->body->error_desc}";
            $error = true;
        }
        if (!isset($httpresponse->body->url)) {
            // Log it.
            $infoadd = "ERROR: url is empty";
            $error = true;
        }
        if (!tool_coursestore_check_url($httpresponse->body->url)) {
            $infoadd = "ERROR: url {$httpresponse->body->url} invalid";
            $error = true;
        }
        if (!tool_coursestore_is_url_available($httpresponse->body->url)) {
            $infoadd = "ERROR: url {$httpresponse->body->url} in not available";
            $error = true;
        }

        $courseid = isset($backup->courseid) ? $backup->courseid : 0;

        // Log either success or failure event.
        if ($error) {
            $info .= $infoadd;
            self::log_event(
                    $info,
                    'coursestore_logging',
                    'Course Bank download failed',
                    self::LOG_MODULE_COURSE_STORE,
                    $courseid,
                    '');
        } else {
            $infoadd = "SUCCESS";
            $info .= $infoadd;
            self::log_event(
                    $info,
                    'coursestore_logging',
                    'Course Bank download success',
                    self::LOG_MODULE_COURSE_STORE,
                    $courseid,
                    '');
        }
    }
}
/**
 * An exception when tool_coursestore_cronlock exists in the database.
 *
 */
class transfer_in_progress extends moodle_exception {
    /**
     * Constructor
     * @param string $debuginfo optional more detailed information
     */
    public function __construct($debuginfo = null) {
        parent::__construct('transferinprogress', 'tool_coursestore', '', null, $debuginfo);
    }
}
