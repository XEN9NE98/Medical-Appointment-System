<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/classes.php';
require_once __DIR__ . '/../config/database.php';

requireUserType('doctor');

$database = new Database();
$db = $database->getConnection();

// Get patient ID from request
$patient_id = $_GET['patient_id'] ?? 0;

if (!$patient_id) {
    echo '<div class="alert alert-error"><strong>Error:</strong> No patient ID provided.</div>';
    exit;
}

// Verify that this patient has appointments with the current doctor
$verify_query = "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ?";
$verify_stmt = $db->prepare($verify_query);
$verify_stmt->execute([$patient_id, $_SESSION['user_id']]);
$verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if ($verify_result['count'] == 0) {
    echo '<div class="alert alert-error"><strong>Access Denied:</strong> You do not have access to this patient\'s records.</div>';
    exit;
}

// Get patient basic information
$patient_query = "SELECT * FROM patients WHERE id = ?";
$patient_stmt = $db->prepare($patient_query);
$patient_stmt->execute([$patient_id]);
$patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo '<div class="alert alert-error"><strong>Error:</strong> Patient not found.</div>';
    exit;
}

// Get appointment history with the current doctor
$appointments_query = "SELECT a.*, d.name as doctor_name, d.specialization,
                      CASE 
                          WHEN a.status = 'pending' THEN '#ffa502'
                          WHEN a.status = 'approved' THEN '#3742fa'
                          WHEN a.status = 'completed' THEN '#2ed573'
                          WHEN a.status = 'cancelled' THEN '#ff4757'
                          ELSE '#747d8c'
                      END as status_color
                      FROM appointments a
                      LEFT JOIN doctors d ON a.doctor_id = d.id
                      WHERE a.patient_id = ? AND a.doctor_id = ?
                      ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments_stmt = $db->prepare($appointments_query);
$appointments_stmt->execute([$patient_id, $_SESSION['user_id']]);
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get medical records if they exist (assuming a medical_records table)
$records_query = "SELECT mr.*, a.appointment_date, a.appointment_time
                  FROM medical_records mr
                  LEFT JOIN appointments a ON mr.appointment_id = a.id
                  WHERE mr.patient_id = ? AND a.doctor_id = ?
                  ORDER BY mr.created_at DESC";
$records_stmt = $db->prepare($records_query);
$records_stmt->execute([$patient_id, $_SESSION['user_id']]);
$medical_records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate patient statistics
$total_appointments = count($appointments);
$completed_appointments = count(array_filter($appointments, fn($a) => $a['status'] === 'completed'));
$pending_appointments = count(array_filter($appointments, fn($a) => $a['status'] === 'pending'));
$cancelled_appointments = count(array_filter($appointments, fn($a) => $a['status'] === 'cancelled'));

// Get first and last appointment dates
$first_appointment = !empty($appointments) ? end($appointments)['appointment_date'] : null;
$last_appointment = !empty($appointments) ? $appointments[0]['appointment_date'] : null;

// Helper functions
function calculateAge($birthDate) {
    if (!$birthDate) return 'N/A';
    $today = new DateTime();
    $age = $today->diff(new DateTime($birthDate));
    return $age->y;
}

function formatDate($date) {
    return $date ? date('M d, Y', strtotime($date)) : 'N/A';
}

function formatDateTime($date, $time) {
    if (!$date) return 'N/A';
    $datetime = $date . ' ' . $time;
    return date('M d, Y \a\t g:i A', strtotime($datetime));
}

function getStatusBadge($status) {
    $colors = [
        'pending' => '#ffa502',
        'approved' => '#3742fa',
        'completed' => '#2ed573',
        'cancelled' => '#ff4757'
    ];
    $color = $colors[$status] ?? '#747d8c';
    return "<span class='status-badge' style='background-color: $color;'>" . ucfirst($status) . "</span>";
}
?>

