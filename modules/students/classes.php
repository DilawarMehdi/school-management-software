<?php
$page_title = 'Classes & Subjects';
$active_page = 'classes';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','teacher');

$msg = '';

// Handle class add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_class') {
        $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
        $section = $conn->real_escape_string(trim($_POST['section'] ?? 'A'));
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        if ($name) {
            $conn->query("INSERT INTO classes (name, section, teacher_id) VALUES ('$name','$section',$teacher_id)");
            $msg = 'Class added successfully.';
        }
    }
    if ($_POST['action'] === 'add_subject') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $sname = $conn->real_escape_string(trim($_POST['subject_name'] ?? ''));
        $code = $conn->real_escape_string(trim($_POST['code'] ?? ''));
        $max_marks = (int)($_POST['max_marks'] ?? 100);
        $pass_marks = (int)($_POST['pass_marks'] ?? 40);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        if ($sname && $class_id) {
            $conn->query("INSERT INTO subjects (class_id,name,code,max_marks,pass_marks,teacher_id) VALUES ($class_id,'$sname','$code',$max_marks,$pass_marks,$teacher_id)");
            $msg = 'Subject added successfully.';
        }
    }
}

// Handle delete
if (isset($_GET['del_class'])) { $conn->query("DELETE FROM classes WHERE id=".(int)$_GET['del_class']); $msg = 'Class deleted.'; }
if (isset($_GET['del_subject'])) { $conn->query("DELETE FROM subjects WHERE id=".(int)$_GET['del_subject']); $msg = 'Subject deleted.'; }

$active_session = $settings['session_year'] ?? '2025-2026';
$classes_q = $conn->query("
    SELECT c.*, u.full_name as teacher_name, 
           (SELECT COUNT(*) 
            FROM student_enrollments se 
            JOIN students s ON se.student_id = s.id 
            WHERE se.class_id = c.id AND se.session_year = '$active_session' AND se.status = 'Active' AND s.status = 'Active'
           ) as student_count 
    FROM classes c 
    LEFT JOIN users u ON c.teacher_id=u.id
");
$classes = [];
if ($classes_q) {
    while ($row = $classes_q->fetch_assoc()) {
        $classes[] = $row;
    }
    sort_classes_naturally($classes);
}
$teachers = $conn->query("SELECT id, full_name FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name");
$teachers_arr = [];
if ($teachers) { while($t = $teachers->fetch_assoc()) $teachers_arr[] = $t; }


// Selected class for subjects
$sel_class = (int)($_GET['class_id'] ?? 0);
$subjects = null;
if ($sel_class > 0) {
    $subjects = $conn->query("SELECT s.*, u.full_name as teacher_name FROM subjects s LEFT JOIN users u ON s.teacher_id=u.id WHERE s.class_id=$sel_class ORDER BY s.name");
}
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-school"></i> Classes & Subjects</h1>
    <p>Manage class sections and assign subjects</p>
  </div>
</div>

<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;"><?= $msg ?></div><?php endif; ?>

<div class="card-grid card-grid-2">
  <!-- Classes -->
  <div class="card">
    <div class="card-header">
      <h3>Classes</h3>
      <button class="btn btn-sm btn-primary" onclick="document.getElementById('classForm').style.display=document.getElementById('classForm').style.display==='none'?'block':'none'"><i class="fa-solid fa-plus"></i> Add</button>
    </div>

    <div id="classForm" style="display:none;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border)">
      <form method="POST">
        <input type="hidden" name="action" value="add_class">
        <div class="form-row">
          <div class="form-group">
            <label>Class Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Class 1" required>
          </div>
          <div class="form-group">
            <label>Section</label>
            <input type="text" name="section" class="form-control" value="A" required>
          </div>
          <div class="form-group">
            <label>Class Teacher</label>
            <select name="teacher_id" class="form-control">
              <option value="0">-- None --</option>
              <?php foreach($teachers_arr as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-sm btn-success">Save Class</button>
      </form>
    </div>

    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Class</th><th>Section</th><th>Teacher</th><th>Students</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(!empty($classes)): foreach($classes as $c): ?>
        <tr class="<?= $sel_class == $c['id'] ? 'style="background:var(--accent-glow)"' : '' ?>">
          <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
          <td><?= htmlspecialchars($c['section']) ?></td>
          <td><?= htmlspecialchars($c['teacher_name'] ?? '-') ?></td>
          <td><span class="badge badge-info"><?= $c['student_count'] ?></span></td>
          <td class="table-actions">
            <a href="?class_id=<?= $c['id'] ?>" class="btn btn-xs btn-secondary" title="View Subjects"><i class="fa-solid fa-book"></i></a>
            <button class="btn btn-xs btn-danger" onclick="confirmDelete('?del_class=<?= $c['id'] ?>','<?= htmlspecialchars($c['name'].' '.$c['section']) ?>')" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:30px">No classes found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Subjects -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-book"></i> Subjects <?php if($sel_class): $ci=$conn->query("SELECT name,section FROM classes WHERE id=$sel_class")->fetch_assoc(); echo '— '.htmlspecialchars($ci['name'].' '.$ci['section']); endif; ?></h3>
      <?php if ($sel_class): ?>
      <button class="btn btn-sm btn-primary" onclick="document.getElementById('subjectForm').style.display=document.getElementById('subjectForm').style.display==='none'?'block':'none'"><i class="fa-solid fa-plus"></i> Add</button>
      <?php endif; ?>
    </div>

    <?php if (!$sel_class): ?>
    <div class="empty-state">
      <div class="icon"><i class="fa-solid fa-book"></i></div>
      <h3>Select a class</h3>
      <p>Click the book icon on a class to view/add subjects.</p>
    </div>
    <?php else: ?>

    <div id="subjectForm" style="display:none;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border)">
      <form method="POST">
        <input type="hidden" name="action" value="add_subject">
        <input type="hidden" name="class_id" value="<?= $sel_class ?>">
        <div class="form-row">
          <div class="form-group">
            <label>Subject Name</label>
            <input type="text" name="subject_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Code</label>
            <input type="text" name="code" class="form-control" placeholder="e.g. ENG">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Max Marks</label>
            <input type="number" name="max_marks" class="form-control" value="100">
          </div>
          <div class="form-group">
            <label>Pass Marks</label>
            <input type="number" name="pass_marks" class="form-control" value="40">
          </div>
          <div class="form-group">
            <label>Teacher</label>
            <select name="teacher_id" class="form-control">
              <option value="0">-- None --</option>
              <?php foreach($teachers_arr as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-sm btn-success">Save Subject</button>
      </form>
    </div>

    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Subject</th><th>Code</th><th>Max</th><th>Pass</th><th>Teacher</th><th></th></tr></thead>
        <tbody>
        <?php if($subjects && $subjects->num_rows > 0): while($s = $subjects->fetch_assoc()): ?>
        <tr>
          <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
          <td><?= htmlspecialchars($s['code'] ?? '-') ?></td>
          <td><?= $s['max_marks'] ?></td>
          <td><?= $s['pass_marks'] ?></td>
          <td><?= htmlspecialchars($s['teacher_name'] ?? '-') ?></td>
          <td><button class="btn btn-xs btn-danger" onclick="confirmDelete('?class_id=<?= $sel_class ?>&del_subject=<?= $s['id'] ?>','<?= htmlspecialchars($s['name']) ?>')"><i class="fa-solid fa-trash-can"></i></button></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No subjects. Add one above.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




