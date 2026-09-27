<?php
/**
 * SIAX SMSS - System Users & Access Permissions Management
 * Full Role-Based Access Control (RBAC) & Custom Module Permissions
 */
$page_title = 'User Management & Access Permissions';
$active_page = 'users';
require_once __DIR__ . '/../../includes/header.php';
require_module_access('system');

$msg = ''; $err = '';

// Available system modules for permission assignment
$available_modules = [
    'dashboard' => ['label' => 'Dashboard & Overview', 'icon' => 'fa-gauge-high', 'desc' => 'Access system dashboard and summary statistics'],
    'students'  => ['label' => 'Student Management', 'icon' => 'fa-user-graduate', 'desc' => 'View/add students, registration, classes, ID cards'],
    'academics' => ['label' => 'Academic & Exam Results', 'icon' => 'fa-pen-to-square', 'desc' => 'Marks entry, exam schedule, grading policy, roll slips'],
    'parents'   => ['label' => 'Parents Portal Management', 'icon' => 'fa-users-viewfinder', 'desc' => 'Announce exam results on public Parents Portal'],
    'finance'   => ['label' => 'Fee Management & Invoices', 'icon' => 'fa-money-bill-wave', 'desc' => 'Fee criteria, monthly invoices, quick pay, fee history'],
    'staff'     => ['label' => 'Staff & Payroll', 'icon' => 'fa-users-gear', 'desc' => 'Staff list, attendance, designations, salary slips'],
    'expenses'  => ['label' => 'Institute Expenses', 'icon' => 'fa-wallet', 'desc' => 'Manage institute expenses and expense categories'],
    'documents' => ['label' => 'Reports & Certificates', 'icon' => 'fa-file-lines', 'desc' => 'All reports, yearly summaries, student certificates'],
    'system'    => ['label' => 'System Settings & Users', 'icon' => 'fa-sliders', 'desc' => 'General school settings, user management, SMS gateway']
];

