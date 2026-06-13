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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect Core Variables
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $dob = $_POST['dob'];
    $age = intval($_POST['age']);
    
    $mother_first_name = trim($_POST['mother_first_name']);
    $mother_last_name = trim($_POST['mother_last_name']);
    $mother_dob = $_POST['mother_dob'];
    $mother_age = intval($_POST['mother_age']);
    $mother_occupation = trim($_POST['mother_occupation']);
    
    $father_first_name = trim($_POST['father_first_name']);
    $father_last_name = trim($_POST['father_last_name']);
    $father_dob = $_POST['father_dob'];
    $father_age = intval($_POST['father_age']);
    
    $residence_country = isset($_POST['residence_country']) ? $_POST['residence_country'] : 'Sri Lanka';
    $religion = $_POST['religion'];
    $nationality = isset($_POST['nationality']) ? $_POST['nationality'] : 'Sri Lankan';
    $language = $_POST['language'];
    
    $education_level = trim($_POST['education_level']);
    $health_status = trim($_POST['health_status']);
    
    $registered_by = $_SESSION['user_id'];

    // Server-side validation check for child's age limit (< 20 years old)
    $birthday = new DateTime($dob);
    $now = new DateTime();
    $calculated_age = $now->diff($birthday)->y;

    if ($calculated_age >= 20) {
        $message = "❌ Error: Child must be below 20 years of age.";
        $message_class = "error-msg";
    } else {
        
        // --- STEP 1: Generate the Alphanumeric Padded Child ID (C000000001, C000000002...) ---
        $id_query = "SELECT MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_id FROM child";
        $id_result = $conn->query($id_query);
        $row = $id_result->fetch_assoc();
        
        if ($row['max_id'] !== null) {
            $next_num = $row['max_id'] + 1;
        } else {
            $next_num = 1; // Falling back to 1 if the table is empty
        }
        
        // str_pad adds leading zeros up to a fixed total string length of 9 characters
        $padded_number = str_pad($next_num, 9, "0", STR_PAD_LEFT);
        $child_custom_id = "C" . $padded_number;

        // --- STEP 2: Safe prepared statement insertion matching schema design rules ---
        $stmt = $conn->prepare("INSERT INTO child (id, first_name, last_name, dob, age, mother_first_name, mother_last_name, mother_dob, mother_age, mother_occupation, father_first_name, father_last_name, father_dob, father_age, residence_country, religion, nationality, language, education_level, health_status, registered_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssssisssisssisssssssi", 
            $child_custom_id, $first_name, $last_name, $dob, $age,
            $mother_first_name, $mother_last_name, $mother_dob, $mother_age, $mother_occupation,
            $father_first_name, $father_last_name, $father_dob, $father_age,
            $residence_country, $religion, $nationality, $language,
            $education_level, $health_status, $registered_by
        );

        if ($stmt->execute()) {
            $message = "✓ Child profile securely registered with ID: $child_custom_id for '$first_name $last_name'.";
            $message_class = "success-msg";
        } else {
            $message = "❌ System execution error: " . $stmt->error;
            $message_class = "error-msg";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Registration Portal</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .admin-nav { background-color: #343a40; padding: 10px 20px; display: flex; gap: 15px; align-items: center; color: white; font-size: 14px; }
        .admin-nav a { color: #ffc107; text-decoration: none; font-weight: bold; padding: 5px 10px; border: 1px solid #ffc107; border-radius: 4px; }
        .admin-nav a:hover { background-color: #ffc107; color: black; }
        
        .container { max-width: 750px; background: white; margin: 30px auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        h2 { text-align: center; color: #1e3a8a; margin-bottom: 25px; }
        .section-divider { background-color: #f0f4f8; padding: 10px; font-weight: bold; color: #1e3a8a; margin: 25px 0 15px 0; border-left: 4px solid #1e3a8a; border-radius: 2px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: span 2; }
        label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-size: 14px; background-color: #fff; }
        input[readonly] { background-color: #edf2f7; color: #4a5568; cursor: not-allowed; }
        button { width: 100%; padding: 14px; background-color: #1e3a8a; border: none; color: white; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 25px; }
        button:hover { background-color: #1d4ed8; }
        .msg-box { padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 14px; font-weight: bold; }
        .success-msg { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-link { display: block; text-align: center; margin-top: 15px; color: #1e3a8a; text-decoration: none; font-size: 14px; font-weight: bold; }
        .nav-link:hover { text-decoration: underline; }
    </style>
    <script>
        function calculateAge(dobId, ageId) {
            const dobInput = document.getElementById(dobId).value;
            if (!dobInput) return;
            
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
    <h2>Child Profile Enrollment Form</h2>

    <?php if (!empty($message)): ?>
        <div class="msg-box <?php echo $message_class; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form action="register_child.php" method="POST">
        
        <div class="section-divider">Primary Child Metrics</div>
        <div class="form-grid">
            <div>
                <label for="first_name">Child First Name</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div>
                <label for="last_name">Child Last Name</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div>
                <label for="dob">Date of Birth (Under 20 Years Allowed)</label>
                <input type="date" id="dob" name="dob" onchange="calculateAge('dob', 'age')" required>
            </div>
            <div>
                <label for="age">Calculated Age</label>
                <input type="number" id="age" name="age" readonly required>
            </div>
        </div>

        <div class="section-divider">Maternal Background Information</div>
        <div class="form-grid">
            <div>
                <label for="mother_first_name">Mother's First Name</label>
                <input type="text" id="mother_first_name" name="mother_first_name" required>
            </div>
            <div>
                <label for="mother_last_name">Mother's Last Name</label>
                <input type="text" id="mother_last_name" name="mother_last_name" required>
            </div>
            <div>
                <label for="mother_dob">Mother's Date of Birth</label>
                <input type="date" id="mother_dob" name="mother_dob" onchange="calculateAge('mother_dob', 'mother_age')" required>
            </div>
            <div>
                <label for="mother_age">Mother's Calculated Age</label>
                <input type="number" id="mother_age" name="mother_age" readonly required>
            </div>
            <div class="full-width">
                <label for="mother_occupation">Mother's Current Occupation</label>
                <input type="text" id="mother_occupation" name="mother_occupation" placeholder="e.g., Teacher, Homemaker" required>
            </div>
        </div>

        <div class="section-divider">Paternal Background Information</div>
        <div class="form-grid">
            <div>
                <label for="father_first_name">Father's First Name</label>
                <input type="text" id="father_first_name" name="father_first_name" required>
            </div>
            <div>
                <label for="father_last_name">Father's Last Name</label>
                <input type="text" id="father_last_name" name="father_last_name" required>
            </div>
            <div>
                <label for="father_dob">Father's Date of Birth</label>
                <input type="date" id="father_dob" name="father_dob" onchange="calculateAge('father_dob', 'father_age')" required>
            </div>
            <div>
                <label for="father_age">Father's Calculated Age</label>
                <input type="number" id="father_age" name="father_age" readonly required>
            </div>
        </div>

        <div class="section-divider">Demographic & Programmatic Tracking Options</div>
        <div class="form-grid">
            <div>
                <label for="residence_country">Residence Country</label>
                <input type="text" id="residence_country" name="residence_country" value="Sri Lanka" readonly required>
            </div>
            <div>
                <label for="nationality">Nationality</label>
                <input type="text" id="nationality" name="nationality" value="Sri Lankan" readonly required>
            </div>
            <div>
                <label for="religion">Religion Classification</label>
                <select id="religion" name="religion" required>
                    <option value="" disabled selected>-- Select Religion --</option>
                    <option value="Buddhist">Buddhist</option>
                    <option value="Catholic">Catholic</option>
                    <option value="Roman Catholic">Roman Catholic</option>
                    <option value="Hindu">Hindu</option>
                    <option value="Muslim">Muslim</option>
                </select>
            </div>
            <div>
                <label for="language">Primary Language spoken</label>
                <select id="language" name="language" required>
                    <option value="" disabled selected>-- Select Language --</option>
                    <option value="Sinhala">Sinhala</option>
                    <option value="Tamil">Tamil</option>
                </select>
            </div>
            
            <div>
                <label for="education_level">Current Educational Grade / Level</label>
                <input type="text" id="education_level" name="education_level" placeholder="e.g., Grade 6, Preschool" required>
            </div>
            <div>
                <label for="health_status">Current Medical / Health Summary</label>
                <input type="text" id="health_status" name="health_status" placeholder="e.g., Excellent Health, Asthmatic" required>
            </div>
        </div>

        <button type="submit">Submit and Save Profile</button>
    </form>
    
    <a href="coordinator_dashboard.php" class="nav-link">← Return to Coordinator Panel Workspace</a>
</div>

</body>
</html>