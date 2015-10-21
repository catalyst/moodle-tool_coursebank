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
 * Report page
 *
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/classes/renderable.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/filters/lib.php');
require_once($CFG->dirroot.'/lib/tablelib.php');

$sort      = optional_param('sort', 'status', PARAM_ALPHANUM);
$dir       = optional_param('dir', 'ASC', PARAM_ALPHA);
$page      = optional_param('page', 0, PARAM_INT);
$perpage   = optional_param('perpage', 50, PARAM_INT);
$date      = optional_param('date', 0, PARAM_INT); // Date to display.
$user      = optional_param('user', 0, PARAM_INT); // User to display.
$type      = optional_param('type', '', PARAM_ALPHA);
$chooselog = optional_param('chooselog', false, PARAM_BOOL);
$logformat = optional_param('download', '', PARAM_ALPHA);
$logreader = optional_param('logreader', '', PARAM_COMPONENT); // Reader which will be used for displaying logs.

$params = array();

if ($sort !== '') {
    $params['sort'] = $sort;
}
if ($dir !== '') {
    $params['dir'] = $dir;
}
if ($date !== 0) {
    $params['date'] = $date;
}
if ($user !== 0) {
    $params['user'] = $user;
}
if ($type !== '') {
    $params['type'] = $type;
}
if ($page !== '0') {
    $params['page'] = $page;
}
if ($perpage !== '10') {
    $params['perpage'] = $perpage;
}
if ($chooselog) {
    $params['chooselog'] = $chooselog;
}
if ($logformat !== '') {
    $params['download'] = $logformat;
}
if ($logreader !== '') {
    $params['logreader'] = $logreader;
}

$context = context_system::instance();

require_login(null, false);
require_capability('tool/coursebank:viewlogs', $context);

admin_externalpage_setup('tool_coursebank_report');

$url = new moodle_url('/admin/tool/coursebank/report.php', $params);
$PAGE->set_url($url);
$PAGE->set_context($context);
$output = $PAGE->get_renderer('tool_coursebank');

// Check if moodle is older then 2.7.x.
if ((float)$CFG->version < 2014051200) {
    $legacy = true;
    $order = 'time DESC';
} else {
    $legacy = false;
    $order = 'timecreated DESC';
}

$reportlog = new tool_coursebank_renderable($legacy, $logreader, $user, $chooselog, true, $url, $date, $type,
        $logformat, $page, $perpage, $order);

if (!empty($chooselog)) {
    // Delay creation of table, till called by user with filter.
    $reportlog->setup_table();

    if (empty($logformat)) {
        echo $output->header();
        $dateinfo = get_string('alldays');
        if ($date) {
            $dateinfo = userdate($date, get_string('strftimedaydate'));
        }
        echo $output->render($reportlog);
    } else {
        if (class_exists('\core\session\manager')) {
            \core\session\manager::write_close();
        } else {
            session_get_instance()->write_close();
        }
        $reportlog->download();
        exit();
    }
} else {
    echo $output->header();
    echo $output->heading(get_string('chooselogs') .':');
    echo $output->render($reportlog);
}


// Footer.
echo $output->footer();
