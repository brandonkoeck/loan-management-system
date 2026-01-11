<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}

// report part 7a: disconnect errors
// i scanned thru my cron log files to match all error keywords that are related to a disconnect

// reporting window
// convert string to timestamp for ease of comparison
$startDate = strtotime("2025-11-01 00:00:00");
$endDate   = strtotime("2025-11-20 23:59:59");

// scan thru log files (from cron jobs)
$logFiles = [
    "/var/www/html/cron/collection.log",
    "/var/www/html/cron/audit.log",
    "/var/www/html/cron/audit_output.log",
    "/var/www/html/cron/fetch.log",
    "/var/www/html/cron/fetch_output.log",
    "/var/www/html/cron/cron_output.log"
];

// list of all potential disconnect errors from my logs
$disconnectKeywords = [
    "SSL_ERROR_SYSCALL",
    "Could not resolve host",
    "ERROR: could not create session",
    "FINAL FAILURE",
    "CURL ERROR",
    "JSON decode error",
    "HTTP 0",
    "ERROR querying files",
    "Previous Session Found",
    "SID not found",
    "Status: ERROR",
    "operation timed out",
    "connect timeout",
	"wrong MIME type"
];

$totalErrors = 0; // error count
$errorEntries = []; // array to hold error info

echo "<h2>Report 7A: Disconnect errors</h2>";

// loop thru log files
foreach ($logFiles as $file) {

    // skip file if it doesn't exist
    if (!file_exists($file)) continue;

    // read log file lines
    $lines = file($file);

    // for each line:
    foreach ($lines as $line) {
		
		// extract the date/time from the beginning of the line
		if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
			$timestamp = strtotime($matches[1]);

			// skip lines outside of the reporting window
			if ($timestamp < $startDate || $timestamp > $endDate) {
				continue;
			}
		}


        // for each error keyword:
        foreach ($disconnectKeywords as $word) {

            // if each line contains error keyword:
            if (stripos($line, $word) !== false) {

                $totalErrors++;
                // store error details in array
                $errorEntries[] = [
                    "file" => basename($file),
                    "line" => trim($line),
                    "keyword" => $word
                ];

                // break out of error check for this line
                break;
            }
        }
    }
}


echo "<p><strong>Total Disconnect Errors:</strong> $totalErrors</p>";

// display error info from entries array
echo "<h3>Disconnect Error Details</h3>";
foreach ($errorEntries as $e) {
    echo "<p><strong>Log File:</strong> {$e['file']}<br>";
    echo "<strong>Matched:</strong> {$e['keyword']}<br>";
    echo "<strong>Entry:</strong> {$e['line']}</p><hr>";
}
?>