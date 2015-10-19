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
 * Course bank main page renderer
 *
 * @package    tool_coursebank
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

class tool_coursebank_renderer extends plugin_renderer_base {
    /**
     * Config object for tool_coursebank
     *
     * @var object
     */
    private $config;

    /**
     * Target URL of the backup server
     *
     * @var string
     */
    private $hosturl;

    /**
     * Returns config object for tool_coursebank.
     *
     * @return object
     */
    private function get_config() {
        if (isset($this->config)) {
            return $this->config;
        }

        $this->config = get_config('tool_coursebank');

        return $this->config;
    }

    /**
     * Output main body of course bank interface
     *
     * @return string $html          Body HTML output
     */
    public function course_bank_main($results, $sort='', $dir='', $page='', $perpage='') {
        if (!is_array($results)) {
            $results = (array)$results;
        }

        $columns = array(
                'coursefullname',
                'filetimemodified',
                'backupfilename',
                'filesize',
                'timetransferstarted',
                'timecompleted',
                'status'
        );

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('backupfiles', 'tool_coursebank', count($results)),
                3
        );

        // Don't output the table if there are no results.
        if (count($results) <= 0 ) {
            $html .= $this->box_end();
            return $html;
        }

        $html .= html_writer::start_tag('table', array('class' => 'generaltable'));
        $html .= html_writer::start_tag('thead');
        $html .= html_writer::start_tag('tr');
        foreach ($columns as $column) {
            $html .= html_writer::tag('th', $this->course_bank_get_column_link($column, $sort, $dir, $page, $perpage));
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
                $html .= html_writer::tag('td', get_string('notstarted', 'tool_coursebank'));
            }
            if ($result->timecompleted > 0) {
                $html .= html_writer::tag('td', s(userdate($result->timecompleted)));
            } else {
                $html .= html_writer::tag('td', get_string('notcompleted', 'tool_coursebank'));
            }
            $html .= html_writer::tag('td', s($this->course_bank_get_status($result)));
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
    public function course_bank_downloads($downloads, $count, $sort='', $dir='', $page='', $perpage='') {
        if (!is_array($downloads)) {
            $downloads = (array)$downloads;
        }

        $columns = array('coursefullname', 'backupfilename', 'filesize',  'filetimemodified');

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('backupfiles', 'tool_coursebank', $count),
                3
        );
        // Don't output the table if there are no results.
        if ($count <= 0) {
            $html .= $this->box_end();
            return $html;
        }

        $html .= html_writer::start_tag('table', array('class' => 'generaltable'));
        $html .= html_writer::start_tag('thead');
        $html .= html_writer::start_tag('tr');
        foreach ($columns as $column) {
            $html .= html_writer::tag('th', $this->course_bank_get_column_link($column, $sort, $dir, $page, $perpage));
        }
        $html .= html_writer::tag('th', get_string('action'));
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::end_tag('thead');

        $html .= html_writer::start_tag('tbody');

        foreach ($downloads as $download) {
            if (!empty($download->disabled)) {
                $class = "greyed";
            } else {
                $class = '';
            }
            $html .= html_writer::start_tag('tr', array("class" => $class));
            $html .= html_writer::tag('td', s($download->coursefullname));
            $html .= html_writer::tag('td', s($download->backupfilename));
            $html .= html_writer::tag('td', s(display_size($download->filesize)));
            $dateformatted = userdate(strtotime($download->filetimemodified));
            $html .= html_writer::tag('td', s($dateformatted));
            $links = $this->course_bank_get_download_actions_links($download);
            $html .= html_writer::tag('td', $links);
            $html .= html_writer::start_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');
        $html .= $this->box_end();
        return $html;
    }
    /**
     * Output main body of course bank transfer queue
     *
     * @return string $html Body HTML output
     */
    public function course_bank_queue($results, $sort='', $dir='', $page='', $perpage='') {
        if (!is_array($results)) {
            $results = (array)$results;
        }

        $columns = array('coursefullname', 'filetimemodified', 'backupfilename', 'filesize', 'timecreated', 'status');

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('backupfiles', 'tool_coursebank', count($results)),
                3
        );
        // Don't output the table if there are no results.
        if (count($results) <= 0 ) {
            $html .= $this->box_end();
            return $html;
        }

