<?php
session_start();

if (isset($_POST['action']) && $_POST['action'] == 'logout') {
    // Unset all of the session variables
    $_SESSION = array();

    // Destroy the session.
    session_destroy();

    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Redirect to login page if not logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

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
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_SESSION['role'] === 'A' || $_SESSION['role'] === 'C')) {
    // Check if files were uploaded
if (isset($_FILES["image"])) {
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
} else {
    echo "No images were uploaded.";
}
}

// Check if the server request method is GET and view_images or download is set
if ($_SERVER["REQUEST_METHOD"] == "GET" && (isset($_GET['view_images']) || isset($_GET['download'])) && ($_SESSION['role'] === 'B' || $_SESSION['role'] === 'C')) {
    // Your existing code for viewing and downloading images goes here...
    // Query to retrieve encrypted image data from the selected folder table
    $selectedFolder = sanitize_folder($_GET['folder']);
    $sql = "SELECT id, images FROM $selectedFolder";
    $result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Output the images
    while ($row = $result->fetch_assoc()) {
        $imageId = $row["id"];
        $encryptedImageData = $row["images"];

        // Decrypt the image data
        $decryptedImageData = xor_encrypt_decrypt($encryptedImageData, $encryptionKey);

        // Convert to base64 for displaying as an image
        $base64Image = base64_encode($decryptedImageData);
        echo "<div class='image-item'>";
        echo "<h2>Image $imageId</h2>";
        echo "<img src='data:image/jpeg;base64,$base64Image' alt='Image $imageId'>";
        echo "</div>";
    }
} else {
    echo "No images found in $selectedFolder.";
}

if (isset($_GET['download']) && $_GET['download'] == download) {
    // Create a temporary directory for storing images
    $tempDir = sys_get_temp_dir() . '/' . uniqid('images_') . '/';
    if (!mkdir($tempDir) && !is_dir($tempDir)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
    }

    // Fetch and decrypt images
    while ($row = $result->fetch_assoc()) {
        $imageId = $row["id"];
        $encryptedImageData = $row["images"];
        $decryptedImageData = xor_encrypt_decrypt($encryptedImageData, $encryptionKey);

        // Save the decrypted image to the temporary directory
        $imageFileName = $tempDir . 'image_' . $imageId . '.jpg';
        file_put_contents($imageFileName, $decryptedImageData);
    }

    // Create a ZIP file containing all images
    $zipFileName = sys_get_temp_dir() . '/' . uniqid('images_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir));
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($tempDir));
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();

        // Send the ZIP file for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipFileName) . '"');
        header('Content-Length: ' . filesize($zipFileName));
        readfile($zipFileName);

        // Clean up temporary files and directory
        array_map('unlink', glob("$tempDir*.*"));
        rmdir($tempDir);
        unlink($zipFileName);
        exit();
    } else {
        echo "Failed to create the ZIP file.";
    }
}
}
// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Image Upload and Viewer</title>
</head>
<body>
    <?php if ($_SESSION['role'] === 'A' || $_SESSION['role'] === 'C'): ?>
        <!-- HTML form for image upload -->
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

    <?php if ($_SESSION['role'] === 'B' || $_SESSION['role'] === 'C'): ?>
        <!-- HTML form for image viewing -->
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
    <?php endif; ?>

    <!-- Feedback area for displaying messages -->
    <div id="upload-feedback">
        <?php
        if (isset($_GET['message'])) {
            echo '<p>' . htmlspecialchars($_GET['message']) . '</p>';
        }
        ?>

            <!-- Logout Form -->
<form method="post">
    <input type="hidden" name="action" value="logout">
    <input type="submit" value="Logout">
</form>
        
    </div>
</body>
</html>
