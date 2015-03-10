<?php

define('CLI_SCRIPT', true);

require(__DIR__.'../../../../../config.php');

//Open input file
$filename = './tolllearning.2015-02-19--083001.mysql.gz';
$file = fopen($filename, 'r');
$filesum = md5_file($filename);
$filesize = filesize($filename);

$ch = curl_init('coursestore-ws.local');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
$chunkno = 0;
$chunksize = 102400;
$data = array(
    'filename'   => $filename,
    'username'   => 'adamr',
    'password'   => '707@11yN07@p@$$W0RD',
    'filesum'    => $filesum,
    'chunksize'  => $chunksize,
    'chunkcount' => ceil($filesize/$chunksize)
);

while($contents = fread($file, $chunksize)) {
    $data['data'] = base64_encode($contents);
    $data['chunksum'] = md5($data['data']);
    $data['chunkno'] = $chunkno;
    $postdata = json_encode($data);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
            'Content-Length: ' . strlen($postdata))
    );

    // Make five attempts to send the chunk
    for($attempt=0; $attempt < 4; $attempt++) {
        //execute post
        $result = curl_exec($ch);
        $response = curl_getinfo($ch);
        if($response['http_code'] == '202') {
            break;
        }
    }
    if($response['http_code'] != '202') {
        echo "Failed to send a chunk!";
    }
    $chunkno++;
}

//Close input file
fclose($file);
//close connection
curl_close($ch);
