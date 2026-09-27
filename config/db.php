<?php
require_once __DIR__ . '/../includes/helpers.php';
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'siax_smss');
define('APP_NAME', 'SIAX SMSS');
define('APP_VERSION', '1.0');

// Developer / Master Admin Credentials for Subscription Management
define('MASTER_USER', 'siax_admin');
define('MASTER_PASS', 'siax@2026');

// Auto-detect BASE_URL so it works on any port and directory depth
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get the physical path of the project root (one level up from this config file)
    $proj_root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $doc_root  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    
    // Calculate relative path from document root (case-insensitive for Windows drive letters)
    $relative_path = !empty($doc_root) ? str_ireplace($doc_root, '', $proj_root) : '/siax-smss';
    $base_path = '/' . ltrim($relative_path, '/') . '/';
    $base_path = str_replace('//', '/', $base_path); // fix double slashes if any
    
    define('BASE_URL', $scheme . '://' . $host . $base_path);
}

define('UPLOAD_PATH', __DIR__ . '/../uploads/');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:40px;background:#0a0f1e;color:#ef4444;text-align:center;">
        <h2><i class="fa-solid fa-triangle-exclamation"></i>️ Database Connection Failed</h2>
        <p>' . $conn->connect_error . '</p>
        <p style="color:#94a3b8;">Make sure XAMPP MySQL is running and the database is created via <code>setup/install.sql</code></p>
    </div>');
}
$conn->set_charset('utf8mb4');

function get_setting($conn, $key, $default = '') {
    $key = $conn->real_escape_string($key);
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key' LIMIT 1");
    if ($r && $r->num_rows > 0) return $r->fetch_assoc()['setting_value'];
    return $default;
}
function all_settings($conn) {
    $out = [];
    $r = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $r->fetch_assoc()) $out[$row['setting_key']] = $row['setting_value'];
    return $out;
}
function generate_receipt_no($conn) {
    $year = date('Y');
    $r = $conn->query("SELECT COUNT(*) as c FROM fee_payments WHERE YEAR(created_at)=$year");
    $count = ($r->fetch_assoc()['c'] ?? 0) + 1;
    return 'RCP-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}
function generate_cert_no($conn, $type) {
    $prefix = strtoupper(substr($type, 0, 3));
    $year = date('Y');
    $r = $conn->query("SELECT COUNT(*) as c FROM certificates WHERE YEAR(created_at)=$year AND type='$type'");
    $count = ($r->fetch_assoc()['c'] ?? 0) + 1;
    return $prefix . '-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
function generate_admission_no($conn) {
    $year = date('Y');
    $r = $conn->query("SELECT COUNT(*) as c FROM students WHERE YEAR(created_at)=$year");
    $count = ($r->fetch_assoc()['c'] ?? 0) + 1;
    return 'ADM-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
function get_grade($marks, $max = 100) {
    $pct = ($max > 0) ? ($marks / $max) * 100 : 0;
    if ($pct >= 90) return ['grade' => 'A+', 'remarks' => 'Outstanding'];
    if ($pct >= 80) return ['grade' => 'A',  'remarks' => 'Excellent'];
    if ($pct >= 70) return ['grade' => 'B',  'remarks' => 'Very Good'];
    if ($pct >= 60) return ['grade' => 'C',  'remarks' => 'Good'];
    if ($pct >= 50) return ['grade' => 'D',  'remarks' => 'Average'];
    return ['grade' => 'F', 'remarks' => 'Fail'];
}



