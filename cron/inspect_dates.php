<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}

// quick inspect script
date_default_timezone_set("America/Chicago");

$username = $env['API_USERNAME'];
$password = $env['API_PASSWORD'];

$data = "username=$username&password=$password";

$ch = curl_init('https://cs4743.professorvaladez.com/api/create_session');
curl_setopt($ch, CURLOPT_POST,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
$result = curl_exec($ch);
curl_close($ch);

$info = json_decode($result,true);
$sid = $info[2];

echo "SID: $sid\n\n";

$data = "uid=$username&sid=$sid";

$ch = curl_init('https://cs4743.professorvaladez.com/api/request_all_documents');
curl_setopt($ch, CURLOPT_POST,1);
curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
$out = curl_exec($ch);
curl_close($ch);

$j = json_decode($out,true);

$tmp = explode(":", $j[1],2);
$arr = json_decode(trim($tmp[1]), true);

// print first 40
for ($i=0; $i<40; $i++) {
    echo $arr[$i] . "\n";
}
?>
