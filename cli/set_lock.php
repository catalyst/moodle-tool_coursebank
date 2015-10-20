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
 * Set cron lock. Intended for testing lock behaviour.
 *
 * EXAMPLE
 *
 * sudo -u www-data php admin/tool/coursebank/tests/set_lock.php $(date +"%s" --date "25 hours ago")
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


if (!isset($argv[1]) || !is_numeric($argv[1])) {
    die("First argument must be a unix timestamp." . PHP_EOL);
}
$lock = (int) $argv[1];
echo "Setting lock: $lock" . PHP_EOL;

if (!tool_coursebank_set_cron_lock($lock)) {
    echo "Couldn't set lock '$lock'." . PHP_EOL;
    exit(1);
}
exit(0);
