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
 * Page that handles performing connection checks
 *
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('../../../config.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

defined('MOODLE_INTERNAL') || die;

$context = context_system::instance();
require_login();

// Get required config variables
$urltarget = get_config('tool_coursestore', 'url');
$conntimeout = get_config('tool_coursestore', 'conntimeout');
$timeout = get_config('tool_coursestore', 'timeout');
$maxatt = get_config('tool_coursestore', 'maxatt');

// Initialise, check connection
$ws_manager = new coursestore_ws_manager($urltarget, $conntimeout, $timeout);
$check = array('operation' => 'check');

if($ws_manager->send($check)) {
    $params = array('section' => 'coursestore_settings', 'result' => 1);
}
else {
    $params = array('section' => 'coursestore_settings', 'result' => 0);
}

$redirect = new moodle_url('/admin/settings.php', $params);

redirect($redirect);
