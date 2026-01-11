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

if($dblink->connect_errno){
    die("Database connection failed: " . $dblink->connect_error);
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
            <div class="panel-heading">Search by Loan ID</div>

            <div class="panel-body">

                <form method="post" action="">
                    <div class="form-group">
                        <label class="control-label">Loan ID:</label>
                        <input type="text" name="loanId" class="form-control" maxlength="50" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="submit" value="submit" class="btn btn-success">
                            Submit
                        </button>
                </div>
                </form>

                <?php
                if(isset($_POST['submit']) && $_POST['submit']=="submit"){

                    $loanId = trim($_POST['loanId']);

                    // invalid character check
                    if(!preg_match('/^[0-9A-Za-z\-]+$/', $loanId)){
                        echo '<div class="alert alert-danger">Invalid loan ID format.</div>';
                    } else {

                        $loanEsc = $dblink->real_escape_string($loanId);

                        // get all docs tied to a loan number
                        $sql = "
                            SELECT d.document_id, d.file_name, d.loan_id, d.file_size, d.last_access,
                                   t.type_name
                            FROM Documents d
                            LEFT JOIN DocumentTypes t ON d.document_type_id = t.document_type_id
                            WHERE d.loan_id = '$loanEsc'
                            ORDER BY d.file_name
                        ";

                        $results = $dblink->query($sql) or die("SQL error: " . $dblink->error);

                        if($results->num_rows == 0){
                            echo '<hr><div class="alert alert-warning">No files found for that loan ID.</div>';
                        } else {
							// if valid, display info table w/ View button
                            echo '<hr>';
                            echo '<table class="table table-striped">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>Loan ID</th>';
                            echo '<th>File Name</th>';
                            echo '<th>File Size</th>';
                            echo '<th>Last Access</th>';
                            echo '<th>Document Type</th>';
                            echo '<th>View</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';

							// loop thru results, display info
                            while($row = $results->fetch_assoc()){
                                echo '<tr>';

                                echo '<td>'.htmlspecialchars($row['loan_id']).'</td>';
                                echo '<td>'.htmlspecialchars($row['file_name']).'</td>';
								// print info, else N/A if not found
                                echo '<td>'.($row['file_size'] ? $row['file_size']." bytes" : "N/A").'</td>';

                                echo '<td>'.($row['last_access'] ? $row['last_access'] : "N/A").'</td>';

                                echo '<td>'.($row['type_name'] ? htmlspecialchars($row['type_name']) : "N/A").'</td>';

                                // take to View page
                                echo '<td><a href="search_view.php?fid='.$row['document_id'].'&auth=1">View</a></td>';

                                echo '</tr>';
                            }

                            echo '</tbody>';
                            echo '</table>';
                    }
                    }
                }
                ?>

        </div>
			
        </div>
    </div>
</div>

</body>
</html>
