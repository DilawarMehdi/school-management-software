<?php ob_start();
/**
 * SIAX SMSS - Fee Criteria Management
 * Follows the design style from the provided format.
 */
$page_title = 'Fee Criteria';
$active_page = 'fee_criteria';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

// --- DATABASE TABLE CHECK & CREATION ---
$conn->query("CREATE TABLE IF NOT EXISTS fee_criteria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$msg = ''; $err = '';

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = $conn->real_escape_string(trim($_POST['title'] ?? ''));
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        if (empty($title)) {
            $err = 'Fee criteria title is required.';
        } else {
            if ($id > 0) {
                $conn->query("UPDATE fee_criteria SET title = '$title' WHERE id = $id");
                $msg = 'Fee criteria updated successfully.';
            } else {
                $conn->query("INSERT INTO fee_criteria (title) VALUES ('$title')");
                $msg = 'New fee criteria added successfully.';
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM fee_criteria WHERE id = $id");
    $msg = 'Fee criteria deleted successfully.';
}

// --- FETCH DATA ---
$criteria_list = $conn->query("SELECT * FROM fee_criteria ORDER BY created_at DESC");
?>

<style>
/* Page Specific Styles matching the reference image */
.fee-criteria-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.fee-criteria-header h1 {
    font-size: 1.8rem;
    font-weight: 600;
    margin: 0;
}
.note-box {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    margin-bottom: 20px;
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.5;
}
.note-box strong {
    color: var(--text-primary);
}
.action-links {
    display: flex;
    gap: 8px;
    font-size: 0.82rem;
}
.action-links a {
    color: var(--accent-light);
    text-decoration: none;
    transition: opacity 0.2s;
}
.action-links a:hover {
    text-decoration: underline;
    opacity: 0.8;
}
.action-sep {
    color: var(--text-muted);
}
/* Ensure the table matches the provided style */
.data-table th {
    text-transform: none;
    font-weight: 700;
    color: var(--text-primary);
    font-size: 0.85rem;
}
.data-table td {
    padding: 14px 16px;
}
</style>

<div class="main-container">
    <?php if($msg): ?>
        <div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:15px"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if($err): ?>
        <div class="login-error" style="margin-bottom:15px"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="fee-criteria-header">
        <h1>Fee Criteria</h1>
        <button class="btn btn-primary" onclick="openCriteriaModal()"><i class="fa-solid fa-plus"></i> Add New Fee Criteria</button>
    </div>

    <div class="note-box">
        You can create different fee criterias for Monthly Tuition Fee, Transport Fee etc and use these criterias for generating monthly invoices.<br>
        <strong>Note:</strong> this is one time setup and need not to repeat it every month untill fee policy changed.
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-wrapper" style="border: none; border-radius: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Fee Criterial Title</th>
                        <th style="width: 15%;">Created On</th>
                        <th style="width: 25%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($criteria_list && $criteria_list->num_rows > 0): ?>
                        <?php while($row = $criteria_list->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= date('d-M-Y', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="update_fee_criteria.php?id=<?= $row['id'] ?>">Update</a>
                                        <span class="action-sep">|</span>
                                        <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this criteria?')">Delete</a>
                                        <span class="action-sep">|</span>
                                        <a href="update_fee_criteria.php?id=<?= $row['id'] ?>">Setup Fee Detail</a>
                                        <span class="action-sep">|</span>
                                        <a href="fee_criteria_detail.php?id=<?= $row['id'] ?>" target="_blank">Show Fee Criteria Detail</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted" style="padding: 40px;">No fee criteria defined yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Criteria Modal -->
<div id="criteriaModal" class="modal-overlay" style="display:none">
    <div class="modal-box" style="max-width: 450px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Fee Criteria</h3>
            <button class="modal-close" onclick="closeCriteriaModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="criteriaForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="criteria_id" value="0">
                <div class="form-group">
                    <label>Fee Criteria Title *</label>
                    <input type="text" name="title" id="criteria_title" class="form-control" placeholder="e.g. Fee Criteria-2026" required>
                </div>
                <div class="btn-group mt-2" style="justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeCriteriaModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Criteria</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCriteriaModal(id = 0, title = '') {
    document.getElementById('criteria_id').value = id;
    document.getElementById('criteria_title').value = title;
    document.getElementById('modalTitle').innerText = id > 0 ? 'Update Fee Criteria' : 'Add New Fee Criteria';
    document.getElementById('submitBtn').innerText = id > 0 ? 'Update Criteria' : 'Save Criteria';
    document.getElementById('criteriaModal').style.display = 'flex';
}

function closeCriteriaModal() {
    document.getElementById('criteriaModal').style.display = 'none';
}

// Close modal on backdrop click
document.getElementById('criteriaModal').addEventListener('click', function(e) {
    if (e.target === this) closeCriteriaModal();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




