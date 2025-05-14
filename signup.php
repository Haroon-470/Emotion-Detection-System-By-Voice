<?php
session_start();
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error'] = "All fields are required!";
        header("Location: loginsignup.html");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: loginsignup.html");
        exit();
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Email already exists!";
        header("Location: loginsignup.html");
        exit();
    }
    

    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $password);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful! Please login.";
        header("Location: loginsignup.html");
    } else {
        die("Error: " . $stmt->error); // Show actual error
        $_SESSION['error'] = "Registration failed! Please try again.";
        header("Location: loginsignup.html");
    }    
    
    $stmt->close();
    $conn->close();
}
?>