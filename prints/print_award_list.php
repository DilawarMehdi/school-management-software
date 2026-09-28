<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$class_id = (int)($_GET['class_id'] ?? 0);
$exam_id  = (int)($_GET['exam_id']  ?? 0);
$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'SIAX SMSS';

$class_info = null;
if ($class_id) {
    $r = $conn->query("SELECT * FROM classes WHERE id=$class_id LIMIT 1");
    if ($r) $class_info = $r->fetch_assoc();
}

$exams_q = $conn->query("SELECT * FROM exams WHERE is_published=1 ORDER BY created_at DESC");
$exams_arr = [];
if ($exams_q) while($e=$exams_q->fetch_assoc()) $exams_arr[]=$e;
$exam_info = null;
foreach($exams_arr as $e) { if($e['id']==$exam_id){$exam_info=$e;break;} }

// Get default grading policy
$def_max_fail = null;
$pol_q = $conn->query("SELECT max_fail_subjects FROM grading_policies ORDER BY is_default DESC, id ASC LIMIT 1");
if ($pol_q && $pol_q->num_rows > 0) {
    $def_max_fail = $pol_q->fetch_assoc()['max_fail_subjects'];
}

// Get students with total marks ranked
$award_data = [];
if ($class_id && $exam_id) {
    $sq = $conn->query(
        "SELECT s.id, s.name, s.admission_no, s.roll_no, s.father_name,
         COALESCE(SUM(m.marks_obtained),0) as obtained,
         COALESCE(SUM(m.max_marks),0) as total_max,
         SUM(CASE WHEN m.is_absent=1 OR m.not_participating=1 OR m.marks_obtained < m.pass_marks THEN 1 ELSE 0 END) as fail_count
         FROM students s
         LEFT JOIN marks m ON m.student_id=s.id AND m.exam_id=$exam_id
         WHERE s.class_id=$class_id AND s.status='Active'
         GROUP BY s.id"
    );
    if ($sq) while($row=$sq->fetch_assoc()) {
        $row['overall_fail'] = false;
        if ($def_max_fail !== null && $row['fail_count'] !== null && $row['fail_count'] >= (int)$def_max_fail) {
            $row['overall_fail'] = true;
        }
        $award_data[] = $row;
    }
} elseif ($class_id) {
    $sq = $conn->query(
        "SELECT s.id, s.name, s.admission_no, s.roll_no, s.father_name,
         0 as obtained, 0 as total_max, 0 as fail_count, 0 as overall_fail
         FROM students s WHERE s.class_id=$class_id AND s.status='Active'
         ORDER BY s.roll_no+0, s.name"
    );
    if ($sq) while($row=$sq->fetch_assoc()) $award_data[]=$row;
}

// Sort in PHP
if ($class_id && $exam_id) {
    usort($award_data, function($a, $b) {
        if ($a['overall_fail'] !== $b['overall_fail']) {
            return $a['overall_fail'] ? 1 : -1;
        }
        if ($b['obtained'] == $a['obtained']) {
            return (int)$a['roll_no'] <=> (int)$b['roll_no'];
        }
        return $b['obtained'] <=> $a['obtained'];
    });
}

// Assign positions (handle ties)
$positions = [];
$prev = -1; $pos = 1; $disp = 1;
foreach($award_data as $i => $s) {
    if ($exam_id) {
        if ($s['overall_fail']) {
            $positions[$i] = 'FAIL';
        } else {
            if ($s['obtained'] != $prev) { $disp = $pos; }
            $positions[$i] = $disp;
            $prev = $s['obtained'];
            $pos++;
        }
    } else {
        $positions[$i] = $i + 1;
    }
}

