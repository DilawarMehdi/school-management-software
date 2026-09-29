<?php
require_once __DIR__ . '/../config/db.php';

// 1. Ensure user_id column exists in staff_members table
$conn->query("ALTER TABLE staff_members ADD COLUMN IF NOT EXISTS user_id INT AFTER id");

// 2. Create staff_attendance table
$sql = "CREATE TABLE IF NOT EXISTS staff_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present','Absent','Late','Half Day','Leave') NOT NULL DEFAULT 'Present',
    time_in TIME NULL,
    time_out TIME NULL,
    notes VARCHAR(255) NULL,
    marked_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_staff_date (staff_id, date),
    FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "SUCCESS: staff_attendance table created/verified.\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}

// 3. Link existing teacher users to staff_members if not linked
$teachers = $conn->query("SELECT * FROM users WHERE role IN ('teacher','accountant')");
if ($teachers) {
    while ($u = $teachers->fetch_assoc()) {
        $check = $conn->query("SELECT id FROM staff_members WHERE user_id = {$u['id']} OR email = '{$u['email']}' LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $sm = $check->fetch_assoc();
            $conn->query("UPDATE staff_members SET user_id = {$u['id']} WHERE id = {$sm['id']}");
        }
    }
}
echo "Completed setup.\n";
