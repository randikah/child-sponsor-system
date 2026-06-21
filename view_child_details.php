<?php
session_start();
require_once 'db_connect.php';

// Access Control Security Guard: Include Coordinator along with Sponsor and Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Sponsor', 'Coordinator', 'Admin'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_class = '';
$child_id = $_GET['id'] ?? '';

// Resolve Sponsor ID matching details
$user_id = $_SESSION['user_id'];
$sponsor_id = $_SESSION['sponsor_id'] ?? '';

if (empty($sponsor_id) && $_SESSION['role'] === 'Sponsor') {
    $sp_stmt = $conn->prepare("SELECT id FROM sponsors WHERE user_id = ?");
    $sp_stmt->bind_param("i", $user_id);
    $sp_stmt->execute();
    if ($sp_row = $sp_stmt->get_result()->fetch_assoc()) {
        $sponsor_id = $sp_row['id'];
        $_SESSION['sponsor_id'] = $sponsor_id;
    }
    $sp_stmt->close();
}

// VERIFY RELATIONSHIP MATCH MATRICES FOR SECURITY
$verified = false;
if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Coordinator') {
    $verified = true;
} else {
    $v_stmt = $conn->prepare("SELECT id FROM child_sponsor_matches WHERE child_id = ? AND sponsor_user_id = ? AND match_status = 'Active'");
    $v_stmt->bind_param("ss", $child_id, $sponsor_id);
    $v_stmt->execute();
    if ($v_stmt->get_result()->num_rows > 0) {
        $verified = true;
    }
    $v_stmt->close();
}

if (!$verified || empty($child_id)) {
    die("❌ Unauthorized Access Block: You do not possess explicit permission matching parameters to view this profile.");
}

