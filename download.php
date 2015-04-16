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
 * Main page for user-facing download interface
 *
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @author     Dmitrii Metelkin <adamr@catalyst-au.net>*
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/filters/lib.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/lib.php');

defined('MOODLE_INTERNAL') || die;

$download     = optional_param('download', 0, PARAM_INT);
$file         = optional_param('file', 0, PARAM_INT);
$sort         = optional_param('sort', 'coursefullname', PARAM_ALPHANUM);
$dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = optional_param('perpage', 50, PARAM_INT);

$context = context_system::instance();
require_login(null, false);
require_capability('tool/coursestore:view', $context);

admin_externalpage_setup('tool_coursestore_download');

$params = array(
    'download' => $download,
    'file' => $file,
    'sort' => $sort,
    'dir' => $dir,
    'page' => $page,
    'perpage' => $perpage
);
$url = new moodle_url('/admin/tool/coursestore/download.php', $params);
$urltarget = get_config('tool_coursestore', 'url');
$timeout = get_config('tool_coursestore', 'timeout');
$wsman = new coursestore_ws_manager($urltarget, $timeout);
if (!$sesskey = tool_coursestore::get_session()) {
    $hash = get_config('tool_coursestore', 'authtoken');
    if (!$wsman->post_session($hash)) {
        $redirecturl = new moodle_url(
                '/admin/tool/coursestore/check_connection.php',
                array('action' => 'conncheck')
        );
        redirect($redirecturl, '', 0);
    }
    $sesskey = tool_coursestore::get_session();
}

// Downloading.
if ($download == 1 and intval($file) > 0) {
    require_capability('tool/coursestore:download', $context);
    $dlresponse = $wsman->get_backup($sesskey, $file, true);
    $errorurl = $url . "?sort=$sort&amp;dir=$dir&amp;page=$page&amp;perpage=$perpage";

    $error = tool_coursestore_error::is_response_backup_download_error($dlresponse);
    coursestore_logging::log_backup_download($dlresponse, $file);

    if (!$error) {
        redirect($dlresponse->body->url, '', 0);
    } else {
        print_error('errordownloading', 'tool_coursestore', $errorurl);
    }
}

$PAGE->set_url($url);
$PAGE->set_context($context);

$header = get_string('downloadsummary', 'tool_coursestore');
$PAGE->set_title($header);
echo $OUTPUT->header();
echo $OUTPUT->heading($header);

$filterparams = array(
        'coursefullname' => 0,
        'backupfilename' => 1,
        'filesize' => 1,
        'filetimemodified' => 1
);
$filtering = new coursestore_filtering('download', $filterparams);
$extraparams = $filtering->get_param_filter();

$response = $wsman->get_downloads($sesskey, $extraparams, $sort, $dir, $page, $perpage);
if ($response->httpcode != $wsman::WS_HTTP_OK) {
    $redirecturl = new moodle_url(
            '/admin/tool/coursestore/check_connection.php',
            array('action' => 'conncheck')
    );
    redirect($redirecturl, '', 0);
}

$count = $wsman->get_downloadcount($sesskey);
if (isset($count->body->error)) {
    $redirecturl = new moodle_url(
            '/admin/tool/coursestore/check_connection.php',
            array('action' => 'conncheck')
    );
    redirect($redirecturl, '', 0);
}

$filtering->display_add();
$filtering->display_active();
$renderer = $PAGE->get_renderer('tool_coursestore');
echo $renderer->course_store_downloads($response->body, $sort, $dir, $page, $perpage);
echo $OUTPUT->paging_bar($count->body->backupcount, $page, $perpage, $url);

echo $OUTPUT->footer();
