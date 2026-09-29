<?php
require_once __DIR__ . '/../config/db.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS student_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        session_year VARCHAR(50) NOT NULL,
        roll_no VARCHAR(20),
        shift VARCHAR(50) DEFAULT 'Morning',
        enrollment_date DATE,
        discharge_date DATE DEFAULT NULL,
        status ENUM('Active', 'Discharged', 'Promoted') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    )",
    // Migrate current student data to enrollments table if empty
    "INSERT INTO student_enrollments (student_id, class_id, session_year, roll_no, enrollment_date, status)
     SELECT s.id, s.class_id, (SELECT setting_value FROM settings WHERE setting_key='session_year' LIMIT 1), s.roll_no, s.admission_date, 'Active'
     FROM students s
     JOIN classes c ON s.class_id = c.id
     WHERE s.id NOT IN (SELECT student_id FROM student_enrollments)"
];

foreach ($queries as $q) {
    if (!$conn->query($q)) {
        echo "Error: " . $conn->error . "\n";
    }
}
echo "Enrollment table created and initialized.";
