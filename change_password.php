<?php
ob_start();
session_start();
require_once 'db_connect.php';

// Verification Interceptor: Make sure the user is genuinely logged in and flagged for a password modification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['require_password_change'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $error = "❌ Passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "❌ Security requirement: Password must be at least 8 characters long.";
        } else {
            // Hash the password securely
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $user_id = $_SESSION['user_id'];

            // Update the users table: change the password and mark password_changed as 1 (True)
            $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed = 1 WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);

            if ($stmt->execute()) {
                // Clear the temporary first-time restriction flag out of the active session state
                unset($_SESSION['require_password_change']);
                
                $success = "✓ Password successfully updated! Redirecting to your workspace dashboard...";
                
                // DYNAMIC ROUTING: Determine the correct dashboard target based on their system role
                $redirect_page = 'login.php'; // Default fallback
                if (isset($_SESSION['role'])) {
                    if ($_SESSION['role'] === 'Admin') {
                        $redirect_page = 'admin_dashboard.php';
                    } elseif ($_SESSION['role'] === 'Coordinator') {
                        $redirect_page = 'coordinator_dashboard.php';
                    } elseif ($_SESSION['role'] === 'Sponsor') {
                        $redirect_page = 'sponsor_dashboard.php';
                    }elseif ($_SESSION['role'] === 'Child') {
                        $redirect_page = 'child_dashboard.php';
                    }
                }

                // Redirect the user automatically after 2 seconds
                header("refresh:2; url=" . $redirect_page);
            } else {
                $error = "❌ Critical System Error: Failed to save changes to the database.";
            }
            $stmt->close();
        }
    } else {
        $error = "❌ Please complete all fields correctly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initial Security Setup - Update Password</title>
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
        .box-container { 
            background-color: #ffffff; 
            padding: 40px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
            width: 100%; 
            max-width: 420px; 
        }
        h3 { 
            text-align: center; 
            color: #b45309; 
            margin-top: 0;
            margin-bottom: 10px; 
        }
        p.notice { 
            font-size: 13px; 
            color: #666; 
            text-align: center; 
            margin-bottom: 24px; 
            line-height: 1.5; 
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 6px; 
            color: #444; 
            font-size: 14px; 
            font-weight: 600; 
        }
        input { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            box-sizing: border-box; 
            font-size: 15px; 
        }
        input:focus {
            border-color: #b45309;
            outline: none;
            box-shadow: 0 0 0 2px rgba(180, 83, 9, 0.15);
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background-color: #d97706; 
            border: none; 
            color: white; 
            font-size: 16px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold; 
            transition: background-color 0.2s ease-in-out;
        }
        button:hover { 
            background-color: #b45309; 
        }
        .msg-alert { 
            padding: 12px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-size: 14px; 
            font-weight: 500; 
        }
        .err { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        .succ { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
    </style>
</head>
<body>

<div class="box-container">
    <h3>First-Time Security Configuration</h3>
    <p class="notice">To protect system profile integrity, all users must reconfigure their temporary account credentials before accessing system tools.</p>
    
    <?php if (!empty($error)): ?>
        <div class="msg-alert err"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="msg-alert succ"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (empty($success)): ?>
        <form action="change_password.php" method="POST">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <button type="submit">Update Password & Proceed</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
<?php
ob_end_flush();
?>