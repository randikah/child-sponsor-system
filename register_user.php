<?php
require_once 'db_connect.php';

$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get data submitted from the web form variables
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $plain_password = trim($_POST['password']);
    $role = $_POST['role']; // Will capture 'Admin', 'Coordinator', or 'Sponsor' [cite: 42]

    // Validate that inputs are not empty
    if (!empty($username) && !empty($email) && !empty($plain_password) && !empty($role)) {
        
        // Hash the plain text password securely
        $hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

        // Prepare the SQL statement to prevent SQL Injection
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

        if ($stmt->execute()) {
            $message = "✓ User Account '$username' created successfully!";
            $message_class = "success-msg";
        } else {
            // Handle duplicate entry errors gracefully
            if ($conn->errno === 1062) {
                $message = "❌ Error: Username or Email already exists.";
            } else {
                $message = "❌ Error saving user: " . $stmt->error;
            }
            $message_class = "error-msg";
        }
        $stmt->close();
    } else {
        $message = "❌ Please fill out all form fields.";
        $message_class = "error-msg";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create System User</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .form-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-size: 14px;
            font-weight: 600;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 15px;
        }
        input:focus, select:focus {
            border-color: #28a745;
            outline: none;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #28a745;
            border: none;
            color: white;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        button:hover {
            background-color: #218838;
        }
        .msg-box {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
        }
        .success-msg {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-msg {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .nav-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        .nav-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Create System User</h2>
    
    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="register_user.php" method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="e.g., john_doe" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="e.g., john@example.com" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter secure password" required>
        </div>

        <div class="form-group">
            <label for="role">System Role Group</label>
            <select id="role" name="role" required>
                <option value="" disabled selected>-- Select a Role --</option>
                <option value="Admin">Admin</option>
                <option value="Coordinator">Coordinator</option>
                <option value="Sponsor">Sponsor</option>
            </select>
        </div>

        <button type="submit">Register Account</button>
    </form>

    <a href="admin_dashboard.php" class="nav-link">← Return to Admin Panel</a>
</div>

</body>
</html>