// Handle Actions: Add, Edit, Delete, Toggle Active, Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. ADD NEW USER
    if ($_POST['action'] === 'add_user') {
        $uname = $conn->real_escape_string(trim($_POST['username'] ?? ''));
        $fname = $conn->real_escape_string(trim($_POST['full_name'] ?? ''));
        $role  = $conn->real_escape_string($_POST['role'] ?? 'teacher');
        $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
        $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $pass  = trim($_POST['password'] ?? '');
        $selected_perms = $_POST['permissions'] ?? [];
        
        if (empty($selected_perms) && $role !== 'admin') {
            $selected_perms = ['dashboard'];
        }
        $perms_json = ($role === 'admin') ? NULL : $conn->real_escape_string(json_encode(array_values($selected_perms)));

        if ($uname && $pass) {
            $hash = $conn->real_escape_string(password_hash($pass, PASSWORD_DEFAULT));
            $sql = "INSERT INTO users (username, password_hash, full_name, role, email, phone, permissions, is_active) 
                    VALUES ('$uname', '$hash', '$fname', '$role', '$email', '$phone', " . ($perms_json ? "'$perms_json'" : "NULL") . ", 1)";
            if ($conn->query($sql)) {
                $msg = "User account '$uname' created successfully with custom access permissions.";
            } else {
                $err = "Failed to create user: " . $conn->error;
            }
        } else {
            $err = "Username and Password are required.";
        }
    }

    // 2. EDIT USER PERMISSIONS & DETAILS
    if ($_POST['action'] === 'edit_user') {
        $uid   = (int)($_POST['user_id'] ?? 0);
        $fname = $conn->real_escape_string(trim($_POST['full_name'] ?? ''));
        $role  = $conn->real_escape_string($_POST['role'] ?? 'teacher');
        $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
        $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $pass  = trim($_POST['password'] ?? '');
        $selected_perms = $_POST['permissions'] ?? [];

        $perms_json = ($role === 'admin') ? NULL : $conn->real_escape_string(json_encode(array_values($selected_perms)));

        if ($uid > 0) {
            $update_sql = "UPDATE users SET full_name='$fname', role='$role', email='$email', phone='$phone', permissions=" . ($perms_json ? "'$perms_json'" : "NULL");
            if (!empty($pass)) {
                $hash = $conn->real_escape_string(password_hash($pass, PASSWORD_DEFAULT));
                $update_sql .= ", password_hash='$hash'";
            }
            $update_sql .= " WHERE id=$uid";
            
            if ($conn->query($update_sql)) {
                $msg = "User details and access permissions updated successfully.";
            } else {
                $err = "Failed to update user: " . $conn->error;
            }
        }
    }

    // 3. TOGGLE USER ACTIVE/INACTIVE
    if ($_POST['action'] === 'toggle_user') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== current_uid()) {
            $conn->query("UPDATE users SET is_active = NOT is_active WHERE id=$uid");
            $msg = 'User account status updated.';
        } else {
            $err = 'You cannot deactivate your own active account.';
        }
    }

    // 4. RESET PASSWORD
    if ($_POST['action'] === 'reset_pass') {
        $uid  = (int)$_POST['user_id'];
        $pass = trim($_POST['new_pass'] ?? '');
        if ($pass && strlen($pass) >= 6) {
            $hash = $conn->real_escape_string(password_hash($pass, PASSWORD_DEFAULT));
            $conn->query("UPDATE users SET password_hash='$hash' WHERE id=$uid");
            $msg = 'Password reset successfully.';
        } else {
            $err = 'Password must be at least 6 characters long.';
        }
    }

    // 5. DELETE USER
    if ($_POST['action'] === 'delete_user') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== current_uid() && $uid > 0) {
            $conn->query("DELETE FROM users WHERE id=$uid");
            $msg = 'User account deleted successfully.';
        } else {
            $err = 'You cannot delete your own logged-in account.';
        }
    }
}

// Fetch all users
$users_q = $conn->query("SELECT * FROM users ORDER BY role ASC, full_name ASC");
$users_arr = [];
if ($users_q) while($u = $users_q->fetch_assoc()) $users_arr[] = $u;

$roles = ['admin' => 'Admin (Full Access)', 'principal' => 'Principal / Management', 'accountant' => 'Accountant / Fee Clerk', 'teacher' => 'Teacher', 'staff' => 'Staff'];
$role_colors = ['admin'=>'danger','principal'=>'info','accountant'=>'warning','teacher'=>'success','student'=>'secondary','parent'=>'secondary','staff'=>'secondary'];
?>

<style>
.user-avatar-badge { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #0984e3, #6c5ce7); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0; }
.preset-btn { background: var(--bg-glass); border: 1px solid var(--border); padding: 6px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; cursor: pointer; color: var(--text-primary); transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.preset-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-1px); }
.module-perm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; margin-top: 10px; }
.perm-card { background: var(--bg-primary); border: 1px solid var(--border); padding: 10px 14px; border-radius: 8px; display: flex; align-items: flex-start; gap: 10px; cursor: pointer; transition: all 0.2s; }
.perm-card:hover { border-color: var(--primary); background: var(--bg-card); }
.perm-card input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; margin-top: 2px; }
.perm-info .lbl { font-size: 0.85rem; font-weight: 700; color: var(--text-primary); display: block; }
.perm-info .desc { font-size: 0.72rem; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px; }
.mod-pill { display: inline-block; background: rgba(9, 132, 227, 0.1); color: var(--primary); border: 1px solid rgba(9, 132, 227, 0.2); padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; margin: 2px 1px; }
</style>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-users-gear" style="color:var(--primary);"></i> User Accounts &amp; Access Permissions</h1>
    <p>Create staff/clerk accounts, assign specific roles, and customize detailed module access permissions.</p>
  </div>
  <div>
    <button type="button" class="btn btn-primary" onclick="openAddUserModal()">
      <i class="fa-solid fa-user-plus"></i> Add New System User
    </button>
  </div>
