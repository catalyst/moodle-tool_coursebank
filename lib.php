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
require_once($CFG->dirroot.'/lib/adminlib.php');

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

    if (is_string($canrun)) {
        mtrace(get_string($canrun, 'tool_coursestore'));
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
 * @return boolean|string If can't run returns a key of lang string.
 */
function tool_coursestore_can_run_cron($type) {
    $enabled = tool_coursestore_get_config('enable');
    $externalcronenabled = tool_coursestore_get_config('externalcron');

    if ($enabled) {
        if ($type == CRON_MOODLE and $externalcronenabled) {
            return 'cron_skippingmoodle';
        } else if ($type == CRON_EXTERNAL and !$externalcronenabled) {
            return 'cron_skippingexternal';
        }
    } else {
        return 'disabled';
    }

    return true;
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
    $headers = get_headers($url);
    if (empty($headers)) {
        return false;
    }
    foreach ($invaldheaders as $invalidheader) {
        if (strstr($headers[0], $invalidheader)) {
            return false;
        }
    }

    return true;
}
/**
 * This class is a somewhat hacky way to ensure that the Course Store session
 * key is discarded whenever settings are changed.
 *
 * (This is important because settings changes may include auth token or the
 * Course Bank URL. Although Course Store will re-authenticate as necessary if
 * a session key does not work, it is conceivable that using a session key
 * associated with an old URL or token value might cause Course Bank to
 * attribute data sent by this Course Store instance to some other instance,
 * resulting in data corruption or overwriting.)
 *
 * This class extends the configempty class and overrides the output_html
 * method in order to output a completely empty, hidden setting item. When a
 * settings page form is saved in Moodle, settings are read in from form
 * elements, so this invisible element allows us to empty the session key
 * on submit without cluttering the settings page unnecessarily.
 */
class admin_setting_configsessionkey extends admin_setting_configempty {
    /**
     * Returns an XHTML string for the hidden field
     *
     * @param string $data
     * @param string $query
     * @return string XHTML string for the editor
     */
    public function output_html($data, $query='') {
        $html = '<div class="form-item clearfix" id="admin-sessionkey">' .
                    '<div class="form-setting"> '.
                        '<div class="form-empty" >' .
                            '<input type="hidden" '.
                                'id="' . $this->get_id() . '"' .
                                'name="' . $this->get_full_name() . '"' .
                                'value=""' .
                            '/>' .
                        '</div>' .
                    '</div>' .
                '</div>';
        return $html;
    }
}
