<?php
session_start();
require_once 'db_connect.php';

// Access Control Security Guard: Allow Coordinator OR Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Coordinator' && $_SESSION['role'] !== 'Admin')) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_class = '';

$child_id = '';
$child_data = null;

$sponsor_id = '';
$sponsor_data = null;

$current_matches = [];
$has_active_sponsorship = false; // Flag to track if the child is already sponsored

// 1. DYNAMIC STATE PRESERVATION (URL QUERY PARAMETERS)
if (isset($_GET['search_child_id']) && !empty(trim($_GET['search_child_id']))) {
    $child_id = trim($_GET['search_child_id']);
}
if (isset($_GET['search_sponsor_id']) && !empty(trim($_GET['search_sponsor_id']))) {
    $sponsor_id = trim($_GET['search_sponsor_id']);
}

// 2. LOAD BENEFICIARY PROFILE & CHECK ACTIVE SPONSORSHIP STATUS
if (!empty($child_id)) {
    $stmt = $conn->prepare("SELECT * FROM child WHERE user_id = ?");
    $stmt->bind_param("s", $child_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $child_data = $res->fetch_assoc();
        
        // Fetch historical pairings mapping database tracking logs
        $match_stmt = $conn->prepare("SELECT m.id, m.match_status, m.created_at, s.id AS sponsor_code, s.first_name, s.last_name, s.residence_country FROM child_sponsor_matches m JOIN sponsors s ON m.sponsor_user_id = s.id WHERE m.child_id = ? ORDER BY m.id DESC");
        $match_stmt->bind_param("s", $child_id);
        $match_stmt->execute();
        $current_matches = $match_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $match_stmt->close();

        // Scan current matches to evaluate if an 'Active' record block already exists for this child
        foreach ($current_matches as $match) {
            if ($match['match_status'] === 'Active') {
                $has_active_sponsorship = true;
                break;
            }
        }
    } else {
        $message = "❌ Error: Child account identifier record [" . htmlspecialchars($child_id) . "] does not exist.";
        $message_class = "error-msg";
        $child_id = '';
    }
    $stmt->close();
}

// 3. PROCESS NEW SPONSORSHIP MATCH CREATION (POST REQUEST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_match') {
    $target_child_id = trim($_POST['match_child_id'] ?? '');
    $target_sponsor_id = trim($_POST['match_sponsor_id'] ?? ''); 
    $assigned_by = $_SESSION['user_id'];

    if ($has_active_sponsorship) {
        $message = "❌ Security Violation: This child is already sponsored.";
        $message_class = "error-msg";
    } elseif (!empty($target_child_id) && !empty($target_sponsor_id)) {
        
        // 1. Check if this specific pairing has existed before (even as Terminated)
        $check_stmt = $conn->prepare("SELECT id, match_status FROM child_sponsor_matches WHERE child_id = ? AND sponsor_user_id = ?");
        $check_stmt->bind_param("ss", $target_child_id, $target_sponsor_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result();

        if ($res->num_rows > 0) {
            $existing = $res->fetch_assoc();
            
            if ($existing['match_status'] === 'Active') {
                $message = "❌ Error: This sponsor is already actively linked to this child.";
                $message_class = "error-msg";
            } else {
                // 2. Reactivate the old "Terminated" record
                $upd_stmt = $conn->prepare("UPDATE child_sponsor_matches SET match_status = 'Active', assigned_by_user_id = ? WHERE id = ?");
                $upd_stmt->bind_param("ii", $assigned_by, $existing['id']);
                $upd_stmt->execute();
                $message = "✓ Sponsorship reactivated successfully!";
                $message_class = "success-msg";
            }
        } else {
            // 3. Brand new pairing
            $ins_stmt = $conn->prepare("INSERT INTO child_sponsor_matches (child_id, sponsor_user_id, match_status, assigned_by_user_id) VALUES (?, ?, 'Active', ?)");
            $ins_stmt->bind_param("ssi", $target_child_id, $target_sponsor_id, $assigned_by);
            $ins_stmt->execute();
            $message = "✓ New sponsorship pairing created!";
            $message_class = "success-msg";
        }
        header("Location: match_sponsor.php?search_child_id=" . urlencode($target_child_id));
        exit();
    }
}


