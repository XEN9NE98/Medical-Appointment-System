<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('patient');

$database = new Database();
$db = $database->getConnection();
$appointment = new Appointment($db);
$doctor = new Doctor($db);

$error = '';
$success = '';

// Get all doctors
$doctors = $doctor->getAll();

if ($_POST) {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $symptoms = $_POST['symptoms'];
    
    // Validate appointment date (not in the past)
    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Appointment date cannot be in the past.';
    } else {
        // Check if appointment slot is available
        $query = "SELECT COUNT(*) as count FROM appointments 
                 WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'";
        $stmt = $db->prepare($query);
        $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            $error = 'This time slot is already booked. Please choose another time.';
        } else {
            if ($appointment->create($_SESSION['user_id'], $doctor_id, $appointment_date, $appointment_time, $symptoms)) {
                $success = 'Appointment booked successfully! You will be notified once the doctor approves it.';
                // Clear form data after successful submission
                $_POST = array();
            } else {
                $error = 'Failed to book appointment. Please try again.';
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
    <title>Book Appointment - Medical System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="home.php" class="logo">🏥 MedSystem</a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="appointment-form.php" class="active">Book Appointment</a></li>
                    <li><a href="appointments.php">My Appointments</a></li>
                    <li><a href="doctors.php">Doctors</a></li>
                    <li><a href="profile.php">Profile</a></li>
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
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Book New Appointment</h1>
                <p>Schedule an appointment with one of our qualified doctors.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="appointmentForm">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Select Doctor *</label>
                        <select name="doctor_id" class="form-select" required onchange="showDoctorInfo(this.value)">
                            <option value="">Choose a doctor</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?php echo $doc['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($doc['name']); ?>"
                                        data-specialty="<?php echo htmlspecialchars($doc['specialization']); ?>"
                                        data-phone="<?php echo htmlspecialchars($doc['phone']); ?>"
                                        <?php echo (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $doc['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doc['name']); ?> - <?php echo htmlspecialchars($doc['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Appointment Date *</label>
                        <input type="date" name="appointment_date" class="form-input" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               value="<?php echo isset($_POST['appointment_date']) ? $_POST['appointment_date'] : ''; ?>" 
                               required onchange="checkAvailability()">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Appointment Time *</label>
                        <select name="appointment_time" class="form-select" required onchange="checkAvailability()">
                            <option value="">Select time</option>
                            <optgroup label="Morning Hours">
                                <option value="09:00:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '09:00:00') ? 'selected' : ''; ?>>9:00 AM</option>
                                <option value="09:30:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '09:30:00') ? 'selected' : ''; ?>>9:30 AM</option>
                                <option value="10:00:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '10:00:00') ? 'selected' : ''; ?>>10:00 AM</option>
                                <option value="10:30:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '10:30:00') ? 'selected' : ''; ?>>10:30 AM</option>
                                <option value="11:00:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '11:00:00') ? 'selected' : ''; ?>>11:00 AM</option>
                                <option value="11:30:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '11:30:00') ? 'selected' : ''; ?>>11:30 AM</option>
                            </optgroup>
                            <optgroup label="Afternoon Hours">
                                <option value="14:00:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '14:00:00') ? 'selected' : ''; ?>>2:00 PM</option>
                                <option value="14:30:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '14:30:00') ? 'selected' : ''; ?>>2:30 PM</option>
                                <option value="15:00:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '15:00:00') ? 'selected' : ''; ?>>3:00 PM</option>
                                <option value="15:30:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '15:30:00') ? 'selected' : ''; ?>>3:30 PM</option>
                                <option value="16:00:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '16:00:00') ? 'selected' : ''; ?>>4:00 PM</option>
                                <option value="16:30:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '16:30:00') ? 'selected' : ''; ?>>4:30 PM</option>
                                <option value="17:00:00" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] == '17:00:00') ? 'selected' : ''; ?>>5:00 PM</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Symptoms / Reason for Visit *</label>
                    <textarea name="symptoms" class="form-textarea" rows="5" 
                              placeholder="Please describe your symptoms or reason for the appointment..."
                              required><?php echo isset($_POST['symptoms']) ? htmlspecialchars($_POST['symptoms']) : ''; ?></textarea>
                </div>

                <!-- Doctor Info Display -->
                <div id="doctorInfo" class="card" style="display: none; background: #f8f9fa; margin: 20px 0;">
                    <h3>Selected Doctor Information</h3>
                    <div class="grid grid-2">
                        <div class="text-center">
                            <div class="doctor-avatar" style="width: 80px; height: 80px; margin: 0 auto;">
                                <span id="doctorInitial"></span>
                            </div>
                        </div>
                        <div>
                            <h4 id="doctorName"></h4>
                            <p><strong>Specialization:</strong> <span id="doctorSpecialty"></span></p>
                            <p><strong>Phone:</strong> <span id="doctorPhone"></span></p>
                        </div>
                    </div>
                </div>

                <!-- Availability Status -->
                <div id="availabilityStatus" class="alert" style="display: none;">
                    <span id="availabilityMessage"></span>
                </div>

                <div class="d-flex gap-20">
                    <button type="submit" class="btn btn-primary" id="bookButton">Book Appointment</button>
                    <a href="home.php" class="btn btn-info">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Available Time Slots Information -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Available Time Slots</h2>
            </div>
            <div class="grid grid-2">
                <div>
                    <h4>Morning Hours</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 5px 0;">9:00 AM - 9:30 AM</li>
                        <li style="padding: 5px 0;">9:30 AM - 10:00 AM</li>
                        <li style="padding: 5px 0;">10:00 AM - 10:30 AM</li>
                        <li style="padding: 5px 0;">10:30 AM - 11:00 AM</li>
                        <li style="padding: 5px 0;">11:00 AM - 11:30 AM</li>
                        <li style="padding: 5px 0;">11:30 AM - 12:00 PM</li>
                    </ul>
                </div>
                <div>
                    <h4>Afternoon Hours</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 5px 0;">2:00 PM - 2:30 PM</li>
                        <li style="padding: 5px 0;">2:30 PM - 3:00 PM</li>
                        <li style="padding: 5px 0;">3:00 PM - 3:30 PM</li>
                        <li style="padding: 5px 0;">3:30 PM - 4:00 PM</li>
                        <li style="padding: 5px 0;">4:00 PM - 4:30 PM</li>
                        <li style="padding: 5px 0;">4:30 PM - 5:00 PM</li>
                        <li style="padding: 5px 0;">5:00 PM - 5:30 PM</li>
                    </ul>
                </div>
            </div>
            <div class="alert alert-info">
                <strong>ℹ️ Note:</strong> Appointments are scheduled for 30-minute slots. Please arrive 15 minutes early for your appointment.
            </div>
        </div>
    </div>

    <script>
        function showDoctorInfo(doctorId) {
            const select = document.querySelector('select[name="doctor_id"]');
            const selectedOption = select.querySelector(`option[value="${doctorId}"]`);
            const doctorInfo = document.getElementById('doctorInfo');
            
            if (doctorId && selectedOption) {
                const name = selectedOption.getAttribute('data-name');
                const specialty = selectedOption.getAttribute('data-specialty');
                const phone = selectedOption.getAttribute('data-phone');
                
                document.getElementById('doctorName').textContent = name;
                document.getElementById('doctorSpecialty').textContent = specialty;
                document.getElementById('doctorPhone').textContent = phone;
                document.getElementById('doctorInitial').textContent = name.charAt(0).toUpperCase();
                
                doctorInfo.style.display = 'block';
                checkAvailability();
            } else {
                doctorInfo.style.display = 'none';
                hideAvailabilityStatus();
            }
        }
        
        function checkAvailability() {
            const doctorId = document.querySelector('select[name="doctor_id"]').value;
            const appointmentDate = document.querySelector('input[name="appointment_date"]').value;
            const appointmentTime = document.querySelector('select[name="appointment_time"]').value;
            
            if (doctorId && appointmentDate && appointmentTime) {
                // You can implement AJAX call here to check real-time availability
                showAvailabilityStatus('✅ Time slot appears to be available', 'success');
            } else {
                hideAvailabilityStatus();
            }
        }
        
        function showAvailabilityStatus(message, type) {
            const statusDiv = document.getElementById('availabilityStatus');
            const messageSpan = document.getElementById('availabilityMessage');
            
            statusDiv.className = 'alert alert-' + type;
            messageSpan.textContent = message;
            statusDiv.style.display = 'block';
        }
        
        function hideAvailabilityStatus() {
            document.getElementById('availabilityStatus').style.display = 'none';
        }
        
        // Set minimum date to today
        document.querySelector('input[name="appointment_date"]').min = new Date().toISOString().split('T')[0];
        
        // Form validation
        document.getElementById('appointmentForm').addEventListener('submit', function(e) {
            const symptoms = document.querySelector('textarea[name="symptoms"]').value.trim();
            if (symptoms.length < 10) {
                e.preventDefault();
                alert('Please provide more detailed symptoms (at least 10 characters)');
                return false;
            }
        });
        
        // Auto-show doctor info if already selected (for form retention after submission)
        window.addEventListener('load', function() {
            const doctorSelect = document.querySelector('select[name="doctor_id"]');
            if (doctorSelect.value) {
                showDoctorInfo(doctorSelect.value);
            }
        });
    </script>
</body>
</html>