<?php

session_start();

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;


if ($user_id === 0) {
   
    if (!isset($_SESSION['temp_user_id'])) {
        $_SESSION['temp_user_id'] = uniqid('user_', true);
    }
    $user_id = $_SESSION['temp_user_id'];
}

$conn = new mysqli("localhost", "root", "", "emotion_db");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}


$q_id = isset($_POST['q_id']) ? intval($_POST['q_id']) : 0;
$dominant_emotion = isset($_POST['dominant_emotion']) ? $conn->real_escape_string($_POST['dominant_emotion']) : '';

// Validate input
if ($q_id <= 0 || empty($dominant_emotion)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Check if a record already exists for this user and question
$check_sql = "SELECT id FROM user_emotions WHERE user_id = ? AND q_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("si", $user_id, $q_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update existing record
    $row = $check_result->fetch_assoc();
    $update_sql = "UPDATE user_emotions SET dominant_emotion = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $dominant_emotion, $row['id']);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Emotion data updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update emotion data: ' . $update_stmt->error]);
    }
    
    $update_stmt->close();
} else {
    // Insert new record
    $insert_sql = "INSERT INTO user_emotions (user_id, q_id, dominant_emotion, created_at, updated_at) 
                  VALUES (?, ?, ?, NOW(), NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sis", $user_id, $q_id, $dominant_emotion);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Emotion data stored successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to store emotion data: ' . $insert_stmt->error]);
    }
    
    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();
?>