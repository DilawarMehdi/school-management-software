<?php
require_once __DIR__ . '/../config/db.php';

echo "--- ADDING PERMISSIONS COLUMN TO USERS TABLE ---\n";
$res = $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions TEXT DEFAULT NULL AFTER role");
if ($res) {
    echo "✓ Added permissions column to users table.\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}
