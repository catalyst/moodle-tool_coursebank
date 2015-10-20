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
 * @package    tool_coursebank
 * @author     Ghada El-Zoghbi <ghada@catalyst-au.net>
 * @author     Adam Riddell <adamr@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/lib.php');
require_once($CFG->dirroot.'/admin/tool/coursebank/locallib.php');

global $PAGE;
// Somewhat hacky fix for jquery load issues.
$currentsection = optional_param('section', '', PARAM_ALPHAEXT);

if ($currentsection == 'coursebank_settings') {
    // Check if moodle is newer then 2.9.x.
    if ((float)$CFG->version > 2015051100) {
        $PAGE->requires->jquery();
    } else {
        $PAGE->requires->js('/admin/tool/coursebank/javascript/jquery-1.11.0.min.js');
    }
    $PAGE->requires->js('/admin/tool/coursebank/javascript/coursebank.js');
}
$displaypages = get_config('tool_coursebank', 'displaypages');

if ($hassiteconfig) {
    if ($displaypages) {
        if (PHPUNIT_TEST) {
            // When unit tests are run,  the menu item 'courses/backups' has not been created yet
            // in Moodle 2.7+.  It has been created for Moodle 2.6 and below.
            // We need to check if it has not been created and actually
            // add it so we don't get the error that the parent doesn't exist.
            // This is not required in the real-world though as these plugins are loaded
            // after the course menu has been set up.
            if (!$ADMIN->locate('backups')) {
                $ADMIN->add('courses', new admin_category('backups', new lang_string('backups', 'admin')));
            }
        }

        $ADMIN->add('backups', new admin_category('coursebank_pages',
                get_string('pluginname', 'tool_coursebank')));

        $ADMIN->add('coursebank_pages', new admin_externalpage('tool_coursebank_queue',
                get_string('nav_queue', 'tool_coursebank'),
                "$CFG->wwwroot/$CFG->admin/tool/coursebank/queue.php", 'tool/coursebank:view'));

        $ADMIN->add('coursebank_pages', new admin_externalpage('tool_coursebank_download',
                get_string('nav_download', 'tool_coursebank'),
                "$CFG->wwwroot/$CFG->admin/tool/coursebank/index.php", 'tool/coursebank:view'));

        $ADMIN->add('reports', new admin_externalpage('tool_coursebank_report',
                get_string('nav_report', 'tool_coursebank'),
                "$CFG->wwwroot/$CFG->admin/tool/coursebank/report.php", 'tool/coursebank:viewlogs'));
    }

    $settings = new admin_settingpage('coursebank_settings',
            get_string('pluginname', 'tool_coursebank')
    );

    $settings->add(new admin_setting_heading('coursebank_settings_description', '',
            get_string('settings_intro', 'tool_coursebank')));

    $renderer = $PAGE->get_renderer('tool_coursebank');

    $text = $renderer->course_bank_conncheck();
    $text .= $renderer->course_bank_speedtest();

    $settings->add(new admin_setting_heading('coursebank_settings_conncheck', '', $text));

    $settings->add(new admin_setting_heading('coursebank_header',
            get_string('settings_header', 'tool_coursebank'),
            '')
    );
    $enableoptions = array(
        0 => get_string('settings_disablestring', 'tool_coursebank'),
        1 => get_string('settings_enablestring', 'tool_coursebank')
    );
    $enable = new admin_setting_configselect('tool_coursebank/enable',
            ' '.get_string('settings_enable', 'tool_coursebank'),
            ' '.get_string('settings_enable_desc', 'tool_coursebank'),
            1,
            $enableoptions
    );
    $settings->add($enable);
    $settings->add(new admin_setting_configtext('tool_coursebank/url',
            get_string('settings_url', 'tool_coursebank'),
            get_string('settings_url_desc', 'tool_coursebank'),
            '',
            PARAM_URL)
    );
    $settings->add(new admin_setting_configtext('tool_coursebank/authtoken',
            get_string('settings_authtoken', 'tool_coursebank'),
            get_string('settings_authtoken_desc', 'tool_coursebank'),
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
    $settings->add(new admin_setting_configselect('tool_coursebank/chunksize',
            get_string('settings_chunksize', 'tool_coursebank'),
            get_string('settings_chunksize_desc', 'tool_coursebank'),
            500,
            $chunksizeopts)
    );
    $settings->add(new admin_setting_configcheckbox('tool_coursebank/externalcron',
            get_string('settings_externalcron', 'tool_coursebank'),
            get_string('settings_externalcron_desc', 'tool_coursebank'), 0));

    $settings->add(new admin_setting_configcheckbox('tool_coursebank/displaypages',
            get_string('settings_displaypages', 'tool_coursebank'),
            get_string('settings_displaypages_desc', 'tool_coursebank'), 1));

    $settings->add(new admin_setting_configcheckbox('tool_coursebank/deletelocalbackup',
            get_string('settings_deletelocalbackup', 'tool_coursebank'),
            get_string('settings_deletelocalbackup_desc', 'tool_coursebank'), 0));

    $settings->add(new admin_setting_configsessionkey(
            'tool_coursebank/sessionkey',
            get_string('settings_sessionkey', 'tool_coursebank'),
            get_string('settings_sessionkey_desc', 'tool_coursebank')
            )
    );

    // Proxy stuff.
    $settings->add(new admin_setting_heading('coursebank_proxy_header',
            get_string('settings_proxyheader', 'tool_coursebank'),
            '')
    );
    $settings->add(new admin_setting_configtext('tool_coursebank/proxyurl',
            get_string('settings_proxyurl', 'tool_coursebank'),
            get_string('settings_proxyurl_desc', 'tool_coursebank'),
            '',
            PARAM_URL)
    );
    $settings->add(new admin_setting_configtext('tool_coursebank/proxyuser',
            get_string('settings_proxyuser', 'tool_coursebank'),
            get_string('settings_proxyuser_desc', 'tool_coursebank'),
            '',
            PARAM_TEXT)
    );
    $settings->add(new admin_setting_configpasswordunmask('tool_coursebank/proxypass',
            get_string('settings_proxypass', 'tool_coursebank'),
            get_string('settings_proxypass_desc', 'tool_coursebank'),
            ''
            )
    );
    $settings->add(new admin_setting_configtext('tool_coursebank/proxyport',
            get_string('settings_proxyport', 'tool_coursebank'),
            get_string('settings_proxyport_desc', 'tool_coursebank'),
            '',
            PARAM_INT)
    );
    $ADMIN->add('tools', $settings);
}
