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
 * PHPUnit data generator tests
 *
 * @package   tool_coursebank
 * @copyright 2015 onwards Catalyst IT
 * @author    Adam Riddell <adamr@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/lib.php');

class coursebank_ws_manager_tester extends coursebank_ws_manager {
    private $testresponse;

    /**
     * Override the constructor so that it doesn't initialise a curl handler.
     */
    public function __construct() {
        $this->curlhandle = null;
    }
    /**
     * Override the close function so that it doesn't try to close a curl
     * handler when called by functions.
     */
    public function close() {
    }
    /**
     * Set the method to be tested. Dummy data generated will vary depending
     * on which request method is to be tested.
     *
     * @param mixed $method  Test response.
     */
    public function set_response($testresponse) {
        $this->testresponse = $testresponse;
    }
    /**
     * Override the send function and generate dummy data based on the contents
     * $testmethod to test the various function which use send, without having
     * actually make web service calls.
     *
     * @param null $resource
     * @param null $data
     * @param null $method
     * @param null $auth
     * @param null $retries
     */
    protected function send($resource=null, $data=null, $method=null, $auth=null, $retries=null, $timeoutsecs=30) {
        return $this->testresponse;
    }
}
class tool_coursebank_testcase extends advanced_testcase {
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_get_test() {
        $this->resetAfterTest(true);
        $wsman = new coursebank_ws_manager_tester();

        // Test successful response.
        $info = array(
            'http_code' => coursebank_ws_manager_tester::WS_HTTP_OK
        );
        $body = new stdClass();
        $response = new coursebank_http_response($body, $info);

        $wsman->set_response($response);
        $this->assertEquals($response, $wsman->get_test('sesskey'));

        // Test failure response.
        $info = array(
            'http_code' => coursebank_ws_manager_tester::WS_HTTP_BAD_REQUEST
        );
        $body = new stdClass();
        $body->error = 400;
        $body->err_desc = 'Error message';

        $response = new coursebank_http_response($body, $info);

        $wsman->set_response($response);
        $this->assertEquals($response, $wsman->get_test('sesskey'));

    }
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_post_session() {
        $this->resetAfterTest(true);
        $wsman = new coursebank_ws_manager_tester();
        $response = new coursebank_http_response();

        // Test false response.
        $wsman->set_response($response);
        $this->assertFalse($wsman->post_session('hash'));

        // Test successful response.
        $info = array(
            'http_code' => coursebank_ws_manager_tester::WS_HTTP_CREATED
        );
        $body = new stdClass();
        $body->sesskey = 'sesskey';
        $response = new coursebank_http_response($body, $info);
        $wsman->set_response($response);
        $this->assertEquals(true, $wsman->post_session('hash'));
        // Test that sess key has been saved properly.
        $sesskeylocal = get_config('tool_coursebank', 'sessionkey');
        $this->assertEquals('sesskey', $sesskeylocal);

        // Test unauthorized response.
        $info = array(
            'http_code' => coursebank_ws_manager_tester::WS_HTTP_UNAUTHORIZED
        );
        $expected = new coursebank_http_response($body, $info);
        $wsman->set_response($expected);
        $this->assertFalse($wsman->post_session('hash'));
    }
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_get_backup() {
    }

    // Return all data needed for a successful POST /backup request / response.

