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

// Determine Mode: Update vs Create
$is_update_mode = false;
$sponsor_id = ''; 

// Form field default values
$first_name = '';
$last_name = '';
$residence_country = '';
$language = '';
$dob = '';
$age = '';
$status = 'Active'; 
$email = ''; 

// Calculate the maximum allowed date of birth (Exactly 20 years ago from today)
$max_dob_allowed = date('Y-m-d', strtotime('-20 years'));

// 1. DYNAMIC SEARCH FUNCTIONALITY
$search_id = '';
if (isset($_GET['search_id']) && !empty(trim($_GET['search_id']))) {
    $search_id = trim($_GET['search_id']);
} elseif (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $search_id = trim($_GET['id']);
}

if (!empty($search_id)) {
    $is_update_mode = true;
    $sponsor_id = $search_id;

    $stmt = $conn->prepare("SELECT first_name, last_name, residence_country, language, dob, age, status FROM sponsors WHERE id = ?");
    $stmt->bind_param("s", $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $first_name = $row['first_name'];
        $last_name = $row['last_name'];
        $residence_country = $row['residence_country'];
        $language = $row['language'];
        $dob = $row['dob'];
        $age = $row['age'];
        $status = $row['status'];
        
        $message = "✓ Loaded Sponsor Profile record for ID: " . htmlspecialchars($sponsor_id);
        $message_class = "success-msg";
    } else {
        $message = "❌ Error: Sponsor account record [" . htmlspecialchars($sponsor_id) . "] not found.";
        $message_class = "error-msg";
        $is_update_mode = false; 
        $sponsor_id = '';
    }
    $stmt->close();
}

