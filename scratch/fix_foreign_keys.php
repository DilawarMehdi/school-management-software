<?php
require_once __DIR__ . '/../config/db.php';

echo "--- DROPPING STRICT FOREIGN KEYS ON MARKS TABLE & MAKING COLUMNS NULLABLE ---\n";

// Drop foreign key constraints on exam_id and subject_id if they exist
@$conn->query("ALTER TABLE marks DROP FOREIGN KEY marks_ibfk_1");
@$conn->query("ALTER TABLE marks DROP FOREIGN KEY marks_ibfk_3");
@$conn->query("ALTER TABLE marks DROP FOREIGN KEY marks_ibfk_4");

// Make exam_id and subject_id NULLABLE so unmapped values don't fail inserts
$res1 = $conn->query("ALTER TABLE marks MODIFY COLUMN exam_id INT NULL DEFAULT NULL");
$res2 = $conn->query("ALTER TABLE marks MODIFY COLUMN subject_id INT NULL DEFAULT NULL");

echo "Modify exam_id: " . ($res1 ? "SUCCESS" : "ERROR: " . $conn->error) . "\n";
echo "Modify subject_id: " . ($res2 ? "SUCCESS" : "ERROR: " . $conn->error) . "\n";

echo "\n--- TESTING INSERT AGAIN ---\n";
$student_id = 1;
$type_id = 1;
$sched_id = 1;

$subject1 = 'English';
$res1 = $conn->query("INSERT INTO marks (student_id, exam_type_id, schedule_id, subject, marks_obtained, max_marks, pass_marks, is_absent, not_participating, exam_id, subject_id)
    VALUES ($student_id, $type_id, $sched_id, '$subject1', 85, 100, 40, 0, 0, NULL, NULL)
    ON DUPLICATE KEY UPDATE marks_obtained=85, is_absent=0, not_participating=0, max_marks=100, pass_marks=40");

echo "Insert English Result: " . ($res1 ? "SUCCESS" : "ERROR: " . $conn->error) . "\n";

$subject2 = 'Math';
$res2 = $conn->query("INSERT INTO marks (student_id, exam_type_id, schedule_id, subject, marks_obtained, max_marks, pass_marks, is_absent, not_participating, exam_id, subject_id)
    VALUES ($student_id, $type_id, $sched_id, '$subject2', 92, 100, 40, 0, 0, NULL, NULL)
    ON DUPLICATE KEY UPDATE marks_obtained=92, is_absent=0, not_participating=0, max_marks=100, pass_marks=40");

echo "Insert Math Result: " . ($res2 ? "SUCCESS" : "ERROR: " . $conn->error) . "\n";

$q = $conn->query("SELECT * FROM marks WHERE student_id = $student_id AND schedule_id = $sched_id");
echo "\n--- SAVED MARKS FOR STUDENT 1 ---\n";
while ($row = $q->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Subject: " . $row['subject'] . " | Obtained: " . $row['marks_obtained'] . "\n";
}
