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

// pull doc types from db
$sql = "SELECT * FROM DocumentTypes ORDER BY type_name";
$typeResults = $dblink->query($sql) or die("SQL error: " . $dblink->error);

// display doc type array
$docTypes = array();
while($row = $typeResults->fetch_assoc()){
    $docTypes[] = $row["type_name"];
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
            <div class="panel-heading">Search by Document Type</div>

            <div class="panel-body">

                <!-- select doc type w/ input for loan filtering -->
                <form method="post" action="">
                    <div class="form-group">
                        <label class="control-label">Document Type:</label>
                        <select name="docType" class="form-control">
                        <?php
                            foreach($docTypes as $t){
                                echo '<option value="'.htmlspecialchars($t).'">'.htmlspecialchars($t).'</option>';
                            }
                        ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="control-label">Loan Filter (optional):</label>
                        <input type="text" name="loanId" class="form-control"
                               placeholder="Leave blank to view this type for all loans">
                    </div>

                    <button type="submit" name="submit" value="submit" class="btn btn-success">
                        Submit
                    </button>
                </form>

                <?php
                // once the form is submitted, run the search
                if(isset($_POST['submit']) && $_POST['submit']=="submit"){

                    $docType = $dblink->real_escape_string($_POST['docType']);
                    $loanFilter = trim($_POST['loanId']);

                    // display docs from specified types
                    $sql = "
                        SELECT d.document_id, d.file_name, d.loan_id,
                               d.file_size, d.last_access,
                               t.type_name
                        FROM Documents d
                        JOIN DocumentTypes t ON d.document_type_id = t.document_type_id
                        WHERE t.type_name = '$docType'
                    ";

                    // check if valid loan number was entered (for filter)
                    if($loanFilter !== "") {
                        if(preg_match('/^[0-9A-Za-z\-]+$/', $loanFilter)) {
							
                            $loanEsc = $dblink->real_escape_string($loanFilter);
                            $sql .= " AND d.loan_id='$loanEsc'"; // only show results from specified loan
							// print which doc types are being shown for loan filter
                            echo '<div class="alert alert-info">Showing '.htmlspecialchars($docType).
                                 ' documents for loan '.htmlspecialchars($loanFilter).'.</div>';
							
                        } else { // loan id was input but invalid
                            echo '<div class="alert alert-danger">Invalid loan ID. Showing all loans instead.</div>';
                        }
                    } else { // show results for all loans (no loan specified)
                        echo '<div class="alert alert-info">Showing '.htmlspecialchars($docType).
                             ' documents for ALL loans.</div>';
                    }

                    $sql .= " ORDER BY d.loan_id, d.file_name";

                    $searchResults = $dblink->query($sql) or die("SQL error: " . $dblink->error);

                    if($searchResults->num_rows < 1){
                        echo '<hr><div class="alert alert-warning">No files found.</div>';
                    } else {
						// if files are found, display info table
                        echo '<hr>';
                        echo '<table class="table table-striped">';
                        echo '<thead>';
                        echo '<tr>
                                <th>Loan ID</th>
                                <th>File Name</th>
                                <th>File Size</th>
                                <th>Last Access</th>
                                <th>Document Type</th>
                                <th>View</th>
                              </tr>';
                        echo '</thead><tbody>';

                        while($searchRow = $searchResults->fetch_assoc()){

                            echo '<tr>';
							// use htmlspecialchars to convert special characters into html 
                            echo '<td>'.htmlspecialchars($searchRow['loan_id']).'</td>';
                            echo '<td>'.htmlspecialchars($searchRow['file_name']).'</td>';

                            echo '<td>'.($searchRow['file_size'] ? $searchRow['file_size']." bytes" : "N/A").'</td>';

                            echo '<td>'.($searchRow['last_access'] ? $searchRow['last_access'] : "N/A").'</td>';

                            echo '<td>'.htmlspecialchars($searchRow['type_name']).'</td>';

                            echo '<td><a href="search_view.php?fid='.$searchRow['document_id'].'&auth=1">View</a></td>';

                            echo '</tr>';
                        }

                        echo '</tbody></table>';
                 }
                }
                ?>

    </div>
    </div>
    </div>
</div>

</body>
</html>
