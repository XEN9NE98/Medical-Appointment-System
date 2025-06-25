<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('doctor');

$database = new Database();
$db = $database->getConnection();
$appointment = new Appointment($db);

// Get current week dates
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($current_date)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($current_date)));

// Navigation dates
$prev_week = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
$next_week = date('Y-m-d', strtotime('+1 week', strtotime($week_start)));

// Get appointments for the current week
$query = "SELECT a.*, p.name as patient_name, p.phone as patient_phone, p.gender, p.date_of_birth
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          WHERE a.doctor_id = ? AND a.appointment_date BETWEEN ? AND ?
          ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $week_start, $week_end]);
$week_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize appointments by date and time
$schedule = [];
$time_slots = [
    '08:00:00', '08:30:00', '09:00:00', '09:30:00', '10:00:00', '10:30:00',
    '11:00:00', '11:30:00', '12:00:00', '12:30:00', '13:00:00', '13:30:00',
    '14:00:00', '14:30:00', '15:00:00', '15:30:00', '16:00:00', '16:30:00',
    '17:00:00', '17:30:00'
];

foreach ($week_appointments as $apt) {
    $date = $apt['appointment_date'];
    $time = $apt['appointment_time'];
    if (!isset($schedule[$date])) {
        $schedule[$date] = [];
    }
    $schedule[$date][$time] = $apt;
}

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
    
    // Redirect to prevent form resubmission
    header('Location: schedule.php?date=' . $current_date . '&success=' . urlencode($success_message));
    exit();
}

// Get success message from URL
$success_message = isset($_GET['success']) ? $_GET['success'] : '';

// Calculate patient age
function calculateAge($birthDate) {
    $today = new DateTime();
    $age = $today->diff(new DateTime($birthDate));
    return $age->y;
}

// Get day name
function getDayName($date) {
    return date('l', strtotime($date));
}

