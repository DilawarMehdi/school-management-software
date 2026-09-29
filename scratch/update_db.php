<?php
require_once __DIR__ . '/../config/db.php';

// Add session_year column to exam_schedules if it doesn't exist
$res = $conn->query("SHOW COLUMNS FROM exam_schedules LIKE 'session_year'");
if ($res && $res->num_rows == 0) {
    $conn->query("ALTER TABLE exam_schedules ADD COLUMN session_year VARCHAR(20) DEFAULT '2025-2026'");
    echo "Added session_year column to exam_schedules.\n";
} else {
    echo "session_year column already exists in exam_schedules.\n";
}
