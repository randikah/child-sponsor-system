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
$child_id = ''; 

// Form field default values to initialize variables cleanly
$first_name = '';
$last_name = '';
$dob = '';
$age = '';

$mother_first_name = '';
$mother_last_name = '';
$mother_dob = '';
$mother_age = '';
$mother_occupation = '';

$father_first_name = '';
$father_last_name = '';
$father_dob = '';
$father_age = '';

$residence_country = 'Sri Lanka';
$religion = '';
$nationality = 'Sri Lankan';
$language = '';

$education_level = '';
$health_status = '';

// 1. DYNAMIC SEARCH FUNCTIONALITY (Updated to use user_id)
$search_id = '';
if (isset($_GET['search_id']) && !empty(trim($_GET['search_id']))) {
    $search_id = trim($_GET['search_id']);
} elseif (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $search_id = trim($_GET['id']);
}

if (!empty($search_id)) {
    $is_update_mode = true;
    $child_id = $search_id;

    $stmt = $conn->prepare("SELECT * FROM child WHERE user_id = ?");
    $stmt->bind_param("s", $child_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        $first_name = $row['first_name'];
        $last_name = $row['last_name'];
        $dob = $row['dob'];
        $age = $row['age'];
        
        $mother_first_name = $row['mother_first_name'];
        $mother_last_name = $row['mother_last_name'];
        $mother_dob = $row['mother_dob'];
        $mother_age = $row['mother_age'];
        $mother_occupation = $row['mother_occupation'];
        
        $father_first_name = $row['father_first_name'] ?? '';
        $father_last_name = $row['father_last_name'] ?? '';
        $father_dob = (!empty($row['father_dob']) && $row['father_dob'] !== '0000-00-00') ? $row['father_dob'] : '';
        $father_age = $row['father_age'] ?? '';
        
        $residence_country = $row['residence_country'];
        $religion = $row['religion'];
        $nationality = $row['nationality'];
        $language = $row['language'];
        
        $education_level = $row['education_level'];
        $health_status = $row['health_status'];
        
        $message = "✓ Loaded Child Profile record for ID: " . htmlspecialchars($child_id);
        $message_class = "success-msg";
    } else {
        $message = "❌ Error: Child account record [" . htmlspecialchars($child_id) . "] not found.";
        $message_class = "error-msg";
        $is_update_mode = false; 
        $child_id = '';
    }
    $stmt->close();
}

