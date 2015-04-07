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

defined('MOODLE_INTERNAL') || die;

$download     = optional_param('download', 0, PARAM_INT);
$sort         = optional_param('sort', 'coursename', PARAM_ALPHANUM);
$dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = optional_param('perpage', 50, PARAM_INT);        // how many per page

$context = context_system::instance();
require_login();

admin_externalpage_setup('tool_coursestore_download');

$url = new moodle_url('/admin/tool/coursestore/download.php');
$PAGE->set_url($url);
$PAGE->set_context($context);

$header = get_string('pluginname', 'tool_coursestore');
$PAGE->set_title($header);

$renderer = $PAGE->get_renderer('tool_coursestore');
echo $OUTPUT->header();

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
if (!$response = $wsman->get_downloads($sesskey)) {
    $redirecturl = new moodle_url(
            '/admin/tool/coursestore/check_connection.php',
            array('action' => 'conncheck')
    );
    redirect($redirecturl, '', 0);
}
echo $renderer->course_store_downloads($response['body'], $sort, $dir, $page, $perpage);
echo $OUTPUT->footer();
