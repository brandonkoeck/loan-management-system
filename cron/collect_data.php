<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}

// set to central time
date_default_timezone_set('America/Chicago');

// log file for monitoring
$logFile = '/var/www/html/cron/collection.log';

// heartbeat log to confirm cron runs even if no files are collected
file_put_contents($logFile, date('Y-m-d H:i:s') . " - cron ran\n", FILE_APPEND);

file_put_contents($logFile, date('Y-m-d H:i:s') . " - starting data collection\n", FILE_APPEND);

$username = $env['API_USERNAME'];
$password = $env['API_PASSWORD'];

// --- CLEANUP: close any previous session before creating a new one ---
$data = "username=$username&password=$password";
$ch = curl_init('https://cs4743.professorvaladez.com/api/close_session');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'content-type: application/x-www-form-urlencoded',
    'content-length: ' . strlen($data)
));
$closeResult = curl_exec($ch);
curl_close($ch);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - preemptive close_session result: $closeResult\n", FILE_APPEND);


// --- CREATE SESSION (robust version with ghost-session recovery) ---
$data = "username=$username&password=$password";

function attempt_create_session($data, $logFile) {
    $ch = curl_init('https://cs4743.professorvaladez.com/api/create_session');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'content-type: application/x-www-form-urlencoded',
        'content-length: ' . strlen($data)
    ));
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - create_session attempt result: $res\n", FILE_APPEND);

    return [$res, $err];
}

// FIRST ATTEMPT
list($result, $curlError) = attempt_create_session($data, $logFile);

if (!$result) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - CURL ERROR: $curlError\n", FILE_APPEND);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Sleeping 10s then retrying...\n", FILE_APPEND);
    sleep(10);
    list($result, $curlError) = attempt_create_session($data, $logFile);

    if (!$result) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - FINAL ERROR: CURL failed again ($curlError)\n", FILE_APPEND);
        exit;
    }
}

$result = trim($result);
$result = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $result);
$info = json_decode($result, true);

// HANDLE JSON DECODE ERROR
if (!$info || !isset($info[0])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - JSON decode error | Raw: $result\n", FILE_APPEND);
    exit;
}

// SPECIAL FIX — HANDLE "Previous Session Found"
if ($info[0] !== "Status: OK" && isset($info[1]) && str_contains($info[1], "Previous Session Found")) {

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Ghost session detected. Forcing hard close...\n", FILE_APPEND);

    // HARD CLOSE (NO SID)
    $hard = "username=$username&password=$password";
    $ch = curl_init('https://cs4743.professorvaladez.com/api/close_session');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $hard);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $hardClose = curl_exec($ch);
    curl_close($ch);

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - hard close_session result: $hardClose\n", FILE_APPEND);

    sleep(5);

    // TRY AGAIN AFTER HARD CLOSE
    list($result, $curlError) = attempt_create_session($data, $logFile);

    $result = trim($result);
    $result = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $result);
    $info = json_decode($result, true);

    if (!$info || !isset($info[0]) || $info[0] !== "Status: OK") {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - STILL failed after hard close. Sleeping 10s and retrying...\n", FILE_APPEND);
        sleep(10);

        // FINAL ATTEMPT
        list($result, $curlError) = attempt_create_session($data, $logFile);
        $info = json_decode(trim($result), true);

        if (!$info || !isset($info[0]) || $info[0] !== "Status: OK") {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - FINAL FAILURE: cannot create session. Raw: $result\n", FILE_APPEND);
            exit;
        }
    }
}

// SUCCESS CASE
$sid = $info[2];
file_put_contents($logFile, date('Y-m-d H:i:s') . " - session created: $sid\n", FILE_APPEND);


$info = json_decode($result);