// 2. HANDLE FORM SUBMISSIONS (POST REQUESTS)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $age = intval($_POST['age'] ?? 0);
    
    $mother_first_name = trim($_POST['mother_first_name'] ?? '');
    $mother_last_name = trim($_POST['mother_last_name'] ?? '');
    $mother_dob = $_POST['mother_dob'] ?? '';
    $mother_age = intval($_POST['mother_age'] ?? 0);
    $mother_occupation = trim($_POST['mother_occupation'] ?? '');
    
    $father_first_name = (!isset($_POST['father_first_name']) || trim($_POST['father_first_name']) === '') ? null : trim($_POST['father_first_name']);
    $father_last_name  = (!isset($_POST['father_last_name']) || trim($_POST['father_last_name']) === '') ? null : trim($_POST['father_last_name']);
    $father_dob        = (!isset($_POST['father_dob']) || trim($_POST['father_dob']) === '') ? null : $_POST['father_dob'];
    $father_age        = (!isset($_POST['father_age']) || trim($_POST['father_age']) === '') ? null : intval($_POST['father_age']);
    
    $residence_country = isset($_POST['residence_country']) ? $_POST['residence_country'] : 'Sri Lanka';
    $religion = $_POST['religion'] ?? '';
    $nationality = isset($_POST['nationality']) ? $_POST['nationality'] : 'Sri Lankan';
    $language = $_POST['language'] ?? '';
    
    $education_level = trim($_POST['education_level'] ?? '');
    $health_status = trim($_POST['health_status'] ?? '');
    
    $action_mode = $_POST['action_mode'] ?? 'create';
    $form_child_id = trim($_POST['child_id'] ?? '');
    $registered_by = $_SESSION['user_id'];

    $birthday = new DateTime($dob);
    $now = new DateTime();
    $calculated_age = $now->diff($birthday)->y;

    if ($calculated_age >= 20) {
        $message = "❌ Error: Child must be below 20 years of age.";
        $message_class = "error-msg";
        if ($action_mode === 'update') {
            $is_update_mode = true;
            $child_id = $form_child_id;
        }
    } else {
        if ($action_mode === 'update' && !empty($form_child_id)) {
            // EXECUTE RECORD UPDATE (Updated WHERE clause to user_id)
            $stmt = $conn->prepare("UPDATE child SET first_name = ?, last_name = ?, dob = ?, age = ?, mother_first_name = ?, mother_last_name = ?, mother_dob = ?, mother_age = ?, mother_occupation = ?, father_first_name = ?, father_last_name = ?, father_dob = ?, father_age = ?, residence_country = ?, religion = ?, nationality = ?, language = ?, education_level = ?, health_status = ? WHERE user_id = ?");
            
            $stmt->bind_param("sssisssisssissssssss", 
                $first_name, $last_name, $dob, $age,
                $mother_first_name, $mother_last_name, $mother_dob, $mother_age, $mother_occupation,
                $father_first_name, $father_last_name, $father_dob, $father_age,
                $residence_country, $religion, $nationality, $language,
                $education_level, $health_status, $form_child_id
            );

            if ($stmt->execute()) {
                $message = "✓ Child profile records for [$form_child_id] updated successfully!";
                $message_class = "success-msg";
                $child_id = $form_child_id;
                $is_update_mode = true;
            } else {
                $message = "❌ System execution error on update: " . $stmt->error;
                $message_class = "error-msg";
                $is_update_mode = true;
                $child_id = $form_child_id;
            }
            $stmt->close();

        } else {
            // START TRANSACTION TO ENSURE BOTH INSERTS ARE SUCCESSFUL
            $conn->begin_transaction();

            try {
                // 1. EXECUTE NEW CHILD CREATION WITH ALPHANUMERIC GENERATOR (Updated query column to user_id)
                $id_query = "SELECT MAX(CAST(SUBSTRING(user_id, 2) AS UNSIGNED)) as max_id FROM child";
                $id_result = $conn->query($id_query);
                $row = $id_result->fetch_assoc();
                $next_num = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;
                
                $padded_number = str_pad($next_num, 9, "0", STR_PAD_LEFT);
                $child_custom_id = "C" . $padded_number;

                // Updated column listing from 'id' to 'user_id'
                $stmt = $conn->prepare("INSERT INTO child (user_id, first_name, last_name, dob, age, mother_first_name, mother_last_name, mother_dob, mother_age, mother_occupation, father_first_name, father_last_name, father_dob, father_age, residence_country, religion, nationality, language, education_level, health_status, registered_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("ssssisssisssisssssssi", 
                    $child_custom_id, $first_name, $last_name, $dob, $age,
                    $mother_first_name, $mother_last_name, $mother_dob, $mother_age, $mother_occupation,
                    $father_first_name, $father_last_name, $father_dob, $father_age,
                    $residence_country, $religion, $nationality, $language,
                    $education_level, $health_status, $registered_by
                );
                $stmt->execute();
                $stmt->close();

                // 2. AUTOMATICALLY CREATE SYSTEM USER ACCOUNT FOR THE CHILD
                $generated_username = trim($first_name) . $next_num; // e.g., Randika1
                $generated_email = strtolower(trim($first_name)) . "." . $child_custom_id . "@example.com"; 
                $default_password_hash = password_hash('child123', PASSWORD_BCRYPT); 
                $user_role = 'Child';
                $password_changed = 0; 

                $user_stmt = $conn->prepare("INSERT INTO users (username, email, password, role, user_type_id, password_changed, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $user_stmt->bind_param("sssssi", $generated_username, $generated_email, $default_password_hash, $user_role, $child_custom_id, $password_changed);
                $user_stmt->execute();
                $user_stmt->close();

                // Commit changes safely
                $conn->commit();

                $message = "✓ Child profile securely registered with ID: <strong>$child_custom_id</strong>.<br>⚡ Login Account Provisioned! Username: <strong>$generated_username</strong> | Password: <strong>child123</strong>";
                $message_class = "success-msg";
                
                // Clear variables cleanly on success
                $first_name = $last_name = $dob = $age = '';
                $mother_first_name = $mother_last_name = $mother_dob = $mother_age = $mother_occupation = '';
                $father_first_name = $father_last_name = $father_dob = $father_age = '';
                $religion = $language = $education_level = $health_status = '';

            } catch (Exception $e) {
                // Rollback database modifications entirely if any query fails
                $conn->rollback();
                $message = "❌ System execution insert error: " . $e->getMessage();
                $message_class = "error-msg";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_update_mode ? 'Modify Child Profile' : 'Child Profile Enrollment Form'; ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .admin-nav { background-color: #343a40; padding: 10px 20px; display: flex; gap: 15px; align-items: center; color: white; font-size: 14px; }
        .admin-nav a { color: #ffc107; text-decoration: none; font-weight: bold; padding: 5px 10px; border: 1px solid #ffc107; border-radius: 4px; }
        .admin-nav a:hover { background-color: #ffc107; color: black; }
        
        .container { max-width: 750px; background: white; margin: 30px auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        h2 { text-align: center; color: #1e3a8a; margin-bottom: 5px; }
        p.subtitle { text-align: center; color: #718096; margin-bottom: 25px; font-size: 14px; }
        
        .search-card { background: #f0f4f8; padding: 20px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #cbd5e0; }
        .search-form { display: flex; gap: 10px; }
        .search-form input { flex-grow: 1; padding: 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 15px; font-weight: bold; text-transform: uppercase; background-color: #ffffff; }
        .btn-search { width: auto; background-color: #2b6cb0; padding: 0 25px; color: white; font-weight: bold; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; margin-top: 0; }
        .btn-search:hover { background-color: #2c5282; }
        .clear-btn { display: flex; align-items: center; justify-content: center; background-color: #e67e22; color: white; border: none; padding: 0 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; text-decoration: none; text-align: center; white-space: nowrap; }
        .clear-btn:hover { background-color: #d35400; }

        .section-divider { background-color: #f0f4f8; padding: 10px; font-weight: bold; color: #1e3a8a; margin: 25px 0 15px 0; border-left: 4px solid #1e3a8a; border-radius: 2px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: span 2; }
        label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 14px; background-color: #fff; }
        input[readonly] { background-color: #edf2f7; color: #4a5568; cursor: not-allowed; }
        
        button { width: 100%; padding: 14px; background-color: #1e3a8a; border: none; color: white; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 25px; }
        button:hover { background-color: #1d4ed8; }
        .btn-update { background-color: #28a745; }
        .btn-update:hover { background-color: #218838; }
        
        .msg-box { padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: bold; line-height: 1.6; }
        .success-msg { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-link { display: block; text-align: center; margin-top: 15px; color: #1e3a8a; text-decoration: none; font-size: 14px; font-weight: bold; }
        .nav-link:hover { text-decoration: underline; }
        .badge-id { display: inline-block; background-color: #e2f0d9; color: #215a1c; padding: 6px 14px; border-radius: 4px; font-family: monospace; font-size: 15px; margin-bottom: 20px; font-weight: bold; border: 1px solid #c3e6cb; }
    </style>
    <script>
        function calculateAge(dobId, ageId) {
            const dobInput = document.getElementById(dobId).value;
            if (!dobInput) {
                document.getElementById(ageId).value = '';
                return;
            }
            
            const birthDate = new Date(dobInput);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            document.getElementById(ageId).value = age >= 0 ? age : 0;
        }

        window.onload = function() {
            const childDob = document.getElementById('dob');
            const today = new Date();
            const minDate = new Date();
            minDate.setFullYear(today.getFullYear() - 20);
            
            const minDateString = minDate.toISOString().split('T')[0];
            const todayString = today.toISOString().split('T')[0];
            
            childDob.setAttribute('min', minDateString);
            childDob.setAttribute('max', todayString);
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
    <h2><?php echo $is_update_mode ? 'Modify Child Profile' : 'Child Profile Enrollment Form'; ?></h2>
    <p class="subtitle"><?php echo $is_update_mode ? 'Update administrative details for the registered child profile record' : 'Register a new alphanumeric system child entry to the database'; ?></p>

    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="search-card">
        <label for="search_id">Search Child Profile by ID</label>
        <form action="register_child.php" method="GET" class="search-form">
            <input type="text" id="search_id" name="search_id" value="<?php echo htmlspecialchars($child_id); ?>" placeholder="e.g., C000000001">
            <button type="submit" class="btn-search">Search</button>
            <?php if ($is_update_mode): ?>
                <a href="register_child.php" class="clear-btn">Clear / New Child</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($is_update_mode): ?>
        <center><div class="badge-id">Mode: Editing Child Record [<?php echo htmlspecialchars($child_id); ?>]</div></center>
    <?php endif; ?>

    <form action="register_child.php<?php echo $is_update_mode ? '?id='.urlencode($child_id) : ''; ?>" method="POST">
        
        <input type="hidden" name="action_mode" value="<?php echo $is_update_mode ? 'update' : 'create'; ?>">
        <input type="hidden" name="child_id" value="<?php echo htmlspecialchars($child_id); ?>">
        
        <div class="section-divider">Primary Child Metrics</div>
        <div class="form-grid">
            <div>
                <label for="first_name">Child First Name</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
            </div>
            <div>
                <label for="last_name">Child Last Name</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
            </div>
            <div>
                <label for="dob">Date of Birth (Under 20 Years Allowed)</label>
                <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($dob); ?>" onchange="calculateAge('dob', 'age')" required>
            </div>
            <div>
                <label for="age">Calculated Age</label>
                <input type="number" id="age" name="age" value="<?php echo htmlspecialchars($age); ?>" readonly required>
            </div>
        </div>

        <div class="section-divider">Maternal Background Information</div>
        <div class="form-grid">
            <div>
                <label for="mother_first_name">Mother's First Name</label>
                <input type="text" id="mother_first_name" name="mother_first_name" value="<?php echo htmlspecialchars($mother_first_name); ?>" required>
            </div>
            <div>
                <label for="mother_last_name">Mother's Last Name</label>
                <input type="text" id="mother_last_name" name="mother_last_name" value="<?php echo htmlspecialchars($mother_last_name); ?>" required>
            </div>
            <div>
                <label for="mother_dob">Mother's Date of Birth</label>
                <input type="date" id="mother_dob" name="mother_dob" value="<?php echo htmlspecialchars($mother_dob); ?>" onchange="calculateAge('mother_dob', 'mother_age')" required>
            </div>
            <div>
                <label for="mother_age">Mother's Calculated Age</label>
                <input type="number" id="mother_age" name="mother_age" value="<?php echo htmlspecialchars($mother_age); ?>" readonly required>
            </div>
            <div class="full-width">
                <label for="mother_occupation">Mother's Current Occupation</label>
                <input type="text" id="mother_occupation" name="mother_occupation" value="<?php echo htmlspecialchars($mother_occupation); ?>" placeholder="e.g., Teacher, Homemaker" required>
            </div>
        </div>

        <div class="section-divider">Paternal Background Information (Optional)</div>
        <div class="form-grid">
            <div>
                <label for="father_first_name">Father's First Name</label>
                <input type="text" id="father_first_name" name="father_first_name" value="<?php echo htmlspecialchars($father_first_name); ?>">
            </div>
            <div>
                <label for="father_last_name">Father's Last Name</label>
                <input type="text" id="father_last_name" name="father_last_name" value="<?php echo htmlspecialchars($father_last_name); ?>">
            </div>
            <div>
                <label for="father_dob">Father's Date of Birth</label>
                <input type="date" id="father_dob" name="father_dob" value="<?php echo htmlspecialchars($father_dob); ?>" onchange="calculateAge('father_dob', 'father_age')">
            </div>
            <div>
                <label for="father_age">Father's Calculated Age</label>
                <input type="number" id="father_age" name="father_age" value="<?php echo htmlspecialchars($father_age); ?>" readonly>
            </div>
        </div>

        <div class="section-divider">Demographic & Programmatic Tracking Options</div>
        <div class="form-grid">
            <div>
                <label for="residence_country">Residence Country</label>
                <input type="text" id="residence_country" name="residence_country" value="<?php echo htmlspecialchars($residence_country); ?>" readonly required>
            </div>
            <div>
                <label for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" value="<?php echo htmlspecialchars($nationality); ?>" readonly required>
            </div>
            <div>
                <label for="religion">Religion Classification</label>
                <select id="religion" name="religion" required>
                    <option value="" disabled <?php echo empty($religion) ? 'selected' : ''; ?>>-- Select Religion --</option>
                    <option value="Buddhist" <?php echo $religion == 'Buddhist' ? 'selected' : ''; ?>>Buddhist</option>
                    <option value="Catholic" <?php echo $religion == 'Catholic' ? 'selected' : ''; ?>>Catholic</option>
                    <option value="Roman Catholic" <?php echo $religion == 'Roman Catholic' ? 'selected' : ''; ?>>Roman Catholic</option>
                    <option value="Hindu" <?php echo $religion == 'Hindu' ? 'selected' : ''; ?>>Hindu</option>
                    <option value="Muslim" <?php echo $religion == 'Muslim' ? 'selected' : ''; ?>>Muslim</option>
                </select>
            </div>
            <div>
                <label for="language">Primary Language spoken</label>
                <select id="language" name="language" required>
                    <option value="" disabled <?php echo empty($language) ? 'selected' : ''; ?>>-- Select Language --</option>
                    <option value="Sinhala" <?php echo $language == 'Sinhala' ? 'selected' : ''; ?>>Sinhala</option>
                    <option value="Tamil" <?php echo $language == 'Tamil' ? 'selected' : ''; ?>>Tamil</option>
                </select>
            </div>
            
            <div>
                <label for="education_level">Current Educational Grade / Level</label>
                <input type="text" id="education_level" name="education_level" value="<?php echo htmlspecialchars($education_level); ?>" placeholder="e.g., Grade 6, Preschool" required>
            </div>
            <div>
                <label for="health_status">Current Medical / Health Summary</label>
                <input type="text" id="health_status" name="health_status" value="<?php echo htmlspecialchars($health_status); ?>" placeholder="e.g., Excellent Health, Asthmatic" required>
            </div>
        </div>

        <?php if ($is_update_mode): ?>
            <button type="submit" class="btn-update">Update Child Profile</button>
        <?php else: ?>
            <button type="submit">Submit and Save Profile</button>
        <?php endif; ?>
    </form>
    
    <a href="coordinator_dashboard.php" class="nav-link">← Return to Coordinator Panel Workspace</a>
</div>

</body>
</html>