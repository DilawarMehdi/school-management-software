<?php
$conn = new mysqli('localhost', 'root', '', 'siax_smss');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add max_fail_subjects
$sql = "ALTER TABLE grading_policies ADD COLUMN max_fail_subjects INT DEFAULT NULL";

if ($conn->query($sql)) {
    echo "Column max_fail_subjects added successfully.\n";
} else {
    echo "Error or already exists: " . $conn->error . "\n";
}
?>
