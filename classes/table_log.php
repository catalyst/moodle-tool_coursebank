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
 * Table log for displaying logs.
 *
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table class.
 *
 */
class tool_coursebank_table_log extends table_sql {

    /**
     * @var array list of user fullnames shown in report
     */
    private $userfullnames = array();

    /**
     * @var stdClass filters parameters
     */
    private $filterparams;

    /**
     * Sets up the table_log parameters.
     *
     * @param string $uniqueid unique id of form.
     * @param stdClass $filterparams (optional) filter params.
     *     - \core\log\sql_select_reader logreader: reader from which data will be fetched.
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
                'eventname', 'description', 'origin', 'ip')));
        $this->define_headers(array_merge($headers, array(
                get_string('time'),
                get_string('fullnameuser'),
                get_string('eventname', 'tool_coursebank'),
                get_string('description'),
                get_string('eventorigin', 'tool_coursebank'),
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
     * @param stdClass $event event data.
     * @return string HTML for the time column
     */
    public function col_time($event) {
        $recenttimestr = get_string('strftimerecent', 'core_langconfig');
        return userdate($event->timecreated, $recenttimestr);
    }
    /**
     * Generate the username column.
     *
     * @param stdClass $event event data.
     * @return string HTML for the username column
     */
    public function col_fullnameuser($event) {
        // Get extra event data for origin and realuserid.
        $logextra = $event->get_logextra();

        // Add username who did the action.
        if (!empty($logextra['realuserid'])) {
            $user = new stdClass();
            $params = array('id' => $logextra['realuserid']);
            $user->realusername = $this->userfullnames[$logextra['realuserid']];
            $user->asusername = $this->userfullnames[$event->userid];
            if (empty($this->download)) {
                $user->realusername = html_writer::link(new moodle_url('/user/view.php', $params), $user->realusername);
                $params['id'] = $event->userid;
                $user->asusername = html_writer::link(new moodle_url('/user/view.php', $params), $user->asusername);
            }
            $username = get_string('eventloggedas', 'tool_coursebank', $user);
        } else if (!empty($event->userid) && !empty($this->userfullnames[$event->userid])) {
            $params = array('id' => $event->userid);
            $username = $this->userfullnames[$event->userid];
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
     * @param stdClass $event event data.
     * @return string HTML for the event name column
     */
    public function col_eventname($event) {
        // Event name.
        if ($this->filterparams->logreader instanceof logstore_legacy\log\store) {
            // Hack for support of logstore_legacy.
            $eventname = $event->eventname;
        } else {
            $eventname = $event->get_name();
        }

        return $eventname;
    }
    /**
     * Generate the description column.
     *
     * @param stdClass $event event data.
     * @return string HTML for the description column
     */
    public function col_description($event) {
        // Description.
        return $event->get_description();
    }
    /**
     * Generate the origin column.
     *
     * @param stdClass $event event data.
     * @return string HTML for the origin column
     */
    public function col_origin($event) {
        // Get extra event data for origin and realuserid.
        $logextra = $event->get_logextra();

        // Add event origin, normally IP/cron.
        return $logextra['origin'];
    }
    /**
     * Generate the ip column.
     *
     * @param stdClass $event event data.
     * @return string HTML for the ip column
     */
    public function col_ip($event) {
        // Get extra event data for origin and realuserid.
        $logextra = $event->get_logextra();
        $ipaddress = $logextra['ip'];

        if (empty($this->download)) {
            $url = new moodle_url("/iplookup/index.php?ip={$ipaddress}&user={$event->userid}");
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
     *  Build SQL for component
     *
     * @return string
     */
    protected function get_component_sql() {
        $joins = array();
        $params = array();

        // We want logs only for tool_coursebank.
        if (empty($this->filterparams->logreader) or $this->filterparams->logreader instanceof logstore_legacy\log\store) {
            $joins[] = "module = '".coursebank_logging::LOG_MODULE_COURSE_BANK."'";
        } else {
            $joins[] = "component = 'tool_coursebank'";
        }

        $sql = implode(' AND ', $joins);
        return array($sql, $params);
    }
    /**
     * Build SQL for error filter
     *
     * @return string
     */
    protected function get_error_sql() {
        $joins = array();
        $params = array();

        if (empty($this->filterparams->logreader) or $this->filterparams->logreader instanceof logstore_legacy\log\store) {
            $joins[] = "( info like '%failed%' OR info like '%Failed%' )";
        } else {
            $joins[] = "( action='error' OR action='infected' OR action like '%failed%' OR action like '%Failed%' )";
        }

        $sql = implode(' AND ', $joins);
        return array($sql, $params);
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
            $joins[] = "timecreated > :date AND timecreated < :enddate";
            $params['date'] = $this->filterparams->date;
            $params['enddate'] = $this->filterparams->date + DAYSECS; // Show logs only for the selected date.
        }

        if (!empty($this->filterparams->userid)) {
            $joins[] = "userid = :userid";
            $params['userid'] = $this->filterparams->userid;
        }

        if (!($this->filterparams->logreader instanceof logstore_legacy\log\store)) {
            // Filter out anonymous actions, this is N/A for legacy log because it never stores them.
            $joins[] = "anonymous = 0";
        }

        $selector = implode(' AND ', $joins);

        if (!$this->is_downloading()) {
            $total = $this->filterparams->logreader->get_events_select_count($selector, $params);
            $this->pagesize($pagesize, $total);
        } else {
            $this->pageable(false);
        }

        $this->rawdata = $this->filterparams->logreader->get_events_select($selector, $params, $this->filterparams->orderby,
                $this->get_page_start(), $this->get_page_size());

        // Set initial bars.
        if ($useinitialsbar && !$this->is_downloading()) {
            $this->initialbars($total > $pagesize);
        }

        // Update list of users and courses list which will be displayed on log page.
        $this->update_users_and_courses_used();
    }

    /**
     * Helper function to create list of course shortname and user fullname shown in log report.
     * This will update $this->userfullnames and $this->courseshortnames array with userfullname and courseshortname (with link),
     * which will be used to render logs in table.
     */
    public function update_users_and_courses_used() {
        global $SITE, $DB;

        $this->userfullnames = array();
        $this->courseshortnames = array($SITE->id => $SITE->shortname);
        $userids = array();
        $courseids = array();
        // For each event cache full username and course.
        // Get list of userids and courseids which will be shown in log report.
        foreach ($this->rawdata as $event) {

            $logextra = $event->get_logextra();
            if (!empty($event->userid) && !in_array($event->userid, $userids)) {
                $userids[] = $event->userid;
            }
            if (!empty($logextra['realuserid']) && !in_array($logextra['realuserid'], $userids)) {
                $userids[] = $logextra['realuserid'];
            }
        }
        // Get user fullname and put that in return list.
        if (!empty($userids)) {
            list($usql, $uparams) = $DB->get_in_or_equal($userids);
            $users = $DB->get_records_sql("SELECT id," . get_all_user_name_fields(true) . " FROM {user} WHERE id " . $usql,
                    $uparams);
            foreach ($users as $userid => $user) {
                $this->userfullnames[$userid] = fullname($user);
            }
        }
    }
}
