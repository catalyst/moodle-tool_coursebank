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

$name = 'tool_coursebank_cronlock';
$argslist = array_slice($argv, 1);

foreach ($argslist as $arg) {
    // Clear the lock regardless of whether we think it is stale.
    if ($arg == '--force') {
        tool_coursebank_clear_cron_lock();
        mtrace(get_string('cron_removinglock', 'tool_coursebank'));
    }
}

if (!tool_coursebank_send_backups_with_lock(CRON_EXTERNAL)) {
    exit(1);
}
exit(0);
