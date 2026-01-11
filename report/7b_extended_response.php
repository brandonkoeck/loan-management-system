<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}

// report part 7b: extended response times

// reporting window
// convert string to timestamp for ease of comparison
$startDate = strtotime("2025-11-01 00:00:00");
$endDate   = strtotime("2025-11-20 23:59:59");

// scan thru log files
$logFiles = [
    "/var/www/html/cron/collection.log",
    "/var/www/html/cron/audit.log",
    "/var/www/html/cron/audit_output.log",
    "/var/www/html/cron/fetch.log",
    "/var/www/html/cron/fetch_output.log",
    "/var/www/html/cron/cron_output.log"
];

// list of all potential slow response errors from my logs
$slowPatterns = [
    "Sleeping",
    "retrying",
    "Retrying",
    "attempt 1 failed",
    "attempt 2 failed",
    "attempt 3 failed",
    "timed out",
    "timeout",
    "connect timeout",
    "operation timed out",
    "ERROR downloading",
    "took too long",
    "504"
];


$totalSlow = 0; // error count
$slowEntries = []; // array to hold error info

echo "<h2>Report 7B: Extended responses</h2>";

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
        foreach ($slowPatterns as $word) {
			// if each line contains error keyword (stripos to ignore case):
            if (stripos($line, $word) !== false) {

                $totalSlow++;

				// store error details in array
                $slowEntries[] = [
                    "file" => basename($file),
                    "line" => trim($line)
                ];

				// break out of error check for this line
                break;
            }
        }
    }
}

echo "<p><strong>Total Extended Response Events:</strong> $totalSlow</p>";

// display error info from entries array
echo "<h3>Extended Response Details</h3>";
foreach ($slowEntries as $s) {
    echo "<p><strong>Log File:</strong> {$s['file']}<br>";
	
	// convert html to safe text
    $cleanLine = htmlspecialchars($s['line']);
	echo "<strong>Entry:</strong> $cleanLine</p><hr>";

}
?>