</div>

<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:20px;padding:12px 16px;border-radius:8px;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-error" style="margin-bottom:20px;padding:12px 16px;border-radius:8px;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Users List Card -->
<div class="card mb-4">
  <div class="card-header">
    <h3><i class="fa-solid fa-users"></i> Registered System Users (<?= count($users_arr) ?>)</h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table" style="font-size:13px;">
      <thead>
        <tr>
          <th>User Profile</th>
          <th>Username</th>
          <th>Role</th>
          <th>Module Access Permissions</th>
          <th>Status</th>
          <th>Last Login</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if(empty($users_arr)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">No system users found.</td></tr>
      <?php else: ?>
        <?php foreach($users_arr as $u): ?>
        <?php 
          $u_perms = json_decode($u['permissions'] ?? '[]', true);
          if ($u['role'] === 'admin' || $u['role'] === 'master') {
              $perm_display = '<span class="mod-pill" style="background:#dcfce7;color:#15803d;border-color:#bbf7d0;"><i class="fa-solid fa-shield-halved"></i> All System Modules (Full Access)</span>';
          } elseif (!empty($u_perms) && is_array($u_perms)) {
              $perm_display = '';
              foreach ($u_perms as $pm) {
                  $lbl = $available_modules[$pm]['label'] ?? ucfirst($pm);
                  $perm_display .= '<span class="mod-pill"><i class="fa-solid ' . ($available_modules[$pm]['icon'] ?? 'fa-check') . '"></i> ' . htmlspecialchars($lbl) . '</span>';
              }
          } else {
              $perm_display = '<span class="mod-pill" style="background:#f1f5f9;color:#64748b;"><i class="fa-solid fa-clock"></i> Role Default Access</span>';
          }
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="user-avatar-badge"><?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1)) ?></div>
              <div>
                <strong style="font-size:0.92rem;color:var(--text-dark);"><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></strong>
                <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($u['email'] ?: 'No email') ?> <?= $u['phone'] ? ' | ' . htmlspecialchars($u['phone']) : '' ?></div>
              </div>
            </div>
          </td>
          <td><code><?= htmlspecialchars($u['username']) ?></code></td>
          <td><span class="badge badge-<?= $role_colors[$u['role']] ?? 'secondary' ?>"><?= ucfirst($u['role']) ?></span></td>
          <td style="max-width:320px;"><?= $perm_display ?></td>
          <td>
            <?php if($u['is_active']): ?>
              <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Active</span>
            <?php else: ?>
              <span class="badge badge-danger"><i class="fa-solid fa-circle-xmark"></i> Inactive</span>
            <?php endif; ?>
          </td>
          <td style="font-size:0.8rem;color:var(--text-muted);"><?= $u['last_login'] ? date('d M Y, h:i A', strtotime($u['last_login'])) : 'Never' ?></td>
          <td class="table-actions" style="text-align:right;">
            <button type="button" class="btn btn-xs btn-primary" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Edit User &amp; Permissions">
              <i class="fa-solid fa-pen-to-square"></i> Edit
            </button>
            <button type="button" class="btn btn-xs btn-warning" onclick="showResetPass(<?= $u['id'] ?>,'<?= htmlspecialchars(addslashes($u['full_name'] ?: $u['username'])) ?>')" title="Reset Password">
              <i class="fa-solid fa-key"></i> Pass
            </button>
            <?php if($u['id'] != current_uid()): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle active status for this user?')">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-xs btn-secondary" title="Toggle Active/Inactive">
                  <?= $u['is_active'] ? '<i class="fa-solid fa-lock"></i>' : '<i class="fa-solid fa-lock-open"></i>' ?>
                </button>
              </form>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this user account?')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-xs btn-danger" title="Delete User">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal 1: Add New User Modal -->
