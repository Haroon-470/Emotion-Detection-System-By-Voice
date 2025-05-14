<?php
session_start();

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "emotion_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Input validation
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Both email and password are required!";
        header("Location: loginsignup.html");
        exit();
    }

    // Fetch user from database
    $stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // User found
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Check password (assumes password is hashed)
        if ($password==$user['password']) {
            $_SESSION['user_id']=$user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['loggedin'] = true;

            // Redirect to the voice emotion recognition page
            header("Location: voice.php");
            exit();
        } else {
            $_SESSION['error'] = "Incorrect password!";
        }
    } else {
        $_SESSION['error'] = "Email not found!";
    }

    // Redirect back to login page on failure
    header("Location: loginsignup.html");
    exit();
}
?>
