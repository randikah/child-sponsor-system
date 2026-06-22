<?php
session_start();
require_once 'db_connect.php';

// Access Control: Only Coordinator/Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Coordinator', 'Admin'])) {
    header("Location: login.php");
    exit();
}

// Handle Mediation Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mediation_action'])) {
    $letter_id = $_POST['letter_id'];
    $status = $_POST['mediation_action']; // 'Approved' or 'Rejected'
    $comment = $_POST['coordinator_comment'] ?? '';

    $stmt = $conn->prepare("UPDATE letters SET status = ?, coordinator_comment = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $comment, $letter_id);
    $stmt->execute();
    $stmt->close();
    header("Location: coordinator_mediation.php?msg=success");
    exit();
}

// Fetch pending letters
$query = "SELECT l.*, 
                 s.first_name AS s_first, s.last_name AS s_last, s.email AS s_email,l.sponsor_user_id,
                 c.first_name AS c_first, c.last_name AS c_last
          FROM letters l
          LEFT JOIN sponsors s ON l.sponsor_user_id = s.id
          LEFT JOIN child c ON l.child_id = c.user_id
          WHERE l.status = 'Pending'
          ORDER BY l.created_at DESC";
$pending_letters = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mediation Portal</title>
    <link rel="stylesheet" href="style.css"> <style>
        /* Ensuring consistency with existing dashboard  */
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; }
        .navbar { background-color: #2b6cb0; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .full-width-card { background: #fff; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-title { font-size: 18px; font-weight: bold; color: #2b6cb0; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 8px; }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { background-color: #f8fafc; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        .history-table td { padding: 12px; border-bottom: 1px solid #edf2f7; }
        .btn-action { padding: 6px 12px; background: #2b6cb0; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .modal-mask { display: none; position: fixed; z-index: 9999; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-body { background: white; padding: 25px; border-radius: 8px; width: 90%; max-width: 500px; }

        /* Shared Button Styles */
            .btn-modal {
                padding: 10px 20px;
                font-size: 14px;
                font-family: 'Segoe UI', Arial, sans-serif;
                font-weight: 600;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: 0.2s;
                margin: 5px;
                min-width: 90px;
            }

            /* Specific Colors */
            .btn-approve { background-color: #2b6cb0; color: white; }
            .btn-approve:hover { background-color: #0d5c55; }

            .btn-reject { background-color: #dc2626; color: white; }
            .btn-reject:hover { background-color: #b91c1c; }

            .btn-close { background-color: #718096; color: white; }
            .btn-close:hover { background-color: #4a5568; }

            /* Override the previous absolute close button if you still want it at the top */
            .close-btn {
                position: absolute;
                top: 10px;
                right: 15px;
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #555;
            }
            .modal-body { 
                background: white; 
                padding: 25px; 
                border-radius: 8px; 
                width: 90%; 
                max-width: 500px; 
                position: relative; /* CRITICAL: keeps content inside */
            }
    </style>
</head>
<body>

<div class="navbar"><h1>⚖️ Mediation Portal</h1><a href="coordinator_dashboard.php" style="color:white;"><-Back </a></div>

<div class="container">
    <div class="full-width-card">
        <div class="card-title">Pending: Sponsor to Child Correspondence</div>
        <table class="history-table">
            <thead><tr><th>Sponsor ID</th><th>Sponsor Name</th><th>Child ID</th><th>Child Name</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($pending_letters as $let): if($let['sender_role'] == 'Sponsor'): ?>
                <tr>
                    <td><?php echo $let['sponsor_user_id']; ?></td>
                    <td><?php echo "{$let['s_first']} {$let['s_last']}"; ?></td>
                    <td><?php echo $let['child_id']; ?></td>
                    <td><?php echo "{$let['c_first']} {$let['c_last']}"; ?></td>
                   <!-- <td><?php echo htmlspecialchars(substr($let['letter_content'], 0, 40)); ?>...</td>-->
                    <td><button class="btn-action" onclick="openModal(<?php echo htmlspecialchars(json_encode($let)); ?>)">Review</button></td>
                </tr>
                <?php endif; endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="full-width-card">
        <div class="card-title">Pending: Child to Sponsor Correspondence</div>
        <table class="history-table">
            <thead><tr><th>Child ID</th><th>Child Name</th><th>Assoc. Sponsor ID</th><th>Sponsor Name</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($pending_letters as $let): if($let['sender_role'] == 'Child'): ?>
                <tr>
                    <td><?php echo $let['child_id']; ?></td>
                    <td><?php echo "{$let['c_first']} {$let['c_last']}"; ?></td>
                    <td><?php echo $let['sponsor_user_id']; ?></td>
                    <td><?php echo "{$let['s_first']} {$let['s_last']}"; ?></td>
                   <!-- <td><?php echo htmlspecialchars(substr($let['letter_content'], 0, 40)); ?>...</td>-->
                    <td><button class="btn-action" onclick="openModal(<?php echo htmlspecialchars(json_encode($let)); ?>)">Review</button></td>
                </tr>
                <?php endif; endforeach; ?>
            </tbody>
        </table>
        </div>
        <div style="text-align: center; margin: 20px 0;">
            <a href="coordinator_dashboard.php" class="nav-link">← Return to Coordinator Panel Workspace</a>
        </div>
    </div>

        <div id="mediationModal" class="modal-mask">
            <div class="modal-body">

                <h3>Review Correspondence</h3>
                <p id="modalContent" style="white-space:pre-line;"></p>
                <div id="fileArea"></div>
                    <form method="POST">
                    <input type="hidden" name="letter_id" id="letter_id">
                    <textarea name="coordinator_comment" style="width:100%; margin: 10px 0;" placeholder="Add rejection note here..."></textarea>
                    <button type="submit" name="mediation_action" value="Approved" class="btn-modal btn-approve">Approve</button>
                    <button type="submit" name="mediation_action" value="Rejected" class="btn-modal btn-reject">Reject</button>
                    <button type="button" class="btn-modal btn-close" onclick="closeModal()">Close</button>
        </form>
        
    </div>
    
</div>

<script>
    // 1. Function to open the modal and populate data
    function openModal(data) {
        document.getElementById('letter_id').value = data.id;
        document.getElementById('modalContent').innerText = data.letter_content;
        
        // Add file link if exists
        const fileArea = document.getElementById('fileArea');
        if (data.file_path && data.file_path !== "") {
           // fileArea.innerHTML = `<p><a href="${data.file_path}" target="_blank" style="color:#2b6cb0; font-weight:bold; text-decoration:none;">📄 View Attachment</a></p>`;

            fileArea.innerHTML = `<p><a href="<?php echo htmlspecialchars($let['file_path']); ?>#toolbar=0&navpanes=0" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   onclick="window.open(this.href, 'targetWindow', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=800,height=600'); return false;" 
                                   style="color: #2b6cb0; font-weight:600; text-decoration:none;">
                                   📄 View File Only
                                </a></p>`;


        } else {
            fileArea.innerHTML = '';
        }
        
        // Show the modal
        document.getElementById('mediationModal').style.display = 'flex';
    }

    // 2. Function to close the modal
    function closeModal() {
        document.getElementById('mediationModal').style.display = 'none';
        // Clear the comment box when closing
        document.querySelector('textarea[name="coordinator_comment"]').value = '';
    }

    // 3. Close modal if user clicks outside the white box
    window.onclick = function(event) {
        const modal = document.getElementById('mediationModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // 4. Form Validation for Rejection
    // We attach the event listener to the form itself to catch the submit action
    document.querySelector('form').addEventListener('submit', function(e) {
        // Find which button was clicked
        const submitter = e.submitter;
        
        if (submitter && submitter.value === "Rejected") {
            const comment = document.querySelector('textarea[name="coordinator_comment"]').value;
            if (comment.trim() === "") {
                alert("Please provide a reason for rejection.");
                e.preventDefault(); // Stop the form from submitting
            }
        }
    });
</script>
</body>
</html>