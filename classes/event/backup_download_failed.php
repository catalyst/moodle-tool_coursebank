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
 * backup_download_failed
 *
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_coursebank\event;
defined('MOODLE_INTERNAL') || die();
/**
 * backup_download_failed
 *
 * This event is to be triggered whenever backup download failed.
 *
 *
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class backup_download_failed extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'r'; // Note: c(reate), r(ead), u(pdate), d(elete).
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return get_string('eventbackupdownloadfailed', 'tool_coursebank');
    }

    public function get_description() {
        $desc = $this->data['other']['info'];

        if (isset($this->data['other']['error'])) {
            $desc .= " Error code: " .  $this->data['other']['error'];
        }
        if (isset($this->data['other']['error_desc'])) {
            $desc .= " Error text: " .  $this->data['other']['error_desc'];
        }

        return $desc;
    }

    public function get_url() {
        return new \moodle_url('/admin/tool/coursebank/index.php');
    }
}
