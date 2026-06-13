<?php
$host = 'localhost';
$db_user = 'root';
$db_pass = ''; // Your MySQL password
$db_name = 'child_sponsor_db';

// Create connection
$conn = new mysqli($host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
} else {
    // REMOVE THIS LINE AFTER TESTING: It confirms connection works!
    //echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; text-align: center;'>✓ Database connected successfully!</div>";
}
?>