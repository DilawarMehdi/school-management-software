<?php
ob_start();
$page_title  = 'Add Grading Policy';
$active_page = 'grading_policy';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'teacher');

$policy_id = (int)($_GET['id'] ?? 0);
$policy_title = '';
$max_fail_subjects = '';

if ($policy_id > 0) {
    $res = $conn->query("SELECT * FROM grading_policies WHERE id = $policy_id LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $policy_title = $row['title'];
        $max_fail_subjects = $row['max_fail_subjects'];
        $page_title = 'Update Grading Policy';
    } else {
        $policy_id = 0; // fallback if not found
    }
}

// Handle Add/Edit Policy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_policy'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $max_fails = $_POST['max_fail_subjects'] !== '' ? (int)$_POST['max_fail_subjects'] : 'NULL';
    
    if ($policy_id > 0) {
        $conn->query("UPDATE grading_policies SET title='$title', max_fail_subjects=$max_fails WHERE id=$policy_id");
        $_SESSION['msg'] = "Grading Policy updated successfully!";
    } else {
        $conn->query("INSERT INTO grading_policies (title, max_fail_subjects) VALUES ('$title', $max_fails)");
        $_SESSION['msg'] = "Grading Policy added successfully!";
    }
    header("Location: grading_policy.php");
    exit;
}
?>

<style>
/* SIAX Premium Design for Add Form */
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
    justify-content: space-between;
    align-items: center;
}
.gp-card-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.gp-card-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
}
.box-tool-btn {
    background: #fff;
    border: 1px solid #ddd;
    color: #666;
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}
.box-tool-btn:hover {
    background: #f5f5f5;
    color: #333;
}
.gp-card-body {
    padding: 30px 40px;
}
.form-row {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}
.form-label {
    width: 250px;
    text-align: right;
    padding-right: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
}
.form-input-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 0.9rem;
    transition: border-color 0.3s;
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
.optional-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: normal;
    display: block;
    margin-top: 2px;
}
</style>

<div class="gp-header">
    <div class="gp-title"><?= $policy_id > 0 ? 'Update Grading Policy Title' : 'Add New Grading Policy Title' ?></div>
</div>

<div class="gp-card" id="formCard">
    <div class="gp-card-header">
        <div class="gp-card-header-left">
            <i class="fa-solid fa-pen-to-square"></i> Input Text Fields
        </div>
        <div class="gp-card-header-right">
            <button type="button" class="box-tool-btn" onclick="toggleCardBody()"><i class="fa-solid fa-minus"></i></button>
            <button type="button" class="box-tool-btn" onclick="toggleFullscreen()"><i class="fa-solid fa-expand"></i></button>
        </div>
    </div>
    <div class="gp-card-body" id="formCardBody">
        <form method="POST">
            <div class="form-row">
                <div class="form-label">
                    Grading Policy Title
                </div>
                <div class="form-input-col">
                    <input type="text" name="title" class="form-control" placeholder="Title" value="<?= htmlspecialchars($policy_title) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-label">
                    Max Failed Subjects Before Overall Fail
                    <span class="optional-hint">(Optional)</span>
                </div>
                <div class="form-input-col">
                    <input type="number" min="1" name="max_fail_subjects" class="form-control" placeholder="e.g. 2" value="<?= htmlspecialchars($max_fail_subjects ?? '') ?>">
                    <button type="submit" name="save_policy" class="btn-primary"><?= $policy_id > 0 ? 'Update' : 'Add' ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCardBody() {
    const body = document.getElementById('formCardBody');
    if (body.style.display === 'none') {
        body.style.display = 'block';
    } else {
        body.style.display = 'none';
    }
}

function toggleFullscreen() {
    const card = document.getElementById('formCard');
    if (!document.fullscreenElement) {
        if (card.requestFullscreen) {
            card.requestFullscreen();
        } else if (card.webkitRequestFullscreen) { /* Safari */
            card.webkitRequestFullscreen();
        } else if (card.msRequestFullscreen) { /* IE11 */
            card.msRequestFullscreen();
        }
        card.style.background = "var(--bg-secondary)"; // ensure background is solid in fullscreen
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) { /* Safari */
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) { /* IE11 */
            document.msExitFullscreen();
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
