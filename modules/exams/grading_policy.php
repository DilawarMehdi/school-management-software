<?php
ob_start();
$page_title  = 'Grading Policy';
$active_page = 'grading_policy';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'teacher');

// Handle Add/Edit Policy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_policy'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $policy_id = (int)($_POST['policy_id'] ?? 0);
    
    if ($policy_id > 0) {
        $conn->query("UPDATE grading_policies SET title='$title' WHERE id=$policy_id");
        $_SESSION['msg'] = "Grading Policy updated successfully!";
    } else {
        $conn->query("INSERT INTO grading_policies (title) VALUES ('$title')");
        $_SESSION['msg'] = "Grading Policy added successfully!";
    }
    header("Location: grading_policy.php");
    exit;
}

// Handle Delete Policy
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM grading_policies WHERE id=$del_id");
    $_SESSION['msg'] = "Grading Policy deleted successfully!";
    header("Location: grading_policy.php");
    exit;
}

// Fetch Policies
$policies = [];
$res = $conn->query("SELECT * FROM grading_policies ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $policies[] = $row;
    }
}
?>

<style>
/* SIAX Premium Design matching the screenshot */
.gp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.gp-title {
    font-size: 1.6rem;
    font-weight: 500;
    color: var(--text-primary);
}
.btn-outline-blue {
    background: transparent;
    color: #3498db;
    border: 1px solid #3498db;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-outline-blue:hover {
    background: #3498db;
    color: #fff;
}
.gp-card {
    background: var(--bg-secondary);
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid var(--border);
    overflow: hidden;
}
.gp-card-header {
    background: var(--bg-glass);
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.gp-table {
    width: 100%;
    border-collapse: collapse;
}
.gp-table th {
    background: transparent;
    padding: 12px 20px;
    text-align: left;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border);
}
.gp-table td {
    padding: 12px 20px;
    font-size: 0.85rem;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.gp-table tr:hover td {
    background: var(--bg-glass);
}
.action-links a {
    color: #3498db;
    text-decoration: none;
    transition: color 0.2s;
}
.action-links a:hover {
    color: #2980b9;
    text-decoration: underline;
}
.action-separator {
    color: #ccc;
    margin: 0 6px;
}

/* Modal Styling */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-overlay.active {
    display: flex;
}
.modal-box {
    background: var(--bg-secondary);
    border-radius: 8px;
    width: 100%;
    max-width: 450px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    overflow: hidden;
}
.modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 700;
    color: var(--text-primary);
}
.modal-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 1.2rem;
}
.modal-close:hover {
    color: #e74c3c;
}
.modal-body {
    padding: 20px;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
}
.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 0.9rem;
}
.form-control:focus {
    outline: none;
    border-color: #3498db;
}
.btn-primary {
    background: #3498db;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: background 0.3s;
}
.btn-primary:hover {
    background: #2980b9;
}
</style>

<?php if(isset($_SESSION['msg'])): ?>
<div style="background:rgba(46, 204, 113, 0.1); color:#27ae60; padding:12px 20px; border-radius:4px; margin-bottom:20px; border:1px solid rgba(46, 204, 113, 0.3); font-weight:600;">
    <i class="fa-solid fa-check-circle" style="margin-right:8px;"></i><?= htmlspecialchars($_SESSION['msg']) ?>
</div>
<?php unset($_SESSION['msg']); endif; ?>

<div class="gp-header">
    <div class="gp-title">Grading Policy</div>
</div>

<div style="text-align: right; margin-bottom: 16px;">
    <a href="add_grading_policy.php" class="btn-outline-blue">Add New Policy Title</a>
</div>

<div class="gp-card">
    <div class="gp-card-header">
        <i class="fa-solid fa-table"></i> Grading Policy
    </div>
    <table class="gp-table">
        <thead>
            <tr>
                <th style="width: 30%;">Title</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($policies)): ?>
            <tr>
                <td colspan="2" style="text-align:center; padding:30px;">No grading policies found.</td>
            </tr>
            <?php else: foreach($policies as $p): ?>
            <tr>
                <td style="color:var(--text-primary)"><?= htmlspecialchars($p['title']) ?></td>
                <td class="action-links">
                    <a href="add_grading_policy.php?id=<?= $p['id'] ?>">Update Policy Title</a>
                    <span class="action-separator">|</span>
                    <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Are you sure you want to delete this policy?')">Delete Policy</a>
                    <span class="action-separator">|</span>
                    <a href="grading_policy_detail.php?id=<?= $p['id'] ?>">Add/Update Policy Detail</a>
                    <span class="action-separator">|</span>
                    <a href="grading_policy_view.php?id=<?= $p['id'] ?>">View</a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
