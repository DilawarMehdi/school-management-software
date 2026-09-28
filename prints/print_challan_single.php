<?php
/**
 * SIAX SMSS - Single/Batch Full-Page Challan Slip
 * Dedicated Authentic Challan Style Format
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$class_id   = (int)($_GET['class_id']   ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

if (!$invoice_id) {
    die("Invoice ID is required.");
}

$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'School System';
$school_address = $settings['school_address'] ?? '';
$school_phone = $settings['phone'] ?? '';
$currency = $settings['currency'] ?? 'Rs.';

$school_logo = '';
if (!empty($settings['school_logo']) && file_exists(UPLOAD_PATH . $settings['school_logo'])) {
    $school_logo = BASE_URL . 'uploads/' . $settings['school_logo'];
} else {
    $school_logo = BASE_URL . 'assets/img/logo.png';
}

// Load challan settings
$c_main   = $settings['challan_main_heading'] ?? $school_name;
$c_sub    = $settings['challan_sub_heading']  ?? '';
$c_phone  = $settings['challan_phone']        ?? $school_phone;
$c_bank   = $settings['challan_bank_name']    ?? '';
$c_acc_t  = $settings['challan_account_title']?? ($settings['bank_title'] ?? '');
$c_acc_n  = $settings['challan_account_no']   ?? ($settings['bank_account'] ?? '');
$c_copy1  = $settings['challan_copy1_title']  ?? 'School/College Copy';
$c_note   = $settings['challan_instructions'] ?? 'Please pay before due date.';
$show_logo = ($settings['challan_show_logo'] ?? '1') == '1';

// Fetch Invoice Info
$inv_q = $conn->query("SELECT * FROM fee_invoices WHERE id = $invoice_id");
$invoice = $inv_q ? $inv_q->fetch_assoc() : null;
if (!$invoice) {
    die("Invoice not found.");
}

// Fetch Student Fee Records
$where = "WHERE sf.invoice_id = $invoice_id";
if ($student_id) $where .= " AND sf.student_id = $student_id";
elseif ($class_id) $where .= " AND sf.class_id = $class_id";

$fees_q = $conn->query("SELECT sf.*, s.name as student_name, s.father_name, s.admission_no, s.family_id, s.phone, c.name as class_name, c.section 
                        FROM student_monthly_fees sf 
                        JOIN students s ON sf.student_id = s.id 
                        JOIN classes c ON sf.class_id = c.id 
                        $where ORDER BY s.name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Challan - <?= htmlspecialchars($invoice['title']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; margin: 0; padding: 0; background: #fff; color: #000; }
        .print-container { width: 210mm; margin: 0 auto; padding: 0; }
        
        .slip-wrapper { 
            width: 190mm; 
            min-height: 270mm;
            border: 2px solid #000; 
            padding: 0; 
            margin: 10mm auto; 
            box-sizing: border-box;
            background: #fff;
            display: flex;
            flex-direction: column;
            font-size: 13px;
        }
        
        .table-meta, .table-fees { width: 100%; border-collapse: collapse; }
        .table-meta td { border: 1px solid #000; padding: 7px 12px; font-size: 13px; }
        .table-meta td:first-child { width: 40%; font-weight: 700; background: #f9fafb; }
        
        .table-fees th, .table-fees td { border: 1px solid #000; padding: 8px 12px; text-align: left; font-size: 13px; }
        .table-fees th { background: #f3f4f6; font-weight: 800; }
        .table-fees td:last-child, .table-fees th:last-child { text-align: center; width: 30%; font-weight: 700; }
        .table-fees tr.total-row td { font-weight: 800; font-size: 15px; background: #f9fafb; }
        
        .amt-words { font-size: 13px; padding: 10px 14px; border-bottom: 2px solid #000; font-weight: 600; }
        .disclaimer { font-size: 11px; line-height: 1.5; color: #000; margin-top: auto; padding: 10px 14px; }
        
        .no-print { background: #f8f9fa; padding: 15px; text-align: center; border-bottom: 1px solid #ddd; }
        .btn-print { padding: 12px 36px; background: #000; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 15px; }

        @media print {
            .no-print { display: none; }
            .print-container { width: 100%; padding: 0; margin: 0; }
            .slip-wrapper { page-break-after: always; margin: 0 auto; border-width: 2px; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128438; PRINT CHALLAN</button>
</div>

<div class="print-container">
<?php if ($fees_q && $fees_q->num_rows > 0): while($f = $fees_q->fetch_assoc()):
    $grand_total     = ($f['tuition_fee'] + ($f['admission_fee'] ?? 0) + $f['prev_pending']) - $f['discount_amount'];
    $after_due_total = $grand_total + 100;
?>

<!-- CHALLAN FORMAT -->
<div class="slip-wrapper">
    <div style="border-bottom:2px solid #000; padding:15px; display:<?= $show_logo ? 'grid' : 'block' ?>; grid-template-columns:80px 1fr; gap:15px; align-items:center;">
        <?php if($show_logo): ?><img src="<?= $school_logo ?>" style="width:70px;height:70px;object-fit:contain;" onerror="this.style.display='none'"><?php endif; ?>
        <div style="text-align:center;">
            <h1 style="font-size:22px;margin:0;font-weight:900;text-transform:uppercase;"><?= htmlspecialchars($c_main) ?></h1>
            <?php if($c_sub): ?><p style="font-size:14px;margin:4px 0;font-weight:700;"><?= htmlspecialchars($c_sub) ?></p><?php endif; ?>
            <p style="font-weight:700;margin:4px 0;font-size:13px;">Phone Number : <?= htmlspecialchars($c_phone) ?></p>
        </div>
    </div>
    <div style="text-align:center;padding:8px;font-weight:900;font-size:14px;border-bottom:2px solid #000;background:#f3f4f6;"><?= htmlspecialchars($c_copy1) ?></div>
    <table class="table-meta">
        <tr><td>Challan Form No</td><td><strong style="font-size:16px;"><?= $f['id'] ?></strong></td></tr>
        <tr><td>Due Date: <strong><?= date('d-M-Y', strtotime($invoice['due_date'])) ?></strong></td><td>Valid Till: <strong><?= date('d-M-Y', strtotime($invoice['valid_till'])) ?></strong></td></tr>
        <tr><td>Student Name</td><td><strong><?= htmlspecialchars($f['student_name']) ?></strong></td></tr>
        <tr><td>Father Name</td><td><?= htmlspecialchars($f['father_name']) ?></td></tr>
        <tr><td>Student Reg No</td><td><strong><?= htmlspecialchars($f['admission_no']) ?></strong></td></tr>
        <tr><td>Class</td><td><?= htmlspecialchars($f['class_name'] . ' - ' . $f['section']) ?></td></tr>
        <tr><td>Bank Name</td><td><?= htmlspecialchars($c_bank) ?></td></tr>
        <tr><td>A/C Title</td><td><?= htmlspecialchars($c_acc_t) ?></td></tr>
        <tr><td>Bank A/C No.</td><td><strong><?= htmlspecialchars($c_acc_n) ?></strong></td></tr>
        <tr><td>Family Number</td><td><?= htmlspecialchars($f['family_id'] ?? '-') ?></td></tr>
        <tr><td>Depositor NIC Number</td><td>-</td></tr>
        <tr style="border-bottom:2px solid #000;"><td>Depositor Phone Number</td><td><?= htmlspecialchars($f['phone'] ?? '-') ?></td></tr>
    </table>
    <table class="table-fees">
        <thead><tr><th>Description</th><th>Amount (<?= $currency ?>)</th></tr></thead>
        <tbody>
            <?php 
            $fee_items_list = json_decode($f['fee_details'], true);
            if (!empty($fee_items_list)):
                foreach ($fee_items_list as $item):
                    if ($item['amount'] > 0):
            ?>
                <tr><td><?= htmlspecialchars($item['title']) ?></td><td><?= number_format($item['amount'], 0) ?></td></tr>
            <?php 
                    endif;
                endforeach;
            else:
            ?>
                <tr><td>Tuition Fee</td><td><?= number_format($f['tuition_fee'], 0) ?></td></tr>
                <?php if($f['admission_fee'] > 0): ?><tr><td>Admission Fee</td><td><?= number_format($f['admission_fee'], 0) ?></td></tr><?php endif; ?>
            <?php endif; ?>
            <?php if($f['prev_pending'] > 0): ?>
                <tr><td>Previous Month Due</td><td><?= number_format($f['prev_pending'], 0) ?></td></tr>
            <?php endif; ?>
            <?php if ($f['discount_amount'] > 0): ?>
                <tr style="color: #ef4444;"><td style="font-weight:700;">Discount / Scholarship</td><td style="text-align:center; font-weight:700;">-<?= number_format($f['discount_amount'], 0) ?></td></tr>
            <?php endif; ?>
            <tr class="total-row"><td>Fee Within Due Date (Till <?= date('d-M-Y', strtotime($invoice['due_date'])) ?>)</td><td><?= number_format($grand_total, 0) ?></td></tr>
            <tr class="total-row" style="color:#ef4444;"><td>Fee After Due Date (From <?= date('d-M-Y', strtotime($invoice['due_date'] . ' +1 day')) ?>)</td><td><?= number_format($after_due_total, 0) ?></td></tr>
        </tbody>
    </table>
    
    <div style="margin-top: auto;">
        <div class="amt-words"><strong>Amount in words :</strong> Rupees <?= numberToWords($grand_total) ?> Only</div>
        <div style="display: flex; justify-content: space-between; padding: 30px 20px 10px 20px; font-size: 11px; font-weight: 700;">
            <div style="border-top: 1.5px dashed #000; width: 40%; text-align: center; padding-top: 5px;">Bank Cashier Stamp &amp; Sign</div>
            <div style="border-top: 1.5px dashed #000; width: 40%; text-align: center; padding-top: 5px;">Authorized Sign / Stamp</div>
        </div>
        <div class="disclaimer"><strong>NOTE:</strong> <?= nl2br(htmlspecialchars($c_note)) ?></div>
        <div style="text-align:center; font-style:italic; font-size:10px; margin-top:4px; color:#666; margin-bottom: 10px;">Generated by SIAC TECHNOLOGIES - SmSS</div>
    </div>
</div>

<?php endwhile; else: ?>
    <div style="text-align:center; padding:50px; font-size:18px;">No fee records found for this invoice.</div>
<?php endif; ?>
</div>

</body>
</html>
