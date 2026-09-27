<?php
$page_title  = 'Exam Type / Category';
$active_page = 'exams';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','teacher');

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS exam_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$msg = ''; $err = '';

// DELETE
if (isset($_GET['del_id'])) {
    $conn->query("DELETE FROM exam_types WHERE id=".(int)$_GET['del_id']);
    header("Location: " . BASE_URL . "modules/exams/exam_types.php?msg=deleted"); exit;
}
if (($_GET['msg'] ?? '') === 'deleted') $msg = 'Exam type deleted.';

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    $title = $conn->real_escape_string(trim($_POST['title'] ?? ''));
    $desc  = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $active_session = $settings['session_year'] ?? '2025-2026';
    if ($title === '') {
        $err = 'Title is required.';
    } elseif ($id > 0) {
        $conn->query("UPDATE exam_types SET title='$title', description='$desc' WHERE id=$id");
        $msg = 'Exam type updated.';
    } else {
        $conn->query("INSERT INTO exam_types (title,description,session_year) VALUES ('$title','$desc','$active_session')");
        $msg = 'Exam type added.';
    }
}

// Edit record
$edit = null;
if (isset($_GET['edit'])) {
    $r = $conn->query("SELECT * FROM exam_types WHERE id=".(int)$_GET['edit']." LIMIT 1");
    if ($r && $r->num_rows) $edit = $r->fetch_assoc();
}

$show_form = isset($_GET['action']) && $_GET['action'] === 'add';
if ($edit || $err) $show_form = true;

// All exam types
$active_session = $settings['session_year'] ?? '2025-2026';
$types = $conn->query("SELECT * FROM exam_types WHERE session_year='$active_session' ORDER BY id DESC");
?>
<style>
.exam-types-wrap{background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.et-top{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border)}
.et-top h2{font-size:1.15rem;font-weight:700;color:var(--text-primary)}
.et-toolbar{padding:10px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.et-info{padding:10px 18px;font-size:.82rem;color:var(--text-secondary);border-bottom:1px solid var(--border);background:var(--bg-glass)}
.et-info span{color:var(--accent-light);font-style:italic}
.et-table{width:100%;border-collapse:collapse}
.et-table th{padding:10px 18px;text-align:left;font-size:.82rem;font-weight:700;color:var(--text-primary);border-bottom:2px solid var(--border);background:var(--bg-glass)}
.et-table td{padding:10px 18px;font-size:.83rem;color:var(--text-primary);border-bottom:1px solid var(--border)}
.et-table tr:last-child td{border-bottom:none}
.et-table tr:hover td{background:var(--bg-glass)}
.et-actions a{color:#0984e3;font-size:.8rem;font-weight:600;text-decoration:none}
.et-actions a:hover{text-decoration:underline}
.et-actions span{color:var(--text-muted);margin:0 3px}
.et-actions .del-link{color:#e74c3c}
/* Form card */
.form-card{background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);padding:22px;margin-bottom:18px}
.form-card h3{font-size:1rem;font-weight:700;margin-bottom:16px;color:var(--text-primary);display:flex;align-items:center;gap:8px}
.et-empty{text-align:center;padding:50px;color:var(--text-muted);font-size:.9rem}
</style>

<!-- Page Header -->
<div class="et-top" style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:16px;padding:16px 20px">
  <h2><i class="fa-solid fa-layer-group" style="color:#0984e3;margin-right:8px"></i>Exams Type / Category</h2>
  <a href="?action=add" class="btn btn-primary" style="font-size:.82rem;padding:7px 16px">
    <i class="fa-solid fa-plus"></i> Add New Exam Type / Category
  </a>
</div>

<!-- Messages -->
<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:12px"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-error" style="margin-bottom:12px"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- ADD / EDIT FORM -->
<?php if ($show_form): ?>
<div class="form-card">
  <h3><i class="fa-solid fa-<?= $edit ? 'pen' : 'plus-circle' ?>" style="color:#0984e3"></i> <?= $edit ? 'Update Exam Type' : 'Add New Exam Type / Category' ?></h3>
  <form method="POST">
    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
    <div class="form-row">
      <div class="form-group" style="flex:2">
        <label>Exam Type Title <span style="color:#ef4444">*</span></label>
        <input type="text" name="title" class="form-control"
               placeholder="e.g. Monthly Test, Mid Term Exam 2025, Final Term KPPS 2025"
               value="<?= htmlspecialchars($edit['title'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group" style="flex:2">
        <label>Description <span style="color:var(--text-muted)">(optional)</span></label>
        <input type="text" name="description" class="form-control"
               placeholder="Short note about this exam type"
               value="<?= htmlspecialchars($edit['description'] ?? '') ?>">
      </div>
    </div>
    <div class="btn-group">
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-<?= $edit ? 'floppy-disk' : 'plus' ?>"></i>
        <?= $edit ? 'Update Exam Type' : 'Add Exam Type' ?>
      </button>
      <a href="<?= BASE_URL ?>modules/exams/exam_types.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- EXAM TYPES TABLE -->
<div class="exam-types-wrap">
  <div class="et-toolbar">
    <i class="fa-solid fa-table" style="color:var(--text-muted)"></i>
  </div>
  <div class="et-info">
    Exam Category helps you to manage your exam record according to exam type like
    <span>(December Test, Monthly Test, Class Test, Term etc)</span>
  </div>
  <table class="et-table">
    <thead>
      <tr>
        <th style="width:80%">Title</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($types && $types->num_rows > 0):
      while ($t = $types->fetch_assoc()): ?>
    <tr>
      <td>
        <?= htmlspecialchars($t['title']) ?>
        <?php if ($t['description']): ?>
        <span style="font-size:.75rem;color:var(--text-muted);margin-left:8px">— <?= htmlspecialchars($t['description']) ?></span>
        <?php endif; ?>
      </td>
      <td class="et-actions">
        <a href="?edit=<?= $t['id'] ?>">Update</a>
        <span>|</span>
        <a href="?del_id=<?= $t['id'] ?>" class="del-link"
           onclick="return confirm('Delete \'<?= htmlspecialchars(addslashes($t['title'])) ?>\'?')">Delete</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="2" class="et-empty">
      <i class="fa-solid fa-folder-open" style="font-size:2rem;color:var(--text-muted);display:block;margin-bottom:10px"></i>
      No exam types added yet.<br>
      <a href="?action=add" style="color:#0984e3;font-weight:600">Add your first exam type</a>
    </td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




