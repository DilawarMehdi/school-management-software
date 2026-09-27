<?php
/**
 * SIAX SMSS - Session Management
 * Premium Fall/Sessions UI design matching SIAX premium aesthetics.
 */
$page_title = 'Fall/Sessions';
$active_page = 'session_management';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal');

$msg = ''; $err = '';

// Load all settings
$settings = all_settings($conn);
$active_session = $settings['session_year'] ?? '2025-2026';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 1. Set Active Session
    if ($_POST['action'] === 'set_active') {
        $session = $conn->real_escape_string(trim($_POST['session_title'] ?? ''));
        if ($session) {
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('session_year', '$session') ON DUPLICATE KEY UPDATE setting_value='$session'");
            $msg = "Active academic session has been changed to: " . htmlspecialchars($session);
            $active_session = $session;
        }
    }
    
    // 2. Add New Session
    if ($_POST['action'] === 'add_session') {
        $new_session = $conn->real_escape_string(trim($_POST['session_title'] ?? ''));
        if ($new_session) {
            $session_list_str = $settings['session_list'] ?? '2024-2025,2025-2026,2026-2027';
            $sessions = array_filter(array_map('trim', explode(',', $session_list_str)));
            if (!in_array($new_session, $sessions)) {
                $sessions[] = $new_session;
                sort($sessions);
                $new_list_str = implode(',', $sessions);
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('session_list', '$new_list_str') ON DUPLICATE KEY UPDATE setting_value='$new_list_str'");
                $msg = "New session '" . htmlspecialchars($new_session) . "' added successfully.";
                $settings['session_list'] = $new_list_str; // update local copy
            } else {
                $err = "A session with this title already exists.";
            }
        } else {
            $err = "Session title cannot be empty.";
        }
    }

    // 3. Update Session Title
    if ($_POST['action'] === 'update_session') {
        $old_title = $conn->real_escape_string(trim($_POST['old_title'] ?? ''));
        $new_title = $conn->real_escape_string(trim($_POST['new_title'] ?? ''));
        if ($old_title && $new_title) {
            $session_list_str = $settings['session_list'] ?? '2024-2025,2025-2026,2026-2027';
            $sessions = array_filter(array_map('trim', explode(',', $session_list_str)));
            
            if (in_array($new_title, $sessions) && $new_title !== $old_title) {
                $err = "A session with the new title already exists.";
            } else {
                // Update in the list
                $key = array_search($old_title, $sessions);
                if ($key !== false) {
                    $sessions[$key] = $new_title;
                }
                sort($sessions);
                $new_list_str = implode(',', $sessions);
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('session_list', '$new_list_str') ON DUPLICATE KEY UPDATE setting_value='$new_list_str'");
                
                // If it was the active session, update that setting too
                if ($old_title === $active_session) {
                    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('session_year', '$new_title') ON DUPLICATE KEY UPDATE setting_value='$new_title'");
                    $active_session = $new_title;
                }
                
                // Cascade update to student_enrollments table
                $conn->query("UPDATE student_enrollments SET session_year = '$new_title' WHERE session_year = '$old_title'");
                
                $msg = "Session successfully updated from '" . htmlspecialchars($old_title) . "' to '" . htmlspecialchars($new_title) . "'.";
                $settings['session_list'] = $new_list_str; // update local copy
            }
        }
    }

    // 4. Delete Session
    if ($_POST['action'] === 'delete_session') {
        $del_title = $conn->real_escape_string(trim($_POST['session_title'] ?? ''));
        if ($del_title) {
            if ($del_title === $active_session) {
                $err = "Cannot delete the active running session. Set another session as active first.";
            } else {
                $session_list_str = $settings['session_list'] ?? '2024-2025,2025-2026,2026-2027';
                $sessions = array_filter(array_map('trim', explode(',', $session_list_str)));
                $sessions = array_diff($sessions, [$del_title]);
                sort($sessions);
                $new_list_str = implode(',', $sessions);
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('session_list', '$new_list_str') ON DUPLICATE KEY UPDATE setting_value='$new_list_str'");
                
                $msg = "Session '" . htmlspecialchars($del_title) . "' deleted successfully.";
                $settings['session_list'] = $new_list_str; // update local copy
            }
        }
    }
}

// Get sessions list
$session_list_str = $settings['session_list'] ?? '2024-2025,2025-2026,2026-2027';
$sessions_arr = array_filter(array_map('trim', explode(',', $session_list_str)));
if (empty($sessions_arr)) {
    $sessions_arr = ['2024-2025', '2025-2026', '2026-2027'];
}

// Get student counts per session
$student_counts = [];
foreach ($sessions_arr as $s) {
    // Check actual enrollments count
    $r = $conn->query("SELECT COUNT(DISTINCT student_id) as total FROM student_enrollments WHERE session_year = '$s'");
    $count = $r ? $r->fetch_assoc()['total'] : 0;
    
    // Fallback for active session to show all active students if enrollments table is empty
    if ($count == 0 && $s === $active_session) {
        $r_active = $conn->query("SELECT COUNT(*) as total FROM students WHERE status='Active'");
        $count = $r_active ? $r_active->fetch_assoc()['total'] : 0;
    }
    
    $student_counts[$s] = $count;
}
?>

