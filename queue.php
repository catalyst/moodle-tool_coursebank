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
$action       = optional_param('action', '', PARAM_ALPHANUM);
$id           = optional_param('id', 0, PARAM_INT);
$confirm      = optional_param('confirm', '', PARAM_ALPHANUM);

$context = context_system::instance();
require_login(null, false);
require_capability('tool/coursebank:view', $context);

admin_externalpage_setup('tool_coursebank_queue');

$header = get_string('backupqueue', 'tool_coursebank');

$urlparams = array(
    'sort'    => $sort,
    'dir'     => $dir,
    'page'    => $page,
    'perpage' => $perpage,
);
$url = new moodle_url('/admin/tool/coursebank/queue.php', $urlparams);

// Get allowed actions.
$actions = tool_coursebank::get_actions();

// Do an action.
if (array_key_exists($action, $actions) and $id > 0) {

    require_capability('tool/coursebank:edit', $context);
    $coursebackup = $DB->get_record('tool_coursebank', array('id' => $id), '*', MUST_EXIST);

    if ($confirm != md5($coursebackup->id)) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading($header);
        $optionsyes = array('action' => $action, 'id' => $id, 'confirm' => md5($coursebackup->id), 'sesskey' => sesskey());
        $confirmstring = get_string('check_'.$action, 'tool_coursebank', $coursebackup->backupfilename);
        echo $OUTPUT->confirm($confirmstring, new moodle_url($url, $optionsyes), $url);
        echo $OUTPUT->footer();
        die;
    } else if (data_submitted() and confirm_sesskey()) {
        if (!tool_coursebank::user_update_status($coursebackup, $actions[$action])) {
            print_error('errorupdatingstatus', 'tool_coursebank',  new moodle_url('/admin/tool/coursebank/queue.php'));
        }
    }
}

$PAGE->set_url($url);
$PAGE->set_context($context);

$PAGE->set_title($header);
echo $OUTPUT->header();
echo $OUTPUT->heading($header);

// Filters.
$filterparams = array(
    'status' => 0,
    'coursefullname' => 1,
    'backupfilename' => 1,
    'filesize' => 1,
    'filetimemodified' => 1
);
$filtering = new coursebank_filtering('queue', $filterparams);
$extra = 'status != :status_ext1 AND status != :status_ext2';
$params = array(
    'status_ext1' => tool_coursebank::STATUS_FINISHED,
    'status_ext2' => tool_coursebank::STATUS_CANCELLED
);
list($extraselect, $extraparams) = $filtering->get_sql_filter($extra, $params);

$results = tool_coursebank::get_summary_data($sort, $dir, $extraselect, $extraparams, $page, $perpage);
// Display filters.
$filtering->display_add();
$filtering->display_active();

// Display table.
$renderer = $PAGE->get_renderer('tool_coursebank');
echo $renderer->course_bank_queue($results['results'], $sort, $dir, $page, $perpage);
echo $OUTPUT->paging_bar($results['count'], $page, $perpage, $url);
// Footer.
echo $OUTPUT->footer();