// Get stats for the week
$week_stats = [
    'total' => count($week_appointments),
    'pending' => count(array_filter($week_appointments, function($apt) { return $apt['status'] === 'pending'; })),
    'approved' => count(array_filter($week_appointments, function($apt) { return $apt['status'] === 'approved'; })),
    'completed' => count(array_filter($week_appointments, function($apt) { return $apt['status'] === 'completed'; })),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Medical System</title>
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
                    <li><a href="schedule.php" class="active">Schedule</a></li>
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
        <!-- Page Header -->
        <div class="card">
            <h1>My Schedule</h1>
            <p>Manage your appointments and view your weekly schedule at a glance.</p>
            <?php if ($success_message): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
        </div>

        <!-- Week Statistics -->
        <div class="grid grid-4">
            <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="stats-number"><?php echo $week_stats['total']; ?></div>
                <div class="stats-label">This Week</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);">
                <div class="stats-number"><?php echo $week_stats['pending']; ?></div>
                <div class="stats-label">Pending</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #2ed573 0%, #17c0eb 100%);">
                <div class="stats-number"><?php echo $week_stats['approved']; ?></div>
                <div class="stats-label">Approved</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #3742fa 0%, #2f3cf5 100%);">
                <div class="stats-number"><?php echo $week_stats['completed']; ?></div>
                <div class="stats-label">Completed</div>
            </div>
        </div>

        <!-- View Controls -->
        <div class="view-controls">
            <a href="schedule.php?date=<?php echo $current_date; ?>" class="view-btn active">Weekly View</a>
            <a href="appointments.php" class="view-btn">List View</a>
            <a href="schedule.php?date=<?php echo date('Y-m-d'); ?>" class="view-btn">Today</a>
        </div>

        <!-- Legend -->
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);"></div>
                <span>Pending</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(135deg, #2ed573 0%, #17c0eb 100%);"></div>
                <span>Approved</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(135deg, #3742fa 0%, #2f3cf5 100%);"></div>
                <span>Completed</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);"></div>
                <span>Cancelled</span>
            </div>
        </div>

        <!-- Schedule Calendar -->
        <div class="schedule-container">
            <div class="schedule-header">
                <div class="week-navigation">
                    <a href="?date=<?php echo $prev_week; ?>" class="nav-btn">
                        ← Previous Week
                    </a>
                    <div class="week-range">
                        <?php echo date('M d', strtotime($week_start)) . ' - ' . date('M d, Y', strtotime($week_end)); ?>
                    </div>
                    <a href="?date=<?php echo $next_week; ?>" class="nav-btn">
                        Next Week →
                    </a>
                </div>
                <div>
                    <a href="?date=<?php echo date('Y-m-d'); ?>" class="nav-btn">Today</a>
                </div>
            </div>

            <div class="schedule-grid">
                <!-- Time column header -->
                <div class="time-header">Time</div>
                
                <!-- Day headers -->
                <?php
                for ($i = 0; $i < 7; $i++) {
                    $current_day = date('Y-m-d', strtotime($week_start . ' +' . $i . ' days'));
                    $day_name = date('D', strtotime($current_day));
                    $day_num = date('j', strtotime($current_day));
                    $is_today = $current_day === date('Y-m-d');
                    $is_weekend = in_array(date('w', strtotime($current_day)), [0, 6]);
                    
                    $header_class = 'day-header';
                    if ($is_today) $header_class .= ' today';
                    elseif ($is_weekend) $header_class .= ' weekend';
                ?>
                <div class="<?php echo $header_class; ?>">
                    <div><?php echo $day_name; ?></div>
                    <div class="day-date"><?php echo $day_num; ?></div>
                </div>
                <?php } ?>

                <!-- Time slots and appointments -->
                <?php foreach ($time_slots as $time): ?>
                    <div class="time-slot">
                        <?php echo date('g:i A', strtotime($time)); ?>
                    </div>
                    
                    <?php for ($i = 0; $i < 7; $i++): 
                        $current_day = date('Y-m-d', strtotime($week_start . ' +' . $i . ' days'));
                        $appointment = isset($schedule[$current_day][$time]) ? $schedule[$current_day][$time] : null;
                    ?>
                    <div class="schedule-cell" data-date="<?php echo $current_day; ?>" data-time="<?php echo $time; ?>">
                        <?php if ($appointment): ?>
                            <div class="appointment-block <?php echo $appointment['status']; ?>" 
                                 onclick="showAppointmentModal(<?php echo htmlspecialchars(json_encode($appointment)); ?>)">
                                <div class="patient-name"><?php echo htmlspecialchars(substr($appointment['patient_name'], 0, 12)); ?></div>
                                <div class="appointment-details">
                                    <?php echo calculateAge($appointment['date_of_birth']); ?>y, <?php echo $appointment['gender']; ?><br>
                                    <?php echo ucfirst($appointment['status']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="grid grid-4">
                <a href="appointments.php?filter=pending" class="btn btn-warning">Review Pending</a>
                <a href="appointments.php?filter=today" class="btn btn-info">Today's Appointments</a>
                <a href="appointments.php" class="btn btn-primary">All Appointments</a>
                <a href="patients.php" class="btn btn-success">My Patients</a>
            </div>
        </div>
    </div>

    <!-- Appointment Modal -->
    <div class="appointment-modal" id="appointmentModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeAppointmentModal()">&times;</span>
            <div id="modalBody">
                <!-- Modal content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function showAppointmentModal(appointment) {
            const modal = document.getElementById('appointmentModal');
            const modalBody = document.getElementById('modalBody');
            
            // Calculate age
            const birthDate = new Date(appointment.date_of_birth);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            
            // Format date and time
            const appointmentDate = new Date(appointment.appointment_date);
            const formattedDate = appointmentDate.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const appointmentTime = new Date('2000-01-01 ' + appointment.appointment_time);
            const formattedTime = appointmentTime.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            
            // Generate status badge
            let statusClass = '';
            let statusText = appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1);
            switch(appointment.status) {
                case 'pending': statusClass = 'background: #fff3cd; color: #856404;'; break;
                case 'approved': statusClass = 'background: #d4edda; color: #155724;'; break;
                case 'completed': statusClass = 'background: #cce7ff; color: #004085;'; break;
                case 'cancelled': statusClass = 'background: #f8d7da; color: #721c24;'; break;
            }
            
            modalBody.innerHTML = `
                <h2 style="margin-bottom: 20px; color: #333;">Appointment Details</h2>
                
                <div class="appointment-status" style="${statusClass}">
                    ${statusText}
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 15px 0; color: #333;">Patient Information</h3>
                    <p style="margin: 5px 0;"><strong>Name:</strong> ${appointment.patient_name}</p>
                    <p style="margin: 5px 0;"><strong>Age:</strong> ${age} years</p>
                    <p style="margin: 5px 0;"><strong>Gender:</strong> ${appointment.gender}</p>
                    <p style="margin: 5px 0;"><strong>Phone:</strong> ${appointment.patient_phone}</p>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 15px 0; color: #333;">Appointment Details</h3>
                    <p style="margin: 5px 0;"><strong>Date:</strong> ${formattedDate}</p>
                    <p style="margin: 5px 0;"><strong>Time:</strong> ${formattedTime}</p>
                    <p style="margin: 5px 0;"><strong>Status:</strong> ${statusText}</p>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                    <h3 style="margin: 0 0 15px 0; color: #333;">Symptoms/Reason</h3>
                    <p style="margin: 0; line-height: 1.6;">${appointment.symptoms || 'No symptoms recorded'}</p>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    ${appointment.status === 'pending' ? `
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="${appointment.id}">
                            <button type="submit" name="action" value="approve" class="btn btn-success">
                                ✓ Approve
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="${appointment.id}">
                            <button type="submit" name="action" value="cancel" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                ✗ Cancel
                            </button>
                        </form>
                    ` : ''}
                    
                    ${appointment.status === 'approved' ? `
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="${appointment.id}">
                            <button type="submit" name="action" value="complete" class="btn btn-info">
                                ✓ Mark Complete
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="appointment_id" value="${appointment.id}">
                            <button type="submit" name="action" value="cancel" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                ✗ Cancel
                            </button>
                        </form>
                    ` : ''}
                    
                    <button onclick="closeAppointmentModal()" class="btn btn-cancel">Close</button>
                </div>
            `;
            
            modal.style.display = 'flex';
        }
        
        function closeAppointmentModal() {
            document.getElementById('appointmentModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('appointmentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAppointmentModal();
            }
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAppointmentModal();
            }
        });
        
        // Add hover effects to empty cells
        document.querySelectorAll('.schedule-cell').forEach(cell => {
            if (!cell.querySelector('.appointment-block')) {
                cell.addEventListener('mouseenter', function() {
                    this.style.background = '#f0f8ff';
                    this.style.cursor = 'pointer';
                });
                
                cell.addEventListener('mouseleave', function() {
                    this.style.background = 'white';
                });
            }
        });
        
        // Auto refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Add animation to appointment blocks
        document.querySelectorAll('.appointment-block').forEach(block => {
            block.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
                this.style.zIndex = '10';
            });
            
            block.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.zIndex = '1';
            });
        });
        
        // Smooth scrolling for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Show loading state for form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = form.querySelector('button[type="submit"]');
                if (button) {
                    const originalText = button.textContent;
                    button.textContent = 'Processing...';
                    button.disabled = true;
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.disabled = false;
                    }, 3000);
                }
            });
        });
    </script>
</body>
</html>