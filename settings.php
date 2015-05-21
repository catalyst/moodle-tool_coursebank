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
require_once($CFG->dirroot.'/admin/tool/coursestore/lib.php');
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

$PAGE->requires->js('/admin/tool/coursestore/javascript/jquery-1.11.0.min.js');
$PAGE->requires->js('/admin/tool/coursestore/javascript/coursestore.js');

$displaypages = tool_coursestore_get_config('displaypages');

if ($displaypages) {
    $ADMIN->add('backups', new admin_category('coursestore_pages',
            get_string('pluginname', 'tool_coursestore')));

    $ADMIN->add('coursestore_pages', new admin_externalpage('tool_coursestore_queue',
            get_string('nav_queue', 'tool_coursestore'),
            "$CFG->wwwroot/$CFG->admin/tool/coursestore/queue.php", 'tool/coursestore:view'));

    $ADMIN->add('coursestore_pages', new admin_externalpage('tool_coursestore_download',
            get_string('nav_download', 'tool_coursestore'),
            "$CFG->wwwroot/$CFG->admin/tool/coursestore/index.php", 'tool/coursestore:view'));

    // Do not include this page in the menu.
    //$ADMIN->add('coursestore_pages', new admin_externalpage('tool_coursestore',
    //        get_string('nav_summary', 'tool_coursestore'),
    //        "$CFG->wwwroot/$CFG->admin/tool/coursestore/transfer_report.php", 'tool/coursestore:view'));

    $ADMIN->add('reports', new admin_externalpage('tool_coursestore_report',
            get_string('nav_report', 'tool_coursestore'),
            "$CFG->wwwroot/$CFG->admin/tool/coursestore/report.php", 'tool/coursestore:viewlogs'));
}

if ($hassiteconfig) {

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
            1,
            $enableoptions
    );
    $settings->add($enable);
    $settings->add(new admin_setting_configtext('tool_coursestore/url',
            get_string('settings_url', 'tool_coursestore'),
            get_string('settings_url_desc', 'tool_coursestore'),
            '',
            PARAM_URL)
    );
    $settings->add(new admin_setting_configtext('tool_coursestore/authtoken',
            get_string('settings_authtoken', 'tool_coursestore'),
            get_string('settings_authtoken_desc', 'tool_coursestore'),
            '',
            PARAM_TEXT)
    );
    $chunksizeopts = array(
        10  => '10kB',
        100 => '100kB',
        200 => '200kB',
        500 => '500kB',
       1000 => '1MB',
       1500 => '1.5MB',
       2000 => '2MB'
    );
    $settings->add(new admin_setting_configselect('tool_coursestore/chunksize',
            get_string('settings_chunksize', 'tool_coursestore'),
            get_string('settings_chunksize_desc', 'tool_coursestore'),
            500,
            $chunksizeopts)
    );
    $settings->add(new admin_setting_configcheckbox('tool_coursestore/externalcron',
            get_string('settings_externalcron', 'tool_coursestore'),
            get_string('settings_externalcron_desc', 'tool_coursestore'), 0));

    $settings->add(new admin_setting_configcheckbox('tool_coursestore/displaypages',
            get_string('settings_displaypages', 'tool_coursestore'),
            get_string('settings_displaypages_desc', 'tool_coursestore'), 1));
    $settings->add(new admin_setting_configsessionkey(
            'tool_coursestore/sessionkey',
            get_string('settings_sessionkey', 'tool_coursestore'),
            get_string('settings_sessionkey_desc', 'tool_coursestore')
            )
    );
    $ADMIN->add('tools', $settings);
}
