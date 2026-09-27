<?php
$page_title = 'Attendance';
$active_page = 'attendance';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','teacher');

$msg = ''; $err = '';
$today = date('Y-m-d');

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $date = $conn->real_escape_string($_POST['date'] ?? $today);
    $statuses = $_POST['status'] ?? [];
    $uid = current_uid();
    $saved = 0;
    foreach ($statuses as $sid => $status) {
        $sid = (int)$sid;
        $status = $conn->real_escape_string($status);
        $conn->query("INSERT INTO attendance (student_id,class_id,date,status,marked_by) VALUES ($sid,$class_id,'$date','$status',$uid) ON DUPLICATE KEY UPDATE status='$status',marked_by=$uid");
        $saved++;
    }
    $msg = "Attendance saved for $saved students.";
}

$sel_class = (int)($_GET['class_id'] ?? 0);
$sel_date  = $_GET['date'] ?? $today;
$safe_date = $conn->real_escape_string($sel_date);

$classes_arr = get_all_classes($conn);


$students_att = [];
if ($sel_class > 0) {
    $sq = $conn->query("SELECT s.id, s.name, s.roll_no, s.admission_no, COALESCE(a.status,'Present') as att_status FROM students s LEFT JOIN attendance a ON a.student_id=s.id AND a.date='$safe_date' WHERE s.class_id=$sel_class AND s.status='Active' ORDER BY s.roll_no, s.name");
    if ($sq) while($r = $sq->fetch_assoc()) $students_att[] = $r;
}

// Attendance report summary for selected class
$report = [];
if ($sel_class > 0) {
    $rq = $conn->query("SELECT s.name, s.roll_no, COUNT(CASE WHEN a.status='Present' THEN 1 END) as present, COUNT(CASE WHEN a.status='Absent' THEN 1 END) as absent, COUNT(CASE WHEN a.status='Late' THEN 1 END) as late, COUNT(CASE WHEN a.status='Leave' THEN 1 END) as leave_c, COUNT(a.id) as total_days FROM students s LEFT JOIN attendance a ON a.student_id=s.id AND a.class_id=$sel_class WHERE s.class_id=$sel_class AND s.status='Active' GROUP BY s.id ORDER BY s.roll_no,s.name");
    if ($rq) while($r = $rq->fetch_assoc()) $report[] = $r;
}
?>

<div class="page-header">
  <div><h1><i class="fa-solid fa-circle-check text-success"></i> Attendance</h1><p>Mark daily attendance and view reports</p></div>
</div>

<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;"><?= $msg ?></div><?php endif; ?>

<!-- FILTER -->
<div class="filter-bar">
  <form method="GET" class="filter-bar" style="margin-bottom:0">
    <select name="class_id" class="form-control" required>
      <option value="">-- Select Class --</option>
      <?php foreach($classes_arr as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $sel_class==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'].' '.$c['section']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($sel_date) ?>">
    <button type="submit" class="btn btn-primary">Load Students</button>
  </form>
</div>

<?php if ($sel_class > 0 && !empty($students_att)): ?>
<div class="card-grid card-grid-2">
  <!-- MARK ATTENDANCE -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-clipboard"></i> Mark Attendance — <?= date('d M Y', strtotime($sel_date)) ?></h3>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-xs btn-success" onclick="setAll('Present')">All Present</button>
        <button type="button" class="btn btn-xs btn-danger" onclick="setAll('Absent')">All Absent</button>
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save_attendance">
      <input type="hidden" name="class_id" value="<?= $sel_class ?>">
      <input type="hidden" name="date" value="<?= htmlspecialchars($sel_date) ?>">
      <div class="table-wrapper">
        <table class="data-table">
          <thead><tr><th>Roll</th><th>Name</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($students_att as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['roll_no']??'-') ?></td>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td>
              <select name="status[<?= $s['id'] ?>]" class="form-control att-status" style="padding:4px 8px;font-size:13px" onchange="colorRow(this)">
                <?php foreach(['Present','Absent','Late','Leave'] as $st): ?>
                <option value="<?= $st ?>" <?= $s['att_status']===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="btn-group mt-2">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Attendance</button>
      </div>
    </form>
  </div>

  <!-- MONTHLY SUMMARY -->
  <div class="card">
    <div class="card-header"><h3><i class="fa-solid fa-chart-bar"></i> Monthly Summary</h3></div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Student</th><th style="color:#34d399">P</th><th style="color:#ef4444">A</th><th style="color:#f59e0b">L</th><th>%</th></tr></thead>
        <tbody>
        <?php foreach($report as $r):
          $total = $r['present'] + $r['absent'] + $r['late'] + $r['leave_c'];
          $pct = $total > 0 ? round(($r['present']/$total)*100) : 0;
          $color = $pct >= 75 ? '#34d399' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
        ?>
        <tr>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><span class="badge badge-success"><?= $r['present'] ?></span></td>
          <td><span class="badge badge-danger"><?= $r['absent'] ?></span></td>
          <td><span class="badge badge-warning"><?= $r['late'] ?></span></td>
          <td><span style="color:<?= $color ?>;font-weight:600"><?= $pct ?>%</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php elseif($sel_class > 0): ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-circle-check text-success"></i></div><h3>No active students in this class</h3></div>
<?php else: ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-circle-check text-success"></i></div><h3>Select a class to mark attendance</h3><p>Choose a class and date from the filter above.</p></div>
<?php endif; ?>

<script>
function setAll(status){document.querySelectorAll('.att-status').forEach(s=>{s.value=status;colorRow(s);});}
function colorRow(sel){
  const colors={Present:'rgba(16,185,129,.08)',Absent:'rgba(239,68,68,.08)',Late:'rgba(245,158,11,.08)',Leave:'rgba(148,163,184,.08)'};
  sel.closest('tr').style.background=colors[sel.value]||'';
}
document.querySelectorAll('.att-status').forEach(s=>colorRow(s));
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>



