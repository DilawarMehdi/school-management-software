<?php
// Dedicated Student List Print Page
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$class_id = (int)($_GET['class_id'] ?? 0);
$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'SIAX SMSS';

$class_info = null;
if ($class_id) {
    $r = $conn->query("SELECT * FROM classes WHERE id=$class_id LIMIT 1");
    if ($r) $class_info = $r->fetch_assoc();
}

$active_session = $settings['session_year'] ?? '2025-2026';
$where = $class_id ? "se.class_id=$class_id" : "1=1";
$students = $conn->query("
    SELECT s.*, c.name as class_name, c.section, se.roll_no as enrollment_roll_no 
    FROM student_enrollments se 
    JOIN students s ON se.student_id=s.id 
    LEFT JOIN classes c ON se.class_id=c.id 
    WHERE $where AND se.session_year='$active_session' AND se.status='Active' AND s.status='Active' 
    ORDER BY (se.roll_no+0), s.name
");
$total = $students ? $students->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student List — <?= htmlspecialchars($school_name) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#fff;color:#000;font-size:12px;padding:20px}
/* Header */
.print-header{display:flex;align-items:center;gap:20px;border-bottom:3px solid #000;padding-bottom:14px;margin-bottom:20px}
.school-logo{width:90px;height:90px;border-radius:50%;border:3px solid #1a5276;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;font-size:32px;font-weight:800}
.school-logo img{width:100%;height:100%;object-fit:cover}
.school-info{flex:1;text-align:center}
.school-info h1{font-size:24px;font-weight:900;text-transform:uppercase;line-height:1.15;letter-spacing:.5px}
.school-info h2{font-size:13px;font-weight:700;text-transform:uppercase;margin-top:4px}
.school-info p{font-size:11px;color:#333;margin-top:3px}
.school-info .class-line{font-size:12px;font-weight:600;margin-top:6px;color:#1a5276}
/* Table */
.student-table{width:100%;border-collapse:collapse;margin-top:16px}
.student-table th{background:#f2f2f2;border:1px solid #aaa;padding:8px 10px;text-align:left;font-size:12px;font-weight:700}
.student-table td{border:1px solid #ccc;padding:7px 10px;vertical-align:middle;font-size:11.5px}
.student-table tr:nth-child(even) td{background:#fafafa}
.student-table .photo{width:52px;height:60px;object-fit:cover;border:1px solid #ccc}
.student-table .photo-placeholder{width:52px;height:60px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#999;background:#f5f5f5}
/* Footer */
.print-footer{margin-top:30px;display:flex;justify-content:space-between;font-size:11px;border-top:1px solid #ccc;padding-top:12px}
.sign-block{text-align:center;min-width:160px}
.sign-block .sign-line{border-top:1px solid #000;margin-bottom:4px;margin-top:30px}
.summary-bar{background:#f2f2f2;border:1px solid #ccc;padding:8px 12px;display:flex;gap:24px;margin-top:12px;font-size:12px}
.summary-bar span strong{color:#000}
@media print{
  body{padding:8px}
  .no-print{display:none!important}
  button{display:none!important}
}
</style>
</head>
<body>

<!-- Top Buttons (hidden when printing) -->
<div class="no-print" style="margin-bottom:14px;display:flex;gap:10px">
  <button onclick="window.print()" style="background:#1a5276;color:#fff;border:none;padding:8px 20px;border-radius:6px;font-weight:700;cursor:pointer;font-size:13px"><i class="fa-solid fa-print"></i>️ Print Now</button>
  <button onclick="window.close()" style="background:#777;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px"><i class="fa-solid fa-xmark"></i> Close</button>
</div>

<!-- SCHOOL HEADER -->
<div class="print-header">
  <div class="school-logo">
    <?php
    $logo = $settings['school_logo'] ?? '';
    if ($logo && file_exists(UPLOAD_PATH . $logo)):
    ?><img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($logo) ?>" alt="Logo">
    <?php else: ?><?= strtoupper(substr($school_name,0,1)) ?><?php endif; ?>
  </div>
  <div class="school-info">
    <h1><?= htmlspecialchars($school_name) ?></h1>
    <h2>Student List</h2>
    <p>
      <?php if(!empty($settings['school_phone'])): ?>Phone : <?= htmlspecialchars($settings['school_phone']) ?> &nbsp;<?php endif; ?>
      <?php if(!empty($settings['school_email'])): ?><?= htmlspecialchars($settings['school_email']) ?><?php endif; ?>
    </p>
    <?php if(!empty($settings['school_address'])): ?><p><?= htmlspecialchars($settings['school_address']) ?></p><?php endif; ?>
    <?php if($class_info): ?>
    <div class="class-line">Class <?= htmlspecialchars($class_info['name']) ?> — Section <?= htmlspecialchars($class_info['section']) ?> &nbsp;|&nbsp; Session: <?= htmlspecialchars($settings['session_year'] ?? '') ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- Summary Bar -->
<div class="summary-bar">
  <span><strong>Class:</strong> <?= $class_info ? htmlspecialchars($class_info['name'].' '.$class_info['section']) : 'All Classes' ?></span>
  <span><strong>Total Students:</strong> <?= $total ?></span>
  <span><strong>Date:</strong> <?= date('d M Y') ?></span>
  <span><strong>Printed By:</strong> <?= htmlspecialchars(current_user()['full_name'] ?? '') ?></span>
</div>

<!-- STUDENT TABLE -->
<table class="student-table">
  <thead>
    <tr>
      <th>#</th>
      <th>Photo</th>
      <th>Reg#</th>
      <th>Roll No</th>
      <th>Name</th>
      <th>Gender</th>
      <th>Father Name</th>
      <th>Guardian Name</th>
      <th>Contact No</th>
    </tr>
  </thead>
  <tbody>
  <?php if($students && $students->num_rows > 0):
    $i = 1;
    while($s = $students->fetch_assoc()):
  ?>
  <tr>
    <td style="text-align:center;font-weight:700"><?= $i++ ?></td>
    <td style="text-align:center;padding:4px">
      <?php if($s['photo']): ?>
      <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($s['photo']) ?>" class="photo" alt="">
      <?php else: ?>
      <div class="photo-placeholder"><?= strtoupper(substr($s['name'],0,1)) ?></div>
      <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($s['admission_no']) ?></td>
    <td style="text-align:center;color:#1a5276;font-weight:700"><?= htmlspecialchars($s['enrollment_roll_no'] ?? ($s['roll_no'] ?? '')) ?></td>
    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
    <td style="text-align:center"><?= htmlspecialchars($s['gender'] ?? '') ?></td>
    <td><?= htmlspecialchars($s['father_name'] ?? '') ?></td>
    <td><?= htmlspecialchars($s['mother_name'] ?: ($s['father_name'] ?? '')) ?></td>
    <td><?= htmlspecialchars($s['father_phone'] ?: ($s['phone'] ?? '')) ?> |</td>
  </tr>
  <?php endwhile; else: ?>
  <tr><td colspan="9" style="text-align:center;padding:30px;color:#999">No students found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<!-- Footer Signatures -->
<div class="print-footer">
  <div class="sign-block"><div class="sign-line"></div>Class Teacher</div>
  <div class="sign-block"><div class="sign-line"></div>In-Charge</div>
  <div class="sign-block"><div class="sign-line"></div><?= htmlspecialchars($settings['principal_name'] ?? 'Principal') ?></div>
</div>

<script>
// Auto-print on load (optional – user can still click the button)
// window.print();
</script>
</body>
</html>




