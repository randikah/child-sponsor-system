<?php
session_start();
require_once 'db_connect.php';

// Access Control Security Guard: Ensure only logged-in children can execute view lookups
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Child') {
    header("Location: login.php");
    exit();
}

$message = '';
$message_class = '';
$sponsor_user_id = $_GET['id'] ?? '';
$child_id = $_SESSION['user_id']; // Logged in child identity code

// VERIFY SYSTEM RELATIONSHIP MATCH PARAMETERS FOR SECURITY
$verified = false;
$v_stmt = $conn->prepare("SELECT id FROM child_sponsor_matches WHERE child_id = ? AND sponsor_user_id = ? AND match_status = 'Active'");
$v_stmt->bind_param("si", $child_id, $sponsor_user_id);
$v_stmt->execute();
if ($v_stmt->get_result()->num_rows > 0) {
    $verified = true;
}
$v_stmt->close();

if (!$verified || empty($sponsor_user_id)) {
    die("❌ Unauthorized Access Block: You do not possess explicit permission matching parameters to open this profile window.");
}

// 1. HANDLE NEW CHILD RESPONSE DISPATCH (TEXT WRITING AND FILE UPLOADS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_letter') {
    $letter_text = trim($_POST['letter_content'] ?? '');
    $filename_db = '';
    $upload_ok = true;

    // Handle file upload if an attachment copy is uploaded (scanned physical drawings or letters)
    if (isset($_FILES['scanned_doc']) && $_FILES['scanned_doc']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['scanned_doc']['tmp_name'];
        $original_name = basename($_FILES['scanned_doc']['name']);
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_filename = "LETTER_CHILD_" . time() . "_" . uniqid() . "." . $file_ext;
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
        // Enforce structural parameters mapping child inputs (sender_role defaults explicitly to 'Child')
        // We set status to 'Pending' so a coordinator can review and translate it before the sponsor sees it.
        $ins_letter = $conn->prepare("INSERT INTO letters (sender_role, child_id, sponsor_user_id, letter_content, file_path, status) VALUES ('Child', ?, ?, ?, ?, 'Pending')");
        $ins_letter->bind_param("siss", $child_id, $sponsor_user_id, $letter_text, $filename_db); 
        
        if ($ins_letter->execute()) {
            $message = "✓ Response letter submitted successfully! It is now 'Pending' awaiting Coordinator review and translation translation.";
            $message_class = "success-msg";
        } else {
            $message = "❌ Database error: Unable to log mailing layout configuration: " . $conn->error;
            $message_class = "error-msg";
        }
        $ins_letter->close();
    }
}

// 2. FETCH DETAILED USER ACCOUNT SPONSOR METRICS
$sponsor_data = null;
$stmt = $conn->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $sponsor_user_id);
$stmt->execute();
$sponsor_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. DYNAMIC INBOUND QUERY: Load all historical outbound entries matching this child (Your own letters)
$my_letters = [];
$my_stmt = $conn->prepare("SELECT * FROM letters WHERE child_id = ? AND sender_role = 'Child' ORDER BY created_at DESC");
$my_stmt->bind_param("s", $child_id);
$my_stmt->execute();
$my_letters = $my_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$my_stmt->close();

// 4. DYNAMIC OUTBOUND QUERY: Load only approved incoming sponsor letters matching this child
$received_letters = [];
$rec_stmt = $conn->prepare("SELECT * FROM letters WHERE child_id = ? AND sender_role = 'Sponsor' AND status = 'Approved' ORDER BY created_at DESC");
$rec_stmt->bind_param("s", $child_id);
$rec_stmt->execute();
$received_letters = $rec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rec_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correspondence Hub - Sponsor Portal</title>
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
    <h1>🤝 Sponsor Correspondence Portal</h1>
    <a href="child_dashboard.php">← Back to Dashboard</a>
</div>

