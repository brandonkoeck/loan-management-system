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

echo "<h2>Report 5: Loan completeness</h2>";

// doc types and their ids
$requiredTypes = [
    70 => "Closing",
    69 => "Credit",
    44 => "Disclosures",
    72 => "Financial",
    10 => "Internal",
    74 => "Legal",
    75 => "MOU",
    73 => "Personal",
    45 => "PreQs",
    46 => "References",
    34 => "Tax Returns",
    71 => "Title"
];

$requiredIds = array_keys($requiredTypes);

// get all loan ids that have at least 1 document
$loanSql = "
    SELECT DISTINCT loan_id
    FROM Documents
    WHERE upload_date BETWEEN '$start' AND '$end'
    ORDER BY loan_id
";
$loanResult = $dblink->query($loanSql);

$incomplete = [];
$complete   = [];

// loop thru loans and check which required doc types they have
if ($loanResult && $loanResult->num_rows > 0) {

    while ($loanRow = $loanResult->fetch_assoc()) {

        $loanId = $loanRow['loan_id'];

        // get the document types this loan has
        $typeSql = "
            SELECT DISTINCT document_type_id
            FROM Documents
            WHERE loan_id = '$loanId'
            AND upload_date BETWEEN '$start' AND '$end'
        ";
        $typeResult = $dblink->query($typeSql);

        $loanTypes = [];
        while ($tr = $typeResult->fetch_assoc()) {
            $loanTypes[] = $tr['document_type_id'];
        }

        // find which required doc types are missing
        $missing = array_diff($requiredIds, $loanTypes);

        if (count($missing) > 0) {
            // store missing type names
            $missingNames = [];
            foreach ($missing as $id) {
                $missingNames[] = $requiredTypes[$id];
            }
            $incomplete[$loanId] = $missingNames;
        } else {
            $complete[] = $loanId;
        }
    }
}

// find loans with 0 documents using Loans table
$zeroSql = "
    SELECT loan_id
    FROM Loans
    WHERE loan_id NOT IN (
        SELECT DISTINCT loan_id FROM Documents
    )
    ORDER BY loan_id
";
$zeroResult = $dblink->query($zeroSql);

$zeroLoans = [];
while ($zr = $zeroResult->fetch_assoc()) {
    $zeroLoans[] = $zr['loan_id'];
}

// print complete loans
echo "<hr><h3>Loans With All Required Documents:</h3>";

if (count($complete) == 0) {
    echo "<p>No loans have all required documents.</p>";
} else {
    foreach ($complete as $loanId) {
        echo "<p>$loanId</p>";
    }
}

// print loans with 0 documents
echo "<hr><h3>Loans With Zero Documents:</h3>";

if (count($zeroLoans) == 0) {
    echo "<p>No loans with zero documents.</p>";
} else {
    foreach ($zeroLoans as $loanId) {
        echo "<p>$loanId</p>";
    }
}

// print incomplete loans
echo "<hr><h3>Loans Missing Required Documents:</h3>";

if (count($incomplete) == 0) {
    echo "<p>All loans have all required documents.</p>";
} else {
    foreach ($incomplete as $loanId => $missingList) {
        echo "<p><strong>Loan:</strong> $loanId<br>";
        echo "Missing: " . implode(", ", $missingList) . "</p>";
    }
}

$dblink->close();
?>
