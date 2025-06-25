<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('doctor');

$database = new Database();
$db = $database->getConnection();
$appointment = new Appointment($db);

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
    } elseif ($action === 'add_notes') {
        $notes = $_POST['medical_notes'];
        $query = "UPDATE appointments SET medical_notes = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$notes, $appointment_id]);
        $success_message = "Medical notes added successfully!";
    }
    
    // Refresh the page to show updated data
    header('Location: appointments.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
    exit();
}

// Get filters
$status_filter = $_GET['filter'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query based on filters
$where_conditions = ["a.doctor_id = ?"];
$params = [$_SESSION['user_id']];

if ($status_filter !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
}

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.phone LIKE ? OR a.symptoms LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get appointments with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$query = "SELECT a.*, p.name as patient_name, p.phone as patient_phone, p.gender, p.date_of_birth, p.address
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          WHERE $where_clause
          ORDER BY a.appointment_date DESC, a.appointment_time DESC
          LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM appointments a 
                JOIN patients p ON a.patient_id = p.id 
                WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_appointments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_appointments / $limit);

// Get appointment statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE doctor_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate patient age function
function calculateAge($birthDate) {
    if (!$birthDate) return 'N/A';
    $today = new DateTime();
    $age = $today->diff(new DateTime($birthDate));
    return $age->y;
}

