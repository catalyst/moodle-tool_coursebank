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
 * This file is used to provide connection check functionality for users with
 * javascript disabled.
 *
 * @package    tool_coursebank
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');

$context = context_system::instance();
require_login();
admin_externalpage_setup('tool_coursebank_download');

$url = new moodle_url('/admin/tool/coursebank/check_connection.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$action = required_param('action', PARAM_TEXT);

if (!in_array($action, array('conncheck', 'speedtest'))) {
    $action = 'conncheck';
}

switch ($action) {
    case 'conncheck':
        $header = get_string('connchecktitle', 'tool_coursebank');

        // Get required config variables.
        $urltarget = get_config('tool_coursebank', 'url');
        $timeout = get_config('tool_coursebank', 'timeout');
        $sesskey = get_config('tool_coursebank', 'sessionkey');

        // Initialise, check connection.
        $wsmanager = new coursebank_ws_manager($urltarget, $timeout);

        $msgtype = tool_coursebank::check_connection($wsmanager, $sesskey) ? 'success' : 'fail';
        $content = get_string('conncheck' . $msgtype, 'tool_coursebank', $urltarget);
        $wsmanager->close();

        break;
    case 'speedtest':
        $header = get_string('speedtesttitle', 'tool_coursebank');

        // Get required config variables.
        $urltarget = get_config('tool_coursebank', 'url');
        $timeout = get_config('tool_coursebank', 'timeout');
        $sesskey = get_config('tool_coursebank', 'sessionkey');

        // Initialise, check connection.
        $wsmanager = new coursebank_ws_manager($urltarget, $timeout);

        $result = tool_coursebank::check_connection_speed($wsmanager, 1, $sesskey);
        $wsmanager->close();

        $add = '';
        if ($result == 0) {
            $msgtype = 'fail';
        } else {
            $msgtype = $result >= 256 ? 'success' : 'slow';
            $add = (string) $result['speed'] .' kbps.';
        }

        $content = get_string('speedtest' . $msgtype, 'tool_coursebank', $urltarget) . $add;

    default:
        break;
}
$renderer = $PAGE->get_renderer('tool_coursebank');

$PAGE->set_title($header);
echo $OUTPUT->header();
echo $OUTPUT->heading($header);
echo $OUTPUT->box_start();
echo $renderer->course_bank_check_notification($action, $msgtype, $content, false);
$returnurl = new moodle_url(
        $CFG->wwwroot.'/admin/settings.php',
        array('section' => 'coursebank_settings')
);
echo $renderer->single_button(
        $returnurl,
        get_string('return', 'tool_coursebank'),
        'get'
);
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
