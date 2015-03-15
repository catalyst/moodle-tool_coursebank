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
 * Course store main page renderer
 *
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

class tool_coursestore_renderer extends plugin_renderer_base {
    public function course_store_main($connectioncheck) {
        global $CFG;
        if(!$connectioncheck) {
            $redirect = new moodle_url(
                    $CFG->wwwroot.'/admin/tool/coursestore/check_connection.php',
                    array('conn' => true)
            );
            $html = $this->single_button($redirect, 'Check connection', 'get');
        }
        return $html;
    }
}
