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

echo "<h2>Report 6: Total number of documents per type</h2>";

// count documents grouped by type
$sql = "
    SELECT 
        dt.type_name,
        COUNT(d.document_id) AS total_docs
    FROM Documents d
    JOIN DocumentTypes dt
          ON d.document_type_id = dt.document_type_id
    WHERE d.upload_date BETWEEN '$start' AND '$end'
    GROUP BY dt.type_name
    ORDER BY dt.type_name
";

$result = $dblink->query($sql);

// check if result returned rows
if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {
        $typeName = $row['type_name'];
        $count    = $row['total_docs'];

        echo "<p><strong>$typeName:</strong> $count</p>";
    }

} else {
    echo "<p>No document types found in this date range.</p>";
}

$dblink->close();
?>