<div class="container">
    <a href="child_dashboard.php" class="back-link">← Return to Main Dashboard Panel</a>

    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="workspace-grid">
        <div class="column-card">
            <div class="card-title">My Assigned Sponsor Info</div>
            <div class="profile-row"><span class="profile-label">Sponsor Account Name:</span><span><?php echo htmlspecialchars($sponsor_data['username'] ?? 'N/A'); ?></span></div>
            <div class="profile-row"><span class="profile-label">Email Context Reference:</span><span><?php echo htmlspecialchars($sponsor_data['email'] ?? 'N/A'); ?></span></div>
            <div class="profile-row"><span class="profile-label">Connection Date:</span><span><?php echo date("M d, Y", strtotime($sponsor_data['created_at'])); ?></span></div>
        </div>

        <div class="column-card">
            <div class="card-title">Write a Response Letter to Sponsor</div>
            <form action="view_sponsor_details.php?id=<?php echo urlencode($sponsor_user_id); ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="send_letter">
                
                <label for="letter_content">Type Letter Message Body (will be reviewed by coordinator)</label>
                <textarea id="letter_content" name="letter_content" placeholder="Type your thank you words or updates to your sponsor here..." required></textarea>
                
                <label for="scanned_doc">Attach Scanned Handwritten Copy/Drawing (Optional)</label>
                <input type="file" id="scanned_doc" name="scanned_doc" accept=".pdf, .jpg, .jpeg, .png">
                <small style="color: #718096; display: block; margin-top: 4px;">Supported extensions: PDF, PNG, JPG up to 5MB size.</small>
                
                <button type="submit" class="btn-submit">Dispatch Letter Entry</button>
            </form>
        </div>
    </div>

    <div class="full-width-card">
        <div class="card-title">My Dispatched Letters History & Review Status</div>
        <table class="history-table">
            <thead>
                <tr>
                    <th>Date Created</th>
                    <th>Document Preview</th>
                    <th>Approval Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($my_letters) > 0): 
                    foreach ($my_letters as $let): 
                        $status = $let['status'] ?? 'Pending';
                        $badge_class = 'badge-pending';
                        if ($status === 'Approved') $badge_class = 'badge-approved';
                        if ($status === 'Rejected') $badge_class = 'badge-rejected';
                ?>
                    <tr>
                        <td><strong><?php echo date("M d, Y", strtotime($let['created_at'])); ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($let['letter_content'] ?? '', 0, 50)) . (strlen($let['letter_content'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                            <?php if ($status === 'Rejected' && !empty($let['coordinator_comment'])): ?>
                                <div class="comment-block">💬 <strong>Coordinator Note:</strong> <?php echo htmlspecialchars($let['coordinator_comment']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-action" onclick="openLetterModal('My Dispatched Letter', <?php echo htmlspecialchars(json_encode($let['letter_content'] ?? '')); ?>, '<?php echo $status; ?>', <?php echo htmlspecialchars(json_encode($let['coordinator_comment'] ?? '')); ?>, '<?php echo htmlspecialchars($let['file_path'] ?? ''); ?>')">Open Document</button>
                        </td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr><td colspan="4" style="text-align: center; color: #a0aec0;">You have not logged any letters to this sponsor profile yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="full-width-card">
        <div class="card-title">Received Sponsor Letters Log</div>
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
                <?php if (count($received_letters) > 0): 
                    foreach ($received_letters as $let): 
                ?>
                    <tr>
                        <td><strong><?php echo date("M d, Y", strtotime($let['created_at'])); ?></strong></td>
                        <td><span style="color: #0f766e; font-weight: bold;">📥 Beneficiary Sponsor</span></td>
                        <td><?php echo htmlspecialchars(substr($let['letter_content'] ?? '', 0, 50)) . (strlen($let['letter_content'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td>
                            <button class="btn-action" onclick="openLetterModal('Letter from Sponsor', <?php echo htmlspecialchars(json_encode($let['letter_content'] ?? '')); ?>, 'Approved', '', '<?php echo htmlspecialchars($let['file_path'] ?? ''); ?>')">Read Mail Entry</button>
                        </td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr><td colspan="4" style="text-align: center; color: #a0aec0;">No cleared letters received from your sponsor yet.</td></tr>
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
        
        <div id="modalAttachmentBlock" style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #e2e8f0; display: none;">
            <label style="margin-top: 0;">Associated Media Attachment:</label>
            <div id="modalFileLinkContainer"></div>
        </div>

        <div class="modal-footer">
            <button class="btn-submit" style="width: auto; margin-top: 0; padding: 8px 20px;" onclick="closeLetterModal()">Close and Go Back</button>
        </div>
    </div>
</div>

<script>
function openLetterModal(title, content, status, comment, filePath) {
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

    const attachmentBlock = document.getElementById('modalAttachmentBlock');
    const linkContainer = document.getElementById('modalFileLinkContainer');
    
    if (filePath && filePath.trim() !== '') {
        attachmentBlock.style.display = 'block';
        linkContainer.innerHTML = `
            <a href="${filePath}#toolbar=0&navpanes=0" 
               style="color: #2b6cb0; font-weight: 600; text-decoration: none; display: inline-block; margin-top: 5px;" 
               onclick="window.open(this.href, 'targetWindow', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=800,height=600'); return false;">
               📄 View Attachment Only
            </a>`;
    } else {
        attachmentBlock.style.display = 'none';
        linkContainer.innerHTML = '';
    }

    document.getElementById('letterModal').style.display = 'flex';
}

function closeLetterModal() {
    document.getElementById('letterModal').style.display = 'none';
}
</script>
</body>
</html>