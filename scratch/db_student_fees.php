<?php
require_once __DIR__ . '/../config/db.php';
$conn->query("CREATE TABLE IF NOT EXISTS student_monthly_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  month VARCHAR(20) NOT NULL,
  tuition_fee DECIMAL(10,2) DEFAULT 0,
  prev_pending DECIMAL(10,2) DEFAULT 0,
  discount_amount DECIMAL(10,2) DEFAULT 0,
  fine_amount DECIMAL(10,2) DEFAULT 0,
  paid_amount DECIMAL(10,2) DEFAULT 0,
  status ENUM('Due', 'Partial', 'Paid') DEFAULT 'Due',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Table student_monthly_fees created.";



