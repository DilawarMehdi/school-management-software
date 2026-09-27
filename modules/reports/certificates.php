<?php
/**
 * SIAX SMSS - Certificates Module
 * Premium design for student certificates.
 */
ob_start();
$page_title = 'Certificates';
$active_page = 'certificates';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$msg = ''; $err = '';

// Issue certificate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_cert') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $type       = $conn->real_escape_string($_POST['type'] ?? 'Leaving');
    $issue_date = $conn->real_escape_string($_POST['issue_date'] ?? date('Y-m-d'));
    $leaving_date = $conn->real_escape_string($_POST['leaving_date'] ?? $issue_date);
    $reason     = $conn->real_escape_string(trim($_POST['reason'] ?? ''));
    $dest       = $conn->real_escape_string(trim($_POST['destination_school'] ?? ''));
    $conduct    = $conn->real_escape_string(trim($_POST['conduct'] ?? 'Good'));
    $remarks    = $conn->real_escape_string(trim($_POST['remarks'] ?? ''));
    $fee_clear  = isset($_POST['fee_clearance']) ? 1 : 0;
    $uid        = current_uid();

    if ($student_id > 0) {
        $cert_no = generate_cert_no($conn, $type);
        $sql = "INSERT INTO certificates (cert_no, student_id, type, issue_date, reason, destination_school, fee_clearance, conduct, remarks, issued_by, leaving_date) 
                VALUES ('$cert_no', $student_id, '$type', '$issue_date', '$reason', '$dest', $fee_clear, '$conduct', '$remarks', $uid, '$leaving_date')";
        
        if ($conn->query($sql)) {
            header("Location: " . BASE_URL . "modules/reports/certificates.php?view=" . $conn->insert_id);
            exit;
        } else {
            $err = 'Database error: ' . $conn->error;
        }
    } else {
        $err = 'Please select a student.';
    }
}

// Delete
if (isset($_GET['delete']) && current_role() === 'admin') {
    $conn->query("DELETE FROM certificates WHERE id=" . (int)$_GET['delete']);
    $msg = 'Certificate deleted successfully.';
}

