<?php
class User {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($email, $password, $user_type) {
        $table = $user_type === 'patient' ? 'patients' : 'doctors';
        $query = "SELECT * FROM " . $table . " WHERE email = ? AND password = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email, md5($password)]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_name'] = $user['name'];
            return true;
        }
        return false;
    }
    
    public function register($data, $user_type) {
        $table = $user_type === 'patient' ? 'patients' : 'doctors';
        $fields = $user_type === 'patient' ? 
            "name, email, password, phone, gender, date_of_birth, address" :
            "name, email, password, phone, specialization, license_number";
        
        $placeholders = str_repeat('?,', count(explode(',', $fields)) - 1) . '?';
        $query = "INSERT INTO " . $table . " (" . $fields . ") VALUES (" . $placeholders . ")";
        
        $stmt = $this->conn->prepare($query);
        $data['password'] = md5($data['password']);
        
        return $stmt->execute(array_values($data));
    }
}

class Appointment {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create($patient_id, $doctor_id, $appointment_date, $appointment_time, $symptoms) {
        $query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status) 
                 VALUES (?, ?, ?, ?, ?, 'pending')";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $symptoms]);
    }
    
    public function getPatientAppointments($patient_id) {
        $query = "SELECT a.*, d.name as doctor_name, d.specialization 
                 FROM appointments a 
                 JOIN doctors d ON a.doctor_id = d.id 
                 WHERE a.patient_id = ? 
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDoctorAppointments($doctor_id) {
        $query = "SELECT a.*, p.name as patient_name, p.phone as patient_phone 
                 FROM appointments a 
                 JOIN patients p ON a.patient_id = p.id 
                 WHERE a.doctor_id = ? 
                 ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$doctor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($appointment_id, $status) {
        $query = "UPDATE appointments SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$status, $appointment_id]);
    }
}

class Doctor {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getAll() {
        $query = "SELECT * FROM doctors ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $query = "SELECT * FROM doctors WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>