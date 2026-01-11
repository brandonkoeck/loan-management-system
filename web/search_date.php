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
            <div class="panel-heading">Search by Document date</div>

            <div class="panel-body">

                <!-- search form for one specific date -->
                <form method="post" action="" style="margin-bottom:25px;">
                    <div class="form-group">
                        <label class="control-label">Specific Date:</label>
                        <input type="date" name="exactDate" class="form-control">
                    </div>
                    <button type="submit" name="submit" value="single" class="btn btn-primary">
                        Search Specific Date
                    </button>
                </form>

                <hr>

                <!-- date range search form -->
                <form method="post" action="">
                    <div class="form-group">
                        <label class="control-label">Start Date (optional):</label>
                        <input type="date" name="startDate" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="control-label">End Date (optional):</label>
                        <input type="date" name="endDate" class="form-control">
                    </div>

                    <p class="text-muted">Enter at least one date</p>

                    <button type="submit" name="submit" value="range" class="btn btn-success">
                        Search Date Range
                    </button>
                </form>

                <?php
				// SEARCH SPECIFIC DATE (ONE DAY)
                if(isset($_POST['submit']) && $_POST['submit']=="single"){

                    $exact = isset($_POST['exactDate']) ? $_POST['exactDate'] : "";
					// if empty, display message
                    if($exact === ""){
                        echo '<div class="alert alert-danger">Please pick a date.</div>';
                    } else {

                        $exactEsc = $dblink->real_escape_string($exact);

                        // parse date from filename (YYYYMMDD_HH_MM_SS)
                        $sql = "
                            SELECT 
                                d.document_id,
                                d.file_name,
                                d.loan_id,
                                d.file_size,
                                d.last_access,
                                d.upload_date,
                                t.type_name,
                                STR_TO_DATE(
                                    SUBSTRING_INDEX(SUBSTRING_INDEX(d.file_name, '-', -1), '.', 1),
                                    '%Y%m%d_%H_%i_%s'
                                ) AS doc_date
                            FROM Documents d
                            LEFT JOIN DocumentTypes t ON d.document_type_id = t.document_type_id
                            WHERE DATE(
                                STR_TO_DATE(
                                    SUBSTRING_INDEX(SUBSTRING_INDEX(d.file_name, '-', -1), '.', 1),
                                    '%Y%m%d_%H_%i_%s'
                                )
                            ) = '$exactEsc' 
                            ORDER BY doc_date, d.loan_id, d.file_name
                        ";

                        $searchResults = $dblink->query($sql) or die("SQL error: " . $dblink->error);

                        echo '<hr><div class="alert alert-info">Showing documents for '.$exactEsc.'.</div>';
						
						// 
                        if($searchResults->num_rows < 1){ // no docs found error
                            echo '<div class="alert alert-warning">No documents found for that date.</div>';
                        } else { // else display doc info table
                            echo '<table class="table table-striped">';
                            echo '<thead><tr>
                                    <th>Document Date</th>
                                    <th>Loan ID</th>
                                    <th>File Name</th>
                                    <th>File Size</th>
                                    <th>Last Access</th>
                                    <th>Document Type</th>
                                    <th>View</th>
                                  </tr></thead><tbody>';
							// display search results table
                            while($row = $searchResults->fetch_assoc()){
                                echo '<tr>';
                                echo '<td>'.$row['doc_date'].'</td>';
                                echo '<td>'.$row['loan_id'].'</td>';
                                echo '<td>'.$row['file_name'].'</td>';
                                echo '<td>'.($row['file_size'] ? $row['file_size'].' bytes' : 'N/A').'</td>';
                                echo '<td>'.($row['last_access'] ? $row['last_access'] : 'N/A').'</td>';
                                echo '<td>'.$row['type_name'].'</td>';
                                echo '<td><a href="search_view.php?fid='.$row['document_id'].'&auth=1">View</a></td>';
                                echo '</tr>';
                       }

                            echo '</tbody></table>';
                       }
                    }

                    // stop before range search runs
                    return;
                }

				// SEARCH DATE RANGE
                if(isset($_POST['submit']) && $_POST['submit']=="range"){

                    $start = isset($_POST['startDate']) ? $_POST['startDate'] : "";
                    $end   = isset($_POST['endDate']) ? $_POST['endDate'] : "";

                    if($start === "" && $end === ""){
                        echo '<div class="alert alert-danger">Please enter at least one date.</div>';
                    } else {

                        // parse date from filename (YYYYMMDD_HH_MM_SS)
                        $sql = "
                            SELECT
                                d.document_id,
                                d.file_name,
                                d.loan_id,
                                d.file_size,
                                d.last_access,
                                d.upload_date,
                                t.type_name,
                                STR_TO_DATE(
                                    SUBSTRING_INDEX(SUBSTRING_INDEX(d.file_name, '-', -1), '.', 1),
                                    '%Y%m%d_%H_%i_%s'
                                ) AS doc_date
                            FROM Documents d
                            LEFT JOIN DocumentTypes t 
                                ON d.document_type_id = t.document_type_id
                            WHERE 1
                        ";

                        // add start filter
                        if($start !== ""){
                            $startEsc = $dblink->real_escape_string($start);
                            $sql .= "
                                AND STR_TO_DATE(
                                  SUBSTRING_INDEX(SUBSTRING_INDEX(d.file_name, '-', -1), '.', 1),
                                  '%Y%m%d_%H_%i_%s'
                                ) >= '$startEsc'
                            ";
                        }

                        // add end filter
                        if($end !== ""){
                            $endEsc = $dblink->real_escape_string($end);
                            $sql .= "
                                AND STR_TO_DATE(
                                  SUBSTRING_INDEX(SUBSTRING_INDEX(d.file_name, '-', -1), '.', 1),
                                  '%Y%m%d_%H_%i_%s'
                                ) <= '$endEsc'
                            ";
                        }

						// display docs by date
                        $sql .= " ORDER BY doc_date, d.loan_id, d.file_name";

                        $searchResults = $dblink->query($sql) or die("SQL error: " . $dblink->error);

                        echo '<hr>';
						
						// no files found 
                        if($searchResults->num_rows == 0){
                            echo '<div class="alert alert-warning">No files found for that date range.</div>';
                        } else { // display search results and parameters

                            echo '<table class="table table-striped">';
                            echo '<thead><tr>
                                    <th>Document Date</th>
                                    <th>Loan ID</th>
                                    <th>File Name</th>
                                    <th>File Size</th>
                                    <th>Last Access</th>
                                    <th>Document Type</th>
                                    <th>View</th>
                                  </tr></thead><tbody>';

                            while($row = $searchResults->fetch_assoc()){
                                echo '<tr>';
                                echo '<td>'.$row['doc_date'].'</td>';
                                echo '<td>'.$row['loan_id'].'</td>';
                                echo '<td>'.$row['file_name'].'</td>';
                                echo '<td>'.($row['file_size'] ? $row['file_size'].' bytes' : 'N/A').'</td>';
                                echo '<td>'.($row['last_access'] ? $row['last_access'] : 'N/A').'</td>';
                                echo '<td>'.$row['type_name'].'</td>';
                                echo '<td><a href="search_view.php?fid='.$row['document_id'].'&auth=1">View</a></td>';
                                echo '</tr>';
                            }

                            echo '</tbody></table>';
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
