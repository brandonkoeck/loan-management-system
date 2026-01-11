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
        <div class="panel-heading">Upload to Existing Loan ID</div>
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

// connect to the database
$dblink = new mysqli(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASSWORD'],
    $env['DB_NAME']
);
if ($dblink->connect_error) {
    die("<div class='alert alert-danger'>Connection failed: " . $dblink->connect_error . "</div>");
}

// get existng loans and document types from database
$loanQuery = "SELECT DISTINCT loan_id FROM Loans ORDER BY loan_id ASC";
$loanResult = $dblink->query($loanQuery);

$typeQuery = "SELECT document_type_id, type_name FROM DocumentTypes ORDER BY type_name ASC";
$typeResult = $dblink->query($typeQuery);

// handle form submission
if (isset($_POST['submit']) && $_POST['submit'] == "submit") {
	// error array for combining error checking
    $errors = [];

    // valid loan number selection
    if (empty($_POST['loanId'])) {
        $errors[] = "Please select a loan number.";
    } else {
        $loanId = $_POST['loanId'];
    }

    // validate document type selected
    if (empty($_POST['docType'])) {
        $errors[] = "Please select a document type.";
    } else {
        $docType = $_POST['docType'];
    }

    // make sure a file is uploaded
    if (!isset($_FILES['userfile']) || $_FILES['userfile']['error'] == UPLOAD_ERR_NO_FILE) {
        $errors[] = "Please select a file to upload.";
    } elseif ($_FILES['userfile']['error'] != UPLOAD_ERR_OK) {
        $errors[] = "Error uploading file. Please try again.";
    } else {
        $fileName = $_FILES['userfile']['name'];
        $fileTmp = $_FILES['userfile']['tmp_name'];
        $fileSize = $_FILES['userfile']['size'];

        // confirm file is a PDF
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileMime = mime_content_type($fileTmp);
		
		// give error if mime type is not pdf
        if ($fileExtension !== 'pdf' || $fileMime !== 'application/pdf') {
            $errors[] = "Invalid file type. Only PDF files are allowed.";
        }
    }

    // if there are no errors, upload the document
    if (empty($errors)) {
        $fp = fopen($fileTmp, "r");
        $fileContent = fread($fp, filesize($fileTmp));
        fclose($fp);

        $fileContent = addslashes($fileContent);
        $uploadDate = date("Ymd_H_i_s");

        // get the DocumentType name for naming the file
        $typeNameQuery = "SELECT type_name FROM DocumentTypes WHERE document_type_id = '$docType'";
        $typeNameResult = $dblink->query($typeNameQuery);

        if ($typeNameResult && $typeNameResult->num_rows > 0) {
            $typeNameRow = $typeNameResult->fetch_assoc();
            $docTypeName = $typeNameRow['type_name'];
            $docName = "$loanId-$docTypeName-$uploadDate.pdf";
        } else {
            $docName = "$loanId-Document-$uploadDate.pdf";
        }

        // insert document into database
        $sql = "INSERT INTO Documents (loan_id, file_name, content, document_type_id, file_size)
                VALUES ('$loanId', '$docName', '$fileContent', '$docType', '$fileSize')";

		// success message
        if ($dblink->query($sql)) {
            echo '<div class="alert alert-success"><strong>Success!</strong> File successfully uploaded to loan ' . htmlspecialchars($loanId) . '.</div>';
			// error
        } else {
            echo '<div class="alert alert-danger"><strong>Error!</strong> Something went wrong: ' . $dblink->error . '</div>';
        }
    } else {
        // show all validation errors
        echo '<div class="alert alert-danger"><strong>Please correct the following:</strong><ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
    }
}
?>

            <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="MAX_FILE_SIZE" value="5000000">

                <div class="form-group">
                    <label class="control-label">Select Existing Loan Number:</label>
                    <select name="loanId" class="form-control" required>
                        <option value="">-- Select a Loan --</option>
                        <?php
						// loop thru loans and display in dropdown
                        if ($loanResult && $loanResult->num_rows > 0) {
                            while ($row = $loanResult->fetch_assoc()) {
                                $selected = (isset($_POST['loanId']) && $_POST['loanId'] == $row['loan_id']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($row['loan_id']) . '" ' . $selected . '>' . htmlspecialchars($row['loan_id']) . '</option>';
                            }
                        } else {
							// no loans found from database
                            echo '<option value="" disabled>No existing loans found</option>';
                        }
                        ?>
                    </select>
                    <span class="help-block">Select from pre existing loan IDs.</span>
                </div>
                
                <div class="form-group">
                    <label class="control-label">Document Type:</label>
                    <select name="docType" class="form-control" required>
                        <option value="">-- Select Document Type --</option>
                        <?php
                        if ($typeResult && $typeResult->num_rows > 0) {
                            while ($row = $typeResult->fetch_assoc()) {
                                $selected = (isset($_POST['docType']) && $_POST['docType'] == $row['document_type_id']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($row['document_type_id']) . '" ' . $selected . '>' . htmlspecialchars($row['type_name']) . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>No document types available</option>';
                        }
                        ?>
                    </select>
                    <span class="help-block">Select the type of document being uploaded.</span>
                </div>

                <div class="form-group">
                    <label class="control-label">File Upload (PDF only)</label>
                    <div class="">
                        <div class="fileupload fileupload-new" data-provides="fileupload">
                            <div class="fileupload-preview thumbnail" style="width:200px; height:150px"></div>
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="btn btn-file btn-primary">
                                        <span class="fileupload-new">Select PDF File</span>
                                        <span class="fileupload-exists">Change</span>
                                        <input name="userfile" type="file" accept=".pdf,application/pdf">
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <a href="#" class="btn btn-danger fileupload-exists" data-dismiss="fileupload">Remove</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="help-block">Only PDF files are valid.</span>
                </div>

                <hr>
                <div class="form-group">
                    <button type="submit" name="submit" value="submit" class="btn btn-lg btn-block btn-success">
                        Upload File to Existing Loan
                    </button>
                </div>
            </form>

            <hr>
            <div class="text-center">
                <a href="upload_new.php" class="btn btn-info">Upload to New Loan Instead</a>
            </div>
        </div>
    </div>
        </div>
    </div>
</body>
</html>

<?php
// close db connection
if (isset($dblink)) {
    $dblink->close();
}
?>