        $html .= html_writer::start_tag('table', array('class' => 'generaltable'));
        $html .= html_writer::start_tag('thead');
        $html .= html_writer::start_tag('tr');
        foreach ($columns as $column) {
            $html .= html_writer::tag('th', $this->course_bank_get_column_link($column, $sort, $dir, $page, $perpage));
        }
        $html .= html_writer::tag('th', $this->course_bank_get_field_name('completion'));
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
                $html .= html_writer::tag('td', get_string('notstarted', 'tool_coursebank'));
            }
            $html .= html_writer::tag('td', s($this->course_bank_get_status($result)));
            $html .= html_writer::tag('td', s($this->course_bank_get_completion($result)));
            $link = $this->course_bank_get_queue_actions_links($result);
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
    private function course_bank_get_status($result) {
        $statusmap = tool_coursebank::get_statuses();
        if (isset($statusmap[$result->status])) {
            return $statusmap[$result->status];
        }
        return '';
    }
    /**
     * Generates completion percentage.
     *
     * @param object $result
     * @return HTML
     */
    private function course_bank_get_completion($result) {
        $percentage = round(($result->chunknumber / $result->totalchunks) * 100);

        // Max value we would like to display is 99%.
        if ($percentage == 100 ) {
            $percentage = $percentage - 1;
        }

        return $percentage . '%';
    }
    /**
     * Generates action links for download page
     *
     * @param object $result
     * @return HTML
     */
    private function course_bank_get_download_actions_links($result) {
        // First check capability.
        if (!has_capability('tool/coursebank:download', context_system::instance())) {
            return '';
        }
        $text = get_string('download', 'tool_coursebank');
        $icon = html_writer::empty_tag('img',
                array('src' => $this->pix_url('t/download')->out(false),
                    'alt' => $text
                ));

        $url = $this->course_bank_get_download_url($result);

        if (!empty($url)) {
            $links = html_writer::link($url, $icon, array('title' => $text));
        } else {
            $links = get_string('notavailable', 'tool_coursebank');
        }

        return $links;
    }

    /**
     * Returns hosturl based on Target URL configuration.
     *
     * @return string
     */
    private function course_bank_get_hosturl() {

        if (isset($this->hosturl)) {
            return $this->hosturl;
        }

        // Defaults.
        $scheme = 'http://';
        $host = rtrim(trim($this->get_config()->url), '/');
        $port = '';
        $path = '';

        // Parse URL from the config.
        $parsedurl = parse_url($this->get_config()->url);

        // Get scheme: http or https.
        if (isset($parsedurl['scheme'])) {
            $scheme = $parsedurl['scheme'] . '://';
        }
        // Get host.
        if (isset($parsedurl['host'])) {
            $host = $parsedurl['host'];
        }
        // Get port.
        if (isset($parsedurl['port'])) {
            $port = ':' . $parsedurl['port'];
        }
        // Get path only if host is set.
        if (isset($parsedurl['path']) and isset($parsedurl['host'])) {
            $path = $parsedurl['path'];
        }

        $this->hosturl = $scheme . $host . $port . $path;

        return $this->hosturl;
    }
    /**
     * Returns download URL
     *
     * @param object $result
     * @return \moodle_url
     */
    private function course_bank_get_download_url($result) {
        $url = '';

        if (isset($result->id) && isset($result->downloadtoken)) {
            $hosturl = $this->course_bank_get_hosturl();
            $url = new moodle_url($hosturl .  '/backup/' . $result->id . '/download/' . $result->downloadtoken);
        } else {
            $url = new moodle_url($result->downloadurl);
        }

        return $url;
    }
    /**
     * Generates action links for queue page
     *
     * @param object $result
     * @return HTML
     */
    private function course_bank_get_queue_actions_links($result) {
        // First check capability.
        if (!has_capability('tool/coursebank:edit', context_system::instance())) {
            return '';
        }
        $links = '';
        $buttons = array();
        $status = $result->status;

        $noaction = tool_coursebank::get_noaction_statuses();
        $canstop  = tool_coursebank::get_canstop_statuses();
        $stopped  = tool_coursebank::get_stopped_statuses();

        if (!in_array($status, $noaction)) {
             // Stop link.
            if (in_array($status, $canstop)) {
                $text = get_string('stop', 'tool_coursebank');
                $icon = html_writer::empty_tag('img',
                        array('src' => $this->pix_url('t/block')->out(false),
                            'alt' => $text
                        ));
                $url = new moodle_url($this->page->url, array('action' => 'stop', 'id' => $result->id));
                $buttons[] = html_writer::link($url, $icon, array('title' => $text));
            }
            // Go link.
            if (in_array($status, $stopped)) {
                $text = get_string('go', 'tool_coursebank');
                $icon = html_writer::empty_tag('img',
                        array('src' => $this->pix_url('t/collapsed')->out(false),
                            'alt' => $text
                        ));
                $url = new moodle_url($this->page->url, array('action' => 'go', 'id' => $result->id));
                $buttons[] = html_writer::link($url, $icon, array('title' => $text));
            }
            // Delete link.
            $text = get_string('delete', 'tool_coursebank');
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
    private function course_bank_get_field_name($field) {
        return get_string($field, 'tool_coursebank');
    }
    /**
     * Generates a link for table's header
     *
     * @param string $column Coulumn name, e.g. 'coursename'
     * @param string $sort Coulumn name to sort by, e.g. 'coursename'
     * @param string $dir Sort direction (ASC or DESC)
     * @return string HTML code of link
     */
    private function course_bank_get_column_link($column, $sort, $dir, $page, $perpage) {

        $name = $this->course_bank_get_field_name($column);
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
    public function course_bank_conncheck() {
        global $CFG;

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('connchecktitle', 'tool_coursebank'),
                3
        );
        // Hide the button, and then show it with js if it is enabled.
        $html .= html_writer::start_tag('div',
                array('class' => 'conncheckbutton-div hide')
        );
        $buttonattr = array(
            'id' => 'conncheck',
            'type' => 'button',
            'class' => 'conncheckbutton',
            'value' => get_string('conncheckbutton', 'tool_coursebank')
        );
        $html .= html_writer::tag('input', '', $buttonattr);
        $html .= html_writer::end_tag('div');

        // Display ordinary link, and hide it with js if it is enabled.
        $html .= html_writer::start_tag('div',
                array('class' => 'conncheckurl-div')
        );
        $nonjsparams = array('action' => 'conncheck');
        $nonjsurl = new moodle_url(
                $CFG->wwwroot.'/admin/tool/coursebank/check_connection.php',
                $nonjsparams
        );
        $html .= html_writer::link(
                $nonjsurl,
                get_string('conncheckbutton', 'tool_coursebank'),
                array('class' => 'conncheckurl')
        );
        $html .= html_writer::end_tag('div');

        $html .= html_writer::start_tag('div',
                array('class' => 'check-div hide'));
        $imgattr = array(
            'class' => 'hide',
            'src'   => $CFG->wwwroot.'/pix/i/loading_small.gif',
            'alt'   => get_string('checking', 'tool_coursebank')
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
        $urltarget = isset($this->get_config()->url) ? $this->get_config()->url : null;
        $html .= $this->course_bank_check_notification(
                'conncheck',
                'success',
                get_string('connchecksuccess', 'tool_coursebank', $urltarget)
        );

        // Failure notification.
        $html .= $this->course_bank_check_notification(
                'conncheck',
                'fail',
                get_string('conncheckfail', 'tool_coursebank', $urltarget)
        );

        $html .= $this->box_end();

        return $html;
    }
    /**
     * Output result of speed test
     *
     * @return string $html          Result HTML output
     */
    public function course_bank_speedtest() {
        global $CFG;

        $html = $this->box_start();
        $html .= $this->heading(
                get_string('speedtesttitle', 'tool_coursebank'),
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
            'value' => get_string('speedtestbutton', 'tool_coursebank')
        );
        $html .= html_writer::tag('input', '', $buttonattr);
        $html .= html_writer::end_tag('div');

        // Display ordinary link, and hide it with js if it is enabled.
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtesturl-div')
        );
        $nonjsparams = array('action' => 'speedtest');
        $nonjsurl = new moodle_url(
                $CFG->wwwroot.'/admin/tool/coursebank/check_connection.php',
                $nonjsparams
        );
        $html .= html_writer::link(
                $nonjsurl,
                get_string('speedtestbutton', 'tool_coursebank'),
                array('class' => 'speedtesturl')
        );
        $html .= html_writer::end_tag('div');
        $html .= html_writer::start_tag('div',
                array('class' => 'speedtest-div hide'));
        $imgattr = array(
            'class' => 'hide',
            'src'   => $CFG->wwwroot.'/pix/i/loading_small.gif',
            'alt'   => get_string('checking', 'tool_coursebank')
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
        $urltarget = isset($this->get_config()->url) ? $this->get_config()->url : null;

        $attr = array(
            'type' => 'hidden',
            'name' => 'success',
            'value' => get_string('speedtestsuccess', 'tool_coursebank', $urltarget),
            'class' => 'speedtestsuccess'
        );

        $html .= html_writer::tag('input', '', $attr);
        $attr = array(
            'type' => 'hidden',
            'name' => 'chunk',
            'value' => get_string('speedtestchunk', 'tool_coursebank', $urltarget),
            'class' => 'speedtestchunk'
        );
        $html .= html_writer::tag('input', '', $attr);
        $html .= $this->course_bank_check_notification(
                'speedtest',
                'success',
                get_string('speedtestsuccess', 'tool_coursebank', $urltarget)
        );

        // Failure notification.
        $html .= $this->course_bank_check_notification(
                'speedtest',
                'fail',
                get_string('speedtestfail', 'tool_coursebank', $urltarget)
        );

        // Slow connection speed notification.
        $attr = array(
            'type' => 'hidden',
            'name' => 'slow',
            'value' => get_string('speedtestslow', 'tool_coursebank', $urltarget),
            'class' => 'speedtestslow'
        );
        $html .= html_writer::tag('input', '', $attr);
        $html .= $this->course_bank_check_notification(
                'speedtest',
                'slow',
                get_string('speedtestslow', 'tool_coursebank', $urltarget)
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
    public function course_bank_check_notification($check, $msgtype, $content='', $hide=true) {
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

    /**
     * Render report page.
     *
     * @param tool_coursebank_renderable $report object of report.
     */
    public function render_tool_coursebank_renderable(tool_coursebank_renderable $report) {
        if (empty($report->lagacy) and empty($report->selectedlogreader)) {
            echo $this->output->notification(get_string('nologreaderenabled', 'tool_coursebank'), 'notifyproblem');
            return;
        }
        if ($report->showselectorform) {
            $this->report_selector_form($report);
        }

        if ($report->showreport) {
            $report->tablelog->out($report->perpage, true);
        }
    }

    /**
     * Prints/return reader selector
     *
     * @param tool_coursebank_renderable $report report.
     */
    public function reader_selector(tool_coursebank_renderable $report) {
        $readers = $report->get_readers(true);
        if (empty($readers)) {
            $readers = array(get_string('nologreaderenabled', 'tool_coursebank'));
        }
        $url = fullclone ($report->url);
        $url->remove_params(array('logreader'));
        $select = new single_select($url, 'logreader', $readers, $report->selectedlogreader, null);
        $select->set_label(get_string('selectlogreader', 'tool_coursebank'));
        echo $this->output->render($select);
    }

    /**
     * This function is used to generate and display selector form
     *
     * @param tool_coursebank_renderable $report report.
     */
    public function report_selector_form(tool_coursebank_renderable $report) {
        echo html_writer::start_tag('form', array('class' => 'logselecform', 'action' => $report->url, 'method' => 'get'));
        echo html_writer::start_tag('div');
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'chooselog', 'value' => '1'));

        // Add date selector.
        $dates = $report->get_date_options();
        echo html_writer::label(get_string('date'), 'menudate', false, array('class' => 'accesshide'));
        echo html_writer::select($dates, "date", $report->date, get_string("alldays"));

        // Add user selector.
        $users = $report->get_user_list();
        if ($report->showusers) {
            echo html_writer::label(get_string('selctauser'), 'menuuser', false, array('class' => 'accesshide'));
            echo html_writer::select($users, "user", $report->userid, get_string("allparticipants"));
        } else {
            $users = array();
            if (!empty($report->userid)) {
                $users[$report->userid] = $report->get_selected_user_fullname();
            } else {
                $users[0] = get_string('allparticipants');
            }
            echo html_writer::label(get_string('selctauser'), 'menuuser', false, array('class' => 'accesshide'));
            echo html_writer::select($users, "user", $report->userid, false);
            $str = new stdClass();
            $str->url = new moodle_url('/admin/tool/coursebank/report.php', array('chooselog' => 0,
                'user' => $report->userid, 'date' => $report->date, 'type' => $report->type, 'showusers' => 1));
            $str->url = $str->url->out(false);
            print_string('logtoomanyusers', 'moodle', $str);
        }

        // Add activity selector.
        $activities = $report->get_type_list();
        echo html_writer::label(get_string('activities'), 'type', false, array('class' => 'accesshide'));
        echo html_writer::select($activities, "type", $report->type, get_string("allactivities"));

        // Add reader option.
        // If there is some reader available then only show submit button.
        $readers = $report->get_readers(true);
        if (!empty($readers)) {
            if (count($readers) == 1) {
                $attributes = array('type' => 'hidden', 'name' => 'logreader', 'value' => key($readers));
                echo html_writer::empty_tag('input', $attributes);
            } else {
                echo html_writer::label(get_string('selectlogreader', 'tool_coursebank'), 'menureader', false,
                        array('class' => 'accesshide'));
                echo html_writer::select($readers, 'logreader', $report->selectedlogreader, false);
            }
            echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('gettheselogs')));
        } else if (!empty($report->lagacy)) {
            echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('gettheselogs')));
        }
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('form');
    }
}
