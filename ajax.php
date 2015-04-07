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
 * @package    tool_coursestore
 * @author     Tim Price <tim.price@catalyst-au.net>
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('MOODLE_INTERNAL', 1);
define('AJAX_SCRIPT', 1);

require_once('../../../config.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

$action = required_param('action', PARAM_TEXT);

// Prevent checks from returning NaN if the user has since logged out.
isloggedin() || die;

$response = null;

$PAGE->set_context(null);
echo $OUTPUT->header();
@header('Content-type: application/json; charset=utf-8');


switch ($action) {
    case 'conncheck':
        $context = context_system::instance();

        // Get required config variables.
        $urltarget = get_config('tool_coursestore', 'url');
        $timeout = get_config('tool_coursestore', 'timeout');
        $sesskey = tool_coursestore::get_session();

        // Initialise, check connection.
        $wsmanager = new coursestore_ws_manager($urltarget, $timeout);

        $response = tool_coursestore::check_connection($wsmanager, $sesskey) ? 1 : 0;
        $wsmanager->close();

        break;
    case 'speedtest':
        $context = context_system::instance();

        // Get required config variables.
        $urltarget = get_config('tool_coursestore', 'url');
        $timeout = get_config('tool_coursestore', 'timeout');
        $sesskey = get_config('tool_coursestore', 'sessionkey');

        // Initialise, check connection.
        $wsmanager = new coursestore_ws_manager($urltarget, $timeout);
        $testsize = 256;

        $response = tool_coursestore::check_connection_speed($wsmanager, $testsize, 1, 5, $sesskey);
        $wsmanager->close();
    default:
        break;
}
echo print_r($response, true);
