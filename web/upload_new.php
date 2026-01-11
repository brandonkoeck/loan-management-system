<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Document Management Web Front End</title>
<link href="assets/css/bootstrap.css" rel="stylesheet">
<link href="assets/css/bootstrap-fileupload.min.css" rel="stylesheet">
<script src="assets/js/jquery-1.10.2.js"></script>
<script src="assets/js/bootstrap.js"></script>
<script src="assets/js/bootstrap-fileupload.js"></script>
<style>
.main-box {
    text-align:center;
    padding:20px;
    border-radius:5px;
    -moz-border-radius:5px ;
    -webkit-border-radius:5px;
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
        <div class="panel-heading">Upload New Loan ID</div>
        <div class="panel-body">
            <h3>Please fill out the form below.</h3>
            <hr>

            <?php
			$env = parse_ini_file(__DIR__ . '/../.env');
			if ($env === false) {
				die("ERROR: Could not load .env file");
			}
			// show errors
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);

            // connect to database and get document types dynamically
            $dblink = new mysqli(
				$env['DB_HOST'],
				$env['DB_USER'],
				$env['DB_PASSWORD'],
				$env['DB_NAME']
			);
            $docTypes = $dblink->query("SELECT type_name FROM DocumentTypes ORDER BY type_name ASC");
            ?>

            <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="MAX_FILE_SIZE" value="5000000">
                <div class="form-group">
                    <label class="control-label">Loan Number:</label>
                    <input class="form-control" name="loanId" type="text">
                    <span class="help-block">Loan number must be digits only (up to 9)</span>
                </div>
				
		        <div class="form-group">
			        <label class="control-label">Document Type:</label>
			        <select name="docType" class="form-control">
				        <option value=""></option>
				        <?php
						// fill dropdown with docmnuent types from DocumentTypes  table
				        if ($docTypes->num_rows > 0) {
					        while ($row = $docTypes->fetch_assoc()) {
						        echo '<option value="' . htmlspecialchars($row['type_name']) . '">' . htmlspecialchars($row['type_name']) . '</option>';
					        }
				        }
				        ?>
			        </select>
		        </div>

                <div class="form-group">
                    <label class="control-label">File Upload</label>
                    <div class="">
                        <div class="fileupload fileupload-new" data-provides="fileupload">
                            <div class="fileupload-preview thumbnail" style="width:200px; height:150px"></div>
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="btn btn-file btn-primary">
                                        <span class="fileupload-new">Select File</span>
                                        <span class="fileupload-exists">Change</span>
                                        <input name="userfile" type="file" accept="application/pdf">
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <a href="#" class="btn btn-danger fileupload-exists" data-dismiss="fileupload">Remove</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="form-group">
                    <button type="submit" name="submit" value="submit" class="btn btn-lg btn-block btn-success">Upload File</button>
                </div>
            </form>

            <?php
            if (isset($_POST['submit']) && $_POST['submit']=="submit") {
                $loanId = trim($_POST['loanId']);
                $docType = trim($_POST['docType']);
                $file = $_FILES['userfile'];

				// loan number validation
                if (empty($loanId)) {
                    echo '<div class="alert alert-danger">Loan number cannot be empty.</div>';
                } elseif (!ctype_digit($loanId)) {
                    echo '<div class="alert alert-danger">Loan number must contain digits only.</div>';
                } elseif (strlen($loanId) > 9) {
                    echo '<div class="alert alert-danger">Loan number cannot exceed 9 digits.</div>';
				// document type validation
                } elseif (empty($docType)) {
                    echo '<div class="alert alert-danger">Please select a document type.</div>';
                } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                    echo '<div class="alert alert-danger">File upload failed. Please try again.</div>';
                } else {
                    // check if PDF
                    $fileType = mime_content_type($file['tmp_name']);
                    if ($fileType !== 'application/pdf') {
						// error if file not PDF
                        echo '<div class="alert alert-danger">Invalid file type. Only PDF files are allowed.</div>';
                    } else {
                        // read file content
                        $fileName = $file['name'];
                        $fileSize = $file['size'];
                        $fp = fopen($file['tmp_name'], "r");
                        $fileContent = addslashes(fread($fp, filesize($file['tmp_name'])));
                        fclose($fp);

                        // check if loan number exists, or make new one if valid
                        $loanCheckSql = "SELECT loan_id FROM Loans WHERE loan_id = '$loanId'";
                        $loanCheckResult = $dblink->query($loanCheckSql);

                        if ($loanCheckResult->num_rows == 0) {
                            $createLoanSql = "INSERT INTO Loans (loan_id) VALUES ('$loanId')";
                            $dblink->query($createLoanSql) or
                                die("<h2>Could not create loan: " . $dblink->error . '</h2>');
                        }

                        // get the document type id
                        $typeQuery = "SELECT document_type_id FROM DocumentTypes WHERE LOWER(type_name) = LOWER('$docType')";
                        $typeResult = $dblink->query($typeQuery);

                        if ($typeResult && $typeResult->num_rows > 0) {
                            $typeRow = $typeResult->fetch_assoc();
                            $documentTypeId = (int) $typeRow['document_type_id'];

                            $uploadDate = date("Ymd_H_i_s");
                            $docName = "$loanId-$docType-$uploadDate.pdf";

                            // insert document into database
                            $sql = "INSERT INTO Documents (loan_id, file_name, content, document_type_id, file_size)
                                    VALUES ('$loanId', '$docName', '$fileContent', '$documentTypeId', '$fileSize')";
                            $dblink->query($sql) or
                                die("<h2>Something went wrong with:<br>$sql<br>" . $dblink->error . '</h2>');

                            echo '<div class="alert alert-success">File successfully uploaded!</div>';
                        } else {
                            echo '<div class="alert alert-danger">Invalid document type selected. Please try again.</div>';
                        }
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
