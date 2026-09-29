<?php
require_once __DIR__ . '/../config/db.php';

echo "--- CHECKING INDEX DEFINITIONS ON MARKS TABLE ---\n";
$r = $conn->query("SHOW CREATE TABLE marks");
if ($r) {
    $row = $r->fetch_assoc();
    echo $row['Create Table'] . "\n";
}
