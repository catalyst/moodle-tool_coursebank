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
 * Course store main page renderer
 *
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

class tool_coursestore_renderer extends plugin_renderer_base {

    /**
     * Output main body of course store interface
     *
     * @return string $html          Body HTML output
     */
    public function course_store_main($results) {
        global $CFG;

        $columns = array(
                'Course name'    => 'shortname',
                'Backup date'    => 'timemodified',
                'File name'      => 'filename',
                'File size'      => 'filesize',
                'Status'         => 'status'
        );

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('backupsummary', 'tool_coursestore'),
                3
        );
        $html .= html_writer::start_tag('table', array('class' => 'generaltable'));
        $html .= html_writer::start_tag('thead');
        $html .= html_writer::start_tag('tr');
        foreach($columns as $column => $name) {
            $html .= html_writer::tag('th', $column);
        }
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::end_tag('thead');

        $html .= html_writer::start_tag('tbody');
        foreach($results as $result) {
            $html .= html_writer::start_tag('tr');
            $html .= html_writer::tag('td', $result->shortname);
            $html .= html_writer::tag('td', userdate($result->timemodified));
            $html .= html_writer::tag('td', $result->filename);
            $html .= html_writer::tag('td', display_size($result->filesize));
            $html .= html_writer::tag('td', $result->status);
            $html .= html_writer::start_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');
        $html .= $this->box_end();
        return $html;
    }
    /**
     * Output result of connection check
     *
     * @return string $html          Result HTML output
     */
    public function course_store_conncheck() {
        global $CFG;

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('connchecktitle', 'tool_coursestore'),
                3
        );
        $buttonattr = array(
            'id' => 'conncheck',
            'type' => 'button',
            'class' => 'conncheckbutton',
            'value' => 'Check connection'
        );
        $html .= html_writer::tag('input', '', $buttonattr);
        $html .= html_writer::start_tag('div',
                array('class' => 'check-div hide'));
        $html .= html_writer::img(
                $CFG->wwwroot.'/pix/i/loading_small.gif',
                get_string('checking', 'tool_coursestore'),
                array('class' => 'hide')
        );
        $html .= html_writer::end_tag('div');
        $inputattr = array(
            'type' => 'hidden',
            'name' => 'wwwroot',
            'value' => $CFG->wwwroot,
            'class' => 'wwwroot'
        );
        $html .= html_writer::tag('input', '', $inputattr);

        $html .= html_writer::start_tag('div',
                array('class' => 'notification-success hide'));
        $html .= $this->notification(
                get_string('connchecksuccess', 'tool_coursestore'),
                'notifysuccess'
        );
        $html .= html_writer::end_tag('div');

        $html .= html_writer::start_tag('div',
                array('class' => 'notification-fail hide'));
        $html .= $this->notification(
                get_string('conncheckfail', 'tool_coursestore'),
                'notifyproblem'
        );
        $html .= html_writer::end_tag('div');

        $html .= $this->box_end();

        return $html;
    }
    /**
     * Output result of speed test
     *
     * @return string $html          Result HTML output
     */
    public function course_store_speedtest() {
        global $CFG;

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('speedtesttitle', 'tool_coursestore'),
                3
        );
        $buttonattr = array(
            'id' => 'speedtest',
            'type' => 'button',
            'class' => 'speedtestbutton',
            'value' => 'Test transfer speed'
        );
        $html .= html_writer::tag('input', '', $buttonattr);
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtest-div hide'));
        $html .= html_writer::img(
                $CFG->wwwroot.'/pix/i/loading_small.gif',
                get_string('checking', 'tool_coursestore'),
                array('class' => 'hide')
        );
        $html .= html_writer::end_tag('div');
        $wwwrootattr = array(
            'type' => 'hidden',
            'name' => 'wwwroot',
            'value' => $CFG->wwwroot,
            'class' => 'wwwroot'
        );
        $html .= html_writer::tag('input', '', $wwwrootattr);

        // Success notification
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtest-success hide'));

        $html .= html_writer::tag(
                'div',
                get_string('speedtestsuccess', 'tool_coursestore'),
                array('class' => 'alert alert-success speedtest-alert')
        );
        $html .= html_writer::end_tag('div');

        // Failure notification
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtest-fail hide'));
        $html .= $this->notification(
                get_string('speedtestfail', 'tool_coursestore'),
                'notifyproblem'
        );
        $html .= html_writer::end_tag('div');

        // Slow connection speed notification
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtest-slow hide'));
        $attr = array(
            'type' => 'hidden',
            'name' => 'slow',
            'value' => get_string('speedtestslow', 'tool_coursestore'),
            'class' => 'speedtestslow'
        );
        $html .= html_writer::tag('input', '', $attr);

        $html .= html_writer::tag(
                'div',
                get_string('speedtestslow', 'tool_coursestore'),
                array('class' => 'alert speedtest-alert-slow')
        );
        $html .= html_writer::end_tag('div');


        $html .= $this->box_end();

        return $html;
    }
}
