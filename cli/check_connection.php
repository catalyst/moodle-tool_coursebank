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
 * @package    tool_coursebank
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'../../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');

// Get required config variables.
$urltarget = get_config('tool_coursebank', 'url');
$timeout = get_config('tool_coursebank', 'timeout');
$maxatt = get_config('tool_coursebank', 'maxatt');
$sessionkey = tool_coursebank::get_session();

// Initialise, check connection.
$wsmanager = new coursebank_ws_manager($urltarget, $timeout);

// Initialise, check connection.
if (!tool_coursebank::check_connection($wsmanager, $sessionkey)) {
    // Connection check failed.
    mtrace(get_string('conncheckfail', 'tool_coursebank', $urltarget));
} else {
    mtrace(get_string('connchecksuccess', 'tool_coursebank', $urltarget));
}
