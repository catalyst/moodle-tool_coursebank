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
 * @package    tool_coursebank
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');
require_once($CFG->dirroot.'/lib/adminlib.php');

define('CRON_MOODLE', 1);
define('CRON_EXTERNAL', 2);

/**
 * Recursively delete a directory that is not empty
 *
 * @param string $dir path
 */
function tool_coursebank_rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") {
                    tool_coursebank_rrmdir($dir."/".$object);
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
function tool_coursebank_cron() {
    return tool_coursebank_send_backups_with_lock(CRON_MOODLE);
}
/**
 * Fetch backups from automated system and send them.
 *
 * This function will need to acquire a lock before continuing.
 *
 * Returns true if successfully run.
 *
 * @param int Execution context: CRON_MOODLE or CRON_EXTERNAL
 * @return boolean
 */
function tool_coursebank_send_backups_with_lock($type) {
    mtrace('Started at ' . date('Y-m-d h:i:s', time()));
    $canrun = tool_coursebank_can_run_cron($type);
    if (is_string($canrun)) {
        mtrace(get_string($canrun, 'tool_coursebank'));
        mtrace('Failed.  ' . date('Y-m-d h:i:s', time()));
        return false;
    }

    // If there's an existing lock...

    if (tool_coursebank_get_cron_lock()) {
        if (!tool_coursebank_cron_lock_can_be_cleared()) {
            mtrace(get_string('cron_locked', 'tool_coursebank'));
            mtrace('Failed.  ' . date('Y-m-d h:i:s', time()));
            return false;
        } else {
            mtrace(get_string('cron_lock_cleared', 'tool_coursebank'));
        }
    }

    if (!tool_coursebank_set_cron_lock()) {
        mtrace(get_string('cron_locked', 'tool_coursebank'));
        mtrace('Failed.  ' . date('Y-m-d h:i:s', time()));
        return false;
    }
    mtrace(get_string('cron_sending', 'tool_coursebank'));
    tool_coursebank::fetch_backups();
    mtrace('Completed at ' . date('Y-m-d h:i:s', time()));
    tool_coursebank_clear_cron_lock();
    return true;
}

/**
 * Check if we can run cron.
 *
 * @param integer $type Type of cron run (moodle or external)
 * @return boolean|string If can't run returns a key of lang string.
 */
function tool_coursebank_can_run_cron($type) {
    $enabled = get_config('tool_coursebank', 'enable');
    $externalcronenabled = get_config('tool_coursebank', 'externalcron');

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
 * Get the CourseBank cron lock.
 *
 * If the lock is present, returns the value of the lock (unix timestamp).
 * If there is no lock, returns null.
 *
 * @global type DB
 * @param string $name cron lock's name
 * @return int|null
 */
function tool_coursebank_get_cron_lock() {
    return get_config('tool_coursebank', 'tool_coursebank_cronlock');
}

/**
 * Set the CourseBank cron lock.
 *
 * This is intended to ensure that the plugin talks to CourseBank one
 * session at time.
 * Normally should be called without $time parameter so that the lock value can
 * default to now.
 *
 * No attempt is made to check if it *should* be set.
 * @see tool_coursebank_cron_lock_can_be_cleared .
 *
 * @param int $time unix timestamp which is used as the lock value.
 * @return bool Whether lock was set.
 */
function tool_coursebank_set_cron_lock($time=null) {
    if (!$time) {
        $time = time();
    }
    if (!is_int($time)) {
        return false;
    }
    return set_config('tool_coursebank_cronlock', $time, 'tool_coursebank');
}

/**
 * Removes CourseBank cron lock if present.
 *
 * No attempt is made to check if it *should* be cleared.
 * @see tool_coursebank_cron_lock_can_be_cleared .
 *
 * @param int $time unix timestamp which is used as the lock value.
 * @return bool Whether lock was cleared.
 */
function tool_coursebank_clear_cron_lock($time=null) {
    return unset_config('tool_coursebank_cronlock', 'tool_coursebank');
}

/**
 * Determines if the existing cron lock can be cleared.
 *
 * The intention is to clear out a stale cron lock (one that should
 * have been cleared).
 *
 * @return bool
 */
function tool_coursebank_cron_lock_can_be_cleared($maxsecs=tool_coursebank::CRON_LOCK_TIMEOUT) {
    $now = time();
    $time = tool_coursebank_get_cron_lock();
    if (!$time) {
        return true;
    }
    if (($now - $time) >= $maxsecs) {
        return true;
    }
    return false;
}


/**
 * Check if URL is valid
 *
 * @param string $url
 */
function tool_coursebank_check_url($url) {
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
function tool_coursebank_is_url_available($url, $invaldheaders=array('404', '403', '500')) {
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
 * This class is a somewhat hacky way to ensure that the Course Bank session
 * key is discarded whenever settings are changed.
 *
 * (This is important because settings changes may include auth token or the
 * External Course Bank URL. Although Course Bank will re-authenticate as
 * necessary if a session key does not work, it is conceivable that using a
 * session key associated with an old URL or token value might cause External
 * Course Bank to attribute data sent by this Course Bank instance to some
 * other instance, resulting in data corruption or overwriting.)
 *
 * This class extends the configempty class and overrides the output_html
 * method in order to output a completely empty, hidden setting item. When a
 * settings page form is saved in Moodle, settings are read in from form
 * elements, so this invisible element allows us to empty the session key
 * on submit without cluttering the settings page unnecessarily.
 */
class admin_setting_configsessionkey extends admin_setting_configtext {

    /**
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, '', PARAM_RAW);
    }

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
