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
 * Report renderable class.
 *
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/admin/tool/coursebank/classes/table_log_lagacy.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/classes/table_log.php');
/**
 * Report renderable class.
 *
 */
class tool_coursebank_renderable implements renderable {
    /**
     * @var manager log manager
     */
    protected $logmanager;

    /**
     * @var string selected log reader pluginname
     */
    public $selectedlogreader = null;

    /**
     * @var int page number
     */
    public $page;

    /**
     * @var int perpage records to show
     */
    public $perpage;

    /**
     * @var moodle_url url of report page
     */
    public $url;

    /**
     * @var int selected date from which records should be displayed
     */
    public $date;

    /**
     * @var int selected user id for which logs are displayed
     */
    public $userid;

    /**
     * @var string selected type of logs to be displayed
     */
    public $type;

    /**
     * @var bool show users
     */
    public $showusers;

    /**
     * @var bool show report
     */
    public $showreport;

    /**
     * @var bool show selector form
     */
    public $showselectorform;

    /**
     * @var string selected log format
     */
    public $logformat;

    /**
     * @var string order to sort
     */
    public $order;

    /**
     * @var table_log table log which will be used for rendering logs
     */
    public $tablelog;

    /**
     * @var bool shows if we want to use table_log_lagacy as a table class
     */
    public $legacy;

