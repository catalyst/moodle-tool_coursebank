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
        // Hide the button, and then show it with js if it is enabled
        $html .= html_writer::start_tag('div',
                array('class' => 'conncheckbutton-div hide')
        );
        $buttonattr = array(
            'id' => 'conncheck',
            'type' => 'button',
            'class' => 'conncheckbutton hide',
            'value' => get_string('conncheckbutton', 'tool_coursestore')
        );
        $html .= html_writer::tag('input', '', $buttonattr);
        $html .= html_writer::end_tag('div');

        // Display ordinary link, and hide it with js if it is enabled
        $html .= html_writer::start_tag('div',
                array('class' => 'conncheckurl-div')
        );
        $nonjsparams = array('action' => 'conncheck');
        $nonjsurl = new moodle_url(
                $CFG->wwwroot.'/admin/tool/coursestore/check_connection.php',
                $nonjsparams
        );
        $html .= html_writer::link(
                $nonjsurl,
                get_string('conncheckbutton', 'tool_coursestore'),
                array('class' => 'conncheckurl')
        );
        $html .= html_writer::end_tag('div');

        $html .= html_writer::start_tag('div',
                array('class' => 'check-div hide'));
        $imgattr = array(
            'class' => 'hide',
            'src'   => $CFG->wwwroot.'/pix/i/loading_small.gif',
            'alt'   => get_string('checking', 'tool_coursestore')
        );

        $html .= html_writer::empty_tag('img', $imgattr);
        $inputattr = array(
            'type' => 'hidden',
            'name' => 'wwwroot',
            'value' => $CFG->wwwroot,
            'class' => 'wwwroot'
        );
        $html .= html_writer::tag('input', '', $inputattr);
        $html .= html_writer::end_tag('div');

        // Success notification
        $html .= $this->course_store_check_notification(
                'conncheck',
                'success',
                get_string('connchecksuccess', 'tool_coursestore')
        );

        // Failure notification
        $html .= $this->course_store_check_notification(
                'conncheck',
                'fail',
                get_string('conncheckfail', 'tool_coursestore')
        );

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

        // Hide the button, and then show it with js if it is enabled
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtestbutton-div hide')
        );
        $buttonattr = array(
            'id' => 'speedtest',
            'type' => 'button',
            'class' => 'speedtestbutton',
            'value' => get_string('speedtestbutton', 'tool_coursestore')
        );
        $html .= html_writer::tag('input', '', $buttonattr);
        $html .= html_writer::end_tag('div');

        // Display ordinary link, and hide it with js if it is enabled
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtesturl-div')
        );
        $nonjsparams = array('action' => 'speedtest');
        $nonjsurl = new moodle_url(
                $CFG->wwwroot.'/admin/tool/coursestore/check_connection.php',
                $nonjsparams
        );
        $html .= html_writer::link(
                $nonjsurl,
                get_string('speedtestbutton', 'tool_coursestore'),
                array('class' => 'speedtesturl')
        );
        $html .= html_writer::end_tag('div');
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtest-div hide'));
        $imgattr = array(
            'class' => 'hide',
            'src'   => $CFG->wwwroot.'/pix/i/loading_small.gif',
            'alt'   => get_string('checking', 'tool_coursestore')
        );

        $html .= html_writer::empty_tag('img', $imgattr);
        $html .= html_writer::end_tag('div');
        $wwwrootattr = array(
            'type' => 'hidden',
            'name' => 'wwwroot',
            'value' => $CFG->wwwroot,
            'class' => 'wwwroot'
        );
        $html .= html_writer::tag('input', '', $wwwrootattr);

        // Success notification

        $html .= $this->course_store_check_notification(
                'speedtest',
                'success',
                get_string('speedtestsuccess', 'tool_coursestore')
        );

        // Failure notification
        $html .= $this->course_store_check_notification(
                'speedtest',
                'fail',
                get_string('speedtestfail', 'tool_coursestore')
        );

        // Slow connection speed notification
        $attr = array(
            'type' => 'hidden',
            'name' => 'slow',
            'value' => get_string('speedtestslow', 'tool_coursestore'),
            'class' => 'speedtestslow'
        );
        $html .= html_writer::tag('input', '', $attr);
        $html .= $this->course_store_check_notification(
                'speedtest',
                'slow',
                get_string('speedtestslow', 'tool_coursestore')
        );

        $html .= $this->box_end();

        return $html;
    }
    /**
     * Output html for notification
     *
     * @param string $check    Check type (e.g. speedtest, conncheck)
     * @param string $msgtype  Failure, success, slow
     * @param string $content  Notification content
     * @param bool   $hide     Whether or not to hide the notification
     *
     * @return string          Output html
     */
    public function course_store_check_notification($check, $msgtype, $content='', $hide=true) {
        switch($msgtype) {
            case 'fail':
                $alert = 'alert-error';
                break;
            case 'success':
                $alert = 'alert-success';
                break;
            case 'slow':
                $alert = 'alert';
                break;
        }

        $hidestring = $hide ? ' hide' : '';
        $html = html_writer::start_tag('div',
                array('class' => $check.'-'.$msgtype.$hidestring));

        $html .= html_writer::tag(
                'div',
                $content,
                array('class' => 'alert '.$alert.' '.$check.'-alert-'.$msgtype)
        );
        $html .= html_writer::end_tag('div');

        return $html;
    }
}
