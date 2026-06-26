<?php
session_start();
require_once 'db_connect.php';

// Access Control Security Guard: Strictly Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Fetch analytics counts
$user_count = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$child_count = $conn->query("SELECT COUNT(*) as total FROM child")->fetch_assoc()['total'];
$active_sponsorships_count = $conn->query("SELECT COUNT(*) as total FROM child_sponsor_matches WHERE match_status = 'Active'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f6f9; display: flex; }
        .sidebar { width: 250px; background-color: #2c3e50; color: white; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .sidebar h2 { text-align: center; font-size: 20px; margin-bottom: 30px; }
        .sidebar a { display: block; color: #adb5bd; padding: 12px; text-decoration: none; border-radius: 4px; margin-bottom: 4px; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: white; }
        .sidebar .role-nav-group { margin-top: 25px; border-top: 1px solid #4f5d73; padding-top: 15px; }
        .sidebar .role-nav-group-title { font-size: 11px; text-transform: uppercase; color: #7f8c8d; padding-left: 12px; font-weight: bold; margin-bottom: 5px; }
        .main-content { flex-grow: 1; padding: 30px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .cards-container { display: flex; gap: 20px; margin-top: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); width: 200px; text-align: center; }
        .card h3 { margin: 0; color: #7f8c8d; font-size: 14px; }
        .card p { font-size: 32px; font-weight: bold; margin: 10px 0 0 0; color: #2c3e50; }
        .btn-logout { background-color: #e74c3c; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Sponsorship Admin</h2>
    <a href="admin_dashboard.php" class="active">📊 Dashboard Analytics</a>
    <a href="register_user.php">👤 System User Registration</a>
    
    <div class="role-nav-group">
        <div class="role-nav-group-title">Navigate Dashboards</div>
        <a href="coordinator_dashboard.php" style="color: #93c5fd;">➡️ Coordinator Panel</a>
        <a href="admin_reset_password.php" style="color: #a7f3d0;">🔑 Reset User Password</a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Administrator)</h1>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>

    <h3>System Overview & Live Analytics</h3>
    <div class="cards-container">
        <div class="card">
            <h3>Total System Users</h3>
            <p><?php echo $user_count; ?></p>
        </div>
        <div class="card">
            <h3>Registered Children</h3>
            <p><?php echo $child_count; ?></p>
        </div>
        <div class="card">
            <h3>Active Sponsorships</h3>
            <p><?php echo $active_sponsorships_count; ?></p>
        </div>
    </div>
</div>

</body>
</html>