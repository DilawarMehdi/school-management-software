<?php
$page_title = 'Settings';
$active_page = 'settings';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin');

$msg = ''; $err = '';

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_settings') {
        $fields = ['school_name','school_address','school_phone','school_email','principal_name','session_year','currency','grade_a_plus','grade_a','grade_b','grade_c','grade_d'];
        foreach ($fields as $field) {
            $key = $conn->real_escape_string($field);
            $val = $conn->real_escape_string(trim($_POST[$field] ?? ''));
            $conn->query("INSERT INTO settings (setting_key,setting_value) VALUES ('$key','$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
        }
        
        // Handle Logo Upload
        if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $filename = 'school_logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['school_logo']['tmp_name'], UPLOAD_PATH . $filename)) {
                    $conn->query("INSERT INTO settings (setting_key,setting_value) VALUES ('school_logo','$filename') ON DUPLICATE KEY UPDATE setting_value='$filename'");
                }
            }
        }

        $msg = 'Settings saved successfully.';
        $settings = all_settings($conn); // refresh
        $school_name = $settings['school_name'];
    }

    // Add user
    if ($_POST['action'] === 'add_user') {
        $uname = $conn->real_escape_string(trim($_POST['username'] ?? ''));
        $fname = $conn->real_escape_string(trim($_POST['full_name'] ?? ''));
        $role  = $conn->real_escape_string($_POST['role'] ?? 'teacher');
        $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
        $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $pass  = trim($_POST['password'] ?? '');
        if ($uname && $pass) {
            $hash = $conn->real_escape_string(password_hash($pass, PASSWORD_DEFAULT));
            if ($conn->query("INSERT INTO users (username,password_hash,full_name,role,email,phone) VALUES ('$uname','$hash','$fname','$role','$email','$phone')")) {
                $msg = 'User created: '.$uname;
            } else { $err = 'Username already exists.'; }
        } else { $err = 'Username and password are required.'; }
    }

    // Toggle user
    if ($_POST['action'] === 'toggle_user') {
        $uid = (int)$_POST['user_id'];
        $conn->query("UPDATE users SET is_active = NOT is_active WHERE id=$uid AND id != ".current_uid());
        $msg = 'User status updated.';
    }

    // Reset password
    if ($_POST['action'] === 'reset_pass') {
        $uid  = (int)$_POST['user_id'];
        $pass = trim($_POST['new_pass'] ?? '');
        if ($pass && strlen($pass) >= 6) {
            $hash = $conn->real_escape_string(password_hash($pass, PASSWORD_DEFAULT));
            $conn->query("UPDATE users SET password_hash='$hash' WHERE id=$uid");
            $msg = 'Password reset successfully.';
        } else { $err = 'Password must be at least 6 characters.'; }
    }
}

$users_q = $conn->query("SELECT * FROM users ORDER BY role,full_name");
$users_arr = [];
if ($users_q) while($u=$users_q->fetch_assoc()) $users_arr[]=$u;

$roles = ['admin','principal','accountant','teacher','student','parent'];
$role_colors = ['admin'=>'danger','principal'=>'info','accountant'=>'warning','teacher'=>'success','student'=>'secondary','parent'=>'secondary'];
?>

<div class="page-header">
  <div><h1><i class="fa-solid fa-gear"></i>️ Settings</h1><p>Configure school information and manage users</p></div>
</div>

<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-error"><?= $err ?></div><?php endif; ?>

