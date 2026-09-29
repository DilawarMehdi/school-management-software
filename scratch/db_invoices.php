<?php
require_once __DIR__ . '/../config/db.php';
$conn->query("CREATE TABLE IF NOT EXISTS fee_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  month VARCHAR(20) NOT NULL,
  session_year VARCHAR(20) NOT NULL,
  created_at DATE NOT NULL,
  due_date DATE NOT NULL,
  valid_till DATE NOT NULL,
  student_count INT DEFAULT 0,
  prev_pending DECIMAL(10,2) DEFAULT 0,
  current_fee DECIMAL(10,2) DEFAULT 0,
  received_fee DECIMAL(10,2) DEFAULT 0,
  waived_fee DECIMAL(10,2) DEFAULT 0,
  fine_amount DECIMAL(10,2) DEFAULT 0,
  fine_off DECIMAL(10,2) DEFAULT 0
)");
echo "Table created.";



