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
 * Filter based on a list of values.
 *
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/user/filters/lib.php');

class coursebank_filter_select extends user_filter_select {
    /**
     * Returns params
     *
     * @param array $data filter settings
     * @return array sql string and $params
     */
    public function get_param_filter($data) {
        $params = array();

        $operator = $data['operator'];
        $value    = $data['value'];

        switch($operator) {
            case 1: // Equal to.
                $params = array('operator' => '=', 'value' => $value);
                break;
            case 2: // Not equal to.
                $params = array('operator' => '<>', 'value' => $value);
                 break;
            default:
                return '';
        }
        return $params;
    }
}

