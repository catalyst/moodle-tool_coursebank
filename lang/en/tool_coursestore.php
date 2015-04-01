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
 * Strings for tool_coursestore
 *
 * @package    tool_coursestore
 * @author     Ghada El-Zoghbi <ghada@catalyst-au.net>
 * @copyright  2015 Catalys IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Course Store';
$string['backupfailed'] = 'Failed sending backup {$a}.';
$string['deletefailed'] = 'Failed deleting backup {$a}.';
$string['sendcoursebackups'] = 'External course backups';
$string['noaccesstofeature'] = 'Sorry, only admin or CLI has access to this feature.';
$string['settingspage'] = 'Configuration';
$string['settings_header'] = 'Course Store configuration options';
$string['settings_url'] = 'Target URL';
$string['settings_url_desc'] = 'Location of the target backup server.';
$string['settings_chunksize'] = 'Chunk size (kB)';
$string['settings_chunksize_desc'] = 'Size (in Kilobytes) of individual backup chunks to be sent to the backup server.';
$string['settings_timeout'] = 'Web service time out';
$string['settings_timeout_desc'] = 'Time out (in seconds) for individual HTTP requests.';
$string['settings_enable'] = 'Active';
$string['settings_enable_desc'] = 'Enable or disable sending of course backups.';
$string['settings_enablestring'] = 'Enable';
$string['settings_disablestring'] = 'Disable';
$string['settings_externalcron'] = 'Use external cron';
$string['settings_externalcron_desc'] = 'If checked the process will be triggered by external cron.
    <br />Server administrators have to set up external cron.
    <br />The simple example: <PRE>2-57/5 * * * * www-data php /path/to/your/moodle/admin/tool/coursestore/cli/backup.php >> /var/log/backup.log</PRE>';
$string['conncheckfail'] = 'Connection error. Please confirm that your course bank settings and network configuration are correct.';
$string['connchecksuccess'] = 'Connection check passed!';
$string['speedtestsuccess'] = 'Connection speed test passed!';
$string['speedtestfail'] = 'Connection error. Please confirm that your course bank settings and network configuration are correct.';
$string['speedtestslow'] = 'Outbound transfers are very slow. The test transfer speed was approximately ';
$string['checking'] = 'Checking...';
$string['return'] = 'Return';
$string['connchecktitle'] = 'Connection check';
$string['speedtesttitle'] = 'Connection speed test';
$string['conncheckbutton'] = 'Check connection';
$string['speedtestbutton'] = 'Test transfer speed';
$string['backupsummary'] = 'Backups summary';
$string['statuserror'] = 'Error';
$string['statusinprogress'] = 'Transfer in progress';
$string['statusnotstarted'] = 'Transfer pending';
$string['statusfinished'] = 'Transfer complete';
$string['settings_requestretries'] = 'HTTP request retries';
$string['settings_requestretries_desc'] = 'Number of times to reattempt the sending of an individual failed request.';
$string['settings_authtoken'] = 'Authentication token';
$string['settings_authtoken_desc'] = 'Authentication token for use in communication with external course bank instance.';
$string['cron_skippingmoodle'] = 'Disabled or configured to use an external cron. Skipping...';
$string['cron_locked'] = 'Cron lock record is in the database. The process may have been interrupted recently or still running.';
$string['cron_force'] = 'The lock can be removed by running this script with --force as an argument.';
$string['cron_duplicate'] = 'Duplicate cron lock';
$string['cron_sending'] = 'Sending backups...';
$string['cron_skippingexternal'] = 'The tool is not configured to be run via CLI. Exiting...';
$string['cron_removinglock'] = 'Removing cron lock in the database...';
// Error codes.
$string['ERROR_TIMEOUT']              = 'The connection has timed out.';
$string['ERROR_MAX_ATTEMPTS_REACHED'] = 'Maximum attempts reached.';
// etc...
