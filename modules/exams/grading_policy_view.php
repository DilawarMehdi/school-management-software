<?php
$page_title  = 'View Grading Policy';
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

$details = [];
$det_res = $conn->query("SELECT * FROM grading_policy_details WHERE policy_id = $policy_id ORDER BY min_percent ASC");
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
</style>

<div class="gp-header">
    <div class="gp-title">Grading Policy</div>
</div>

<div class="gp-card">
    <div class="gp-card-header">
        <i class="fa-solid fa-table"></i> Grading Policy
    </div>
    <table class="gp-table">
        <thead>
            <tr>
                <th>Marks Range</th>
                <th>Grade</th>
                <th>Result</th>
                <th>Remarks</th>
                <th>Graph Color & Level</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($details)): ?>
            <tr>
                <td colspan="5" style="text-align:center; padding:30px;">No rules found for this policy.</td>
            </tr>
            <?php else: foreach($details as $d): ?>
            <tr>
                <td><?= floatval($d['min_percent']) ?>% --- to --- <?= floatval($d['max_percent']) ?>%</td>
                <td style="color:var(--text-primary);"><?= htmlspecialchars($d['grade_letter']) ?></td>
                <td><?= htmlspecialchars($d['result'] ?? 'Pass') ?></td>
                <td><?= htmlspecialchars($d['remarks'] ?? '') ?></td>
                <td><?= htmlspecialchars($d['graph_color']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
