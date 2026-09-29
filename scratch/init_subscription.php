<?php
require_once __DIR__ . '/../config/db.php';

$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('subscription_start', '" . date('Y-m-d') . "')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('subscription_expiry', '" . date('Y-m-d', strtotime('+1 year')) . "')");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('license_status', 'Active')");

echo "Subscription settings initialized.";
