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
 * The EVENTNAME event.
 *
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_coursestore\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The EVENTNAME event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      This event is to be triggered whenever a connection check call is made.
 * }
 *
 * @package    tool_coursestore
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class connection_checked extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'r'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return get_string('eventconnectionchecked', 'tool_coursestore');
    }

    public function get_description() {
        if ($this->data['other']['conncheckaction'] == 'speedtest') {
            if (isset($this->data['other']['speed'])) {
                if((int) $this->data['other']['speed'] === 0) {
                    return "Connection check failed.";
                }
                return "Connection speed tested - approximate speed: ".
                        $this->data['other']['speed'] . " kbps.";
            }
        } else {
            if (isset($this->data['other']['status'])) {
                if($this->data['other']['status']) {
                    return "Connection check passed.";
                }
            }
                return "Connection check failed.";
        }
    }

    public function get_url() {
        if (isset($this->data['other']['conncheckaction'])) {
            $params = array('action' => $this->data['other']['conncheckaction']);
        } else {
            $params = array('action' => 'conncheck');
        }
        return new \moodle_url('/admin/tool/coursestore/check_connection.php', $params);
    }
}