// Get status color
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return '#ffa502';
        case 'approved': return '#2ed573';
        case 'completed': return '#3742fa';
        case 'cancelled': return '#ff4757';
        default: return '#666';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Appointments - Medical System</title>
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
                    <li><a href="appointments.php" class="active">All Appointments</a></li>
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
        <!-- Page Header -->
        <div class="card">
            <h1>All Appointments</h1>
            <p>Manage and track all your patient appointments</p>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
            <?php endif; ?>
        </div>

        <!-- Statistics Overview -->
        <div class="grid grid-5">
            <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="stats-number"><?php echo $stats['total']; ?></div>
                <div class="stats-label">Total</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);">
                <div class="stats-number"><?php echo $stats['pending']; ?></div>
                <div class="stats-label">Pending</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #2ed573 0%, #17c0eb 100%);">
                <div class="stats-number"><?php echo $stats['approved']; ?></div>
                <div class="stats-label">Approved</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #3742fa 0%, #2f3cf5 100%);">
                <div class="stats-number"><?php echo $stats['completed']; ?></div>
                <div class="stats-label">Completed</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);">
                <div class="stats-number"><?php echo $stats['cancelled']; ?></div>
                <div class="stats-label">Cancelled</div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Filter Appointments</h2>
            </div>
            <form method="GET" class="search-form">
                <div class="grid grid-4">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="filter" class="form-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-input" value="<?php echo $date_filter; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-input" placeholder="Patient name, phone, or symptoms..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="appointments.php" class="btn btn-secondary ml-10">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Filter Buttons -->
        <div class="card">
            <div class="d-flex gap-10" style="flex-wrap: wrap;">
                <a href="appointments.php" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">
                    All (<?php echo $stats['total']; ?>)
                </a>
                <a href="appointments.php?filter=pending" class="btn <?php echo $status_filter === 'pending' ? 'btn-warning' : 'btn-secondary'; ?>">
                    Pending (<?php echo $stats['pending']; ?>)
                </a>
                <a href="appointments.php?filter=approved" class="btn <?php echo $status_filter === 'approved' ? 'btn-success' : 'btn-secondary'; ?>">
                    Approved (<?php echo $stats['approved']; ?>)
                </a>
                <a href="appointments.php?filter=completed" class="btn <?php echo $status_filter === 'completed' ? 'btn-info' : 'btn-secondary'; ?>">
                    Completed (<?php echo $stats['completed']; ?>)
                </a>
                <a href="appointments.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-secondary">
                    Today
                </a>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    Appointments 
                    <?php if ($status_filter !== 'all'): ?>
                        - <?php echo ucfirst($status_filter); ?>
                    <?php endif; ?>
                    (<?php echo $total_appointments; ?> total)
                </h2>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="text-center" style="padding: 60px;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📅</div>
                    <h3>No appointments found</h3>
                    <p style="color: #666;">Try adjusting your filters or check back later.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Patient Info</th>
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
                                    <span style="color: #666;"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong><br>
                                        <small style="color: #666;">
                                            Age: <?php echo calculateAge($apt['date_of_birth']); ?> | 
                                            <?php echo ucfirst($apt['gender']); ?><br>
                                            📞 <?php echo htmlspecialchars($apt['patient_phone']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td class="symptoms-cell">
                                    <div style="max-height: 60px; overflow-y: auto;">
                                        <?php echo htmlspecialchars($apt['symptoms']); ?>
                                    </div>
                                    <?php if ($apt['medical_notes']): ?>
                                        <hr style="margin: 8px 0;">
                                        <strong>Notes:</strong><br>
                                        <small style="color: #2ed573;"><?php echo htmlspecialchars($apt['medical_notes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($apt['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                                    ✓ Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                <button type="submit" name="action" value="cancel" class="btn btn-danger" 
                                                        onclick="return confirm('Cancel this appointment?')">
                                                    ✗ Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($apt['status'] === 'approved'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                <button type="submit" name="action" value="complete" class="btn btn-info">
                                                    ✓ Complete
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-warning" onclick="showNotesModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['medical_notes'] ?? ''); ?>')">
                                                📝 Notes
                                            </button>
                                        <?php elseif ($apt['status'] === 'completed'): ?>
                                            <button type="button" class="btn btn-info" onclick="showNotesModal(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['medical_notes'] ?? ''); ?>')">
                                                📝 Notes
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #666; font-size: 0.8rem;">No actions</span>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-secondary" onclick="showPatientDetails(<?php echo htmlspecialchars(json_encode($apt)); ?>)">
                                            👤 Details
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">← Previous</a>
                    <?php endif; ?>
                    
                    <span style="margin: 0 15px;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">Next →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Medical Notes Modal -->
    <div id="notesModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Medical Notes</h3>
                <span class="close" onclick="closeNotesModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="notesAppointmentId">
                    <div class="form-group">
                        <label class="form-label">Medical Notes</label>
                        <textarea name="medical_notes" id="medicalNotes" class="form-textarea" rows="6" 
                                placeholder="Add your medical notes, diagnosis, prescriptions, etc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeNotesModal()">Cancel</button>
                    <button type="submit" name="action" value="add_notes" class="btn btn-primary">Save Notes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <div id="patientModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Patient Details</h3>
                <span class="close" onclick="closePatientModal()">&times;</span>
            </div>
            <div class="modal-body" id="patientDetails">
                <!-- Patient details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePatientModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function showNotesModal(appointmentId, currentNotes) {
            document.getElementById('notesAppointmentId').value = appointmentId;
            document.getElementById('medicalNotes').value = currentNotes;
            document.getElementById('notesModal').style.display = 'block';
        }

        function closeNotesModal() {
            document.getElementById('notesModal').style.display = 'none';
        }

        function showPatientDetails(appointment) {
            const modal = document.getElementById('patientModal');
            const detailsDiv = document.getElementById('patientDetails');
            
            const age = appointment.date_of_birth ? calculateAge(appointment.date_of_birth) : 'N/A';
            
            detailsDiv.innerHTML = `
                <div class="patient-info-grid">
                    <div class="patient-info-item">
                        <strong>Name:</strong> ${appointment.patient_name}
                    </div>
                    <div class="patient-info-item">
                        <strong>Phone:</strong> ${appointment.patient_phone}
                    </div>
                    <div class="patient-info-item">
                        <strong>Gender:</strong> ${appointment.gender}
                    </div>
                    <div class="patient-info-item">
                        <strong>Age:</strong> ${age} years
                    </div>
                    <div class="patient-info-item">
                        <strong>Date of Birth:</strong> ${appointment.date_of_birth || 'N/A'}
                    </div>
                    <div class="patient-info-item">
                        <strong>Address:</strong> ${appointment.address || 'N/A'}
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <strong>Appointment Details:</strong><br>
                    <p><strong>Date:</strong> ${new Date(appointment.appointment_date).toLocaleDateString()}</p>
                    <p><strong>Time:</strong> ${appointment.appointment_time}</p>
                    <p><strong>Status:</strong> <span class="status-badge status-${appointment.status}">${appointment.status}</span></p>
                    <p><strong>Symptoms:</strong></p>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px;">
                        ${appointment.symptoms}
                    </div>
                    ${appointment.medical_notes ? `
                        <p style="margin-top: 15px;"><strong>Medical Notes:</strong></p>
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin-top: 10px;">
                            ${appointment.medical_notes}
                        </div>
                    ` : ''}
                </div>
            `;
            
            modal.style.display = 'block';
        }

        function closePatientModal() {
            document.getElementById('patientModal').style.display = 'none';
        }

        function calculateAge(birthDate) {
            if (!birthDate) return 'N/A';
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            return age;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const notesModal = document.getElementById('notesModal');
            const patientModal = document.getElementById('patientModal');
            if (event.target === notesModal) {
                closeNotesModal();
            }
            if (event.target === patientModal) {
                closePatientModal();
            }
        }

        // Add loading states to form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = form.querySelector('button[type="submit"]');
                if (button) {
                    const originalText = button.textContent;
                    button.textContent = 'Processing...';
                    button.disabled = true;
                    
                    // Re-enable after 3 seconds in case of issues
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.disabled = false;
                    }, 3000);
                }
            });
        });

        // Add confirmation for critical actions
        document.querySelectorAll('button[value="cancel"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

        // Auto-hide success messages after 5 seconds
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.opacity = '0';
                setTimeout(() => {
                    successAlert.remove();
                }, 300);
            }, 5000);
        }
    </script>
</body>
</html>