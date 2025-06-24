<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('patient');

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Get current patient data
$query = "SELECT * FROM patients WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Get patient statistics
$query = "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
    MIN(appointment_date) as first_appointment
    FROM appointments WHERE patient_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity
$query = "SELECT a.*, d.name as doctor_name, d.specialization 
          FROM appointments a 
          JOIN doctors d ON a.doctor_id = d.id 
          WHERE a.patient_id = ? 
          ORDER BY a.created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $gender = $_POST['gender'];
        $date_of_birth = $_POST['date_of_birth'];
        $address = trim($_POST['address']);
        
        // Validation
        if (empty($name) || empty($email) || empty($phone)) {
            $error = "Name, email, and phone are required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email already exists for other users
            $query = "SELECT id FROM patients WHERE email = ? AND id != ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Email address already exists.";
            } else {
                // Update profile
                $query = "UPDATE patients SET name = ?, email = ?, phone = ?, gender = ?, date_of_birth = ?, address = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$name, $email, $phone, $gender, $date_of_birth, $address, $_SESSION['user_id']])) {
                    $_SESSION['user_name'] = $name; // Update session
                    $message = "Profile updated successfully!";
                    
                    // Refresh patient data
                    $query = "SELECT * FROM patients WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$_SESSION['user_id']]);
                    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to update profile.";
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required.";
        } elseif (md5($current_password) !== $patient['password']) {
            $error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long.";
        } else {
            // Update password
            $query = "UPDATE patients SET password = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([md5($new_password), $_SESSION['user_id']])) {
                $message = "Password changed successfully!";
                $patient['password'] = md5($new_password); // Update local data
            } else {
                $error = "Failed to change password.";
            }
        }
    }
}

// Calculate age
$age = '';
if ($patient['date_of_birth']) {
    $dob = new DateTime($patient['date_of_birth']);
    $now = new DateTime();
    $age = $dob->diff($now)->y . ' years old';
}

