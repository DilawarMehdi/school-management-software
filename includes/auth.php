<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() { return isset($_SESSION['smss_user']); }
function current_user() { return $_SESSION['smss_user'] ?? null; }
function current_role() { return $_SESSION['smss_user']['role'] ?? ''; }
function current_uid() { return $_SESSION['smss_user']['id'] ?? 0; }

function require_login() {
    if (!is_logged_in()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Session expired. Please log in again.', 'redirect' => BASE_URL . 'index.php']);
            exit;
        }
        header('Location: ' . BASE_URL . 'index.php?msg=session_expired');
        exit;
    }
    
    // Check subscription status for non-master users
    if (current_role() !== 'master' && !is_subscription_active()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Subscription expired.']);
            exit;
        }
        header('Location: ' . BASE_URL . 'index.php?err=subscription_expired');
        exit;
    }
}
/**
 * Auto-detect which module the current page belongs to based on file path.
 * Used by require_role() to check custom module permissions as a fallback.
 */
function get_current_module_from_path() {
    $path = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    if (strpos($path, 'modules/fees') !== false)     return 'finance';
    if (strpos($path, 'modules/expenses') !== false)  return 'expenses';
    if (strpos($path, 'modules/students') !== false)  return 'students';
    if (strpos($path, 'modules/exams') !== false)     return 'academics';
    if (strpos($path, 'modules/staff') !== false)     return 'staff';
    if (strpos($path, 'modules/reports') !== false)   return 'documents';
    if (strpos($path, 'modules/settings') !== false)  return 'system';
    if (strpos($path, 'modules/parents') !== false)   return 'parents';
    if (strpos($path, 'modules/fall') !== false)      return 'academics';
    if (strpos($path, 'modules/resources') !== false) return 'dashboard';
    if (strpos($path, 'dashboard') !== false)         return 'dashboard';
    return null;
}

function require_role(...$roles) {
    require_login();
    
    // 1. Check role-based access (original behavior) — if role matches, allow immediately
    if (in_array(current_role(), $roles)) {
        return; // Access granted by role
    }
    
    // 2. Fallback: Check custom module permissions assigned to this user
    //    This allows a "teacher" role with custom "finance" permission to access fee pages
    $module = get_current_module_from_path();
    if ($module && has_module_access($module)) {
        return; // Access granted by custom module permission
    }
    
    // 3. Access denied — neither role nor custom permissions match
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied. You do not have permission to access this page.']);
        exit;
    }
    header("Location: " . BASE_URL . "dashboard.php?err=access_denied");
    exit;
}
function current_permissions() {
    $user = current_user();
    if (!$user) return [];
    
    // Master or Admin role has access to all modules
    if (($user['role'] ?? '') === 'master' || ($user['role'] ?? '') === 'admin') {
        return ['all'];
    }
    
    // Check if custom permissions JSON exists on user account
    if (!empty($user['permissions'])) {
        $perms = json_decode($user['permissions'], true);
        if (is_array($perms) && !empty($perms)) {
            return $perms;
        }
    }
    
    // Role-based defaults if no custom JSON is defined
    $role = $user['role'] ?? '';
    $defaults = [
        'principal'  => ['dashboard', 'students', 'academics', 'parents', 'finance', 'expenses', 'documents'],
        'accountant' => ['dashboard', 'finance'],
        'teacher'    => ['dashboard', 'students', 'academics'],
        'student'    => ['dashboard', 'portal'],
        'parent'     => ['dashboard', 'portal'],
        'staff'      => ['dashboard']
    ];
    return $defaults[$role] ?? ['dashboard'];
}

function has_module_access($module) {
    $perms = current_permissions();
    if (in_array('all', $perms)) return true;
    return in_array($module, $perms);
}

function require_module_access($module) {
    require_login();
    if (!has_module_access($module)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Access denied: You do not have permission to access the ' . $module . ' module.']);
            exit;
        }
        header("Location: " . BASE_URL . "dashboard.php?err=access_denied&module=" . urlencode($module));
        exit;
    }
}

function can($action) {
    $role = current_role();
    $permissions = [
        'admin'      => ['all'],
        'principal'  => ['view_reports','approve_certs','view_students','view_fees','view_attendance','view_results'],
        'accountant' => ['manage_fees','view_students','view_reports'],
        'teacher'    => ['mark_attendance','enter_marks','view_students'],
        'student'    => ['view_own_results','view_own_fees','view_own_certs'],
        'parent'     => ['view_own_results','view_own_fees','view_own_certs'],
    ];
    $perms = $permissions[$role] ?? [];
    return in_array('all', $perms) || in_array($action, $perms);
}
function login_user($conn, $username, $password) {
    // 1. Check for Master Admin first
    if ($username === MASTER_USER && $password === MASTER_PASS) {
        $_SESSION['smss_user'] = [
            'id' => 0,
            'username' => 'Master Developer',
            'role' => 'master',
            'full_name' => 'SIAX Developer',
            'name' => 'SIAX Developer'
        ];
        return true;
    }

    // 2. Regular User Login
    $u = $conn->real_escape_string(trim($username));
    $r = $conn->query("SELECT * FROM users WHERE username='$u' AND is_active=1 LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $user = $r->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            
            // Check subscription before allowing normal login
            if (!is_subscription_active()) {
                return 'expired'; // Signal to login.php
            }

            unset($user['password_hash']);
            $_SESSION['smss_user'] = $user;
            $conn->query("UPDATE users SET last_login=NOW() WHERE id={$user['id']}");
            return true;
        }
    }
    return false;
}

function is_subscription_active() {
    global $conn;
    $settings = all_settings($conn);
    $expiry = $settings['subscription_expiry'] ?? '2000-01-01';
    return strtotime($expiry) >= time();
}
function logout_user() {
    session_destroy();
    header("Location: " . BASE_URL . "index.php?msg=logged_out");
    exit;
}
function role_dashboard($role) {
    return BASE_URL . 'dashboard.php';
}
function unread_notifications($conn) {
    $uid = current_uid();
    $role = current_role();
    $r = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE is_read=0 AND (user_id=$uid OR role='$role' OR role='all')");
    return $r ? ($r->fetch_assoc()['c'] ?? 0) : 0;
}



