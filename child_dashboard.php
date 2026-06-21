<?php
session_start();
require_once 'db_connect.php';

// Access Control Security Guard: Ensure only logged-in Children can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Child') {
    header("Location: login.php");
    exit();
}

// Straightaway grab the alphanumeric child ID from your new column session variable
$child_id = $_SESSION['user_type_id']; // This equals 'C000000001'

// 1. Fetch Child Profile details straight away using this ID
$child_stmt = $conn->prepare("SELECT * FROM child WHERE id = ?");
$child_stmt->bind_param("s", $child_id);
$child_stmt->execute();
$child_profile = $child_stmt->get_result()->fetch_assoc();
$child_stmt->close();

// 2. Query who their matched sponsor is using the relationship table
$sponsor_id = null;
$match_stmt = $conn->prepare("SELECT sponsor_user_id FROM child_sponsor_matches WHERE child_id = ? AND match_status = 'Active' LIMIT 1");
$match_stmt->bind_param("s", $child_id);
$match_stmt->execute();
$match_res = $match_stmt->get_result()->fetch_assoc();
if ($match_res) {
    // This will grab the sponsor's string ID (e.g., 'S000000001') or user table link based on your mapping
    $sponsor_id = $match_res['sponsor_user_id']; 
}
$match_stmt->close();

// 3. Get that Sponsor's profile info from the users table using the sponsor's ID
$sponsor_profile = null;
if ($sponsor_id) {
    // If your sponsor uses an alphanumeric code in user_type_id as well, look it up here:
    $s_stmt = $conn->prepare("SELECT id, username, email, user_type_id FROM users WHERE user_type_id = ? OR id = ?");
    $s_stmt->bind_param("ss", $sponsor_id, $sponsor_id);
    $s_stmt->execute();
    $sponsor_profile = $s_stmt->get_result()->fetch_assoc();
    $s_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Child Dashboard Panel</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background-color: #f4f6f9; color: #333; }
        .navbar { background-color: #0f766e; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar h1 { margin: 0; font-size: 20px; }
        .navbar a { color: white; text-decoration: none; font-size: 14px; font-weight: bold; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .welcome-box { background: white; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .welcome-box h2 { margin: 0 0 5px 0; color: #0f766e; }
        .card-title { font-size: 18px; font-weight: bold; color: #0f766e; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 8px; }
        .sponsor-card { background: white; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .btn-portal { padding: 12px 24px; background-color: #0f766e; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .btn-portal:hover { background-color: #0d5c55; }
    </style>
</head>
<body>

<div class="navbar">
    <h1>👋 Welcome to Your Letter Hub</h1>
    <a href="logout.php">Logout</a>
</div>

<div class="container">
    <div class="welcome-box">
        <h2>Hello, <?php echo htmlspecialchars($child_profile['first_name'] ?? 'Student'); ?>!</h2>
        <p>You can read updates from your assigned sponsor or send new letters directly back to them from this panel.</p>
    </div>

    <div class="card-title">My Connected Sponsor Profile</div>
    
    <div class="sponsor-card">
        <?php if ($sponsor_profile): ?>
            <div>
                <h3 style="margin: 0 0 8px 0;">Sponsor: <?php echo htmlspecialchars($sponsor_profile['username']); ?></h3>
                <p style="margin: 4px 0; font-size: 14px; color: #718096;">Email Reference: <?php echo htmlspecialchars($sponsor_profile['email']); ?></p>
                <p style="margin: 4px 0; font-size: 14px;"><span style="color: #059669; font-weight: bold;">✓ Linked Account (<?php echo htmlspecialchars($sponsor_profile['user_type_id'] ?? $sponsor_profile['id']); ?>)</span></p>
            </div>
            <div>
                <a href="view_sponsor_details.php?id=<?php echo urlencode($sponsor_profile['id']); ?>" class="btn-portal">
                    Open Correspondence Hub →
                </a>
            </div>
        <?php else: ?>
            <p style="color: #a0aec0; font-style: italic; width: 100%; text-align: center;">🤝 No sponsor accounts are linked to your child profile structure yet.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>