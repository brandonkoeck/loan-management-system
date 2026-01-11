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

echo "<h2>Report 4: Loan average comparison</h2>";

// get global average doc size
$globalSizeSql = "
    SELECT AVG(file_size) AS global_avg_size
    FROM Documents
    WHERE upload_date BETWEEN '$start' AND '$end'
";
$globalSizeResult = $dblink->query($globalSizeSql); // run query
$globalSizeRow = $globalSizeResult->fetch_assoc(); // get result for average doc sizes
$globalAvgSize = $globalSizeRow['global_avg_size']; // store avg file size in variable

// get global avg number of docs per loan
$globalDocsSql = "
    SELECT COUNT(*) / COUNT(DISTINCT loan_id) AS global_avg_docs
    FROM Documents
    WHERE upload_date BETWEEN '$start' AND '$end'
";
$globalDocsResult = $dblink->query($globalDocsSql); // run query
$globalDocsRow = $globalDocsResult->fetch_assoc(); // get result for average number of docs
$globalAvgDocs = $globalDocsRow['global_avg_docs']; // store avg number of docs in variable

echo "<p><strong>Global average document size (bytes):</strong> $globalAvgSize</p>";
echo "<p><strong>Global average documents per loan:</strong> $globalAvgDocs</p>";
echo "<hr>";

// get per loan stats (number of docs and avg file size)
$loanSql = "
    SELECT 
        loan_id,
        COUNT(*) AS doc_count,
        AVG(file_size) AS avg_doc_size
    FROM Documents
    WHERE upload_date BETWEEN '$start' AND '$end'
    GROUP BY loan_id
    ORDER BY loan_id
";
$loanResult = $dblink->query($loanSql);

// check if query returned anything
if ($loanResult && $loanResult->num_rows > 0) {
	// loop thru result rows
    while ($row = $loanResult->fetch_assoc()) {

        $loanId   = $row['loan_id'];
        $docCount = $row['doc_count'];
        $avgSize  = $row['avg_doc_size'];

        // compare each loan to global averages
        $sizeComparison = ($avgSize > $globalAvgSize) ? "Above global average" : "Below global average";
        $docsComparison = ($docCount > $globalAvgDocs) ? "Above global average" : "Below global average";

        echo "<p><strong>Loan:</strong> $loanId<br>";
        echo "Total Documents: $docCount<br>";
        echo "Average Document Size (bytes): $avgSize<br>";
        echo "Size Comparison: $sizeComparison<br>";
        echo "Document Count Comparison: $docsComparison</p>";
        echo "<hr>";
    }

} else {
    echo "<p>No loans found in this date range.</p>";
}

$dblink->close();
?>
