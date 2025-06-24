<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('patient');

$database = new Database();
$db = $database->getConnection();
$appointment = new Appointment($db);

$message = '';

// Handle appointment cancellation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    
    // Verify the appointment belongs to the current patient
    $query = "SELECT * FROM appointments WHERE id = ? AND patient_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$appointment_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        $apt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Only allow cancellation if status is pending or approved and appointment is in future
        if (($apt['status'] == 'pending' || $apt['status'] == 'approved') && 
            strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time']) > time()) {
            
            if ($appointment->updateStatus($appointment_id, 'cancelled')) {
                $message = '<div class="alert alert-success">✅ Appointment cancelled successfully!</div>';
            } else {
                $message = '<div class="alert alert-error">❌ Failed to cancel appointment. Please try again.</div>';
            }
        } else {
            $message = '<div class="alert alert-error">❌ Cannot cancel this appointment.</div>';
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';

// Build query based on filters
$where_conditions = ["a.patient_id = ?"];
$params = [$_SESSION['user_id']];

if ($status_filter !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($date_filter === 'upcoming') {
    $where_conditions[] = "a.appointment_date >= CURDATE()";
} elseif ($date_filter === 'past') {
    $where_conditions[] = "a.appointment_date < CURDATE()";
}

$where_clause = implode(' AND ', $where_conditions);

// Get appointments with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total appointments for pagination
$count_query = "SELECT COUNT(*) as total FROM appointments a WHERE " . $where_clause;
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_appointments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_appointments / $per_page);

// Get appointments
$query = "SELECT a.*, d.name as doctor_name, d.specialization, d.phone as doctor_phone 
          FROM appointments a 
          JOIN doctors d ON a.doctor_id = d.id 
          WHERE " . $where_clause . "
          ORDER BY a.appointment_date DESC, a.appointment_time DESC 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get appointment statistics for current filters
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments a WHERE a.patient_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Medical System</title>
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
                    <li><a href="appointments.php" class="active">My Appointments</a></li>
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
        <!-- Page Header -->
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">My Appointments</h1>
                <p>View and manage all your medical appointments.</p>
            </div>
            <div class="d-flex justify-between align-center">
                <a href="appointment-form.php" class="btn btn-primary">Book New Appointment</a>
                <div class="d-flex gap-10">
                    <button onclick="window.print()" class="btn btn-info">Print List</button>
                    <button onclick="exportAppointments()" class="btn btn-success">Export</button>
                </div>
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Statistics -->
        <div class="grid grid-5">
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['total']; ?></div>
                <div class="stats-label">Total</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['pending']; ?></div>
                <div class="stats-label">Pending</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['approved']; ?></div>
                <div class="stats-label">Approved</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['completed']; ?></div>
                <div class="stats-label">Completed</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['cancelled']; ?></div>
                <div class="stats-label">Cancelled</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Filter Appointments</h2>
            </div>
            <form method="GET" action="" class="grid grid-3">
                <div class="form-group">
                    <label class="form-label">Filter by Status</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Filter by Date</label>
                    <select name="date" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Dates</option>
                        <option value="upcoming" <?php echo $date_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="past" <?php echo $date_filter === 'past' ? 'selected' : ''; ?>>Past</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <a href="appointments.php" class="btn btn-info">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Appointments List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    Appointments 
                    <?php if ($status_filter !== 'all' || $date_filter !== 'all'): ?>
                        <small style="font-weight: normal; color: #666;">
                            (Filtered: <?php echo ucfirst($status_filter); ?> 
                            <?php echo $date_filter !== 'all' ? '- ' . ucfirst($date_filter) : ''; ?>)
                        </small>
                    <?php endif; ?>
                </h2>
                <p>Showing <?php echo count($appointments); ?> of <?php echo $total_appointments; ?> appointments</p>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="text-center p-20">
                    <h3>No appointments found</h3>
                    <p>
                        <?php if ($status_filter !== 'all' || $date_filter !== 'all'): ?>
                            No appointments match your current filters.
                        <?php else: ?>
                            You haven't booked any appointments yet.
                        <?php endif; ?>
                    </p>
                    <a href="appointment-form.php" class="btn btn-primary">Book Your First Appointment</a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Symptoms</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $apt): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></strong><br>
                                    <small><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                    <?php
                                    $appointment_datetime = strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time']);
                                    $now = time();
                                    if ($appointment_datetime > $now && $apt['status'] !== 'cancelled') {
                                        $diff = $appointment_datetime - $now;
                                        if ($diff < 86400) { // Less than 24 hours
                                            echo '<br><span style="color: #ff4757; font-weight: bold;">Today!</span>';
                                        } elseif ($diff < 172800) { // Less than 48 hours
                                            echo '<br><span style="color: #ffa502; font-weight: bold;">Tomorrow</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($apt['doctor_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($apt['specialization']); ?></small><br>
                                    <small>📞 <?php echo htmlspecialchars($apt['doctor_phone']); ?></small>
                                </td>
                                <td>
                                    <div style="max-width: 200px;">
                                        <?php 
                                        $symptoms = htmlspecialchars($apt['symptoms']);
                                        echo strlen($symptoms) > 100 ? substr($symptoms, 0, 100) . '...' : $symptoms;
                                        ?>
                                        <?php if (strlen($symptoms) > 100): ?>
                                            <br><a href="#" onclick="showFullSymptoms('<?php echo $apt['id']; ?>')" style="color: #667eea; font-size: 0.9rem;">Read more</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                    <?php if ($apt['status'] === 'pending'): ?>
                                        <br><small style="color: #666; margin-top: 5px;">Waiting for approval</small>
                                    <?php elseif ($apt['status'] === 'approved'): ?>
                                        <br><small style="color: #2ed573; margin-top: 5px;">Confirmed</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-10" style="flex-direction: column;">
                                        <?php
                                        $appointment_datetime = strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time']);
                                        $can_cancel = ($apt['status'] == 'pending' || $apt['status'] == 'approved') && 
                                                     $appointment_datetime > time();
                                        ?>
                                        
                                        <button onclick="viewAppointment(<?php echo $apt['id']; ?>)" 
                                                class="btn btn-info" style="font-size: 0.8rem; padding: 5px 10px;">
                                            View Details
                                        </button>
                                        
                                        <?php if ($can_cancel): ?>
                                            <form method="POST" action="" style="margin: 0;" 
                                                  onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                                <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                <button type="submit" name="cancel_appointment" 
                                                        class="btn btn-danger" style="font-size: 0.8rem; padding: 5px 10px;">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($apt['status'] === 'completed'): ?>
                                            <button onclick="downloadReport(<?php echo $apt['id']; ?>)" 
                                                    class="btn btn-success" style="font-size: 0.8rem; padding: 5px 10px;">
                                                Get Report
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- Hidden full symptoms for modal -->
                            <div id="symptoms-<?php echo $apt['id']; ?>" style="display: none;">
                                <?php echo htmlspecialchars($apt['symptoms']); ?>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination" style="text-align: center; margin-top: 20px;">
                    <?php
                    $base_url = "appointments.php?";
                    if ($status_filter !== 'all') $base_url .= "status=" . $status_filter . "&";
                    if ($date_filter !== 'all') $base_url .= "date=" . $date_filter . "&";
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $base_url; ?>page=<?php echo ($page - 1); ?>" class="btn btn-info">Previous</a>
                    <?php endif; ?>
                    
                    <span style="margin: 0 20px;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $base_url; ?>page=<?php echo ($page + 1); ?>" class="btn btn-info">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Appointment Guidelines -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Appointment Guidelines</h2>
            </div>
            <div class="grid grid-2">
                <div>
                    <h4>📋 Before Your Appointment</h4>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="padding: 5px 0;">✓ Arrive 15 minutes early</li>
                        <li style="padding: 5px 0;">✓ Bring valid ID and insurance card</li>
                        <li style="padding: 5px 0;">✓ List current medications</li>
                        <li style="padding: 5px 0;">✓ Prepare questions for your doctor</li>
                    </ul>
                </div>
                <div>
                    <h4>⏰ Cancellation Policy</h4>
                    <ul style="list-style: none; padding-left: 0;">
                        <li style="padding: 5px 0;">• Cancel at least 24 hours in advance</li>
                        <li style="padding: 5px 0;">• No-show appointments may incur fees</li>
                        <li style="padding: 5px 0;">• Emergency cancellations are accepted</li>
                        <li style="padding: 5px 0;">• Contact us if you need to reschedule</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for appointment details -->
    <div id="appointmentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Appointment Details</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="modalContent"></div>
        </div>
    </div>

    <script>
        function viewAppointment(appointmentId) {
            // Find the appointment data from the table
            const rows = document.querySelectorAll('table tbody tr');
            for (let row of rows) {
                if (row.innerHTML.includes('appointment_id" value="' + appointmentId)) {
                    const cells = row.cells;
                    const dateTime = cells[0].innerHTML;
                    const doctor = cells[1].innerHTML;
                    const symptoms = document.getElementById('symptoms-' + appointmentId).innerHTML;
                    const status = cells[3].innerHTML;
                    
                    document.getElementById('modalContent').innerHTML = `
                        <div style="margin-bottom: 15px;">
                            <strong>Date & Time:</strong><br>
                            ${dateTime}
                        </div>
                        <div style="margin-bottom: 15px;">
                            <strong>Doctor:</strong><br>
                            ${doctor}
                        </div>
                        <div style="margin-bottom: 15px;">
                            <strong>Status:</strong><br>
                            ${status}
                        </div>
                        <div style="margin-bottom: 15px;">
                            <strong>Symptoms/Reason:</strong><br>
                            ${symptoms}
                        </div>
                    `;
                    break;
                }
            }
            
            document.getElementById('appointmentModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('appointmentModal').style.display = 'none';
        }
        
        function showFullSymptoms(appointmentId) {
            const symptoms = document.getElementById('symptoms-' + appointmentId).innerHTML;
            alert(symptoms);
        }
        
        function exportAppointments() {
            // Simple CSV export
            let csv = 'Date,Time,Doctor,Specialization,Symptoms,Status\n';
            
            <?php foreach ($appointments as $apt): ?>
            csv += '"<?php echo date('Y-m-d', strtotime($apt['appointment_date'])); ?>",';
            csv += '"<?php echo date('H:i', strtotime($apt['appointment_time'])); ?>",';
            csv += '"<?php echo htmlspecialchars($apt['doctor_name']); ?>",';
            csv += '"<?php echo htmlspecialchars($apt['specialization']); ?>",';
            csv += '"<?php echo str_replace('"', '""', htmlspecialchars($apt['symptoms'])); ?>",';
            csv += '"<?php echo $apt['status']; ?>"\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'my_appointments_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        function downloadReport(appointmentId) {
            alert('Medical report download feature will be implemented with doctor\'s prescription system.');
        }
        
        // Close modal when clicking outside
        document.getElementById('appointmentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Auto-refresh page every 5 minutes to update appointment status
        setInterval(function() {
            if (document.hidden === false) {
                location.reload();
            }
        }, 300000); // 5 minutes
    </script>

    <style>
        .grid-5 {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
        
        @media print {
            .header, .btn, form, .pagination {
                display: none !important;
            }
            
            .card {
                box-shadow: none !important;
                border: 1px solid #ccc;
            }
            
            body {
                background: white !important;
            }
        }
        
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .stats-card:hover {
            cursor: pointer;
        }
    </style>
</body>
</html>