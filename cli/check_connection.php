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
 * Basic script to test connection to remote server
 *
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

define('CLI_SCRIPT', true);

require(__DIR__.'../../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

// Get required config variables
$urltarget = get_config('tool_coursestore', 'url');
$timeout = get_config('tool_coursestore', 'timeout');
$maxatt = get_config('tool_coursestore', 'maxatt');

// Initialise, check connection
$ws_manager = new coursestore_ws_manager($urltarget, $timeout);
$check = array('operation' => 'check');
if(!$ws_manager->send($check)) {
    //Connection check failed
    mtrace(get_string('conncheckfail', 'tool_coursestore'));
}
else {
    mtrace(get_string('connchecksuccess', 'tool_coursestore'));
}
