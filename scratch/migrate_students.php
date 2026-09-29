<?php
require_once __DIR__ . '/../config/db.php';

$queries = [
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS father_occupation VARCHAR(255) AFTER father_name",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS permanent_address TEXT AFTER address",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS family_id VARCHAR(50) AFTER admission_no",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(255) AFTER mother_name",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS guardian_contact VARCHAR(20) AFTER phone",
    "ALTER TABLE students ADD COLUMN IF NOT EXISTS admission_class_id INT AFTER class_id"
];

foreach ($queries as $q) {
    if (!$conn->query($q)) {
        echo "Error: " . $conn->error . "\n";
    } else {
        echo "Success: " . substr($q, 0, 50) . "...\n";
    }
}
echo "Migration complete.";
