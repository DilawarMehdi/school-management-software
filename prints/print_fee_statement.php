<?php
/**
 * SIAX SMSS - Student Fee Statement (Printable Dossier)
 */
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';

$student_id = (int)($_GET['student_id'] ?? 0);
$session = $conn->real_escape_string($_GET['session'] ?? '2025-2026');

if (!$student_id) die("Invalid Student ID.");

$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'Your School Name';
$school_phone = $settings['phone'] ?? '03555295651';
$currency = $settings['currency'] ?? 'PKR';
$school_logo_raw = $settings['school_logo'] ?? '';
if ($school_logo_raw && file_exists(__DIR__ . '/../uploads/' . $school_logo_raw)) {
    $school_logo = '../uploads/' . $school_logo_raw;
} elseif ($school_logo_raw && file_exists(__DIR__ . '/../' . $school_logo_raw)) {
    $school_logo = '../' . $school_logo_raw;
} else {
    $school_logo = '../assets/img/logo.png';
}

// Fetch student details
$st_q = $conn->query("
    SELECT s.*, c.name as class_name, c.section 
    FROM students s
    LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.session_year = '$session'
    LEFT JOIN classes c ON se.class_id = c.id
    WHERE s.id = $student_id
");
$student = $st_q->fetch_assoc();
if (!$student) die("Student not found.");

// Fetch monthly fee history for the session
$q = $conn->query("
    SELECT smf.*, fi.month, fi.title as invoice_title
    FROM student_monthly_fees smf
    JOIN fee_invoices fi ON smf.invoice_id = fi.id
    WHERE smf.student_id = $student_id AND fi.session_year = '$session'
    ORDER BY fi.id ASC
");
$invoices = [];
$total_billed = 0;
$total_paid = 0;
$total_discount = 0;

if ($q) {
    while ($row = $q->fetch_assoc()) {
        $billed = ($row['tuition_fee'] + $row['admission_fee'] + $row['prev_pending']) - $row['discount_amount'];
        $row['billed'] = $billed;
        $row['pending'] = $billed - $row['paid_amount'];
        
        $total_billed += $billed;
        $total_paid += $row['paid_amount'];
        $total_discount += $row['discount_amount'];
        
        $invoices[] = $row;
    }
}
$net_pending = $total_billed - $total_paid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Statement - <?= htmlspecialchars($student['name']) ?></title>
    <style>
        @page { size: portrait; margin: 15mm; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; margin: 0; padding: 0; color: #000; }
        
        .statement-container { width: 100%; max-width: 190mm; margin: 0 auto; box-sizing: border-box; }
        
        .header { display: flex; align-items: center; gap: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { width: 80px; height: 80px; object-fit: contain; }
        .school-info { flex: 1; text-align: center; }
        .school-info h1 { font-size: 24px; margin: 0; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .school-info p { margin: 4px 0; font-size: 13px; font-weight: 600; }
        
        .receipt-title { text-align: center; font-size: 18px; font-weight: 800; margin-bottom: 5px; text-transform: uppercase; }
        .receipt-subtitle { text-align: center; font-size: 12px; font-weight: 600; margin-bottom: 25px; color: #444; }
        
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 25px; border: 1px solid #000; padding: 15px; background: #fdfdfd; }
        .meta-item { display: flex; border-bottom: 1px dashed #ccc; padding: 5px 0; }
        .meta-item:last-child { border-bottom: none; }
        .meta-label { font-weight: 700; width: 40%; }
        .meta-val { font-weight: 600; }
        
        .fee-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .fee-table th, .fee-table td { border: 1px solid #000; padding: 10px 12px; text-align: left; }
        .fee-table th { background: #f0f0f0; font-weight: 800; text-transform: uppercase; font-size: 11px; }
        .fee-table td { font-weight: 600; }
        .fee-table th.num, .fee-table td.num { text-align: right; }
        
        .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .status-Paid { background: #dcfce7; color: #16a34a; border: 1px solid #16a34a; }
        .status-Pending { background: #fee2e2; color: #dc2626; border: 1px solid #dc2626; }
        .status-Partial { background: #fef3c7; color: #d97706; border: 1px solid #d97706; }
        
        .summary-box { width: 300px; float: right; border: 2px solid #000; border-collapse: collapse; margin-bottom: 40px; }
        .summary-box td { padding: 8px 12px; border-bottom: 1px solid #ccc; font-size: 13px; }
        .summary-box tr:last-child td { border-bottom: none; background: #f0f0f0; font-size: 15px; font-weight: 800; }
        
        .clearfix::after { content: ""; clear: both; display: table; }
        
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; }
        .sig-line { width: 200px; text-align: center; border-top: 1px solid #000; padding-top: 5px; font-weight: 700; }
        
        .footer-note { text-align: center; margin-top: 30px; font-size: 11px; font-weight: 600; color: #555; }
        
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">
    
    <div class="no-print" style="text-align:center; padding: 10px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #4f46e5; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
            🖨️ Print / Save as PDF
        </button>
    </div>

    <div class="statement-container">
        <!-- Header -->
        <div class="header">
            <img src="<?= $school_logo ?>" class="logo" alt="Logo">
            <div class="school-info">
                <h1><?= htmlspecialchars($school_name) ?></h1>
                <p>Contact: <?= htmlspecialchars($school_phone) ?></p>
            </div>
            <div style="width: 80px;"></div> <!-- Balance spacer -->
        </div>
        
        <div class="receipt-title">STUDENT FEE STATEMENT</div>
        <div class="receipt-subtitle">Academic Session: <?= htmlspecialchars($session) ?> | Date: <?= date('d M Y') ?></div>
        
        <!-- Student Details -->
        <div class="meta-grid">
            <div>
                <div class="meta-item"><div class="meta-label">Student Name:</div><div class="meta-val"><?= htmlspecialchars($student['name']) ?></div></div>
                <div class="meta-item"><div class="meta-label">Admission No:</div><div class="meta-val"><?= htmlspecialchars($student['admission_no']) ?></div></div>
                <div class="meta-item"><div class="meta-label">Class:</div><div class="meta-val"><?= htmlspecialchars(($student['class_name']??'N/A') . ' ' . ($student['section']??'')) ?></div></div>
            </div>
            <div>
                <div class="meta-item"><div class="meta-label">Father's Name:</div><div class="meta-val"><?= htmlspecialchars($student['father_name'] ?: 'N/A') ?></div></div>
                <div class="meta-item"><div class="meta-label">Contact No:</div><div class="meta-val"><?= htmlspecialchars($student['phone'] ?: ($student['father_phone'] ?: 'N/A')) ?></div></div>
                <div class="meta-item"><div class="meta-label">Gender:</div><div class="meta-val"><?= htmlspecialchars($student['gender']) ?></div></div>
            </div>
        </div>
        
        <!-- Invoice History Table -->
        <table class="fee-table">
            <thead>
                <tr>
                    <th>Invoice Month</th>
                    <th class="num">Amount Billed</th>
                    <th class="num">Amount Paid</th>
                    <th class="num">Balance (Dues)</th>
                    <th style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($invoices)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding: 20px;">No fee invoices generated for this session yet.</td>
                </tr>
                <?php else: ?>
                    <?php foreach($invoices as $inv): ?>
                    <tr>
                        <td><?= htmlspecialchars($inv['month']) ?></td>
                        <td class="num"><?= $currency ?> <?= number_format($inv['billed'], 0) ?></td>
                        <td class="num" style="<?= $inv['paid_amount'] > 0 ? 'color:#16a34a;' : '' ?>"><?= $currency ?> <?= number_format($inv['paid_amount'], 0) ?></td>
                        <td class="num" style="<?= $inv['pending'] > 0 ? 'color:#dc2626;' : '' ?>"><?= $currency ?> <?= number_format($inv['pending'], 0) ?></td>
                        <td style="text-align:center;">
                            <span class="status-badge status-<?= $inv['status'] ?>"><?= $inv['status'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Summary & Balance -->
        <div class="clearfix">
            <table class="summary-box">
                <tr>
                    <td style="font-weight:700;">Total Billed (Session)</td>
                    <td style="text-align:right;"><?= $currency ?> <?= number_format($total_billed, 0) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:700;">Total Paid (Session)</td>
                    <td style="text-align:right; color:#16a34a;"><?= $currency ?> <?= number_format($total_paid, 0) ?></td>
                </tr>
                <tr>
                    <td style="font-weight:700; color:#dc2626;">TOTAL DUES PENDING</td>
                    <td style="text-align:right; color:#dc2626;"><?= $currency ?> <?= number_format($net_pending, 0) ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Signatures -->
        <div class="signatures">
            <div class="sig-line">Parent / Guardian Signature</div>
            <div class="sig-line">Principal / Accountant</div>
        </div>
        
        <div class="footer-note">
            This is an automatically generated fee statement. All amounts are subject to audit and verification.
        </div>
    </div>
    
</body>
</html>
