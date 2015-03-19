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
     * Test that a connection to the configured web service consumer can be
     * made successfully.
     *
     * @param coursestore_ws_manager $wsman  Web service manager object
     * @return bool                          True for success, false otherwise
     */
    public static function check_connection(coursestore_ws_manager $wsman) {

        $check = array('operation' => 'check');
        $checkresult = $wsman->send($check);
        if($checkresult['http_code'] == 200) {
            return true;
        }
        return false;
    }
    /**
     * Test the speed of a transfer of $testsize kilobytes. A total
     * of $count HTTP requests will be sent. For each request, the
     * function will make $maxhttp attempts.
     *
     * @param coursestore_ws_manager $wsman  Web service manager object
     * @param int                 $testsize  Approximate size of test transfer
     *                                       in kB
     * @param int                    $count  Number of HTTP requests to make
     * @param int                  $maxhttp  Maximum number of http requests to
     *                                       try
     *
     * @return int                  Approximate connection speed in kbps
     */
    public static function check_connection_speed(coursestore_ws_manager $wsman,
            $testsize, $count, $maxhttp) {

        $check = array('operation' => 'speedtest');
        $check['data'] = str_pad('', $testsize*1000, '0');
        $check['checksum'] = md5($check['data']);
        $start = microtime(true);

        // Make $count requests with the dummy data
        for($i=0; $i<$count; $i++) {
            for($j=0; $j<$maxhttp; $j++) {
                $response = $wsman->send($check);
                if($response['http_code'] == 200) {
                    break;
                }
            }
            // If $maxhttps unsuccessful attempts have been made
            if($response['http_code'] != 200) {
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

        require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

        // Construct the full path of the backup file
        $backup->filepath = $CFG->dataroot . '/filedir/' .
                substr($backup->contenthash, 0, 2) . '/' .
                substr($backup->contenthash, 2,2) .'/' . $backup->contenthash;

        if(!is_readable($backup->filepath)) {
            return false;
        }

        // Get required config variables
        $urltarget = get_config('tool_coursestore', 'url');
        $conntimeout = get_config('tool_coursestore', 'conntimeout');
        $timeout = get_config('tool_coursestore', 'timeout');
        $maxhttprequests = get_config('tool_coursestore', 'maxhttprequests');

        // Initialise, check connection
        $ws_manager = new coursestore_ws_manager($urltarget, $conntimeout, $timeout);
        if(!tool_coursestore::check_connection($ws_manager)) {
            $backup->status = tool_coursestore::STATUS_ERROR;
            $DB->update_record('tool_coursestore', $backup);
            $ws_manager-close();
            return false;
        }

        // Chunk size is set in kilobytes
        $chunksize = $backup->chunksize * 1000;

        $backup->operation = 'transfer';
        if($backup->status == tool_coursestore::STATUS_NOTSTARTED) {
            $backup->status = tool_coursestore::STATUS_INPROGRESS;
        }

        // Open input file
        $file = fopen($backup->filepath, 'rb');

        // Set offset based on chunk number
        if($backup->chunknumber != 0) {
            fseek($file, $backup->chunknumber * $chunksize);
        }

        // Read the file in chunks, attempt to send them
        while($contents = fread($file, $chunksize)) {
            $backup->data = base64_encode($contents);
            $backup->chunksum = md5($backup->data);
            $backup->timechunksent = time();

            $result = $ws_manager->send($backup, $maxhttprequests);
            if($result['http_code'] == '200') {
                $backup->timechunkcompleted = time();
                $backup->chunknumber++;
                if($backup->status == tool_coursestore::STATUS_ERROR) {
                    $backup->chunkretries = 0;
                    $backup->status = tool_coursestore::STATUS_INPROGRESS;
                }
                if($backup->chunknumber == $backup->totalchunks) {
                    $backup->status = tool_coursestore::STATUS_FINISHED;
                }
                $DB->update_record('tool_coursestore', $backup);
            }
            else {
                if($backup->status == tool_coursestore::STATUS_ERROR) {
                    $backup->chunkretries++;
                }
                else {
                    $backup->status = tool_coursestore::STATUS_ERROR;
                }
                $DB->update_record('tool_coursestore', $backup);
                return false;
            }
        }

        $ws_manager->close();
        fclose($file);
        return true;
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

    /**
     * @param string  $url            Target URL
     * @param int     $conntimeout    Connection time out (seconds)
     * @param int     $timeout        Request time out (seconds)
     */
    function __construct($url, $conntimeout, $timeout) {
        $curlopts = array(
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $conntimeout,
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
     * Send a the provided data in JSON encoding as a POST request
     *
     * @param array $data             Associative array of request data to send
     * @param int   $maxhttprequests  Max number of attempts to make before
     *                                failing
     *
     * @return bool                   Return true if successful
     */
    function send($data, $maxhttprequests = 5) {
        $postdata = json_encode($data);
        curl_setopt($this->curlhandle, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($this->curlhandle, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata))
        );
        //Make $maxatt number of attempts to send request
        for($attempt=0; $attempt < $maxhttprequests; $attempt++) {
            $result = curl_exec($this->curlhandle);
            $response = curl_getinfo($this->curlhandle);
            $httpcode = $response['http_code'];
            if($httpcode == '200') {
               return $response;
            }
        }
        return $response;
    }

}
