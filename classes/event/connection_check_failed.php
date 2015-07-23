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
 * connection_checked
 *
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_coursebank\event;
defined('MOODLE_INTERNAL') || die();
/**
 * connection_checked
 *
 * This event is to be triggered whenever a connection check failed.
 *
 * @property-read array $other {
 *      conncheckaction => set to speedtest, if checking speed. set to 'conncheck', if checking connection.
 *      status          => Connection check result
 *      speed           => Resulting connection speed
 *      error           => error code
 *      error_desc      => error description
 * }
 *
 * @package    tool_coursebank
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class connection_check_failed extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'r'; // Note: c(reate), r(ead), u(pdate), d(elete).
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return get_string('eventconnectioncheckfailed', 'tool_coursebank');
    }

    public function get_description() {
        if ($this->data['other']['conncheckaction'] == 'speedtest') {
            if (isset($this->data['other']['speed'])) {
                if ((int) $this->data['other']['speed'] === 0) {
                    $desc = "Connection check failed.";
                }

                if (isset($this->data['other']['error'])) {
                    $desc .= " Error code: " .  $this->data['other']['error'];
                }
                if (isset($this->data['other']['error_desc'])) {
                    $desc .= " Error text: " .  $this->data['other']['error_desc'];
                }
                return $desc;
            }
        } else {
            // It's failed.
            $desc = "Connection check failed.";

            if (isset($this->data['other']['error'])) {
                $desc .= " Error code: " .  $this->data['other']['error'];
            }
            if (isset($this->data['other']['error_desc'])) {
                $desc .= " Error text: " .  $this->data['other']['error_desc'];
            }
            return $desc;

        }
    }

    public function get_url() {
        if (isset($this->data['other']['conncheckaction'])) {
            $params = array('action' => $this->data['other']['conncheckaction']);
        } else {
            $params = array('action' => 'conncheck');
        }
        return new \moodle_url('/admin/tool/coursebank/check_connection.php', $params);
    }
}
