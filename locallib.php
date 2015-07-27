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

abstract class tool_coursebank {

    // Status.
    const STATUS_NOTSTARTED = 0;
    const STATUS_INPROGRESS = 1;
    const STATUS_FINISHED = 2;
    const STATUS_ONHOLD = 3;
    const STATUS_CANCELLED = 4;
    const STATUS_ERROR = 99;
    const DEFAULT_TIMEOUT = 10;
    const DEFAULT_RETRIES = 4;
    const SEND_SUCCESS = 0;
    const SEND_ERROR = 1;
    const SEND_CRON_TIMEOUT = 2;
    const CRON_TIMEOUT = 30;
    // Maximum number of days a backup can be for fetching.
    const MAX_BACKUP_DAYS = 2;
    /**
     * Returns an array of available statuses
     * @return array of availble statuses
     */
    public static function get_statuses() {
        $notstarted   = get_string('statusnotstarted', 'tool_coursebank');
        $inprogress   = get_string('statusinprogress', 'tool_coursebank');
        $statuserror  = get_string('statuserror', 'tool_coursebank');
        $finished     = get_string('statusfinished', 'tool_coursebank');
        $onhold       = get_string('statusonhold', 'tool_coursebank');
        $cancelled    = get_string('statuscancelled', 'tool_coursebank');

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
     * Get the stored session key for use with the external course bank
     * REST API if one exists.
     *
     * @return string or bool false  Session key
     */
    public static function get_session() {
        $sessionkey = get_config('tool_coursebank', 'sessionkey');
        if (!empty($sessionkey)) {
            return $sessionkey;
        }
        return '';
    }

    /**
     * Set the session key for use with the external course bank REST API.
     *
     * @return bool  Success/failure
     */
    public static function set_session($sessionkey) {
        if (set_config('sessionkey', $sessionkey, 'tool_coursebank')) {
            return true;
        }
        return false;
    }

    /**
     * Test that a connection to the configured web service consumer can be
     * made successfully.
     *
     * @param  coursebank_ws_manager $wsman  Web service manager object.
     * @param  string               $sesskey  External course bank session key.
     *
     * @return bool                           True for success, false otherwise.
     */
    public static function check_connection(coursebank_ws_manager $wsman, $sesskey=false) {
        global $USER;

        $result = $wsman->get_test($sesskey);
        $success = false;
        if (isset($result->httpcode)) {
            if ($result->httpcode == coursebank_ws_manager::WS_HTTP_OK) {
                $success = true;
            }
        }
        // First log the result, then return it.
        $info = 'Connection check ';
        $info .= $success ? 'passed.' : 'failed.';
        $event = $success ? 'connection_checked' : 'connection_check_failed';
        $action = 'Connection check';
        $otherdata = array(
            'conncheckaction' => 'conncheck',
            'status' => $success
        );
        coursebank_logging::log_event(
            $info,
            $event,
            $action,
            coursebank_logging::LOG_MODULE_COURSE_BANK,
            SITEID,
            '',
            $USER->id,
            $otherdata
        );
        return $success;
    }
    /**
     * Using get_optimal_chunksize, test the speed of a transfer request.
     *
     * Log the result of the test before returning an array containing both the
     * tested transfer speed in kbps and the optimal chunk size for the site.
     *
     * @param coursebank_ws_manager $wsman  Web service manager object.
     * @param int                    $retry  Number of retry attempts.
     * @param string               $sesskey  Session key.
     *
     * @return array                         Approximate connection speed in
     *                                       kbps, and chunk size in kB:
     *
     *                                       array('speed' => $speed,
     *                                             'chunksize' => $chunksize)
     */
    public static function check_connection_speed(coursebank_ws_manager $wsman,
            $retry, $sesskey) {
        global $USER;

        $chunksizes = array(10, 100, 200, 500, 1000, 1500, 2000);
        $result = self::get_optimal_chunksize($wsman, $sesskey, $chunksizes, $retry);

        // Log connection speed test.
        $otherdata = array(
            'conncheckaction' => 'speedtest',
        );
        if ($result['speed'] > 0) {
            $event = 'connection_checked';
            $status = ($result['speed'] > 256) ? 'passed' : 'very slow';
            $info = "Connection speed test $status. Approximate speed: "
                    . $result['speed'] . " kbps.";
            $otherdata['speed'] = $result['speed'];
        } else {
            $event = 'connection_check_failed';
            $info = "Connection speed test failed.";
            $otherdata['speed'] = 0;
        }
        coursebank_logging::log_event(
            $info,
            $event,
            'Connection check',
            coursebank_logging::LOG_MODULE_COURSE_BANK,
            SITEID,
            '',
            $USER->id,
            $otherdata
        );

        return $result;
    }
    /**
     * Send test transfer requests for each of the provided chunk sizes, and
     * return the most optimal chunk size value (in kB), along with the
     * transfer speed achieved in kbps.
     *
     * The provided array of chunk size values should be in ascending order.
     * Iterate through this array of chunk sizes, calculating a rough transfer
     * speed for a single request with dummy data of the corresponding chunk
     * size. Continue iterating while increasing the chunk size improves the
     * transfer speed by at least 10%. Stop either when this is no longer the
     * case, or a transfer takes longer than 5 seconds.
     *
     * @param coursebank_ws_manager $wsman  Web service manager object.
     * @param string               $sesskey  Session key.
     * @param array int              $sizes  Array of chunk sizes, sorted
     *                                       in ascending order, in kB.
     * @param int                    $retry  Number of retry attempts.
     *
     * @return array                         Array containing tested speed in
     *                                       kbps and suggested chunksize in kB.
     */
    protected static function get_optimal_chunksize(coursebank_ws_manager $wsman,
            $sesskey, $sizes=array(10, 100, 1000), $retry=1) {
        $timeout = 5;
        $threshold = 1.1;
        $current = $sizes[0];
        $speed = self::test_chunk_speed(
                $wsman,
                $current,
                $retry,
                $sesskey
        );
        foreach (array_slice($sizes, 1) as $size) {
            $starttime = time();
            $newspeed = self::test_chunk_speed($wsman, $size, $retry, $sesskey);
            if ($newspeed < ($threshold * $speed) || (time() - $starttime) >= $timeout) {
                break;
            }
            $speed = $newspeed;
            $current = $size;
        }
        return array('speed' => $speed, 'chunksize' => $current);

    }
    /**
     * Send a number of test transfer requests, then calculate and return a
     * rough connection speed (in kbps) based on these transfers. If a request
     * fails, return 0.
     *
     * @param coursebank_ws_manager $wsman    ws_manager object.
     * @param int                 $testsize    Size of test data to transfer.
     * @param int                    $retry    Number of retry attempts.
     * @param string               $sesskey    Session key string.
     * @param int                    $count    Number of requests to make.
     *
     * @return int                   $speed
     */
    public static function test_chunk_speed(coursebank_ws_manager $wsman,
            $testsize, $retry, $sesskey, $count=1) {
        $starttime = microtime(true);
        for ($j = 0; $j <= $retry; $j++) {
            $check = str_pad('', $testsize * 1000, '0');
            $response = $wsman->get_test(
                    $sesskey, $check, $count, $testsize, $starttime, $endtime);
            if ($response->httpcode == coursebank_ws_manager::WS_HTTP_OK) {
                break;
            }
        }
        if ($response->httpcode == coursebank_ws_manager::WS_HTTP_OK) {
            $elapsed = $endtime - $starttime;
            // Convert 'total kB transferred'/'total time' into kb/s.
            $speed = self::calculate_speed(1, $testsize, $starttime, $endtime);
        } else {
            $speed = 0;
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
        return get_config('tool_coursebank', 'chunksize');
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
     * Function to fetch course bank records for display on the summary
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

        $results = $DB->get_records_select('tool_coursebank', $extraselect, $extraparams, $sort, '*', $page, $recordsperpage);
        $count = $DB->count_records_select('tool_coursebank', $extraselect, $extraparams);

        return array('results' => $results, 'count' => $count);
    }
    /**
     *
     * @param coursebank_ws_manager $wsmanager
     * @param object $backup         Course bank database record object
     * @param array  $data           Data array to post/put.
     * @param string $sessionkey     Session token
     * @param int    $retries        Number of retries to attempt sending
     *                               when an error occurs.
     * @return array of: int     result null = needs further investigation.
     *                                    -1 = some error, don't continue.
     *                                     1 = all good but don't continue
     *                                         -> i.e. External Course Bank already has the backup.
     *                   boolean deletechunks - whether or not chunks should be deleted.
     *                   int     highestiterator - highest iterator Cours Bank has received for this backup.
     *                   coursebank_http_response putresponse - the response from the put request.
     *
     */
    private static function update_backup(coursebank_ws_manager $wsmanager, $data, $backup, $sessionkey, $retries) {
        global $DB;

        $result = null;
        $deletechunks = false;
        $highestiterator = 0;
        $putresponse = $wsmanager->put_backup($sessionkey, $data, $backup->uniqueid, $retries);
        if ($putresponse !== true) {
            if ($putresponse->httpcode == coursebank_ws_manager::WS_HTTP_BAD_REQUEST) {
                if (isset($putresponse->body->chunksreceived)) {
                    // We've sent chunks to External Course Bank already?
                    if ($putresponse->body->chunksreceived == 0) {
                        // Possible network error. Should retry again later.
                        $backup->status = self::STATUS_ERROR;
                        $DB->update_record('tool_coursebank', $backup);
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
                } else if (isset($putresponse->body->is_completed)) {
                    // We've sent chunks to External Course Bank already?
                    if ($putresponse->body->is_completed) {
                        // Can skip this one as it's already been sent to External Course Bank and is complete.
                        // Another process (?!?) may have completed this in the meantime.
                        $backup->status = self::STATUS_FINISHED;
                        $backup->timecompleted = time();
                        $DB->update_record('tool_coursebank', $backup);
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
     * @param object $backup    Course bank database record object
     *
     * @return int  0 = all good, continue.
     *             -1 = some error, don't continue.
     *              1 = all good but don't continue -> i.e. External Course Bank already has the backup.
     */
    private static function initialise_backup(coursebank_ws_manager $wsmanager, $backup, $sessionkey, $retries) {
        global $DB;

        $coursedate = '';
        if ($backup->coursestartdate > 0) {
            $datetime = new DateTime("@" . $backup->coursestartdate);
            $coursedate = $datetime->format('Y-m-d H:i:s');
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
            'filetimemodified' => $backup->filetimemodified
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
            if ($postresponse->httpcode == coursebank_ws_manager::WS_HTTP_CONFLICT) {
                // External Course Bank already has some data for this backup.
                if ($postresponse->body->is_completed) {
                    // Can skip this one as it's already been sent to External Course Bank and is complete.
                    // Data may not match due to chunk size settings changing.
                    $backup->status = self::STATUS_FINISHED;
                    $backup->timecompleted = time();
                    $DB->update_record('tool_coursebank', $backup);
                    // Don't let the send_backup() continue.
                    return 1;
                } else if ($postresponse->body->chunksreceived == 0) {
                    /* External Course Bank has some other data for this backup.
                     * But no chunks have been sent yet.
                     * Try to update it.
                     * Don't unset the fileid or the uuid fields.*/
                    list($result, $deletechunks, $highestiterator, $putresponse) = self::update_backup(
                            $wsmanager, $data, $backup, $sessionkey, $retries);
                    if (!is_null($result)) {
                        return $result;
                    }
                } else {
                    /* Post_backup informs us that there's already data for this backup
                     * in External Course Bank.  And, that it's already started receiving
                     * chunks.
                     * We need to delete the chunks, then update the backup, then continue.*/
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
                    /* Ignore any errors for this.  Perhaps one of the chunks is missing and
                     * that's why we're getting an error.
                     * Anyway, the update below should catch any errors.*/
                }
                list($result, $deletechunks, $highestiterator, $putresponse) = self::update_backup(
                        $wsmanager, $data, $backup, $sessionkey, $retries);
                if (!is_null($result)) {
                    return $result;
                }
                if ($deletechunks || $highestiterator > 0) {
                    // Something is wrong. Try again later.
                    $backup->status = self::STATUS_ERROR;
                    $DB->update_record('tool_coursebank', $backup);
                    // Log a transfer interruption event.
                    $putresponse->log_http_error($backup->courseid, $backup->id);
                    return -1;
                }
                // Continue.
            } else {
                $backup->status = self::STATUS_ERROR;
                $DB->update_record('tool_coursebank', $backup);
                // Log a transfer interruption event.
                $postresponse->log_http_error($backup->courseid, $backup->id);
                return -1;
            }
        }
        $backup->status = self::STATUS_INPROGRESS;
        $DB->update_record('tool_coursebank', $backup);
        return 0;
    }
    /**
     * Convenience function to handle sending a file along with the relevant
     * metadata.
     *
     * @param object $backup      Course bank database record object
     * @param int    $starttime   Timestamp corresponding to the the time when
     *                            the transfer task was started. Used to
     *                            determine if a transfer should be halted due
     *                            to cron task time out.
     *
     * @return int   $returncode  Return code. One of:
     *                              0 = Successfully transferred
     *                              1 = Error
     *                              2 = Cron timeout
     *
     */
    public static function send_backup($backup, $starttime) {
        global $CFG, $DB, $USER;

        // Calculate time limit point as timestamp.
        $endtime = $starttime + (self::CRON_TIMEOUT) * 60;

        // Copy the backup file into our storage area so there are no changes to the file
        // during transfer, unless file already exists.
        if ($backup->isbackedup == 0) {
            $backup = self::copy_backup($backup);
        }

        if ($backup === false) {
            return self::SEND_ERROR;
        }

        // Get required config variables.
        $urltarget = get_config('tool_coursebank', 'url');
        $timeout = self::DEFAULT_TIMEOUT;
        $retries = self::DEFAULT_RETRIES;
        $token = get_config('tool_coursebank', 'authtoken');
        $sessionkey = self::get_session();

        // Initialise, check connection.
        $wsmanager = new coursebank_ws_manager($urltarget, $timeout);
        if (!self::check_connection($wsmanager, $sessionkey)) {
            $backup->status = self::STATUS_ERROR;
            $DB->update_record('tool_coursebank', $backup);
            $wsmanager->close();
            return self::SEND_ERROR;
        }

        // Update again in case a new session key was given.
        $sessionkey = self::get_session();

        // Chunk size is set in kilobytes.
        $chunksize = $backup->chunksize * 1000;

        // Open input file.
        $coursebankfilepath = self::get_coursebank_filepath($backup);
        $file = fopen($coursebankfilepath, 'rb');

        // Log transfer_resumed event.
        if ($backup->chunknumber > 0) {
            // Don't need to log the start. It will be logged in the post_backup call.
            coursebank_logging::log_transfer_resumed($backup, null, 'course');
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
                    return self::SEND_SUCCESS;
                    break;
                case -1:
                    $wsmanager->close();
                    return self::SEND_ERROR;
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
                $DB->update_record('tool_coursebank', $backup);
            } else {
                if ($backup->status == self::STATUS_ERROR) {
                    $backup->chunkretries++;
                } else {
                    $backup->status = self::STATUS_ERROR;
                }
                $DB->update_record('tool_coursebank', $backup);
                // Log a transfer interruption event.
                $response->log_http_error($backup->courseid, $backup->id);
                return self::SEND_ERROR;
            }
            if (time() >= $endtime) {
                // Cron task time limit reached, delay transfer to next run.
                return self::SEND_CRON_TIMEOUT;
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
            if ($completion->httpcode != coursebank_ws_manager::WS_HTTP_OK) {
                $backup->status = self::STATUS_ERROR;
                // Start from the beginning next time.
                $backup->chunknumber = 0;
                $backup->timechunkcompleted = 0;
                $DB->update_record('tool_coursebank', $backup);

                // Log a transfer interruption event.
                $completion->log_http_error($backup->courseid, $backup->id);
                return self::SEND_ERROR;
            }
            $backup->status = self::STATUS_FINISHED;
            $backup->timecompleted = time();
            $DB->update_record('tool_coursebank', $backup);
        }

        $wsmanager->close();
        fclose($file);
        return self::SEND_SUCCESS;
    }

    public static function get_coursebank_data_dir() {
        global $CFG;

        $dir = $CFG->dataroot . "/coursebank";
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        return $dir;
    }

    public static function get_coursebank_filepath($backup) {
        return self::get_coursebank_data_dir() . "/" . $backup->contenthash;
    }

    /**
     * Convenience function to handle copying the backup file to the designated storage area.
     *
     * @param object $backup    Course bank database record object
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
        if (!is_writable(self::get_coursebank_data_dir())) {
            throw new invalid_dataroot_permissions();
        }

        // Remove any old backups that may have failed and later cancelled.
        self::clean_coursebank_data_dir();

        $coursebankfilepath = self::get_coursebank_filepath($backup);
        copy($moodlefilepath, $coursebankfilepath);

        $backup->isbackedup = 1; // We have created a copy.
        $DB->update_record('tool_coursebank', $backup);

        return $backup;
    }
    /**
     * Convenience function to clear out any old copied backup files.
     *
     */
    public static function clean_coursebank_data_dir() {
        $coursebankdatadir = self::get_coursebank_data_dir();
        if (!is_writable($coursebankdatadir)) {
            return false;
        }

        if (is_dir($coursebankdatadir)) {
            $maxbackuptime = time() - (self::MAX_BACKUP_DAYS * DAYSECS);
            $objects = scandir($coursebankdatadir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    $filename = $coursebankdatadir."/".$object;
                    if (filetype($filename) == "file") {
                        $filemtime = filemtime($filename);
                        if ($filemtime < $maxbackuptime) {
                            unlink($filename);
                        }
                    }
                }
            }
            reset($objects);
        }
        return true;
    }
    /**
     * Function to handle deleteing the backup file from the designated storage area.
     *
     * @param object $backup           Course bank database record object
     * @param boolean $updaterecord   Update the database record to relect that the backup is no longer copied.
     *
     */
    public static function delete_backup($backup, $updaterecord) {
        global $DB;

        $coursebankfilepath = self::get_coursebank_filepath($backup);

        if (!is_readable($coursebankfilepath)) {
            return false;
        }
        if (!is_writable(self::get_coursebank_data_dir())) {
            return false;
        }

        unlink($coursebankfilepath);

        if ($updaterecord == true) {
            $backup->isbackedup = 0; // We have deleted the copy.
            $DB->update_record('tool_coursebank', $backup);
        }

        return true;
    }
    /**
     *  Function to handle deleting the moodle backup file from the storage area.
     *
     * @param object $backup Course bank database record object
     *
     */
    public static function delete_moodle_backup($backup) {
        global $CFG;

        require_once($CFG->dirroot . '/backup/util/helper/backup_cron_helper.class.php');

        $deletelocalbackup = get_config('tool_coursebank', 'deletelocalbackup');

        // We don't want to delete moodle backup file.
        if (empty($deletelocalbackup)) {
            return true;
        }

        $config = get_config('backup');
        $storage = $config->backup_auto_storage;
        $dir = $config->backup_auto_destination;

        if (!file_exists($dir) || !is_dir($dir) || !is_writable($dir)) {
            $dir = null;
        }

        // Clean up excess backups in the course backup filearea.
        if ($storage == 0 || $storage == 2) {
            $filestorage = get_file_storage();
            $context = context_course::instance($backup->courseid);
            $component = 'backup';
            $filearea = 'automated';
            $itemid = 0;
            $files = array();
            // Store all the matching files into timemodified => stored_file array.
            foreach ($filestorage->get_area_files($context->id, $component, $filearea, $itemid) as $file) {
                $files[$file->get_timemodified()] = $file;
            }

            // Sort by keys descending (newer to older filemodified).
            krsort($files);
            foreach ($files as $file) {
                if ($backup->fileid == $file->get_id()) {
                    $file->delete();
                    // Log it.
                    $delstring = get_string(
                            'moodledeletesuccess',
                            'tool_coursebank',
                            $backup->backupfilename
                    );
                    coursebank_logging::log_delete_backup($delstring, true);
                }
            }
        }

        // Clean up excess backups in the specified external directory.
        if (!empty($dir) && ($storage == 1 || $storage == 2)) {
            // Calculate backup filename regex, ignoring the date/time/info parts that can be
            // variable, depending of languages, formats and automated backup settings.
            $filename = backup::FORMAT_MOODLE . '-' . backup::TYPE_1COURSE . '-' . $backup->courseid . '-';
            $regex = '#' . preg_quote($filename, '#') . '.*\.mbz$#';

            // Store all the matching files into filename => timemodified array.
            $files = array();
            foreach (scandir($dir) as $file) {
                // Skip files not matching the naming convention.
                if (!preg_match($regex, $file, $matches)) {
                    continue;
                }

                // Read the information contained in the backup itself.
                try {
                    $bcinfo = backup_general_helper::get_backup_information_from_mbz($dir . '/' . $file);
                } catch (backup_helper_exception $e) {
                    mtrace('Error: ' . $file . ' does not appear to be a valid backup (' . $e->errorcode . ')');
                    continue;
                }

                // Make sure this backup concerns the course and site we are looking for.
                if ($bcinfo->format === backup::FORMAT_MOODLE &&
                        $bcinfo->type === backup::TYPE_1COURSE &&
                        $bcinfo->original_course_id == $backup->courseid &&
                        backup_general_helper::backup_is_samesite($bcinfo)) {
                    $files[$file] = $bcinfo->backup_date;
                }
            }

            // Sort by values descending (newer to older filemodified).
            arsort($files);
            foreach (array_keys($files) as $file) {
                if ($file == $backup->backupfilename) {
                    unlink($dir . '/' . $file);
                    // Log it.
                    $delstring = get_string(
                            'moodledeletesuccess',
                            'tool_coursebank',
                            $backup->backupfilename
                    );
                    coursebank_logging::log_delete_backup($delstring, true);
                }
            }
        }

        return true;
    }
    /**
     * Generates 128 bits of random data.
     *
     * @return string guid v4 string.
     */
    public static function generate_uuid() {
        if (function_exists("openssl_random_pseudo_bytes")) {
            // Algorithm from stackoverflow: http://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
            // If openSSL extension is installed.
            $data = openssl_random_pseudo_bytes(16);

            $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100.
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10.

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        } else if (function_exists("uuid_create")) {
            $uuid = '';
            $context = null;
            uuid_create($context);

            uuid_make($context, UUID_MAKE_V4);
            uuid_export($context, UUID_FMT_STR, $uuid);
            return trim($uuid);
        } else {
            // Fallback uuid generation based on:
            // "http://www.php.net/manual/en/function.uniqid.php#94959".
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

                // 32 bits for "time_low".
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),

                // 16 bits for "time_mid".
                mt_rand(0, 0xffff),

                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4.
                mt_rand(0, 0x0fff) | 0x4000,

                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1.
                mt_rand(0, 0x3fff) | 0x8000,

                // 48 bits for "node".
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        }
    }
    /**
     * Function to cancel old course backup records in the coursetore table
     * which have not been completely transferred to External Course Bank.
     *
     * It should ignore any errors and continue.
     *
     */
    public static function cancel_old_backups() {
        global $DB;

        // Get backups that are older than 2 days old - that are still 'in progress', 'error', or not started yet.
        $maxbackuptime = time() - (self::MAX_BACKUP_DAYS * DAYSECS);

        $sql = "SELECT tcs.id,
                       tcs.status,
                       tcs.isbackedup,
                       tcs.contenthash
                FROM {tool_coursebank} tcs
                WHERE tcs.status IN (:statusnotstarted, :statusinprogress, :statuserror)
                AND   tcs.filetimecreated < :maxbackuptime";

        $params = array('statusnotstarted'  => self::STATUS_NOTSTARTED,
                        'statusinprogress'  => self::STATUS_INPROGRESS,
                        'statuserror'       => self::STATUS_ERROR,
                        'maxbackuptime'     => $maxbackuptime,
                        );
        $recordset = $DB->get_recordset_sql($sql, $params);

        foreach ($recordset as $coursebackup) {
            if ($coursebackup->isbackedup) {
                self::delete_backup($coursebackup, false);
            }
            $coursebackup->isbackedup = 0;
            $coursebackup->status = self::STATUS_CANCELLED;
            $DB->update_record('tool_coursebank', $coursebackup);
        }
        $recordset->close();
    }
    /**
     * Function to fetch course backup records from the Moodle DB, add them
     * to the course bank table, and then process the files before sending
     * via web service to the configured external course bank instance.
     *
     * - The query consists of three parts which are combined with a UNION
     *
     */
    public static function fetch_backups() {
        global $CFG, $DB;

        $starttime = time();

        // Cancel any old backups that are in the coursebank table
        // which have not been completely transferred.
        self::cancel_old_backups();

        // Get backups that are less than 2 days old.
        $maxbackuptime = time() - (self::MAX_BACKUP_DAYS * DAYSECS);
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
                LEFT JOIN {tool_coursebank} tcs on tcs.fileid = f.id
                WHERE tcs.id IS NULL
                AND ct.contextlevel = :contextcourse1
                AND   f.mimetype IN ('application/vnd.moodle.backup', 'application/x-gzip')
                AND   f.timecreated >= :maxbackuptime1
                UNION
                " . $sqlselect . "
                INNER JOIN {tool_coursebank} tcs on tcs.fileid = f.id
                WHERE ct.contextlevel = :contextcourse2
                AND   f.mimetype IN ('application/vnd.moodle.backup', 'application/x-gzip')
                AND   tcs.status IN (:statusnotstarted2, :statusinprogress2, :statuserror2)
                AND   f.timecreated >= :maxbackuptime2
                UNION
                " . $sqlselect . "
                RIGHT JOIN {tool_coursebank} tcs on tcs.fileid = f.id
                WHERE f.id IS NULL
                AND tcs.isbackedup = 1
                AND tcs.status IN (:statusnotstarted3, :statusinprogress3, :statuserror3)
                AND   f.timecreated >= :maxbackuptime3
                ORDER BY timecreated";
        // This could possibly be done better... But moodle expects each instance of the variable to be provided separately,
        // so we have this.
        $params = array('statusnotstarted'  => self::STATUS_NOTSTARTED,
                        'statuserror'       => self::STATUS_ERROR,
                        'statusinprogress'  => self::STATUS_INPROGRESS,
                        'statusnotstarted2' => self::STATUS_NOTSTARTED,
                        'statuserror2'      => self::STATUS_ERROR,
                        'statusinprogress2' => self::STATUS_INPROGRESS,
                        'statusnotstarted3' => self::STATUS_NOTSTARTED,
                        'statuserror3'      => self::STATUS_ERROR,
                        'statusinprogress3' => self::STATUS_INPROGRESS,
                        'contextcourse1'    => CONTEXT_COURSE,
                        'contextcourse2'    => CONTEXT_COURSE,
                        'maxbackuptime1'    => $maxbackuptime,
                        'maxbackuptime2'    => $maxbackuptime,
                        'maxbackuptime3'    => $maxbackuptime,
                        );
        $recordset = $DB->get_recordset_sql($sql, $params);

        $insertfields = array('filesize', 'filetimecreated',
                'filetimemodified', 'courseid', 'contenthash',
                'pathnamehash', 'userid', 'coursefullname',
                'courseshortname', 'coursestartdate', 'categoryid',
                'categoryname'
        );
        foreach ($recordset as $coursebackup) {
            if (!isset($coursebackup->status)) {
                // The record hasn't been input in the course bank table yet.
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
                $backupid = $DB->insert_record('tool_coursebank', $cs);

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
        $recordset->close();

        // Get all backups that are pending transfer, attempt to transfer them.
        $sql = 'SELECT * FROM {tool_coursebank}
                        WHERE status IN (:notstarted, :inprogress, :error)
                        AND filetimecreated >= :maxbackuptime';
        $contstatus = array(
            'notstarted'    => self::STATUS_NOTSTARTED,
            'inprogress'    => self::STATUS_INPROGRESS,
            'error'         => self::STATUS_ERROR,
            'maxbackuptime' => $maxbackuptime,
        );
        $transferrs = $DB->get_recordset_sql($sql, $contstatus);

        foreach ($transferrs as $coursebackup) {
            $idparam = array('id' => $coursebackup->id);
            $status = $DB->get_field(
                    'tool_coursebank',
                    'status',
                    $idparam,
                    MUST_EXIST
            );
            // Skip this course if its status is on-hold or cancelled.
            if (!in_array($status, $contstatus)) {
                mtrace("Skipping transfer of backup with Course Bank ID " .
                        "$coursebackup->id and status code $status" .
                        " (Course ID: $coursebackup->courseid).");
                continue;
            }
            $result = self::send_backup($coursebackup, $starttime);
            if ($result == self::SEND_SUCCESS) {
                // Delete file from the designated storage area.
                $delete = self::delete_backup($coursebackup, true);
                if (!$delete) {
                    $delfail = get_string(
                            'deletefailed',
                            'tool_coursebank',
                            $coursebackup->backupfilename
                    );
                    // Log it.
                    coursebank_logging::log_delete_backup($delfail);
                    mtrace($delfail . "\n");
                }
                // Delete file from the automated backups storage area.
                $localdelete = self::delete_moodle_backup($coursebackup, true);
                if (!$localdelete) {
                    $delfail = get_string(
                            'localdeletefailed',
                            'tool_coursebank',
                            $coursebackup->backupfilename
                    );
                    // Log it.
                    coursebank_logging::log_delete_backup($delfail);
                    mtrace($delfail . "\n");
                }
            } else if ($result == self::SEND_CRON_TIMEOUT) {
                $crontimeout = get_string(
                        'crontimeout',
                        'tool_coursebank'
                );
                coursebank_logging::log_cron_timeout(
                        $crontimeout,
                        $coursebackup->courseid
                );
                break;
            } else {
                $bufail = get_string(
                        'backupfailed',
                        'tool_coursebank',
                        $coursebackup->backupfilename
                );
                // Log it.
                coursebank_logging::log_send_backup($bufail);
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
            if (PHPUNIT_TEST) {
                return false;
            }
            $logtable = coursebank_logging::get_log_table_name();
            // If no log table, then it's legacy log table.
            if (empty($logtable)) {
                return true;
            }
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
            coursebank_logging::log_status_update(
                    "Failed updating: status code \"$status\" does not exist."
            );
            return false;
        }
        // Check if record exists.
        if (!$DB->record_exists('tool_coursebank', array('id' => $coursebackup->id))) {
            coursebank_logging::log_status_update(
                    "Failed updating: course bank record with ID: " .
                    "\"$coursebackup->id\" does not exist"
            );
            return false;
        }
        // If status is a "no action" status we can't update it.
        // This prevents us from changing the status mid-way through a transfer.
        if (in_array($coursebackup->status, $noactionstatuses)) {
            coursebank_logging::log_status_update(
                    "Failed updating: current status is " .
                    "$coursebackup->status for course bank backup with " .
                    "ID $coursebackup->id. This status is in the " .
                    "\"no action\" list."
            );
            return false;
        }
        // Delete the copied backup - just in case. But, don't stop if the file doesn't exist.
        self::delete_backup($coursebackup, false);
        // Finally update.
        $oldstatus = $coursebackup->status;
        $coursebackup->status = $status;
        $coursebackup->isbackedup = 0;
        $DB->update_record('tool_coursebank', $coursebackup);
        coursebank_logging::log_status_update(
                "Updating status: successfully " .
                "updated status from $oldstatus to $status for backup with" .
                " ID $coursebackup->id.",
                true
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
abstract class tool_coursebank_error {
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
        if (!tool_coursebank_check_url($httpresponse->body->url)) {
            return true;
        }
        if (!tool_coursebank_is_url_available($httpresponse->body->url)) {
            return true;
        }

        return false;
    }
}

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
     *
     * @return array or bool false  Associative array of the form:
     *                                  'body' => <response body array>,
     *                                  'response => <response info array>
     *                              Or false if connection could not be made
     */
    protected function send($resource='', $data=array(), $method='POST', $auth=null, $retries = 4) {
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
        // Set proxy configuration if it is enabled.
        if (!empty($this->proxyurl)) {
            $curlopts[CURLOPT_PROXY] = $this->proxyurl;
            $curlopts[CURLOPT_PROXYUSERPWD] = "$this->proxyuser:$this->proxypass";
            $curlopts[CURLOPT_PROXYPORT] = $this->proxyport;
        }
        curl_setopt_array($this->curlhandle, $curlopts);
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $result = curl_exec($this->curlhandle);
            $info = curl_getinfo($this->curlhandle);
            if ($result) {
                $body = json_decode($result);
                $response = new coursebank_http_response($body, $info, $curlopts);
                break;
            }
        }
        if (!isset($response)) {
            $response = new coursebank_http_response(false, false, $curlopts);
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
     *
     * @return array or bool false  Associative array of the form:
     *                                  'body' => <response body array>,
     *                                  'response => <response info array>
     *                              Or false if connection could not be made
     */
    protected function send_authenticated($resource='', $data=array(), $method='POST', $auth=null, $retries = 4) {
        // Don't try sending unless we already have a session key.
        $result = false;
        if ($auth) {
            $result = $this->send($resource, $data, $method, $auth, $retries);
        }
        if (!$result || $result->httpcode == self::WS_HTTP_UNAUTHORIZED) {
            $token = get_config('tool_coursebank', 'authtoken');
            if ($this->post_session($token) === true) {
                $sesskey = tool_coursebank::get_session();
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
                $info = "Local value for $datafield does not match external coursebank value.";
                $httpresponse->log_http_error(
                        $data['courseid'],
                        $data['fileid'],
                        $info
                );
                return false;
            }
        }

        $dtresponse = new DateTime($httpresponse->body->coursestartdate);
        $dtdata = new DateTime($data['startdate']);
        $responsedate = $dtresponse->format('Y-m-d H:i:s');
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
    public function post_backup($data, $sessionkey, $retries=4) {

        $response = $this->send_authenticated('backup', $data, 'POST', $sessionkey, $retries);
        coursebank_logging::log_transfer_started($data, $response, 'course');

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
        coursebank_logging::log_backup_updated($data, $response);

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
        // Log transfer_completed event.
        coursebank_logging::log_transfer_completed($backup);
        $uniqueid = $backup->uniqueid;
        return $this->send_authenticated('backupcomplete/' . $uniqueid, $data, 'PUT', $sessionkey);
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
        coursebank_logging::log_get_downloads($result);
        return $result;
    }
    /**
     * Get count of backup files available from external course bank instance.
     */
    public function get_downloadcount($sesskey, array $params = null) {
        $result = $this->send_authenticated('downloadcount', array(), 'GET', $sesskey);
        coursebank_logging::log_get_downloadcount($result);
        return $result;
    }
}
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
                'responseinfo'  => $this->info,
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

/**
 * Basic logging class
 *
 */
class coursebank_logging {
    const LOG_MODULE_COURSE_BANK = 'Course bank';

    /**
     * Method to log course bank events, using either the modern events 2
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
    public static function log_event($info='', $eventname='coursebank_logging', $action='',
            $module=self::LOG_MODULE_COURSE_BANK, $courseid=SITEID, $url='', $userid=0, $other = array()) {
        global $USER, $CFG;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        $url = str_replace($CFG->wwwroot, "/", $url);

        if (!tool_coursebank::legacy_logging()) {
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

            $classname = '\tool_coursebank\event\\' .  $eventname;

            if (class_exists($classname)) {
                $event = $classname::create($eventdata);
                $event->trigger();

                return true;
            }
        } else {
            // Legacy logging.
            add_to_log($courseid, $module, $action, $url, $info, 0, $userid);
            return true;
        }
    }
    /** Log simple generic event for an http request.
     *
     * @param coursebank_http_responsehttpresponse Response object.
     * @param string                   eventname    Event class name.
     * @param string                   eventdesc    Event description.
     * @param string                   action       Action description.
     */
    protected static function log_generic_request($httpresponse, $eventname, $eventdesc, $action) {
        global $USER;

        if ($httpresponse->httpcode == coursebank_ws_manager::WS_HTTP_OK) {
            $otherdata = array('status' => true);
        } else {
            $otherdata = array(
                'status' => false,
                'error'  => $httpresponse->error_desc,
                'error'  => $httpresponse->error
            );
        }
        $status = $otherdata['status'] ? 'Succeeded' : 'Failed';

        if ($status == 'Failed') {
            $eventname = $eventname . '_failed';
        }
        $info = $eventdesc . ' ' . $status . '.';

        self::log_event(
            $info,
            $eventname,
            $action,
            self::LOG_MODULE_COURSE_BANK,
            SITEID,
            '',
            $USER->id,
            $otherdata
        );
    }
    /**
     * Log an event for cron run time out.
     *
     * @param string $info      Information about time out.
     * @param int    $courseid  Moodle course id.
     */
    public static function log_cron_timeout($info, $courseid) {
        self::log_event(
                $info,
                'timeout_reached',
                'Cron timeout reached',
                self::LOG_MODULE_COURSE_BANK,
                $courseid
        );
    }
    /**
     * Log an event for a coursebank backup status update.
     *
     * @param string $info  Information about update
     */
    public static function log_status_update($info, $result = false) {
        if (!$result) {
            $event = 'status_update_failed';
        } else {
            $event = 'status_updated';
        }
        self::log_event($info, $event, 'Update status');
    }
    /**
     * Log the fact that transfers for a course backup or chunk have started.
     *
     * @param mixed $backup    if object: Course bank database record object
     *                         if array: data getting sent to webservice.
     * @param object $httpresponse
     * @param string $level    either 'course' or 'chunk'.
     */
    public static function log_transfer_started($backup, $httpresponse=null, $level='course') {
        global $USER;

        if (is_object($backup)) {
            // This is the backup object.
            $coursebankid = $backup->id;
            $courseid = $backup->courseid;
        } else if (is_array($backup)) {
            // This is the data that is getting sent to the webservice.
            $coursebankid = $backup['fileid'];
            $courseid = $backup['courseid'];
        }

        $otherdata = array(
            'level'         => $level,
            'coursebankid' => $coursebankid,
        );
        if (($httpresponse instanceof coursebank_http_response)
            && $httpresponse->httpcode == coursebank_ws_manager::WS_HTTP_CREATED) {
            // At this stage, $backup is an array.
            $validatehash = coursebank_ws_manager::get_backup_validated_hash($backup);
            if ($validatehash != $httpresponse->body->hash) {
                $event = 'transfer_start_failed';
                $info = "Transfer of " . ($level == 'course' ? 'backup' : 'chunk') . " for course bank id $coursebankid " .
                        "failed. (Course ID: $courseid)";
                $otherdata['error_desc'] = "Returned hash ({$httpresponse->body->hash}) " .
                                           "does not match validated hash ($validatehash).";
            } else {
                $event = 'transfer_started';
                $info = "Transfer of " . ($level == 'course' ? 'backup' : 'chunk') . " for course bank id $coursebankid " .
                        "started. (Course ID: $courseid)";
            }
        } else {
            $event = 'transfer_start_failed';
            $info = "Transfer of " . ($level == 'course' ? 'backup' : 'chunk') . " for course bank id $coursebankid " .
                    "failed. (Course ID: $courseid)";
            if ($httpresponse instanceof coursebank_http_response) {
                if ($httpresponse->httpcode == coursebank_ws_manager::WS_HTTP_CONFLICT && $level == 'course') {
                    // The course was already created.
                    // Check if External Course Bank has the same data as us.
                    if (!coursebank_ws_manager::check_post_backup_data_is_same($httpresponse, $backup)) {
                        $info .= " The backup already exists.";
                        if ($httpresponse->body->is_completed) {
                            // External Course Bank already has a complete copy of this backup.
                            $info .= " The backup is complete in External Course Bank.";
                        }
                    } else {
                        // It's ok, will continue.
                        $event = 'transfer_started';
                        $info = "Transfer of " .
                                ($level == 'course' ? 'backup' : 'chunk') .
                                " for course bank id $coursebankid " .
                        "started. (Course ID: $courseid) It already exists " .
                        "in External Course Bank.  Will continue.";
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
            $event,
            'Transfer started',
            self::LOG_MODULE_COURSE_BANK,
            $courseid,
            '',
            $USER->id,
            $otherdata
        );
    }
    /**
     * Log the fact that transfers for a backup have resumed.
     *
     * @param object $backup    Course bank database record object
     */
    public static function log_transfer_resumed($backup) {
        global $USER;

        $info = "Transfer of backup with course bank id $backup->id " .
                "resumed. (Course ID: $backup->courseid)";
        $otherdata = array(
            'coursebankid' => $backup->id
        );
        self::log_event(
            $info,
            'transfer_resumed',
            'Transfer resumed',
            self::LOG_MODULE_COURSE_BANK,
            $backup->courseid,
            '',
            $USER->id,
            $otherdata
        );
    }
    /**
     * Log transfer completion event.
     *
     * @param object $backup    Course bank database record object
     */
    public static function log_transfer_completed($backup) {
        global $USER;

        // Log transfer_completed event.
        $info = "Transfer of backup with course bank id $backup->id " .
                "completed. (Course ID: $backup->courseid)";
        $otherdata = array(
            'coursebankid' => $backup->id
            );
        self::log_event(
                $info,
                'transfer_completed',
                'Transfer completed',
                self::LOG_MODULE_COURSE_BANK,
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
     * Log final status of backup sending.
     *
     * @param string $info
     * @param bool $result
     */
    public static function log_send_backup($info, $result = false) {
        if (!$result) {
            $event = 'backup_send_failed';
        }
        self::log_event($info, $event, 'Sending backup');
    }
    /**
     * Log deleting backup
     *
     * @param string $info
     * @param bool $result
     */
    public static function log_delete_backup($info, $result = false) {
        if (!$result) {
            $event = 'backup_delete_failed';
        } else {
            $event = 'backup_deleted';
        }
        self::log_event($info, $event, 'Deleting backup');
    }
    /**
     * Log transfer backup download event.
     *
     * @param http_response $httpresponse   HTTP response object generated.
     * @param int           $coursebankid  Course bank ID
     *
     * @return bool                         Success/failure of download.
     */
    public static function log_backup_download($httpresponse, $coursebankid) {
        global $USER;

        $error = false;
        $info = "Downloading backup file coursebankid $coursebankid userid $USER->id: ";

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
        if (!tool_coursebank_check_url($httpresponse->body->url)) {
            $infoadd = "ERROR: url {$httpresponse->body->url} invalid";
            $error = true;
        }
        if (!tool_coursebank_is_url_available($httpresponse->body->url)) {
            $infoadd = "ERROR: url {$httpresponse->body->url} in not available";
            $error = true;
        }

        $courseid = isset($backup->courseid) ? $backup->courseid : 0;

        // Log either success or failure event.
        if ($error) {
            $event = 'backup_download_failed';
            $info .= $infoadd;
        } else {
            $event = 'backup_downloaded';
            $infoadd = "SUCCESS";
            $info .= $infoadd;
        }
        self::log_event(
                $info,
                $event,
                'Backup file download',
                self::LOG_MODULE_COURSE_BANK,
                $courseid,
                '');
    }

    /**
     * Returns log table name of preferred reader, if leagcy then return empty string.
     *
     * @return string table name
     */
    public static function get_log_table_name() {
        // Get prefered sql_internal_reader reader (if enabled).
        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers();
        $logtable = '';

        // Get preferred reader.
        if (!empty($readers)) {
            foreach ($readers as $readerpluginname => $reader) {
                // If legacy reader is preferred reader.
                if ($readerpluginname == 'logstore_legacy') {
                    break;
                }

                // If sql_internal_reader is preferred reader.
                if ($reader instanceof \core\log\sql_internal_reader) {
                    $logtable = $reader->get_internal_log_table_name();
                    break;
                }
            }
        }
        return $logtable;
    }
}
/**
 * An exception when tool_coursebank_cronlock exists in the database.
 *
 */
class transfer_in_progress extends moodle_exception {
    /**
     * Constructor
     * @param string $debuginfo optional more detailed information
     */
    public function __construct($debuginfo = null) {
        parent::__construct('transferinprogress', 'tool_coursebank', '', null, $debuginfo);
    }
}
