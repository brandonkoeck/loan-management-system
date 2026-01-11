<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}

date_default_timezone_set("America/Chicago");

$log = "/var/www/html/cron/fetch.log";
file_put_contents($log, date('Y-m-d H:i:s') . " - fetch started\n", FILE_APPEND);

$username = $env['API_USERNAME'];
$password = $env['API_PASSWORD'];

// DB CONNECT
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

// GET UP TO 50 FILES (unprocessed)
$queue = $dblink->query("
    SELECT fid 
    FROM MissingFiles 
    WHERE processed = 0 
    ORDER BY added_at 
    LIMIT 50
");

if ($queue->num_rows === 0) {
    file_put_contents($log, "Nothing to fetch\n\n", FILE_APPEND);
    exit;
}


// --------------------------------------------------
// SESSION HANDLERS
// --------------------------------------------------

function create_session($username, $password, $log) {
    $data = "username=$username&password=$password";
    $ch = curl_init('https://cs4743.professorvaladez.com/api/create_session');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    $info = json_decode($result, true);
    if ($info && isset($info[0]) && $info[0] === "Status: OK") {
        file_put_contents($log, "New SID created: " . $info[2] . "\n", FILE_APPEND);
        return $info[2];
    }

    file_put_contents($log, "SID creation FAILED: $result\n", FILE_APPEND);
    return false;
}

function close_session($sid) {
    if (!$sid) return;
    $data = "sid=$sid";
    $ch = curl_init('https://cs4743.professorvaladez.com/api/close_session');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// --------------------------------------------------
// PROCESS QUEUE IN BATCHES OF 5
// --------------------------------------------------

$batchSize = 5;
$batch = [];
$fileList = [];

while ($row = $queue->fetch_assoc()) {
    $fileList[] = $row['fid'];
}

$total = count($fileList);
$index = 0;

while ($index < $total) {

    // extract next 5 file IDs
    $batch = array_slice($fileList, $index, $batchSize);
    $index += $batchSize;

    // OPEN NEW SESSION FOR THIS BATCH
    $sid = create_session($username, $password, $log);
    if (!$sid) {
        file_put_contents($log, "Could not create SID for batch, skipping\n", FILE_APPEND);
        continue;
    }

    // PROCESS EACH FILE IN THIS BATCH
    foreach ($batch as $fid) {

        file_put_contents($log, "Fetching $fid\n", FILE_APPEND);

        // REQUEST FILE
        $data = "sid=$sid&uid=$username&fid=$fid";
        $ch = curl_init('https://cs4743.professorvaladez.com/api/request_file');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 200);
        $content = curl_exec($ch);
        curl_close($ch);

        if (!$content) {
            file_put_contents($log, "FAILED: $fid (no content)\n", FILE_APPEND);
            $dblink->query("UPDATE MissingFiles SET processed = 1 WHERE fid='$fid'");
            continue;
        }

        // MIME CHECK
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($content);

        if ($mime !== "application/pdf") {
            file_put_contents($log, "FAILED: $fid invalid MIME ($mime)\n", FILE_APPEND);
            file_put_contents($log, "API RETURNED: " . substr($content, 0, 200) . "\n", FILE_APPEND);
            $dblink->query("UPDATE MissingFiles SET processed = 1 WHERE fid='$fid'");
            continue;
        }

        // PARSE loanId + type
        $parts = explode("-", $fid);
        $loanId = $parts[0];
        $docTypeFromFile = isset($parts[1]) ? $parts[1] : "unknown";

        // normalize type
        $base = preg_replace('/_[0-9]+$/', '', $docTypeFromFile);
        $base = strtolower(str_replace('_', ' ', trim($base)));

        $canonical = [
            'credit' => 'Credit',
            'closing' => 'Closing',
            'title' => 'Title',
            'financial' => 'Financial',
            'personal' => 'Personal',
            'legal' => 'Legal',
            'mou' => 'MOU',
            'disclosure' => 'Disclosures',
            'disclosures' => 'Disclosures',
            'tax return' => 'Tax Returns',
            'tax returns' => 'Tax Returns',
            'internal' => 'Internal',
            'reference' => 'References',
            'references' => 'References',
            'preqs' => 'PreQs',
            'pre-qs' => 'PreQs'
        ];

        $normalizedType = $canonical[$base] ?? "Internal";

        // ensure type exists
        $typeQuery = "SELECT document_type_id FROM DocumentTypes WHERE LOWER(type_name)=LOWER('$normalizedType')";
        $typeResult = $dblink->query($typeQuery);

        if ($typeResult && $typeResult->num_rows > 0) {
            $documentTypeId = (int)$typeResult->fetch_assoc()['document_type_id'];
        } else {
            $dblink->query("INSERT INTO DocumentTypes (type_name, is_required) VALUES ('$normalizedType', 0)");
            $documentTypeId = $dblink->insert_id;
        }

        // ensure loan exists
        $loanCheck = $dblink->query("SELECT loan_id FROM Loans WHERE loan_id='$loanId'");
        if ($loanCheck->num_rows == 0) {
            $dblink->query("INSERT INTO Loans (loan_id) VALUES ('$loanId')");
        }

        // insert document
        $contentSQL = addslashes($content);
        $fileSize = strlen($content);

        $sql = "
            INSERT INTO Documents
                (file_name, loan_id, content, document_type_id, file_size, uploaded_by)
            VALUES
                ('$fid', '$loanId', '$contentSQL', '$documentTypeId', '$fileSize', 'cron')
        ";

        if ($dblink->query($sql)) {
            file_put_contents($log, "Stored $fid\n", FILE_APPEND);
        } else {
            file_put_contents($log, "DB ERROR storing $fid: " . $dblink->error . "\n", FILE_APPEND);
        }

        $dblink->query("UPDATE MissingFiles SET processed = 1 WHERE fid='$fid'");
    }

    // CLOSE SID FOR THIS BATCH
    close_session($sid);
}

file_put_contents($log, "fetch complete\n\n", FILE_APPEND);
?>
