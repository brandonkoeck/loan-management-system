<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}
session_start();

$dblink = new mysqli(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASSWORD'],
    $env['DB_NAME']
);
if ($dblink->connect_errno) {
    die("<h3>Database connection failed: " . $dblink->connect_error . "</h3>");
}
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Document Management Web Front End</title>
<link href="assets/css/bootstrap.css" rel="stylesheet">

<style>
.main-box {
    text-align:center;
    padding:20px;
    border-radius:5px;
    margin-bottom:40px;
}
</style>
</head>

<body>
<div class="row main-box">
    <h3>Document Management System</h3>
    <hr>

    <div class="col-md-12">
        <div class="panel panel-primary">
        <div class="panel-heading">List All Files</div>

            <div class="panel-body">

                <?php
				// get all docs and doc types from db
				// SORT BY LOAN ID THEN FILE NAME
                $sql = "
                    SELECT
                        d.document_id,
                        d.file_name,
                        d.loan_id,
                        d.file_size,
                        d.last_access,
                        t.type_name
                    FROM Documents d
                    LEFT JOIN DocumentTypes t
                        ON d.document_type_id = t.document_type_id
                    ORDER BY d.loan_id, d.file_name
                ";

                $searchResults = $dblink->query($sql) or
                    die("<h3>Something went wrong with: $sql<br>" . $dblink->error . "</h3>");

                // no files found error
                if ($searchResults->num_rows < 1) {
                    echo '<div class="alert alert-warning">No files found in the system.</div>';
                } else {

                    echo '<table class="table table-striped">';
                    echo '<thead><tr>
                            <th>Loan ID</th>
                            <th>File Name</th>
                            <th>File Size</th>
                            <th>Last Access</th>
                            <th>Document Type</th>
                            <th>View</th>
                          </tr></thead>';
                    echo '<tbody>';

                    // loop thru and print rows (htmlspecialchars ensure safe string)
                    while ($row = $searchResults->fetch_assoc()) {

                        echo '<tr>';

                        echo '<td>'.htmlspecialchars($row['loan_id']).'</td>';

                        echo '<td>'.htmlspecialchars($row['file_name']).'</td>';

                        echo '<td>'.($row['file_size'] ? $row['file_size']." bytes" : "N/A").'</td>';

                        echo '<td>'.($row['last_access'] ? $row['last_access'] : "N/A").'</td>';

                        echo '<td>'.htmlspecialchars($row['type_name']).'</td>';

                        // go back to search view page (with auth)
                        echo '<td><a href="search_view.php?fid='.$row['document_id'].'&auth=1">View</a></td>';

                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                }
                ?>
           </div>
        </div>
		
    </div>

</div>
</body>
</html>
