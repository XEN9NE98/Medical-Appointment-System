<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('doctor');

$database = new Database();
$db = $database->getConnection();
$appointment = new Appointment($db);

// Get doctor's appointments
$all_appointments = $appointment->getDoctorAppointments($_SESSION['user_id']);

// Get appointment statistics
$query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM appointments WHERE doctor_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's appointments
$query = "SELECT a.*, p.name as patient_name, p.phone as patient_phone, p.gender, p.date_of_birth
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
          ORDER BY a.appointment_time ASC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending appointments for approval
$query = "SELECT a.*, p.name as patient_name, p.phone as patient_phone, p.gender, p.date_of_birth
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          WHERE a.doctor_id = ? AND a.status = 'pending'
          ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$pending_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments (next 7 days)
$query = "SELECT a.*, p.name as patient_name, p.phone as patient_phone
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          WHERE a.doctor_id = ? AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
          AND a.status IN ('approved', 'completed')
          ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle appointment status updates
if ($_POST && isset($_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $appointment->updateStatus($appointment_id, 'approved');
        $success_message = "Appointment approved successfully!";
    } elseif ($action === 'complete') {
        $appointment->updateStatus($appointment_id, 'completed');
        $success_message = "Appointment marked as completed!";
    } elseif ($action === 'cancel') {
        $appointment->updateStatus($appointment_id, 'cancelled');
        $success_message = "Appointment cancelled!";
    }
    
    // Refresh the page to show updated data
    header('Location: home.php');
    exit();
}

