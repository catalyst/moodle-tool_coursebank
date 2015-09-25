<?php
/**
 * Set cron lock.
 *
 * Intended for testing lock behaviour.
 *
 * EXAMPLE
 * 
 *   sudo -u www-data php admin/tool/coursebank/tests/set_lock.php $(date +"%s" --date "25 hours ago") 
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
