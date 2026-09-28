<?php
/**
 * SIAX SMSS - 3-Copy Horizontal Challan Format
 * Bank Copy | School Copy | Student Copy
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

// Load Challan Settings
$c_main   = $settings['challan_main_heading'] ?? $school_name;
$c_phone  = $settings['challan_phone']        ?? $school_phone;
$c_bank   = $settings['challan_bank_name']    ?? '';
$c_acc_t  = $settings['challan_account_title']?? ($settings['bank_title'] ?? '');
$c_acc_n  = $settings['challan_account_no']   ?? ($settings['bank_account'] ?? '');
$c_copy1  = $settings['challan_copy1_title']  ?? 'Bank Copy';
$c_copy2  = $settings['challan_copy2_title']  ?? 'School Copy';
$c_copy3  = $settings['challan_copy3_title']  ?? 'Student Copy';
$c_note   = $settings['challan_instructions'] ?? '1.Please pay the fee before the due date. 2.Keep receipt safe.';
$show_logo = ($settings['challan_show_logo'] ?? '1') == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Challans - <?= htmlspecialchars($invoice['title']) ?></title>
    <style>
        @page {
            size: A4 landscape;
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
            width: 297mm;
            height: 210mm;
            box-sizing: border-box;
            padding: 6mm 8mm;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5mm;
            page-break-after: always;
            align-items: stretch;
        }
        .challan-slip {
            border: 2px solid #000;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            background: #fff;
            padding: 0;
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
            .page { padding: 5mm 6mm; gap: 4mm; height: 198mm; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128438; PRINT 3-COPY CHALLANS</button>
</div>

<?php 
if ($fees_q && $fees_q->num_rows > 0): while($f = $fees_q->fetch_assoc()): 
    $current_total   = ($f['tuition_fee'] + ($f['admission_fee'] ?? 0));
    $grand_total     = ($current_total + $f['prev_pending']) - $f['discount_amount'];
    $after_due_total = $grand_total + 100;
?>
<div class="page">
    <?php 
    $copies = [$c_copy1, $c_copy2, $c_copy3];
    foreach($copies as $copy_title): 
    ?>
    <div class="challan-slip">
        <!-- Header -->
        <div style="border-bottom: 2px solid #000; padding: 8px 10px; display: <?= $show_logo ? 'grid' : 'block' ?>; grid-template-columns: 50px 1fr; gap: 8px; align-items: center;">
            <?php if($show_logo): ?><img src="<?= $school_logo ?>" style="width:44px; height:44px; object-fit:contain;" onerror="this.style.display='none'"><?php endif; ?>
            <div style="text-align:center;">
                <h1 style="font-size: 13px; margin:0; font-weight:900; text-transform:uppercase;"><?= htmlspecialchars($c_main) ?></h1>
                <p style="font-weight:700; margin:2px 0; font-size:9px;">Phone : <?= htmlspecialchars($c_phone) ?></p>
            </div>
        </div>
        <div style="text-align:center; padding:4px; font-weight:900; font-size:11px; border-bottom:2px solid #000; background:#f3f4f6; text-transform:uppercase;"><?= htmlspecialchars($copy_title) ?></div>
        
        <!-- Meta Table -->
        <table style="width:100%; border-collapse: collapse; font-size: 9.5px;">
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000; font-weight:700; width:40%;">Challan Form No</td><td style="padding:3px 5px; font-weight:800;"><?= $f['id'] ?></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000;">Due: <strong><?= date('d-M-y', strtotime($invoice['due_date'])) ?></strong></td><td style="padding:3px 5px;">Valid: <strong><?= date('d-M-y', strtotime($invoice['valid_till'])) ?></strong></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000; font-weight:700;">Student Name</td><td style="padding:3px 5px; font-weight:800;"><?= htmlspecialchars($f['student_name']) ?></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000; font-weight:700;">Father Name</td><td style="padding:3px 5px;"><?= htmlspecialchars($f['father_name']) ?></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000; font-weight:700;">Student Reg No</td><td style="padding:3px 5px; font-weight:800;"><?= htmlspecialchars($f['admission_no']) ?></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000; font-weight:700;">Class</td><td style="padding:3px 5px;"><?= htmlspecialchars($f['class_name'] . ' - ' . $f['section']) ?></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000;">Bank Name</td><td style="padding:3px 5px;"><?= htmlspecialchars($c_bank) ?></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000;">A/C Title</td><td style="padding:3px 5px;"><?= htmlspecialchars($c_acc_t) ?></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000;">Bank A/C No.</td><td style="padding:3px 5px; font-weight:700;"><?= htmlspecialchars($c_acc_n) ?></td></tr>
            <tr style="border-bottom: 1px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000; font-weight:700;">Family Number</td><td style="padding:3px 5px;"><?= htmlspecialchars($f['family_id'] ?? '-') ?></td></tr>
            <tr style="border-bottom: 2px solid #000;"><td style="padding:3px 5px; border-right: 1px solid #000; font-weight:700;">Depositor Contact</td><td style="padding:3px 5px;"><?= htmlspecialchars($f['phone'] ?? '-') ?></td></tr>
        </table>
        
        <!-- Fee Particulars -->
        <table style="width:100%; border-collapse: collapse; font-size: 9.5px;">
            <tr style="font-weight:800; text-align:center; border-bottom: 1px solid #000; background:#f9fafb;"><td style="padding:3px; border-right: 2px solid #000; width:68%;">Description</td><td style="padding:3px;">Amount</td></tr>
            <?php 
            $fee_items_list = json_decode($f['fee_details'], true);
            if (!empty($fee_items_list)):
                foreach ($fee_items_list as $item):
                    if ($item['amount'] > 0):
            ?>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 5px; border-right: 2px solid #000;"><?= htmlspecialchars($item['title']) ?></td><td style="padding:2.5px 5px; text-align:center; font-weight:700;"><?= number_format($item['amount'], 0) ?></td></tr>
            <?php 
                    endif;
                endforeach;
            else:
            ?>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 5px; border-right: 2px solid #000;">Tuition Fee</td><td style="padding:2.5px 5px; text-align:center; font-weight:700;"><?= number_format($f['tuition_fee'], 0) ?></td></tr>
                <?php if($f['admission_fee'] > 0): ?><tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 5px; border-right: 2px solid #000;">Admission Fee</td><td style="padding:2.5px 5px; text-align:center; font-weight:700;"><?= number_format($f['admission_fee'], 0) ?></td></tr><?php endif; ?>
            <?php endif; ?>
            <?php if($f['prev_pending'] > 0): ?>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:2.5px 5px; border-right: 2px solid #000;">Previous Due</td><td style="padding:2.5px 5px; text-align:center; font-weight:700;"><?= number_format($f['prev_pending'], 0) ?></td></tr>
            <?php endif; ?>
            <?php if ($f['discount_amount'] > 0): ?>
                <tr style="border-bottom: 1px solid #000; color: #ef4444;"><td style="padding:2.5px 5px; border-right: 2px solid #000; font-weight:700;">Discount</td><td style="padding:2.5px 5px; text-align:center; font-weight:700;">-<?= number_format($f['discount_amount'], 0) ?></td></tr>
            <?php endif; ?>
        </table>
        
        <!-- Totals & Footers -->
        <div style="margin-top: auto;">
            <table style="width:100%; border-collapse: collapse; font-size: 9.5px; border-top: 2px solid #000;">
                <tr style="border-bottom: 1px solid #000; font-weight:800;">
                    <td style="padding:3px 5px; border-right: 2px solid #000; width:68%;">Within Due Date</td>
                    <td style="padding:3px 5px; text-align:center;"><?= number_format($grand_total, 0) ?></td>
                </tr>
                <tr style="border-bottom: 2px solid #000; font-weight:800;">
                    <td style="padding:3px 5px; border-right: 2px solid #000;">After Due Date</td>
                    <td style="padding:3px 5px; text-align:center;"><?= number_format($after_due_total, 0) ?></td>
                </tr>
            </table>
            <div style="padding:4px; font-size: 9px; border-bottom: 2px solid #000;"><strong>In Words:</strong> Rs. <?= numberToWords($grand_total) ?> Only</div>
            <div style="display: flex; justify-content: space-between; padding: 16px 5px 4px 5px; font-size: 7.5px; font-weight: 700;">
                <div style="border-top: 1px dashed #000; width: 46%; text-align: center; padding-top: 2px;">Bank Cashier Stamp &amp; Sign</div>
                <div style="border-top: 1px dashed #000; width: 46%; text-align: center; padding-top: 2px;">Authorized Sign / Stamp</div>
            </div>
            <div style="padding:3px 5px; font-size: 7.5px; line-height:1.2;"><strong>NOTE:</strong> <?= htmlspecialchars($c_note) ?></div>
            <div style="text-align:center; font-style:italic; font-size:7px; margin-top:2px; color:#777;">Generated by SIAC TECHNOLOGIES - SmSS</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endwhile; endif; ?>

</body>
</html>
