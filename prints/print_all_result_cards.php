<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$class_id = (int)($_GET['class_id'] ?? 0);
$exam_id  = (int)($_GET['exam_id']  ?? 0);
$type_id  = (int)($_GET['type_id']  ?? 0);

$settings     = all_settings($conn);
$school_name  = $settings['school_name']    ?? 'SIAC SMSS';
$school_addr  = $settings['school_address'] ?? '';
$school_phone = $settings['school_phone']   ?? '';
$school_email = $settings['school_email']   ?? '';
$school_web   = $settings['school_website'] ?? '';
$principal    = $settings['principal_name'] ?? '';
$session_year = $settings['session_year']   ?? date('Y');
$show_position = ($settings['show_position_in_result'] ?? '1') === '1';

/* ── Resolve exam ── */
$exam_name = '';
$exam_info = [];
if ($type_id > 0) {
    $tq = $conn->query("SELECT * FROM exam_types WHERE id=$type_id");
    if ($tq && $tq->num_rows > 0) {
        $exam_info = $tq->fetch_assoc();
        $exam_name = $exam_info['title'] ?? '';
    }
}
if (!$exam_name && $exam_id > 0) {
    $eq = $conn->query("SELECT * FROM exams WHERE id=$exam_id");
    if ($eq && $eq->num_rows > 0) {
        $exam_info = $eq->fetch_assoc();
        $exam_name = $exam_info['name'] ?? '';
    }
}

if (!$class_id || (!$exam_id && !$type_id))
    die("<div style='font-family:Arial,sans-serif;text-align:center;padding:50px'><h2>Invalid Request</h2><p>Class and Exam must be selected.</p></div>");

/* ── Grading policy ── */
$def_max_fail   = null;
$policy_details = [];

$pol_q = $conn->query(
    "SELECT gp.id, gp.max_fail_subjects,
            gpd.min_percent, gpd.max_percent,
            gpd.grade_letter, gpd.remarks
     FROM grading_policies gp
     LEFT JOIN grading_policy_details gpd ON gpd.policy_id = gp.id
     ORDER BY gp.is_default DESC, gp.id ASC, gpd.min_percent DESC"
);

if ($pol_q && $pol_q->num_rows > 0) {
    while ($pr = $pol_q->fetch_assoc()) {
        if ($def_max_fail === null) $def_max_fail = $pr['max_fail_subjects'];
        if (!empty($pr['grade_letter'])) {
            $policy_details[] = $pr;
        }
    }
}

function policy_remark(float $pct, array $details, string $fallback = ''): string {
    foreach ($details as $d) {
        if ($pct >= (float)$d['min_percent'] && $pct <= (float)$d['max_percent']) {
            return $d['remarks'] ?? $fallback;
        }
    }
    if (!empty($details)) {
        $last = end($details);
        return $last['remarks'] ?? $fallback;
    }
    return $fallback;
}

/* ── Class info ── */
$cq = $conn->query("SELECT * FROM classes WHERE id=$class_id LIMIT 1");
$class_row = $cq ? $cq->fetch_assoc() : [];
$class_display = trim(($class_row['name'] ?? '') . ' — Section ' . ($class_row['section'] ?? ''));

/* ── Students ── */
$students_q = $conn->query(
    "SELECT s.*, c.name as class_name, c.section, se.roll_no as enrollment_roll_no
     FROM student_enrollments se 
     JOIN students s ON se.student_id=s.id
     LEFT JOIN classes c ON se.class_id=c.id
     WHERE se.class_id=$class_id AND se.session_year='$session_year' AND se.status='Active' AND s.status='Active'
     ORDER BY (se.roll_no+0), s.name"
);
$all_students = [];
if ($students_q) while ($s = $students_q->fetch_assoc()) $all_students[] = $s;

