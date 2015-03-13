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
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

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
     * @param array $data    Associative array of request data to send
     * @param int   $maxatt  Max number of attempts to make before failing
     *
     * @return bool          True if request was successful, false otherwise
     */
    function send($data, $maxatt = 5) {
        $postdata = json_encode($data);
        curl_setopt($this->curlhandle, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($this->curlhandle, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata))
        );
        //Make $maxatt number of attempts to send request
        for($attempt=0; $attempt < $maxatt; $attempt++) {
            $result = curl_exec($this->curlhandle);
            $response = curl_getinfo($this->curlhandle);
            $httpcode = $response['http_code'];
            if($httpcode == '202' || $httpcode == '200') {
               return true;
            }
        }
        return false;
    }

}
/**
 * Convenience function to handle sending a file along with the relevant
 * metatadata.
 *
 * @param object $backup    Course store database record object
 *
 */
function send_file($backup) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/admin/tool/coursestore/lib.php');

    // Get required config variables
    $urltarget = get_config('tool_coursestore', 'url');
    $conntimeout = get_config('tool_coursestore', 'conntimeout');
    $timeout = get_config('tool_coursestore', 'timeout');
    $maxatt = get_config('tool_coursestore', 'maxatt');

    // Initialise, check connection
    $ws_manager = new coursestore_ws_manager($urltarget, $conntimeout, $timeout);
    $check = array('operation' => 'check');
    if(!$ws_manager->send($check)) {
        //Connection check failed
        return false;
    }

    // Chunk size is set in kilobytes
    $chunksize = $backup->chunksize * 1000;

    $backup->operation = 'transfer';
    $backup->status = STATUS_INPROGRESS;

    // Open input file
    $file = fopen($backup->filepath, 'r');

    // Set offset based on chunk number
    if($backup->chunknumber != 0) {
        fseek($file, $backup->chunknumber * $chunksize);
    }

    // Read the file in chunks, attempt to send them
    while($contents = fread($file, $chunksize)) {
        $backup->data = base64_encode($contents);
        $backup->chunksum = md5($backup->data);
        $backup->timechunksent = time();

        if($ws_manager->send($backup, 5)) {
            $backup->timechunkcompleted = time();
            $backup->chunknumber++;
            if($backup->chunknumber == $backup->totalchunks) {
                $backup->status = STATUS_FINISHED;
            }
            $DB->update_record('tool_coursestore', $backup);
        }
        else {
            $backup->status = STATUS_ERROR;
            $DB->update_record('tool_coursestore', $backup);
            return false;
        }
    }

    $ws_manager->close();
    fclose($file);
    return true;
}
