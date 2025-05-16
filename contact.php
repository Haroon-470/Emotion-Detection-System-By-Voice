<?php
// Start session to access user_id
session_start();

// Initialize variables
$message = '';
$success = false;

// Database connection configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "emotion_db";

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = $_POST['name'];
    $email = $_POST['email'];
    $user_message = $_POST['message'];
    
    // Get user_id from session (if available)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    // Create database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        $message = "Database connection failed: " . $conn->connect_error;
    } else {
        // Prepare and bind the SQL statement
        $stmt = $conn->prepare("INSERT INTO user_queries (user_id, name, email, message, submission_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("isss", $user_id, $name, $email, $user_message);
        
        // Execute the statement
        if ($stmt->execute()) {
            $success = true;
            $message = "Thank you for your message. We'll get back to you soon!";
        } else {
            $message = "Error: " . $stmt->error;
        }
        
        // Close statement and connection
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Contact Us - EmoDLive</title>
    <link rel="stylesheet" href="./css/style.css">
    <style>
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .return-home {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            width: 200px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <header>
        <div class="logo">EmoDLive</div>
        <nav>
            <ul>
                <li><a href="home.html">Home</a></li>
                <li><a href="voice.php">Demo</a></li>
                <li><a href="team.html">Team</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
        <a href="#" class="btn get-started">Get Started</a>
    </header>

    <!-- Main Content Section -->
    <section class="contact">
        <h1>Contact Us</h1>
        
        <?php if ($success): ?>
            <!-- Success message after form submission -->
            <div class="success-message">
                <?php echo $message; ?>
            </div>
            <a href="home.html" class="return-home">Return to Home Page</a>
        <?php else: ?>
            <p>If you have any questions or feedback, feel free to reach out to us!</p>
            
            <?php if (!empty($message)): ?>
                <div class="error-message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="contact-container">
                <form class="contact-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div>
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div>
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div>
                        <label for="message">Message:</label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn primary">Send Message</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>