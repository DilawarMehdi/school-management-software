<?php
require_once __DIR__ . '/config/db.php';
session_start();

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php?msg=logged_out');
    exit;
}

// Already logged in?
if (isset($_SESSION['smss_user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/auth.php';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $login_result = login_user($conn, $username, $password);
        
        if ($login_result === true) {
            header('Location: dashboard.php');
            exit;
        } elseif ($login_result === 'expired') {
            $error = 'Software subscription has expired. Please contact support.';
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

if (isset($_GET['err']) && $_GET['err'] === 'subscription_expired') {
    $error = 'Access Denied: Your software subscription has expired.';
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'logged_out') $msg = 'You have been logged out successfully.';
    if ($_GET['msg'] === 'session_expired') $msg = 'Session expired. Please log in again.';
    if ($_GET['msg'] === 'signup_pending') $msg = 'Your registration request has been submitted and is pending super admin approval.';
}

$school_name = get_setting($conn, 'school_name', 'SIAX SMSS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= htmlspecialchars($school_name) ?></title>
<meta name="description" content="SIAX SMSS School Management Software System Login">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/main.css">
<style>
.password-field-wrapper { position: relative; width: 100%; }
.password-toggle-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  transition: color 0.2s ease;
  z-index: 2;
}
.password-toggle-btn:hover { color: var(--accent-light); }
.password-field-wrapper input { padding-right: 42px; }
</style>
<script>
(function() {
  var saved = localStorage.getItem('smss_theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
})();

function togglePassword(inputId, iconId) {
  var input = document.getElementById(inputId);
  var icon = document.getElementById(iconId);
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}
</script>
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="logo-icon">S</div>
      <h1><?= htmlspecialchars($school_name) ?></h1>
      <p>School Management Software System</p>
    </div>

    <?php if ($error): ?>
    <div class="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($msg): ?>
    <div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" class="login-form" id="loginForm">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-field-wrapper">
          <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
          <button type="button" class="password-toggle-btn" onclick="togglePassword('password', 'passwordEyeIcon')" aria-label="Toggle password visibility" title="Show/Hide Password">
            <i class="fa-solid fa-eye" id="passwordEyeIcon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" id="loginBtn">Sign In</button>
    </form>

    <div class="login-footer">
      <p style="margin-bottom:8px;">No account? <a href="register.php" style="color: var(--accent-light); font-weight: 600;">Sign Up / Register</a></p>
      <p>Default: <strong>admin</strong> / <strong>password</strong></p>
      <p style="margin-top:8px;">Powered by <strong>SIAC Technologies</strong></p>
    </div>
  </div>
</div>
</body>
</html>