<style>
/* Premium Action buttons */
.action-link {
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s;
    cursor: pointer;
}
.action-link.update { color: #0984e3; margin-right: 8px; }
.action-link.update:hover { color: #00b894; text-decoration: underline; }
.action-link.delete { color: #ef4444; margin-right: 8px; }
.action-link.delete:hover { color: #d63031; text-decoration: underline; }
.action-link.set-active { color: #6c5ce7; font-weight: 700; }
.action-link.set-active:hover { color: #00b894; text-decoration: underline; }
</style>

<div class="main-container">
    <!-- Header with Action Button matching Mockup -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0; color: var(--text-primary);">Fall/Sessions</h1>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 700; border-radius: 6px;">
            <i class="fa-solid fa-plus-circle"></i> Add New Fall/Session
        </button>
    </div>

    <?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:20px;"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="login-error" style="margin-bottom:20px;"><?= $err ?></div><?php endif; ?>

    <!-- Main Card containing the Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0; overflow: hidden; margin-bottom: 30px;">
        <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; background: rgba(255,255,255,0.02); display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-table" style="color: #00b894; font-size: 1.1rem;"></i>
            <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary);">Fall/Session</h3>
        </div>

        <div class="table-wrapper" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 12px 20px; text-align: left; font-weight: 700; width: 40%;">Title</th>
                        <th style="padding: 12px 20px; text-align: left; font-weight: 700; width: 25%;">No Of Student</th>
                        <th style="padding: 12px 20px; text-align: left; font-weight: 700; width: 35%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions_arr as $s): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 15px 20px; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                <?= htmlspecialchars($s) ?>
                            </td>
                            <td style="padding: 15px 20px; font-weight: 500; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= $student_counts[$s] ?>
                            </td>
                            <td style="padding: 15px 20px; font-size: 0.85rem;">
                                <!-- Update Action -->
                                <a class="action-link update" onclick="openUpdateModal('<?= htmlspecialchars(addslashes($s)) ?>')">Update</a>
                                <span style="color: var(--border); margin-right: 8px;">|</span>
                                
                                <!-- Delete Action -->
                                <a class="action-link delete" onclick="confirmDeleteSession('<?= htmlspecialchars(addslashes($s)) ?>')">Delete</a>
                                <span style="color: var(--border); margin-right: 8px;">|</span>
                                
                                <!-- Status Action -->
                                <?php if ($s === $active_session): ?>
                                    <span style="color: var(--text-muted); font-weight: 500; font-style: italic;">Currently running Session / Fall</span>
                                <?php else: ?>
                                    <a class="action-link set-active" onclick="setActiveSession('<?= htmlspecialchars(addslashes($s)) ?>')">Set it Currently Running Fall / Session</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD NEW SESSION MODAL -->
<div id="addSessionModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 450px; width: 90%; overflow: hidden; box-shadow: var(--shadow-lg);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-plus-circle" style="color: #0984e3; margin-right: 8px;"></i> Add New Fall/Session</h3>
            <button class="modal-close" onclick="closeAddModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form method="POST">
                <input type="hidden" name="action" value="add_session">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="add_title" style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Session Title *</label>
                    <input type="text" name="session_title" id="add_title" class="form-control" placeholder="e.g. Session 2026-27" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- UPDATE SESSION MODAL -->
<div id="updateSessionModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 450px; width: 90%; overflow: hidden; box-shadow: var(--shadow-lg);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-pen-to-square" style="color: #00b894; margin-right: 8px;"></i> Update Fall/Session</h3>
            <button class="modal-close" onclick="closeUpdateModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form method="POST">
                <input type="hidden" name="action" value="update_session">
                <input type="hidden" name="old_title" id="update_old_title">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="update_new_title" style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Session Title *</label>
                    <input type="text" name="new_title" id="update_new_title" class="form-control" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #00b894;">Update Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- HIDDEN POST FORMS FOR ACTIONS -->
<form id="actionForm" method="POST" style="display:none;">
    <input type="hidden" name="action" id="actionFormAction">
    <input type="hidden" name="session_title" id="actionFormTitle">
</form>

<script>
function openAddModal() {
    document.getElementById('addSessionModal').style.display = 'flex';
    document.getElementById('add_title').focus();
}
function closeAddModal() {
    document.getElementById('addSessionModal').style.display = 'none';
}
function openUpdateModal(title) {
    document.getElementById('update_old_title').value = title;
    document.getElementById('update_new_title').value = title;
    document.getElementById('updateSessionModal').style.display = 'flex';
    document.getElementById('update_new_title').focus();
}
function closeUpdateModal() {
    document.getElementById('updateSessionModal').style.display = 'none';
}
function setActiveSession(title) {
    if (confirm("Are you sure you want to set '" + title + "' as the currently running active academic session?")) {
        document.getElementById('actionFormAction').value = 'set_active';
        document.getElementById('actionFormTitle').value = title;
        document.getElementById('actionForm').submit();
    }
}
function confirmDeleteSession(title) {
    if (confirm("Are you sure you want to permanently delete session '" + title + "'? All associated records will remain intact, but this option will no longer be listed.")) {
        document.getElementById('actionFormAction').value = 'delete_session';
        document.getElementById('actionFormTitle').value = title;
        document.getElementById('actionForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