/* ── Build result data ── */
$all_results = [];
foreach ($all_students as $st) {
    if ($type_id > 0) {
        $mq = $conn->query(
            "SELECT m.*, m.subject as sub_name
             FROM marks m
             WHERE m.exam_type_id=$type_id AND m.student_id={$st['id']}
             ORDER BY m.subject"
        );
    } else {
        $mq = $conn->query(
            "SELECT m.*, sub.name as sub_name, sub.code
             FROM marks m JOIN subjects sub ON m.subject_id=sub.id
             WHERE m.exam_id=$exam_id AND m.student_id={$st['id']}
             ORDER BY sub.name"
        );
    }
    $marks = []; $total_obtained = 0; $total_max = 0; $fail_count = 0;
    if ($mq && $mq->num_rows > 0) {
        while ($m = $mq->fetch_assoc()) {
            $g   = get_grade($m['marks_obtained'], $m['max_marks']);
            $pct = $m['max_marks'] > 0 ? round(($m['marks_obtained'] / $m['max_marks']) * 100, 2) : 0;
            $m['grade']   = $g['grade'];
            $m['remarks'] = policy_remark(
                $m['is_absent'] ? 0 : $pct,
                $policy_details,
                $g['remarks']
            );
            if ($m['is_absent']) $m['remarks'] = 'Absent';
            $m['pct']    = $pct;
            $m['passed'] = !$m['is_absent'] && $m['marks_obtained'] >= $m['pass_marks'];
            if (!$m['passed']) $fail_count++;
            $total_obtained += $m['marks_obtained'];
            $total_max      += $m['max_marks'];
            $marks[] = $m;
        }
    }
    if (empty($marks)) continue;

    $overall = get_grade($total_obtained, $total_max);
    $overall_pct = $total_max > 0 ? round(($total_obtained / $total_max) * 100, 2) : 0;
    $result_status = ($def_max_fail === null) ? 'PASS'
                   : ($fail_count >= (int)$def_max_fail ? 'FAIL' : 'PASS');

    $all_results[] = array_merge($st, [
        'exam'           => $exam_info,
        'marks'          => $marks,
        'total_obtained' => $total_obtained,
        'total_max'      => $total_max,
        'fail_count'     => $fail_count,
        'overall_grade'  => $overall['grade'],
        'overall_pct'    => $overall_pct,
        'overall_remarks'=> $overall['remarks'],
        'result_status'  => $result_status,
    ]);
}

if (empty($all_results))
    die("<div style='font-family:Arial,sans-serif;text-align:center;padding:50px'><h2>No Marks Found</h2><p>No marks have been entered for this class and exam yet.</p></div>");

