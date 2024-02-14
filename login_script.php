<?php
session_start();

// Database configuration
$dbHost = 'localhost';
$dbUser = 'afnan';
$dbPass = 'john_wick_77';
$dbName = 'mywebsite_images';

// Create a database connection
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Verify the password 
        if ($password === $row['password']) { 
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $row['role'];
            header("Location: index.php"); // Redirect to your main page
            exit();
        } else {
            echo "Invalid password!";
        }
    } else {
        echo "User not found!";
    }
    $stmt->close();
    $conn->close();
}
?>
