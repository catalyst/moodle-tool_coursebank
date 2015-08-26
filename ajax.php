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
 * This file is used for connection test AJAX callbacks.
 *
 * @package    tool_coursebank
 * @author     Tim Price <tim.price@catalyst-au.net>
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', 1);

require_once('../../../config.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');

$action = required_param('action', PARAM_TEXT);
$response = null;

// If users session has expired, redirect them to the login page.
if (!isloggedin()) {
    $redirect = true;
    $redirecturl = $CFG->wwwroot . '/login/index.php';
} else {
    $redirect = false;
    $redirecturl = null;
}

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/admin/tool/coursebank/ajax.php');


echo $OUTPUT->header();

switch ($action) {
    case 'conncheck':
        // Get required config variables.
        $urltarget = get_config('tool_coursebank', 'url');
        $timeout = get_config('tool_coursebank', 'timeout');
        $sesskey = tool_coursebank::get_session();

        // Initialise, check connection.
        $wsmanager = new coursebank_ws_manager($urltarget, $timeout);

        $response = array();
        $response[] = tool_coursebank::check_connection($wsmanager, $sesskey) ? 1 : 0;
        $wsmanager->close();

        break;
    case 'speedtest':
        // Get required config variables.
        $urltarget = get_config('tool_coursebank', 'url');
        $timeout = get_config('tool_coursebank', 'timeout');
        $sesskey = get_config('tool_coursebank', 'sessionkey');

        // Initialise, check connection.
        $wsmanager = new coursebank_ws_manager($urltarget, $timeout);

        $response = array();
        $response[] = tool_coursebank::check_connection_speed($wsmanager, 4, $sesskey);
        $wsmanager->close();
    default:
        break;
}
$response[] = $redirect;
$response[] = $redirecturl;
echo json_encode($response);
