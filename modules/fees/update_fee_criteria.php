<?php ob_start();
/**
 * SIAX SMSS - Update Fee Criteria
 * Full-page editor for setting standard fees per class.
 */
$page_title = 'Update Fee Criteria';
$active_page = 'fee_criteria';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: " . BASE_URL . "modules/fees/fee_criteria.php");
    exit;
}

// Fetch Criteria
$res = $conn->query("SELECT * FROM fee_criteria WHERE id = $id");
$criteria = $res->fetch_assoc();
if (!$criteria) {
    header("Location: " . BASE_URL . "modules/fees/fee_criteria.php");
    exit;
}

$msg = ''; $err = '';

// Ensure details table exists
$conn->query("CREATE TABLE IF NOT EXISTS fee_criteria_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  criteria_id INT NOT NULL,
  class_id INT NOT NULL,
  standard_fee DECIMAL(10,2) DEFAULT 0,
  UNIQUE KEY unique_criteria_class (criteria_id, class_id)
)");

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_criteria'])) {
        $title = $conn->real_escape_string(trim($_POST['title'] ?? ''));
        if ($title) {
            $conn->query("UPDATE fee_criteria SET title = '$title' WHERE id = $id");
            $criteria['title'] = $title;
            $msg = 'Criteria title updated.';
        }
    }

    if (isset($_POST['update_all_fees'])) {
        $fees = $_POST['fees'] ?? [];
        foreach ($fees as $class_id => $amount) {
            $class_id = (int)$class_id;
            $amount = (float)$amount;
            $conn->query("INSERT INTO fee_criteria_details (criteria_id, class_id, standard_fee) 
                          VALUES ($id, $class_id, $amount) 
                          ON DUPLICATE KEY UPDATE standard_fee = $amount");
        }
        $msg = 'All class fees updated successfully.';
    }
}

// Fetch Classes and Sections (Naturally sorted)
$classes_list = get_all_classes($conn);
$classes = [];
foreach ($classes_list as $c) {
    // Get existing fee for this class/criteria
    $fee_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $id AND class_id = " . $c['id']);
    $c['standard_fee'] = ($fee_q && $fee_q->num_rows > 0) ? $fee_q->fetch_assoc()['standard_fee'] : 0;
    $classes[] = $c;
}

?>

<style>
.update-header {
    margin-bottom: 25px;
}
.update-header h1 {
    font-size: 1.8rem;
    font-weight: 600;
    margin: 0;
    color: var(--text-primary);
}
.criteria-form-grid {
    display: grid;
    grid-template-columns: 1fr 2fr 1fr 1fr;
    gap: 20px;
    align-items: center;
    background: var(--bg-card);
    padding: 15px 20px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    margin-bottom: 25px;
}
.criteria-form-grid label {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-secondary);
}
.classes-section-title {
    font-weight: 700;
    font-size: 0.95rem;
    padding: 10px 15px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-bottom: none;
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    color: var(--text-primary);
}
.class-list-container {
    border: 1px solid var(--border);
    border-radius: 0 0 var(--radius-sm) var(--radius-sm);
    overflow: hidden;
}
.class-row {
    display: grid;
    grid-template-columns: 1.5fr 1fr 2fr 2fr;
    gap: 0;
    align-items: stretch;
    border-bottom: 1px solid var(--border);
    background: var(--bg-card);
}
.class-row:last-child {
    border-bottom: none;
}
.class-cell {
    padding: 15px;
    display: flex;
    align-items: center;
    border-right: 1px solid var(--border);
}
.class-cell:last-child {
    border-right: none;
}
.class-name-cell {
    font-weight: 700;
    font-size: 0.9rem;
}
.section-cell {
    justify-content: center;
    font-weight: 600;
}
.fee-input-cell {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.fee-input-cell label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-secondary);
}
.btn-update-class {
    background: #27ae60;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-update-class:hover {
    background: #219150;
}
.standard-fee-input {
    width: 100px;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--bg-primary);
    color: var(--text-primary);
}
</style>

<div class="main-container">
    <?php if($msg): ?>
        <div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:15px"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="update-header">
        <h1>Update Fee Criteria</h1>
    </div>

    <form method="POST" class="criteria-form-grid">
        <input type="hidden" name="update_criteria" value="1">
        <label>Criteria Title</label>
        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($criteria['title']) ?>" required>
        
        <label style="text-align: right;">Created Date</label>
        <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($criteria['created_at'])) ?>" readonly style="background: #e9ecef; color: #495057;">
    </form>

    <form method="POST">
        <input type="hidden" name="update_all_fees" value="1">
        <div class="classes-section-title">
            Classes & Sections
            <button type="submit" class="btn btn-primary" style="float: right; padding: 4px 15px; font-size: 0.8rem; margin-top: -4px;">Save All Fees</button>
        </div>
        <div class="class-list-container">
            <?php foreach ($classes as $c): ?>
                <div class="class-row">
                    <div class="class-cell class-name-cell">
                        <?= htmlspecialchars($c['name']) ?>
                    </div>
                    <div class="class-cell section-cell">
                        <?= htmlspecialchars($c['section']) ?>
                    </div>
                    <div class="class-cell fee-input-cell" style="grid-column: span 2;">
                        <div style="display: flex; align-items: flex-end; gap: 15px; width: 100%;">
                            <div style="display: flex; flex-direction: column; gap: 5px; flex: 1;">
                                <label>Standard Fee for <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['section']) ?>)</label>
                                <input type="number" name="fees[<?= $c['id'] ?>]" class="form-control" value="<?= $c['standard_fee'] ?>" step="0.01" style="width: 100%;">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 25px; display: flex; justify-content: space-between; align-items: center;">
            <a href="<?= BASE_URL ?>modules/fees/fee_criteria.php" class="btn btn-secondary">Back to Criteria List</a>
            <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">Save All Fee Details</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




