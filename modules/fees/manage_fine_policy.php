<?php
ob_start();
/**
 * SIAX SMSS - Manage Fine Policy
 * Detailed editor matching SC2.
 */
$page_title = 'Manage Fine Policy';
$active_page = 'fine_policy';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$id = (int)($_GET['id'] ?? 0);
$policy = null;
if ($id) {
    $policy = $conn->query("SELECT * FROM fine_policies WHERE id = $id")->fetch_assoc();
}

$msg = ''; $err = '';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $conn->real_escape_string(trim($_POST['title'] ?? ''));
    $details = $_POST['details'] ?? [];

    if ($title) {
        if ($id) {
            $conn->query("UPDATE fine_policies SET title = '$title' WHERE id = $id");
        } else {
            $conn->query("INSERT INTO fine_policies (title) VALUES ('$title')");
            $id = $conn->insert_id;
        }

        // Save class details
        foreach ($details as $class_id => $data) {
            $class_id = (int)$class_id;
            $fine_amount = (float)($data['amount'] ?? 0);
            $fine_type = $conn->real_escape_string($data['type'] ?? 'Per Day');
            
            $conn->query("INSERT INTO fine_policy_details (policy_id, class_id, fine_amount, fine_type) 
                          VALUES ($id, $class_id, $fine_amount, '$fine_type') 
                          ON DUPLICATE KEY UPDATE fine_amount = $fine_amount, fine_type = '$fine_type'");
        }
        $msg = 'Fine policy saved successfully.';
        if (!$policy) {
            header("Location: manage_fine_policy.php?id=$id&msg=saved");
            exit;
        }
    } else {
        $err = 'Please enter a policy title.';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'saved') $msg = 'Fine policy saved successfully.';

// Fetch Classes (Naturally sorted)
$classes_list = get_all_classes($conn);
$classes = [];
foreach ($classes_list as $c) {
    // Get existing details
    $detail_q = $conn->query("SELECT * FROM fine_policy_details WHERE policy_id = $id AND class_id = " . $c['id']);
    $detail = ($detail_q && $detail_q->num_rows > 0) ? $detail_q->fetch_assoc() : ['fine_amount' => 0, 'fine_type' => 'Per Day'];
    $c['detail'] = $detail;
    $classes[] = $c;
}

?>

<style>
.manage-header { margin-bottom: 25px; }
.manage-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--text-primary); margin: 0; }
.policy-meta-grid {
    display: grid;
    grid-template-columns: 1.2fr 2fr 1fr 1fr;
    gap: 20px;
    align-items: center;
    background: var(--bg-card);
    padding: 15px 20px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    margin-bottom: 25px;
}
.policy-meta-grid label { font-weight: 700; color: var(--text-secondary); font-size: 0.9rem; }

.classes-title-bar {
    background: var(--bg-secondary);
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-bottom: none;
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    font-weight: 700;
    color: var(--text-primary);
    font-size: 0.95rem;
}
.classes-container { border: 1px solid var(--border); border-radius: 0 0 var(--radius-sm) var(--radius-sm); overflow: hidden; }
.class-policy-row {
    display: grid;
    grid-template-columns: 1fr 0.8fr 1fr 2fr 2fr;
    gap: 15px;
    align-items: center;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
    padding: 12px 20px;
}
.class-policy-row:last-child { border-bottom: none; }
.class-name { font-weight: 700; font-size: 0.9rem; }
.section-name { font-weight: 600; color: var(--text-secondary); background: var(--bg-secondary); padding: 5px 12px; border-radius: 4px; text-align: center; }
.fine-input { width: 120px; padding: 8px 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg-primary); color: var(--text-primary); }
.type-options { display: flex; align-items: center; gap: 15px; font-size: 0.85rem; }
.type-options label { display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-secondary); }
.type-options input[type="radio"] { accent-color: var(--accent); }
.label-green { color: #27ae60; font-weight: 700; }

.save-bar { margin-top: 30px; display: flex; gap: 10px; }
</style>

<div class="main-container">
    <?php if($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:15px"><?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="login-error" style="margin-bottom:15px"><?= $err ?></div><?php endif; ?>

    <div class="manage-header">
        <h1><?= $id ? 'Update Fine Policy' : 'Add New Fine Policy' ?></h1>
    </div>

    <form method="POST">
        <div class="policy-meta-grid">
            <label>Fine Policy Title</label>
            <input type="text" name="title" class="form-control" value="<?= $policy ? htmlspecialchars($policy['title']) : date('Y') ?>" required>
            
            <label style="text-align: right;">Created Date</label>
            <input type="text" class="form-control" value="<?= $policy ? date('d/m/Y', strtotime($policy['created_at'])) : date('d/m/Y') ?>" readonly style="background: var(--bg-secondary);">
        </div>

        <div class="classes-title-bar">Classes & Sections</div>
        <div class="classes-container">
            <?php foreach ($classes as $c): ?>
                <div class="class-policy-row">
                    <div class="class-name"><?= htmlspecialchars($c['name']) ?></div>
                    <div class="section-name"><?= htmlspecialchars($c['section']) ?></div>
                    <div>
                        <input type="number" name="details[<?= $c['id'] ?>][amount]" class="fine-input" placeholder="Fine Amount" value="<?= (float)$c['detail']['fine_amount'] ?>" step="0.01">
                    </div>
                    <div class="type-options" style="grid-column: span 2;">
                        <label>
                            <input type="radio" name="details[<?= $c['id'] ?>][type]" value="Per Day" <?= $c['detail']['fine_type'] == 'Per Day' ? 'checked' : '' ?>>
                            <span class="label-green">Fine Per Day After Due Date</span>
                        </label>
                        <label>
                            <input type="radio" name="details[<?= $c['id'] ?>][type]" value="Fixed" <?= $c['detail']['fine_type'] == 'Fixed' ? 'checked' : '' ?>>
                            Fixed Amount After Due Date
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="save-bar">
            <button type="submit" class="btn btn-primary">Save Fine Policy</button>
            <a href="<?= BASE_URL ?>modules/fees/fine_policy.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>




