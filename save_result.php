<?php
session_start();
$user_id=$_SESSION['user_id'];
// Connect to MySQL
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'emotion_db';
// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the dominant emotion is set in POST request
if (isset($_POST['dominant_emotion'])) {
    $dominantEmotion = $conn->real_escape_string($_POST['dominant_emotion']);
    
    // Prepare SQL query to insert emotion into the database
    $sql = "INSERT INTO result (user_id,result_emotion) VALUES ('$user_id','$dominantEmotion')";
    
    if ($conn->query($sql) === TRUE) {
        echo "Emotion successfully uploaded to the database!";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
} else {
    echo "No emotion data received.";
}

// Close the connection
$conn->close();
?>
