<?php

/**
 * PHPUnit data generator tests
 *
 * @package   tool_coursestore
 * @copyright 2015 onwards Catalyst IT
 * @author    Adam Riddell <adamr@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

class coursestore_ws_manager_tester extends coursestore_ws_manager {
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
    protected function send($resource=null, $data=null, $method=null, $auth=null, $retries=null) {
        return $this->testresponse;
    }
    protected function test_get_test() {
    }
}
class tool_coursestore_testcase extends advanced_testcase {
    /**
     * @group tool_coursestore
     */
    public function test_tool_coursestore_get_test() {
        $this->resetAfterTest(true);
        $wsman = new coursestore_ws_manager_tester();

        // Test false response
        $wsman->set_response(false);
        $this->assertEquals(false, $wsman->get_test('sesskey'));

        // Test successful response
        $wsman->set_response(true);
        $this->assertEquals(true, $wsman->get_test('sesskey'));

        //TODO: Refactor connection checks into get_test, add more tests.

    }
    /**
     * @group tool_coursestore
     */
    public function test_tool_coursestore_post_session() {
        $this->resetAfterTest(true);
        $wsman = new coursestore_ws_manager_tester();
        $response = new coursestore_http_response();

        // Test false response.
        $wsman->set_response($response);
        $this->assertEquals($response, $wsman->post_session('hash'));

        // Test successful response.
        $info = array(
            'http_code' => coursestore_ws_manager_tester::WS_HTTP_CREATED
        );
        $body = new stdClass();
        $body->sesskey = 'sesskey';
        $response = new coursestore_http_response($body, $info);
        $wsman->set_response($response);
        $this->assertEquals(true, $wsman->post_session('hash'));
        // Test that sess key has been saved properly
        $sesskeylocal = get_config('tool_coursestore', 'sessionkey');
        $this->assertEquals('sesskey', $sesskeylocal);

        // Test unauthorized response.
        $info = array(
            'http_code' => coursestore_ws_manager_tester::WS_HTTP_UNAUTHORIZED
        );
        $expected = new coursestore_http_response($body, $info);
        $wsman->set_response($expected);
        $this->assertEquals($expected, $wsman->post_session('hash'));
    }
    /**
     * @group tool_coursestore
     */
    public function test_tool_coursestore_get_backup() {
    }
    /**
     * @group tool_coursestore
     */
    public function test_tool_coursestore_post_backup() {
        $this->resetAfterTest(true);
        $wsman = new coursestore_ws_manager_tester();

        // Test successful response.
        $info = array(
            'http_code' => coursestore_ws_manager_tester::WS_HTTP_CREATED
        );
        $body = new stdClass();
        $testdata = array(
            'fileid' => 1,
            'filename' => 'test.mbz',
            'filesize' => 20000
        );
        $body->hash = md5($testdata['fileid'] . ',' . $testdata['filename'] . ',' .
                $testdata['filesize']);

        $response = new coursestore_http_response($body, $info);
        $wsman->set_response($response);
        $result = $wsman->post_backup($testdata, 'sesskey', 0);
        $this->assertEquals($testdata['fileid'], $result);

        // Test hash mismatch response.
        $testdata['filename'] = 'test-different-hash.mbz';
        $result = $wsman->post_backup($testdata, 'sesskey', 0);
        $this->assertEquals($response, $result);

        // Test failed request.
        $response = new coursestore_http_response();
        $wsman->set_response($response);
        $result = $wsman->post_backup($testdata, 'sesskey', 0);
        $this->assertEquals($response, $result);

        // Test response if backup already exists.
        $info['http_code'] = coursestore_ws_manager_tester::WS_HTTP_CONFLICT;
        $response = new coursestore_http_response($body, $info);
        $wsman->set_response($response);
        $result = $wsman->post_backup($testdata, 'sesskey', 0);
        $this->assertEquals($testdata['fileid'], $result);

        // Test unexpected HTTP response code.
        $info['http_code'] = coursestore_ws_manager_tester::WS_HTTP_UNAUTHORIZED;
        $response = new coursestore_http_response($body, $info);
        $wsman->set_response($response);
        $result = $wsman->post_backup($testdata, 'sesskey', 0);
        $this->assertEquals($response, $result);
    }
    /**
     * @group tool_coursestore
     */
    public function test_tool_coursestore_put_backup_complete() {
    }
    /**
     * @group tool_coursestore
     */
    public function test_tool_coursestore_get_chunk() {
    }
    /**
     * @group tool_coursestore
     */
    public function test_tool_coursestore_put_chunk() {
        $this->resetAfterTest(true);
        $wsman = new coursestore_ws_manager_tester();

        // Test failed request.
        $response = new coursestore_http_response();
        $wsman->set_response($response);
        $data = array('original_data' => 'data');
        $result = $wsman->put_chunk($data, 1, 2, 'sesskey', 0);
        $this->assertEquals($response, $result);

        // Test successful request.
        $body = new stdClass();
        $body->chunkhash = md5($data['original_data']);
        $info = array(
            'http_code' => coursestore_ws_manager_tester::WS_HTTP_OK
        );
        $response = new coursestore_http_response($body, $info);
        $wsman->set_response($response);
        $result = $wsman->put_chunk($data, 1, 2, 'sesskey', 0);
        $this->assertEquals(true, $result);

        // Test md5sum mismatch.
        $body->chunkhash = md5('hashmismatch');
        $response = new coursestore_http_response($body, $info);
        $wsman->set_response($response);
        $result = $wsman->put_chunk($data, 1, 2, 'sesskey', 0);
        $this->assertEquals($response, $result);

        // Test unexpected HTTP response code.
        $info = array('http_code' => coursestore_ws_manager_tester::WS_HTTP_UNAUTHORIZED);
        $response = new coursestore_http_response($body, $info);
        $wsman->set_response($response);
        $body->chunkhash = md5($data['original_data']);
        $result = $wsman->put_chunk($data, 1, 2, 'sesskey', 0);
        $this->assertEquals($response, $result);
    }
    /**
     * @group tool_coursestore
     */
    public function test_get_statuses() {
        $statuses = tool_coursestore::get_statuses();
        $this->assertCount(6, $statuses);
        $this->assertEquals(109, array_sum(array_flip($statuses)));
    }
    /**
     * @group tool_coursestore
     */
    public function test_get_noaction_statuses() {
        $statuses = tool_coursestore::get_statuses();
        $this->assertCount(3, $statuses);
        $this->assertEquals(7, array_sum($statuses));
    }
    /**
     * @group tool_coursestore
     */
    public function test_get_canstop_statuses() {
        $statuses = tool_coursestore::get_statuses();
        $this->assertCount(2, $statuses);
        $this->assertEquals(99, array_sum(array_flip($statuses)));
    }
    /**
     * @group tool_coursestore
     */
    public function test_get_stopped_statuses() {
        $statuses = tool_coursestore::get_statuses();
        $this->assertCount(1, $statuses);
        $this->assertEquals(3, array_sum(array_flip($statuses)));
    }
}
