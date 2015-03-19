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

        $html .= $this->single_button('', 'Check connection', 'get', array('id' => 'conncheck'));
        $html .= '<input type = "hidden" name = "wwwroot" value = "' . $CFG->wwwroot . '" class = "wwwroot">';


        $html .= '<div class = "notification-success hide">';
        $html .= $this->notification(
                get_string('connchecksuccess', 'tool_coursestore'),
                'notifysuccess'
        );
        $html .= '</div>';

        $html .= '<div class = "notification-fail hide">';
        $html .= $this->notification(
                get_string('conncheckfail', 'tool_coursestore'),
                'notifyproblem'
        );
        $html .= '</div>';

        $html .= $this->box_end();

        return $html;
    }
}
