<?php
require_once __DIR__ . '/../config/db.php';
$conn->query("ALTER TABLE certificates ADD COLUMN IF NOT EXISTS leaving_date DATE AFTER issue_date");
echo "DB Updated";