<div class="patient-history-container">
    <!-- Patient Summary -->
    <div class="patient-summary">
        <div class="patient-avatar-large">
            <?php echo strtoupper(substr($patient['name'], 0, 1)); ?>
        </div>
        <div class="patient-summary-info">
            <h2><?php echo htmlspecialchars($patient['name']); ?></h2>
            <div class="patient-basic-info">
                <div class="info-item">
                    <span class="info-label">Age:</span>
                    <span class="info-value"><?php echo calculateAge($patient['date_of_birth']); ?> years old</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Gender:</span>
                    <span class="info-value"><?php echo $patient['gender']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></span>
                </div>
                <?php if ($patient['address']): ?>
                <div class="info-item">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['address']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="history-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_appointments; ?></div>
            <div class="stat-label">Total Appointments</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $completed_appointments; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $pending_appointments; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo formatDate($first_appointment); ?></div>
            <div class="stat-label">First Visit</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="history-tabs">
        <button class="tab-btn active" data-tab="appointments">Appointments</button>
        <button class="tab-btn" data-tab="medical-records">Medical Records</button>
        <button class="tab-btn" data-tab="timeline">Timeline</button>
    </div>

    <!-- Appointments Tab -->
    <div id="appointments-tab" class="tab-content active">
        <h3>Appointment History</h3>
        <?php if (empty($appointments)): ?>
            <div class="no-data">
                <div class="no-data-icon">📅</div>
                <p>No appointments found with this doctor.</p>
            </div>
        <?php else: ?>
            <div class="appointments-list">
                <?php foreach ($appointments as $appointment): ?>
                <div class="appointment-item">
                    <div class="appointment-header">
                        <div class="appointment-date">
                            <div class="date-day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></div>
                            <div class="date-month"><?php echo date('M Y', strtotime($appointment['appointment_date'])); ?></div>
                        </div>
                        <div class="appointment-details">
                            <div class="appointment-time">
                                <strong><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></strong>
                                <?php echo getStatusBadge($appointment['status']); ?>
                            </div>
                            <div class="appointment-reason">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason'] ?? 'General Consultation'); ?>
                            </div>
                            <?php if ($appointment['notes']): ?>
                            <div class="appointment-notes">
                                <strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($appointment['status'] === 'completed' && $appointment['notes']): ?>
                    <div class="appointment-outcome">
                        <strong>Visit Outcome:</strong>
                        <p><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Medical Records Tab -->
    <div id="medical-records-tab" class="tab-content">
        <h3>Medical Records</h3>
        <?php if (empty($medical_records)): ?>
            <div class="no-data">
                <div class="no-data-icon">📋</div>
                <p>No medical records available.</p>
                <small>Medical records will appear here after completed appointments with documented findings.</small>
            </div>
        <?php else: ?>
            <div class="medical-records-list">
                <?php foreach ($medical_records as $record): ?>
                <div class="medical-record-item">
                    <div class="record-header">
                        <div class="record-date">
                            <?php echo formatDate($record['appointment_date']); ?>
                        </div>
                        <div class="record-type">
                            <?php echo htmlspecialchars($record['record_type'] ?? 'General Record'); ?>
                        </div>
                    </div>
                    <div class="record-content">
                        <?php if ($record['diagnosis']): ?>
                        <div class="record-section">
                            <strong>Diagnosis:</strong>
                            <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($record['treatment']): ?>
                        <div class="record-section">
                            <strong>Treatment:</strong>
                            <p><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($record['prescription']): ?>
                        <div class="record-section">
                            <strong>Prescription:</strong>
                            <p><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($record['notes']): ?>
                        <div class="record-section">
                            <strong>Additional Notes:</strong>
                            <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Timeline Tab -->
    <div id="timeline-tab" class="tab-content">
        <h3>Patient Timeline</h3>
        <?php if (empty($appointments)): ?>
            <div class="no-data">
                <div class="no-data-icon">⏰</div>
                <p>No timeline data available.</p>
            </div>
        <?php else: ?>
            <div class="timeline">
                <?php 
                $timeline_events = [];
                
                // Add appointments to timeline
                foreach ($appointments as $appointment) {
                    $timeline_events[] = [
                        'date' => $appointment['appointment_date'],
                        'time' => $appointment['appointment_time'],
                        'type' => 'appointment',
                        'status' => $appointment['status'],
                        'title' => 'Appointment - ' . ucfirst($appointment['status']),
                        'description' => $appointment['reason'] ?? 'General Consultation',
                        'notes' => $appointment['notes']
                    ];
                }
                
                // Add medical records to timeline
                foreach ($medical_records as $record) {
                    if ($record['appointment_date']) {
                        $timeline_events[] = [
                            'date' => $record['appointment_date'],
                            'time' => '23:59:59',
                            'type' => 'medical_record',
                            'title' => 'Medical Record Added',
                            'description' => $record['record_type'] ?? 'General Record',
                            'diagnosis' => $record['diagnosis']
                        ];
                    }
                }
                
                // Sort timeline events by date and time
                usort($timeline_events, function($a, $b) {
                    $datetime_a = $a['date'] . ' ' . $a['time'];
                    $datetime_b = $b['date'] . ' ' . $b['time'];
                    return strtotime($datetime_b) - strtotime($datetime_a);
                });
                ?>
                
                <?php foreach ($timeline_events as $event): ?>
                <div class="timeline-item">
                    <div class="timeline-marker <?php echo $event['type']; ?>">
                        <?php echo $event['type'] === 'appointment' ? '📅' : '📋'; ?>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="timeline-title"><?php echo htmlspecialchars($event['title']); ?></div>
                            <div class="timeline-date"><?php echo formatDate($event['date']); ?></div>
                        </div>
                        <div class="timeline-description">
                            <?php echo htmlspecialchars($event['description']); ?>
                        </div>
                        <?php if (!empty($event['notes'])): ?>
                        <div class="timeline-notes">
                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($event['notes'])); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($event['diagnosis'])): ?>
                        <div class="timeline-diagnosis">
                            <strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($event['diagnosis'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
// Tab functionality
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Remove active class from all tabs and buttons
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Add active class to clicked button and corresponding content
        this.classList.add('active');
        document.getElementById(this.dataset.tab + '-tab').classList.add('active');
    });
});
</script>