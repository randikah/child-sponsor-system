<?php
session_start();
require_once 'db_connect.php';

// Access Control Security Guard
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Sponsor' && $_SESSION['role'] !== 'Admin')) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_class = '';
$child_id = $_GET['id'] ?? '';

// Resolve Sponsor ID matching details
$user_id = $_SESSION['user_id'];
$sponsor_id = '';
$sp_stmt = $conn->prepare("SELECT id FROM sponsors WHERE user_id = ?");
$sp_stmt->bind_param("i", $user_id);
$sp_stmt->execute();
$sp_res = $sp_stmt->get_result();
if ($sp_row = $sp_res->fetch_assoc()) {
    $sponsor_id = $sp_row['id'];
}
$sp_stmt->close();

// VERIFY RELATIONSHIP MATCH MATRICES FOR SECURITY
$verified = false;
$v_stmt = $conn->prepare("SELECT id FROM child_sponsor_matches WHERE child_id = ? AND sponsor_user_id = ? AND match_status = 'Active'");
$v_stmt->bind_param("ss", $child_id, $sponsor_id);
$v_stmt->execute();
if ($v_stmt->get_result()->num_rows > 0 || $_SESSION['role'] === 'Admin') {
    $verified = true;
}
$v_stmt->close();

if (!$verified || empty($child_id)) {
    die("❌ Unauthorized Access Block: You do not possess explicit permission matching parameters to view this profile.");
}

// 1. HANDLE NEW CORRESPONDENCE DISPATCH (TEXT WRITING AND FILE UPLOADS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_letter') {
    $letter_text = trim($_POST['letter_content'] ?? '');
    $filename_db = '';
    $upload_ok = true;

    // Handle digital document upload parsing if an attachment is detected
    if (isset($_FILES['scanned_doc']) && $_FILES['scanned_doc']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['scanned_doc']['tmp_name'];
        $original_name = basename($_FILES['scanned_doc']['name']);
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Allowed extension parameters validation filtering checks
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        if (in_array($file_ext, $allowed_exts)) {
            // Re-label attachment files safely avoiding configuration overwriting collusions
            $new_filename = "LETTER_" . time() . "_" . uniqid() . "." . $file_ext;
            $upload_dir = "uploads/letters/";
            
            // Build sub-directory tree seamlessly if missing
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                $filename_db = $upload_dir . $new_filename;
            } else {
                $upload_ok = false;
                $message = "❌ System upload block: Internal folder migration write error failed.";
                $message_class = "error-msg";
            }
        } else {
            $upload_ok = false;
            $message = "❌ Verification failure: Invalid format. Allowed extensions: PDF, JPG, JPEG, PNG.";
            $message_class = "error-msg";
        }
    }

    if ($upload_ok && (!empty($letter_text) || !empty($filename_db))) {
        // Log details inside your system letters correspondence matching table registry
        // (Assumes a simple tracking layout schema setup layout: adjustment as required)
        $ins_letter = $conn->prepare("INSERT INTO letters (sender_type, child_id, sponsor_id, content_text, scan_file_path, status) VALUES ('Sponsor', ?, ?, ?, ?, 'Pending Review')");
        $ins_letter->bind_param("ssss", $child_id, $sponsor_id, $letter_text, $filename_db);
        if ($ins_letter->execute()) {
            $message = "✓ Letter entry submitted successfully to coordinators for translation review!";
            $message_class = "success-msg";
        } else {
            $message = "❌ Database error: Unable to log mailing configuration.";
            $message_class = "error-msg";
        }
        $ins_letter->close();
    }
}