<div id="addUserModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.7); z-index:9999; align-items:center; justify-content:center; padding:15px; backdrop-filter:blur(4px);">
  <div class="card" style="width:750px; max-width:98vw; max-height:92vh; overflow-y:auto; padding:25px;">
    <div class="card-header" style="margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px;">
      <h3><i class="fa-solid fa-user-plus" style="color:var(--primary);"></i> Add New System User</h3>
      <button type="button" onclick="closeAddUserModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);">&times;</button>
    </div>
    
    <form method="POST" id="addUserForm">
      <input type="hidden" name="action" value="add_user">
      
      <div class="form-row">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Ali Raza (Fee Clerk)" required>
        </div>
        <div class="form-group">
          <label>Username * (Login Identifier)</label>
          <input type="text" name="username" class="form-control" placeholder="e.g. clerk_ali" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6">
        </div>
        <div class="form-group">
          <label>Primary Role *</label>
          <select name="role" id="addRoleSelect" class="form-control" onchange="handleRolePresetChange(this.value, 'add')">
            <?php foreach($roles as $r_key => $r_lbl): ?>
            <option value="<?= $r_key ?>"><?= $r_lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="user@school.com">
        </div>
        <div class="form-group">
          <label>Phone Contact</label>
          <input type="text" name="phone" class="form-control" placeholder="0300-0000000">
        </div>
      </div>

      <!-- Quick Permission Presets Bar -->
      <div style="background:rgba(99,102,241,0.06); border:1px solid rgba(99,102,241,0.2); padding:12px 15px; border-radius:10px; margin:15px 0 10px;">
        <div style="font-size:0.8rem; font-weight:700; color:var(--text-primary); margin-bottom:8px;">
          <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--primary);"></i> Quick Access Presets (1-Tap Fill):
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
          <button type="button" class="preset-btn" onclick="applyPreset('clerk', 'add')"><i class="fa-solid fa-receipt"></i> Fee Clerk Preset (Fees Only)</button>
          <button type="button" class="preset-btn" onclick="applyPreset('teacher', 'add')"><i class="fa-solid fa-pen-to-square"></i> Teacher Preset (Academic &amp; Results)</button>
          <button type="button" class="preset-btn" onclick="applyPreset('principal', 'add')"><i class="fa-solid fa-user-shield"></i> Principal Preset (All Except System)</button>
          <button type="button" class="preset-btn" onclick="applyPreset('all', 'add')"><i class="fa-solid fa-check-double"></i> Select All</button>
          <button type="button" class="preset-btn" onclick="applyPreset('clear', 'add')"><i class="fa-solid fa-eraser"></i> Clear All</button>
        </div>
      </div>

      <!-- Custom Module Access Checkboxes -->
      <label style="font-size:0.85rem; font-weight:700; color:var(--text-primary); display:block; margin-bottom:6px;">
        Custom Module Access Permissions:
      </label>
      <div class="module-perm-grid" id="addPermGrid">
        <?php foreach($available_modules as $mod_key => $mod_data): ?>
        <label class="perm-card">
          <input type="checkbox" name="permissions[]" value="<?= $mod_key ?>" class="add-perm-cb" id="add_perm_<?= $mod_key ?>" checked>
          <div class="perm-info">
            <span class="lbl"><i class="fa-solid <?= $mod_data['icon'] ?>" style="color:var(--primary); margin-right:4px;"></i> <?= htmlspecialchars($mod_data['label']) ?></span>
            <span class="desc"><?= htmlspecialchars($mod_data['desc']) ?></span>
          </div>
        </label>
        <?php endforeach; ?>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid var(--border); padding-top:15px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="padding:10px 24px;"><i class="fa-solid fa-plus"></i> Create User Account</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 2: Edit User & Permissions Modal -->
