<?php
session_start();
require_once 'db_connect.php';

// Access Control: Only Admins allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$message = '';

// Handle the reset action
if (isset($_GET['user_id'])) {
    $target_user_id = intval($_GET['user_id']);
    
    // 1. Hash the default password "test123"
    $new_default_password = password_hash('test123', PASSWORD_BCRYPT);
    
    // 2. Update password to default AND set password_changed to 0
    $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed = 0 WHERE id = ?");
    $stmt->bind_param("si", $new_default_password, $target_user_id);
    
    if ($stmt->execute()) {
        $message = "✓ User password reset to 'test123'. They will be prompted to change it on next login.";
    } else {
        $message = "❌ Error resetting user.";
    }
    $stmt->close();
}

// Fetch all users
$users = $conn->query("SELECT id, username, role FROM users");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset User Password</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f6f9; display: flex; }
        .sidebar { width: 250px; background-color: #2c3e50; color: white; min-height: 100vh; padding: 20px; }
        .sidebar a { display: block; color: #adb5bd; padding: 12px; text-decoration: none; border-radius: 4px; margin-bottom: 4px; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: white; }
        .main-content { flex-grow: 1; padding: 30px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .history-table th, .history-table td { padding: 12px; border-bottom: 1px solid #edf2f7; text-align: left; }
        .btn-reset { background: #dc2626; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Sponsorship Admin</h2>
    <a href="admin_dashboard.php">📊 Admin Dashboard</a>
    <a href="admin_reset_password.php" class="active">🔑 Reset User Password</a>
</div>

<div class="main-content">
    <div class="card">
        <h3>Force Password Reset</h3>
        <p>Use this tool to reset a user's password to <strong>test123</strong>. They will be forced to change it upon their next successful login.</p>
        
        <?php if($message) echo "<p style='padding:10px; background:#d4edda; color:#155724;'>$message</p>"; ?>
        
        <table class="history-table">
            <thead><tr><th>Username</th><th>Role</th><th>Action</th></tr></thead>
            <tbody>
                <?php while($row = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['role']); ?></td>
                    <td>
                        <a href="admin_reset_password.php?user_id=<?php echo $row['id']; ?>" 
                           onclick="return confirm('Are you sure? This will set their password to test123.');"
                           class="btn-reset">
                           Reset Password to test123
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>