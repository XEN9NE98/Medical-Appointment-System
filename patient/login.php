<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$error = '';
$success = '';

if ($_POST) {
    if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        $user_type = $_POST['user_type'];
        
        if ($user->login($email, $password, $user_type)) {
            if ($user_type === 'patient') {
                header('Location: patient/home.php');
            } else {
                header('Location: doctor/home.php');
            }
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    }
    
    if (isset($_POST['register'])) {
        $user_type = $_POST['user_type'];
        $data = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'password' => $_POST['password'],
            'phone' => $_POST['phone']
        ];
        
        if ($user_type === 'patient') {
            $data['gender'] = $_POST['gender'];
            $data['date_of_birth'] = $_POST['date_of_birth'];
            $data['address'] = $_POST['address'];
        } else {
            $data['specialization'] = $_POST['specialization'];
            $data['license_number'] = $_POST['license_number'];
        }
        
        if ($user->register($data, $user_type)) {
            $success = 'Registration successful! Please login.';
        } else {
            $error = 'Registration failed. Email might already exist.';
        }
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    if (getUserType() === 'patient') {
        header('Location: patient/home.php');
    } else {
        header('Location: doctor/home.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical System - Login</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <h1 class="login-title">MedSystem</h1>
            <p class="text-center" style="color: #666; margin-bottom: 30px;">Your Health, Our Priority</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="user-type-selector">
                <div class="user-type-option active" onclick="selectUserType('patient')">Patient</div>
                <div class="user-type-option" onclick="selectUserType('doctor')">Doctor</div>
            </div>
            
            <!-- Login Form -->
            <form id="loginForm" method="POST" action="">
                <input type="hidden" name="user_type" id="loginUserType" value="patient">
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" name="login" class="btn btn-primary" style="width: 100%; margin-bottom: 20px;">
                    Login
                </button>
            </form>
            
            <div class="text-center">
                <a href="#" onclick="showRegisterForm()" style="color: #667eea; text-decoration: none;">
                    Don't have an account? Register here
                </a>
            </div>
            
            <!-- Register Form (Hidden by default) -->
            <form id="registerForm" method="POST" action="" style="display: none; margin-top: 20px;">
                <input type="hidden" name="user_type" id="registerUserType" value="patient">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-input" placeholder="Enter your full name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter your email" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Create a password" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input" placeholder="Enter your phone number" required>
                </div>
                
                <!-- Patient specific fields -->
                <div id="patientFields">
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-textarea" placeholder="Enter your address" required></textarea>
                    </div>
                </div>
                
                <!-- Doctor specific fields -->
                <div id="doctorFields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Specialization</label>
                        <select name="specialization" class="form-select">
                            <option value="">Select Specialization</option>
                            <option value="General Medicine">General Medicine</option>
                            <option value="Cardiology">Cardiology</option>
                            <option value="Dermatology">Dermatology</option>
                            <option value="Neurology">Neurology</option>
                            <option value="Orthopedics">Orthopedics</option>
                            <option value="Pediatrics">Pediatrics</option>
                            <option value="Psychiatry">Psychiatry</option>
                            <option value="Radiology">Radiology</option>
                            <option value="Surgery">Surgery</option>
                            <option value="Gynecology">Gynecology</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">License Number</label>
                        <input type="text" name="license_number" class="form-input" placeholder="Enter your medical license number">
                    </div>
                </div>
                
                <button type="submit" name="register" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                    Register
                </button>
                <button type="button" onclick="showLoginForm()" class="btn btn-info" style="width: 100%;">
                    Back to Login
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentUserType = 'patient';
        
        function selectUserType(type) {
            currentUserType = type;
            
            // Update visual selection
            document.querySelectorAll('.user-type-option').forEach(el => el.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update form values
            document.getElementById('loginUserType').value = type;
            document.getElementById('registerUserType').value = type;
            
            // Show/hide fields for registration
            if (type === 'patient') {
                document.getElementById('patientFields').style.display = 'block';
                document.getElementById('doctorFields').style.display = 'none';
                // Make patient fields required
                document.querySelector('select[name="gender"]').required = true;
                document.querySelector('input[name="date_of_birth"]').required = true;
                document.querySelector('textarea[name="address"]').required = true;
                document.querySelector('select[name="specialization"]').required = false;
                document.querySelector('input[name="license_number"]').required = false;
            } else {
                document.getElementById('patientFields').style.display = 'none';
                document.getElementById('doctorFields').style.display = 'block';
                // Make doctor fields required
                document.querySelector('select[name="gender"]').required = false;
                document.querySelector('input[name="date_of_birth"]').required = false;
                document.querySelector('textarea[name="address"]').required = false;
                document.querySelector('select[name="specialization"]').required = true;
                document.querySelector('input[name="license_number"]').required = true;
            }
        }
        
        function showRegisterForm() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
            document.querySelector('.login-title').textContent = 'Register - MedSystem';
        }
        
        function showLoginForm() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('registerForm').style.display = 'none';
            document.querySelector('.login-title').textContent = 'MedSystem';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const dobInput = document.querySelector('input[name="date_of_birth"]');
            const today = new Date();
            const maxDate = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
            dobInput.max = maxDate.toISOString().split('T')[0];
        });
    </script>
</body>
</html>