// 2. FETCH DETAILED BENEFICIARY PROFILE CARD DATA
$child_data = null;
$stmt = $conn->prepare("SELECT * FROM child WHERE id = ?");
$stmt->bind_param("s", $child_id);
$stmt->execute();
$child_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. FETCH COMPREHENSIVE COMMUNICATIONS HISTORY TRACKING REGISTRY
$letters_history = [];
$let_stmt = $conn->prepare("SELECT * FROM letters WHERE child_id = ? AND sponsor_id = ? ORDER BY id DESC");
$let_stmt->bind_param("ss", $child_id, $sponsor_id);
$let_stmt->execute();
$letters_history = $let_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$let_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Profile & Letter Center</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background-color: #f4f6f9; color: #333; }
        .navbar { background-color: #0f766e; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar h1 { margin: 0; font-size: 20px; }
        .navbar a { color: white; text-decoration: none; font-size: 14px; font-weight: bold; }
        
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #0f766e; text-decoration: none; font-weight: bold; }
        .back-link:hover { text-decoration: underline; }
        
        .workspace-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 30px; }
        @media (max-width: 850px) { .workspace-grid { grid-template-columns: 1fr; } }
        
        .column-card { background: #fff; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-title { font-size: 18px; font-weight: bold; color: #0f766e; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 8px; }
        
        .profile-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #edf2f7; font-size: 14px; }
        .profile-label { font-weight: 600; color: #4a5568; }
        
        label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 6px; margin-top: 15px; }
        textarea { width: 100%; height: 120px; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; font-family: inherit; box-sizing: border-box; resize: vertical; }
        input[type="file"] { display: block; width: 100%; padding: 8px; background: #fafafa; border: 1px dashed #cbd5e0; border-radius: 4px; box-sizing: border-box; }
        
        .btn-submit { width: 100%; padding: 12px; background-color: #0f766e; border: none; color: white; font-size: 15px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 15px; }
        .btn-submit:hover { background-color: #0d5c55; }
        
        .msg-box { padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: bold; }
        .success-msg { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .letter-node { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin-bottom: 15px; font-size: 14px; }
        .letter-header { display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
        .tag-incoming { color: #b45309; }
        .tag-outgoing { color: #047857; }
        .doc-link { display: inline-block; margin-top: 8px; color: #2b6cb0; font-weight: 600; text-decoration: none; font-size: 13px; }
        .doc-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🤝 Profile & Correspondence Hub</h1>
    <a href="sponsor_dashboard.php">← Back to Dashboard</a>
</div>

<div class="container">
    <a href="sponsor_dashboard.php" class="back-link">← Return to Beneficiary Listing Grid Workspace</a>

    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="workspace-grid">
        <div class="column-card">
            <div class="card-title">Beneficiary Profile Record</div>
            <div class="profile-row">
                <span class="profile-label">Beneficiary ID:</span>
                <span style="font-family: monospace; font-weight: bold; color: #2b6cb0;"><?php echo htmlspecialchars($child_data['id']); ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Full Name:</span>
                <span><?php echo htmlspecialchars($child_data['first_name'] . ' ' . $child_data['last_name']); ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Age / Date of Birth:</span>
                <span><?php echo htmlspecialchars($child_data['age'] . ' Years (' . $child_data['dob'] . ')'); ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Education Status:</span>
                <span><?php echo htmlspecialchars($child_data['education_level']); ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Language Spoken:</span>
                <span><?php echo htmlspecialchars($child_data['language']); ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Health Conditions:</span>
                <span><?php echo htmlspecialchars($child_data['health_status']); ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Religious Alignment:</span>
                <span><?php echo htmlspecialchars($child_data['religion']); ?></span>
            </div>
        </div>

        <div class="column-card">
            <div class="card-title">Write / Upload a Letter</div>
            <form action="view_child_details.php?id=<?php echo urlencode($child_id); ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="send_letter">
                
                <label for="letter_content">Type Letter Body Message (will be translated for the child)</label>
                <textarea id="letter_content" name="letter_content" placeholder="Write your encouraging words to your sponsored child here..." required></textarea>
                
                <label for="scanned_doc">Attach Original Scanned Document copy (Optional)</label>
                <input type="file" id="scanned_doc" name="scanned_doc" accept=".pdf, .jpg, .jpeg, .png">
                <small style="color: #718096; display: block; margin-top: 4px;">Supported extensions: PDF, PNG, JPG up to 5MB size.</small>
                
                <button type="submit" class="btn-submit">Dispatch Letter Entry</button>
            </form>

            <div class="card-title" style="margin-top: 40px;">Letter Correspondence Feed logs</div>
            <?php if (count($letters_history) > 0): ?>
                <?php foreach ($letters_history as $let): ?>
                    <div class="letter-node">
                        <div class="letter-header">
                            <span class="<?php echo ($let['sender_type'] === 'Child') ? 'tag-incoming' : 'tag-outgoing'; ?>">
                                <?php echo ($let['sender_type'] === 'Child') ? '📥 Received from Child' : '📤 Sent by Me (Sponsor)'; ?>
                            </span>
                            <span style="color: #718096;"><?php echo date("M d, Y", strtotime($let['created_at'] ?? 'now')); ?></span>
                        </div>
                        <div style="white-space: pre-line; line-height: 1.4; color: #2d3748;">
                            <?php echo htmlspecialchars($let['content_text']); ?>
                        </div>
                        <?php if (!empty($let['scan_file_path'])): ?>
                            <a href="<?php echo htmlspecialchars($let['scan_file_path']); ?>" target="_blank" class="doc-link">
                                📄 View Attached Original Scan Document Document →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="font-size: 13px; color: #a0aec0; text-align: center; margin-top: 15px;">No active letters log historical data generated for this sponsorship pairing context.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>