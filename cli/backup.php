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
 * Send course backups offsite.
 *
 * @package    tool_coursebank
 * @author     Ghada El-Zoghbi <ghada@catalyst-au.net>
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>*
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/coursebank/lib.php');

$canrun = tool_coursebank_can_run_cron(CRON_EXTERNAL);
$name = 'tool_coursebank_cronlock';
$argslist = array_slice($argv, 1);

if (is_string($canrun)) {
    mtrace(get_string($canrun, 'tool_coursebank'));
    die();
}

mtrace('Started at ' . date('Y-m-d h:i:s', time()));

foreach ($argslist as $arg) {
    if ($arg == '--force') {
        mtrace(get_string('cron_removinglock', 'tool_coursebank'));
        tool_coursebank_delete_cron_lock($name);
    }
}
// Check if lock is in database. If so probably something was broken during the last run.
// We need to get admin to check this manually.
if (tool_coursebank_does_cron_lock_exist($name)) {
    mtrace(get_string('cron_locked', 'tool_coursebank') . get_string('cron_force', 'tool_coursebank'));
    die();
}
// Lock cron.
if (!tool_coursebank_set_cron_lock($name, time())) {
    mtrace(get_string('cron_duplicate', 'tool_coursebank'));
    die();
}
// Run the process.
mtrace(get_string('cron_sending', 'tool_coursebank'));
tool_coursebank::fetch_backups();
mtrace('Successfully completed at ' . date('Y-m-d h:i:s', time()));
// Purge DB lock.
tool_coursebank_delete_cron_lock($name);

