<?php
require_once __DIR__ . '/../config/db.php';
$conn->query("ALTER TABLE student_monthly_fees ADD COLUMN payment_date DATE DEFAULT NULL AFTER paid_amount");
echo "Column added successfully or already exists.";
?>



