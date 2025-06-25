<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('doctor');

$database = new Database();
$db = $database->getConnection();

// Get doctor information
$query = "SELECT * FROM doctors WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get appointment statistics
$query = "SELECT 
    COUNT(*) as total_appointments,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
    MIN(created_at) as member_since
    FROM appointments WHERE doctor_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activities
$query = "SELECT 
    a.created_at,
    a.status,
    p.name as patient_name,
    a.appointment_date,
    a.appointment_time
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id 
    WHERE a.doctor_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $specialization = trim($_POST['specialization']);
        $license_number = trim($_POST['license_number']);
        
        // Validate inputs
        if (empty($name) || empty($email) || empty($phone) || empty($specialization) || empty($license_number)) {
            $error_message = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if email already exists (except current user)
            $query = "SELECT id FROM doctors WHERE email = ? AND id != ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                $error_message = "Email address is already in use by another doctor.";
            } else {
                // Update profile
                $query = "UPDATE doctors SET name = ?, email = ?, phone = ?, specialization = ?, license_number = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$name, $email, $phone, $specialization, $license_number, $_SESSION['user_id']])) {
                    $success_message = "Profile updated successfully!";
                    $_SESSION['user_name'] = $name; // Update session name
                    
                    // Refresh doctor data
                    $query = "SELECT * FROM doctors WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$_SESSION['user_id']]);
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error_message = "Error updating profile. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (md5($current_password) !== $doctor['password']) {
            $error_message = "Current password is incorrect.";
        } else {
            // Update password
            $query = "UPDATE doctors SET password = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([md5($new_password), $_SESSION['user_id']])) {
                $success_message = "Password changed successfully!";
                
                // Refresh doctor data
                $query = "SELECT * FROM doctors WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error_message = "Error changing password. Please try again.";
            }
        }
    }
}