<div id="editUserModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.7); z-index:9999; align-items:center; justify-content:center; padding:15px; backdrop-filter:blur(4px);">
  <div class="card" style="width:750px; max-width:98vw; max-height:92vh; overflow-y:auto; padding:25px;">
    <div class="card-header" style="margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:10px;">
      <h3><i class="fa-solid fa-pen-to-square" style="color:var(--primary);"></i> Edit User &amp; Access Permissions</h3>
      <button type="button" onclick="closeEditUserModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);">&times;</button>
    </div>
    
    <form method="POST" id="editUserForm">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="editUserId">
      
      <div class="form-row">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" id="editFullName" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Username</label>
          <input type="text" id="editUsername" class="form-control" readonly style="background:var(--bg-primary); cursor:not-allowed;">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>New Password (Leave blank to keep current)</label>
          <input type="password" name="password" class="form-control" placeholder="Update password (optional)">
        </div>
        <div class="form-group">
          <label>Primary Role *</label>
          <select name="role" id="editRoleSelect" class="form-control" onchange="handleRolePresetChange(this.value, 'edit')">
            <?php foreach($roles as $r_key => $r_lbl): ?>
            <option value="<?= $r_key ?>"><?= $r_lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" id="editEmail" class="form-control">
        </div>
        <div class="form-group">
          <label>Phone Contact</label>
          <input type="text" name="phone" id="editPhone" class="form-control">
        </div>
      </div>

      <!-- Quick Permission Presets Bar -->
      <div style="background:rgba(99,102,241,0.06); border:1px solid rgba(99,102,241,0.2); padding:12px 15px; border-radius:10px; margin:15px 0 10px;">
        <div style="font-size:0.8rem; font-weight:700; color:var(--text-primary); margin-bottom:8px;">
          <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--primary);"></i> Quick Access Presets (1-Tap Fill):
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
          <button type="button" class="preset-btn" onclick="applyPreset('clerk', 'edit')"><i class="fa-solid fa-receipt"></i> Fee Clerk Preset (Fees Only)</button>
          <button type="button" class="preset-btn" onclick="applyPreset('teacher', 'edit')"><i class="fa-solid fa-pen-to-square"></i> Teacher Preset (Academic &amp; Results)</button>
          <button type="button" class="preset-btn" onclick="applyPreset('principal', 'edit')"><i class="fa-solid fa-user-shield"></i> Principal Preset (All Except System)</button>
          <button type="button" class="preset-btn" onclick="applyPreset('all', 'edit')"><i class="fa-solid fa-check-double"></i> Select All</button>
          <button type="button" class="preset-btn" onclick="applyPreset('clear', 'edit')"><i class="fa-solid fa-eraser"></i> Clear All</button>
        </div>
      </div>

      <!-- Custom Module Access Checkboxes -->
      <label style="font-size:0.85rem; font-weight:700; color:var(--text-primary); display:block; margin-bottom:6px;">
        Custom Module Access Permissions:
      </label>
      <div class="module-perm-grid" id="editPermGrid">
        <?php foreach($available_modules as $mod_key => $mod_data): ?>
        <label class="perm-card">
          <input type="checkbox" name="permissions[]" value="<?= $mod_key ?>" class="edit-perm-cb" id="edit_perm_<?= $mod_key ?>">
          <div class="perm-info">
            <span class="lbl"><i class="fa-solid <?= $mod_data['icon'] ?>" style="color:var(--primary); margin-right:4px;"></i> <?= htmlspecialchars($mod_data['label']) ?></span>
            <span class="desc"><?= htmlspecialchars($mod_data['desc']) ?></span>
          </div>
        </label>
        <?php endforeach; ?>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid var(--border); padding-top:15px;">
        <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="padding:10px 24px;"><i class="fa-solid fa-floppy-disk"></i> Update User Account</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 3: Reset Password Modal -->