// Calculate member since
$member_since = '';
if ($patient['created_at']) {
    $created = new DateTime($patient['created_at']);
    $member_since = $created->format('M Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Medical System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="home.php" class="logo">MedSystem</a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="appointment-form.php">Book Appointment</a></li>
                    <li><a href="appointments.php">My Appointments</a></li>
                    <li><a href="doctors.php">Doctors</a></li>
                    <li><a href="profile.php" class="active">Profile</a></li>
                </ul>
            </nav>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
                <span><?php echo $_SESSION['user_name']; ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar-large">
                <?php echo strtoupper(substr($patient['name'], 0, 1)); ?>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($patient['name']); ?></div>
            <div class="profile-type">Patient</div>
            <div class="profile-member-since">Member since <?php echo $member_since; ?></div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <button class="profile-tab active" onclick="showTab('personal-info')">Personal Information</button>
            <button class="profile-tab" onclick="showTab('security')">Security</button>
            <button class="profile-tab" onclick="showTab('activity')">Activity</button>
        </div>

        <!-- Personal Information Tab -->
        <div id="personal-info" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Personal Information</h2>
                </div>

                <!-- Account Statistics -->
                <div class="account-stats">
                    <div class="account-stat">
                        <div class="account-stat-number"><?php echo $stats['total_appointments'] ?: '0'; ?></div>
                        <div class="account-stat-label">Total Appointments</div>
                    </div>
                    <div class="account-stat">
                        <div class="account-stat-number"><?php echo $stats['completed_appointments'] ?: '0'; ?></div>
                        <div class="account-stat-label">Completed</div>
                    </div>
                    <div class="account-stat">
                        <div class="account-stat-number"><?php echo $stats['pending_appointments'] ?: '0'; ?></div>
                        <div class="account-stat-label">Pending</div>
                    </div>
                </div>

                <form method="POST" id="profileForm">
                    <div class="profile-info-grid" id="profileInfo">
                        <div class="profile-info-item">
                            <div class="profile-info-label">Full Name</div>
                            <div class="profile-info-value" id="name-display"><?php echo htmlspecialchars($patient['name']); ?></div>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($patient['name']); ?>" style="display: none;" required>
                        </div>
                        
                        <div class="profile-info-item">
                            <div class="profile-info-label">Email Address</div>
                            <div class="profile-info-value" id="email-display"><?php echo htmlspecialchars($patient['email']); ?></div>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($patient['email']); ?>" style="display: none;" required>
                        </div>
                        
                        <div class="profile-info-item">
                            <div class="profile-info-label">Phone Number</div>
                            <div class="profile-info-value" id="phone-display"><?php echo htmlspecialchars($patient['phone']); ?></div>
                            <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($patient['phone']); ?>" style="display: none;" required>
                        </div>
                        
                        <div class="profile-info-item">
                            <div class="profile-info-label">Gender</div>
                            <div class="profile-info-value" id="gender-display"><?php echo htmlspecialchars($patient['gender']); ?></div>
                            <select name="gender" class="form-select" style="display: none;" required>
                                <option value="Male" <?php echo $patient['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $patient['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        
                        <div class="profile-info-item">
                            <div class="profile-info-label">Date of Birth</div>
                            <div class="profile-info-value" id="dob-display">
                                <?php echo $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) . ' (' . $age . ')' : 'Not set'; ?>
                            </div>
                            <input type="date" name="date_of_birth" class="form-input" value="<?php echo $patient['date_of_birth']; ?>" style="display: none;">
                        </div>
                        
                        <div class="profile-info-item">
                            <div class="profile-info-label">Member Since</div>
                            <div class="profile-info-value"><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="display: none;" id="address-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-textarea" rows="3"><?php echo htmlspecialchars($patient['address']); ?></textarea>
                    </div>
                    
                    <div class="profile-info-item" id="address-display-item">
                        <div class="profile-info-label">Address</div>
                        <div class="profile-info-value"><?php echo htmlspecialchars($patient['address']) ?: 'Not provided'; ?></div>
                    </div>

                    <div class="profile-actions">
                        <button type="button" class="btn btn-edit" id="editBtn" onclick="toggleEdit()">Edit Profile</button>
                        <button type="submit" name="update_profile" class="btn btn-success" id="saveBtn" style="display: none;">Save Changes</button>
                        <button type="button" class="btn btn-cancel" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Security Settings</h2>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" id="newPassword" required>
                        <div id="passwordStrength" class="password-strength"></div>
                        <div class="password-requirements">
                            <strong>Password Requirements:</strong>
                            <ul>
                                <li>At least 6 characters long</li>
                                <li>Contains at least one letter</li>
                                <li>Contains at least one number (recommended)</li>
                                <li>Contains special characters (recommended)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                    
                    <div class="profile-actions">
                        <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Activity Tab -->
        <div id="activity" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Activity</h2>
                </div>
                
                <?php if (empty($recent_activities)): ?>
                    <p>No recent activities to display.</p>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php echo date('M d, Y \a\t h:i A', strtotime($activity['created_at'])); ?>
                            </div>
                            <div class="timeline-title">
                                Appointment with <?php echo htmlspecialchars($activity['doctor_name']); ?>
                            </div>
                            <div class="timeline-description">
                                <?php echo htmlspecialchars($activity['specialization']); ?> • 
                                <?php echo date('M d, Y', strtotime($activity['appointment_date'])); ?> at 
                                <?php echo date('h:i A', strtotime($activity['appointment_time'])); ?> • 
                                <span class="status-badge status-<?php echo $activity['status']; ?>">
                                    <?php echo ucfirst($activity['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-20">
                    <a href="appointments.php" class="btn btn-info">View All Appointments</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.profile-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Edit profile functionality
        let originalData = {};

        function toggleEdit() {
            const profileInfo = document.getElementById('profileInfo');
            const editBtn = document.getElementById('editBtn');
            const saveBtn = document.getElementById('saveBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const addressGroup = document.getElementById('address-group');
            const addressDisplayItem = document.getElementById('address-display-item');
            
            // Store original data
            const form = document.getElementById('profileForm');
            const formData = new FormData(form);
            originalData = {};
            for (let [key, value] of formData.entries()) {
                originalData[key] = value;
            }
            
            // Toggle edit mode
            profileInfo.classList.add('edit-mode');
            
            // Hide display elements and show input elements
            const displays = profileInfo.querySelectorAll('.profile-info-value');
            const inputs = profileInfo.querySelectorAll('.form-input, .form-select');
            
            displays.forEach(display => display.style.display = 'none');
            inputs.forEach(input => input.style.display = 'block');
            
            // Show address textarea and hide display
            addressGroup.style.display = 'block';
            addressDisplayItem.style.display = 'none';
            
            // Toggle buttons
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-block';
            cancelBtn.style.display = 'inline-block';
        }

        function cancelEdit() {
            const profileInfo = document.getElementById('profileInfo');
            const editBtn = document.getElementById('editBtn');
            const saveBtn = document.getElementById('saveBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const addressGroup = document.getElementById('address-group');
            const addressDisplayItem = document.getElementById('address-display-item');
            
            // Restore original values
            const form = document.getElementById('profileForm');
            for (let [key, value] of Object.entries(originalData)) {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) input.value = value;
            }
            
            // Toggle edit mode
            profileInfo.classList.remove('edit-mode');
            
            // Show display elements and hide input elements
            const displays = profileInfo.querySelectorAll('.profile-info-value');
            const inputs = profileInfo.querySelectorAll('.form-input, .form-select');
            
            displays.forEach(display => display.style.display = 'block');
            inputs.forEach(input => input.style.display = 'none');
            
            // Hide address textarea and show display
            addressGroup.style.display = 'none';
            addressDisplayItem.style.display = 'block';
            
            // Toggle buttons
            editBtn.style.display = 'inline-block';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
        }

        // Password strength checker
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-zA-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    feedback = '<span class="strength-weak">Weak</span>';
                    break;
                case 2:
                case 3:
                    feedback = '<span class="strength-medium">Medium</span>';
                    break;
                case 4:
                    feedback = '<span class="strength-strong">Strong</span>';
                    break;
            }
            
            strengthDiv.innerHTML = feedback;
        });
    </script>
</body>
</html>