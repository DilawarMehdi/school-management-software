<?php
require_once __DIR__ . '/../config/db.php';

// Test inserting marks for student ID 1 in schedule 1 for English and Math
$student_id = 1;
$type_id = 1;
$sched_id = 1;

// Subject 1: English
$subject1 = 'English';
$res1 = $conn->query("INSERT INTO marks (student_id, exam_type_id, schedule_id, subject, marks_obtained, max_marks, pass_marks, is_absent, not_participating, exam_id, subject_id)
    VALUES ($student_id, $type_id, $sched_id, '$subject1', 85, 100, 40, 0, 0, 1, 1)
    ON DUPLICATE KEY UPDATE marks_obtained=85, is_absent=0, not_participating=0, max_marks=100, pass_marks=40");

echo "Insert English Result: " . ($res1 ? "SUCCESS" : "ERROR: " . $conn->error) . "\n";

// Subject 2: Math
$subject2 = 'Math';
$res2 = $conn->query("INSERT INTO marks (student_id, exam_type_id, schedule_id, subject, marks_obtained, max_marks, pass_marks, is_absent, not_participating, exam_id, subject_id)
    VALUES ($student_id, $type_id, $sched_id, '$subject2', 92, 100, 40, 0, 0, 1, 2)
    ON DUPLICATE KEY UPDATE marks_obtained=92, is_absent=0, not_participating=0, max_marks=100, pass_marks=40");

echo "Insert Math Result: " . ($res2 ? "SUCCESS" : "ERROR: " . $conn->error) . "\n";

// Verify saved marks for Student 1
$q = $conn->query("SELECT * FROM marks WHERE student_id = $student_id AND schedule_id = $sched_id");
echo "\n--- SAVED MARKS FOR STUDENT 1 ---\n";
while ($row = $q->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Subject: " . $row['subject'] . " | Obtained: " . $row['marks_obtained'] . "\n";
}
