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
    die("database connection failed: " . $dblink->connect_error);
}

// reporting window
$start = "2025-11-01 00:00:00";
$end   = "2025-11-20 23:59:59";

echo "<h2>Report 3: Total documents and average number of documents per loan</h2>";

// get total docs and average docs per loan
$sql = "
    SELECT 
        COUNT(*) AS total_docs,
        COUNT(*) / COUNT(DISTINCT loan_id) AS avg_docs_per_loan
    FROM Documents
    WHERE upload_date BETWEEN '$start' AND '$end'
";

$result = $dblink->query($sql);

// if query returned data, echo results
if ($result && $row = $result->fetch_assoc()) {

    $totalDocs = $row['total_docs'];
    $avgDocs   = $row['avg_docs_per_loan'];

    echo "<p><strong>Total documents:</strong> $totalDocs</p>";
    echo "<p><strong>Average documents per loan:</strong> $avgDocs</p>";

} else {
    echo "<p>No documents found in this date range.</p>";
}

$dblink->close();
?>
