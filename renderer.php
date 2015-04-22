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
    public function course_store_main($results, $sort='', $dir='', $page='', $perpage='') {
        $columns = array(
                'coursefullname',
                'filetimemodified',
                'backupfilename',
                'filesize',
                'timecreated',
                'timecompleted',
                'status'
        );

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('backupfiles', 'tool_coursestore', count($results)),
                3
        );
        $html .= html_writer::start_tag('table', array('class' => 'generaltable'));
        $html .= html_writer::start_tag('thead');
        $html .= html_writer::start_tag('tr');
        foreach ($columns as $column) {
            $html .= html_writer::tag('th', $this->course_store_get_column_link($column, $sort, $dir, $page, $perpage));
        }
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::end_tag('thead');

        $html .= html_writer::start_tag('tbody');
        foreach ($results as $result) {
            $html .= html_writer::start_tag('tr');
            $html .= html_writer::tag('td', s($result->coursefullname));
            $html .= html_writer::tag('td', s(userdate($result->filetimemodified)));
            $html .= html_writer::tag('td', s($result->backupfilename));
            $html .= html_writer::tag('td', s(display_size($result->filesize)));
            if ($result->timetransferstarted > 0) {
                $html .= html_writer::tag('td', s(userdate($result->timetransferstarted)));
            } else {
                $html .= html_writer::tag('td', get_string('notstarted', 'tool_coursestore'));
            }
            if ($result->timecompleted > 0) {
                $html .= html_writer::tag('td', s(userdate($result->timecompleted)));
            } else {
                $html .= html_writer::tag('td', get_string('notcompleted', 'tool_coursestore'));
            }
            $html .= html_writer::tag('td', s($this->course_store_get_status($result)));
            $html .= html_writer::start_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');
        $html .= $this->box_end();
        return $html;
    }
    /**
     * Output main body of downloads interface.
     *
     * @return string $html          Body HTML output
     */
    public function course_store_downloads($downloads, $sort='', $dir='', $page='', $perpage='') {
        $columns = array('coursefullname', 'backupfilename', 'filesize',  'filetimemodified');

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('backupfiles', 'tool_coursestore', count((array)$downloads)),
                3
        );
        // Don't output the table if there are no results.
        if (count($downloads <= 0)) {
            $html .= $this->box_end();
            return $html;
        }

        $html .= html_writer::start_tag('table', array('class' => 'generaltable'));
        $html .= html_writer::start_tag('thead');
        $html .= html_writer::start_tag('tr');
        foreach ($columns as $column) {
            $html .= html_writer::tag('th', $this->course_store_get_column_link($column, $sort, $dir, $page, $perpage));
        }
        $html .= html_writer::tag('th', get_string('action'));
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::end_tag('thead');

        $html .= html_writer::start_tag('tbody');

        foreach ((array) $downloads as $download) {
            $html .= html_writer::start_tag('tr');
            $html .= html_writer::tag('td', s($download->coursefullname));
            $html .= html_writer::tag('td', s($download->backupfilename));
            $html .= html_writer::tag('td', s(display_size($download->filesize)));
            $dateformatted = userdate(strtotime($download->filetimemodified));
            $html .= html_writer::tag('td', s($dateformatted));
            $links = $this->course_store_get_download_actions_links($download);
            $html .= html_writer::tag('td', $links);
            $html .= html_writer::start_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');
        $html .= $this->box_end();
        return $html;
    }
    /**
     * Output main body of course store transfer queue
     *
     * @return string $html Body HTML output
     */
    public function course_store_queue($results, $sort='', $dir='', $page='', $perpage='') {
        $columns = array('coursefullname', 'filetimemodified', 'backupfilename', 'filesize', 'timecreated', 'status');

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('backupfiles', 'tool_coursestore', count($results)),
                3
        );
        // Don't output the table if there are no results.
        if (count($results <= 0)) {
            $html .= $this->box_end();
            return $html;
        }

        $html .= html_writer::start_tag('table', array('class' => 'generaltable'));
        $html .= html_writer::start_tag('thead');
        $html .= html_writer::start_tag('tr');
        foreach ($columns as $column) {
            $html .= html_writer::tag('th', $this->course_store_get_column_link($column, $sort, $dir, $page, $perpage));
        }
        $html .= html_writer::tag('th', get_string('action'));
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::end_tag('thead');

        $html .= html_writer::start_tag('tbody');
        foreach ($results as $result) {
            $html .= html_writer::start_tag('tr');
            $html .= html_writer::tag('td', s($result->coursefullname));
            $html .= html_writer::tag('td', s(userdate($result->filetimemodified)));
            $html .= html_writer::tag('td', s($result->backupfilename));
            $html .= html_writer::tag('td', s(display_size($result->filesize)));
            if ($result->timetransferstarted > 0) {
                $html .= html_writer::tag('td', s(userdate($result->timetransferstarted)));
            } else {
                $html .= html_writer::tag('td', get_string('notstarted', 'tool_coursestore'));
            }
            $html .= html_writer::tag('td', s($this->course_store_get_status($result)));
            $link = $this->course_store_get_queue_actions_links($result);
            $html .= html_writer::tag('td', $link);
            $html .= html_writer::start_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');
        $html .= $this->box_end();
        return $html;
    }
    /**
     * Generates human readable status
     *
     * @param object $result
     * @return string
     */
    private function course_store_get_status($result) {
        $statusmap = tool_coursestore::get_statuses();
        if (isset($statusmap[$result->status])) {
            return $statusmap[$result->status];
        }
        return '';
    }
    /**
     * Generates action links for download page
     *
     * @param object $result
     * @return HTML
     */
    private function course_store_get_download_actions_links($result) {
        // First check capability.
        if (!has_capability('tool/coursestore:download', context_system::instance())) {
            return '';
        }
        $text = get_string('download', 'tool_coursestore');
        $icon = html_writer::empty_tag('img',
                array('src' => $this->pix_url('t/download')->out(false),
                    'alt' => $text
                ));
        $url = new moodle_url($this->page->url, array('download' => 1, 'file' => $result->uniqueid));
        $links = html_writer::link($url, $icon, array('title' => $text));

        return $links;
    }
    /**
     * Generates action links for queue page
     *
     * @param object $result
     * @return HTML
     */
    private function course_store_get_queue_actions_links($result) {
        // First check capability.
        if (!has_capability('tool/coursestore:edit', context_system::instance())) {
            return '';
        }
        $links = '';
        $buttons = array();
        $status = $result->status;

        $noaction = tool_coursestore::get_noaction_statuses();
        $canstop  = tool_coursestore::get_canstop_statuses();
        $stopped  = tool_coursestore::get_stopped_statuses();

        if (!in_array($status, $noaction)) {
             // Stop link.
            if (in_array($status, $canstop)) {
                $text = get_string('stop', 'tool_coursestore');
                $icon = html_writer::empty_tag('img',
                        array('src' => $this->pix_url('t/block')->out(false),
                            'alt' => $text
                        ));
                $url = new moodle_url($this->page->url, array('action' => 'stop', 'id' => $result->id));
                $buttons[] = html_writer::link($url, $icon, array('title' => $text));
            }
            // Go link.
            if (in_array($status, $stopped)) {
                $text = get_string('go', 'tool_coursestore');
                $icon = html_writer::empty_tag('img',
                        array('src' => $this->pix_url('t/collapsed')->out(false),
                            'alt' => $text
                        ));
                $url = new moodle_url($this->page->url, array('action' => 'go', 'id' => $result->id));
                $buttons[] = html_writer::link($url, $icon, array('title' => $text));
            }
            // Delete link.
            $text = get_string('delete', 'tool_coursestore');
            $icon = html_writer::empty_tag('img',
                    array('src' => $this->pix_url('t/delete')->out(false),
                        'alt' => $text
                    ));
            $url = new moodle_url($this->page->url, array('action' => 'delete', 'id' => $result->id));
            $buttons[] = html_writer::link($url, $icon, array('title' => $text));

            $links = implode(' ', $buttons);
        }

        return $links;
    }
    /**
     * Returns the display name of a field
     *
     * @param string $field Field name, e.g. 'coursename'
     * @return string Text description taken from language file, e.g. 'Course name'
     */
    private function course_store_get_field_name($field) {
        return get_string($field, 'tool_coursestore');
    }
    /**
     * Generates a link for table's header
     *
     * @param string $column Coulumn name, e.g. 'coursename'
     * @param string $sort Coulumn name to sort by, e.g. 'coursename'
     * @param string $dir Sort direction (ASC or DESC)
     * @return string HTML code of link
     */
    private function course_store_get_column_link($column, $sort, $dir, $page, $perpage) {

        $name = $this->course_store_get_field_name($column);
        if ($sort != $column) {
            $columndir = "ASC";
            $columnicon = "";
        } else {
            $columndir = $dir == "ASC" ? "DESC" : "ASC";
            $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
            $columnicon = "<img class='iconsort' src=\"" . $this->output->pix_url('t/' . $columnicon) . "\" alt=\"\" />";
        }
        $$column = "<a href=\"?sort=$column&amp;dir=$columndir&amp;page=$page&amp;perpage=$perpage\">" . $name . "</a>$columnicon";

        return $$column;
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
        // Hide the button, and then show it with js if it is enabled.
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

        // Display ordinary link, and hide it with js if it is enabled.
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

        // Success notification.
        $urltarget = get_config('tool_coursestore', 'url');
        $html .= $this->course_store_check_notification(
                'conncheck',
                'success',
                get_string('connchecksuccess', 'tool_coursestore', $urltarget)
        );

        // Failure notification.
        $html .= $this->course_store_check_notification(
                'conncheck',
                'fail',
                get_string('conncheckfail', 'tool_coursestore', $urltarget)
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

        // Hide the button, and then show it with js if it is enabled.
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

        // Display ordinary link, and hide it with js if it is enabled.
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

        // Success notification.
        $urltarget = get_config('tool_coursestore', 'url');
        $attr = array(
            'type' => 'hidden',
            'name' => 'success',
            'value' => get_string('speedtestsuccess', 'tool_coursestore', $urltarget),
            'class' => 'speedtestsuccess'
        );
        $html .= html_writer::tag('input', '', $attr);
        $html .= $this->course_store_check_notification(
                'speedtest',
                'success',
                get_string('speedtestsuccess', 'tool_coursestore', $urltarget)
        );

        // Failure notification.
        $html .= $this->course_store_check_notification(
                'speedtest',
                'fail',
                get_string('speedtestfail', 'tool_coursestore', $urltarget)
        );

        // Slow connection speed notification.
        $attr = array(
            'type' => 'hidden',
            'name' => 'slow',
            'value' => get_string('speedtestslow', 'tool_coursestore', $urltarget),
            'class' => 'speedtestslow'
        );
        $html .= html_writer::tag('input', '', $attr);
        $html .= $this->course_store_check_notification(
                'speedtest',
                'slow',
                get_string('speedtestslow', 'tool_coursestore', $urltarget)
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
