<?php
/**
 * Get cron lock and print to screen.
 *
 * EXAMPLE
 * 
 *   sudo -u www-data php admin/tool/coursebank/tests/get_lock.php
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/admin/tool/coursebank/lib.php');

$lock = tool_coursebank_get_cron_lock();

if ($lock) {
    echo "Lock FOUND: $lock , time of lock: " . strftime('%Y-%m-%d %H:%M', $lock) . PHP_EOL;
    exit(0);
}
else {
    echo "NO LOCK FOUND" . PHP_EOL;
    exit(1);
}