<div id="resetPassModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.7); z-index:9999; align-items:center; justify-content:center; padding:15px; backdrop-filter:blur(4px);">
  <div class="card" style="width:380px; max-width:95vw; padding:20px;">
    <div class="card-header" style="margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid var(--border);">
      <h3><i class="fa-solid fa-key" style="color:var(--primary);"></i> Reset User Password</h3>
      <button type="button" onclick="document.getElementById('resetPassModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);">&times;</button>
    </div>
    <form method="POST" id="resetPassForm">
      <input type="hidden" name="action" value="reset_pass">
      <input type="hidden" name="user_id" id="resetUserId">
      <div class="form-group">
        <label id="resetUserLabel">New Password for User</label>
        <input type="password" name="new_pass" class="form-control" placeholder="Min 6 characters" required minlength="6">
      </div>
      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px;">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('resetPassModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i> Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddUserModal() {
  document.getElementById('addUserModal').style.display = 'flex';
}
function closeAddUserModal() {
  document.getElementById('addUserModal').style.display = 'none';
}

function openEditUserModal(user) {
  document.getElementById('editUserId').value = user.id;
  document.getElementById('editFullName').value = user.full_name || '';
  document.getElementById('editUsername').value = user.username || '';
  document.getElementById('editEmail').value = user.email || '';
  document.getElementById('editPhone').value = user.phone || '';
  document.getElementById('editRoleSelect').value = user.role || 'teacher';

  // Uncheck all edit checkboxes
  document.querySelectorAll('.edit-perm-cb').forEach(cb => cb.checked = false);

  // Check saved permissions
  var perms = [];
  try { perms = JSON.parse(user.permissions || '[]'); } catch(e) {}
  
  if (user.role === 'admin' || user.role === 'master') {
    document.querySelectorAll('.edit-perm-cb').forEach(cb => cb.checked = true);
  } else if (perms && perms.length > 0) {
    perms.forEach(p => {
      var cb = document.getElementById('edit_perm_' + p);
      if (cb) cb.checked = true;
    });
  } else {
    // Default role preset
    applyPreset(user.role === 'accountant' ? 'clerk' : (user.role === 'teacher' ? 'teacher' : 'principal'), 'edit');
  }

  document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditUserModal() {
  document.getElementById('editUserModal').style.display = 'none';
}

function showResetPass(uid, name) {
  document.getElementById('resetUserId').value = uid;
  document.getElementById('resetUserLabel').textContent = 'New Password for: ' + name;
  document.getElementById('resetPassModal').style.display = 'flex';
}

function applyPreset(preset, prefix) {
  var selector = '.' + prefix + '-perm-cb';
  var checkboxes = document.querySelectorAll(selector);
  checkboxes.forEach(cb => cb.checked = false);

  if (preset === 'all') {
    checkboxes.forEach(cb => cb.checked = true);
  } else if (preset === 'clerk' || preset === 'accountant') {
    var allowed = ['dashboard', 'finance'];
    allowed.forEach(p => {
      var cb = document.getElementById(prefix + '_perm_' + p);
      if (cb) cb.checked = true;
    });
  } else if (preset === 'teacher') {
    var allowed = ['dashboard', 'students', 'academics'];
    allowed.forEach(p => {
      var cb = document.getElementById(prefix + '_perm_' + p);
      if (cb) cb.checked = true;
    });
  } else if (preset === 'principal') {
    var allowed = ['dashboard', 'students', 'academics', 'parents', 'finance', 'expenses', 'documents'];
    allowed.forEach(p => {
      var cb = document.getElementById(prefix + '_perm_' + p);
      if (cb) cb.checked = true;
    });
  }
}

function handleRolePresetChange(role, prefix) {
  if (role === 'accountant') applyPreset('clerk', prefix);
  else if (role === 'teacher') applyPreset('teacher', prefix);
  else if (role === 'principal') applyPreset('principal', prefix);
  else if (role === 'admin') applyPreset('all', prefix);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
