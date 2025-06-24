<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('patient');

$database = new Database();
$db = $database->getConnection();
$appointment = new Appointment($db);

// Get patient's recent appointments
$recent_appointments = $appointment->getPatientAppointments($_SESSION['user_id']);
$recent_appointments = array_slice($recent_appointments, 0, 5); // Get only 5 recent

// Get appointment statistics
$query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM appointments WHERE patient_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming appointments
$query = "SELECT a.*, d.name as doctor_name, d.specialization 
          FROM appointments a 
          JOIN doctors d ON a.doctor_id = d.id 
          WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'
          ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 3";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Medical System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="home.php" class="logo">MedSystem</a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="home.php" class="active">Home</a></li>
                    <li><a href="appointment-form.php">Book Appointment</a></li>
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
        <!-- Welcome Section -->
        <div class="card">
            <h1>Welcome back, <?php echo $_SESSION['user_name']; ?>!</h1>
            <p>Manage your health appointments and medical records from your dashboard.</p>
        </div>

        <!-- Statistics -->
        <div class="grid grid-4">
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['total']; ?></div>
                <div class="stats-label">Total Appointments</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['pending']; ?></div>
                <div class="stats-label">Pending Appointments</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['approved']; ?></div>
                <div class="stats-label">Approved Appointments</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['completed']; ?></div>
                <div class="stats-label">Completed Appointments</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="grid grid-3">
                <a href="appointment-form.php" class="btn btn-primary">Book New Appointment</a>
                <a href="appointments.php" class="btn btn-info">View All Appointments</a>
                <a href="doctors.php" class="btn btn-success">Browse Doctors</a>
            </div>
        </div>

        <div class="grid grid-2">
            <!-- Upcoming Appointments -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Upcoming Appointments</h2>
                </div>
                <?php if (empty($upcoming_appointments)): ?>
                    <p>No upcoming appointments scheduled.</p>
                    <a href="appointment-form.php" class="btn btn-primary">Book Appointment</a>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_appointments as $apt): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($apt['doctor_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($apt['specialization']); ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $apt['status']; ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
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

            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Activities</h2>
                </div>
                <?php if (empty($recent_appointments)): ?>
                    <p>No recent appointment activities.</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recent_appointments as $apt): ?>
                        <div class="activity-item" style="padding: 15px; border-bottom: 1px solid #f1f2f6;">
                            <div class="d-flex justify-between align-center">
                                <div>
                                    <strong>Appointment with <?php echo htmlspecialchars($apt['doctor_name']); ?></strong><br>
                                    <small><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></small>
                                </div>
                                <span class="status-badge status-<?php echo $apt['status']; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Health Tips -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Health Tips</h2>
            </div>
            <div class="grid grid-3">
                <div class="tip-card" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <h4>Stay Hydrated</h4>
                    <p>Drink at least 8 glasses of water daily to maintain optimal health.</p>
                </div>
                <div class="tip-card" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <h4>Regular Exercise</h4>
                    <p>Aim for at least 30 minutes of physical activity daily.</p>
                </div>
                <div class="tip-card" style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <h4>Balanced Diet</h4>
                    <p>Include fruits, vegetables, and whole grains in your daily meals.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>