<?php
/**
 * Create advance_fees table for tracking advance fee entries per student.
 */
require_once __DIR__ . '/../config/db.php';

$conn->query("CREATE TABLE IF NOT EXISTS advance_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  invoice_id INT DEFAULT NULL,
  advance_month VARCHAR(30) NOT NULL,
  advance_year INT NOT NULL,
  amount DECIMAL(10,2) DEFAULT 0,
  status ENUM('Pending','Applied') DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  applied_at DATETIME DEFAULT NULL,
  notes TEXT DEFAULT NULL
)");

// Ensure fee_details and admission_fee columns exist in student_monthly_fees
$conn->query("ALTER TABLE student_monthly_fees ADD COLUMN IF NOT EXISTS fee_details TEXT DEFAULT NULL");
$conn->query("ALTER TABLE student_monthly_fees ADD COLUMN IF NOT EXISTS admission_fee DECIMAL(10,2) DEFAULT 0");
$conn->query("ALTER TABLE student_monthly_fees ADD COLUMN IF NOT EXISTS payment_date DATE DEFAULT NULL");

echo "advance_fees table and required columns created successfully.";