    public function make_post_backup_data() {
        $info = array(
            'http_code' => coursebank_ws_manager_tester::WS_HTTP_CREATED
        );
        $response = new stdClass();
        $request = array(
            'fileid' => 1,
            'filename' => 'test.mbz',
            'filehash' => 'somehash',
            'filesize' => 20000,
            'courseid' => 4,
            'uuid' => '139ae275-4b4d-4150-8be1-f588bdb85c1f',
            'chunksize' => 100,
            'totalchunks' => 40,
            'coursename' => 'test course',
            'categoryid' => 2,
            'categoryname' => 'test category',
            'startdate' => '2015-01-01 12:00:00'
        );
        $response->hash = md5($request['fileid'] . ',' . $request['uuid'] .
                ',' . $request['filename'] . ',' . $request['filesize']);

        return array($request, $response, $info);
    }

    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_post_backup() {
        $this->resetAfterTest(true);
        $wsman = new coursebank_ws_manager_tester();

        list ($req, $res, $info) = $this->make_post_backup_data();
        $wsman->set_response(new coursebank_http_response($res, $info));
        $result = $wsman->post_backup($req, 'sesskey', 0);
        $this->assertEquals($req['fileid'], $result);

        // Test hash mismatch response.
        list ($req, $res, $info) = $this->make_post_backup_data();
        $req['filename'] = 'test-different-hash.mbz';
        $result = $wsman->post_backup($req, 'sesskey', 0);
        $response = new coursebank_http_response($res, $info);
        $this->assertEquals($response, $result);

        // Test failed request.
        list ($req, $res, $info) = $this->make_post_backup_data();
        $response = new coursebank_http_response();
        $wsman->set_response($response);
        $result = $wsman->post_backup($req, 'sesskey', 0);
        $this->assertEquals($response, $result);

        // Test response if backup already exists and Coursebank says backup is NOT completed.
        list ($req, $res, $info) = $this->make_post_backup_data();
        $info['http_code'] = coursebank_ws_manager_tester::WS_HTTP_CONFLICT;
        $res->is_completed = false;
        $res->is_inprogress = true;
        foreach ($req as $field => $value) {
            $res->$field = $value;
        }
        $res->coursestartdate = $req['startdate'];
        $response = new coursebank_http_response($res, $info);
        $wsman->set_response($response);
        $result = $wsman->post_backup($req, 'sesskey', 0);
        $this->assertEquals($req['fileid'], $result, "We should continue sending the backup.");

        // Test response if backup already exists and Coursebank says backup IS completed.
        //
        // This might happen if
        // - Moodle times out doing PUT /backupcomplete (after successfully PUTting all chunks)
        // - but CourseBank successfully processes the PUT /backupcomplete
        // 
        // NOTE: this plugin will set chunknumber to 0 in
        // {tool_coursebank} table as a result of /backupcomplete
        // failing.  As a result, when we retry (on the next cron run)
        // this will trigger initialise_backup() which will call POST
        // /backup again.  Coursebank will then return a
        // WS_HTTP_CONFLICT.

        list ($req, $res, $info) = $this->make_post_backup_data();
        $info['http_code'] = coursebank_ws_manager_tester::WS_HTTP_CONFLICT;
        $res->is_completed = true; // CourseBank says the backup is completed (assembled etc)
        foreach ($req as $field => $value) {
            $res->$field = $value;
        }
        $res->coursestartdate = $req['startdate'];
        $response = new coursebank_http_response($res, $info);
        $wsman->set_response($response);
        $result = $wsman->post_backup($req, 'sesskey', 0);
        $this->assertEquals($response, $result, "We should get back response NOT file id and NOT continue the backup.");

        // Test unexpected HTTP response code.

        list ($req, $res, $info) = $this->make_post_backup_data();
        $info['http_code'] = coursebank_ws_manager_tester::WS_HTTP_UNAUTHORIZED;
        $response = new coursebank_http_response($res, $info);
        $wsman->set_response($response);
        $result = $wsman->post_backup($req, 'sesskey', 0);
        $this->assertEquals($response, $result, "We should get back response NOT file id");


    }
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_put_backup_complete() {
    }
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_get_chunk() {
    }
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_put_chunk() {
        $this->resetAfterTest(true);
        $wsman = new coursebank_ws_manager_tester();

        // Test failed request.
        $response = new coursebank_http_response();
        $wsman->set_response($response);
        $data = array('original_data' => 'data');
        $result = $wsman->put_chunk($data, 1, 2, 'sesskey', 0);
        $this->assertEquals($response, $result);

        // Test successful request.
        $body = new stdClass();
        $body->chunkhash = md5($data['original_data']);
        $info = array(
            'http_code' => coursebank_ws_manager_tester::WS_HTTP_OK
        );
        $response = new coursebank_http_response($body, $info);
        $wsman->set_response($response);
        $result = $wsman->put_chunk($data, 1, 2, 'sesskey', 0);
        $this->assertEquals(true, $result);

        // Test md5sum mismatch.
        $body->chunkhash = md5('hashmismatch');
        $response = new coursebank_http_response($body, $info);
        $wsman->set_response($response);
        $result = $wsman->put_chunk($data, 1, 2, 'sesskey', 0);
        $this->assertEquals($response, $result);

        // Test unexpected HTTP response code.
        $info = array('http_code' => coursebank_ws_manager_tester::WS_HTTP_UNAUTHORIZED);
        $response = new coursebank_http_response($body, $info);
        $wsman->set_response($response);
        $body->chunkhash = md5($data['original_data']);
        $result = $wsman->put_chunk($data, 1, 2, 'sesskey', 0);
        $this->assertEquals($response, $result);
    }
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_log_response() {
        global $CFG;

        $this->resetAfterTest(true);

        // Normal HTTP response.
        $body1 = array(
            'hash' => 'd41d8cd98f00b204e9800998ecf8427e',
            'size' => 0,
            'utc_time' => 1437620443.2718
        );
        $info1 = array(
            'url' => 'HTTP://coursebank.local/test',
            'content_type' => 'application/json',
            'http_code' => 200
        );
        $request1 = array(
            10036 => 'GET',
            10015 => '{"data":"DATA"}',
            10023 => array (
                0 => 'Accept: application/json',
                1 => 'Content-Type: application/json',
                2 => 'Content-Length: 11',
                3 => 'sesskey: GV8h1M7YoM'
                     ),
            10002 => 'coursebank.local/test'
        );
        $normal = new coursebank_http_response($body1, $info1, $request1);

        // Chunk PUT HTTP response.
        $info2 = array(
            'url' => 'HTTP://coursebank.local/chunks/' .
                    '13811cb2-4512-417e-b927-ae2d2ad2c9eb/25',
            'content_type' => 'application/json',
            'http_code' => 200
        );
        $request2 = array(
            10036 => 'PUT',
            10015 => '{"data":"DATA"}',
            10023 => array (
                0 => 'Accept: application/json',
                1 => 'Content-Type: application/json',
                2 => 'Content-Length: 11',
                3 => 'sesskey: GV8h1M7YoM'
                     ),
            10002 => 'coursebank.local/chunks/' .
                    '13811cb2-4512-417e-b927-ae2d2ad2c9eb/25',
        );

        $normalchunk = new coursebank_http_response($body1, $info2, $request2);

        // Error HTTP response.
        $info3 = array(
            'url' => 'HTTP://coursebank.local/test',
            'content_type' => 'application/json',
            'http_code' => 500
        );

        $error = new coursebank_http_response($body1, $info3, $request1);

        // No HTTP response.
        $noresponse = new coursebank_http_response(false, false, $request1);

        // Debug levels mapped to expected responses.
        //
        // Each response array includes boolean values corresponding to:
        //     - Successful chunk HTTP responses
        //     - Successful normal HTTP responses
        //     - Error HTTP responses
        //
        // log_response should log any type of response for which the value
        // mapped in the response array is true.
        //
        // For example: if the debug level is DEBUG_NORMAL, log_response
        // should log normal HTTP responses and error responses.
        $debugmap = array(
            DEBUG_NONE => array(false, false, true),
            DEBUG_MINIMAL => array(false, false, true),
            DEBUG_NORMAL => array(false, true, true),
            DEBUG_DEVELOPER => array(true, true, true),
            DEBUG_ALL => array(true, true, true)
        );

        foreach ($debugmap as $level => $output) {
            $CFG->debug = $level;
            // Test normal HTTP chunk response.
            $this->assertequals($output[0], $normalchunk->log_response());

            // Test normal http response.
            $this->assertequals($output[1], $normal->log_response());

            // Test http error response.
            $this->assertequals($output[2], $error->log_response());

            // Test response time-out.
            $this->assertequals($output[2], $noresponse->log_response());
        }
    }
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_generate_event_data() {
        global $CFG;

        $this->resetAfterTest(true);

        // Normal HTTP response.
        $body1 = array(
            'hash' => 'd41d8cd98f00b204e9800998ecf8427e',
            'size' => 0,
            'utc_time' => 1437620443.2718
        );
        $info1 = array(
            'url' => 'HTTP://coursebank.local/test',
            'content_type' => 'application/json',
            'http_code' => 200
        );
        $request1 = array(
            10036 => 'GET',
            10015 => '{"data":"DATA"}',
            10023 => array (
                0 => 'Accept: application/json',
                1 => 'Content-Type: application/json',
                2 => 'Content-Length: 11',
                3 => 'sesskey: GV8h1M7YoM'
                     ),
            10002 => 'coursebank.local/test'
        );
        $normal = new coursebank_http_response($body1, $info1, $request1);
        $eventdata = $normal->generate_event_data();
        $this->assertInternalType('array', $eventdata);
        $this->assertArrayHasKey('other', $eventdata);
        $this->assertArrayHasKey('context', $eventdata);
        $this->assertInstanceOf('context_system', $eventdata['context']);

        // Chunk PUT HTTP response.
        $info2 = array(
            'url' => 'HTTP://coursebank.local/chunks/' .
                    '13811cb2-4512-417e-b927-ae2d2ad2c9eb/25',
            'content_type' => 'application/json',
            'http_code' => 200
        );
        $request2 = array(
            10036 => 'PUT',
            10015 => '{"data":"DATA"}',
            10023 => array (
                0 => 'Accept: application/json',
                1 => 'Content-Type: application/json',
                2 => 'Content-Length: 11',
                3 => 'sesskey: GV8h1M7YoM'
                     ),
            10002 => 'coursebank.local/chunks/' .
                    '13811cb2-4512-417e-b927-ae2d2ad2c9eb/25',
        );

        $normalchunk = new coursebank_http_response($body1, $info2, $request2);
        $eventdata = $normalchunk->generate_event_data();
        $this->assertInternalType('array', $eventdata);
        $this->assertArrayHasKey('other', $eventdata);
        $this->assertArrayHasKey('context', $eventdata);
        $this->assertInstanceOf('context_system', $eventdata['context']);

        // Error HTTP response.
        $info3 = array(
            'url' => 'HTTP://coursebank.local/test',
            'content_type' => 'application/json',
            'http_code' => 500
        );

        $error = new coursebank_http_response($body1, $info3, $request1);
        $eventdata = $error->generate_event_data();
        $this->assertInternalType('array', $eventdata);
        $this->assertArrayHasKey('other', $eventdata);
        $this->assertArrayHasKey('context', $eventdata);
        $this->assertInstanceOf('context_system', $eventdata['context']);

        // No HTTP response.
        $noresponse = new coursebank_http_response(false, false, $request1);
        $eventdata = $noresponse->generate_event_data();
        $this->assertInternalType('array', $eventdata);
        $this->assertArrayHasKey('other', $eventdata);
        $this->assertArrayHasKey('context', $eventdata);
        $this->assertInstanceOf('context_system', $eventdata['context']);
    }
    /**
     * @group tool_coursebank
     */
    public function test_tool_coursebank_log_event() {
        global $CFG, $USER, $DB;

        $this->resetAfterTest(true);

        // Set up test logging data.
        $info = 'Event description';
        $eventname = 'coursebank_logging';
        $action = 'Event action';
        $module = coursebank_logging::LOG_MODULE_COURSE_BANK;
        $courseid = SITEID;
        $url = 'some.kind/of/url';
        $userid = $USER->id;
        $other = array(
            'somedata' => 'other data',
            'float'    => 2.0,
            'bla'      => 'whatever'
        );

        // Test modern event-based logging.
        if ((float) $CFG->version >= 2014051200) {
            $eventsink = $this->redirectEvents();
            $countbefore = $eventsink->count();
            coursebank_logging::log_event(
                    $info,
                    $eventname,
                    $action,
                    $module,
                    $courseid,
                    $url,
                    $userid,
                    $other
            );
            $logentries = $DB->get_records('logstore_standard_log');
            $this->assertEquals($countbefore + 1, $eventsink->count());
            $events = $eventsink->get_events();
            $mostrecentevent = $events[count($events) - 1];
            $this->assertEquals($info, $mostrecentevent->get_description());
            $this->assertEquals($url, $mostrecentevent->get_url()->out());
        }
        // TODO: Add legacy add_to_log testing.
    }
    /**
     * @group tool_coursebank
     */
    public function test_get_statuses() {
        $statuses = tool_coursebank::get_statuses();
        $this->assertCount(6, $statuses);
        $this->assertEquals(109, array_sum(array_flip($statuses)));
    }
    /**
     * @group tool_coursebank
     */
    public function test_get_noaction_statuses() {
        $statuses = tool_coursebank::get_noaction_statuses();
        $this->assertCount(3, $statuses);
        $this->assertEquals(7, array_sum($statuses));
    }
    /**
     * @group tool_coursebank
     */
    public function test_get_canstop_statuses() {
        $statuses = tool_coursebank::get_canstop_statuses();
        $this->assertCount(2, $statuses);
        $this->assertEquals(99, array_sum($statuses));
    }
    /**
     * @group tool_coursebank
     */
    public function test_get_stopped_statuses() {
        $statuses = tool_coursebank::get_stopped_statuses();
        $this->assertCount(1, $statuses);
        $this->assertEquals(3, array_sum($statuses));
    }
    /**
     * @group tool_coursebank
     */
    public function test_delete_moodle_backup() {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        $backup = new stdClass();
        $backup->courseid = $course->id;
        $backup->backupfilename = 'test.mbz';

        set_config('deletelocalbackup', false, 'tool_coursebank');
        $result = tool_coursebank::delete_moodle_backup($backup);
        $this->assertTrue($result);

        set_config('deletelocalbackup', true, 'tool_coursebank');
        $result = tool_coursebank::delete_moodle_backup($backup);
        $this->assertTrue($result);
    }

