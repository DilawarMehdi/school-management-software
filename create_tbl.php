<?php
require 'config/db.php';
$conn->query("CREATE TABLE IF NOT EXISTS schedule_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    class_subject_id INT NOT NULL,
    exam_date DATE,
    start_time TIME,
    UNIQUE KEY(schedule_id, class_subject_id)
)");
echo "Table created.\n";
