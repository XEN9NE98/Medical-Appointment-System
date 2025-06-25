<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('doctor');

$database = new Database();
$db = $database->getConnection();

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$age_group = $_GET['age_group'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'name';
$sort_order = $_GET['sort_order'] ?? 'ASC';

// Build the query to get patients who have appointments with this doctor
$query = "SELECT DISTINCT p.*, 
          COUNT(a.id) as total_appointments,
          MAX(a.appointment_date) as last_appointment,
          MIN(a.appointment_date) as first_appointment,
          SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
          SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
          SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_appointments
          FROM patients p 
          INNER JOIN appointments a ON p.id = a.patient_id 
          WHERE a.doctor_id = ?";

$params = [$_SESSION['user_id']];

// Add search filter
if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add gender filter
if (!empty($gender_filter)) {
    $query .= " AND p.gender = ?";
    $params[] = $gender_filter;
}

$query .= " GROUP BY p.id";

// Add age group filter (needs to be after GROUP BY)
if (!empty($age_group)) {
    switch ($age_group) {
        case 'child':
            $query .= " HAVING TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 18";
            break;
        case 'adult':
            $query .= " HAVING TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 18 AND 64";
            break;
        case 'senior':
            $query .= " HAVING TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) >= 65";
            break;
    }
}

// Add sorting
$valid_sort_columns = ['name', 'email', 'gender', 'date_of_birth', 'total_appointments', 'last_appointment'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'name';
}
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

if ($sort_by === 'date_of_birth') {
    $query .= " ORDER BY p.date_of_birth $sort_order";
} elseif (in_array($sort_by, ['total_appointments', 'last_appointment'])) {
    $query .= " ORDER BY $sort_by $sort_order";
} else {
    $query .= " ORDER BY p.$sort_by $sort_order";
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(DISTINCT p.id) as total_patients,
    COUNT(DISTINCT CASE WHEN p.gender = 'Male' THEN p.id END) as male_patients,
    COUNT(DISTINCT CASE WHEN p.gender = 'Female' THEN p.id END) as female_patients,
    COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 18 THEN p.id END) as child_patients,
    COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 18 AND 64 THEN p.id END) as adult_patients,
    COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) >= 65 THEN p.id END) as senior_patients
    FROM patients p 
    INNER JOIN appointments a ON p.id = a.patient_id 
    WHERE a.doctor_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Function to calculate age
function calculateAge($birthDate) {
    if (!$birthDate) return 'N/A';
    $today = new DateTime();
    $age = $today->diff(new DateTime($birthDate));
    return $age->y;
}

// Function to get age group
function getAgeGroup($birthDate) {
    $age = calculateAge($birthDate);
    if ($age === 'N/A') return 'Unknown';
    if ($age < 18) return 'Child';
    if ($age >= 18 && $age < 65) return 'Adult';
    return 'Senior';
}

