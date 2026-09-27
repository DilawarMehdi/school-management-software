<?php
/**
 * SIAX SMSS - Designation List
 * Manage staff designations and work titles in the school.
 */
$page_title = 'Designation List';
$active_page = 'designations';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal');

$msg = ''; $err = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $title = $conn->real_escape_string(trim($_POST['title'] ?? ''));
        $desc = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        if ($title) {
            if ($conn->query("INSERT INTO staff_designations (title, description) VALUES ('$title', '$desc')")) {
                $msg = "Designation '$title' added successfully.";
            } else {
                $err = "Error adding designation: " . $conn->error;
            }
        } else {
            $err = "Designation title is required.";
        }
    }
    
    if ($_POST['action'] === 'update') {
        $id = (int)$_POST['id'];
        $title = $conn->real_escape_string(trim($_POST['title'] ?? ''));
        $desc = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        if ($title && $id) {
            if ($conn->query("UPDATE staff_designations SET title='$title', description='$desc' WHERE id=$id")) {
                $msg = "Designation updated successfully.";
            } else {
                $err = "Error updating designation: " . $conn->error;
            }
        } else {
            $err = "Designation title is required.";
        }
    }
    
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            // Check if any staff member is associated
            $check = $conn->query("SELECT COUNT(*) as total FROM staff_members WHERE designation_id=$id");
            $has_staff = $check ? $check->fetch_assoc()['total'] : 0;
            if ($has_staff > 0) {
                $err = "Cannot delete designation. There are active staff members assigned to it.";
            } else {
                if ($conn->query("DELETE FROM staff_designations WHERE id=$id")) {
                    $msg = "Designation deleted successfully.";
                } else {
                    $err = "Error deleting designation: " . $conn->error;
                }
            }
        }
    }
}

// Fetch designations
$q = $conn->query("
    SELECT d.*, COUNT(s.id) as staff_count 
    FROM staff_designations d 
    LEFT JOIN staff_members s ON d.id = s.designation_id 
    GROUP BY d.id 
    ORDER BY d.title
");
$designations = [];
if ($q) while ($row = $q->fetch_assoc()) $designations[] = $row;
?>

<div class="main-container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0; color: var(--text-primary);">Designation List</h1>
            <p>Define official work titles, roles, and professional descriptions for school staff.</p>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 700; border-radius: 6px;">
            <i class="fa-solid fa-plus-circle"></i> Add New Designation
        </button>
    </div>

    <?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:20px;"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="login-error" style="margin-bottom:20px;"><?= $err ?></div><?php endif; ?>

    <!-- Main Card containing the Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0; overflow: hidden; margin-bottom: 30px;">
        <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; background: rgba(255,255,255,0.02); display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-user-tag" style="color: #00b894; font-size: 1.1rem;"></i>
            <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary);">Designations</h3>
        </div>

        <div class="table-wrapper" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 12px 20px; text-align: left; font-weight: 700; width: 30%;">Designation Title</th>
                        <th style="padding: 12px 20px; text-align: left; font-weight: 700; width: 40%;">Description</th>
                        <th style="padding: 12px 20px; text-align: center; font-weight: 700; width: 15%;">No. of Staff</th>
                        <th style="padding: 12px 20px; text-align: center; font-weight: 700; width: 15%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($designations)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">No designations found. Click the button above to add one.</td>
                        </tr>
                    <?php else: foreach ($designations as $d): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 15px 20px; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                <?= htmlspecialchars($d['title']) ?>
                            </td>
                            <td style="padding: 15px 20px; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= htmlspecialchars($d['description'] ?: '-') ?>
                            </td>
                            <td style="padding: 15px 20px; text-align: center; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                <span class="badge" style="background: rgba(9, 132, 227, 0.15); color: #0984e3; padding: 4px 10px; border-radius: 4px; font-weight: 700;">
                                    <?= $d['staff_count'] ?>
                                </span>
                            </td>
                            <td style="padding: 15px 20px; text-align: center; font-size: 0.85rem;">
                                <a onclick="openUpdateModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['title'])) ?>', '<?= htmlspecialchars(addslashes($d['description'])) ?>')" style="color: #0984e3; margin-right: 12px; cursor: pointer; font-weight:600;"><i class="fa-solid fa-edit"></i> Edit</a>
                                <a onclick="confirmDelete(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['title'])) ?>')" style="color: #ef4444; cursor: pointer; font-weight:600;"><i class="fa-solid fa-trash-can"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD MODAL -->
<div id="addModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 450px; width: 90%; overflow: hidden; box-shadow: var(--shadow-lg);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-plus-circle" style="color: #0984e3; margin-right: 8px;"></i> Add New Designation</h3>
            <button onclick="closeAddModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Designation Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Senior Lecturer" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Designation details..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Designation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- UPDATE MODAL -->
<div id="updateModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 450px; width: 90%; overflow: hidden; box-shadow: var(--shadow-lg);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-pen-to-square" style="color: #00b894; margin-right: 8px;"></i> Edit Designation</h3>
            <button onclick="closeUpdateModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Designation Title *</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #00b894;">Update Designation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE FORM -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}
function openUpdateModal(id, title, desc) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_description').value = desc;
    document.getElementById('updateModal').style.display = 'flex';
}
function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
}
function confirmDelete(id, title) {
    if (confirm("Are you sure you want to delete the designation '" + title + "'?")) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
