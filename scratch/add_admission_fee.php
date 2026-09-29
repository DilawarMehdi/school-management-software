<?php
require_once __DIR__ . '/../config/db.php';
$conn->query("ALTER TABLE student_monthly_fees ADD COLUMN admission_fee DECIMAL(10,2) DEFAULT 0 AFTER tuition_fee");
echo "Column admission_fee added successfully.";
?>



