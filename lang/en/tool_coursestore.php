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
$string['settings_chunksize'] = 'Chunk size';
$string['settings_chunksize_desc'] = 'Size of individual backup chunks to be sent to the backup server.';
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
$string['settings_displaypages'] = 'Display pages';
$string['settings_displaypages_desc'] = 'Hide/Display Course Store pages in the navigation menu under Site administration > Courses > Backups > Course Store';
$string['conncheckfail'] = 'Connection to {$a} failed. Please confirm that your course bank settings and network configuration are correct.';
$string['connchecksuccess'] = 'Connection check to {$a} passed!';
$string['speedtestsuccess'] = 'Connection speed test to {$a} passed! The test transfer speed was approximately ';
$string['speedtestchunk'] = 'The recommended chunk size for your system is ';
$string['speedtestfail'] = 'Connection to {$a} failed. Please confirm that your course bank settings and network configuration are correct.';
$string['speedtestslow'] = 'Outbound transfers to {$a} are very slow. The test transfer speed was approximately ';
$string['checking'] = 'Checking...';
$string['return'] = 'Go to settings page';
$string['connchecktitle'] = 'Connection check';
$string['speedtesttitle'] = 'Connection speed test';
$string['conncheckbutton'] = 'Check connection';
$string['speedtestbutton'] = 'Test transfer speed';
$string['backupsummary'] = 'Course Store backups transfer report';
$string['backupqueue'] = 'Course Store backups transfer queue';
$string['backupfiles'] = '{$a} file(s)';
$string['statuserror'] = 'Error';
$string['statusinprogress'] = 'Transfer in progress';
$string['statusnotstarted'] = 'Transfer pending';
$string['statusfinished'] = 'Transfer complete';
$string['statusonhold'] = 'Transfer is on hold';
$string['statuscancelled'] = 'Transfer is cancelled';
$string['settings_requestretries'] = 'HTTP request retries';
$string['settings_requestretries_desc'] = 'Number of times to reattempt the sending of an individual failed request.';
$string['settings_authtoken'] = 'Authentication token';
$string['settings_authtoken_desc'] = 'Authentication token for use in communication with external course bank instance.';
$string['cron_skippingmoodle'] = 'Configured to use an external cron. Skipping...';
$string['cron_locked'] = 'Cron lock record is in the database. The process may have been interrupted recently or still running.';
$string['cron_force'] = 'The lock can be removed by running this script with --force as an argument.';
$string['cron_duplicate'] = 'Duplicate cron lock';
$string['cron_sending'] = 'Sending backups...';
$string['cron_skippingexternal'] = 'The tool is not configured to be run via CLI. Exiting...';
$string['disabled'] = 'Sending of course backups is disabled.';
$string['cron_removinglock'] = 'Removing cron lock in the database...';
$string['nav_summary'] = 'Backups transfer report';
$string['nav_download'] = 'Download backups';
$string['nav_queue'] = 'Transfer queue';
$string['nav_report'] = 'Course Store logs';
$string['downloadsummary'] = 'Course Bank download backups';
$string['eventconnectionchecked'] = 'Connection checked';
$string['eventconnectioncheckfailed'] = 'Connection check failed';
$string['eventgetsession'] = 'Get session key';
$string['eventgetsessionfailed'] = 'Get session key failed';
$string['coursefullname'] = 'Course name';
$string['filetimemodified'] = 'Backup date';
$string['backupfilename'] = 'File name';
$string['eventtransferstarted'] = 'Backup transfer started';
$string['eventtransferstartfailed'] = 'Backup transfer start failed';
$string['eventtransfercompleted'] = 'Backup transfer completed';
$string['eventtransferinterrupted'] = 'Backup transfer interrupted';
$string['eventbackupdownloaded'] = 'Backup downloaded';
$string['eventbackupdownloadfailed'] = 'Backup download failed';
$string['eventtransferresumed'] = 'Backup transfer resumed';
$string['coursename'] = 'Course name';
$string['backupdate'] = 'Backup date';
$string['filename'] = 'File name';
$string['filesize'] = 'File size';
$string['status'] = 'Status';
$string['timecreated'] = 'Transfer started';
$string['timetransferstarted'] = 'Transfer started';
$string['notstarted'] = 'Not started';
$string['timecompleted'] = 'Transfer completed';
$string['notcompleted'] = 'Not completed';
$string['filtermorethan'] = 'more than';
$string['filterlessthan'] = 'less than';
$string['filterisequalto'] = 'is equal to';
$string['errordownloading'] = 'Error downloading the backup file.';
$string['errorgetdownloadlist'] = 'Can\'t get a list of backups from course bank. Please confirm that your course bank settings and network configuration are correct.';
$string['coursestorelogging'] = 'Course Store Logging';
$string['coursestore:view'] = 'View a list of course store backup files';
$string['coursestore:download'] = 'Download course store backup files';
$string['coursestore:edit'] = 'Edit course store backup files';
$string['coursestore:viewlogs'] = 'View Course Store logs';
$string['errorupdatingstatus'] = 'Error updating status';
$string['check_delete'] = 'Are you sure you want to delete {$a} from the transfer queue?';
$string['check_stop'] = 'Are you sure you want to put transferring of {$a} on hold?';
$string['check_go'] = 'Are you sure you want to resume transferring of {$a}?';
$string['transferinprogress'] = 'Can\'t continue. Transfer is in progress or may have been interrupted recently. The lock record is in the database.';
$string['delete'] = 'Delete from the queue';
$string['stop'] = 'Put on hold';
$string['go'] = 'Resume transferring';
$string['download'] = 'Download backup file';
$string['reportpageheader'] = 'Course Store logs';
$string['nologreaderenabled'] = 'No log reader enabled';
$string['eventhttprequest'] = 'Http request';
$string['eventhttprequestfailed'] = 'Http request failed';
$string['eventstatusupdated'] = 'Backup status updated';
$string['eventstatusupdatefailed'] = 'Backup status update failed';
$string['eventbackupdeletefailed'] = 'Backup delete failed';
$string['eventbackupsendfailed'] = 'Backup send failed';
$string['errorsonly'] = 'Errors only';
$string['eventorigin'] = 'Origin';
$string['eventloggedas'] = '{$a->realusername} as {$a->asusername}';
$string['selectlogreader'] = 'Select log reader';
$string['eventname'] = 'Event name';
$string['crontimeout'] = 'Cron execution time limit reached! Deferring transfer of remaining courses to next run.';
$string['eventtimeoutreached_desc'] = 'Cron execition time limit reached during transfer of course {$a}.';
$string['settings_sessionkey'] = 'Session key';
$string['settings_sessionkey_desc'] = 'Session key used by Course Store to authenticate with Course Bank.';
// Error codes.
$string['ERROR_TIMEOUT']              = 'The connection has timed out.';
$string['ERROR_MAX_ATTEMPTS_REACHED'] = 'Maximum attempts reached.';
