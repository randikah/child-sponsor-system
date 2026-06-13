<?php
session_start();
require_once 'db_connect.php';

$message = '';
$message_class = '';

// Determine Mode: Update vs Create
$is_update_mode = false;
$sponsor_id = ''; // Now acts as a VARCHAR string tracker (e.g., S000000001)

// Form field default values
$first_name = '';
$last_name = '';
$residence_country = '';
$language = '';
$dob = '';
$age = '';
$status = 'Active'; // Default status value

// Calculate the maximum allowed date of birth (Exactly 20 years ago from today)
$max_dob_allowed = date('Y-m-d', strtotime('-20 years'));

// 1. DYNAMIC SEARCH FUNCTIONALITY (Triggered via Search GET request or URL parameter)
$search_id = '';
if (isset($_GET['search_id']) && !empty(trim($_GET['search_id']))) {
    $search_id = trim($_GET['search_id']);
} elseif (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $search_id = trim($_GET['id']);
}

if (!empty($search_id)) {
    $is_update_mode = true;
    $sponsor_id = $search_id;

    // Fetch existing records using the VARCHAR id (including status column)
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
        
        $message = "✓ Loaded Sponsor Profile record for ID: $sponsor_id";
        $message_class = "success-msg";
    } else {
        $message = "❌ Error: Sponsor account record [" . htmlspecialchars($sponsor_id) . "] not found.";
        $message_class = "error-msg";
        $is_update_mode = false; // Fallback to creation mode if record doesn't exist
        $sponsor_id = '';
    }
    $stmt->close();
}