<div class="card-grid card-grid-2">
  <!-- School Settings -->
  <div class="card">
    <div class="card-header"><h3><i class="fa-solid fa-school"></i> School Information</h3></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_settings">
      
      <!-- Logo Upload Section -->
      <div style="display:flex; align-items:center; gap:15px; background:rgba(99,102,241,.05); padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid rgba(99,102,241,.1);">
        <div style="width:70px; height:70px; border-radius:10px; overflow:hidden; background:#fff; border:1px solid var(--border); display:flex; align-items:center; justify-content:center;">
          <?php if(!empty($settings['school_logo']) && file_exists(UPLOAD_PATH.$settings['school_logo'])): ?>
            <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($settings['school_logo']) ?>" style="width:100%; height:100%; object-fit:contain;">
          <?php else: ?>
            <i class="fa-solid fa-image" style="font-size:24px; color:#ccc;"></i>
          <?php endif; ?>
        </div>
        <div style="flex:1;">
          <label style="font-weight:700; font-size:13px; display:block; margin-bottom:4px;">School Logo</label>
          <input type="file" name="school_logo" class="form-control" accept="image/*" style="font-size:12px;">
          <p style="font-size:10px; color:var(--text-muted); margin-top:4px;">Best size: Square (512x512px). PNG or JPG supported.</p>
        </div>
      </div>

      <?php $fields = [
        'school_name' => 'School Name',
        'principal_name' => "Principal's Name",
        'school_address' => 'Address',
        'school_phone' => 'Phone',
        'school_email' => 'Email',
        'session_year' => 'Session Year (e.g. 2025-2026)',
        'currency' => 'Currency Symbol (e.g. PKR)',
      ]; ?>
      <?php foreach($fields as $key => $label): ?>
      <div class="form-group">
        <label><?= $label ?></label>
        <input type="text" name="<?= $key ?>" class="form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
      </div>
      <?php endforeach; ?>
      <div style="border-top:1px solid var(--border);padding-top:12px;margin-top:12px">
        <label style="font-weight:600;margin-bottom:8px;display:block">Grade Thresholds (%)</label>
        <div class="form-row">
          <?php foreach(['grade_a_plus'=>'A+ (min)','grade_a'=>'A (min)','grade_b'=>'B (min)','grade_c'=>'C (min)','grade_d'=>'D (min)'] as $key=>$label): ?>
          <div class="form-group"><label><?= $label ?></label><input type="number" name="<?= $key ?>" class="form-control" value="<?= htmlspecialchars($settings[$key] ?? '') ?>" min="0" max="100"></div>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-2 w-100" style="padding:12px;"><i class="fa-solid fa-floppy-disk"></i> Save School Information</button>
    </form>
  </div>

  <!-- User Management -->
  <div>
    <div class="card mb-3" style="background:linear-gradient(135deg, rgba(99,102,241,0.1), rgba(168,85,247,0.1)); border:1px solid rgba(99,102,241,0.3);">
      <div style="padding:15px; text-align:center;">
        <i class="fa-solid fa-users-gear" style="font-size:2rem; color:var(--accent); margin-bottom:8px;"></i>
        <h4 style="margin-bottom:4px; font-weight:700;">Advanced User Management &amp; Permissions</h4>
        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:12px;">Create staff accounts, set fee clerk/teacher presets, and assign specific module permissions.</p>
        <a href="<?= BASE_URL ?>modules/settings/users.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Open User Accounts &amp; Permissions Manager</a>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h3><i class="fa-solid fa-user"></i> Quick Add User</h3></div>
      <form method="POST">
        <input type="hidden" name="action" value="add_user">
        <div class="form-row">
          <div class="form-group"><label>Username *</label><input type="text" name="username" class="form-control" required></div>
          <div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Role</label><select name="role" class="form-control"><?php foreach($roles as $r): ?><option value="<?= $r ?>"><?= ucfirst($r) ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label>Password *</label><input type="password" name="password" class="form-control" required placeholder="Min 6 chars"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
        </div>
        <button type="submit" class="btn btn-success mt-2"><i class="fa-solid fa-plus"></i> Create User</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fa-solid fa-users"></i> System Users</h3></div>
      <div class="table-wrapper">
        <table class="data-table" style="font-size:13px">
          <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach($users_arr as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['full_name']) ?></td>
            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
            <td><span class="badge badge-<?= $role_colors[$u['role']] ?? 'secondary' ?>"><?= ucfirst($u['role']) ?></span></td>
            <td><span class="badge badge-<?= $u['is_active']?'success':'danger' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
            <td class="table-actions">
              <?php if($u['id'] != current_uid()): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-xs btn-secondary" title="Toggle Active"><?= $u['is_active']?'<i class="fa-solid fa-lock"></i>':'<i class="fa-solid fa-lock-open"></i>' ?></button>
              </form>
              <?php endif; ?>
              <button class="btn btn-xs btn-warning" onclick="showResetPass(<?= $u['id'] ?>,'<?= htmlspecialchars(addslashes($u['full_name'])) ?>')" title="Reset Password"><i class="fa-solid fa-key"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPassModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;display:none;align-items:center;justify-content:center">
  <div class="card" style="width:360px;max-width:95vw">
    <div class="card-header"><h3><i class="fa-solid fa-key"></i> Reset Password</h3></div>
    <form method="POST" id="resetPassForm">
      <input type="hidden" name="action" value="reset_pass">
      <input type="hidden" name="user_id" id="resetUserId">
      <div class="form-group"><label id="resetUserLabel">New Password for User</label><input type="password" name="new_pass" class="form-control" placeholder="Min 6 characters" required minlength="6"></div>
      <div class="btn-group mt-2">
        <button type="submit" class="btn btn-primary">Reset Password</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('resetPassModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function showResetPass(uid, name) {
  document.getElementById('resetUserId').value = uid;
  document.getElementById('resetUserLabel').textContent = 'New Password for: ' + name;
  document.getElementById('resetPassModal').style.display = 'flex';
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




