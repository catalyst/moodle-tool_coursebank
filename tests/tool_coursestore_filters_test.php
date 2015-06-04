<?php
/**
 * Tests for filters and sorting
 *
 * @package   tool_coursebank
 * @copyright 2015 onwards Catalyst IT
 * @author    Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/filters/lib.php');

class tool_coursebank_filters_testcase extends advanced_testcase {

    protected function setUp() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    public function test_filter_date() {
        $data = array();
        $filter = new coursebank_filter_date('filetimemodified', get_string('filetimemodified', 'tool_coursebank'), 1, 'filetimemodified');

        $data['after'] = 1111;
        $data['before'] = 0;
        $params = $filter->get_param_filter($data);
        $this->assertCount(2, $params);
        $expected = array('operator' => '>=', 'value' => '1111');
        $this->assertEquals($expected, $params);

        $data['after'] = 0;
        $data['before'] = 1111;
        $params = $filter->get_param_filter($data);
        $this->assertCount(2, $params);
        $expected = array('operator' => '<=', 'value' => '1111');
        $this->assertEquals($expected, $params);

        $data['after'] = 'Some error';
        $data['before'] = 'Some error';
        $params = $filter->get_param_filter($data);
        $this->assertEmpty($params);
    }

    public function test_filter_filesize() {
        $data = array();
        $filter = new coursebank_filter_filesize('filesize', get_string('filesize', 'tool_coursebank'), 1, 'filesize');

        $operators = $filter->getoperators();
        $this->assertCount(2, $operators);

        $sizes = $filter->get_size();
        $this->assertCount(4, $sizes);

        foreach ($sizes as $scale => $stringname) {
            $result = $filter->value_to_bytes($scale, 1024);
            $this->assertEquals(1024 * pow(1024, $scale), $result);
        }

        $data['operator'] = 0;
        $data['scale'] = 0;
        $data['value'] = 1024;
        $sqlparams = $filter->get_sql_filter($data);
        $params = $filter->get_param_filter($data);
        $expected = array('operator' => '>', 'value' => 1024);
        $this->assertEquals($expected, $params);
        $this->assertEquals('filesize > 1024', $sqlparams[0]);

        $data['operator'] = 1;
        $data['scale'] = 1;
        $data['value'] = 1024;
        $sqlparams = $filter->get_sql_filter($data);
        $params = $filter->get_param_filter($data);
        $expected = array('operator' => '<', 'value' => 1048576);
        $this->assertEquals($expected, $params);
        $this->assertEquals('filesize < 1048576', $sqlparams[0]);

        $data['operator'] = 0;
        $data['scale'] = 3;
        $data['value'] = 1024;
        $sqlparams = $filter->get_sql_filter($data);
        $params = $filter->get_param_filter($data);
        $expected = array('operator' => '>', 'value' => 1099511627776);
        $this->assertEquals($expected, $params);
        $this->assertEquals('filesize > 1099511627776', $sqlparams[0]);
    }

    public function test_filter_select() {
        $data = array();

        $filtering = new coursebank_filtering('queue');
        $statuses = $filtering->get_status_choices();
        $this->assertCount(4, $statuses);

        $filtering = new coursebank_filtering('download');
        $statuses = $filtering->get_status_choices();
        $this->assertCount(6, $statuses);

        $filter = new coursebank_filter_select('status', get_string('status', 'tool_coursebank'), 1, 'status', $statuses);

        $value = 1111;
        $expected = array(
            0 => '',
            1 => array('operator' => '=',  'value' => $value),
            2 => array('operator' => '<>', 'value' => $value),
        );

        for ($i=1; $i<=2; $i++) {
            $data['operator'] = $i;
            $data['value'] = $value;
            $params = $filter->get_param_filter($data);
            $this->assertCount(2, $params);
            $this->assertEquals($expected[$i], $params);
        }

        $data['operator'] = 0;
        $data['value'] = 1111;
        $params = $filter->get_param_filter($data);
        $this->assertEmpty($params);
    }

    public function test_filter_text() {
        $data = array();

        $filter = new coursebank_filter_text('coursefullname', get_string('coursefullname', 'tool_coursebank'), 1, 'coursefullname');

        $value = 1111;
        $expected = array(
            0 => array('operator' => 'LIKE',     'value' => $value),
            1 => array('operator' => 'NOT LIKE', 'value' => $value),
            2 => array('operator' => '=',        'value' => $value),
            3 => array('operator' => 'LIKE%',    'value' => $value),
            4 => array('operator' => '%LIKE',    'value' => $value),
            5 => array('operator' => 'EMPTY',    'value' => $value),
        );

        for ($i=0; $i<=5; $i++) {
            $data['operator'] = $i;
            $data['value'] = $value;
            $params = $filter->get_param_filter($data);
            $this->assertEquals($expected[$i], $params);
        }
    }
}