// 2. HANDLE FORM SUBMISSIONS (POST REQUESTS)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Using null coalescing assignment operators to permanently eliminate "Undefined array key" warnings
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $residence_country = trim($_POST['residence_country'] ?? '');
    $language = trim($_POST['language'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    
    // Check toggle switch state: if checked = Active, if unchecked/unset = Inactive
    $status = isset($_POST['status']) ? 'Active' : 'Inactive';
    
    $action_mode = isset($_POST['action_mode']) ? $_POST['action_mode'] : 'create';
    $form_sponsor_id = isset($_POST['sponsor_id']) ? trim($_POST['sponsor_id']) : '';

    // Form inputs validation execution
    if (!empty($first_name) && !empty($last_name) && !empty($residence_country) && !empty($language) && !empty($dob) && $age > 0) {
        
        // Backend verification check ensuring selected date is strictly greater than 20 years old
        if (strtotime($dob) > strtotime($max_dob_allowed)) {
            $message = "❌ Policy Error: Registered system sponsors must be at least 20 years of age.";
            $message_class = "error-msg";
            if ($action_mode === 'update') {
                $is_update_mode = true;
                $sponsor_id = $form_sponsor_id;
            }
        } else {
            if ($action_mode === 'update' && !empty($form_sponsor_id)) {
                // EXECUTE RECORD UPDATE USING ALPHANUMERIC ID WITH STATUS MATCH INJECTION
                $stmt = $conn->prepare("UPDATE sponsors SET first_name = ?, last_name = ?, residence_country = ?, language = ?, dob = ?, age = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sssssiss", $first_name, $last_name, $residence_country, $language, $dob, $age, $status, $form_sponsor_id);
                
                if ($stmt->execute()) {
                    $message = "✓ Sponsor profile records for [$form_sponsor_id] updated successfully!";
                    $message_class = "success-msg";
                    $sponsor_id = $form_sponsor_id;
                    $is_update_mode = true; // Maintain persistent edit-state view
                } else {
                    $message = "❌ Execution Error updating database row: " . $stmt->error;
                    $message_class = "error-msg";
                }
                $stmt->close();
                
            } else {
                // EXECUTE NEW SPONSOR INSERTION WITH STATUS COLUMN VALUE
                $stmt = $conn->prepare("INSERT INTO sponsors (first_name, last_name, residence_country, language, dob, age, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssis", $first_name, $last_name, $residence_country, $language, $dob, $age, $status);

                if ($stmt->execute()) {
                    // Get the generated numeric primary key ID
                    $last_inserted_num = $conn->insert_id;

                    // Query the database to retrieve the generated alphanumeric string directly
                    $fetch_stmt = $conn->prepare("SELECT id FROM sponsors WHERE internal_id = ?");
                    $fetch_stmt->bind_param("i", $last_inserted_num);
                    $fetch_stmt->execute();
                    $res = $fetch_stmt->get_result();
                    $row = $res->fetch_assoc();
                    $assigned_id = $row['id'] ?? "S" . str_pad($last_inserted_num, 9, "0", STR_PAD_LEFT);
                    $fetch_stmt->close();

                    $message = "✓ New Sponsor saved successfully! Assigned Alphanumeric ID: <strong>$assigned_id</strong>";
                    $message_class = "success-msg";
                    
                    // Flush variables out cleanly to prepare interface form for empty input sequence
                    $first_name = $last_name = $residence_country = $language = $dob = $age = '';
                    $status = 'Active';
                } else {
                    $message = "❌ Database insert processing failure: " . $stmt->error;
                    $message_class = "error-msg";
                }
                $stmt->close();
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
    <title><?php echo $is_update_mode ? 'Modify Sponsor Info' : 'Add New Sponsor'; ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; display: flex; flex-direction: column; }
        
        .container { max-width: 750px; background: white; margin: 30px auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        h2 { text-align: center; color: #1e3a8a; margin-bottom: 5px; }
        p.subtitle { text-align: center; color: #718096; margin-bottom: 25px; font-size: 14px; }
        
        .search-card { background: #f0f4f8; padding: 20px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #cbd5e0; }
        .search-form { display: flex; gap: 10px; }
        .search-form input { flex-grow: 1; padding: 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 15px; font-weight: bold; text-transform: uppercase; background-color: #ffffff; }
        .btn-search { width: auto; background-color: #2b6cb0; padding: 0 25px; color: white; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
        .btn-search:hover { background-color: #2c5282; }
        .clear-btn { display: flex; align-items: center; justify-content: center; background-color: #e67e22; color: white; border: none; padding: 0 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; text-decoration: none; text-align: center; white-space: nowrap; }
        .clear-btn:hover { background-color: #d35400; }

        .section-divider { background-color: #f0f4f8; padding: 10px; font-weight: bold; color: #1e3a8a; margin: 25px 0 15px 0; border-left: 4px solid #1e3a8a; border-radius: 2px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: span 2; }
        label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 14px; background-color: #fff; }
        input:focus, select:focus { border-color: #1e3a8a; outline: none; box-shadow: 0 0 4px rgba(30, 58, 138, 0.2); }
        
        /* Premium CSS Switch Slide Bar Component Styles */
        .status-container { display: flex; align-items: center; justify-content: space-between; background-color: #f8fafc; padding: 12px 18px; border: 1px solid #cbd5e0; border-radius: 6px; }
        .status-label-group { display: flex; flex-direction: column; }
        .status-title { font-weight: bold; color: #2d3748; font-size: 14px; }
        .status-desc { font-size: 12px; color: #718096; margin-top: 2px; }
        
        .switch { position: relative; display: inline-block; width: 64px; height: 32px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #dc3545; transition: .3s ease; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 24px; width: 24px; left: 4px; bottom: 4px; background-color: white; transition: .3s ease; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: #28a745; }
        input:checked + .slider:before { transform: translateX(32px); }
        
        .status-badge { font-weight: bold; font-size: 13px; min-width: 65px; text-align: right; display: inline-block; text-transform: uppercase; transition: color 0.2s ease; }
        .status-badge.active { color: #28a745; }
        .status-badge.inactive { color: #dc3545; }

        .btn-submit { width: 100%; padding: 14px; background-color: #1e3a8a; border: none; color: white; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 25px; }
        .btn-submit:hover { background-color: #1d4ed8; }
        .btn-submit btn-update { background-color: #28a745; }
        .btn-submit btn-update:hover { background-color: #218838; }
        
        .msg-box { padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: bold; }
        .success-msg { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-link { display: block; text-align: center; margin-top: 15px; color: #1e3a8a; text-decoration: none; font-size: 14px; font-weight: bold; }
        .nav-link:hover { text-decoration: underline; }
        .badge-id { display: inline-block; background-color: #e2f0d9; color: #215a1c; padding: 6px 14px; border-radius: 4px; font-family: monospace; font-size: 15px; margin-bottom: 20px; font-weight: bold; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

<div class="container">
    <h2><?php echo $is_update_mode ? 'Modify Sponsor Profile' : 'Sponsor Enrollment Portal'; ?></h2>
    <p class="subtitle"><?php echo $is_update_mode ? 'Update administrative details for the registered user account record' : 'Register a new alphanumeric system sponsor entry to database'; ?></p>

    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="search-card">
        <label for="search_id">Enter Sponsor Registration ID</label>
        <form action="register_sponsor.php" method="GET" class="search-form">
            <input type="text" id="search_id" name="search_id" value="<?php echo htmlspecialchars($sponsor_id); ?>" placeholder="e.g., S000000001" required>
            <button type="submit" class="btn-search">Search Record</button>
            <?php if ($is_update_mode): ?>
                <a href="register_sponsor.php" class="clear-btn">Clear Form / New Sponsor</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($is_update_mode): ?>
        <center><div class="badge-id">Mode: Editing Sponsor Record [<?php echo htmlspecialchars($sponsor_id); ?>]</div></center>
    <?php endif; ?>

    <form action="register_sponsor.php<?php echo $is_update_mode ? '?id='.urlencode($sponsor_id) : ''; ?>" method="POST" id="sponsorForm">
        
        <input type="hidden" name="action_mode" value="<?php echo $is_update_mode ? 'update' : 'create'; ?>">
        <input type="hidden" name="sponsor_id" value="<?php echo htmlspecialchars($sponsor_id); ?>">

        <div class="section-divider">Sponsor Profile Information</div>
        <div class="form-grid">
            <div>
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" placeholder="e.g., Jane" required>
            </div>
            <div>
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" placeholder="e.g., Smith" required>
            </div>
            
            <div class="full-width">
                <label for="residence_country">Country of Residence (Auto-Search Combobox)</label>
                <input type="text" id="residence_country" name="residence_country" list="countries" value="<?php echo htmlspecialchars($residence_country); ?>" placeholder="Type to search and filter country selection..." required>
                <datalist id="countries">
                    <option value="Australia">
                    <option value="Canada">
                    <option value="France">
                    <option value="Germany">
                    <option value="India">
                    <option value="Italy">
                    <option value="Japan">
                    <option value="New Zealand">
                    <option value="Singapore">
                    <option value="Sri Lanka">
                    <option value="United Kingdom">
                    <option value="United States">
                </datalist>
            </div>

            <div class="full-width">
                <label for="language">Primary Language Selection</label>
                <select id="language" name="language" required>
                    <option value="" disabled <?php echo empty($language) ? 'selected' : ''; ?>>-- Choose Option --</option>
                    <option value="English" <?php echo $language == 'English' ? 'selected' : ''; ?>>English</option>
                    <option value="Sinhala" <?php echo $language == 'Sinhala' ? 'selected' : ''; ?>>Sinhala</option>
                    <option value="Tamil" <?php echo $language == 'Tamil' ? 'selected' : ''; ?>>Tamil</option>
                    <option value="French" <?php echo $language == 'French' ? 'selected' : ''; ?>>French</option>
                    <option value="Spanish" <?php echo $language == 'Spanish' ? 'selected' : ''; ?>>Spanish</option>
                </select>
            </div>

            <div>
                <label for="dob">Date of Birth (Minimum 20 Years Old Required)</label>
                <input type="date" id="dob" name="dob" max="<?php echo $max_dob_allowed; ?>" value="<?php echo htmlspecialchars($dob); ?>" required>
            </div>
            <div>
                <label for="age">Age Metric</label>
                <input type="number" id="age" name="age" min="20" max="120" value="<?php echo htmlspecialchars($age); ?>" placeholder="e.g., 34" required>
            </div>

            <div class="full-width" style="margin-top: 10px;">
                <label>Sponsor Lifecycle System Status</label>
                <div class="status-container">
                    <div class="status-label-group">
                        <span class="status-title">Account Allocation Settings</span>
                        <span class="status-desc">Determine if this entity is visible inside active funding registries</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span id="statusTxt" class="status-badge <?php echo ($status === 'Active') ? 'active' : 'inactive'; ?>">
                            <?php echo $status; ?>
                        </span>
                        <label class="switch" style="margin: 0;">
                            <input type="checkbox" id="status_toggle" name="status" value="Active" <?php echo ($status === 'Active') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_update_mode): ?>
            <button type="submit" class="btn-submit btn-update" style="background-color: #28a745;">Update Sponsor Record</button>
        <?php else: ?>
            <button type="submit" class="btn-submit">Create New Sponsor</button>
        <?php endif; ?>
    </form>
    
    <a href="coordinator_dashboard.php" class="nav-link">← Return to Coordinator Panel Workspace</a>
</div>

<script>
// Client-side control to handle real-time UI text update of the slide toggle status bar
document.getElementById('status_toggle').addEventListener('change', function() {
    var txtEl = document.getElementById('statusTxt');
    if(this.checked) {
        txtEl.textContent = 'Active';
        txtEl.className = 'status-badge active';
    } else {
        txtEl.textContent = 'Inactive';
        txtEl.className = 'status-badge inactive';
    }
});

// Client-side integration to auto-calculate/verify age field matches selected DOB year metric
document.getElementById('dob').addEventListener('change', function() {
    var birthDate = new Date(this.value);
    if(isNaN(birthDate)) return;
    
    var today = new Date();
    var age = today.getFullYear() - birthDate.getFullYear();
    var m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    // Auto-update age input field to assist the administrator
    if(age >= 20) {
        document.getElementById('age').value = age;
    } else {
        document.getElementById('age').value = '';
    }
});

// Secondary security verification handling form submission
document.getElementById('sponsorForm').addEventListener('submit', function(e) {
    var dobInput = document.getElementById('dob').value;
    var maxAllowed = "<?php echo $max_dob_allowed; ?>";
    
    if (dobInput > maxAllowed) {
        e.preventDefault();
        alert("Registration Rejected: Sponsor must be at least 20 years old.");
    }
});
</script>
</body>
</html>