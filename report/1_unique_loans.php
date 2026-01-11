<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}

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

// get list of unique loan IDs in the date range
$loanSql = "
    SELECT DISTINCT loan_id
    FROM Documents
    WHERE upload_date BETWEEN '$start' AND '$end'
    ORDER BY loan_id
";
$loanResult = $dblink->query($loanSql);

echo "<h2>Report 1: Unique loans</h2>";
echo "<h3>Loan Numbers: (total at bottom):</h3>";

$loanList = [];

// if loans are found, loop thru and echo them
if ($loanResult && $loanResult->num_rows > 0) {
    while ($row = $loanResult->fetch_assoc()) {
        $loanList[] = $row['loan_id'];
        echo $row['loan_id'] . "<br>";
    }
} else {
    echo "No loans found in this date range.<br>";
}

// count unique loans
$countSql = "
    SELECT COUNT(DISTINCT loan_id) AS unique_loan_count
    FROM Documents
    WHERE upload_date BETWEEN '$start' AND '$end'
";
$countResult = $dblink->query($countSql);
$countRow = $countResult->fetch_assoc();
$total = $countRow['unique_loan_count'];

echo "<br><strong>Total unique loans: $total</strong>";

$dblink->close();
?>