// 1. HANDLE NEW CORRESPONDENCE DISPATCH (TEXT WRITING AND FILE UPLOADS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_letter') {
    $letter_text = trim($_POST['letter_content'] ?? '');
    $filename_db = '';
    $upload_ok = true;

    // Handle file upload if an attachment is provided
    if (isset($_FILES['scanned_doc']) && $_FILES['scanned_doc']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['scanned_doc']['tmp_name'];
        $original_name = basename($_FILES['scanned_doc']['name']);
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_filename = "LETTER_" . time() . "_" . uniqid() . "." . $file_ext;
            $upload_dir = "uploads/letters/";
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                $filename_db = $upload_dir . $new_filename;
            } else {
                $upload_ok = false;
                $message = "❌ System upload block: Folder write permission failure.";
                $message_class = "error-msg";
            }
        } else {
            $upload_ok = false;
            $message = "❌ Format error. Allowed extensions: PDF, JPG, JPEG, PNG.";
            $message_class = "error-msg";
        }
    }

    if ($upload_ok && (!empty($letter_text) || !empty($filename_db))) {
        // We explicitly insert 'Pending' as the initial status.
        $ins_letter = $conn->prepare("INSERT INTO letters (sender_role, child_id, sponsor_user_id, letter_title, file_path, status) VALUES ('Sponsor', ?, ?, ?, ?, 'Pending')");
        $default_title = "Letter to Beneficiary";
        $ins_letter->bind_param("ssss", $child_id, $sponsor_id, $letter_text, $filename_db); 
        
        if ($ins_letter->execute()) {
            $message = "✓ Letter entry submitted successfully! It is now 'Pending' awaiting Coordinator review.";
            $message_class = "success-msg";
        } else {
            $message = "❌ Database error: Unable to log mailing configuration: " . $conn->error;
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
$letter_stmt = $conn->prepare("SELECT * FROM letters WHERE child_id = ? ORDER BY created_at DESC");
$letter_stmt->bind_param("s", $child_id);
$letter_stmt->execute();
$letters_history = $letter_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$letter_stmt->close();
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
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #0f766e; text-decoration: none; font-weight: bold; }
        .back-link:hover { text-decoration: underline; }
        
        .workspace-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 30px; margin-bottom: 30px; }
        @media (max-width: 850px) { .workspace-grid { grid-template-columns: 1fr; } }
        
        .column-card { background: #fff; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); height: fit-content; }
        .full-width-card { background: #fff; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-top: 30px; }
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
        
        .history-table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: left; font-size: 14px; }
        .history-table th { background-color: #f8fafc; padding: 12px; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        .history-table td { padding: 12px; border-bottom: 1px solid #edf2f7; vertical-align: middle; }
        .history-table tr:hover { background-color: #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; display: inline-block; }
        .badge-pending { background-color: #fef3c7; color: #d97706; }
        .badge-approved { background-color: #d1fae5; color: #059669; }
        .badge-rejected { background-color: #fee2e2; color: #dc2626; }
        
        .btn-action { padding: 6px 12px; background: #0f766e; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; border: none; cursor: pointer; }
        .btn-action:hover { background: #0d5c55; }
        .comment-block { background: #fff5f5; border-left: 3px solid #f56565; padding: 8px 12px; margin-top: 6px; font-size: 13px; color: #c53030; border-radius: 0 4px 4px 0; }

        .modal-mask { display: none; position: fixed; z-index: 9999; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-body { background: white; padding: 25px; border-radius: 8px; width: 100%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); max-height: 85vh; overflow-y: auto; }
        .modal-footer { text-align: right; margin-top: 20px; border-top: 1px solid #edf2f7; padding-top: 15px; }
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
            <div class="profile-row"><span class="profile-label">Beneficiary ID:</span><span style="font-family: monospace; font-weight: bold; color: #2b6cb0;"><?php echo htmlspecialchars($child_data['id'] ?? 'N/A'); ?></span></div>
            <div class="profile-row"><span class="profile-label">Full Name:</span><span><?php echo htmlspecialchars(($child_data['first_name'] ?? '') . ' ' . ($child_data['last_name'] ?? '')); ?></span></div>
            <div class="profile-row"><span class="profile-label">Age / Date of Birth:</span><span><?php echo htmlspecialchars(($child_data['age'] ?? 'N/A') . ' Years (' . ($child_data['dob'] ?? 'N/A') . ')'); ?></span></div>
            <div class="profile-row"><span class="profile-label">Education Status:</span><span><?php echo htmlspecialchars($child_data['education_level'] ?? 'N/A'); ?></span></div>
            <div class="profile-row"><span class="profile-label">Language Spoken:</span><span><?php echo htmlspecialchars($child_data['language'] ?? 'N/A'); ?></span></div>
            <div class="profile-row"><span class="profile-label">Health Conditions:</span><span><?php echo htmlspecialchars($child_data['health_status'] ?? 'N/A'); ?></span></div>
            <div class="profile-row"><span class="profile-label">Religious Alignment:</span><span><?php echo htmlspecialchars($child_data['religion'] ?? 'N/A'); ?></span></div>
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
        </div>
    </div>

    <div class="full-width-card">
        <div class="card-title">My Dispatched Letters History & Moderation Progress</div>
        <table class="history-table">
            <thead>
                <tr>
                    <th>Date Created</th>
                    <th>Document Preview</th>
                    <th>Approval Status</th>
                    <th>File Attachment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sponsor_letters = array_filter($letters_history, function($l) { return $l['sender_role'] !== 'Child'; });
                if (count($sponsor_letters) > 0): 
                    foreach ($sponsor_letters as $let): 
                        $status = $let['status'] ?? 'Pending';
                        $badge_class = 'badge-pending';
                        if ($status === 'Approved') $badge_class = 'badge-approved';
                        if ($status === 'Rejected') $badge_class = 'badge-rejected';
                ?>
                    <tr>
                        <td><strong><?php echo date("M d, Y", strtotime($let['created_at'])); ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($let['letter_title'], 0, 50)) . (strlen($let['letter_title']) > 50 ? '...' : ''); ?></td>
                        <td>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                            <?php if ($status === 'Rejected' && !empty($let['coordinator_comment'])): ?>
                                <div class="comment-block">💬 <strong>Coordinator Note:</strong> <?php echo htmlspecialchars($let['coordinator_comment']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($let['file_path'])): ?>
                                <a href="<?php echo htmlspecialchars($let['file_path']); ?>" target="_blank" style="color: #2b6cb0; font-weight:600; text-decoration:none;">📄 View File</a>
                            <?php else: ?>
                                <span style="color: #a0aec0;">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-action" onclick="openLetterModal('My Dispatched Letter', <?php echo htmlspecialchars(json_encode($let['letter_title'])); ?>, '<?php echo $status; ?>', <?php echo htmlspecialchars(json_encode($let['coordinator_comment'] ?? '')); ?>)">Open Document</button>
                        </td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr><td colspan="5" style="text-align: center; color: #a0aec0;">No letters logged to this beneficiary profile yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="full-width-card">
        <div class="card-title">Received Beneficiary Correspondence Mail Log</div>
        <table class="history-table">
            <thead>
                <tr>
                    <th>Date Received</th>
                    <th>Sender Classification</th>
                    <th>Letter Content</th>
                    <th>Action Link</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $child_letters = array_filter($letters_history, function($l) { return $l['sender_role'] === 'Child'; });
                if (count($child_letters) > 0): 
                    foreach ($child_letters as $let): 
                ?>
                    <tr>
                        <td><strong><?php echo date("M d, Y", strtotime($let['created_at'])); ?></strong></td>
                        <td><span style="color: #b45309; font-weight: bold;">📥 Beneficiary Child</span></td>
                        <td><?php echo htmlspecialchars(substr($let['letter_title'], 0, 50)) . (strlen($let['letter_title']) > 50 ? '...' : ''); ?></td>
                        <td>
                            <button class="btn-action" onclick="openLetterModal('Letter from Child', <?php echo htmlspecialchars(json_encode($let['letter_title'])); ?>, 'Approved', '')">Read Mail Entry</button>
                        </td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr><td colspan="4" style="text-align: center; color: #a0aec0;">No letters received from this child beneficiary yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="letterModal" class="modal-mask">
    <div class="modal-body">
        <h3 id="modalTitle" style="color: #0f766e; margin-top: 0; border-bottom: 2px solid #edf2f7; padding-bottom: 8px;">Document Details</h3>
        <p><strong>System Record Meta-State:</strong> <span id="modalStatus" class="badge">Pending</span></p>
        <div id="modalCommentBox" style="display: none; margin-bottom: 15px;"></div>
        <label>Letter Content Description:</label>
        <p id="modalContent" style="background: #f8fafc; padding: 15px; border-radius: 4px; border: 1px solid #e2e8f0; white-space: pre-line; line-height: 1.5;"></p>
        <div class="modal-footer">
            <button class="btn-submit" style="width: auto; margin-top: 0; padding: 8px 20px;" onclick="closeLetterModal()">Close and Go Back</button>
        </div>
    </div>
</div>

<script>
function openLetterModal(title, content, status, comment) {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalContent').innerText = content;
    
    const statusBadge = document.getElementById('modalStatus');
    statusBadge.innerText = status;
    statusBadge.className = 'badge';
    if (status === 'Approved') statusBadge.classList.add('badge-approved');
    else if (status === 'Rejected') statusBadge.classList.add('badge-rejected');
    else statusBadge.classList.add('badge-pending');

    const commentBox = document.getElementById('modalCommentBox');
    if (status === 'Rejected' && comment !== '') {
        commentBox.style.display = 'block';
        commentBox.innerHTML = `<div class="comment-block"><strong>Rejection Motive Note:</strong> ${comment}</div>`;
    } else {
        commentBox.style.display = 'none';
    }

    document.getElementById('letterModal').style.display = 'flex';
}

function closeLetterModal() {
    document.getElementById('letterModal').style.display = 'none';
}
</script>
</body>
</html>