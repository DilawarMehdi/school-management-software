<?php
require_once __DIR__ . '/../config/db.php';

// Hardcode a class and exam to see if marks exist
$class_id = 1; // Example
$exam_id = 1; // Example

// Find an exam and class combination that has marks
$mq = $conn->query("SELECT DISTINCT exam_id, class_id FROM marks JOIN students ON marks.student_id = students.id LIMIT 1");
if ($mq && $mq->num_rows > 0) {
    $row = $mq->fetch_assoc();
    $class_id = $row['class_id'];
    $exam_id = $row['exam_id'];
    echo "Found marks for Class $class_id, Exam $exam_id\n";
} else {
    die("No marks found in the database at all.\n");
}

$students_q = $conn->query("SELECT s.* FROM students s WHERE s.class_id=$class_id AND s.status='Active'");
$all_students = [];
if ($students_q) while($s = $students_q->fetch_assoc()) $all_students[] = $s;

$all_results = [];
foreach ($all_students as $st) {
    $mq = $conn->query("SELECT m.* FROM marks m WHERE m.exam_id=$exam_id AND m.student_id={$st['id']}");
    $marks = [];
    if ($mq && $mq->num_rows > 0) {
        while($m = $mq->fetch_assoc()) {
            $marks[] = $m;
        }
    }
    
    if (empty($marks)) {
        continue;
    }
    $all_results[] = $st['id'];
}

echo "Found " . count($all_results) . " students with marks.\n";
