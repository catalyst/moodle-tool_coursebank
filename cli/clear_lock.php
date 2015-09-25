<?php
/**
 * Clear cron lock but check first.
 *
 * EXAMPLE
 * 
 *   sudo -u www-data php admin/tool/coursebank/tests/get_lock.php
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/admin/tool/coursebank/lib.php');

$lock = tool_coursebank_get_cron_lock();

if (!$lock) {
    echo "There is no lock to clear." . PHP_EOL;
    exit(0);
}
else {
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
}
else {
    echo "FAILED TO REMOVE LOCK." . PHP_EOL;
    exit(1);
}