    /**
     * @group tool_coursebank
     */
    public function test_cron_lock_get_set_clear() {
        $this->resetAfterTest(true);
        $lockname = 'tool_coursebank_cronlock';
        $now = time();
        set_config($lockname, $now, 'tool_coursebank');

        // Get
        $this->assertEquals($now, tool_coursebank_get_cron_lock(), "We can get the lock.");

        // Set
        $this->assertTrue(tool_coursebank_set_cron_lock($now+1), "We can set a new lock.");
        $this->assertEquals($now+1, get_config('tool_coursebank', $lockname), "We should get the new lock.");

        // Clearing the lock:
        $this->assertTrue(tool_coursebank_clear_cron_lock());
        $this->assertEquals(null, get_config('tool_coursebank', $lockname), "Lock should be cleared.");

        $this->assertTrue(tool_coursebank_clear_cron_lock());
        $this->assertTrue(tool_coursebank_clear_cron_lock(), "Clearing more than once should be fine.");

        // Bad lock:
        $this->assertFalse(tool_coursebank_set_cron_lock('foo'), "Should fail to set lock if not int.");
        $this->assertEquals(null, get_config('tool_coursebank', $lockname), "Lock should not be set.");

        // Default behaviour
        set_config($lockname, 1, 'tool_coursebank');
        $this->assertTrue((tool_coursebank_set_cron_lock() - $now) < 3, "Default lock sets time to now.");
    }