/* ── Calculate Class Position ── */
$total_students = count($all_results);
if ($show_position && $total_students > 0) {
    $sorted_by_pct = $all_results;
    usort($sorted_by_pct, fn($a, $b) => $b['overall_pct'] <=> $a['overall_pct']);
    $position_map = [];
    $pos = 1;
    $prev_pct = null;
    foreach ($sorted_by_pct as $idx => $r) {
        if ($prev_pct !== null && abs($r['overall_pct'] - $prev_pct) < 0.001) {
            $position_map[$r['id']] = $position_map[$sorted_by_pct[$idx-1]['id']];
        } else {
            $position_map[$r['id']] = $pos;
        }
        $prev_pct = $r['overall_pct'];
        $pos++;
    }
    foreach ($all_results as &$rd) {
        $rd['class_position'] = $position_map[$rd['id']] ?? null;
    }
    unset($rd);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Result Cards &mdash; <?= htmlspecialchars($school_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<!-- Font Awesome local copy -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/fontawesome/css/all.min.css">
<style>
/* ── RESET ── */
*{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:'Inter',Arial,sans-serif;
    background:linear-gradient(135deg,#1e1b4b 0%,#312e81 40%,#4f46e5 100%);
    min-height:100vh;
    padding:0;
    color:#1e293b;
}

/* ── PRINT CONTROLS ── */
.print-controls{
    background:rgba(255,255,255,.97);
    padding:14px 24px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:16px;
    border-bottom:4px solid #6366f1;
    box-shadow:0 4px 24px rgba(0,0,0,.2);
    position:sticky;
    top:0;
    z-index:100;
}
.btn-print{
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    color:#fff;
    border:none;
    padding:11px 34px;
    border-radius:30px;
    font-weight:700;
    font-size:13px;
    cursor:pointer;
    letter-spacing:.5px;
    font-family:'Inter',Arial,sans-serif;
    box-shadow:0 4px 16px rgba(99,102,241,.45);
    transition:all .2s;
}
.btn-print:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(99,102,241,.5);}
.print-info{
    font-size:12px;
    color:#475569;
    font-weight:600;
    background:#f1f5f9;
    padding:8px 16px;
    border-radius:20px;
    border:1px solid #e2e8f0;
}

/* ── CARDS WRAPPER ── */
.cards-wrapper{padding:30px 0;}

/* ── RESULT CARD ── */
.result-card{
    background:#fff;
    width:210mm;
    min-height:296mm;
    margin:0 auto 36px;
    box-shadow:0 25px 60px rgba(0,0,0,.35);
    position:relative;
    display:flex;
    flex-direction:column;
    overflow:hidden;
    border-radius:6px;
}

/* ── TOP RAINBOW STRIPE ── */
.rc-rainbow{
    height:7px;
    background:linear-gradient(90deg,#f59e0b,#ef4444,#ec4899,#8b5cf6,#6366f1,#06b6d4,#10b981);
    flex-shrink:0;
}

/* ── HEADER ── */
.rc-header{
    background:linear-gradient(135deg,#1e1b4b 0%,#3730a3 60%,#4c1d95 100%);
    padding:16px 22px 14px;
    display:flex;
    align-items:center;
    gap:16px;
    position:relative;
    overflow:hidden;
    flex-shrink:0;
}
.rc-header::before{
    content:'';position:absolute;top:-40px;right:-40px;
    width:150px;height:150px;
    background:rgba(255,255,255,.05);border-radius:50%;
}
.rc-header::after{
    content:'';position:absolute;bottom:-50px;left:30%;
    width:180px;height:180px;
    background:rgba(255,255,255,.04);border-radius:50%;
}
.rc-logo-wrap{
    flex-shrink:0;width:72px;height:72px;
    border:2px solid rgba(255,255,255,.25);
    border-radius:12px;overflow:hidden;
    background:rgba(255,255,255,.1);
    display:flex;align-items:center;justify-content:center;
    position:relative;z-index:1;
}
.rc-logo-wrap img{width:100%;height:100%;object-fit:contain;}
.rc-logo-wrap .logo-placeholder{font-size:30px;line-height:1;}
.rc-header-center{flex:1;text-align:center;position:relative;z-index:1;}
.rc-school-name{
    font-size:17px;font-weight:900;text-transform:uppercase;
    letter-spacing:2px;color:#fff;line-height:1.2;
    text-shadow:0 2px 8px rgba(0,0,0,.4);
}
.rc-school-city{
    font-size:10px;font-weight:600;text-transform:uppercase;
    letter-spacing:1.5px;color:#c4b5fd;margin-top:3px;
}
.rc-school-meta{
    font-size:9px;color:#a5b4fc;margin-top:5px;
    display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:4px 10px;
}
.rc-class-exam{
    margin-top:6px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.2);
    border-radius:20px;
    padding:3px 14px;
    font-size:9.5px;color:#e0d9ff;font-weight:600;
    display:inline-block;
    backdrop-filter:blur(4px);
}
.rc-logo-spacer{flex-shrink:0;width:72px;}

/* ── TITLE BAR ── */
.rc-title-bar{
    background:linear-gradient(90deg,#f59e0b 0%,#ef4444 50%,#ec4899 100%);
    color:#fff;
    text-align:center;
    padding:7px 10px;
    font-size:11px;
    font-weight:800;
    letter-spacing:5px;
    text-transform:uppercase;
    text-shadow:0 1px 4px rgba(0,0,0,.2);
    flex-shrink:0;
}

/* ── BODY ── */
.rc-body{
    padding:12px 18px 14px;
    flex:1;
    display:flex;
    flex-direction:column;
    gap:10px;
}

/* ── SECTION LABEL ── */
.rc-section-label{
    font-size:9.5px;font-weight:800;
    text-transform:uppercase;letter-spacing:2px;
    color:#4f46e5;
    display:flex;align-items:center;gap:8px;
    margin-bottom:5px;
}
.rc-section-label i{font-size:11px;}
.rc-section-label::after{
    content:'';flex:1;height:2px;
    background:linear-gradient(90deg,#6366f1,transparent);
    border-radius:2px;
}

/* ── STUDENT INFO TABLE ── */
.rc-info-table{
    width:100%;border-collapse:collapse;
    font-size:11px;border-radius:8px;overflow:hidden;
    border:1px solid #e0e7ff;
}
.rc-info-table td{padding:6px 11px;border:1px solid #e0e7ff;}
.rc-info-table td.lbl{
    background:linear-gradient(135deg,#eef2ff,#e0e7ff);
    color:#4338ca;font-weight:700;
    font-size:9px;text-transform:uppercase;letter-spacing:.6px;
    white-space:nowrap;width:18%;
}
.rc-info-table td.val{font-weight:700;color:#0f172a;font-size:11.5px;width:30%;}

/* ── MARKS TABLE ── */
.rc-marks-table{
    width:100%;border-collapse:collapse;font-size:11px;
    border-radius:10px;overflow:hidden;
    box-shadow:0 2px 12px rgba(99,102,241,.12);
}
.rc-marks-table thead tr{
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
}
.rc-marks-table th{
    color:#fff;font-weight:700;padding:8px 9px;
    text-align:center;font-size:9.5px;
    letter-spacing:.5px;text-transform:uppercase;border:none;
}
.rc-marks-table th.left{text-align:left;}
.rc-marks-table td{
    border:1px solid #e0e7ff;padding:6px 8px;
    text-align:center;font-size:11px;color:#1e293b;
}
.rc-marks-table td.subject-name{
    text-align:left;font-weight:600;color:#1e1b4b;
}
.rc-marks-table tbody tr:nth-child(odd){background:#fafbff;}
.rc-marks-table tbody tr:nth-child(even){background:#f3f4fd;}
.rc-marks-table tfoot tr td{
    background:linear-gradient(135deg,#1e1b4b,#3730a3);
    color:#fff;font-weight:900;border:none;
    padding:8px 9px;text-align:center;font-size:11.5px;
}
.rc-marks-table tfoot td.left{text-align:left;}

/* badges */
.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:9px;font-weight:700;letter-spacing:.3px;}
.badge-pass{background:linear-gradient(135deg,#10b981,#059669);color:#fff;}
.badge-fail{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;}
.badge-absent{background:#94a3b8;color:#fff;}

/* ── RESULT BANNER ── */
.rc-result-banner{
    display:flex;
    border-radius:12px;overflow:hidden;
    box-shadow:0 4px 20px rgba(99,102,241,.18);
}
.rc-result-banner .left-part{
    flex:1;padding:12px 16px;
    background:linear-gradient(135deg,#eef2ff,#f5f3ff);
    border:1.5px solid #c7d2fe;
    border-right:none;border-radius:12px 0 0 12px;
    display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;
    font-size:10.5px;color:#1e1b4b;font-weight:600;
}
.rc-result-banner .left-part .stat-chip{
    display:flex;align-items:center;gap:5px;
    background:#fff;border:1px solid #e0e7ff;
    border-radius:20px;padding:3px 10px;
    font-size:10px;font-weight:700;color:#3730a3;
    box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.rc-result-banner .left-part .stat-chip.status-pass{
    background:linear-gradient(135deg,#d1fae5,#a7f3d0);
    border-color:#6ee7b7;color:#065f46;
}
.rc-result-banner .left-part .stat-chip.status-fail{
    background:linear-gradient(135deg,#fee2e2,#fecaca);
    border-color:#fca5a5;color:#7f1d1d;
}
.rc-result-banner .left-part .stat-chip.position-chip{
    background:linear-gradient(135deg,#fef3c7,#fde68a);
    border-color:#fcd34d;color:#78350f;
}
.rc-result-banner .right-part{
    width:130px;
    background:linear-gradient(135deg,#4f46e5,#7c3aed);
    color:#fff;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    padding:10px;gap:2px;
    border-radius:0 12px 12px 0;
}
.rc-result-banner .right-part .grade-lbl{
    font-size:9px;font-weight:700;letter-spacing:2px;
    text-transform:uppercase;opacity:.8;
}
.rc-result-banner .right-part .grade-val{
    font-size:30px;font-weight:900;line-height:1;
    text-shadow:0 2px 8px rgba(0,0,0,.3);
}
.rc-result-banner .right-part .grade-sub{
    font-size:9px;opacity:.75;font-weight:600;margin-top:2px;
}

/* ── REMARKS TABLE ── */
.rc-remarks-table{
    width:100%;border-collapse:collapse;font-size:11px;
    border-radius:10px;overflow:hidden;
    box-shadow:0 2px 10px rgba(6,182,212,.1);
}
.rc-remarks-table thead tr{
    background:linear-gradient(135deg,#0ea5e9,#6366f1);
}
.rc-remarks-table th{
    color:#fff;padding:7px 10px;text-align:left;
    font-size:9.5px;font-weight:700;
    letter-spacing:.5px;text-transform:uppercase;border:none;
}
.rc-remarks-table td{border:1px solid #e0f2fe;padding:5px 10px;color:#1e293b;}
.rc-remarks-table tbody tr:nth-child(odd){background:#f0f9ff;}
.rc-remarks-table tbody tr:nth-child(even){background:#fff;}

/* ── SIGNATURE BLOCK ── */
.rc-sigs{margin-top:auto;padding-top:14px;}
.rc-sigs-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 30px;}
.rc-sig-item{text-align:center;}
.rc-sig-line{
    border-top:2px dashed #c7d2fe;padding-top:5px;
    font-size:9px;color:#4338ca;font-weight:700;
    text-transform:uppercase;letter-spacing:.8px;
}

/* ── BRANDING ── */
.rc-brand{
    background:linear-gradient(90deg,#f8faff,#ede9fe,#f8faff);
    text-align:center;padding:6px 10px;
    font-size:9px;color:#6366f1;font-weight:700;
    letter-spacing:.8px;border-top:2px solid #e0e7ff;
    flex-shrink:0;
}

/* ── BOTTOM STRIPE ── */
.rc-bottom-stripe{
    height:6px;
    background:linear-gradient(90deg,#10b981,#06b6d4,#6366f1,#8b5cf6,#ec4899,#f59e0b);
    flex-shrink:0;
}

/* ── PRINT ── */
@media print{
    body{background:#fff !important;}
    .print-controls{display:none !important;}
    .cards-wrapper{padding:0;}
    .result-card{
        box-shadow:none;margin:0 auto;
        width:210mm;min-height:0;
        page-break-after:always;border-radius:0;
    }
    .result-card:last-child{page-break-after:auto;}
    .rc-rainbow,.rc-bottom-stripe,.rc-header,
    .rc-title-bar,
    .rc-marks-table thead tr,
    .rc-marks-table tfoot tr td,
    .rc-remarks-table thead tr,
    .rc-result-banner .right-part,
    .badge-pass,.badge-fail,.badge-absent,
    .stat-chip.status-pass,.stat-chip.status-fail,
    .stat-chip.position-chip{
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
    }
}
</style>
</head>
<body>

<!-- Print Controls -->
<div class="print-controls">
    <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> &nbsp;Print All Result Cards</button>
    <span class="print-info">
        <i class="fa-solid fa-user-graduate" style="color:#6366f1;"></i> <?= count($all_results) ?> Student(s)
        &nbsp;&bull;&nbsp; <i class="fa-solid fa-file-lines" style="color:#8b5cf6;"></i> <?= htmlspecialchars($exam_name) ?>
        &nbsp;&bull;&nbsp; <i class="fa-solid fa-chalkboard" style="color:#0ea5e9;"></i> <?= htmlspecialchars($class_display) ?>
    </span>
</div>

<div class="cards-wrapper">
<?php foreach ($all_results as $rd): ?>
<div class="result-card">

    <!-- Rainbow top stripe -->
    <div class="rc-rainbow"></div>

    <!-- ── HEADER ── -->
    <div class="rc-header">
        <!-- Logo -->
        <div class="rc-logo-wrap">
            <?php
            $logo_path = UPLOAD_PATH . ($settings['school_logo'] ?? '');
            $logo_url  = BASE_URL . 'uploads/' . htmlspecialchars($settings['school_logo'] ?? '');
            if (!empty($settings['school_logo']) && file_exists($logo_path)):
            ?>
                <img src="<?= $logo_url ?>" alt="School Logo">
            <?php else: ?>
                <span class="logo-placeholder"><i class="fa-solid fa-school" style="color:#fff;"></i></span>
            <?php endif; ?>
        </div>

        <!-- School info -->
        <div class="rc-header-center">
            <div class="rc-school-name"><?= htmlspecialchars($school_name) ?></div>
            <?php if ($school_addr): ?>
            <div class="rc-school-city"><?= htmlspecialchars($school_addr) ?></div>
            <?php endif; ?>
            <div class="rc-school-meta">
                <?php if ($school_phone): ?><span><i class="fa-solid fa-phone-volume"></i> <?= htmlspecialchars($school_phone) ?></span><?php endif; ?>
                <?php if ($school_web):   ?><span><i class="fa-solid fa-globe"></i> <?= htmlspecialchars($school_web) ?></span><?php endif; ?>
                <?php if ($school_email): ?><span><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($school_email) ?></span><?php endif; ?>
            </div>
            <div class="rc-class-exam">
                <i class="fa-solid fa-graduation-cap"></i> Class: <?= htmlspecialchars(($rd['class_name'] ?? '') . ' &mdash; ' . ($rd['section'] ?? '')) ?>
                &nbsp;|&nbsp;
                <i class="fa-solid fa-calendar-days"></i> <?= htmlspecialchars($exam_name) ?> &nbsp;<?= htmlspecialchars($session_year) ?>
            </div>
        </div>

        <!-- Spacer -->
        <div class="rc-logo-spacer"></div>
    </div>

    <!-- ── TITLE BAR ── -->
    <div class="rc-title-bar"><i class="fa-solid fa-star" style="font-size:9px;vertical-align:middle;margin-right:6px;"></i> Student Result Card <i class="fa-solid fa-star" style="font-size:9px;vertical-align:middle;margin-left:6px;"></i></div>

    <!-- ── BODY ── -->
    <div class="rc-body">

        <!-- Student Info -->
        <div>
            <div class="rc-section-label"><i class="fa-solid fa-id-card"></i> Student Information</div>
            <table class="rc-info-table">
                <tr>
                    <td class="lbl">Roll No.</td>
                    <td class="val"><?= htmlspecialchars($rd['enrollment_roll_no'] ?? ($rd['roll_no'] ?? '&mdash;')) ?></td>
                    <td class="lbl">Exam / Term</td>
                    <td class="val"><?= htmlspecialchars($exam_name . ' · ' . $session_year) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Admission No.</td>
                    <td class="val"><?= htmlspecialchars($rd['admission_no'] ?? '&mdash;') ?></td>
                    <td class="lbl">Section</td>
                    <td class="val"><?= htmlspecialchars($rd['section'] ?? '&mdash;') ?></td>
                </tr>
                <tr>
                    <td class="lbl">Student Name</td>
                    <td class="val"><?= htmlspecialchars($rd['name'] ?? '&mdash;') ?></td>
                    <td class="lbl">Son / D/O</td>
                    <td class="val"><?= htmlspecialchars($rd['father_name'] ?? '&mdash;') ?></td>
                </tr>
            </table>
        </div>

        <!-- Academic Performance -->
        <div>
            <div class="rc-section-label"><i class="fa-solid fa-chart-column"></i> Academic Performance</div>
            <table class="rc-marks-table">
                <thead>
                    <tr>
                        <th class="left" style="width:34%">Subject</th>
                        <th style="width:12%">Total</th>
                        <th style="width:13%">Obtained</th>
                        <th style="width:12%">Percentage</th>
                        <th style="width:9%">Grade</th>
                        <th style="width:10%">Result</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rd['marks'] as $m): ?>
                <tr>
                    <td class="subject-name"><?= htmlspecialchars($m['sub_name']) ?></td>
                    <td><?= $m['max_marks'] ?></td>
                    <td>
                        <?php if ($m['is_absent']): ?>
                            <span class="badge badge-absent">Absent</span>
                        <?php else: ?>
                            <?= $m['marks_obtained'] ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $m['is_absent'] ? '0%' : number_format($m['pct'], 1) . '%' ?></td>
                    <td><strong><?= $m['is_absent'] ? '&mdash;' : htmlspecialchars($m['grade']) ?></strong></td>
                    <td>
                        <?php if ($m['is_absent']): ?>
                            <span class="badge badge-absent">Absent</span>
                        <?php elseif ($m['passed']): ?>
                            <span class="badge badge-pass">Pass</span>
                        <?php else: ?>
                            <span class="badge badge-fail">Fail</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="left" colspan="2">GRAND TOTAL</td>
                        <td><strong><?= $rd['total_obtained'] ?></strong></td>
                        <td><strong><?= number_format($rd['overall_pct'], 1) ?>%</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Overall Result Banner -->
        <div class="rc-result-banner">
            <div class="left-part">
                <span class="stat-chip">
                    <i class="fa-solid fa-calculator" style="color:#6366f1;"></i> <strong>Total:</strong>&nbsp;<?= $rd['total_obtained'] ?> / <?= $rd['total_max'] ?>
                </span>
                <span class="stat-chip">
                    <i class="fa-solid fa-percent" style="color:#8b5cf6;"></i> <strong>Percentage:</strong>&nbsp;<?= number_format($rd['overall_pct'], 2) ?>%
                </span>
                <span class="stat-chip <?= $rd['result_status'] === 'PASS' ? 'status-pass' : 'status-fail' ?>">
                    <i class="fa-solid <?= $rd['result_status'] === 'PASS' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> <strong>Status:</strong>&nbsp;<?= $rd['result_status'] ?>
                </span>
                <?php if ($show_position && isset($rd['class_position']) && $rd['class_position']): ?>
                    <?php
                    $pos = (int)$rd['class_position'];
                    $mod100 = $pos % 100;
                    $mod10  = $pos % 10;
                    if ($mod100 >= 11 && $mod100 <= 13) { $suffix = 'th'; }
                    elseif ($mod10 === 1) { $suffix = 'st'; }
                    elseif ($mod10 === 2) { $suffix = 'nd'; }
                    elseif ($mod10 === 3) { $suffix = 'rd'; }
                    else { $suffix = 'th'; }
                    ?>
                    <span class="stat-chip position-chip">
                        <i class="fa-solid fa-award" style="color:#d97706;"></i> <strong>Position:</strong>&nbsp;<?= $pos . $suffix ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="right-part">
                <span class="grade-lbl">Grade</span>
                <span class="grade-val"><?= htmlspecialchars($rd['overall_grade']) ?></span>
                <span class="grade-sub"><?= htmlspecialchars($rd['overall_remarks']) ?></span>
            </div>
        </div>

        <!-- Teacher's Remarks -->
        <div>
            <div class="rc-section-label"><i class="fa-solid fa-comments"></i> Teacher&apos;s Remarks</div>
            <table class="rc-remarks-table">
                <thead>
                    <tr>
                        <th style="width:26%">Subject</th>
                        <th style="width:10%">Grade</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rd['marks'] as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['sub_name']) ?></td>
                    <td style="text-align:center;font-weight:800;color:#4f46e5;">
                        <?= $m['is_absent'] ? '&mdash;' : htmlspecialchars($m['grade']) ?>
                    </td>
                    <td>
                        <?php
                        if ($m['is_absent']) {
                            echo '<em style="color:#94a3b8;">Student was absent</em>';
                        } else {
                            echo htmlspecialchars($m['remarks']);
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Signature Block -->
        <div class="rc-sigs">
            <div class="rc-sigs-grid">
                <div class="rc-sig-item">
                    <div style="height:28px;"></div>
                    <div class="rc-sig-line">Parent / Guardian</div>
                </div>
                <div class="rc-sig-item">
                    <div style="height:28px;"></div>
                    <div class="rc-sig-line">Class Teacher</div>
                </div>
                <div class="rc-sig-item">
                    <div style="height:28px;"></div>
                    <div class="rc-sig-line">Incharge Exams</div>
                </div>
                <div class="rc-sig-item">
                    <div style="height:28px;"></div>
                    <div class="rc-sig-line"><?= htmlspecialchars($principal ?: 'Principal / Head') ?></div>
                </div>
            </div>
        </div>

    </div><!-- /rc-body -->

    <!-- Branding -->
    <div class="rc-brand"><i class="fa-solid fa-shield-halved" style="font-size:8px;vertical-align:middle;margin-right:4px;"></i> Generated by SIAC Technologies &mdash; SMSS &nbsp;|&nbsp; <?= date('d M Y') ?> <i class="fa-solid fa-shield-halved" style="font-size:8px;vertical-align:middle;margin-left:4px;"></i></div>

    <!-- Bottom rainbow stripe -->
    <div class="rc-bottom-stripe"></div>

</div><!-- /result-card -->
<?php endforeach; ?>
</div><!-- /cards-wrapper -->

</body>
</html>
