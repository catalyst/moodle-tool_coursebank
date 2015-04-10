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
 *
 * @package    tool_coursestore
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

define('CRON_MOODLE', 1);
define('CRON_EXTERNAL', 2);

/**
 * Recursively delete a directory that is not empty
 *
 * @param string $dir path
 */
function tool_coursestore_rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") {
                    coursestore_rrmdir($dir."/".$object);
                } else {
                    unlink($dir."/".$object);
                }
            }
        }
        reset($objects);
        rmdir($dir);
    }
}
/**
 * Legacy cron function
 */
function tool_coursestore_cron() {
    tool_coursestore_cron_run();
}
/**
 * Run cron code
 *
 * @return boolean
 */
function tool_coursestore_cron_run() {
    $name = 'tool_coursestore_cronlock';
    $canrun = tool_coursestore_can_run_cron(CRON_MOODLE);

    if (!$canrun) {
        mtrace(get_string('cron_skippingmoodle', 'tool_coursestore'));
        return true;
    }

    if (tool_coursestore_does_cron_lock_exist($name)) {
        mtrace(get_string('cron_locked', 'tool_coursestore'));
        return true;
    }

    tool_coursestore_set_cron_lock($name, time());
    tool_coursestore::fetch_backups();
    tool_coursestore_delete_cron_lock($name);
}
/**
 * Check if we can run cron.
 *
 * @param integer $type Type of cron run (moodle or external)
 * @return boolean
 */
function tool_coursestore_can_run_cron($type) {
    $enabled = tool_coursestore_get_config('enable');
    $externalcronenabled = tool_coursestore_get_config('externalcron');

    if ($enabled) {
        if ($type == CRON_MOODLE and !$externalcronenabled) {
            return true;
        } else if ($type == CRON_EXTERNAL and $externalcronenabled) {
            return true;
        }
    }

    return false;
}
/**
 * Set config to tool_coursestore plugin
 *
 * @param string $name
 * @param string $value
 * @return bool true or exception
 */
function tool_coursestore_set_config($name, $value) {
    $result = set_config($name, $value, 'tool_coursestore');
    return $result;
}
/**
 * Gets config for tool_coursestore plugin
 *
 * @param string $name
 * @return mixed hash-like object or single value, return false no config found
 */
function tool_coursestore_get_config($name) {
    $value = get_config('tool_coursestore', $name);
    return $value;
}
/**
 * Insert temporary cron lock into the config table
 *
 * @global type DB
 * @param string $name cron lock's name
 * @param string $value cron lock's value
 * @return bool
 */
function tool_coursestore_set_cron_lock($name, $value) {
    global $DB;
    try {
        $lock = new stdClass();
        $lock->name = $name;
        $lock->value = $value;
        $DB->insert_record('config', $lock);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
/**
 * Check if the temporary cron lock still exists in the config table
 *
 * @global type DB
 * @param string $name cron lock's name
 * @return bool
 */
function tool_coursestore_does_cron_lock_exist($name) {
    global $DB;

    return $DB->record_exists('config', array('name' => $name));
}
 /**
  * Delete the temporary cron lock from the config table
  *
  * @global type DB
  * @param string $name cron lock's name
  */
function tool_coursestore_delete_cron_lock($name) {
    global $DB;
    if (tool_coursestore_does_cron_lock_exist($name)) {
        $DB->delete_records('config', array('name' => $name));
    }
}
/**
 * Check if URL is valid
 *
 * @param string $url
 */
function tool_coursestore_check_url($url) {
    // Validate URL first.
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    return true;
}
/**
 * Check if URL returns valid header.
 *
 * @param string $url
 * @param array $invaldheaders A list of invalid responses e.g 404, 500.
 * @return boolean
 */
function tool_coursestore_is_url_available($url, $invaldheaders=array('404', '403', '500')) {
    $avaible = true;
    $headers = get_headers($url);
    foreach ($invaldheaders as $invalidheader) {
        if (strstr($headers[0], $invalidheader)) {
            $avaible = false;
            break;
        }
    }

    return $avaible;
}

