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

// Get required config variables
$urltarget = get_config('tool_coursestore', 'url');
$conntimeout = get_config('tool_coursestore', 'conntimeout');
$timeout = get_config('tool_coursestore', 'timeout');

// Initialise, check connection
$ws_manager = new coursestore_ws_manager($urltarget, $conntimeout, $timeout);
$check = array('operation' => 'check');
if(!$ws_manager->send($check)) {
    die("Connection check failed!");
}

// Chunk size is set in kilobytes
$chunksize = get_config('tool_coursestore', 'chunksize') * 1000;

// Open input file
$file = fopen($filename, 'r');
$filesum = md5_file($filename);
$filesize = filesize($filename);

// Set file-wide data
$data = array(
    'operation'  => 'transfer',
    'filename'   => $filename,
    'filesum'    => $filesum,
    'chunksize'  => $chunksize,
    'chunkcount' => ceil($filesize/$chunksize)
);

// Read the file in chunks, attempt to send them
$chunkno = 0;
while($contents = fread($file, $chunksize)) {
    $data['data'] = base64_encode($contents);
    $data['chunksum'] = md5($data['data']);
    $data['chunkno'] = $chunkno;
    if(!$ws_manager->send($data)) {
        echo "Failed to send a chunk!";
        break;
    }
    $chunkno++;
}

$ws_manager->close();
fclose($file);