function ordinal($n) {
    if ($n === 'FAIL') return 'Fail';
    if ($n == 1) return '1st';
    if ($n == 2) return '2nd';
    if ($n == 3) return '3rd';
    return $n . 'th';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Award List &mdash; <?= htmlspecialchars($school_name) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#fff;color:#000;font-size:12px;padding:24px}

/* Controls */
.controls{background:#f5f5f5;border:1px solid #ccc;padding:12px 16px;border-radius:6px;margin-bottom:18px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.controls label{font-weight:700;font-size:12px}
.controls select{padding:6px 10px;border:1px solid #ccc;border-radius:4px;font-size:12px}
.btn-print{background:#1a5276;color:#fff;border:none;padding:7px 18px;border-radius:4px;font-weight:700;font-size:12px;cursor:pointer}
.btn-close{background:#777;color:#fff;border:none;padding:7px 14px;border-radius:4px;font-weight:700;font-size:12px;cursor:pointer}

/* Header */
.page-header{display:flex;align-items:center;gap:20px;border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:22px}
.logo-circle{width:88px;height:88px;border-radius:50%;border:3px solid #1a5276;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;font-size:30px;font-weight:800}
.logo-circle img{width:100%;height:100%;object-fit:cover}
.hdr-text{flex:1;text-align:center}
.hdr-text .school-big{font-size:22px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;line-height:1.2}
.hdr-text .school-med{font-size:13px;font-weight:700;text-transform:uppercase;margin-top:3px}
.hdr-text .school-small{font-size:11px;color:#333;margin-top:3px}
.hdr-text .class-line{font-size:12px;font-weight:700;margin-top:6px}

/* Subject/Total/Session bar */
.info-bar{font-size:13px;font-weight:700;margin:14px 0 10px;letter-spacing:.2px;color:#000}
.info-bar span{margin-right:4px}

/* Table */
.award-tbl{width:100%;border-collapse:collapse}
.award-tbl th{border:1px solid #555;padding:8px 8px;font-size:12px;font-weight:700;text-align:center;background:#fff;vertical-align:middle}
.award-tbl td{border:1px solid #888;padding:7px 8px;font-size:11.5px;vertical-align:middle}
.award-tbl tbody tr:nth-child(even) td{background:#fafafa}
.col-roll{width:52px;text-align:center}
.col-reg{width:64px;text-align:center}
.col-name{width:22%}
.col-father{width:20%}
.col-marks{width:72px;text-align:center}
.col-grade{width:56px;text-align:center}
.col-pos{width:68px;text-align:center}
.col-remarks{text-align:left}

/* Position badges */
.pos-1st{font-weight:700;color:#8b6914}
.pos-2nd{font-weight:700;color:#5a5a5a}
.pos-3rd{font-weight:700;color:#7d4b1e}

/* Footer */
.sign-row{margin-top:40px;display:flex;justify-content:space-between}
.sign-col{text-align:center;min-width:160px}
.sign-line{border-top:1px solid #000;padding-top:4px;margin-top:36px;font-size:11px}

@media print{
  .controls{display:none!important}
  body{padding:10px}
}
</style>
</head>
<body>

<!-- Controls -->
<div class="controls">
  <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <label>Class:</label>
    <select name="class_id">
      <option value="">-- Select --</option>
      <?php
      $classes_all = get_all_classes($conn);
      foreach($classes_all as $c):
      ?><option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'].' '.$c['section']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Exam:</label>
    <select name="exam_id">
      <option value="">-- No Exam (List Only) --</option>
      <?php foreach($exams_arr as $e): ?><option value="<?= $e['id'] ?>" <?= $exam_id==$e['id']?'selected':'' ?>><?= htmlspecialchars($e['name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn-print" style="background:#2e7d32">Load</button>
    <?php if($class_id): ?>
    <button type="button" class="btn-print" onclick="window.print()">Print Award List</button>
    <?php endif; ?>
    <button type="button" class="btn-close" onclick="window.close()">Close</button>
  </form>
</div>

<!-- School Header -->
<div class="page-header">
  <div class="logo-circle">
    <?php $logo=$settings['school_logo']??'';
    if($logo && file_exists(UPLOAD_PATH.$logo)): ?>
    <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($logo) ?>" alt="Logo">
    <?php else: ?><?= strtoupper(substr($school_name,0,1)) ?><?php endif; ?>
  </div>
  <div class="hdr-text">
    <div class="school-big"><?= htmlspecialchars($school_name) ?></div>
    <div class="school-med">Exam Award List</div>
    <div class="school-small">
      <?php if(!empty($settings['school_phone'])): ?>Phone : <?= htmlspecialchars($settings['school_phone']) ?><?php endif; ?>
      <?php if(!empty($settings['school_email'])): ?> &nbsp; <?= htmlspecialchars($settings['school_email']) ?><?php endif; ?>
    </div>
    <?php if(!empty($settings['school_address'])): ?>
    <div class="school-small"><?= htmlspecialchars($settings['school_address']) ?></div>
    <?php endif; ?>
    <?php if($class_info): ?>
    <div class="class-line">Class <?= htmlspecialchars($class_info['name']) ?> &mdash; Section <?= htmlspecialchars($class_info['section']) ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- Info Bar (Subject / Total Marks / Session) -->
<div class="info-bar">
  <span>Subject</span>
  <span style="color:#999">----------------------------------------------</span>
  <span>Total Marks</span>
  <?php 
    $disp_max = !empty($exam_info['total_marks']) ? (int)$exam_info['total_marks'] : (!empty($award_data[0]['total_max']) ? (int)$award_data[0]['total_max'] : 0);
  ?>
  <?php if($disp_max > 0): ?>
  <span style="font-weight:400"><?= $disp_max ?></span>
  <?php else: ?>
  <span style="color:#999">-----------</span>
  <?php endif; ?>
  <span>Session</span>
  <span style="color:#999">----------------</span>
  <span style="font-weight:400"><?= htmlspecialchars($settings['session_year'] ?? date('Y')) ?></span>
</div>
<?php if($exam_info): ?>
<div style="margin-bottom:10px;font-size:12px"><strong>Exam:</strong> <?= htmlspecialchars($exam_info['name']) ?> &nbsp; | &nbsp; <strong>Date:</strong> <?= date('d M Y') ?></div>
<?php endif; ?>

<!-- Award Table -->
<table class="award-tbl">
  <thead>
    <tr>
      <th class="col-roll">Roll-<br>No</th>
      <th class="col-reg">Reg-No</th>
      <th class="col-name">Name</th>
      <th class="col-father">Father Name</th>
      <th class="col-marks">Obt.Marks</th>
      <th class="col-grade">Grade</th>
      <th class="col-pos">Position</th>
      <th class="col-remarks">Remarks</th>
    </tr>
  </thead>
  <tbody>
  <?php if(!empty($award_data)):
    foreach($award_data as $i => $s):
      $pct = $s['total_max'] > 0 ? round(($s['obtained']/$s['total_max'])*100, 1) : 0;
      $g   = $exam_id ? get_grade($s['obtained'], $s['total_max']) : ['grade'=>'', 'remark'=>''];
      $p   = $positions[$i];
      $pos_class = $p===1?'pos-1st':($p===2?'pos-2nd':($p===3?'pos-3rd':''));
  ?>
  <tr>
    <td class="col-roll"><?= htmlspecialchars($s['roll_no'] ?? '') ?></td>
    <td class="col-reg"><?= htmlspecialchars($s['admission_no']) ?></td>
    <td class="col-name"><?= htmlspecialchars($s['name']) ?></td>
    <td class="col-father"><?= htmlspecialchars($s['father_name'] ?? '') ?></td>
    <td class="col-marks"><?= $exam_id ? $s['obtained'] : '' ?></td>
    <td class="col-grade"><?= $g['grade'] ?></td>
    <td class="col-pos <?= $pos_class ?>">
      <?php if($exam_id && $s['total_max'] > 0): echo ordinal($p); endif; ?>
    </td>
    <td class="col-remarks" style="font-size:11px"><?= htmlspecialchars($g['remark'] ?? '') ?></td>
  </tr>
  <?php endforeach;
  else: ?>
  <tr><td colspan="8" style="text-align:center;padding:40px;color:#999;font-size:13px">
    <?= $class_id ? 'No students found.' : 'Please select a class.' ?>
  </td></tr>
  <?php endif; ?>
  </tbody>
</table>

<!-- Signatures -->
<?php if(!empty($award_data)): ?>
<div class="sign-row">
  <div class="sign-col"><div class="sign-line">Class Teacher</div></div>
  <div class="sign-col"><div class="sign-line">In-Charge</div></div>
  <div class="sign-col"><div class="sign-line"><?= htmlspecialchars($settings['principal_name'] ?? 'Principal') ?></div></div>
</div>
<?php endif; ?>

<div style="text-align:center; margin-top:30px; font-size:10px; color:#777; border-top:1px dashed #eee; padding-top:10px;">
  Generated by SIAC Technologies-Smss
</div>

</body>
</html>