// Function to format date
function formatDate($date) {
    return $date ? date('M d, Y', strtotime($date)) : 'N/A';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - Medical System</title>
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
                    <li><a href="patients.php" class="active">My Patients</a></li>
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
            <h1>My Patients</h1>
            <p>Manage and view information about your patients</p>
        </div>

        <!-- Statistics -->
        <div class="grid grid-4">
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['total_patients']; ?></div>
                <div class="stats-label">Total Patients</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #3742fa 0%, #2f3cf5 100%);">
                <div class="stats-number"><?php echo $stats['male_patients']; ?></div>
                <div class="stats-label">Male Patients</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);">
                <div class="stats-number"><?php echo $stats['female_patients']; ?></div>
                <div class="stats-label">Female Patients</div>
            </div>
            <div class="stats-card" style="background: linear-gradient(135deg, #2ed573 0%, #17c0eb 100%);">
                <div class="stats-number"><?php echo $stats['child_patients'] + $stats['adult_patients'] + $stats['senior_patients']; ?></div>
                <div class="stats-label">All Age Groups</div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">Search Patients</label>
                        <input type="text" name="search" class="form-input" 
                               placeholder="Search by name, email, or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">All Genders</option>
                            <option value="Male" <?php echo $gender_filter === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $gender_filter === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Age Group</label>
                        <select name="age_group" class="form-select">
                            <option value="">All Ages</option>
                            <option value="child" <?php echo $age_group === 'child' ? 'selected' : ''; ?>>Children (&lt;18)</option>
                            <option value="adult" <?php echo $age_group === 'adult' ? 'selected' : ''; ?>>Adults (18-64)</option>
                            <option value="senior" <?php echo $age_group === 'senior' ? 'selected' : ''; ?>>Seniors (65+)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Sort By</label>
                        <select name="sort_by" class="form-select">
                            <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="date_of_birth" <?php echo $sort_by === 'date_of_birth' ? 'selected' : ''; ?>>Age</option>
                            <option value="total_appointments" <?php echo $sort_by === 'total_appointments' ? 'selected' : ''; ?>>Total Appointments</option>
                            <option value="last_appointment" <?php echo $sort_by === 'last_appointment' ? 'selected' : ''; ?>>Last Visit</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Order</label>
                        <select name="sort_order" class="form-select">
                            <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="patients.php" class="btn btn-secondary ml-10">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- View Toggle -->
        <div class="view-toggle">
            <button class="toggle-btn active" id="listViewBtn">List View</button>
            <button class="toggle-btn" id="gridViewBtn">Grid View</button>
            <div style="margin-left: auto; color: #666;">
                Showing <?php echo count($patients); ?> patients
            </div>
        </div>

        <!-- Patients List -->
        <div id="patientsContainer" class="list-view">
            <?php if (empty($patients)): ?>
                <div class="card text-center" style="padding: 60px;">
                    <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">👥</div>
                    <h2>No Patients Found</h2>
                    <p>No patients match your current search criteria.</p>
                    <?php if (!empty($search) || !empty($gender_filter) || !empty($age_group)): ?>
                        <a href="patients.php" class="btn btn-primary mt-20">Clear Filters</a>
                    <?php else: ?>
                        <p style="color: #666; margin-top: 15px;">Patients will appear here once they book appointments with you.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($patients as $patient): ?>
                <div class="patient-card">
                    <div class="patient-header">
                        <div class="patient-avatar">
                            <?php echo strtoupper(substr($patient['name'], 0, 1)); ?>
                        </div>
                        <div class="patient-info">
                            <h3><?php echo htmlspecialchars($patient['name']); ?></h3>
                            <div class="patient-details">
                                <span class="gender-icon">
                                    <?php echo $patient['gender'] === 'Male' ? '👨' : '👩'; ?>
                                </span>
                                <?php echo $patient['gender']; ?> • 
                                Age: <?php echo calculateAge($patient['date_of_birth']); ?>
                                <span class="age-badge age-<?php echo strtolower(getAgeGroup($patient['date_of_birth'])); ?>">
                                    <?php echo getAgeGroup($patient['date_of_birth']); ?>
                                </span>
                            </div>
                            <div class="patient-details">
                                📧 <?php echo htmlspecialchars($patient['email']); ?> • 
                                📞 <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?>
                            </div>
                            <?php if ($patient['last_appointment']): ?>
                                <div class="last-visit">
                                    Last visit: <?php echo formatDate($patient['last_appointment']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="patient-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $patient['total_appointments']; ?></div>
                            <div class="stat-label">Total Visits</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $patient['completed_appointments']; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $patient['pending_appointments']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $patient['approved_appointments']; ?></div>
                            <div class="stat-label">Scheduled</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo formatDate($patient['first_appointment']); ?></div>
                            <div class="stat-label">First Visit</div>
                        </div>
                    </div>
                    
                    <?php if ($patient['address']): ?>
                        <div class="emergency-contact">
                            <strong>📍 Address:</strong> <?php echo htmlspecialchars($patient['address']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="patient-actions">
                        <button class="btn btn-info" onclick="viewPatientHistory(<?php echo $patient['id']; ?>)">
                            📋 View History
                        </button>
                        <a href="appointments.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                            📅 View Appointments
                        </a>
                        <button class="btn btn-success" onclick="contactPatient('<?php echo htmlspecialchars($patient['phone']); ?>', '<?php echo htmlspecialchars($patient['email']); ?>')">
                            📞 Contact
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Patient History Modal -->
    <div id="historyModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Patient History</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="historyContent">
                <div class="text-center">
                    <div class="loading"></div>
                    <p>Loading patient history...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // View toggle functionality
        document.getElementById('listViewBtn').addEventListener('click', function() {
            document.getElementById('patientsContainer').className = 'list-view';
            this.classList.add('active');
            document.getElementById('gridViewBtn').classList.remove('active');
        });

        document.getElementById('gridViewBtn').addEventListener('click', function() {
            document.getElementById('patientsContainer').className = 'grid-view';
            this.classList.add('active');
            document.getElementById('listViewBtn').classList.remove('active');
        });

        // View patient history
        function viewPatientHistory(patientId) {
            document.getElementById('historyModal').style.display = 'block';
            
            // Simulate loading patient history (you would normally fetch this via AJAX)
            fetch(`patient_history.php?patient_id=${patientId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('historyContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('historyContent').innerHTML = `
                        <div class="alert alert-error">
                            <strong>Error:</strong> Unable to load patient history at this time.
                        </div>
                    `;
                });
        }

        // Contact patient
        function contactPatient(phone, email) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Contact Patient</h3>
                        <span class="close" onclick="this.closest('.modal').remove()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <p>Choose how you'd like to contact this patient:</p>
                        <div class="patient-actions">
                            <a href="tel:${phone}" class="btn btn-primary">📞 Call ${phone}</a>
                            <a href="mailto:${email}" class="btn btn-info">📧 Email ${email}</a>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }

        // Close modal
        function closeModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    if (modal.id !== 'historyModal') {
                        modal.remove();
                    }
                }
            });
        }

        // Auto-submit form when sort options change
        document.querySelectorAll('select[name="sort_by"], select[name="sort_order"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                    if (modal.id !== 'historyModal') {
                        modal.remove();
                    }
                });
            }
        });

        // Highlight search terms
        const searchTerm = '<?php echo addslashes($search); ?>';
        if (searchTerm) {
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            document.querySelectorAll('.patient-info, .patient-details').forEach(element => {
                element.innerHTML = element.innerHTML.replace(regex, '<mark>$1</mark>');
            });
        }
    </script>
</body>
</html>