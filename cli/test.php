<?php

define('CLI_SCRIPT', true);

require(__DIR__.'../../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/admin/tool/coursestore/locallib.php');

// Handle command line arguments
if(sizeof($argv) != 2) {
    die("No input file provided!\n");
}
if(!is_readable($argv[1])) {
    die("Provided file does not exist or is not readable!\n");
}
$filename = $argv[1];

if(!send_file($filename, array('filename' => $filename))) {
    echo "Transfer failed!";
}
