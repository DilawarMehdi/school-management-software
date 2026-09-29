<?php
require_once __DIR__ . '/../config/db.php';
$res = $conn->query("DESCRIBE student_monthly_fees");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>



