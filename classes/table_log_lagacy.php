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
 * Table for displaying legacy logs.
 *
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/admin/tool/coursebank/classes/table_log.php');

/**
 * Table class for moodle less then 2.7.
 *
 */
class tool_coursebank_table_log_legacy extends tool_coursebank_table_log {
    /**
     * Sets up the table_log parameters.
     *
     * @param string $uniqueid unique id of form.
     * @param stdClass $filterparams (optional) filter params.
     *     - int date: Date from which logs to be viewed.
     */
    public function __construct($uniqueid, $filterparams = null) {
        parent::__construct($uniqueid);

        $this->set_attribute('class', 'tool_coursebank_report generaltable generalbox');
        $this->filterparams = $filterparams;
        // Add course column if logs are displayed for site.
        $cols = array();
        $headers = array();

        $this->define_columns(array_merge($cols, array('time', 'fullnameuser',
                'eventname', 'description', 'ip')));
        $this->define_headers(array_merge($headers, array(
                get_string('time'),
                get_string('fullnameuser'),
                get_string('action'),
                get_string('description'),
                get_string('ip_address')
                )
            ));
        $this->collapsible(false);
        $this->sortable(false);
        $this->pageable(true);
    }
    /**
     * Generate the time column.
     *
     * @param stdClass $raw data.
     * @return string HTML for the time column
     */
    public function col_time($raw) {
        $recenttimestr = get_string('strftimerecent', 'core_langconfig');
        return userdate($raw->time, $recenttimestr);
    }
    /**
     * Generate the username column.
     *
     * @param stdClass $raw data.
     * @return string HTML for the username column
     */
    public function col_fullnameuser($raw) {
        if (!empty($raw->userid) && !empty($this->userfullnames[$raw->userid])) {
            $params = array('id' => $raw->userid);
            $username = $this->userfullnames[$raw->userid];
            if (empty($this->download)) {
                $username = html_writer::link(new moodle_url('/user/view.php', $params), $username);
            }
        } else {
            $username = '-';
        }
        return $username;
    }
    /**
     * Generate the event name column.
     *
     * @param stdClass $raw data.
     * @return string HTML for the event name column
     */
    public function col_eventname($raw) {
        // Event name.
        $eventname = $raw->action;

        return $eventname;
    }
    /**
     * Generate the description column.
     *
     * @param stdClass $raw data.
     * @return string HTML for the description column
     */
    public function col_description($raw) {
        // Description.
        return $raw->info;
    }
    /**
     * Generate the ip column.
     *
     * @param stdClass $raw data.
     * @return string HTML for the ip column
     */
    public function col_ip($raw) {
        $ipaddress = $raw->ip;

        if (empty($this->download)) {
            $url = new moodle_url("/iplookup/index.php?ip={$ipaddress}&user={$raw->userid}");
            $ipaddress = $this->action_link($url, $ipaddress, 'ip');
        }
        return $ipaddress;
    }
    /**
     * Method to create a link with popup action.
     *
     * @param moodle_url $url The url to open.
     * @param string $text Anchor text for the link.
     * @param string $name Name of the popup window.
     *
     * @return string html to use.
     */
    protected function action_link(moodle_url $url, $text, $name = 'popup') {
        global $OUTPUT;
        $link = new action_link($url, $text, new popup_action('click', $url, $name, array('height' => 440, 'width' => 700)));
        return $OUTPUT->render($link);
    }
    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        $joins = array();
        $params = array();

        list($actionsql, $actionparams) = $this->get_component_sql();
        $joins[] = $actionsql;
        $params = array_merge($params, $actionparams);

        if ($this->filterparams->type == 'errors') {
            list($actionsql, $actionparams) = $this->get_error_sql();
            $joins[] = $actionsql;
            $params = array_merge($params, $actionparams);
        }

        if (!empty($this->filterparams->date)) {
            $joins[] = "time > :date AND time < :enddate";
            $params['date'] = $this->filterparams->date;
            $params['enddate'] = $this->filterparams->date + DAYSECS; // Show logs only for the selected date.
        }

        if (!empty($this->filterparams->userid)) {
            $joins[] = "userid = :userid";
            $params['userid'] = $this->filterparams->userid;
        }

        $selector = implode(' AND ', $joins);

        if (!$this->is_downloading()) {
            $sql = "SELECT COUNT(*) FROM {log} WHERE $selector";

            $total = $DB->count_records_sql($sql, $params);
            $this->pagesize($pagesize, $total);
        } else {
            $this->pageable(false);
        }

        if ($this->filterparams->orderby) {
            $order = "ORDER BY {$this->filterparams->orderby}";
        }

        $sql = "SELECT * FROM {log} WHERE $selector $order";
        $this->rawdata = $DB->get_records_sql($sql, $params, $this->get_page_start(), $this->get_page_size());

        // Set initial bars.
        if ($useinitialsbar && !$this->is_downloading()) {
            $this->initialbars($total > $pagesize);
        }

        // Update list of users which will be displayed on log page.
        $this->update_users_used();
    }

    /**
     * Helper function to create list of course shortname and user fullname shown in log report.
     * This will update $this->userfullnames and $this->courseshortnames array with userfullname and courseshortname (with link),
     * which will be used to render logs in table.
     */
    public function update_users_used() {
        global $DB, $CFG;

        $this->userfullnames = array();
        $userids = array();

        // For each event cache full username and course.
        // Get list of userids and courseids which will be shown in log report.
        foreach ($this->rawdata as $row) {
            if (!empty($row->userid) && !in_array($row->userid, $userids)) {
                $userids[] = $row->userid;
            }
        }
        // Get user fullname and put that in return list.
        if (!empty($userids)) {
            list($usql, $uparams) = $DB->get_in_or_equal($userids);
            // Function get_all_user_name_fields is missing from 2.4, so we need to do it manually.
            // Check if moodle is older then 2.7.x.
            if ((float)$CFG->version < 2014051200) {
                $alternatenames = array('firstname' => 'firstname',
                                        'lastname' => 'lastname');
                $alternatenames = implode(',', $alternatenames);
                $users = $DB->get_records_sql("SELECT id," . $alternatenames . " FROM {user} WHERE id " . $usql,
                        $uparams);
            } else {
                $users = $DB->get_records_sql("SELECT id," . get_all_user_name_fields(true) . " FROM {user} WHERE id " . $usql,
                        $uparams);
            }
            foreach ($users as $userid => $user) {
                $this->userfullnames[$userid] = fullname($user);
            }
        }
    }
}
