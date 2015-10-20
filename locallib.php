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

require_once($CFG->dirroot.'/admin/tool/coursebank/classes/coursebank_ws_manager.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/classes/coursebank_http_response.php');
require_once($CFG->dirroot . '/backup/util/helper/backup_cron_helper.class.php');

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

    // The youngest age a lock can reach before it will be removed.
    // Allow a longish period of time.
    const CRON_LOCK_TIMEOUT = 28800; // seconds (8 hours).

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
            // Convert 'total kB transferred'/'total time' into kb/s.
            $elapsed = $endtime - $starttime;
            $speed = round(($testsize * $count * 8) / $elapsed, 2);
        } else {
            $speed = 0;
        }

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
        global $DB, $USER;

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
                        $desc = coursebank_logging::get_backup_details(
                                'event_backup_update_interrupted',
                                $backup->uniqueid,
                                $backup->backupfilename
                        );
                        coursebank_logging::log_event(
                                $desc,
                                'transfer_interrupted',
                                'Transfer interrupted',
                                coursebank_logging::LOG_MODULE_COURSE_BANK,
                                $data['courseid'],
                                '',
                                $USER->id,
                                $data
                        );
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
        // Log a transfer update event.
        $desc = coursebank_logging::get_backup_details(
                'event_backup_update',
                $data['uuid'],
                $data['filename']
        );
        coursebank_logging::log_event(
                $desc,
                'backup_updated',
                'Backup updated',
                coursebank_logging::LOG_MODULE_COURSE_BANK,
                $data['courseid'],
                '',
                $USER->id,
                $data
        );
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
        global $DB, $USER, $CFG;

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
            'filetimemodified' => $backup->filetimemodified,
            'dbtype'           => isset($CFG->dbtype) ? $CFG->dbtype : 'unknown'
        );
        if (!isset($backup->timetransferstarted) || $backup->timetransferstarted == 0) {
            $backup->timetransferstarted = time();
        }
        $postresponse = $wsmanager->post_backup($data, $sessionkey, $retries);

        // Unexpected http response or none received.
        if ($postresponse instanceof coursebank_http_response) {
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
                    $desc = coursebank_logging::get_backup_details(
                            'event_backup_init_completed',
                            $data['uuid'],
                            $data['filename']
                    );
                    coursebank_logging::log_event(
                        $desc,
                        'transfer_started',
                        'Transfer started',
                        coursebank_logging::LOG_MODULE_COURSE_BANK,
                        $data['courseid'],
                        '',
                        $USER->id,
                        $data
                    );
                    // Don't let the send_backup() continue.
                    return 1;
                } else if ($postresponse->body->chunksreceived == 0) {
                    /* External Course Bank has some other data for this backup.
                     * But no chunks have been sent yet.
                     * Try to update it.
                     * Don't unset the fileid or the uuid fields.*/
                    list($result, $deletechunks, $highestiterator, $putresponse) = self::update_backup(
                            $wsmanager, $data, $backup, $sessionkey, $retries);
                    $desc = coursebank_logging::get_backup_details(
                        'event_backup_init_exists_nodata',
                        $data['uuid'],
                        $data['filename']
                    );
                    coursebank_logging::log_event(
                        $desc,
                        'transfer_started',
                        'Transfer started',
                        coursebank_logging::LOG_MODULE_COURSE_BANK,
                        $data['courseid'],
                        '',
                        $USER->id,
                        $data
                    );
                    if (!is_null($result)) {
                        return $result;
                    }
                } else {
                    /* Post_backup informs us that there's already data for this backup
                     * in External Course Bank.  And, that it's already started receiving
                     * chunks.
                     * We need to delete the chunks, then update the backup, then continue.*/
                    if (isset($postresponse->body->chunksreceived)) {
                        $desc = coursebank_logging::get_backup_details(
                                'event_backup_init_exists_data',
                                $data['uuid'],
                                $data['filename']
                        );
                        coursebank_logging::log_event(
                            $desc,
                            'transfer_started',
                            'Transfer started',
                            coursebank_logging::LOG_MODULE_COURSE_BANK,
                            $data['courseid'],
                            '',
                            $USER->id,
                            $data
                        );
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
                    $desc = coursebank_logging::get_backup_details(
                            'event_backup_init_interrupted',
                            $data['uuid'],
                            $data['filename']
                    );
                    coursebank_logging::log_event(
                        $desc,
                        'transfer_start_failed',
                        'Transfer start failed',
                        coursebank_logging::LOG_MODULE_COURSE_BANK,
                        $data['courseid'],
                        '',
                        $USER->id,
                        $data
                    );
                    return -1;
                }
                // Continue.
            } else {
                $backup->status = self::STATUS_ERROR;
                $DB->update_record('tool_coursebank', $backup);
                $desc = coursebank_logging::get_backup_details(
                        'event_backup_init_interrupted',
                        $data['uuid'],
                        $data['filename']
                );
                coursebank_logging::log_event(
                    $desc,
                    'transfer_start_failed',
                    'Transfer start failed',
                    coursebank_logging::LOG_MODULE_COURSE_BANK,
                    $data['courseid'],
                    '',
                    $USER->id,
                    $data
                );
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
            coursebank_logging::log_transfer_resumed($backup);
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
        // Log a transfer start event.
        $desc = coursebank_logging::get_backup_details(
                'event_backup_transfer_started',
                $backup->uniqueid,
                $backup->backupfilename
        );
        coursebank_logging::log_event(
                $desc,
                'transfer_started',
                'Transfer started',
                coursebank_logging::LOG_MODULE_COURSE_BANK,
                $backup->courseid,
                '',
                $USER->id,
                $backup
        );

        // Read the file in chunks, attempt to send them.
        while ($contents = fread($file, $chunksize)) {

            $data = array(
                'data'          => base64_encode($contents),
                'chunksize'     => $chunksize,
                'original_data' => $contents,
            );

            // Record time this current chunk was started to get sent.
            $backup->timechunksent = time();
            $DB->update_record('tool_coursebank', $backup);

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
                $desc = coursebank_logging::get_backup_details(
                        'event_backup_chunk_interrupted',
                        $backup->uniqueid,
                        $backup->backupfilename
                );
                coursebank_logging::log_event(
                        $desc,
                        'transfer_interrupted',
                        'Transfer interrupted',
                        coursebank_logging::LOG_MODULE_COURSE_BANK,
                        $backup->courseid,
                        '',
                        $USER->id,
                        $backup
                );
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
                //
                // IMPORTANT: in the event that Coursebank has completed the backup, but
                // we timed out or failed for some reason, we rely here on chunknumber
                // being reset to 0 so that initialise_backup will be called in the next run.
                // This should get a WS_HTTP_CONFLICT from Coursebank and finish the backup.
                $backup->chunknumber = 0;
                $backup->timechunkcompleted = 0;
                $DB->update_record('tool_coursebank', $backup);

                // Log a transfer interruption event.
                $desc = coursebank_logging::get_backup_details(
                        'event_backup_update_interrupted',
                        $backup->uniqueid,
                        $data['filename']
                );
                coursebank_logging::log_event(
                        $desc,
                        'transfer_interrupted',
                        'Transfer interrupted',
                        coursebank_logging::LOG_MODULE_COURSE_BANK,
                        $backup->courseid,
                        '',
                        $USER->id,
                        $backup
                );
                return self::SEND_ERROR;
            } else {
                $backup->status = self::STATUS_FINISHED;
                $backup->timecompleted = time();
                $DB->update_record('tool_coursebank', $backup);
                // Log transfer_completed event.
                $desc = coursebank_logging::get_backup_details(
                        'event_backup_transfer_completed',
                        $backup->uniqueid,
                        $backup->backupfilename
                );
                coursebank_logging::log_event(
                        $desc,
                        'transfer_completed',
                        'Transfer completed',
                        coursebank_logging::LOG_MODULE_COURSE_BANK,
                        $backup->courseid,
                        '',
                        $USER->id,
                        $backup
                );
            }
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
        $deletelocalbackup = get_config('tool_coursebank', 'deletelocalbackup');

        // We don't want to delete moodle backup file.
        if (empty($deletelocalbackup)) {
            return true;
        }

        if (self::last_automated_backup_succeed($backup)) {
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
        } else {
            // Log it.
            $skipstring = get_string(
                    'moodledeleteskip',
                    'tool_coursebank',
                    $backup->backupfilename
            );
            $event = 'backup_delete_skipped';
            coursebank_logging::log_event($skipstring, $event, 'Deleting backup');
        }

        return true;
    }
    /**
     * Checks if the last automated backup fore related course succeed.
     *
     * @param object $backup Course bank database record object
     *
     */
    public static function last_automated_backup_succeed($backup) {
        global $DB;

        $backupcourse = $DB->get_record('backup_courses', array('courseid' => $backup->courseid));

        if (empty($backupcourse)) {
            return true;
        }

        // The last backup is considered as successful when OK or SKIPPED.
        $lastbackupsuccess = ($backupcourse->laststatus == backup_cron_automated_helper::BACKUP_STATUS_SKIPPED ||
                                   $backupcourse->laststatus == backup_cron_automated_helper::BACKUP_STATUS_OK) && (
                                   $backupcourse->laststarttime > 0 && $backupcourse->lastendtime > 0);

        return $lastbackupsuccess;
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
     */
    public static function fetch_backups() {
        global $CFG, $DB, $USER;

        // Log cron_started event.
        $info = get_string('eventcronstarted', 'tool_coursebank') . '.';
        coursebank_logging::log_event(
                $info,
                'cron_started',
                get_string('eventcronstarted', 'tool_coursebank'),
                coursebank_logging::LOG_MODULE_COURSE_BANK,
                SITEID,
                '',
                $USER->id,
                array()
        );
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
        $params = array('statusnotstarted2' => self::STATUS_NOTSTARTED,
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
        $insertcount = 0;
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
                $cs->timecreated = time();
                foreach ($insertfields as $field) {
                    $cs->$field = $coursebackup->$field;
                }
                $DB->insert_record('tool_coursebank', $cs);
                $insertcount++;
            }
        }

        // Log transfer_queue populated event.
        $info = get_string(
                'event_transfer_queue_populated',
                'tool_coursebank',
                $insertcount
        );
        coursebank_logging::log_event(
                $info,
                'transfer_queue_populated',
                'Transfer queue populated',
                coursebank_logging::LOG_MODULE_COURSE_BANK,
                SITEID,
                '',
                $USER->id,
                array('queuecount' => $insertcount)
        );
        $recordset->close();

        // Get all backups that are pending transfer, attempt to transfer them.
        $sql = 'SELECT * FROM {tool_coursebank}
                        WHERE status IN (:notstarted, :inprogress, :error)
                        AND filetimecreated >= :maxbackuptime ORDER BY id';
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
                $localdelete = self::delete_moodle_backup($coursebackup);
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

        // Log cron_completed event.
        $info = get_string('eventcroncompleted', 'tool_coursebank') . '.';
        coursebank_logging::log_event(
                $info,
                'cron_completed',
                get_string('eventcroncompleted', 'tool_coursebank'),
                coursebank_logging::LOG_MODULE_COURSE_BANK,
                SITEID,
                '',
                $USER->id,
                array()
        );
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
 * Basic logging class
 *
 */
class coursebank_logging {
    const LOG_MODULE_COURSE_BANK = 'Coursebank';

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
     * @param array/stdClass $other Other data we may want to use
     * @return boolean
     */
    public static function log_event($info='', $eventname='coursebank_logging', $action='',
            $module=self::LOG_MODULE_COURSE_BANK, $courseid=SITEID, $url='', $userid=0, $other = array()) {
        global $USER, $CFG;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        $url = str_replace($CFG->wwwroot, "/", $url);

        // Cast $other to array in case we've been passed an object.
        $other = (array) $other;

        if (!tool_coursebank::legacy_logging()) {
            $otherdata = array_merge(
                array(
                    'courseid' => $courseid,
                    'module'   => $module,
                    'action'   => $action,
                    'url'      => $url,
                    'info'     => $info
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
        return false;
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
                } else if ($reader instanceof \core\log\sql_internal_table_reader) { // Moodle 2.9.
                    $logtable = $reader->get_internal_log_table_name();
                    break;
                }
            }
        }
        return $logtable;
    }

    /**
     * Build a language string for use in logging an event.
     *
     * Fetch the language string provided in $lang and insert backup details
     * derived from the provided UUID and filename.
     *
     * @param string $lang      Language string to insert the details into.
     * @param string $uuid      Coursebank unique ID.
     * @param string $filename  Moodle course backup filename.
     */
    public static function get_backup_details($lang, $uuid, $filename) {
        $data = (object) array('uuid' => $uuid, 'filename' => $filename);
        $backupdetails = get_string(
                'identify_backup',
                'tool_coursebank',
                $data
        );
        return get_string($lang, 'tool_coursebank', $backupdetails);
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
