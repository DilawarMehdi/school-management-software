<?php
require_once __DIR__ . '/../config/db.php';
$conn->query("CREATE TABLE IF NOT EXISTS fee_payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monthly_fee_id INT NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    fine_paid DECIMAL(10,2) DEFAULT 0,
    waived_fee DECIMAL(10,2) DEFAULT 0,
    waived_fine DECIMAL(10,2) DEFAULT 0,
    payment_date DATE NOT NULL,
    received_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (monthly_fee_id) REFERENCES student_monthly_fees(id) ON DELETE CASCADE
)");
echo "Fee payment history table created.";
?>



