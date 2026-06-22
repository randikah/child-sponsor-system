<?php
session_start();
require_once 'db_connect.php';

// Access Control Security Guard: Allow Sponsor OR Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Sponsor' && $_SESSION['role'] !== 'Admin')) {
    header("Location: login.php");
    exit();
}

// 1. RESOLVE THE SPONSOR ID FROM THE LOGIN SESSION USER ID
$user_id = $_SESSION['user_id'];
$sponsor_id = '';
$sponsor_name = $_SESSION['username'];

// Fetch alphanumeric ID mapping details from your dedicated sponsors table structure
// Fallback: If 'id' is the primary key and corresponds to your session user_id:
$sp_stmt = $conn->prepare("SELECT id, first_name, last_name FROM sponsors WHERE id = ?");
$sp_stmt->bind_param("s", $user_id); // Changed parameter bind column from user_id to id
$sp_stmt->execute();
$sp_res = $sp_stmt->get_result();

if ($sp_res->num_rows === 1) {
    $sponsor_row = $sp_res->fetch_assoc();
    $sponsor_id = $sponsor_row['id'];
    $sponsor_name = $sponsor_row['first_name'] . ' ' . $sponsor_row['last_name'];
} else {
    // If no row is matched, assign the session user_id directly as a fallback
    $sponsor_id = ($_SESSION['sponsor_id']);
}
$sp_stmt->close();

// 2. FETCH ALL CURRENTLY ASSIGNED ACTIVE CHILDREN FOR THIS SPONSOR
$sponsored_children = [];
if (!empty($sponsor_id)) {
    $child_query = $conn->prepare("
        SELECT c.user_id, c.first_name, c.last_name, c.age, c.education_level 
        FROM child_sponsor_matches m 
        JOIN child c ON m.child_id = c.user_id 
        WHERE m.sponsor_user_id = ? AND m.match_status = 'Active'
    ");
    $child_query->bind_param("s", $sponsor_id);
    $child_query->execute();
    $sponsored_children = $child_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $child_query->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsor Portal Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background-color: #f4f6f9; color: #333; }
        .admin-nav { background-color: #343a40; padding: 10px 20px; display: flex; gap: 15px; align-items: center; color: white; font-size: 14px; }
        .admin-nav a { color: #ffc107; text-decoration: none; font-weight: bold; padding: 5px 10px; border: 1px solid #ffc107; border-radius: 4px; }
        .admin-nav a:hover { background-color: #ffc107; color: black; }
        
        .navbar { background-color: #0f766e; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; font-size: 14px; }
        .btn-logout { background-color: #b91c1c; padding: 8px 15px; border-radius: 4px; font-weight: bold; transition: 0.2s; }
        .btn-logout:hover { background-color: #991b1b; }
        
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .welcome-box { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; border-left: 5px solid #0f766e; }
        .welcome-box h2 { margin-top: 0; color: #0f766e; }
        
        .dashboard-layout { display: flex; gap: 25px; }
        @media (max-width: 800px) { .dashboard-layout { flex-direction: column; } }
        
        .child-profile-preview { flex: 2; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .message-panel { flex: 1; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); height: fit-content; }
        
        .card-title { font-size: 18px; font-weight: bold; color: #0f766e; margin-bottom: 15px; border-bottom: 2px solid #edf2f7; padding-bottom: 8px; }
        
        /* Grid styling for child list layout */
        .child-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        @media (max-width: 600px) { .child-grid { grid-template-columns: 1fr; } }
        
        .child-card { background: #fafafa; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; display: flex; flex-direction: column; justify-content: space-between; transition: 0.2s; }
        .child-card:hover { border-color: #0f766e; box-shadow: 0 4px 6px rgba(15, 118, 110, 0.08); }
        .child-meta { font-size: 14px; margin-bottom: 12px; }
        .child-id { font-family: monospace; font-weight: bold; color: #2b6cb0; background: #ebf8ff; padding: 2px 6px; border-radius: 4px; }
        .child-name { font-size: 16px; font-weight: bold; margin: 8px 0 4px 0; color: #1a202c; }
        
        .btn-view { display: inline-block; text-align: center; background-color: #0f766e; color: white; padding: 8px 12px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 13px; transition: 0.2s; }
        .btn-view:hover { background-color: #0d5c55; }
        
        .empty-state { color: #718096; font-style: italic; text-align: center; padding: 30px 10px; border: 2px dashed #edf2f7; border-radius: 6px; background-color: #fafafa; }
    </style>
</head>
<body>

<?php if ($_SESSION['role'] === 'Admin'): ?>
    <div class="admin-nav">
        <strong>⚡ Admin View Mode:</strong> Go to: 
        <a href="admin_dashboard.php">Admin Panel</a>
        <a href="coordinator_dashboard.php">Coordinator Panel</a>
        <a href="sponsor_dashboard.php" style="background:#ffc107; color:black;">Sponsor Portal</a>
    </div>
<?php endif; ?>

<div class="navbar">
    <h1>🤝 Sponsor Engagement Portal</h1>
    <div>
        <a href="#">My Profile (<?php echo htmlspecialchars($sponsor_id); ?>)</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="container">
    <div class="welcome-box">
        <h2>Welcome Back, <?php echo htmlspecialchars($sponsor_name); ?>!</h2>
        <p>Your Sponsor Registry Account Code: <strong style="font-family: monospace; color:#2b6cb0;"><?php echo htmlspecialchars($sponsor_id); ?></strong></p>
        <p>Thank you for making a lasting difference. Below are the children assigned to your sponsorship registry. You can inspect updates, read scanned logs, or upload responses by choosing a child profile record.</p>
    </div>

    <div class="dashboard-layout">
        <div class="child-profile-preview">
            <div class="card-title">My Sponsored Beneficiaries </div>
            
            <?php if (count($sponsored_children) > 0): ?>
                <div class="child-grid">
                    <?php foreach ($sponsored_children as $child): ?>
                        <div class="child-card">
                            <div class="child-meta">
                                <span class="child-id"><?php echo htmlspecialchars($child['user_id']); ?></span>
                                <div class="child-name"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></div>
                                <div style="color: #718096; font-size: 13px; margin-top: 4px;">
                                    Age: <?php echo htmlspecialchars($child['age']); ?> Yrs | Ed: <?php echo htmlspecialchars($child['education_level']); ?>
                                </div>
                            </div>
                            <a href="view_child_details.php?id=<?php echo urlencode($child['user_id']); ?>" class="btn-view">
                                Open Profile & Correspondence Center →
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    No active child profiles are linked to your sponsorship record yet. Once a field coordinator establishes a match, the child's identity data mapping card will populate here instantly.
                </div>
            <?php endif; ?>
        </div>

        <div class="message-panel">
            <div class="card-title">Correspondence Rules</div>
            <p style="font-size: 14px; color: #4a5568; line-height: 1.5;">
                To preserve child protection and privacy boundaries, all mail items are systematically routed and screened by program administrators.
            </p>
            <p style="font-size: 13px; color: #718096; background: #f7fafc; padding: 10px; border-radius: 4px; border-left: 3px solid #cbd5e0;">
                💡 <strong>Tip:</strong> Select an active beneficiary profile from the left panel column workspace to read received letters or drop off replies.
            </p>
        </div>
    </div>

    <pre style="background: #222; color: #00ff00; padding: 15px; border-radius: 5px; margin: 20px; font-size: 13px; overflow: auto;">
    <strong>Active Session Dump Matrix:</strong>
    <?php print_r($_SESSION); ?>
</pre>
</div>

</body>
</html>