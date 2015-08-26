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
 * Main page for user-facing course bank interface
 *
 * @package    tool_coursebank
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/filters/lib.php');

$sort         = optional_param('sort', 'status', PARAM_ALPHANUM);
$dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = optional_param('perpage', 50, PARAM_INT);

$context = context_system::instance();

require_login(null, false);
require_capability('tool/coursebank:view', $context);

// This means that it will not have a side menu bar to the left.

$url = new moodle_url('/admin/tool/coursebank/transfer_report.php');
$PAGE->set_url($url);
$PAGE->set_context($context);

$header = get_string('backupsummary', 'tool_coursebank');
$PAGE->set_title($header);
echo $OUTPUT->header();
echo $OUTPUT->heading($header);

// Filters.
$filterparams = array('status' => 0, 'coursefullname' => 1, 'backupfilename' => 1, 'filesize' => 1, 'filetimemodified' => 1);
$filtering = new coursebank_filtering('summary', $filterparams);
list($extraselect, $extraparams) = $filtering->get_sql_filter();
$sort = array('coursefullname', 'filetimemodified', 'backupfilename', 'filesize', 'timetransferstarted', 'timecompleted', 'status');
$results = tool_coursebank::get_summary_data($sort, $dir, $extraselect, $extraparams, $page, $perpage, $sort);

// Display filters.
$filtering->display_add();
$filtering->display_active();

// Display table.
$renderer = $PAGE->get_renderer('tool_coursebank');
echo $renderer->course_bank_main($results['results'], $sort, $dir, $page, $perpage);
echo $OUTPUT->paging_bar($results['count'], $page, $perpage, $url);
// Footer.
echo $OUTPUT->footer();
