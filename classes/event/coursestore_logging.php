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
 * coursestore_logging
 *
 * @package    tool_coursestore
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_coursestore\event;
defined('MOODLE_INTERNAL') || die();
/**
 * coursestore_logging
 *
 * @property-read array $other {
 *      courseid => Moodle course ID
 *      module   => Name of the module
 *      action   => Name of the action
 *      url      => URL
 *      info     => Error text
 *      userid   => Moodle User ID
 * }
 *
 *
 * @package    coursestore_logging
 * @author     Dmitrii Metelkin <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class coursestore_logging extends \core\event\base {
    static $action;

    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        self::$action = $this->data['other']['action'];
    }

    public static function get_name() {
        return get_string('coursestorelogging', 'tool_coursestore');
    }

    public function get_description() {
        $desc = $this->data['other']['info'];
        return $desc;
    }

    public function get_url() {
        if (!empty($this->data['other']['url'])) {
            return new \moodle_url($this->data['other']['url']);
        } else {
            return new \moodle_url('/admin/tool/coursestore/index.php');
        }

    }
}