    /**
     * @group tool_coursebank
     */
    public function test_cron_lock_can_be_cleared() {
        $lockname = 'tool_coursebank_cronlock';
        $this->resetAfterTest(true);
        $now = time();
        $TIMEOUT = tool_coursebank::CRON_LOCK_TIMEOUT;

        // Default behaviour: should clear lock after a day:
        tool_coursebank_set_cron_lock($now - ($TIMEOUT + 1));
        $this->assertEquals(true, tool_coursebank_cron_lock_can_be_cleared());
        tool_coursebank_set_cron_lock($now - $TIMEOUT);
        $this->assertEquals(true, tool_coursebank_cron_lock_can_be_cleared());
        tool_coursebank_set_cron_lock($now - ($TIMEOUT - 1));
        $this->assertEquals(false, tool_coursebank_cron_lock_can_be_cleared(), "Lock should be deemed not clearable.");

        unset_config($lockname, 'tool_coursebank');
        $this->assertEquals(true, tool_coursebank_cron_lock_can_be_cleared(), "Return true if lock already cleared.");

        // With parameter:
        set_config($lockname, $now - 1, 'tool_coursebank');
        $this->assertEquals(true, tool_coursebank_cron_lock_can_be_cleared(1), "Lock should be clearable.");
    }

}