    /**
     * Constructor.
     * @param bool $legacy shows if we want to use table_log_lagacy as a table class
     * @param string $logreader (optional)reader pluginname from which logs will be fetched.
     * @param bool $showreport (optional) show report.
     * @param bool $showselectorform (optional) show selector form.
     * @param moodle_url|string $url (optional) page url.
     * @param int $date date (optional) timestamp of start of the day for which logs will be displayed.
     * @param string $type type log of records to get.
     * @param string $logformat log format.
     * @param int $page (optional) page number.
     * @param int $perpage (optional) number of records to show per page.
     * @param string $order (optional) sortorder of fetched records
     */
    public function __construct($lagacy, $logreader = "", $userid = 0, $showreport = true, $showselectorform = true, $url = "",
            $date = 0, $type = "", $logformat='showashtml', $page = 0, $perpage = 100, $order = "timecreated ASC") {

        global $PAGE;

        $this->lagacy = $lagacy;

        // Use first reader as selected reader, if not passed.
        if (empty($logreader)) {
            $readers = $this->get_readers();
            if (!empty($readers)) {
                reset($readers);
                $logreader = key($readers);
            } else {
                $logreader = null;
            }
        }
        // Use page url if empty.
        if (empty($url)) {
            $url = new moodle_url($PAGE->url);
        } else {
            $url = new moodle_url($url);
        }
        $this->selectedlogreader = $logreader;
        $url->param('logreader', $logreader);

        $this->date = $date;
        $this->type = $type;
        $this->userid = $userid;
        $this->page = $page;
        $this->perpage = $perpage;
        $this->url = $url;
        $this->order = $order;
        $this->showreport = $showreport;
        $this->showselectorform = $showselectorform;
        $this->logformat = $logformat;
    }
    /**
     * Get a list of enabled sql_select_reader objects/name
     *
     * @param bool $nameonly if true only reader names will be returned.
     * @return array core\log\sql_select_reader object or name.
     */
    public function get_readers($nameonly = false) {
        if ($this->lagacy) {
            return false;
        }

        if (!isset($this->logmanager)) {
            $this->logmanager = get_log_manager();
        }

        $readers = $this->logmanager->get_readers('core\log\sql_select_reader');
        if ($nameonly) {
            foreach ($readers as $pluginname => $reader) {
                $readers[$pluginname] = $reader->get_name();
            }
        }
        return $readers;
    }
    /**
     * Return selected user fullname.
     *
     * @return string user fullname.
     */
    public function get_selected_user_fullname() {
        $user = core_user::get_user($this->userid);
        return fullname($user);
    }
    /**
     * Return list of users.
     *
     * @return array list of users.
     */
    public function get_user_list() {
        global $CFG, $SITE;

        $courseid = $SITE->id;

        $context = context_course::instance($courseid);
        $limitfrom = empty($this->showusers) ? 0 : '';
        $limitnum  = empty($this->showusers) ? COURSE_MAX_USERS_PER_DROPDOWN + 1 : '';
        // Function get_all_user_name_fields is missing from 2.4, so we need to do it manually.
        // Check if moodle is older then 2.7.x.
        if ((float)$CFG->version < 2014051200) {
            $alternatenames = array('firstname' => 'firstname',
                                    'lastname' => 'lastname');
            // Create an sql field snippet if requested.
            foreach ($alternatenames as $key => $altname) {
                $alternatenames[$key] = 'u.' . $altname;
            }
            $alternatenames = implode(',', $alternatenames);

            $courseusers = get_enrolled_users($context, '', 0, 'u.id, ' . $alternatenames,
                    null, $limitfrom, $limitnum);
        } else {
            $courseusers = get_enrolled_users($context, '', 0, 'u.id, ' . get_all_user_name_fields(true, 'u'),
                    null, $limitfrom, $limitnum);
        }

        if (count($courseusers) < COURSE_MAX_USERS_PER_DROPDOWN && !$this->showusers) {
            $this->showusers = 1;
        }

        $users = array();
        if ($this->showusers) {
            if ($courseusers) {
                foreach ($courseusers as $courseuser) {
                     $users[$courseuser->id] = fullname($courseuser, has_capability('moodle/site:viewfullnames', $context));
                }
            }
            $users[$CFG->siteguest] = get_string('guestuser');
        }
        return $users;
    }
    /**
     * Return list of date options.
     *
     * @return array date options.
     */
    public function get_date_options() {
        global $SITE;

        $strftimedate = get_string("strftimedate");
        $strftimedaydate = get_string("strftimedaydate");

        // Get all the possible dates.
        // Note that we are keeping track of real (GMT) time and user time.
        // User time is only used in displays - all calcs and passing is GMT.
        $timenow = time(); // GMT.

        // What day is it now for the user, and when is midnight that day (in GMT).
        $timemidnight = usergetmidnight($timenow);

        // Put today up the top of the list.
        $dates = array("$timemidnight" => get_string("today").", ".userdate($timenow, $strftimedate) );

        // If course is empty, get it from frontpage.
        $course = $SITE;
        if (!empty($this->course)) {
            $course = $this->course;
        }
        if (!$course->startdate or ($course->startdate > $timenow)) {
            $course->startdate = $course->timecreated;
        }

        $numdates = 1;
        while ($timemidnight > $course->startdate and $numdates < 365) {
            $timemidnight = $timemidnight - 86400;
            $timenow = $timenow - 86400;
            $dates["$timemidnight"] = userdate($timenow, $strftimedaydate);
            $numdates++;
        }
        return $dates;
    }
    /**
     * Helper function to return list of activities to show in selection filter.
     *
     * @return array list of activities.
     */
    public function get_type_list() {
        $activities = array();
        $activities["errors"] = get_string("errorsonly", 'tool_coursebank');

        return $activities;
    }
    /**
     * Setup table log.
     */
    public function setup_table() {
        if (empty($this->lagacy)) {
            $readers = $this->get_readers();
        }

        $filter = new \stdClass();
        if (!empty($this->course)) {
            $filter->courseid = $this->course->id;
        } else {
            $filter->courseid = 0;
        }

        $filter->userid = $this->userid;
        $filter->date = $this->date;
        $filter->type = $this->type;
        $filter->orderby = $this->order;

        if (empty($this->lagacy)) {
            $filter->logreader = $readers[$this->selectedlogreader];
            $this->tablelog = new tool_coursebank_table_log('coursebank_report', $filter);
        } else {
            $filter->logreader = null;
            $this->tablelog = new tool_coursebank_table_log_legacy('coursebank_report', $filter);
        }

        $this->tablelog->define_baseurl($this->url);
        $this->tablelog->is_downloadable(true);
        $this->tablelog->show_download_buttons_at(array(TABLE_P_BOTTOM));
    }
    /**
     * Download logs in specified format.
     */
    public function download() {
        $filename = 'coursebank_report_' . userdate(time(), get_string('backupnameformat', 'langconfig'), 99, false);
        $this->tablelog->is_downloading($this->logformat, $filename);
        $this->tablelog->out($this->perpage, false);
    }
}
