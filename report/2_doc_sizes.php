<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// connect to database
$dblink = new mysqli(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASSWORD'],
    $env['DB_NAME']
);
if ($dblink->connect_errno) {
    die("Database connection failed: " . $dblink->connect_error);
}

// reporting window
$start = "2025-11-01 00:00:00";
$end   = "2025-11-20 23:59:59";

echo "<h2>Report 2: Total and average document size</h2>";

// get total and average file sizes
$sql = "
    SELECT 
        SUM(file_size) AS total_size_bytes,
        AVG(file_size) AS avg_size_bytes
    FROM Documents
    WHERE upload_date BETWEEN '$start' AND '$end'
";

$result = $dblink->query($sql);

// if query works, store and echo total/avg size variables
if ($result && $row = $result->fetch_assoc()) {

    $totalBytes = $row['total_size_bytes'];
    $avgBytes   = $row['avg_size_bytes'];

    echo "<p><strong>Total size (bytes):</strong> $totalBytes</p>";
    echo "<p><strong>Average size (bytes):</strong> $avgBytes</p>";
} else {
    echo "<p>No documents were found in the date range</p>";
}

$dblink->close();
?>
