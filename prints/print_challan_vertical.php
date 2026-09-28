<?php
/**
 * SIAX SMSS - Vertical 3-per-Page Challan Format
 * Dedicated Challan Layout
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$class_id   = (int)($_GET['class_id']   ?? 0);

if (!$invoice_id) {
    die("Invoice ID is required.");
}

$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'School System';
$school_phone = $settings['phone'] ?? '';
$currency = $settings['currency'] ?? 'Rs.';

$school_logo = '';
if (!empty($settings['school_logo']) && file_exists(UPLOAD_PATH . $settings['school_logo'])) {
    $school_logo = BASE_URL . 'uploads/' . $settings['school_logo'];
} else {
    $school_logo = BASE_URL . 'assets/img/logo.png';
}

$show_logo = ($settings['challan_show_logo'] ?? '1') == '1';

// Fetch Invoice Info
$inv_q = $conn->query("SELECT * FROM fee_invoices WHERE id = $invoice_id");
$invoice = $inv_q ? $inv_q->fetch_assoc() : null;
if (!$invoice) {
    die("Invoice not found.");
}

// Fetch Student Fee Records
$where = "WHERE sf.invoice_id = $invoice_id";
if ($class_id) $where .= " AND sf.class_id = $class_id";

$fees_q = $conn->query("SELECT sf.*, s.name as student_name, s.father_name, s.admission_no, s.family_id, s.phone, c.name as class_name, c.section 
                        FROM student_monthly_fees sf 
                        JOIN students s ON sf.student_id = s.id 
                        JOIN classes c ON sf.class_id = c.id 
                        $where ORDER BY s.name");

$all_students = [];
if ($fees_q) {
    while($row = $fees_q->fetch_assoc()) {
        $all_students[] = $row;
    }
}

// Load Challan Settings
$c_main   = $settings['challan_main_heading'] ?? $school_name;
$c_phone  = $settings['challan_phone']        ?? $school_phone;
$c_bank   = $settings['challan_bank_name']    ?? '';
$c_acc_t  = $settings['challan_account_title']?? ($settings['bank_title'] ?? '');
$c_acc_n  = $settings['challan_account_no']   ?? ($settings['bank_account'] ?? '');
$c_copy1  = $settings['challan_copy1_title']  ?? 'School/College Copy';
$c_note   = $settings['challan_instructions'] ?? '1.Please pay the fee before due date. 2.Keep receipt safe.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Vertical Challans - <?= htmlspecialchars($invoice['title']) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }
        .page {
            width: 210mm;
            height: 297mm;
            box-sizing: border-box;
            padding: 8mm 12mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-after: always;
        }
        .challan-slip {
            height: 88mm;
            border: 2px solid #000;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            background: #fff;
            padding: 0;
            position: relative;
        }
        .no-print {
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        .btn-print {
            padding: 10px 30px;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
        }
        @media print {
            .no-print { display: none; }
            body { background: #fff; }
            .page { padding: 6mm 10mm; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">
        &#128438; PRINT VERTICAL CHALLANS
    </button>
</div>

<?php 
$chunks = array_chunk($all_students, 3);

foreach($chunks as $page_students):
?>
<div class="page">
    <?php foreach($page_students as $f): 
        $current_total   = ($f['tuition_fee'] + ($f['admission_fee'] ?? 0));
        $grand_total     = ($current_total + $f['prev_pending']) - $f['discount_amount'];
        $after_due_total = $grand_total + 100;
    ?>
    
    <div class="challan-slip">
        <!-- Header -->
        <div style="border-bottom: 2px solid #000; padding: 6px 12px; display: <?= $show_logo ? 'grid' : 'block' ?>; grid-template-columns: 50px 1fr; gap: 10px; align-items: center;">
            <?php if($show_logo): ?><img src="<?= $school_logo ?>" style="width:40px; height:40px; object-fit:contain;" onerror="this.style.display='none'"><?php endif; ?>
            <div style="text-align:center;">
                <h1 style="font-size: 13px; margin:0; font-weight:900; text-transform:uppercase;"><?= htmlspecialchars($c_main) ?></h1>
                <p style="font-weight:700; margin:2px 0; font-size:9px;">Phone : <?= htmlspecialchars($c_phone) ?></p>
            </div>
        </div>
        
        <!-- Two Column Meta & Particulars -->
        <div style="display:grid; grid-template-columns: 50% 50%; border-bottom: 2px solid #000;">
            <!-- Left Info -->
            <table style="width:100%; border-collapse: collapse; font-size: 9.5px; border-right: 1.5px solid #000;">
                <tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 6px; font-weight:700; width:45%;">Challan No</td><td style="padding:2.5px 6px; font-weight:800;"><?= $f['id'] ?></td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 6px; font-weight:700;">Student Name</td><td style="padding:2.5px 6px; font-weight:800;"><?= htmlspecialchars($f['student_name']) ?></td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 6px; font-weight:700;">Father Name</td><td style="padding:2.5px 6px;"><?= htmlspecialchars($f['father_name']) ?></td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 6px; font-weight:700;">Reg No / Class</td><td style="padding:2.5px 6px; font-weight:800;"><?= htmlspecialchars($f['admission_no'] . ' | ' . $f['class_name'] . '-' . $f['section']) ?></td></tr>
                <tr><td style="padding:2.5px 6px; font-weight:700;">Due Date</td><td style="padding:2.5px 6px; font-weight:800;"><?= date('d-M-Y', strtotime($invoice['due_date'])) ?></td></tr>
            </table>
            <!-- Right Particulars -->
            <table style="width:100%; border-collapse: collapse; font-size: 9.5px;">
                <tr style="font-weight:800; border-bottom: 1px solid #000; background:#f9fafb;"><td style="padding:2.5px 6px; border-right: 1px solid #000;">Description</td><td style="padding:2.5px 6px; text-align:center;">Amount</td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 6px; border-right: 1px solid #000;">Tuition Fee</td><td style="padding:2.5px 6px; text-align:center; font-weight:700;"><?= number_format($f['tuition_fee'], 0) ?></td></tr>
                <?php if($f['admission_fee'] > 0): ?><tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 6px; border-right: 1px solid #000;">Admission Fee</td><td style="padding:2.5px 6px; text-align:center; font-weight:700;"><?= number_format($f['admission_fee'], 0) ?></td></tr><?php endif; ?>
                <?php if($f['prev_pending'] > 0): ?><tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 6px; border-right: 1px solid #000;">Previous Due</td><td style="padding:2.5px 6px; text-align:center; font-weight:700;"><?= number_format($f['prev_pending'], 0) ?></td></tr><?php endif; ?>
                <?php if($f['discount_amount'] > 0): ?><tr style="border-bottom: 1px solid #000; color:#ef4444;"><td style="padding:2.5px 6px; border-right: 1px solid #000; font-weight:700;">Discount</td><td style="padding:2.5px 6px; text-align:center; font-weight:700;">-<?= number_format($f['discount_amount'], 0) ?></td></tr><?php endif; ?>
                <tr style="font-weight:800; background:#f3f4f6;"><td style="padding:3px 6px; border-right: 1px solid #000;">Payable Within Due</td><td style="padding:3px 6px; text-align:center;"><?= number_format($grand_total, 0) ?></td></tr>
            </table>
        </div>

        <!-- Footer -->
        <div style="margin-top: auto; padding: 4px 10px; font-size: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span><strong>Bank:</strong> <?= htmlspecialchars($c_bank) ?> (A/C: <?= htmlspecialchars($c_acc_n) ?>)</span>
                <span><strong>Payable After Due:</strong> Rs. <?= number_format($after_due_total, 0) ?></span>
                <span><strong>Sign / Stamp:</strong> ___________________</span>
            </div>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

</body>
</html>
