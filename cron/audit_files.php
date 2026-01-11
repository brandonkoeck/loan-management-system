<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}

date_default_timezone_set("America/Chicago");

$log = "/var/www/html/cron/audit.log";
file_put_contents($log, date('Y-m-d H:i:s') . " - audit started\n", FILE_APPEND);

// -----------------------------------------------------------------------------
// DB CONNECT
// -----------------------------------------------------------------------------
$dblink = new mysqli(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASSWORD'],
    $env['DB_NAME']
);

if ($dblink->connect_error) {
    file_put_contents($log, "DB ERROR\n", FILE_APPEND);
    exit;
}

// -----------------------------------------------------------------------------
// CONFIG (username and password)
// -----------------------------------------------------------------------------
$username = $env['API_USERNAME'];
$password = $env['API_PASSWORD'];

// -----------------------------------------------------------------------------
// STEP 1: CREATE SESSION
// -----------------------------------------------------------------------------
$data = "username=$username&password=$password";

$ch = curl_init('https://cs4743.professorvaladez.com/api/create_session');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

$info = json_decode($result, true);

if (!$info || $info[0] !== "Status: OK") {
    file_put_contents($log, "CREATE SESSION ERROR: $result\n", FILE_APPEND);
    exit;
}

$sid = $info[2];
file_put_contents($log, "Session created: $sid\n", FILE_APPEND);

// -----------------------------------------------------------------------------
// STEP 2: CALL request_all_documents WITH SID + UID
// -----------------------------------------------------------------------------
$data = "uid=$username&sid=$sid";

$ch = curl_init('https://cs4743.professorvaladez.com/api/request_all_documents');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
curl_close($ch);

$info = json_decode($result, true);

if (!$info || $info[0] !== "Status: OK") {
    file_put_contents($log, "API ERROR: $result\n", FILE_APPEND);
    exit;
}

// -----------------------------------------------------------------------------
// PARSE FILE LIST
// -----------------------------------------------------------------------------
$tmp = explode(":", $info[1], 2);
$fileList = json_decode(trim($tmp[1]), true);

if (!$fileList) {
    file_put_contents($log, "PARSE ERROR (file list)\n", FILE_APPEND);
    exit;
}

file_put_contents($log, "Total files returned: " . count($fileList) . "\n", FILE_APPEND);

// -----------------------------------------------------------------------------
// FILTER BY DATE RANGE (2025-11-01 to 2025-11-21)
// -----------------------------------------------------------------------------
$start = strtotime("2025-11-01");
$end   = strtotime("2025-11-21 23:59:59");

$filtered = [];

foreach ($fileList as $fname) {

    // split by "-"
    $parts = explode("-", $fname);

    // We need at least: loan - type - datePart
    if (count($parts) < 3) continue;

    // third part looks like: 20251101_00_00_01.pdf
    $dateChunk = $parts[2];

    // remove everything after the first "_"
    // turns "20251101_00_00_01.pdf" into "20251101"
    $yyyymmdd = substr($dateChunk, 0, 8);

    // Safety check
    if (strlen($yyyymmdd) !== 8 || !ctype_digit($yyyymmdd)) {
        continue;
    }

    // Convert to "YYYY-MM-DD"
    $year  = substr($yyyymmdd, 0, 4);
    $month = substr($yyyymmdd, 4, 2);
    $day   = substr($yyyymmdd, 6, 2);

    $dateString = "$year-$month-$day";
    $ts = strtotime($dateString);

    if ($ts >= $start && $ts <= $end) {
        $filtered[] = $fname;
    }
}

file_put_contents($log, "Filtered to collection window: " . count($filtered) . " files\n", FILE_APPEND);


// -----------------------------------------------------------------------------
// LOAD EXISTING LOCAL FILE NAMES
// -----------------------------------------------------------------------------
$existing = [];
$res = $dblink->query("SELECT file_name FROM Documents");
while ($row = $res->fetch_assoc()) {
    $existing[$row['file_name']] = true;
}

file_put_contents($log, "Local files: " . count($existing) . "\n", FILE_APPEND);

// -----------------------------------------------------------------------------
// INSERT MISSING FILES INTO MissingFiles QUEUE
// -----------------------------------------------------------------------------
$insertCount = 0;

$stmt = $dblink->prepare("INSERT IGNORE INTO MissingFiles (fid) VALUES (?)");

foreach ($filtered as $fname) {
    if (!isset($existing[$fname])) {
        $stmt->bind_param("s", $fname);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $insertCount++;
        }
    }
}

file_put_contents($log,
    "Missing files added to queue: $insertCount\n",
    FILE_APPEND
);

// -----------------------------------------------------------------------------
// FINISH
// -----------------------------------------------------------------------------
file_put_contents($log, "audit complete\n\n", FILE_APPEND);

?>
