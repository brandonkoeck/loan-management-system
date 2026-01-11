<?php
$env = parse_ini_file(__DIR__ . '/../.env');
if ($env === false) {
    die("ERROR: Could not load .env file");
}
session_start();

// make sure page was opened properly from "View" button
if(!isset($_GET['fid']) || !isset($_GET['auth'])){
    die("Unauthorized access.");
}

// save the ID so view_file know which PDF is allowed
$_SESSION['allowed_pdf'] = intval($_GET['fid']);

$dblink = new mysqli(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASSWORD'],
    $env['DB_NAME']
);

if($dblink->connect_errno){
    die("DB connection failed: " . $dblink->connect_error);
}

$fid = intval($_GET['fid']);

// pull document info
$sql = "
    SELECT d.document_id, d.file_name, d.loan_id, d.file_size, d.last_access,
           t.type_name, d.upload_date
    FROM Documents d
    LEFT JOIN DocumentTypes t ON d.document_type_id = t.document_type_id
    WHERE d.document_id = '$fid'
";

$result = $dblink->query($sql) or die("SQL error: " . $dblink->error);

if($result->num_rows < 1){
    die("Document not found.");
}

$data = $result->fetch_assoc();
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
            <div class="panel-heading">
                View File: <?php echo htmlspecialchars($data['file_name']); ?>
            </div>

            <div class="panel-body">

                <!-- table for file info (echo from dtabase) -->
                <h3>File Info:</h3>

                <table class="table table-bordered">
                    <tr><th>Loan ID</th>
                        <td><?php echo htmlspecialchars($data['loan_id']); ?></td></tr>

                    <tr><th>File Name</th>
                        <td><?php echo htmlspecialchars($data['file_name']); ?></td></tr>

                    <tr><th>File Size</th>
                        <td><?php echo isset($data['file_size']) ? $data['file_size']." bytes" : "N/A"; ?></td></tr>

                    <tr><th>Document Type</th>
                        <td><?php echo htmlspecialchars($data['type_name']); ?></td></tr>

                    <tr><th>Upload Date</th>
                        <td><?php echo htmlspecialchars($data['upload_date']); ?></td></tr>

                    <tr><th>Last Access</th>
                        <td><?php echo isset($data['last_access']) ? $data['last_access'] : "N/A"; ?></td></tr>

                    <tr><th>View Document</th>
                        <td>
                            <!-- open pdf in browser with proper id -->
                            <a href="view_file.php?fid=<?php echo $data['document_id']; ?>" target="_blank">
                                Open in PDF Viewer
                            </a>
                      </td>
                    </tr>
                </table>

                <a href="search_main.php" class="btn btn-default">Back to Search Menu</a>
            </div>
     
	</div>
    </div>
</div>

</body>
</html>