// Format member since date
$member_since = $stats['member_since'] ? date('M Y', strtotime($stats['member_since'])) : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile - Medical System</title>
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
                    <li><a href="appointments.php">All Appointments</a></li>
                    <li><a href="patients.php">My Patients</a></li>
                    <li><a href="schedule.php">Schedule</a></li>
                    <li><a href="profile.php" class="active">Profile</a></li>
                </ul>
            </nav>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
                <span>Dr. <?php echo $_SESSION['user_name']; ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar-large">
                <?php echo strtoupper(substr($doctor['name'], 0, 2)); ?>
            </div>
            <div class="profile-name">Dr. <?php echo htmlspecialchars($doctor['name']); ?></div>
            <div class="profile-type"><?php echo htmlspecialchars($doctor['specialization']); ?></div>
            <div class="profile-member-since">Member since <?php echo $member_since; ?></div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">❌ <?php echo $error_message; ?></div>
        <?php endif; ?>

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
            <div class="account-stat">
                <div class="account-stat-number"><?php echo date('Y') - date('Y', strtotime($doctor['created_at'])); ?></div>
                <div class="account-stat-label">Years Active</div>
            </div>
        </div>

        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <button class="profile-tab active" onclick="showTab('profile-info')">Profile Information</button>
            <button class="profile-tab" onclick="showTab('security')">Security Settings</button>
            <button class="profile-tab" onclick="showTab('activity')">Recent Activity</button>
        </div>

        <!-- Profile Information Tab -->
        <div id="profile-info" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Profile Information</h2>
                </div>
                
                <form method="POST" id="profileForm">
                    <div class="profile-info-grid">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   value="<?php echo htmlspecialchars($doctor['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization" class="form-label">Specialization</label>
                            <select id="specialization" name="specialization" class="form-select" required>
                                <option value="">Select Specialization</option>
                                <option value="Cardiology" <?php echo ($doctor['specialization'] === 'Cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                <option value="Dermatology" <?php echo ($doctor['specialization'] === 'Dermatology') ? 'selected' : ''; ?>>Dermatology</option>
                                <option value="Endocrinology" <?php echo ($doctor['specialization'] === 'Endocrinology') ? 'selected' : ''; ?>>Endocrinology</option>
                                <option value="Gastroenterology" <?php echo ($doctor['specialization'] === 'Gastroenterology') ? 'selected' : ''; ?>>Gastroenterology</option>
                                <option value="General Practice" <?php echo ($doctor['specialization'] === 'General Practice') ? 'selected' : ''; ?>>General Practice</option>
                                <option value="Gynecology" <?php echo ($doctor['specialization'] === 'Gynecology') ? 'selected' : ''; ?>>Gynecology</option>
                                <option value="Neurology" <?php echo ($doctor['specialization'] === 'Neurology') ? 'selected' : ''; ?>>Neurology</option>
                                <option value="Oncology" <?php echo ($doctor['specialization'] === 'Oncology') ? 'selected' : ''; ?>>Oncology</option>
                                <option value="Orthopedics" <?php echo ($doctor['specialization'] === 'Orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                                <option value="Pediatrics" <?php echo ($doctor['specialization'] === 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                                <option value="Psychiatry" <?php echo ($doctor['specialization'] === 'Psychiatry') ? 'selected' : ''; ?>>Psychiatry</option>
                                <option value="Radiology" <?php echo ($doctor['specialization'] === 'Radiology') ? 'selected' : ''; ?>>Radiology</option>
                                <option value="Surgery" <?php echo ($doctor['specialization'] === 'Surgery') ? 'selected' : ''; ?>>Surgery</option>
                                <option value="Urology" <?php echo ($doctor['specialization'] === 'Urology') ? 'selected' : ''; ?>>Urology</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="license_number" class="form-label">License Number</label>
                            <input type="text" id="license_number" name="license_number" class="form-input" 
                                   value="<?php echo htmlspecialchars($doctor['license_number']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Member Since</label>
                            <div class="profile-info-value" style="padding: 12px 16px; border: 2px solid #e1e5e9; border-radius: 10px; background: #f8f9fa;">
                                <?php echo date('F d, Y', strtotime($doctor['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            💾 Update Profile
                        </button>
                        <button type="button" class="btn btn-cancel" onclick="resetForm()">
                            🔄 Reset Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Settings Tab -->
        <div id="security" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Change Password</h2>
                </div>
                
                <form method="POST" id="passwordForm">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" required>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        <div id="passwordMatch" style="font-size: 0.8rem; margin-top: 5px;"></div>
                    </div>
                    
                    <div class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li>At least 6 characters long</li>
                            <li>Include uppercase and lowercase letters</li>
                            <li>Include at least one number</li>
                            <li>Include at least one special character</li>
                        </ul>
                    </div>
                    
                    <div class="profile-actions">
                        <button type="submit" name="change_password" class="btn btn-warning">
                            🔒 Change Password
                        </button>
                        <button type="button" class="btn btn-cancel" onclick="clearPasswordForm()">
                            🧹 Clear Form
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Account Security</h2>
                </div>
                <div class="grid grid-2">
                    <div class="profile-info-item">
                        <div class="profile-info-label">Account Status</div>
                        <div class="profile-info-value" style="color: #2ed573;">✅ Active</div>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Last Login</div>
                        <div class="profile-info-value"><?php echo date('M d, Y H:i', time()); ?></div>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Account Type</div>
                        <div class="profile-info-value">Doctor</div>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Two-Factor Auth</div>
                        <div class="profile-info-value" style="color: #ffa502;">⚠️ Not Enabled</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Tab -->
        <div id="activity" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Activity</h2>
                </div>
                
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center" style="padding: 40px;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">📋</div>
                        <p>No recent activity to display.</p>
                        <p style="color: #666; font-size: 0.9rem;">Your appointment activities will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                                </div>
                                <div class="timeline-title">
                                    Appointment <?php echo ucfirst($activity['status']); ?>
                                </div>
                                <div class="timeline-description">
                                    Patient: <?php echo htmlspecialchars($activity['patient_name']); ?><br>
                                    Scheduled for: <?php echo date('M d, Y', strtotime($activity['appointment_date'])); ?> 
                                    at <?php echo date('h:i A', strtotime($activity['appointment_time'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-20">
                        <a href="appointments.php" class="btn btn-info">View All Appointments</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.profile-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Reset profile form
        function resetForm() {
            document.getElementById('profileForm').reset();
            // You could also reload the page to get original values
            // location.reload();
        }

        // Clear password form
        function clearPasswordForm() {
            document.getElementById('passwordForm').reset();
            document.getElementById('passwordStrength').textContent = '';
            document.getElementById('passwordMatch').textContent = '';
        }

        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 6) strength += 1;
            else feedback.push('At least 6 characters');
            
            if (/[A-Z]/.test(password)) strength += 1;
            else feedback.push('Uppercase letter');
            
            if (/[a-z]/.test(password)) strength += 1;
            else feedback.push('Lowercase letter');
            
            if (/[0-9]/.test(password)) strength += 1;
            else feedback.push('Number');
            
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            else feedback.push('Special character');
            
            if (strength < 3) {
                strengthDiv.className = 'password-strength strength-weak';
                strengthDiv.textContent = 'Weak - Missing: ' + feedback.join(', ');
            } else if (strength < 5) {
                strengthDiv.className = 'password-strength strength-medium';
                strengthDiv.textContent = 'Medium - Missing: ' + feedback.join(', ');
            } else {
                strengthDiv.className = 'password-strength strength-strong';
                strengthDiv.textContent = 'Strong password!';
            }
        });

        // Password confirmation checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchDiv.textContent = '';
                return;
            }
            
            if (newPassword === confirmPassword) {
                matchDiv.textContent = '✅ Passwords match';
                matchDiv.style.color = '#2ed573';
            } else {
                matchDiv.textContent = '❌ Passwords do not match';
                matchDiv.style.color = '#ff4757';
            }
        });

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const specialization = document.getElementById('specialization').value;
            const license = document.getElementById('license_number').value.trim();
            
            if (!name || !email || !phone || !specialization || !license) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
        });

        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all password fields.');
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                return;
            }
        });

        // Auto-save draft functionality (optional)
        let formData = {};
        document.querySelectorAll('#profileForm input, #profileForm select').forEach(input => {
            input.addEventListener('input', function() {
                formData[this.name] = this.value;
                // You could save to localStorage here if needed
                // localStorage.setItem('doctorProfileDraft', JSON.stringify(formData));
            });
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
            }
            this.value = value;
        });
    </script>
</body>
</html>