<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('patient');

$database = new Database();
$db = $database->getConnection();
$doctor = new Doctor($db);

// Get all doctors
$doctors = $doctor->getAll();

// Search functionality
$search = '';
$filtered_doctors = $doctors;

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $filtered_doctors = array_filter($doctors, function($doc) use ($search) {
        return stripos($doc['name'], $search) !== false || 
               stripos($doc['specialization'], $search) !== false;
    });
}

// Filter by specialization
$specialization_filter = '';
if (isset($_GET['specialization']) && !empty($_GET['specialization'])) {
    $specialization_filter = $_GET['specialization'];
    $filtered_doctors = array_filter($filtered_doctors, function($doc) use ($specialization_filter) {
        return $doc['specialization'] === $specialization_filter;
    });
}

// Get unique specializations for filter
$specializations = array_unique(array_column($doctors, 'specialization'));
sort($specializations);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Doctors - Medical System</title>
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
                    <li><a href="doctors.php" class="active">Doctors</a></li>
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
            <h1>Browse Doctors</h1>
            <p>Find and connect with our qualified medical professionals</p>
        </div>

        <!-- Search and Filter Section -->
        <div class="card">
            <form method="GET" class="search-form">
                <div class="grid grid-3">
                    <div class="form-group">
                        <label for="search">Search by Name or Specialization</label>
                        <input type="text" 
                               id="search" 
                               name="search"
                               class="form-input"
                               placeholder="Enter doctor name or specialization..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label for="specialization">Filter by Specialization</label>
                        <select id="specialization" name="specialization" class="form-select">
                            <option value="">All Specializations</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>" 
                                        <?php echo $specialization_filter === $spec ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="margin-right: 10px;">Search</button>
                        <?php if (!empty($search) || !empty($specialization_filter)): ?>
                            <a href="doctors.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div class="card">
            <div class="d-flex justify-between align-center">
                <div>
                    <h3>Available Doctors (<?php echo count($filtered_doctors); ?>)</h3>
                    <?php if (!empty($search) || !empty($specialization_filter)): ?>
                        <p>
                            <?php if (!empty($search)): ?>
                                Searching for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                            <?php endif; ?>
                            <?php if (!empty($specialization_filter)): ?>
                                <?php echo !empty($search) ? ' | ' : ''; ?>
                                Specialization: <strong><?php echo htmlspecialchars($specialization_filter); ?></strong>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Doctors Grid -->
        <?php if (empty($filtered_doctors)): ?>
            <div class="card text-center">
                <h3>No doctors found</h3>
                <p>Try adjusting your search criteria or browse all doctors.</p>
                <a href="doctors.php" class="btn btn-primary">View All Doctors</a>
            </div>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($filtered_doctors as $doc): ?>
                    <div class="card doctor-card">
                        <div class="doctor-header">
                            <div class="doctor-avatar">
                                <?php echo strtoupper(substr($doc['name'], 0, 2)); ?>
                            </div>
                            <div class="doctor-info">
                                <h3><?php echo htmlspecialchars($doc['name']); ?></h3>
                                <p class="specialization"><?php echo htmlspecialchars($doc['specialization']); ?></p>
                                <p class="license">License: <?php echo htmlspecialchars($doc['license_number']); ?></p>
                            </div>
                        </div>
                        
                        <div class="doctor-contact">
                            <div class="contact-item">
                                <strong>Email:</strong> <?php echo htmlspecialchars($doc['email']); ?>
                            </div>
                            <div class="contact-item">
                                <strong>Phone:</strong> <?php echo htmlspecialchars($doc['phone']); ?>
                            </div>
                            <div class="contact-item">
                                <strong>Member since:</strong> <?php echo date('M Y', strtotime($doc['created_at'])); ?>
                            </div>
                        </div>

                        <div class="doctor-actions">
                            <a href="appointment-form.php?doctor_id=<?php echo $doc['id']; ?>" 
                               class="btn btn-primary btn-full">
                                Book Appointment
                            </a>
                            <button onclick="viewDoctorDetails(<?php echo $doc['id']; ?>)" 
                                    class="btn btn-info btn-full">
                                View Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Specializations Overview -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Our Specializations</h2>
            </div>
            <div class="grid grid-4">
                <?php 
                $spec_counts = array_count_values(array_column($doctors, 'specialization'));
                foreach ($spec_counts as $spec => $count): 
                ?>
                    <div class="specialization-card">
                        <h4><?php echo htmlspecialchars($spec); ?></h4>
                        <p><?php echo $count; ?> Doctor<?php echo $count > 1 ? 's' : ''; ?></p>
                        <a href="doctors.php?specialization=<?php echo urlencode($spec); ?>" 
                           class="btn btn-sm btn-outline">
                            View Doctors
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Doctor Details Modal -->
    <div id="doctorModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalDoctorName">Doctor Details</h3>
                <span class="close" onclick="closeDoctorModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalDoctorBody">
                <!-- Doctor details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button onclick="closeDoctorModal()" class="btn btn-secondary">Close</button>
                <button id="modalBookBtn" class="btn btn-primary">Book Appointment</button>
            </div>
        </div>
    </div>

    <script>
    function viewDoctorDetails(doctorId) {
        // Find doctor data
        const doctors = <?php echo json_encode($doctors); ?>;
        const doctor = doctors.find(d => d.id == doctorId);
        
        if (!doctor) return;
        
        // Update modal content
        document.getElementById('modalDoctorName').textContent = doctor.name;
        document.getElementById('modalDoctorBody').innerHTML = `
            <div class="doctor-details">
                <div class="detail-row">
                    <strong>Name:</strong> ${doctor.name}
                </div>
                <div class="detail-row">
                    <strong>Specialization:</strong> ${doctor.specialization}
                </div>
                <div class="detail-row">
                    <strong>License Number:</strong> ${doctor.license_number}
                </div>
                <div class="detail-row">
                    <strong>Email:</strong> ${doctor.email}
                </div>
                <div class="detail-row">
                    <strong>Phone:</strong> ${doctor.phone}
                </div>
                <div class="detail-row">
                    <strong>Member Since:</strong> ${new Date(doctor.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long'})}
                </div>
            </div>
        `;
        
        // Update book appointment button
        document.getElementById('modalBookBtn').onclick = function() {
            window.location.href = `appointment-form.php?doctor_id=${doctor.id}`;
        };
        
        // Show modal
        document.getElementById('doctorModal').style.display = 'block';
    }
    
    function closeDoctorModal() {
        document.getElementById('doctorModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('doctorModal');
        if (event.target === modal) {
            closeDoctorModal();
        }
    }
    </script>
</body>
</html>