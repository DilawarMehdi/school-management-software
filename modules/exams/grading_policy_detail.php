<?php
ob_start();
$page_title  = 'Grading Policy Detail';
$active_page = 'grading_policy';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'teacher');

$policy_id = (int)($_GET['id'] ?? 0);
if ($policy_id <= 0) {
    header("Location: grading_policy.php");
    exit;
}

// Verify policy exists
$policy_res = $conn->query("SELECT * FROM grading_policies WHERE id = $policy_id LIMIT 1");
if (!$policy_res || $policy_res->num_rows == 0) {
    header("Location: grading_policy.php");
    exit;
}

// Handle Add Detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_detail'])) {
    $min_pct = (float)$_POST['min_percent'];
    $max_pct = (float)$_POST['max_percent'];
    $grade   = $conn->real_escape_string($_POST['grade_title']);
    $graph   = $conn->real_escape_string($_POST['graph_color']);
    $result  = $conn->real_escape_string($_POST['result']);
    $remarks = $conn->real_escape_string($_POST['remarks']);

    $q = "INSERT INTO grading_policy_details (policy_id, min_percent, max_percent, grade_letter, graph_color, result, remarks) 
          VALUES ($policy_id, $min_pct, $max_pct, '$grade', '$graph', '$result', '$remarks')";
    if ($conn->query($q)) {
        $_SESSION['msg'] = "Policy detail added successfully!";
    } else {
        $_SESSION['err'] = "Failed to add detail: " . $conn->error;
    }
    header("Location: grading_policy_detail.php?id=$policy_id");
    exit;
}

// Handle Delete Detail
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM grading_policy_details WHERE id=$del_id AND policy_id=$policy_id");
    $_SESSION['msg'] = "Policy detail deleted successfully!";
    header("Location: grading_policy_detail.php?id=$policy_id");
    exit;
}

// Fetch details
$details = [];
$det_res = $conn->query("SELECT * FROM grading_policy_details WHERE policy_id = $policy_id ORDER BY min_percent DESC");
if ($det_res) {
    while ($r = $det_res->fetch_assoc()) {
        $details[] = $r;
    }
}
?>

<style>
.gp-header {
    margin-bottom: 24px;
}
.gp-title {
    font-size: 1.6rem;
    font-weight: 500;
    color: var(--text-primary);
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
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.gp-card-body {
    padding: 24px 30px;
}
.form-row {
    display: flex;
    align-items: center;
    margin-bottom: 18px;
}
.form-label {
    width: 200px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    flex-shrink: 0;
}
.form-input-col {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 20px;
}
.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 0.9rem;
}
.form-control:focus {
    outline: none;
    border-color: #3498db;
}
.form-control[readonly] {
    background: #f5f5f5;
    color: #666;
}
.btn-primary {
    background: #3498db;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: background 0.3s;
}
.btn-primary:hover {
    background: #2980b9;
}
.gp-table {
    width: 100%;
    border-collapse: collapse;
}
.gp-table th {
    background: transparent;
    padding: 12px 15px;
    text-align: left;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border);
    border-top: 1px solid var(--border);
}
.gp-table td {
    padding: 12px 15px;
    font-size: 0.85rem;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.gp-table tr:hover td {
    background: var(--bg-glass);
}
.action-link {
    color: #e74c3c;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}
.action-link:hover {
    color: #c0392b;
    text-decoration: underline;
}
</style>

<div class="gp-header">
    <div class="gp-title">Add/Update Grading Policy Detail</div>
</div>

<?php if(isset($_SESSION['msg'])): ?>
<div style="background:rgba(46, 204, 113, 0.1); color:#27ae60; padding:12px 20px; border-radius:4px; margin-bottom:20px; border:1px solid rgba(46, 204, 113, 0.3); font-weight:600;">
    <i class="fa-solid fa-check-circle" style="margin-right:8px;"></i><?= htmlspecialchars($_SESSION['msg']) ?>
</div>
<?php unset($_SESSION['msg']); endif; ?>
<?php if(isset($_SESSION['err'])): ?>
<div style="background:rgba(231, 76, 60, 0.1); color:#e74c3c; padding:12px 20px; border-radius:4px; margin-bottom:20px; border:1px solid rgba(231, 76, 60, 0.3); font-weight:600;">
    <i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i><?= htmlspecialchars($_SESSION['err']) ?>
</div>
<?php unset($_SESSION['err']); endif; ?>

<div class="gp-card">
    <div class="gp-card-header">
        <i class="fa-solid fa-table"></i> Grading Policy Detail
    </div>
    
    <div class="gp-card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-label">Marks(%) Range From</div>
                <div class="form-input-col">
                    <input type="number" step="0.01" name="min_percent" class="form-control" placeholder="0" required>
                    <span style="font-size:0.85rem;font-weight:600;color:var(--text-primary);">To</span>
                    <input type="number" step="0.01" name="max_percent" class="form-control" placeholder="10" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-label">Grade Title</div>
                <div class="form-input-col">
                    <input type="text" name="grade_title" class="form-control" placeholder="Grade Title" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-label">Graph Color & Level</div>
                <div class="form-input-col">
                    <select name="graph_color" class="form-control" required>
                        <option value="green ( Level Excellent )">green ( Level Excellent )</option>
                        <option value="blue ( Level Good )">blue ( Level Good )</option>
                        <option value="orange ( Level Average )">orange ( Level Average )</option>
                        <option value="red ( Level Poor )">red ( Level Poor )</option>
                        <option value="grey ( Level Fail )">grey ( Level Fail )</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-label">Result</div>
                <div class="form-input-col">
                    <select name="result" class="form-control" required>
                        <option value="Pass">Pass</option>
                        <option value="Fail">Fail</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-label">Remarks</div>
                <div class="form-input-col">
                    <input type="text" name="remarks" class="form-control" placeholder="Remarks">
                </div>
            </div>
            <div class="form-row" style="margin-top: 24px; margin-bottom: 0;">
                <div class="form-label"></div>
                <div class="form-input-col">
                    <button type="submit" name="save_detail" class="btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Details Table -->
    <table class="gp-table">
        <thead>
            <tr>
                <th>Marks Range</th>
                <th>Grade</th>
                <th>Result</th>
                <th>Remarks</th>
                <th>Graph Color & Level</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($details)): ?>
            <tr>
                <td colspan="6" style="text-align:center; padding:20px;">No grading rules defined for this policy.</td>
            </tr>
            <?php else: foreach($details as $d): ?>
            <tr>
                <td><?= floatval($d['min_percent']) ?>% - <?= floatval($d['max_percent']) ?>%</td>
                <td style="font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($d['grade_letter']) ?></td>
                <td>
                    <?php if($d['result'] === 'Pass'): ?>
                        <span style="color:#27ae60;font-weight:600;">Pass</span>
                    <?php else: ?>
                        <span style="color:#e74c3c;font-weight:600;">Fail</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($d['remarks'] ?? '') ?></td>
                <td><?= htmlspecialchars($d['graph_color']) ?></td>
                <td>
                    <a href="?id=<?= $policy_id ?>&delete=<?= $d['id'] ?>" class="action-link" onclick="return confirm('Are you sure you want to delete this rule?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