// Fetch students (session-aware)
$active_session = $settings['session_year'] ?? '2025-2026';
$students_q = $conn->query("
    SELECT s.id, s.name, s.admission_no, c.name as class_name, c.section 
    FROM student_enrollments se 
    JOIN students s ON se.student_id = s.id 
    LEFT JOIN classes c ON se.class_id = c.id 
    WHERE se.session_year = '$active_session' AND se.status = 'Active' AND s.status = 'Active' 
    ORDER BY s.name
");
$students_arr = [];
if ($students_q) while($s = $students_q->fetch_assoc()) $students_arr[] = $s;

// Fetch issued certificates (session-aware)
$certs_q = $conn->query("
    SELECT ce.*, s.name as student_name, s.admission_no, c.name as class_name, c.section 
    FROM certificates ce 
    JOIN students s ON ce.student_id=s.id 
    LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.session_year = '$active_session' 
    LEFT JOIN classes c ON COALESCE(se.class_id, s.class_id) = c.id 
    ORDER BY ce.created_at DESC LIMIT 50
");

$cert_types = [
    'Leaving' => ['icon' => 'fa-door-open', 'color' => '#ef4444', 'title' => 'School Leaving Certificate'],
    'Character' => ['icon' => 'fa-award', 'color' => '#10b981', 'title' => 'Character Certificate'],
    'Bonafide' => ['icon' => 'fa-id-card', 'color' => '#3b82f6', 'title' => 'Bonafide Certificate'],
    'Transfer' => ['icon' => 'fa-exchange-alt', 'color' => '#f59e0b', 'title' => 'Transfer Certificate'],
    'Migration' => ['icon' => 'fa-plane-departure', 'color' => '#8b5cf6', 'title' => 'Migration Certificate']
];

// View certificate
$view_cert = null;
$is_blank = false;
$blank_type = 'Leaving';

if (isset($_GET['view'])) {
    if ($_GET['view'] === 'blank') {
        $is_blank = true;
        $blank_type = isset($_GET['type']) && isset($cert_types[$_GET['type']]) ? $_GET['type'] : 'Leaving';
    } else {
        $vid = (int)$_GET['view'];
        $vq = $conn->query("
            SELECT ce.*, s.*, c.name as class_name, c.section 
            FROM certificates ce 
            JOIN students s ON ce.student_id=s.id 
            LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.session_year = '$active_session' 
            LEFT JOIN classes c ON COALESCE(se.class_id, s.class_id) = c.id 
            WHERE ce.id=$vid
        ");
        if ($vq && $vq->num_rows) $view_cert = $vq->fetch_assoc();
    }
}
?>

<style>
/* PREMIUM CERTIFICATE STYLE */
.cert-print-container {
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    margin-bottom: 30px;
}
@page {
    size: A4 portrait;
    margin: 0;
}
.cert-doc {
    background: #fff;
    color: #111;
    width: 210mm;
    min-height: 297mm;
    box-sizing: border-box;
    margin: 0 auto;
    padding: 18mm 20mm 16mm;
    border: 14px solid #e2e8f0;
    position: relative;
    font-family: 'Times New Roman', serif;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
}
.cert-doc::before {
    content: '';
    position: absolute;
    top: 5px; left: 5px; right: 5px; bottom: 5px;
    border: 2px solid #64748b;
    pointer-events: none;
}
.cert-header {
    text-align: center;
    margin-bottom: 28px;
}
.cert-header h1 { font-size: 26px; font-weight: 800; margin: 0; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; }
.cert-header h2 { font-size: 15px; margin: 6px 0; color: #475569; font-weight: 400; }
.cert-header p { font-size: 11.5px; color: #64748b; margin: 2px 0; }

.cert-title-box {
    text-align: center;
    margin: 22px 0;
}
.cert-title-box h3 {
    display: inline-block;
    font-size: 22px;
    font-weight: 700;
    border-bottom: 2px solid #1e293b;
    padding-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 2.5px;
    color: #1e293b;
}

.cert-meta {
    display: flex;
    justify-content: space-between;
    font-size: 13.5px;
    margin-bottom: 24px;
    color: #475569;
}

.cert-body {
    font-size: 16.5px;
    line-height: 2.1;
    text-align: justify;
    color: #1e293b;
    flex: 1;
}
.cert-body .field {
    font-weight: 700;
    border-bottom: 1px dashed #94a3b8;
    padding: 0 6px;
    color: #000;
}
.cert-body .blank-line {
    display: inline-block;
    border-bottom: 1.5px solid #1e293b;
    height: 18px;
    margin: 0 4px;
    vertical-align: middle;
}

.cert-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: auto;
    padding-top: 30px;
}
.cert-stamp {
    width: 110px;
    height: 110px;
    border: 2px solid #cbd5e1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9.5px;
    color: #94a3b8;
    text-align: center;
    text-transform: uppercase;
}
.cert-signature {
    text-align: center;
    width: 200px;
}
.cert-signature .sign-line {
    border-top: 2px solid #1e293b;
    margin-top: 50px;
    padding-top: 6px;
    font-weight: 700;
    font-size: 13.5px;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }
    .no-print { display: none !important; }
    body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
    .cert-print-container { padding: 0 !important; margin: 0 !important; background: transparent !important; }
    .cert-doc {
        width: 210mm !important;
        height: 297mm !important;
        min-height: 297mm !important;
        max-width: 210mm !important;
        box-shadow: none !important;
        margin: 0 auto !important;
        padding: 16mm 18mm 14mm !important;
        border: 12px solid #cbd5e1 !important;
        page-break-after: avoid !important;
        page-break-inside: avoid !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .cert-doc::before {
        border: 2px solid #64748b !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .cert-body .blank-line {
        border-bottom-color: #000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

/* UI DASHBOARD */
.cert-type-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    transition: all 0.2s;
}
.cert-type-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--accent-glow); }
.cert-type-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
    flex-shrink: 0;
}
.cert-type-header {
    display: flex;
    align-items: center;
    gap: 14px;
}
.cert-type-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
    border-top: 1px solid var(--border);
    padding-top: 12px;
}
.btn-blank-cert {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}
.btn-blank-cert:hover {
    background: #6366f1;
    color: #fff;
    border-color: #6366f1;
}
</style>

<div class="main-container">
    <!-- ── BLANK CERTIFICATE VIEW ── -->
    <?php if($is_blank): ?>
    <div class="page-header no-print">
        <div>
            <h1><i class="fa-solid fa-file-lines" style="color:#6366f1;"></i> Blank Certificate Template</h1>
            <p>Ready-to-print official <?= $cert_types[$blank_type]['title'] ?> template</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <!-- Type Switcher -->
            <div class="dropdown" style="display:inline-block;">
                <select class="form-control" style="font-size:0.85rem; padding:8px 14px; font-weight:600;" onchange="location.href='?view=blank&type='+this.value">
                    <?php foreach($cert_types as $k => $ct): ?>
                    <option value="<?= $k ?>" <?= $blank_type===$k?'selected':'' ?>><?= $ct['title'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Blank Certificate</button>
            <a href="<?= BASE_URL ?>modules/reports/certificates.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
        </div>
    </div>

    <div class="cert-print-container">
        <div class="cert-doc">
            <div class="cert-header">
                <h1><?= htmlspecialchars($school_name) ?></h1>
                <h2><?= htmlspecialchars($settings['school_address'] ?? 'Official Address Not Set') ?></h2>
                <p>Phone: <?= htmlspecialchars($settings['school_phone'] ?? '-') ?> | Email: <?= htmlspecialchars($settings['school_email'] ?? '-') ?></p>
            </div>
            
            <div class="cert-title-box">
                <h3><?= $cert_types[$blank_type]['title'] ?></h3>
            </div>

            <div class="cert-meta">
                <span>Certificate No: <span class="blank-line" style="min-width: 140px;">&nbsp;</span></span>
                <span>Issue Date: <span class="blank-line" style="min-width: 140px;">&nbsp;</span></span>
            </div>

            <div class="cert-body">
                <?php if($blank_type === 'Leaving'): ?>
                    <p>This is to certify that <span class="blank-line" style="min-width: 280px;">&nbsp;</span> Son/Daughter of <span class="blank-line" style="min-width: 250px;">&nbsp;</span>, bearing Admission No. <span class="blank-line" style="min-width: 130px;">&nbsp;</span>, was a student of this school in Class <span class="blank-line" style="min-width: 140px;">&nbsp;</span>.</p>
                    <p>He/She was admitted on <span class="blank-line" style="min-width: 160px;">&nbsp;</span> and has been withdrawn from the school on <span class="blank-line" style="min-width: 160px;">&nbsp;</span>.</p>
                    <p>As per the school record, his/her date of birth is <span class="blank-line" style="min-width: 180px;">&nbsp;</span>.</p>
                    <p>His/Her conduct and character during his/her stay at this institution remained <span class="blank-line" style="min-width: 160px;">&nbsp;</span>.</p>
                    <p>All school dues have been <span class="blank-line" style="min-width: 180px;">&nbsp;</span>.</p>
                    <p style="margin-top:20px;">Remarks: <span class="blank-line" style="min-width: 80%;">&nbsp;</span></p>
                
                <?php elseif($blank_type === 'Character'): ?>
                    <p>It is certified that <span class="blank-line" style="min-width: 280px;">&nbsp;</span> Son/Daughter of <span class="blank-line" style="min-width: 250px;">&nbsp;</span>, having Admission No. <span class="blank-line" style="min-width: 130px;">&nbsp;</span>, has been a student of Class <span class="blank-line" style="min-width: 140px;">&nbsp;</span> at this school from <span class="blank-line" style="min-width: 140px;">&nbsp;</span> to <span class="blank-line" style="min-width: 140px;">&nbsp;</span>.</p>
                    <p>During his/her stay, he/she has been found to be <span class="blank-line" style="min-width: 160px;">&nbsp;</span> in conduct and character. He/She is diligent, obedient, and took an active interest in co-curricular activities.</p>
                    <p>I wish him/her every success in his/her future endeavors.</p>
                    <p style="margin-top:20px;">Remarks: <span class="blank-line" style="min-width: 80%;">&nbsp;</span></p>

                <?php elseif($blank_type === 'Bonafide'): ?>
                    <p>This is to certify that <span class="blank-line" style="min-width: 280px;">&nbsp;</span> Son/Daughter of <span class="blank-line" style="min-width: 250px;">&nbsp;</span>, bearing Admission No. <span class="blank-line" style="min-width: 130px;">&nbsp;</span>, is a bonafide student of this school studying in Class <span class="blank-line" style="min-width: 140px;">&nbsp;</span> during the session <span class="blank-line" style="min-width: 120px;"><?= htmlspecialchars($settings['session_year'] ?? '') ?></span>.</p>
                    <p>According to the school records, his/her date of birth is <span class="blank-line" style="min-width: 180px;">&nbsp;</span>.</p>
                    <p>This certificate is issued at the request of the parent/guardian for the purpose of <span class="blank-line" style="min-width: 280px;">&nbsp;</span>.</p>
                    <p style="margin-top:20px;">Remarks: <span class="blank-line" style="min-width: 80%;">&nbsp;</span></p>

                <?php elseif($blank_type === 'Transfer'): ?>
                    <p>This is to certify that <span class="blank-line" style="min-width: 280px;">&nbsp;</span> Son/Daughter of <span class="blank-line" style="min-width: 250px;">&nbsp;</span>, bearing Admission No. <span class="blank-line" style="min-width: 130px;">&nbsp;</span>, was a student of this school in Class <span class="blank-line" style="min-width: 140px;">&nbsp;</span>.</p>
                    <p>He/She is transferring to <span class="blank-line" style="min-width: 300px;">&nbsp;</span> on <span class="blank-line" style="min-width: 150px;">&nbsp;</span>.</p>
                    <p>His/Her conduct remained <span class="blank-line" style="min-width: 160px;">&nbsp;</span>. All school dues have been <span class="blank-line" style="min-width: 160px;">&nbsp;</span>.</p>
                    <p style="margin-top:20px;">Remarks: <span class="blank-line" style="min-width: 80%;">&nbsp;</span></p>

                <?php else: ?>
                    <p>This is to certify that <span class="blank-line" style="min-width: 280px;">&nbsp;</span> Son/Daughter of <span class="blank-line" style="min-width: 250px;">&nbsp;</span>, bearing Admission No. <span class="blank-line" style="min-width: 130px;">&nbsp;</span>, was/is a student of this school in Class <span class="blank-line" style="min-width: 140px;">&nbsp;</span>.</p>
                    <p>This Migration Certificate is issued on <span class="blank-line" style="min-width: 160px;">&nbsp;</span> to enable the student to seek admission in <span class="blank-line" style="min-width: 280px;">&nbsp;</span>.</p>
                    <p>Conduct: <span class="blank-line" style="min-width: 150px;">&nbsp;</span>. Fee Status: <span class="blank-line" style="min-width: 150px;">&nbsp;</span>.</p>
                    <p style="margin-top:20px;">Remarks: <span class="blank-line" style="min-width: 80%;">&nbsp;</span></p>
                <?php endif; ?>
            </div>

            <div class="cert-footer">
                <div class="cert-stamp">OFFICIAL SEAL / STAMP</div>
                <div class="cert-signature">
                    <div class="sign-line"><?= htmlspecialchars($settings['principal_name'] ?? 'Principal') ?></div>
                    <div style="font-size:12px; color:#64748b;">Head of Institution</div>
                </div>
            </div>
        </div>
    </div>
    <?php ob_end_flush(); return; endif; ?>

    <!-- ── ISSUED CERTIFICATE VIEW ── -->
    <?php if($view_cert): ?>
    <div class="page-header no-print">
        <div>
            <h1>View Certificate</h1>
            <p><?= $cert_types[$view_cert['type']]['title'] ?> for <?= htmlspecialchars($view_cert['name']) ?></p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Now</button>
            <a href="?view=blank&type=<?= $view_cert['type'] ?>" class="btn btn-secondary" style="background:#4f46e5;color:#fff;"><i class="fa-solid fa-file"></i> Print Blank Copy</a>
            <a href="<?= BASE_URL ?>modules/reports/certificates.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="cert-print-container">
        <div class="cert-doc">
            <div class="cert-header">
                <h1><?= htmlspecialchars($school_name) ?></h1>
                <h2><?= htmlspecialchars($settings['school_address'] ?? 'Official Address Not Set') ?></h2>
                <p>Phone: <?= htmlspecialchars($settings['school_phone'] ?? '-') ?> | Email: <?= htmlspecialchars($settings['school_email'] ?? '-') ?></p>
            </div>
            
            <div class="cert-title-box">
                <h3><?= $cert_types[$view_cert['type']]['title'] ?></h3>
            </div>

            <div class="cert-meta">
                <span>Certificate No: <strong><?= htmlspecialchars($view_cert['cert_no']) ?></strong></span>
                <span>Issue Date: <strong><?= date('d M Y', strtotime($view_cert['issue_date'])) ?></strong></span>
            </div>

            <div class="cert-body">
                <?php 
                $name = htmlspecialchars($view_cert['name']);
                $fname = htmlspecialchars($view_cert['father_name'] ?? '');
                $class = htmlspecialchars(($view_cert['class_name'] ?? '') . ' ' . ($view_cert['section'] ?? ''));
                $adm_no = htmlspecialchars($view_cert['admission_no']);
                $dob = $view_cert['dob'] ? date('d M Y', strtotime($view_cert['dob'])) : '---';
                $adm_date = $view_cert['admission_date'] ? date('d M Y', strtotime($view_cert['admission_date'])) : '---';
                $leaving_date = $view_cert['leaving_date'] ? date('d M Y', strtotime($view_cert['leaving_date'])) : date('d M Y', strtotime($view_cert['issue_date']));
                $conduct = htmlspecialchars($view_cert['conduct']);
                ?>

                <?php if($view_cert['type'] === 'Leaving'): ?>
                    <p>This is to certify that <span class="field"><?= $name ?></span> Son/Daughter of <span class="field"><?= $fname ?></span>, bearing Admission No. <span class="field"><?= $adm_no ?></span>, was a student of this school in Class <span class="field"><?= $class ?></span>.</p>
                    <p>He/She was admitted on <span class="field"><?= $adm_date ?></span> and has been withdrawn from the school on <span class="field"><?= $leaving_date ?></span>.</p>
                    <p>As per the school record, his/her date of birth is <span class="field"><?= $dob ?></span>.</p>
                    <p>His/Her conduct and character during his/her stay at this institution remained <span class="field"><?= $conduct ?></span>.</p>
                    <p>All school dues have been <span class="field"><?= $view_cert['fee_clearance'] ? 'Fully Paid' : 'Uncleared' ?></span>.</p>
                
                <?php elseif($view_cert['type'] === 'Character'): ?>
                    <p>It is certified that <span class="field"><?= $name ?></span> Son/Daughter of <span class="field"><?= $fname ?></span>, having Admission No. <span class="field"><?= $adm_no ?></span>, has been a student of Class <span class="field"><?= $class ?></span> at this school from <span class="field"><?= $adm_date ?></span> to <span class="field"><?= $leaving_date ?></span>.</p>
                    <p>During his/her stay, he/she has been found to be <span class="field"><?= $conduct ?></span> in conduct and character. He/She is diligent, obedient, and took an active interest in co-curricular activities.</p>
                    <p>I wish him/her every success in his/her future endeavors.</p>

                <?php elseif($view_cert['type'] === 'Bonafide'): ?>
                    <p>This is to certify that <span class="field"><?= $name ?></span> Son/Daughter of <span class="field"><?= $fname ?></span>, bearing Admission No. <span class="field"><?= $adm_no ?></span>, is a bonafide student of this school studying in Class <span class="field"><?= $class ?></span> during the session <span class="field"><?= $settings['session_year'] ?? 'Current' ?></span>.</p>
                    <p>According to the school records, his/her date of birth is <span class="field"><?= $dob ?></span>.</p>
                    <p>This certificate is issued at the request of the parent/guardian for the purpose of <span class="field"><?= htmlspecialchars($view_cert['reason'] ?: 'General Use') ?></span>.</p>

                <?php else: ?>
                    <p>This is to certify that <span class="field"><?= $name ?></span> Son/Daughter of <span class="field"><?= $fname ?></span>, bearing Admission No. <span class="field"><?= $adm_no ?></span>, was/is a student of this school in Class <span class="field"><?= $class ?></span>.</p>
                    <p>This certificate (Type: <?= $view_cert['type'] ?>) is issued on <span class="field"><?= date('d M Y', strtotime($view_cert['issue_date'])) ?></span>.</p>
                    <p>Conduct: <span class="field"><?= $conduct ?></span>. Fee Status: <span class="field"><?= $view_cert['fee_clearance'] ? 'Cleared' : 'Pending' ?></span>.</p>
                <?php endif; ?>

                <?php if($view_cert['remarks']): ?>
                    <p style="margin-top:20px; font-style: italic;">Remarks: <?= htmlspecialchars($view_cert['remarks']) ?></p>
                <?php endif; ?>
            </div>

            <div class="cert-footer">
                <div class="cert-stamp">OFFICIAL SEAL / STAMP</div>
                <div class="cert-signature">
                    <div class="sign-line"><?= htmlspecialchars($settings['principal_name'] ?? 'Principal') ?></div>
                    <div style="font-size:12px; color:#64748b;">Head of Institution</div>
                </div>
            </div>
        </div>
    </div>
    <?php ob_end_flush(); return; endif; ?>

    <div class="page-header">
        <div>
            <h1>Certificates</h1>
            <p>Issue professional certificates for students or print blank templates</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="?view=blank&type=Leaving" class="btn btn-secondary" style="background:#4f46e5; color:#fff; border:none;">
                <i class="fa-solid fa-file"></i> Print Blank Certificate
            </a>
            <button class="btn btn-primary" onclick="document.getElementById('issueForm').style.display='block'; window.scrollTo(0,0);">
                <i class="fa-solid fa-plus-circle"></i> Issue New Certificate
            </button>
        </div>
    </div>

    <?php if($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:15px"><?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="login-error" style="margin-bottom:15px"><?= $err ?></div><?php endif; ?>

    <!-- Certificate Types Grid -->
    <div class="card-grid card-grid-3 mb-4">
        <?php foreach($cert_types as $key => $ct): ?>
        <div class="cert-type-card">
            <div class="cert-type-header" onclick="openIssueForm('<?= $key ?>')" style="cursor:pointer;">
                <div class="cert-type-icon" style="background: <?= $ct['color'] ?>">
                    <i class="fa-solid <?= $ct['icon'] ?>"></i>
                </div>
                <div>
                    <h4 style="margin:0; font-weight:700;"><?= $ct['title'] ?></h4>
                    <p style="margin:2px 0 0 0; font-size:0.75rem; color:var(--text-muted);">Click to fill &amp; issue</p>
                </div>
            </div>
            <div class="cert-type-actions">
                <button type="button" class="btn-blank-cert" style="flex:1; justify-content:center;" onclick="openIssueForm('<?= $key ?>')">
                    <i class="fa-solid fa-pen-to-square"></i> Issue
                </button>
                <a href="?view=blank&type=<?= $key ?>" class="btn-blank-cert" style="flex:1; justify-content:center;">
                    <i class="fa-solid fa-print"></i> Print Blank
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Issue Form -->
    <div class="card mb-4" id="issueForm" style="display:none; border-color: var(--accent-glow);">
        <div class="card-header">
            <h3><i class="fa-solid fa-file-signature"></i> Issue <span id="selectedCertType">Certificate</span></h3>
        </div>
        <form method="POST" style="padding: 25px;">
            <input type="hidden" name="action" value="issue_cert">
            <input type="hidden" name="type" id="certTypeInput" value="Leaving">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Student *</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach($students_arr as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['admission_no'].' — '.$s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Leaving / End Date</label>
                    <input type="date" name="leaving_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Conduct / Character</label>
                    <select name="conduct" class="form-control">
                        <option>Exemplary</option>
                        <option selected>Excellent</option>
                        <option>Very Good</option>
                        <option>Good</option>
                        <option>Satisfactory</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reason / Purpose</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Higher Studies / Personal Request">
                </div>
                <div class="form-group" style="display:flex; align-items:center; padding-top:25px;">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="checkbox" name="fee_clearance" value="1" checked> Fee Clearance Granted
                    </label>
                </div>
            </div>

            <div class="form-group" id="destSchoolGroup">
                <label>Destination School / Institution</label>
                <input type="text" name="destination_school" class="form-control" placeholder="Where the student is migrating to">
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Any additional comments..."></textarea>
            </div>

            <div class="btn-group mt-3">
                <button type="submit" class="btn btn-primary">Generate & Print</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('issueForm').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Recent Certificates -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-history"></i> Recently Issued Certificates</h3>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cert No</th>
                        <th>Student</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($certs_q && $certs_q->num_rows > 0): while($c = $certs_q->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['cert_no']) ?></strong></td>
                        <td><?= htmlspecialchars($c['student_name']) ?> <small class="text-muted"><?= $c['admission_no'] ?></small></td>
                        <td><span class="badge" style="background: <?= $cert_types[$c['type']]['color'] ?? '#3b82f6' ?>; color:#fff;"><?= $c['type'] ?></span></td>
                        <td><?= date('d M Y', strtotime($c['issue_date'])) ?></td>
                        <td><?= $c['fee_clearance'] ? '<span class="text-success">Cleared</span>' : '<span class="text-danger">Pending</span>' ?></td>
                        <td class="table-actions">
                            <a href="?view=<?= $c['id'] ?>" class="btn btn-xs btn-primary"><i class="fa-solid fa-print"></i></a>
                            <?php if(current_role()==='admin'): ?>
                            <a href="?delete=<?= $c['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete this record?')"><i class="fa-solid fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-5">No certificates found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function openIssueForm(type) {
    document.getElementById('issueForm').style.display = 'block';
    document.getElementById('selectedCertType').textContent = type;
    document.getElementById('certTypeInput').value = type;
    
    // Hide/Show destination for Leaving/Transfer only
    const destGroup = document.getElementById('destSchoolGroup');
    if (type === 'Leaving' || type === 'Transfer' || type === 'Migration') {
        destGroup.style.display = 'block';
    } else {
        destGroup.style.display = 'none';
    }
    
    window.scrollTo({ top: document.getElementById('issueForm').offsetTop - 100, behavior: 'smooth' });
}
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
