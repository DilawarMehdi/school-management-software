<?php
$conn = new mysqli('localhost', 'root', '', 'siax_smss');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add graph_color and result
$sql = "ALTER TABLE grading_policy_details 
        ADD COLUMN graph_color VARCHAR(50) DEFAULT NULL,
        ADD COLUMN result VARCHAR(20) DEFAULT 'Pass'";

if ($conn->query($sql)) {
    echo "Columns added successfully.\n";
} else {
    echo "Error or already exists: " . $conn->error . "\n";
}
?>