// 2. HANDLE FORM SUBMISSIONS (POST REQUESTS)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $residence_country = trim($_POST['residence_country'] ?? '');
    $language = trim($_POST['language'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $status = isset($_POST['status']) ? 'Active' : 'Inactive';
    $action_mode = isset($_POST['action_mode']) ? $_POST['action_mode'] : 'create';
    $form_sponsor_id = isset($_POST['sponsor_id']) ? trim($_POST['sponsor_id']) : '';

    // Field Validation Base Check
    if (!empty($first_name) && !empty($last_name) && !empty($residence_country) && !empty($language) && !empty($dob) && $age > 0) {
        
        if (strtotime($dob) > strtotime($max_dob_allowed)) {
            $message = "❌ Policy Error: Registered system sponsors must be at least 20 years of age.";
            $message_class = "error-msg";
            if ($action_mode === 'update') {
                $is_update_mode = true;
                $sponsor_id = $form_sponsor_id;
            }
        } else {
            if ($action_mode === 'update' && !empty($form_sponsor_id)) {
                // UPDATE SPONSOR RECORD
                $stmt = $conn->prepare("UPDATE sponsors SET first_name = ?, last_name = ?, residence_country = ?, language = ?, dob = ?, age = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sssssiss", $first_name, $last_name, $residence_country, $language, $dob, $age, $status, $form_sponsor_id);
                
                if ($stmt->execute()) {
                    $message = "✓ Sponsor profile records for [$form_sponsor_id] updated successfully!";
                    $message_class = "success-msg";
                    $sponsor_id = $form_sponsor_id;
                    $is_update_mode = true; 
                } else {
                    $message = "❌ Execution Error updating database row: " . $stmt->error;
                    $message_class = "error-msg";
                }
                $stmt->close();
                
            } else {
                // NEW SPONSOR CREATION
                if (empty($email) || empty($password)) {
                    $message = "❌ Registration Error: Email and Password fields are required for new login creation.";
                    $message_class = "error-msg";
                } else {
                    // Start Database Transaction
                    $conn->begin_transaction();

                    try {
                        // 1. Insert into sponsors table first to fetch the operational primary auto_increment internal key
                        $stmt = $conn->prepare("INSERT INTO sponsors (first_name, last_name, residence_country, language, dob, age, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssiss", $first_name, $last_name, $residence_country, $language, $dob, $age, $status);
                        $stmt->execute();
                        $last_inserted_num = $conn->insert_id;
                        $stmt->close();

                        // 2. Format the custom tracking sequence string
                        $assigned_id = "S" . str_pad($last_inserted_num, 9, "0", STR_PAD_LEFT);

                        // 3. Update the sponsor record with its official tracking code
                        $update_id_stmt = $conn->prepare("UPDATE sponsors SET id = ? WHERE internal_id = ?");
                        $update_id_stmt->bind_param("si", $assigned_id, $last_inserted_num);
                        $update_id_stmt->execute();
                        $update_id_stmt->close();

                        // 4. Create the System Login in the `users` table including user_type_id mapping
                        $username = "Sponsor" . $last_inserted_num; 
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $role = 'Sponsor';
                        $password_changed = 1; // Default password configured manually by creator, no forced update requirement

                        $user_stmt = $conn->prepare("INSERT INTO users (username, email, password, role, user_type_id, password_changed, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $user_stmt->bind_param("sssssi", $username, $email, $hashed_password, $role, $assigned_id, $password_changed);
                        $user_stmt->execute();
                        $user_id_generated = $conn->insert_id;
                        $user_stmt->close();

                        // 5. Connect the core authentication id row pointer directly inside the details entity tracking sheet
                        $link_stmt = $conn->prepare("UPDATE sponsors SET user_id = ? WHERE internal_id = ?");
                        $link_stmt->bind_param("ii", $user_id_generated, $last_inserted_num);
                        $link_stmt->execute();
                        $link_stmt->close();

                        // Commit Transaction if everything succeeds
                        $conn->commit();

                        $message = "✓ New Sponsor saved successfully! Assigned Alphanumeric ID: <strong>$assigned_id</strong>. Account login username created: <strong>$username</strong>";
                        $message_class = "success-msg";
                        
                        // Clear form out cleanly
                        $first_name = $last_name = $residence_country = $language = $dob = $age = $email = '';
                        $status = 'Active';

                    } catch (Exception $e) {
                        // Rollback state changes if an error is encountered
                        $conn->rollback();
                        $message = "❌ Database Transaction Failure: " . $e->getMessage();
                        $message_class = "error-msg";
                    }
                }
            }
        }
    } else {
        $message = "❌ Verification Warning: Please answer all interactive selection elements correctly.";
        $message_class = "error-msg";
        if ($action_mode === 'update') {
            $is_update_mode = true;
            $sponsor_id = $form_sponsor_id;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_update_mode ? 'Modify Sponsor Info' : 'Sponsor Enrollment Portal'; ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .admin-nav { background-color: #343a40; padding: 10px 20px; display: flex; gap: 15px; align-items: center; color: white; font-size: 14px; }
        .admin-nav a { color: #ffc107; text-decoration: none; font-weight: bold; padding: 5px 10px; border: 1px solid #ffc107; border-radius: 4px; }
        .admin-nav a:hover { background-color: #ffc107; color: black; }
        
        .container { max-width: 750px; background: white; margin: 30px auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        h2 { text-align: center; color: #0f766e; margin-bottom: 5px; }
        p.subtitle { text-align: center; color: #718096; margin-bottom: 25px; font-size: 14px; }
        
        .search-card { background: #f0f4f8; padding: 20px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #cbd5e0; }
        .search-form { display: flex; gap: 10px; }
        .search-form input { flex-grow: 1; padding: 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 15px; font-weight: bold; text-transform: uppercase; background-color: #ffffff; }
        .btn-search { width: auto; background-color: #0f766e; padding: 0 25px; color: white; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; margin-top: 0; }
        .btn-search:hover { background-color: #0d5c55; }
        .clear-btn { display: flex; align-items: center; justify-content: center; background-color: #e67e22; color: white; border: none; padding: 0 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; text-decoration: none; text-align: center; white-space: nowrap; }
        .clear-btn:hover { background-color: #d35400; }

        .section-divider { background-color: #f0f4f8; padding: 10px; font-weight: bold; color: #0f766e; margin: 25px 0 15px 0; border-left: 4px solid #0f766e; border-radius: 2px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: span 2; }
        label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 14px; background-color: #fff; }
        
        button { width: 100%; padding: 14px; background-color: #0f766e; border: none; color: white; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 25px; }
        button:hover { background-color: #0d5c55; }
        .btn-update { background-color: #28a745; }
        .btn-update:hover { background-color: #218838; }
        
        .msg-box { padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: bold; line-height: 1.6; }
        .success-msg { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-link { display: block; text-align: center; margin-top: 15px; color: #0f766e; text-decoration: none; font-size: 14px; font-weight: bold; }
        .nav-link:hover { text-decoration: underline; }
        .badge-id { display: inline-block; background-color: #e2f0d9; color: #215a1c; padding: 6px 14px; border-radius: 4px; font-family: monospace; font-size: 15px; margin-bottom: 20px; font-weight: bold; border: 1px solid #c3e6cb; }
        
        .toggle-container { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        .toggle-container input { width: auto; cursor: pointer; }
    </style>
    <script>
        function calculateAge() {
            const dobInput = document.getElementById('dob').value;
            if (!dobInput) {
                document.getElementById('age').value = '';
                return;
            }
            const birthDate = new Date(dobInput);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            document.getElementById('age').value = age >= 0 ? age : 0;
        }
    </script>
</head>
<body>

<?php if ($_SESSION['role'] === 'Admin'): ?>
    <div class="admin-nav">
        <strong>⚡ Admin View Mode:</strong> Go to: 
        <a href="admin_dashboard.php">Admin Panel</a>
        <a href="coordinator_dashboard.php">Coordinator Panel</a>
        <a href="sponsor_dashboard.php">Sponsor Portal</a>
    </div>
<?php endif; ?>

<div class="container">
    <h2><?php echo $is_update_mode ? 'Modify Sponsor Info' : 'Sponsor Enrollment Portal'; ?></h2>
    <p class="subtitle"><?php echo $is_update_mode ? 'Update tracking states for the designated sponsor profile record' : 'Register a new profile and create system gateway authentication details'; ?></p>

    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="search-card">
        <label for="search_id">Search Sponsor Profile by ID</label>
        <form action="register_sponsor.php" method="GET" class="search-form">
            <input type="text" id="search_id" name="search_id" value="<?php echo htmlspecialchars($sponsor_id); ?>" placeholder="e.g., S000000001">
            <button type="submit" class="btn-search">Search</button>
            <?php if ($is_update_mode): ?>
                <a href="register_sponsor.php" class="clear-btn">Clear / New Sponsor</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($is_update_mode): ?>
        <center><div class="badge-id">Mode: Editing Sponsor Record [<?php echo htmlspecialchars($sponsor_id); ?>]</div></center>
    <?php endif; ?>

    <form action="register_sponsor.php" method="POST">
        
        <input type="hidden" name="action_mode" value="<?php echo $is_update_mode ? 'update' : 'create'; ?>">
        <input type="hidden" name="sponsor_id" value="<?php echo htmlspecialchars($sponsor_id); ?>">
        
        <div class="section-divider">Sponsor Profile Information</div>
        <div class="form-grid">
            <div>
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
            </div>
            <div>
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
            </div>
            <div>
                <label for="dob">Date of Birth (Must be at least 20 years old)</label>
                <input type="date" id="dob" name="dob" max="<?php echo $max_dob_allowed; ?>" value="<?php echo htmlspecialchars($dob); ?>" onchange="calculateAge()" required>
            </div>
            <div>
                <label for="age">Calculated Age</label>
                <input type="number" id="age" name="age" value="<?php echo htmlspecialchars($age); ?>" readonly required>
            </div>
            <div>
                <label for="residence_country">Residence Country</label>
                <input type="text" id="residence_country" name="residence_country" value="<?php echo htmlspecialchars($residence_country); ?>" placeholder="e.g., United Kingdom, USA" required>
            </div>
            <div>
                <label for="language">Preferred Communication Language</label>
                <input type="text" id="language" name="language" value="<?php echo htmlspecialchars($language); ?>" placeholder="e.g., English, French" required>
            </div>
            <div class="full-width toggle-container">
                <input type="checkbox" id="status" name="status" <?php echo $status === 'Active' ? 'checked' : ''; ?>>
                <label for="status">Profile Access Status remains Active inside system lists</label>
            </div>
        </div>

        <?php if (!$is_update_mode): ?>
            <div class="section-divider">Portal Credentials Provisioning</div>
            <div class="form-grid">
                <div>
                    <label for="email">Sponsor Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                <div>
                    <label for="password">Account Gateway Access Password</label>
                    <input type="password" id="password" name="password" placeholder="Define secure entry key" required>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_update_mode): ?>
            <button type="submit" class="btn-update">Update Sponsor Details</button>
        <?php else: ?>
            <button type="submit">Submit and Register Sponsor</button>
        <?php endif; ?>
    </form>
    
    <a href="coordinator_dashboard.php" class="nav-link">← Return to Coordinator Panel Workspace</a>
</div>

</body>
</html>