// 4. PROCESS MATCH TERMINATION DEACTIVATION (POST REQUEST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'terminate_match') {
    $match_id = intval($_POST['match_id'] ?? 0);

    if ($match_id > 0) {
        $del_stmt = $conn->prepare("UPDATE child_sponsor_matches SET match_status = 'Terminated' WHERE id = ?");
        $del_stmt->bind_param("i", $match_id);
        if ($del_stmt->execute()) {
            $message = "✓ Sponsorship relationship safely marked as Terminated.";
            $message_class = "success-msg";
            
            // Refresh state parameters dynamically to unblock linking workspace options immediately
            header("Location: match_sponsor.php?search_child_id=" . urlencode($child_id) . "&search_sponsor_id=" . urlencode($sponsor_id));
            exit();
        }
        $del_stmt->close();
    }
}

// 5. LOAD SPONSOR PROFILE FROM DATABASE (EXCLUSIVELY ACTIVE)
if (!empty($sponsor_id)) {
    $stmt = $conn->prepare("SELECT * FROM sponsors WHERE id = ?");
    $stmt->bind_param("s", $sponsor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $fetched_sponsor = $res->fetch_assoc();
        if (strcasecmp($fetched_sponsor['status'], 'Active') === 0) {
            $sponsor_data = $fetched_sponsor;
        } else {
            $message = "⚠️ Status Alert: Target Sponsor profile found but lifecycle marker is currently configuration disabled or Inactive.";
            $message_class = "error-msg";
            $sponsor_id = '';
        }
    } else {
        $message = "❌ Error: Sponsor platform registry ID account reference [" . htmlspecialchars($sponsor_id) . "] does not exist.";
        $message_class = "error-msg";
        $sponsor_id = '';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsorship Matching Management System</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .admin-nav { background-color: #343a40; padding: 10px 20px; display: flex; gap: 15px; align-items: center; color: white; font-size: 14px; }
        .admin-nav a { color: #ffc107; text-decoration: none; font-weight: bold; padding: 5px 10px; border: 1px solid #ffc107; border-radius: 4px; }
        .admin-nav a:hover { background-color: #ffc107; color: black; }
        
        .container { max-width: 1100px; background: white; margin: 30px auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        h2 { text-align: center; color: #1e3a8a; margin-bottom: 5px; }
        p.subtitle { text-align: center; color: #718096; margin-bottom: 25px; font-size: 14px; }
        
        .search-card { background: #f0f4f8; padding: 20px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #cbd5e0; }
        .search-form { display: flex; gap: 10px; }
        .search-form input { flex-grow: 1; padding: 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 15px; font-weight: bold; text-transform: uppercase; background-color: #fff; }
        .btn-search { background-color: #2b6cb0; padding: 0 25px; color: white; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; }
        .btn-search:hover { background-color: #2c5282; }

        .workspace-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; }
        @media (max-width: 850px) { .workspace-grid { grid-template-columns: 1fr; } }
        
        .column-card { background: #fff; border: 1px solid #e2e8f0; padding: 20px; border-radius: 6px; display: flex; flex-direction: column; justify-content: space-between; }
        .card-title { font-size: 16px; font-weight: bold; color: #1e3a8a; margin-bottom: 15px; border-bottom: 2px solid #edf2f7; padding-bottom: 8px; }
        
        .profile-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #edf2f7; font-size: 14px; }
        .profile-label { font-weight: 600; color: #4a5568; }
        .profile-value { color: #2d3748; }

        label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 6px; }
        
        .btn-action { width: 100%; padding: 12px; background-color: #28a745; border: none; color: white; font-size: 15px; font-weight: bold; border-radius: 4px; cursor: pointer; box-sizing: border-box; margin-top: 15px; }
        .btn-action:hover { background-color: #218838; }
        .btn-terminate { background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; }
        .btn-terminate:hover { background-color: #c82333; }

        .msg-box { padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: bold; }
        .success-msg { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .match-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-active { background-color: #c3e6cb; color: #155724; }
        .badge-terminated { background-color: #e2e8f0; color: #4a5568; }
        
        .alert-block { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 12px; border-radius: 4px; font-size: 13px; font-weight: 600; margin-bottom: 15px; text-align: center; }

        .nav-link { display: block; text-align: center; margin-top: 25px; color: #1e3a8a; text-decoration: none; font-size: 14px; font-weight: bold; }
        .nav-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<?php if ($_SESSION['role'] === 'Admin'): ?>
    <div class="admin-nav">
        <strong>⚡ Admin View Mode:</strong> Go to: 
        <a href="admin_dashboard.php">Admin Panel</a>
        <a href="coordinator_dashboard.php">Coordinator Panel</a>
        <a href="register_child.php">Child Registry Lookup</a>
    </div>
<?php endif; ?>

<div class="container">
    <h2>Sponsorship Matching Center</h2>
    <p class="subtitle">Link program beneficiaries with active external funding donors and manage active sponsorship assignments</p>

    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="search-card">
        <label for="search_child_id">Specify Targeted Child Alphanumeric ID string</label>
        <form action="match_sponsor.php" method="GET" class="search-form">
            <input type="hidden" name="search_sponsor_id" value="<?php echo htmlspecialchars($sponsor_id); ?>">
            <input type="text" id="search_child_id" name="search_child_id" value="<?php echo htmlspecialchars($child_id); ?>" placeholder="e.g., C000000001" required>
            <button type="submit" class="btn-search">Load Beneficiary Profile</button>
        </form>
    </div>

    <?php if ($child_data): ?>
        <div class="workspace-grid">
            
            <div class="column-card">
                <div>
                    <div class="card-title">Beneficiary Summary Matrix</div>
                    <div class="profile-row">
                        <span class="profile-label">System Record ID:</span>
                        <span class="profile-value" style="font-family: monospace; font-weight: bold; color: #2b6cb0;"><?php echo htmlspecialchars($child_data['user_id']); ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Full Legal Name:</span>
                        <span class="profile-value"><?php echo htmlspecialchars($child_data['first_name'] . ' ' . $child_data['last_name']); ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Age & Date of Birth:</span>
                        <span class="profile-value"><?php echo htmlspecialchars($child_data['age'] . ' Years (' . $child_data['dob'] . ')'); ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Education Status:</span>
                        <span class="profile-value"><?php echo htmlspecialchars($child_data['education_level']); ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Primary Language:</span>
                        <span class="profile-value"><?php echo htmlspecialchars($child_data['language']); ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Religious Alignment:</span>
                        <span class="profile-value"><?php echo htmlspecialchars($child_data['religion']); ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Medical Summary:</span>
                        <span class="profile-value"><?php echo htmlspecialchars($child_data['health_status']); ?></span>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <div class="card-title">Active & Historical Funding Registry</div>
                    <?php if (count($current_matches) > 0): ?>
                        <?php foreach ($current_matches as $match): ?>
                            <div class="profile-row" style="align-items: center;">
                                <div>
                                    <strong style="font-size: 13px; color: #1e3a8a;"><?php echo htmlspecialchars($match['first_name'] . ' ' . $match['last_name']); ?></strong> 
                                    <span style="font-family: monospace; font-size:12px; color: #718096;">[<?php echo htmlspecialchars($match['sponsor_code']); ?>]</span><br>
                                    <small style="color:#718096;">Country: <?php echo htmlspecialchars($match['residence_country']); ?></small>
                                </div>
                                <div style="text-align: right; display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                                    <span class="match-badge <?php echo ($match['match_status'] === 'Active') ? 'badge-active' : 'badge-terminated'; ?>">
                                        <?php echo $match['match_status']; ?>
                                    </span>
                                    <?php if ($match['match_status'] === 'Active'): ?>
                                        <form action="match_sponsor.php?search_child_id=<?php echo urlencode($child_id); ?>&search_sponsor_id=<?php echo urlencode($sponsor_id); ?>" method="POST" onsubmit="return confirm('Are you sure you want to terminate this sponsorship assignment?');">
                                            <input type="hidden" name="action" value="terminate_match">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <button type="submit" class="btn-terminate">Terminate</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size: 13px; color: #a0aec0; text-align: center; margin-top: 15px;">No historical or active sponsorship associations configured for this beneficiary profile.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="column-card">
                <div>
                    <div class="card-title">Establish New Sponsorship Association</div>
                    
                    <?php if ($has_active_sponsorship): ?>
                        <div class="alert-block">
                            ⚠️ MATCH BLOCKER: This child profile is linked to an Active funding sponsor. You must terminate the existing active sponsorship entry before you can assign a new donor.
                        </div>
                    <?php endif; ?>

                    <label for="search_sponsor_id">Specify Target Funding Sponsor ID</label>
                    <form action="match_sponsor.php" method="GET" class="search-form" style="margin-bottom: 20px;">
                        <input type="hidden" name="search_child_id" value="<?php echo htmlspecialchars($child_id); ?>">
                        <input type="text" 
                               id="search_sponsor_id" 
                               name="search_sponsor_id" 
                               value="<?php echo htmlspecialchars($sponsor_id); ?>" 
                               placeholder="e.g., S000000001" 
                               <?php echo $has_active_sponsorship ? 'disabled style="background-color: #e9ecef; cursor: not-allowed;"' : ''; ?> 
                               required>
                        <button type="submit" 
                                class="btn-search" 
                                style="background-color: #4a5568;" 
                                <?php echo $has_active_sponsorship ? 'disabled style="background-color: #cbd5e0; cursor: not-allowed;"' : ''; ?>>
                            Search
                        </button>
                    </form>

                    <?php if ($sponsor_data && !$has_active_sponsorship): ?>
                        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
                            <div style="font-size: 14px; font-weight: bold; color: #2d3748; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
                                Verified Funding Sponsor Profile
                            </div>
                            <div class="profile-row">
                                <span class="profile-label">Sponsor ID:</span>
                                <span class="profile-value" style="font-family: monospace; font-weight: bold; color: #28a745;"><?php echo htmlspecialchars($sponsor_data['id']); ?></span>
                            </div>
                            <div class="profile-row">
                                <span class="profile-label">Full Name:</span>
                                <span class="profile-value"><?php echo htmlspecialchars($sponsor_data['first_name'] . ' ' . $sponsor_data['last_name']); ?></span>
                            </div>
                            <div class="profile-row">
                                <span class="profile-label">Country of Residence:</span>
                                <span class="profile-value"><?php echo htmlspecialchars($sponsor_data['residence_country']); ?></span>
                            </div>
                            <div class="profile-row">
                                <span class="profile-label">System Status:</span>
                                <span class="profile-value"><span class="match-badge badge-active"><?php echo htmlspecialchars($sponsor_data['status']); ?></span></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if (!$has_active_sponsorship): ?>
                            <p style="text-align: center; color: #a0aec0; padding: 30px 10px; border: 2px dashed #edf2f7; border-radius: 6px; background-color: #fafafa; font-size: 13px;">
                                <?php if (!empty($sponsor_id)): ?>
                                    No valid active sponsor records returned matching search parameter query.
                                <?php else: ?>
                                    Enter an active Sponsor Account verification query string ID above to view metadata criteria profile.
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div>
                    <?php if ($child_data && $sponsor_data && !$has_active_sponsorship): ?>
                        <form action="match_sponsor.php?search_child_id=<?php echo urlencode($child_id); ?>&search_sponsor_id=<?php echo urlencode($sponsor_id); ?>" method="POST">
                            <input type="hidden" name="action" value="create_match">
                            <input type="hidden" name="match_child_id" value="<?php echo htmlspecialchars($child_data['user_id']); ?>">
                            <input type="hidden" name="match_sponsor_id" value="<?php echo htmlspecialchars($sponsor_data['id']); ?>">
                            
                            <button type="submit" class="btn-action">Link Sponsor to Child</button>
                        </form>
                    <?php else: ?>
                        <button class="btn-action" style="background-color: #cbd5e0; color: #718096; cursor: not-allowed;" disabled>
                            <?php echo $has_active_sponsorship ? 'Sponsorship Blocked (Child Already Matched)' : 'Select Both Profiles to Link'; ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    <?php else: ?>
        <p style="text-align: center; color: #a0aec0; padding: 40px 0; border: 2px dashed #edf2f7; border-radius: 6px; background-color: #fafafa;">
            Provide a valid child identity search metric above to generate the program matching interface toolkit workspace.
        </p>
    <?php endif; ?>

    <a href="coordinator_dashboard.php" class="nav-link">← Return to Coordinator Panel Workspace</a>
</div>

</body>
</html>