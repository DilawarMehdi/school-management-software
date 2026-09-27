<?php
$page_title = 'Admission Form Generator';
$active_page = 'admission_form';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','accountant','teacher');

$id = (int)($_GET['id'] ?? 0);
$class_id = (int)($_GET['class_id'] ?? 0);
$search_term = trim($_GET['search'] ?? '');
$student = null;

if ($id > 0) {
    $sq = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=$id");
    if ($sq && $sq->num_rows) $student = $sq->fetch_assoc();
}

// Fetch all classes for dropdown (Naturally sorted)
$classes_arr = get_all_classes($conn);


// Fetch all students for direct dropdown selector
$students_q = $conn->query("SELECT id, name, admission_no FROM students WHERE status='Active' ORDER BY name");
$students_arr = [];
if ($students_q) while($s = $students_q->fetch_assoc()) $students_arr[] = $s;

// Fetch filtered students list if class_id or search_term is set
$filtered_students = [];
if ($class_id > 0 || !empty($search_term)) {
    $where = ["s.status='Active'"];
    if ($class_id > 0) {
        $where[] = "s.class_id = $class_id";
    }
    if (!empty($search_term)) {
        $st_esc = $conn->real_escape_string($search_term);
        $where[] = "(s.name LIKE '%$st_esc%' OR s.admission_no LIKE '%$st_esc%' OR s.father_name LIKE '%$st_esc%' OR s.roll_no LIKE '%$st_esc%')";
    }
    $w_sql = implode(' AND ', $where);
    $fq = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE $w_sql ORDER BY s.roll_no+0, s.name ASC");
    if ($fq) while($row = $fq->fetch_assoc()) $filtered_students[] = $row;
}
?>
<style>
.adm-form-doc{background:#fff;color:#111;max-width:780px;margin:0 auto;padding:32px 40px;border:2px solid #333;font-family:'Times New Roman',serif;font-size:13px}
.adm-header{text-align:center;border-bottom:3px double #333;padding-bottom:12px;margin-bottom:16px}
.adm-header h1{font-size:20px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:1px}
.adm-header h2{font-size:14px;margin:4px 0 0;font-weight:normal}
.adm-header p{font-size:11px;color:#555;margin:3px 0}
.adm-title{text-align:center;font-size:16px;font-weight:700;text-decoration:underline;text-transform:uppercase;letter-spacing:2px;margin:14px 0}
.adm-row{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;margin-bottom:8px}
.adm-field{border-bottom:1px solid #333;padding:4px 0;font-size:13px;min-height:26px}
.adm-field label{font-size:11px;color:#555;display:block;margin-bottom:2px}
.adm-field .val{font-weight:600}
.adm-photo{float:right;width:100px;height:120px;border:1px solid #333;display:flex;align-items:center;justify-content:center;font-size:11px;color:#999;text-align:center;margin-left:12px}
.adm-photo img{width:100%;height:100%;object-fit:cover}
.adm-section{font-weight:700;font-size:12px;background:#eee;padding:4px 8px;margin:14px 0 8px;text-transform:uppercase;letter-spacing:.5px}
.adm-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px 16px}
.adm-footer{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:30px;text-align:center;font-size:12px}
.adm-sign{border-top:1px solid #333;padding-top:4px}

.search-filter-card { margin-bottom: 20px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
.search-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }

@media print{
  .no-print, .top-header, .sidebar, #showMenuBtn, .page-header, .search-filter-card, .students-list-card { display:none!important; }
  body, .main-wrapper, .page-content { padding:0!important; margin:0!important; background:#fff!important; }
  .adm-form-doc{ box-shadow:none; border:2px solid #333; max-width:100%; margin:0; padding:20px; page-break-inside:avoid; }
}
</style>

<div class="page-header no-print">
  <div>
    <h1><i class="fa-solid fa-clipboard"></i> Admission Form Generator</h1>
    <p>Search students by Name, Registration No., or Class to view &amp; print admission forms</p>
  </div>
  <?php if($student): ?>
  <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Admission Form</button>
  <?php endif; ?>
</div>

<!-- Search & Selection Bar -->
<div class="search-filter-card no-print">
  <form method="GET" action="admission_form.php">
    <div class="search-filter-grid">
      <!-- 1. Filter by Class -->
      <div>
        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
          <i class="fa-solid fa-school"></i> Filter by Class
        </label>
        <select name="class_id" class="form-control" onchange="this.form.submit()">
          <option value="">-- All Classes --</option>
          <?php foreach($classes_arr as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $class_id == $c['id'] ? 'selected' : '' ?>>Class <?= htmlspecialchars($c['name'].' '.$c['section']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- 2. Search by Student Name / Registration No. -->
      <div>
        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
          <i class="fa-solid fa-magnifying-glass"></i> Search Name / Reg No.
        </label>
        <input type="text" name="search" class="form-control" placeholder="Enter Student Name or Reg No..." value="<?= htmlspecialchars($search_term) ?>">
      </div>

      <!-- 3. Direct Select Student Dropdown -->
      <div>
        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
          <i class="fa-solid fa-user"></i> Direct Select Student
        </label>
        <select name="id" class="form-control" onchange="this.form.submit()">
          <option value="">-- Select Student --</option>
          <?php foreach($students_arr as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['admission_no'].' — '.$s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; gap: 8px;">
        <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-search"></i> Search</button>
        <a href="admission_form.php" class="btn btn-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </div>
  </form>
</div>

<!-- Student List Table (Shown when class_id or search_term is set) -->
<?php if (!empty($filtered_students)): ?>
<div class="card students-list-card no-print" style="margin-bottom: 24px;">
  <div class="card-header" style="padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid var(--border);">
    <h3 style="font-size: 1rem; font-weight: 700;">
      <i class="fa-solid fa-users"></i> Matching Students (<?= count($filtered_students) ?>)
    </h3>
    <span class="text-muted" style="font-size: 0.78rem;">Click the green <i class="fa-solid fa-print text-success"></i> print icon on the left to print admission form</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 60px; text-align: center;">Print</th>
          <th>Reg No.</th>
          <th>Roll No.</th>
          <th>Student Name</th>
          <th>Father Name</th>
          <th>Class</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($filtered_students as $st): ?>
        <tr style="<?= $id == $st['id'] ? 'background: var(--accent-glow);' : '' ?>">
          <td style="text-align: center;">
            <a href="admission_form.php?id=<?= $st['id'] ?>&class_id=<?= $class_id ?>&search=<?= urlencode($search_term) ?>&auto_print=1" class="btn btn-xs btn-success" title="Print Admission Form for <?= htmlspecialchars($st['name']) ?>">
              <i class="fa-solid fa-print"></i>
            </a>
          </td>
          <td><strong><?= htmlspecialchars($st['admission_no']) ?></strong></td>
          <td><?= htmlspecialchars($st['roll_no'] ?? '-') ?></td>
          <td><strong><?= htmlspecialchars($st['name']) ?></strong></td>
          <td><?= htmlspecialchars($st['father_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars(($st['class_name'] ?? '').' '.($st['section'] ?? '')) ?></td>
          <td style="text-align: right;">
            <a href="admission_form.php?id=<?= $st['id'] ?>&class_id=<?= $class_id ?>&search=<?= urlencode($search_term) ?>" class="btn btn-xs btn-primary">
              <i class="fa-solid fa-eye"></i> View Form
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif (($class_id > 0 || !empty($search_term)) && empty($filtered_students)): ?>
<div class="card no-print" style="margin-bottom: 24px; text-align: center; padding: 24px; color: var(--text-muted);">
  <i class="fa-solid fa-user-slash" style="font-size: 2rem; margin-bottom: 8px;"></i>
  <p>No active students found matching your search or selected class.</p>
</div>
<?php endif; ?>

<!-- Admission Form Preview / Document -->
<?php if ($student): ?>
<div class="adm-form-doc">
  <div class="adm-header">
    <h1><?= htmlspecialchars($school_name) ?></h1>
    <h2><?= htmlspecialchars($settings['school_address'] ?? '') ?></h2>
    <p>Phone: <?= htmlspecialchars($settings['school_phone'] ?? '') ?> | Email: <?= htmlspecialchars($settings['school_email'] ?? '') ?></p>
  </div>
  <div class="adm-title">Student Admission Form</div>

  <div style="overflow:hidden">
    <div class="adm-photo">
      <?php if($student['photo']): ?>
      <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($student['photo']) ?>" alt="Photo">
      <?php else: ?>
      Photo<br>(Paste Here)
      <?php endif; ?>
    </div>
    <div class="adm-row" style="margin-right:120px">
      <div class="adm-field"><label>Admission No.</label><div class="val"><?= htmlspecialchars($student['admission_no']) ?></div></div>
      <div class="adm-field"><label>Admission Date</label><div class="val"><?= $student['admission_date'] ? date('d/m/Y',strtotime($student['admission_date'])) : '' ?></div></div>
      <div class="adm-field"><label>Class</label><div class="val"><?= htmlspecialchars(($student['class_name']??'').' '.($student['section']??'')) ?></div></div>
      <div class="adm-field"><label>Roll No.</label><div class="val"><?= htmlspecialchars($student['roll_no']??'') ?></div></div>
    </div>
  </div>

  <div class="adm-section">Personal Information</div>
  <div class="adm-row">
    <div class="adm-field"><label>Full Name</label><div class="val"><?= htmlspecialchars($student['name']) ?></div></div>
    <div class="adm-field"><label>Date of Birth</label><div class="val"><?= $student['dob'] ? date('d/m/Y',strtotime($student['dob'])) : '' ?></div></div>
    <div class="adm-field"><label>Gender</label><div class="val"><?= htmlspecialchars($student['gender']??'') ?></div></div>
    <div class="adm-field"><label>Religion</label><div class="val"><?= htmlspecialchars($student['religion']??'') ?></div></div>
    <div class="adm-field"><label>Nationality</label><div class="val"><?= htmlspecialchars($student['nationality']??'') ?></div></div>
    <div class="adm-field"><label>CNIC / B-Form No.</label><div class="val"><?= htmlspecialchars($student['cnic']??'') ?></div></div>
  </div>

  <div class="adm-section">Family Information</div>
  <div class="adm-row">
    <div class="adm-field"><label>Father's Name</label><div class="val"><?= htmlspecialchars($student['father_name']??'') ?></div></div>
    <div class="adm-field"><label>Mother's Name</label><div class="val"><?= htmlspecialchars($student['mother_name']??'') ?></div></div>
    <div class="adm-field"><label>Father's Phone</label><div class="val"><?= htmlspecialchars($student['father_phone']??'') ?></div></div>
    <div class="adm-field"><label>Student Phone</label><div class="val"><?= htmlspecialchars($student['phone']??'') ?></div></div>
    <div class="adm-field"><label>Email</label><div class="val"><?= htmlspecialchars($student['email']??'') ?></div></div>
  </div>

  <div class="adm-section">Address</div>
  <div class="adm-field"><label>Full Address</label><div class="val" style="min-height:30px"><?= htmlspecialchars($student['address']??'') ?></div></div>

  <div class="adm-section">Declaration</div>
  <p style="font-size:12px;line-height:1.8">I hereby declare that the information provided above is true and correct to the best of my knowledge. I agree to abide by all the rules and regulations of the school.</p>

  <div class="adm-footer">
    <div><div class="adm-sign" style="margin-top:40px">Parent/Guardian Signature</div></div>
    <div><div class="adm-sign" style="margin-top:40px">Class Teacher</div></div>
    <div><div class="adm-sign" style="margin-top:40px"><?= htmlspecialchars($settings['principal_name']??'Principal') ?></div></div>
  </div>

  <div style="text-align:right;font-size:10px;color:#888;margin-top:16px;border-top:1px solid #ddd;padding-top:8px">
    Generated by SIAC Technologies | <?= date('d M Y H:i') ?>
  </div>
</div>

<?php if(isset($_GET['auto_print']) && $_GET['auto_print'] == '1'): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        window.print();
    }, 400);
});
</script>
<?php endif; ?>

<?php elseif (empty($filtered_students)): ?>
<div class="empty-state no-print">
  <div class="icon"><i class="fa-solid fa-clipboard"></i></div>
  <h3>Select a student or choose a Class / Search term above</h3>
  <p>Search by student name, registration number, or filter by class to generate &amp; print admission forms.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
