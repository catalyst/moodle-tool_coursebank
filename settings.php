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
 * Adds settings links to admin tree.
 *
 * @package    tool_coursestore
 * @author     Ghada El-Zoghbi <ghada@catalyst-au.net>
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

$PAGE->requires->js('/admin/tool/coursestore/javascript/jquery-1.11.0.min.js');
$PAGE->requires->js('/admin/tool/coursestore/javascript/coursestore.js');

if ($hassiteconfig) {
    $ADMIN->add('backups', new admin_externalpage('tool_coursestore',
            get_string('nav_summary', 'tool_coursestore'),
            "$CFG->wwwroot/$CFG->admin/tool/coursestore/index.php", 'moodle/site:config'));

    $ADMIN->add('backups', new admin_externalpage('tool_coursestore_download',
            get_string('nav_download', 'tool_coursestore'),
            "$CFG->wwwroot/$CFG->admin/tool/coursestore/download.php", 'moodle/site:config'));

    $settings = new admin_settingpage('coursestore_settings',
            get_string('pluginname', 'tool_coursestore')
    );

    $renderer = $PAGE->get_renderer('tool_coursestore');

    $text = $renderer->course_store_conncheck();
    $text .= $renderer->course_store_speedtest();

    $settings->add(new admin_setting_heading('coursestore_settings_conncheck', '', $text));

    $settings->add(new admin_setting_heading('coursestore_header',
            get_string('settings_header', 'tool_coursestore'),
            '')
    );
    $enableoptions = array(
        0 => get_string('settings_disablestring', 'tool_coursestore'),
        1 => get_string('settings_enablestring', 'tool_coursestore')
    );
    $enable = new admin_setting_configselect('tool_coursestore/enable',
            ' '.get_string('settings_enable', 'tool_coursestore'),
            ' '.get_string('settings_enable_desc', 'tool_coursestore'),
            0,
            $enableoptions
    );
    $settings->add($enable);
    $settings->add(new admin_setting_configcheckbox('tool_coursestore/externalcron',
            get_string('settings_externalcron', 'tool_coursestore'),
            get_string('settings_externalcron_desc', 'tool_coursestore'), 0));
    $settings->add(new admin_setting_configtext('tool_coursestore/url',
            get_string('settings_url', 'tool_coursestore'),
            get_string('settings_url_desc', 'tool_coursestore'),
            '',
            PARAM_URL)
    );
    $settings->add(new admin_setting_configtext('tool_coursestore/chunksize',
            get_string('settings_chunksize', 'tool_coursestore'),
            get_string('settings_chunksize_desc', 'tool_coursestore'),
            100,
            PARAM_INT)
    );
    $settings->add(new admin_setting_configtext('tool_coursestore/timeout',
            get_string('settings_timeout', 'tool_coursestore'),
            get_string('settings_timeout_desc', 'tool_coursestore'),
            30,
            PARAM_INT)
    );
    $settings->add(new admin_setting_configtext('tool_coursestore/requestretries',
            get_string('settings_requestretries', 'tool_coursestore'),
            get_string('settings_requestretries', 'tool_coursestore'),
            4,
            PARAM_INT)
    );
    $settings->add(new admin_setting_configtext('tool_coursestore/authtoken',
            get_string('settings_authtoken', 'tool_coursestore'),
            get_string('settings_authtoken_desc', 'tool_coursestore'),
            '',
            PARAM_TEXT)
    );
    $settings->add(new admin_setting_configcheckbox('tool_coursestore/loghttpdata',
            get_string('settings_loghttpdata', 'tool_coursestore'),
            get_string('settings_loghttpdata_desc', 'tool_coursestore'), 0));

    $ADMIN->add('tools', $settings);
}
