<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// create sesssion to track when file viewing is valid
session_start();

// check if pdf is coming from a valid search
if(!isset($_SESSION['allowed_pdf'])){
    die("Your PDF view has expired. Please start a new search");
}

// check if link ID matches the allowed document ID
$fidParam = intval($_GET['fid']);
if($fidParam != $_SESSION['allowed_pdf']){
    die("Access denied.");
}

$dblink = new mysqli(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASSWORD'],
    $env['DB_NAME']
);
if($dblink->connect_errno){
    die("DB connection failed: " . $dblink->connect_error);
}

$fid = $fidParam;

// pull the file from database
$sql = "SELECT file_name, content FROM Documents WHERE document_id='$fid'";
$result = $dblink->query($sql) or die("SQL error: " . $dblink->error);

if($result->num_rows < 1){
    die("File not found.");
}

$data = $result->fetch_assoc();

// update time accessed
$upd = "UPDATE Documents SET last_access=NOW() WHERE document_id='$fid'";
$dblink->query($upd);

// send pdf content to browser
header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=".$data['file_name']);
echo $data['content'];

// remove access so we cant view the same PDF afte reloading
unset($_SESSION['allowed_pdf']);
exit;
