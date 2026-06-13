<?php
require_once 'db_connect.php';

// Wipe the table clean
//$conn->query("TRUNCATE TABLE users");

// Wipe the table clean with temporary constraint bypass flags
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE users");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

$username = 'admin';
$email = 'admin@example.com';
$plain_password = 'password123';
$role = 'Admin';

// Generate a perfect, secure hash using PHP's native library
$hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

// Insert the clean user profile
$stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

if ($stmt->execute()) {
    echo "<h2>✓ Fresh Test Account Created Successfully!</h2>";
    echo "<p><strong>Username:</strong> " . $username . "</p>";
    echo "<p><strong>Password:</strong> " . $plain_password . "</p>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
} else {
    echo "Error creating account: " . $stmt->error;
}

$stmt->close();
?>