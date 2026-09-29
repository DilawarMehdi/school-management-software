<?php
require_once __DIR__ . '/../config/db.php';

echo "--- MARKS TABLE FIELDS ---\n";
$r = $conn->query("DESCRIBE marks");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | Null: " . $row['Null'] . " | Key: " . $row['Key'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\n--- MARKS TABLE INDEXES ---\n";
$r2 = $conn->query("SHOW INDEX FROM marks");
if ($r2) {
    while ($row = $r2->fetch_assoc()) {
        echo "Key_name: " . $row['Key_name'] . " | Column_name: " . $row['Column_name'] . " | Non_unique: " . $row['Non_unique'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
