<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');

$q = $conn->real_escape_string(trim($_GET['q'] ?? ''));

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT s.id, s.admission_no, s.roll_no, s.name, s.father_name, c.name as class_name, c.section, 'Morning' as shift
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.name LIKE '%$q%' OR s.admission_no LIKE '%$q%' OR s.father_name LIKE '%$q%'
        LIMIT 10";

$res = $conn->query($sql);
$results = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $results[] = $row;
    }
}

echo json_encode($results);
