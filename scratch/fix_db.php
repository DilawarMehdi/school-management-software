<?php
$conn = new mysqli('localhost', 'root', '', 'siax_smss');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "Adding fee_details column...\n";
$res = $conn->query("ALTER TABLE student_monthly_fees ADD COLUMN fee_details TEXT AFTER month");
if ($res) echo "SUCCESS: fee_details added.\n";
else echo "ERROR: " . $conn->error . "\n";

echo "Ensuring admission_fee column...\n";
// It seems admission_fee was there in describe, but let's be sure.
$res = $conn->query("ALTER TABLE student_monthly_fees MODIFY COLUMN admission_fee DECIMAL(10,2) DEFAULT 0");
if ($res) echo "SUCCESS: admission_fee verified.\n";
else echo "ERROR: " . $conn->error . "\n";
?>



