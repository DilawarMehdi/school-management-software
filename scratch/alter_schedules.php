<?php
$conn = new mysqli('localhost', 'root', '', 'siax_smss');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add policy_id
$sql = "ALTER TABLE exam_schedules ADD COLUMN policy_id INT(11) DEFAULT NULL";

if ($conn->query($sql)) {
    echo "Column policy_id added successfully.\n";
} else {
    echo "Error or already exists: " . $conn->error . "\n";
}

// Get default policy id if exists
$res = $conn->query("SELECT id FROM grading_policies ORDER BY is_default DESC, id ASC LIMIT 1");
if ($res && $res->num_rows > 0) {
    $def_id = $res->fetch_assoc()['id'];
    $conn->query("UPDATE exam_schedules SET policy_id = $def_id WHERE policy_id IS NULL");
    echo "Updated existing schedules to default policy ID: $def_id\n";
}
?>
