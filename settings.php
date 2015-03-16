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

if ($hassiteconfig) {
    $ADMIN->add('backups', new admin_externalpage('tool_coursestore', get_string('pluginname', 'tool_coursestore'), "$CFG->wwwroot/$CFG->admin/tool/coursestore/index.php", 'moodle/site:config'));

    $settings = new admin_settingpage('coursestore_settings',
            get_string('pluginname', 'tool_coursestore')
    );
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
            10,
            PARAM_INT)
    );
    $settings->add(new admin_setting_configtext('tool_coursestore/conntimeout',
            get_string('settings_conntimeout', 'tool_coursestore'),
            get_string('settings_conntimeout_desc', 'tool_coursestore'),
            5,
            PARAM_INT)
    );
    $settings->add(new admin_setting_configtext('tool_coursestore/maxattempts',
            get_string('settings_maxattempts', 'tool_coursestore'),
            get_string('settings_maxattempts_desc', 'tool_coursestore'),
            3,
            PARAM_INT)
    );
    $settings->add(new admin_setting_configtext('tool_coursestore/maxhttprequests',
            get_string('settings_maxhttprequests', 'tool_coursestore'),
            get_string('settings_maxhttprequests_desc', 'tool_coursestore'),
            5,
            PARAM_INT)
    );

    $ADMIN->add('tools', $settings);
}
