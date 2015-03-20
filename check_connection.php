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
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

$context = context_system::instance();
require_login();
admin_externalpage_setup('tool_coursestore');

$url = new moodle_url('/admin/tool/coursestore/check_connection.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$action = required_param('action', PARAM_TEXT);

if(!in_array($action, array('conncheck', 'speedtest'))) {
    $action = 'conncheck';
}

switch ($action) {
    case 'conncheck':
        $header = get_string('connchecktitle', 'tool_coursestore');

        // Get required config variables
        $urltarget = get_config('tool_coursestore', 'url');
        $conntimeout = get_config('tool_coursestore', 'conntimeout');
        $timeout = get_config('tool_coursestore', 'timeout');

        // Initialise, check connection
        $ws_manager = new coursestore_ws_manager($urltarget, $conntimeout, $timeout);

        $result = tool_coursestore::check_connection($ws_manager) ? 1 : 0;
        if(tool_coursestore::check_connection($ws_manager)) {
            $msgtype = 'success';
        }
        else {
            $msgtype = 'fail';
        }
        $content = get_string('conncheck'.$msgtype, 'tool_coursestore');
        $ws_manager->close();

        break;
    case 'speedtest':
        $header = get_string('speedtesttitle', 'tool_coursestore');

        // Get required config variables
        $urltarget = get_config('tool_coursestore', 'url');
        $conntimeout = get_config('tool_coursestore', 'conntimeout');
        $timeout = get_config('tool_coursestore', 'timeout');

        // Initialise, check connection
        $ws_manager = new coursestore_ws_manager($urltarget, $conntimeout, $timeout);

        $result = tool_coursestore::check_connection_speed($ws_manager, 256, 1, 5);
        $ws_manager->close();

        $add ='';
        if($result >= 256) {
            $msgtype = 'success';
        }
        else if($result == 0) {
            $msgtype = 'fail';
        }
        else {
            $msgtype = 'slow';
            $add = (string) $result.' kbps.';
        }

        $content = get_string('speedtest'.$msgtype, 'tool_coursestore').$add;

    default:
        break;
}
$renderer = $PAGE->get_renderer('tool_coursestore');

$PAGE->set_title($header);
echo $OUTPUT->header();
echo $OUTPUT->heading($header);
echo $OUTPUT->box_start();
echo $renderer->course_store_check_notification($action, $msgtype, $content, false);
$returnurl = new moodle_url(
        $CFG->wwwroot.'/admin/settings.php',
        array('section' => 'coursestore_settings')
);
echo $renderer->single_button(
        $returnurl,
        get_string('return', 'tool_coursestore'),
        'get'
);
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
