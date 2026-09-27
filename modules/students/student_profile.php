<?php
$page_title = 'Student Profile';
$active_page = 'students';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','accountant','teacher');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: " . BASE_URL . "modules/students/students.php"); exit; }

$sq = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=$id");
if (!$sq || $sq->num_rows === 0) { header("Location: " . BASE_URL . "modules/students/students.php"); exit; }
$s = $sq->fetch_assoc();
$currency = $settings['currency'] ?? 'PKR';

// Fee history
$fees = $conn->query("SELECT * FROM fee_payments WHERE student_id=$id ORDER BY payment_date DESC");

// Attendance summary
$att = $conn->query("SELECT COUNT(CASE WHEN status='Present' THEN 1 END) as present, COUNT(CASE WHEN status='Absent' THEN 1 END) as absent, COUNT(CASE WHEN status='Late' THEN 1 END) as late, COUNT(*) as total FROM attendance WHERE student_id=$id");
$att = $att ? $att->fetch_assoc() : ['present'=>0,'absent'=>0,'late'=>0,'total'=>0];
$att_pct = $att['total'] > 0 ? round(($att['present']/$att['total'])*100) : 0;
$att_color = $att_pct >= 75 ? 'var(--success)' : ($att_pct >= 50 ? 'var(--warning)' : 'var(--danger)');

// Results
$results = $conn->query("SELECT e.name as exam_name, SUM(m.marks_obtained) as obtained, SUM(m.max_marks) as total_max FROM marks m JOIN exams e ON m.exam_id=e.id WHERE m.student_id=$id AND e.is_published=1 GROUP BY e.id ORDER BY e.created_at DESC");

// Certificates
$certs = $conn->query("SELECT * FROM certificates WHERE student_id=$id ORDER BY created_at DESC");

// Total fee paid
$fee_total = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as t FROM fee_payments WHERE student_id=$id")->fetch_assoc()['t'] ?? 0;
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-user"></i> Student Profile</h1>
    <p>Full details for <?= htmlspecialchars($s['name']) ?></p>
  </div>
  <div class="btn-group">
    <a href="<?= BASE_URL ?>modules/students/enrollment.php?id=<?= $id ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Enrollment</a>
    <a href="<?= BASE_URL ?>modules/students/students.php?edit=<?= $id ?>" class="btn btn-secondary"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
    <a href="<?= BASE_URL ?>modules/students/students.php" class="btn btn-secondary">← Back</a>
  </div>
</div>

<!-- Profile Card -->
<div class="card mb-3">
  <div style="display:flex;gap:28px;align-items:flex-start;flex-wrap:wrap">
    <!-- Photo -->
    <div style="flex-shrink:0">
      <?php if($s['photo']): ?>
      <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($s['photo']) ?>" style="width:110px;height:110px;border-radius:var(--radius);object-fit:cover;border:2px solid var(--border)" alt="Photo">
      <?php else: ?>
      <div style="width:110px;height:110px;border-radius:var(--radius);background:var(--accent-gradient);display:flex;align-items:center;justify-content:center;font-size:42px;font-weight:700;color:#fff;border:2px solid var(--border)"><?= strtoupper(substr($s['name'],0,1)) ?></div>
      <?php endif; ?>
      <div style="margin-top:8px;text-align:center">
        <span class="badge badge-<?= $s['status']==='Active'?'success':'warning' ?>"><?= $s['status'] ?></span>
      </div>
    </div>

    <!-- Info Grid -->
    <div style="flex:1;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px 24px">
      <?php $fields = [
        'Admission No' => $s['admission_no'],
        'Class' => ($s['class_name']??'-').' '.($s['section']??''),
        'Roll No' => $s['roll_no'] ?: '-',
        'Admission Date' => $s['admission_date'] ? date('d M Y',strtotime($s['admission_date'])) : '-',
        'Father\'s Name' => $s['father_name'] ?: '-',
        'Mother\'s Name' => $s['mother_name'] ?: '-',
        'Date of Birth' => $s['dob'] ? date('d M Y',strtotime($s['dob'])) : '-',
        'Gender' => $s['gender'],
        'Religion' => $s['religion'] ?: '-',
        'Nationality' => $s['nationality'] ?: '-',
        'CNIC / B-Form' => $s['cnic'] ?: '-',
        'Phone' => $s['phone'] ?: '-',
        'Father\'s Phone' => $s['father_phone'] ?: '-',
        'Email' => $s['email'] ?: '-',
        'Address' => $s['address'] ?: '-',
      ]; ?>
      <?php foreach($fields as $label => $value): ?>
      <div style="border-bottom:1px solid var(--border);padding-bottom:8px">
        <div style="font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px"><?= $label ?></div>
        <div style="font-size:.9rem;color:var(--text-primary)"><?= htmlspecialchars($value) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Stats Row -->
