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
 * Constants for the course store.
 *
 * @package    tool_coursestore
 * @author     Ghada El-Zoghbi <ghada@catalyst-au.net>
 * @copyright  2015 Catalys IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

abstract class tool_coursestore {

    // status
    const STATUS_NOTSTARTED  = 0;
    const STATUS_INPROGRESS  = 1;
    const STATUS_FINISHED    = 2;
    const STATUS_ERROR       = 99;

    public static function get_config_chunck_size() {
        return 100;
    }

    public static function calculate_total_chunks($chuncksize, $filesize) {
        return ceil($filesize / $chuncksize);
    }

    public static function send_backup($backup) {
        return true;
    }
}