// Calculate patient age function
function calculateAge($birthDate) {
    $today = new DateTime();
    $age = $today->diff(new DateTime($birthDate));
    return $age->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Medical System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="home.php" class="logo">MedSystem</a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="home.php" class="active">Dashboard</a></li>
                    <li><a href="appointments.php">All Appointments</a></li>
                    <li><a href="patients.php">My Patients</a></li>
                    <li><a href="schedule.php">Schedule</a></li>
                    <li><a href="profile.php">Profile</a></li>
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
        <!-- Welcome Section -->
        <div class="card">
            <h1>Welcome back, Dr. <?php echo $_SESSION['user_name']; ?>!</h1>
            <p>Manage your appointments, patients, and medical practice from your dashboard.</p>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
            <?php endif; ?>
        </div>

        <!-- Statistics -->
        <div class="grid grid-4">
            <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="stats-number"><?php echo $stats['total']; ?></div>
                <div class="stats-label">Total Appointments</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);">
                <div class="stats-number"><?php echo $stats['pending']; ?></div>
                <div class="stats-label">Pending Approvals</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #2ed573 0%, #17c0eb 100%);">
                <div class="stats-number"><?php echo $stats['approved']; ?></div>
                <div class="stats-label">Approved Appointments</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #3742fa 0%, #2f3cf5 100%);">
                <div class="stats-number"><?php echo $stats['completed']; ?></div>
                <div class="stats-label">Completed</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="grid grid-4">
                <a href="appointments.php" class="btn btn-primary">View All Appointments</a>
                <a href="patients.php" class="btn btn-info">Manage Patients</a>
                <a href="schedule.php" class="btn btn-success">My Schedule</a>
                <a href="profile.php" class="btn btn-warning">Update Profile</a>
            </div>
        </div>

        <div class="grid grid-2">
            <!-- Today's Appointments -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Today's Appointments (<?php echo date('M d, Y'); ?>)</h2>
                </div>
                <?php if (empty($today_appointments)): ?>
                    <div class="text-center" style="padding: 40px;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">📅</div>
                        <p>No appointments scheduled for today.</p>
                        <p style="color: #666; font-size: 0.9rem;">Enjoy your free day!</p>
                    </div>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach ($today_appointments as $apt): ?>
                        <div class="appointment-item" style="padding: 15px; border-bottom: 1px solid #f1f2f6; border-left: 4px solid <?php 
                            echo $apt['status'] === 'pending' ? '#ffa502' : 
                                ($apt['status'] === 'approved' ? '#2ed573' : 
                                ($apt['status'] === 'completed' ? '#3742fa' : '#ff4757')); 
                        ?>;">
                            <div class="d-flex justify-between align-center">
                                <div>
                                    <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($apt['patient_name']); ?></h4>
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                                        <strong>Time:</strong> <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?><br>
                                        <strong>Age:</strong> <?php echo calculateAge($apt['date_of_birth']); ?> years 
                                        (<?php echo ucfirst($apt['gender']); ?>)<br>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($apt['patient_phone']); ?>
                                    </p>
                                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555;">
                                        <strong>Symptoms:</strong> <?php echo htmlspecialchars(substr($apt['symptoms'], 0, 100)) . (strlen($apt['symptoms']) > 100 ? '...' : ''); ?>
                                    </p>
                                </div>
                                <div class="appointment-actions">
                                    <span class="status-badge status-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                    <?php if ($apt['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline; margin-left: 10px;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-success" style="padding: 5px 12px; font-size: 0.8rem;">Approve</button>
                                        </form>
                                    <?php elseif ($apt['status'] === 'approved'): ?>
                                        <form method="POST" style="display: inline; margin-left: 10px;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                            <button type="submit" name="action" value="complete" class="btn btn-info" style="padding: 5px 12px; font-size: 0.8rem;">Complete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pending Appointments for Approval -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Pending Approvals</h2>
                </div>
                <?php if (empty($pending_appointments)): ?>
                    <div class="text-center" style="padding: 40px;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">✅</div>
                        <p>No pending appointments to approve.</p>
                        <p style="color: #666; font-size: 0.9rem;">All caught up!</p>
                    </div>
                <?php else: ?>
                    <div class="pending-list">
                        <?php foreach ($pending_appointments as $apt): ?>
                        <div class="pending-item" style="padding: 15px; border-bottom: 1px solid #f1f2f6; background: #fff3cd; border-radius: 8px; margin-bottom: 10px;">
                            <div class="d-flex justify-between align-center">
                                <div>
                                    <h4 style="margin-bottom: 5px; color: #856404;"><?php echo htmlspecialchars($apt['patient_name']); ?></h4>
                                    <p style="margin: 0; color: #856404; font-size: 0.9rem;">
                                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?><br>
                                        <strong>Time:</strong> <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?><br>
                                        <strong>Age:</strong> <?php echo calculateAge($apt['date_of_birth']); ?> years
                                    </p>
                                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #856404;">
                                        <strong>Reason:</strong> <?php echo htmlspecialchars(substr($apt['symptoms'], 0, 80)) . (strlen($apt['symptoms']) > 80 ? '...' : ''); ?>
                                    </p>
                                </div>
                                <div class="pending-actions d-flex gap-10">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success" style="padding: 8px 16px;">
                                            ✓ Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                        <button type="submit" name="action" value="cancel" class="btn btn-danger" style="padding: 8px 16px;" 
                                                onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                            ✗ Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-20">
                        <a href="appointments.php?filter=pending" class="btn btn-warning">View All Pending</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Upcoming Appointments (Next 7 Days)</h2>
            </div>
            <?php if (empty($upcoming_appointments)): ?>
                <p>No upcoming appointments in the next 7 days.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $apt): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($apt['patient_phone']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($apt['status'] === 'approved'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                            <button type="submit" name="action" value="complete" class="btn btn-info" style="padding: 5px 10px; font-size: 0.8rem;">
                                                Complete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #666; font-size: 0.8rem;">No action needed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-20">
                    <a href="appointments.php" class="btn btn-info">View All Appointments</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Practice Insights -->
        <div class="grid grid-3">
            <div class="card" style="text-align: center;">
                <h3>Patient Satisfaction</h3>
                <div style="font-size: 3rem; color: #2ed573; margin: 20px 0;">😊</div>
                <p>Keep up the great work!</p>
                <small style="color: #666;">Based on patient feedback</small>
            </div>
            
            <div class="card" style="text-align: center;">
                <h3>Today's Status</h3>
                <div style="font-size: 3rem; color: #667eea; margin: 20px 0;">
                    <?php echo count($today_appointments) > 0 ? '👨‍⚕️' : '🏖️'; ?>
                </div>
                <p><?php echo count($today_appointments) > 0 ? 'Busy day ahead!' : 'Relaxing day!'; ?></p>
                <small style="color: #666;"><?php echo count($today_appointments); ?> appointments today</small>
            </div>
            
            <div class="card" style="text-align: center;">
                <h3>Quick Tip</h3>
                <div style="font-size: 3rem; color: #ffa502; margin: 20px 0;">💡</div>
                <p>Stay organized</p>
                <small style="color: #666;">Review pending appointments regularly for better patient care</small>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh page every 5 minutes to show updated appointment data
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes

        // Add confirmation for critical actions
        document.querySelectorAll('form button[value="cancel"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

        // Show loading state when submitting forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = form.querySelector('button[type="submit"]');
                const originalText = button.textContent;
                button.textContent = 'Processing...';
                button.disabled = true;
                
                // Re-enable button after 3 seconds in case of network issues
                setTimeout(() => {
                    button.textContent = originalText;
                    button.disabled = false;
                }, 3000);
            });
        });

        // Add hover effects to appointment items
        document.querySelectorAll('.appointment-item, .pending-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
                this.style.transition = 'all 0.2s ease';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>