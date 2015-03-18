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
 * @package blocks-ouaforum
* @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
* @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
// This file is used for AJAX callbacks.

define('MOODLE_INTERNAL', 1);
define('AJAX_SCRIPT', 1);

require_once('../../../config.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

$action = required_param('action', PARAM_TEXT);

require_login();

$response = null;

$PAGE->set_context(null);
echo $OUTPUT->header();
@header('Content-type: application/json; charset=utf-8');


switch ($action) {
    case 'conncheck':
        $context = context_system::instance();

        // Get required config variables
        $urltarget = get_config('tool_coursestore', 'url');
        $conntimeout = get_config('tool_coursestore', 'conntimeout');
        $timeout = get_config('tool_coursestore', 'timeout');
        $maxatt = get_config('tool_coursestore', 'maxatt');

        // Initialise, check connection
        $ws_manager = new coursestore_ws_manager($urltarget, $conntimeout, $timeout);
        $check = array('operation' => 'check');

        if($ws_manager->send($check)) {
            $result = "1";
        } else {
            $result = "0";
        }

        $response = $result;
        break;
    default:
        break;
}

echo json_encode($response);
