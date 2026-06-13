<?php
session_start();
require_once 'db_connect.php';

// Access Control Security Guard: Allow Coordinator OR Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Coordinator' && $_SESSION['role'] !== 'Admin')) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinator Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f6f9; display: flex; flex-direction: column; }
        .admin-nav { background-color: #343a40; padding: 10px 20px; display: flex; gap: 15px; align-items: center; color: white; font-size: 14px; }
        .admin-nav a { color: #ffc107; text-decoration: none; font-weight: bold; padding: 5px 10px; border: 1px solid #ffc107; border-radius: 4px; }
        .admin-nav a:hover { background-color: #ffc107; color: black; }
        .dashboard-container { display: flex; min-height: calc(100vh - 44px); }
        .sidebar { width: 250px; background-color: #1e3a8a; color: white; padding: 20px; box-sizing: border-box;}
        .sidebar h2 { text-align: center; font-size: 18px; margin-bottom: 30px; border-bottom: 1px solid #3b82f6; padding-bottom: 10px; }
        .sidebar a { display: block; color: #93c5fd; padding: 12px; text-decoration: none; border-radius: 4px; margin-bottom: 5px;}
        .sidebar a:hover, .sidebar a.active { background-color: #1d4ed8; color: white; }
        .main-content { flex-grow: 1; padding: 30px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .action-card { background: white; padding: 20px; border-radius: 8px; border-left: 5px solid #1e3a8a; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .action-card h3 { margin-top: 0; color: #1e3a8a; }
        .btn-action { display: inline-block; background-color: #1e3a8a; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px; margin-top: 10px; font-size: 14px; }
        .btn-logout { background-color: #e74c3c; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>

<?php if ($_SESSION['role'] === 'Admin'): ?>
    <div class="admin-nav">
        <strong>⚡ Admin View Mode:</strong> Go to: 
        <a href="admin_dashboard.php">Admin Panel</a>
        <a href="coordinator_dashboard.php" style="background:#ffc107; color:black;">Coordinator Panel</a>
        <a href="sponsor_dashboard.php">Sponsor Portal</a>
    </div>
<?php endif; ?>

<div class="dashboard-container">
    <div class="sidebar">
        <h2>Coordinator Panel</h2>
        <a href="#" class="active">🏠 Overview</a>
        <a href="register_child.php">👶 Manage Child Profiles</a>
        <a href="update_child.php" class="btn-action">✏️ Update Child Profile Details</a>
        <a href="register_sponsor.php">#️⃣ Manage Sponsors</a>
        <a href="#">✉️ Correspondence</a>
 
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Coordinator Management Workspace</h1>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
        
        <p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (<?php echo $_SESSION['role']; ?>)</p>

        <h2>Quick Actions Workspace</h2>
        <div class="action-grid">
            <div class="action-card">
                <h3>Child Records</h3>
                <p>Register new child profiles into the centralized system, update documentation, and track ongoing progress metrics.</p>
                <a href="register_child.php" class="btn-action">+ Register Child</a>
            </div>
            <div class="action-card">
                <h3>Sponsor Matches</h3>
                <p>Link  sponsorship and  active assignments.</p>
                <a href="match_sponsor.php" class="btn-action">Link Sponsor to Child</a>
            </div>
            <div class="action-card">
                <h3>Mediated Correspondence</h3>
                <p>Review, screen, and route incoming communication entries between sponsors and children to maintain protection standards.</p>
                <a href="#" class="btn-action">Open Message Queue</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>