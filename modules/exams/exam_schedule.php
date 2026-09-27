<?php
$page_title  = 'Exam Schedule';
$active_page = 'exam_schedule';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','teacher');

$msg = ''; 
$err = '';

// Delete Schedule
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM exam_schedules WHERE id = $del_id");
    header("Location: " . BASE_URL . "modules/exams/exam_schedule.php?msg=del");
    exit;
}
if (($_GET['msg']??'') === 'del') {
    $msg = 'Exam schedule deleted successfully.';
}

// Add / Update Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $type_id = (int)($_POST['exam_type_id'] ?? 0);
    $title = $conn->real_escape_string(trim($_POST['title'] ?? ''));

    if (!$type_id || $title === '') {
        $err = 'Exam type and title are required.';
    } elseif ($id > 0) {
        $conn->query("UPDATE exam_schedules SET exam_type_id=$type_id, title='$title' WHERE id=$id");
        $msg = 'Exam schedule updated successfully.';
    } else {
        $active_session = $settings['session_year'] ?? '2025-2026';
        $conn->query("INSERT INTO exam_schedules (exam_type_id, title, session_year) VALUES ($type_id, '$title', '$active_session')");
        $msg = 'Exam schedule added successfully.';
    }
}

// Edit Mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $r = $conn->query("SELECT * FROM exam_schedules WHERE id = $edit_id LIMIT 1");
    if ($r && $r->num_rows) {
        $edit = $r->fetch_assoc();
    }
}

$show_form = isset($_GET['action']) && $_GET['action'] === 'add';
if ($edit || $err) {
    $show_form = true;
}

// Fetch Data
$active_session = $settings['session_year'] ?? '2025-2026';
$types_q = $conn->query("SELECT * FROM exam_types WHERE session_year='$active_session' ORDER BY id DESC");
$types_arr = [];
if ($types_q) {
    while ($t = $types_q->fetch_assoc()) $types_arr[] = $t;
}

$active_session = $settings['session_year'] ?? '2025-2026';
$schedules_q = $conn->query("SELECT es.*, et.title as type_name FROM exam_schedules es LEFT JOIN exam_types et ON et.id = es.exam_type_id WHERE es.session_year = '$active_session' ORDER BY es.created_at DESC");
?>
<style>
.form-card { background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:16px; }
.sched-wrap { background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.sched-table { width:100%; border-collapse:collapse; }
.sched-table th { padding:14px 20px; text-align:left; font-size:.85rem; font-weight:700; color:var(--text-secondary); border-bottom:2px solid var(--border); background:var(--bg-glass); text-transform:uppercase; letter-spacing:.5px; }
.sched-table td { padding:14px 20px; font-size:.9rem; color:var(--text-primary); border-bottom:1px solid var(--border); vertical-align:middle; }
.sched-table tr:last-child td { border-bottom:none; }
.sched-table tr:hover td { background:var(--bg-glass); }
.act-link { color:#0984e3; font-size:.8rem; font-weight:600; text-decoration:none; }
.act-link.del { color:#e74c3c; }
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <div>
    <h1 style="font-size:1.15rem;font-weight:700"><i class="fa-solid fa-calendar-days" style="color:#6366f1;margin-right:8px"></i>Exam Schedule</h1>
    <p style="font-size:.78rem;color:var(--text-muted);margin-top:3px">Manage exam schedules / terms across your academic session</p>
  </div>
  <?php if(!$show_form): ?>
  <a href="?action=add" class="btn btn-primary" style="font-size:.8rem">
    <i class="fa-solid fa-plus"></i> Add New Schedule
  </a>
  <?php endif; ?>
</div>

<?php if($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:10px"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="login-error" style="margin-bottom:10px"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Info Banner -->
<div style="background:rgba(59,130,246,.07);border-bottom:1px solid rgba(59,130,246,.15);padding:14px 20px;font-size:.85rem;color:#3b82f6;font-weight:500;border-radius:var(--radius);margin-bottom:20px;line-height:1.5;">
  <i class="fa-solid fa-circle-info" style="margin-right:6px"></i>
  If you have created an exam Type "Monthly Test" then you can create multiple exam schedules against this category like <strong>January-2018</strong>, <strong>Feb-2018</strong>, <strong>March-2018</strong> etc and then you can add classes results against these schedules in result section.
</div>

<!-- ADD/EDIT FORM -->
<?php if($show_form): ?>
<div class="form-card">
  <h3 style="font-size:.95rem;font-weight:700;margin-bottom:14px">
    <i class="fa-solid fa-<?= $edit?'pen':'plus-circle' ?>" style="color:#6366f1"></i>
    <?= $edit ? 'Update Schedule' : 'Add New Schedule' ?>
  </h3>
  <form method="POST">
    <input type="hidden" name="id" value="<?= $edit['id']??0 ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Exam Type *</label>
        <select name="exam_type_id" class="form-control" required>
          <option value="">-- Select --</option>
          <?php foreach($types_arr as $t): ?>
          <option value="<?= $t['id'] ?>" <?= ($edit['exam_type_id']??0)==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="grid-column: span 2;">
        <label>Schedule Title *</label>
        <input type="text" name="title" class="form-control" placeholder="e.g. Mid Exam 2026 - Mid Exam July 2026" value="<?= htmlspecialchars($edit['title']??'') ?>" required autofocus>
      </div>
    </div>
    
    <div class="btn-group">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= $edit ? 'Update Schedule' : 'Save Schedule' ?></button>
      <a href="?" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- SCHEDULE TABLE -->
<div class="sched-wrap">
  <table class="sched-table">
    <thead>
      <tr>
        <th style="width:30%">Exam Type</th>
        <th style="width:50%">Title</th>
        <th style="width:20%">Action</th>
      </tr>
    </thead>
    <tbody>
    <?php if($schedules_q && $schedules_q->num_rows > 0): while($s = $schedules_q->fetch_assoc()): ?>
    <tr>
      <td style="font-weight:600;color:var(--text-secondary);font-size:.85rem"><?= htmlspecialchars($s['type_name'] ?? 'Unknown') ?></td>
      <td style="font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($s['title']) ?></td>
      <td>
        <a href="?edit=<?= $s['id'] ?>" class="act-link">Update</a>
        <span style="color:var(--text-muted);margin:0 5px">|</span>
        <a href="?delete=<?= $s['id'] ?>" class="act-link del" onclick="return confirm('Delete this schedule?')">Delete</a>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr>
      <td colspan="3" style="text-align:center;padding:50px;color:var(--text-muted)">
        <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;display:block;margin-bottom:8px"></i>
        No schedules found.
      </td>
    </tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
