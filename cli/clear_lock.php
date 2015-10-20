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
 * Clear cron lock but check first.
 *
 * EXAMPLE
 *
 * sudo -u www-data php admin/tool/coursebank/tests/get_lock.php
 *
 *
 * @package    tool_coursebank
 * @author     Daniel Bush <danb@catalyst-au.net>
 * @copyright  2015 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/admin/tool/coursebank/lib.php');

$lock = tool_coursebank_get_cron_lock();

if (!$lock) {
    echo "There is no lock to clear." . PHP_EOL;
    exit(0);
} else {
    echo "Lock FOUND: $lock , time of lock: " . strftime('%Y-%m-%d %H:%M', $lock) . PHP_EOL;
}

if (!tool_coursebank_cron_lock_can_be_cleared()) {
    echo "Can't clear lock yet." . PHP_EOL;
    echo "If you want to clear the lock anyway, see tool_coursebank_clear_cron_lock()." . PHP_EOL;
    exit(1);
}

if (tool_coursebank_clear_cron_lock()) {
    echo "REMOVED LOCK." . PHP_EOL;
    exit(0);
} else {
    echo "FAILED TO REMOVE LOCK." . PHP_EOL;
    exit(1);
}

