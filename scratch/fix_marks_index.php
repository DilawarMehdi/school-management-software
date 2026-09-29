<?php
require_once __DIR__ . '/../config/db.php';

echo "--- DROPPING FLAWED INDEXES & CREATING PROPER MARKS INDEXES ---\n";

// Drop flawed indexes
@$conn->query("ALTER TABLE marks DROP KEY uniq_mark");
@$conn->query("ALTER TABLE marks DROP KEY unique_mark");

// Add correct unique indexes that include subject!
$res1 = $conn->query("ALTER TABLE marks ADD UNIQUE KEY uniq_student_sched_subject (student_id, schedule_id, subject)");
if ($res1) {
    echo "✓ Added uniq_student_sched_subject (student_id, schedule_id, subject)\n";
} else {
    echo "✗ Error adding uniq_student_sched_subject: " . $conn->error . "\n";
}

echo "\n--- VERIFYING NEW TABLE SCHEMA ---\n";
$r = $conn->query("SHOW CREATE TABLE marks");
if ($r) {
    $row = $r->fetch_assoc();
    echo $row['Create Table'] . "\n";
}