if (!$info || $info[0] != "Status: OK") {
	file_put_contents($logFile, date('Y-m-d H:i:s') . " - query_files raw result: $result\n", FILE_APPEND);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR querying files\n", FILE_APPEND);
    // close session before exit
    $data = "sid=$sid";
    $ch = curl_init('https://cs4743.professorvaladez.com/api/close_session');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'content-type: application/x-www-form-urlencoded',
        'content-length: '.strlen($data)));
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// parse file list
$tmp = explode(":", $info[1]);
$files = json_decode($tmp[1]);

if (!$files || count($files) == 0) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - no files to collect\n", FILE_APPEND);
    // close session
    $data = "sid=$sid";
    $ch = curl_init('https://cs4743.professorvaladez.com/api/close_session');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'content-type: application/x-www-form-urlencoded',
        'content-length: '.strlen($data)));
    curl_exec($ch);
    curl_close($ch);
    exit;
}

file_put_contents($logFile, date('Y-m-d H:i:s') . " - found " . count($files) . " files\n", FILE_APPEND);

// connect to database
$dblink = new mysqli(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASSWORD'],
    $env['DB_NAME']
);

if ($dblink->connect_error) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: database connection failed\n", FILE_APPEND);
    exit;
}

// download and store each file
foreach($files as $key => $value) {
    // check if file already exists
    $checkSql = "SELECT document_id FROM Documents WHERE file_name = '$value'";
    $checkResult = $dblink->query($checkSql);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - skipping $value (already exists)\n", FILE_APPEND);
        continue;
    }

    // --- REFRESH SESSION BEFORE EACH FILE (safe version) ---
    // Close old session (if any)
    if (!empty($sid)) {
        $data = "sid=$sid";
        $ch = curl_init('https://cs4743.professorvaladez.com/api/close_session');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'content-type: application/x-www-form-urlencoded',
            'content-length: '.strlen($data)
        ));
        curl_exec($ch);
        curl_close($ch);
    }

    // Create a fresh session for each file (short timeout safe)
    $data = "username=$username&password=$password";
    $ch = curl_init('https://cs4743.professorvaladez.com/api/create_session');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'content-type: application/x-www-form-urlencoded',
        'content-length: '.strlen($data)
    ));
    $result = curl_exec($ch);
    curl_close($ch);

    $info = json_decode($result);
    if ($info && $info[0] == "Status: OK") {
        $sid = $info[2];
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - session refreshed for $value: $sid\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: could not refresh session for $value\n", FILE_APPEND);
        continue; // skip this file
    }

    // --- DOWNLOAD FILE WITH RETRY LOGIC ---
    $maxRetries = 3;
    $retryDelay = 2; // seconds between retries
    $content = false;
    $validPdf = false;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $data = "sid=$sid&uid=$username&fid=$value";
        $ch = curl_init('https://cs4743.professorvaladez.com/api/request_file');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout for large files
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'content-type: application/x-www-form-urlencoded',
            'content-length: '.strlen($data)
        ));
        $content = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$content || $httpCode != 200) {
            if ($attempt < $maxRetries) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - attempt $attempt failed for $value (HTTP $httpCode), retrying...\n", FILE_APPEND);
                sleep($retryDelay);
            }
            continue;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($content);

        if ($mime === 'application/pdf') {
            if ($attempt > 1) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - successfully downloaded $value on attempt $attempt\n", FILE_APPEND);
            }
            $validPdf = true;
            break;
        } else {
            $preview = substr($content, 0, 200);
            if ($attempt < $maxRetries) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - attempt $attempt failed for $value (wrong MIME: $mime), retrying... Preview: $preview\n", FILE_APPEND);
                sleep($retryDelay);
            } else {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $value has wrong MIME type ($mime) after $maxRetries attempts. Content preview: $preview\n", FILE_APPEND);
            }
        }
    }

    if (!$validPdf) {
        continue;
    }

    // --- DATABASE LOGIC (unchanged from your version) ---
    $tmp = explode("-", $value);
    $loanId = $tmp[0];

    $loanCheckSql = "SELECT loan_id FROM Loans WHERE loan_id = '$loanId'";
    $loanCheckResult = $dblink->query($loanCheckSql);

    if (!$loanCheckResult || $loanCheckResult->num_rows == 0) {
        $createLoanSql = "INSERT INTO Loans (loan_id) VALUES ('$loanId')";
        if (!$dblink->query($createLoanSql)) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR creating loan $loanId: " . $dblink->error . "\n", FILE_APPEND);
            continue;
        }
    }

	// --- Normalize and handle document type name ---
	$docTypeFromFile = isset($tmp[1]) ? $tmp[1] : 'unknown';
	$originalType = $docTypeFromFile;

	// strip trailing _<digits> like Financial_3
	$base = preg_replace('/_[0-9]+$/', '', $docTypeFromFile);
	// unify: underscores -> spaces, lowercase
	$base = strtolower(str_replace('_', ' ', trim($base)));

	// canonical targets MUST match exactly what's in your DocumentTypes table
	$canonical = [
	  'credit'        => 'Credit',
	  'closing'       => 'Closing',
	  'title'         => 'Title',
	  'financial'     => 'Financial',
	  'personal'      => 'Personal',
	  'legal'         => 'Legal',
	  'mou'           => 'MOU',
	  'disclosure'    => 'Disclosures',   // your table shows plural
	  'disclosures'   => 'Disclosures',
	  'tax return'    => 'Tax Returns',   // your table shows plural
	  'tax returns'   => 'Tax Returns',
	  'internal'      => 'Internal',
	  'preqs'         => 'PreQs',
	  'pre-qs'        => 'PreQs',
	  'reference'     => 'References',
	  'references'    => 'References',
	];

	// choose canonical or default to Internal
	if (isset($canonical[$base])) {
		$normalizedType = $canonical[$base];
		if (strcasecmp($normalizedType, $originalType) !== 0) {
			file_put_contents($logFile, date('Y-m-d H:i:s') .
			  " - normalized '$originalType' → '$normalizedType'\n", FILE_APPEND);
		}
	} else {
		$normalizedType = 'Internal';
		file_put_contents($logFile, date('Y-m-d H:i:s') .
		  " - unknown type '$originalType' normalized to 'Internal'\n", FILE_APPEND);
	}

	// look up (or create) the type
	$typeQuery = "SELECT document_type_id FROM DocumentTypes WHERE LOWER(type_name) = LOWER('$normalizedType')";
	$typeResult = $dblink->query($typeQuery);
	if ($typeResult && $typeResult->num_rows > 0) {
		$typeRow = $typeResult->fetch_assoc();
		$documentTypeId = (int)$typeRow['document_type_id'];
	} else {
		$insertTypeSql = "INSERT INTO DocumentTypes (type_name, is_required) VALUES ('$normalizedType', 0)";
		if ($dblink->query($insertTypeSql)) {
			$documentTypeId = $dblink->insert_id;
			file_put_contents($logFile, date('Y-m-d H:i:s') .
			  " - NEW document type added (normalized): $normalizedType\n", FILE_APPEND);
		} else {
			file_put_contents($logFile, date('Y-m-d H:i:s') .
			  " - ERROR adding doc type $normalizedType\n", FILE_APPEND);
			$documentTypeId = 1; // fallback
		}
	}


    $contentClean = addslashes($content);
    $fileSize = strlen($content);

    $sql = "INSERT INTO Documents (file_name, loan_id, content, document_type_id, file_size, uploaded_by)
            VALUES ('$value', '$loanId', '$contentClean', '$documentTypeId', '$fileSize', 'cron')";

    if ($dblink->query($sql)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - successfully stored $value\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR storing $value: " . $dblink->error . "\n", FILE_APPEND);
    }
}


$dblink->close();

// close session
$data = "sid=$sid";
$ch = curl_init('https://cs4743.professorvaladez.com/api/close_session');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'content-type: application/x-www-form-urlencoded',
    'content-length: '.strlen($data)));
curl_exec($ch);
curl_close($ch);

file_put_contents($logFile, date('Y-m-d H:i:s') . " - collection complete\n\n", FILE_APPEND);
?>