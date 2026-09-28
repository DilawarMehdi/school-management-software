<?php
/**
 * SIAX SMSS - Print Fee List Report
 * Generates a class-wise fee report for a specific invoice.
 */
require_once __DIR__ . '/../config/db.php';

$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$class_id = (int)($_GET['class_id'] ?? 0);

if (!$invoice_id || !$class_id) die("Invalid Invoice or Class ID.");

$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'Your School Name';
$school_phone = $settings['phone'] ?? '03555295651';
$school_logo_raw = $settings['school_logo'] ?? '';
if ($school_logo_raw && file_exists(UPLOAD_PATH . $school_logo_raw)) {
    $school_logo = BASE_URL . 'uploads/' . $school_logo_raw;
} elseif ($school_logo_raw && file_exists($school_logo_raw)) {
    $school_logo = BASE_URL . $school_logo_raw;
} else {
    $school_logo = BASE_URL . 'assets/img/logo.png';
}
$school_website = $settings['website'] ?? 'www.siax.edu.pk';

// Fetch Invoice
$inv_q = $conn->query("SELECT * FROM fee_invoices WHERE id = $invoice_id");
$invoice = $inv_q->fetch_assoc();

// Fetch Class Info
$class_q = $conn->query("SELECT * FROM classes WHERE id = $class_id");
$class = $class_q->fetch_assoc();

// Fetch Student Fee Records
$fees_q = $conn->query("SELECT sf.*, s.name as student_name, s.father_name, s.admission_no 
                        FROM student_monthly_fees sf 
                        JOIN students s ON sf.student_id = s.id 
                        WHERE sf.invoice_id = $invoice_id AND sf.class_id = $class_id 
                        ORDER BY s.name");

$students = [];
while($row = $fees_q->fetch_assoc()) $students[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee List - <?= htmlspecialchars($class['name'] . ' ' . $class['section']) ?></title>
    <style>
        @page { size: landscape; margin: 10mm; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 11px; margin: 0; padding: 0; color: #000; }
        
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { width: 80px; height: 80px; object-fit: contain; }
        .school-info { flex: 1; text-align: center; }
        .school-info h1 { font-size: 24px; margin: 0; text-transform: uppercase; font-weight: 800; }
        .school-info p { margin: 2px 0; font-size: 12px; font-weight: 600; }
        
        .report-meta { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 10px; font-weight: 700; }
        .due-date { color: #000; font-size: 13px; }

        .fee-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .fee-table th, .fee-table td { border: 1px solid #000; padding: 6px 4px; text-align: center; }
        .fee-table th { background: #f8f9fa; font-weight: 800; font-size: 10px; text-transform: uppercase; }
        .fee-table td { font-size: 11px; }
        
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        .font-bold { font-weight: 800; }
        .text-red { color: #ef4444; }
        .text-green { color: #10b981; }

        .footer-totals { background: #eee !important; font-weight: 800; }

        .btn-print { 
            position: fixed; top: 20px; right: 20px; padding: 10px 20px; 
            background: #000; color: #fff; border: none; border-radius: 5px; 
            cursor: pointer; font-weight: bold; z-index: 1000;
        }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">PRINT REPORT</button>

<div class="header">
    <img src="<?= $school_logo ?>" class="logo" onerror="this.style.display='none'">
    <div class="school-info">
        <h1><?= htmlspecialchars($school_name) ?></h1>
        <p>Class Fee List</p>
        <p>Phone : <?= htmlspecialchars($school_phone) ?> &nbsp; <?= htmlspecialchars($school_website) ?></p>
        <p>Class <?= htmlspecialchars($class['name']) ?> - Section <?= htmlspecialchars($class['section']) ?></p>
    </div>
    <div style="width: 80px;"></div> <!-- Spacer to center the info -->
</div>

<div class="report-meta">
    <div></div>
    <div class="due-date">Due Date: <strong><?= date('d-M-Y', strtotime($invoice['due_date'])) ?></strong></div>
</div>

<table class="fee-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Reg#</th>
            <th class="text-left">Name</th>
            <th class="text-left">Father Name</th>
            <th>Prev/ Pending Fee</th>
            <th>Current Fee</th>
            <th>Discount /Scholarship</th>
            <th>Total Fee</th>
            <th>Fine</th>
            <th>Total Payable</th>
            <th>Paid Fee</th>
            <th>Paid Fine</th>
            <th>Total Paid</th>
            <th>Remaining / Remitted</th>
            <th>Paid On</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $i = 1;
        $t_prev = $t_curr = $t_disc = $t_fee = $t_fine = $t_payable = $t_paid_fee = $t_paid_fine = $t_total_paid = 0;
        
        foreach($students as $s): 
            $total_fee = ($s['tuition_fee'] + $s['prev_pending']) - $s['discount_amount'];
            $total_payable = $total_fee + $s['fine_amount'];
            $total_paid = $s['paid_amount']; // Assuming paid_amount includes fee + fine for now
            $remaining = $total_payable - $total_paid;
            
            $t_prev += $s['prev_pending'];
            $t_curr += $s['tuition_fee'];
            $t_disc += $s['discount_amount'];
            $t_fee += $total_fee;
            $t_fine += $s['fine_amount'];
            $t_payable += $total_payable;
            $t_paid_fee += $s['paid_amount']; // Simplified
            $t_total_paid += $total_paid;
        ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($s['admission_no']) ?></td>
            <td class="text-left"><?= htmlspecialchars($s['student_name']) ?></td>
            <td class="text-left"><?= htmlspecialchars($s['father_name']) ?></td>
            <td><?= number_format($s['prev_pending'], 0) ?></td>
            <td><?= number_format($s['tuition_fee'], 0) ?></td>
            <td><?= number_format($s['discount_amount'], 0) ?></td>
            <td><?= number_format($total_fee, 0) ?></td>
            <td><?= number_format($s['fine_amount'], 0) ?></td>
            <td class="font-bold"><?= number_format($total_payable, 0) ?></td>
            <td><?= $s['paid_amount'] > 0 ? number_format($s['paid_amount'], 0) : '' ?></td>
            <td></td>
            <td><?= $total_paid > 0 ? number_format($total_paid, 0) : '' ?></td>
            <td class="font-bold"><?= number_format($remaining, 0) ?></td>
            <td><?= $s['payment_date'] ? date('d-M', strtotime($s['payment_date'])) : '' ?></td>
            <td class="<?= $remaining <= 0 ? 'text-green' : 'text-red' ?> font-bold">
                <?= $remaining <= 0 ? 'Paid' : 'Due' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="footer-totals">
            <td colspan="4" class="text-left">Total</td>
            <td><?= number_format($t_prev, 0) ?></td>
            <td><?= number_format($t_curr, 0) ?></td>
            <td><?= number_format($t_disc, 0) ?></td>
            <td><?= number_format($t_fee, 0) ?></td>
            <td><?= number_format($t_fine, 0) ?></td>
            <td><?= number_format($t_payable, 0) ?></td>
            <td><?= number_format($t_paid_fee, 0) ?></td>
            <td>0</td>
            <td><?= number_format($t_total_paid, 0) ?></td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
</table>

</body>
</html>




