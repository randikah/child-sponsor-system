<?php
ob_start(); 
session_start();
require_once 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_input = trim($_POST['username']); 
    $password = trim($_POST['password']);

    if (!empty($login_input) && !empty($password)) {
        // Find user by username OR email address
        $stmt = $conn->prepare("SELECT id, username, email, password, role, password_changed FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify the hashed password
            if (password_verify($password, $user['password'])) {
                // Set core authentication session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['sponsor_id'] = ''; 

                // Resolve relational sponsor profile ID if the logging-in user is a Sponsor
                if ($user['role'] === 'Sponsor') {
                    $sp_check = $conn->prepare("SELECT id FROM sponsors WHERE user_id = ?");
                    $sp_check->bind_param("i", $user['id']);
                    $sp_check->execute();
                    $sp_res = $sp_check->get_result();
                    
                    if ($sp_row = $sp_res->fetch_assoc()) {
                        $_SESSION['sponsor_id'] = $sp_row['id']; 
                    }
                    $sp_check->close();
                }

                // --- UNIVERSAL FIRST-TIME LOGIN ENFORCEMENT ---
                // This now applies to ALL users (Sponsors, Coordinators, Admins, etc.)
                if ((int)$user['password_changed'] === 0) {
                    $_SESSION['require_password_change'] = true;
                    header("Location: change_password.php");
                    exit();
                }

                // SECURE DASHBOARD ROUTING (Only reached if password has already been changed)
                if ($user['role'] === 'Admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] === 'Coordinator') {
                    header("Location: coordinator_dashboard.php");
                } elseif ($user['role'] === 'Sponsor') {
                    header("Location: sponsor_dashboard.php");
                } else {
                    $error = "Access Denied: Unrecognized system access classification.";
                }
                
                if (empty($error)) {
                    exit();
                }
            } else {
                $error = "Invalid identity credentials or password.";
            }
        } else {
            $error = "Invalid identity credentials or password.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all identity and access fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Child & Sponsor Management System</title>
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
        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            color: #0f766e;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input:focus {
            border-color: #0f766e;
            outline: none;
            box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.15);
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #0f766e;
            border: none;
            color: white;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: ease-in-out 0.2s;
        }
        button:hover {
            background-color: #0d5c55;
        }
        .error-msg {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2>System Gateway Login</h2>
    
    <?php if (!empty($error)): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit">Sign In</button>
    </form>
</div>

</body>
</html>
<?php
ob_end_flush(); // Flushes and sends everything cleanly to the browser
?>