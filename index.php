<?php
session_start(); // Start the session at the beginning

// Function to sanitize the folder name input
function sanitize_folder($folder) {
    return filter_var($folder, FILTER_SANITIZE_STRING);
}

// XOR encryption/decryption function
function xor_encrypt_decrypt($data, $key) {
    $keyLength = strlen($key);
    $output = '';

    for ($i = 0; $i < strlen($data); $i++) {
        $output .= $data[$i] ^ $key[$i % $keyLength];
    }

    return $output;
}

// Logout mechanism
if (isset($_POST['action']) && $_POST['action'] == 'logout') {
    // Unset all of the session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Access control for upload form (User A and C)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'A' && $_SESSION['role'] !== 'C')) {
    // If the user is not A or C, do not allow them to upload
    $uploadPermission = false;
} else {
    $uploadPermission = true;
}

// Access control for view and download (User B and C)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'B' && $_SESSION['role'] !== 'C')) {
    // If the user is not B or C, do not allow them to view or download
    $viewDownloadPermission = false;
} else {
    $viewDownloadPermission = true;
}

// Database configuration
$dbHost = 'localhost';
$dbUser = 'afnan';
$dbPass = 'john_wick_77';
$dbName = 'mywebsite_images';
$encryptionKey = '123'; // Replace with your actual key

// Create a database connection
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the server request method is POST for file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["image"]) && $uploadPermission) {
    $uploadedFiles = $_FILES["image"];
    $folder = sanitize_folder($_POST["folder"]); // Sanitize the folder input

    // Loop through the uploaded files
    foreach ($uploadedFiles["error"] as $key => $error) {
        // Check for file upload errors
        if ($error == UPLOAD_ERR_OK) {
            // Get the image data
            $imageData = file_get_contents($uploadedFiles["tmp_name"][$key]);

            // Encrypt the image data
            $encryptedImageData = xor_encrypt_decrypt($imageData, $encryptionKey);

            // Prepare and execute the database insertion
            $stmt = $conn->prepare("INSERT INTO $folder (images) VALUES (?)");
            $null = NULL; // This is needed to bind the blob data
            $stmt->bind_param("b", $null);
            $stmt->send_long_data(0, $encryptedImageData);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                echo "Image uploaded successfully!";
            } else {
                echo "Failed to upload the image.";
            }

            // Close the statement
            $stmt->close();
        } else {
            echo "File upload error: " . $error;
        }
    }
}

// Check if the server request method is GET for image download
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['download']) && $viewDownloadPermission && $_GET['download'] == 'download' && isset($_GET['folder'])) {
    $selectedFolder = sanitize_folder($_GET['folder']);
    $sql = "SELECT id, images FROM $selectedFolder";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Logic for creating and sending ZIP file of images goes here...
        // Refer to the previous snippet for the detailed ZIP creation and download logic.
    } else {
        echo "No images found in $selectedFolder.";
    }
}

// Always close the database connection at the end
$conn->close();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Image Upload and Viewer</title>
</head>
<body>
    <?php if ($uploadPermission): ?>
    <!-- HTML form for image upload (shown only to User A and C) -->
    <h1>Upload Images</h1>
    <form action="index.php" method="POST" enctype="multipart/form-data">
        <label for="image">Choose image(s) to upload:</label>
        <input type="file" name="image[]" id="image" accept="image/*" multiple>
        <br>
        <label for="folder">Select a folder:</label>
        <select name="folder" id="folder">
            <option value="Case001">Case001</option>
            <option value="Case002">Case002</option>
            <option value="Case003">Case003</option>
        </select>
        <br>
        <input type="submit" value="Upload">
    </form>
    <?php endif; ?>

    <?php if ($viewDownloadPermission): ?>
    <!-- HTML form for image viewing and download (shown only to User B and C) -->
    <h1>View Images</h1>
    <form action="index.php" method="GET">
        <label for="view_folder">Select a folder to view images:</label>
        <select name="folder" id="view_folder">
            <option value="Case001">Case001</option>
            <option value="Case002">Case002</option>
            <option value="Case003">Case003</option>
        </select>
        <input type="submit" name="view_images" value="View Images">
        <input type="submit" name="download" value="download" class="download-link" id="download_zip" />
    </form>
    <?php endif; ?>

    <!-- Feedback area for displaying messages -->
    <div id="upload-feedback">
        <?php
        if (isset($_GET['message'])) {
            echo '<p>' . htmlspecialchars($_GET['message']) . '</p>';
        }
        ?>
    </div>

    <!-- Logout Form -->
    <form method="post">
        <input type="hidden" name="action" value="logout">
        <input type="submit" value="Logout">
    </form>

</body>
</html>