<div class="stat-cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-info"><h3><?= $currency ?> <?= number_format($fee_total) ?></h3><p>Total Fee Paid</p></div></div>
  <div class="stat-card"><div class="stat-icon" style="color:<?= $att_color ?>"><i class="fa-solid fa-circle-check text-success"></i></div><div class="stat-info"><h3 style="color:<?= $att_color ?>"><?= $att_pct ?>%</h3><p>Attendance</p></div></div>
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-circle-check text-success"></i></div><div class="stat-info"><h3><?= $att['present'] ?></h3><p>Days Present</p></div></div>
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-circle-xmark text-danger"></i></div><div class="stat-info"><h3><?= $att['absent'] ?></h3><p>Days Absent</p></div></div>
</div>

<div class="card-grid card-grid-2">
  <!-- Fee History -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-money-bill-wave"></i> Fee Payments</h3>
      <a href="<?= BASE_URL ?>modules/fees/fees.php?student_id=<?= $id ?>" class="btn btn-sm btn-secondary">Collect Fee</a>
    </div>
    <?php if($fees && $fees->num_rows>0): ?>
    <div class="table-wrapper">
      <table class="data-table" style="font-size:13px">
        <thead><tr><th>Receipt</th><th>Month</th><th>Amount</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php while($f=$fees->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($f['receipt_no']) ?></td>
          <td><?= htmlspecialchars($f['month']?:'-') ?></td>
          <td class="text-success"><strong><?= $currency ?> <?= number_format($f['paid_amount'],2) ?></strong></td>
          <td><?= date('d M Y',strtotime($f['payment_date'])) ?></td>
          <td><a href="<?= BASE_URL ?>modules/fees/fees.php?receipt=<?= $f['id'] ?>" class="btn btn-xs btn-secondary" title="Print"><i class="fa-solid fa-print"></i>️</a></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?><div class="empty-state" style="padding:20px"><div class="icon" style="font-size:24px"><i class="fa-solid fa-money-bill-wave"></i></div><p>No payments yet.</p></div><?php endif; ?>
  </div>

  <!-- Results & Certs -->
  <div>
    <div class="card mb-3">
      <div class="card-header">
        <h3><i class="fa-solid fa-trophy"></i> Results</h3>
        <a href="<?= BASE_URL ?>modules/exams/results.php?student_id=<?= $id ?>" class="btn btn-sm btn-secondary">View Cards</a>
      </div>
      <?php if($results && $results->num_rows>0): ?>
      <div class="table-wrapper">
        <table class="data-table" style="font-size:13px">
          <thead><tr><th>Exam</th><th>Obtained</th><th>Max</th><th>%</th><th>Grade</th></tr></thead>
          <tbody>
          <?php while($r=$results->fetch_assoc()):
            $pct=$r['total_max']>0?round(($r['obtained']/$r['total_max'])*100,1):0;
            $g=get_grade($r['obtained'],$r['total_max']);
          ?>
          <tr><td><?= htmlspecialchars($r['exam_name']) ?></td><td><?= $r['obtained'] ?></td><td><?= $r['total_max'] ?></td><td><?= $pct ?>%</td><td><strong><?= $g['grade'] ?></strong></td></tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?><div class="empty-state" style="padding:16px"><div class="icon" style="font-size:20px"><i class="fa-solid fa-trophy"></i></div><p>No published results.</p></div><?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header">
        <h3><i class="fa-solid fa-file-lines"></i> Certificates</h3>
        <a href="<?= BASE_URL ?>modules/reports/certificates.php" class="btn btn-sm btn-secondary">Issue</a>
      </div>
      <?php if($certs && $certs->num_rows>0): ?>
      <div class="table-wrapper">
        <table class="data-table" style="font-size:13px">
          <thead><tr><th>Cert#</th><th>Type</th><th>Date</th><th></th></tr></thead>
          <tbody>
          <?php while($c=$certs->fetch_assoc()): ?>
          <tr><td><?= htmlspecialchars($c['cert_no']) ?></td><td><span class="badge badge-info"><?= $c['type'] ?></span></td><td><?= date('d M Y',strtotime($c['issue_date'])) ?></td><td><a href="<?= BASE_URL ?>modules/reports/certificates.php?view=<?= $c['id'] ?>" class="btn btn-xs btn-secondary"><i class="fa-solid fa-print"></i>️</a></td></tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?><div class="empty-state" style="padding:16px"><div class="icon" style="font-size:20px"><i class="fa-solid fa-file-lines"></i></div><p>No certificates issued.</p></div><?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




