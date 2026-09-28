<?php
/**
 * SIAX SMSS - Monthly Fee Receipt
 * Professional receipt with a PAID stamp.
 */
require_once __DIR__ . '/../config/db.php';

$mid = (int)($_GET['monthly_fee_id'] ?? 0);
if (!$mid) die("Invalid Record ID.");

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

$r_q = $conn->query("SELECT sf.*, s.name as student_name, s.father_name, s.admission_no, c.name as class_name, c.section 
                    FROM student_monthly_fees sf 
                    JOIN students s ON sf.student_id = s.id 
                    JOIN classes c ON sf.class_id = c.id 
                    WHERE sf.id = $mid");
$f = $r_q->fetch_assoc();

if (!$f) die("Record not found.");

$total_payable = ($f['tuition_fee'] + $f['prev_pending'] + ($f['admission_fee'] ?? 0) + $f['fine_amount']) - $f['discount_amount'];
$paid_amount = $f['paid_amount'];
$remaining = $total_payable - $paid_amount;

function numberToWords($number) {
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $dictionary  = array(
        0                   => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10                  => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        20                  => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety',
        100                 => 'Hundred', 1000 => 'Thousand', 1000000 => 'Million'
    );
    if (!is_numeric($number)) return false;
    $number = (int) round((float) $number); // Convert decimals like 0.00 to integer
    if ($number < 0) return $negative . numberToWords(abs($number));
    $string = null;
    switch (true) {
        case $number < 21: $string = $dictionary[$number] ?? 'Zero'; break;
        case $number < 100:
            $tens = ((int) ($number / 10)) * 10; $units = $number % 10;
            $string = $dictionary[$tens]; if ($units) $string .= $hyphen . $dictionary[$units];
            break;
        case $number < 1000:
            $hundreds = $number / 100; $remainder = $number % 100;
            $string = $dictionary[(int) $hundreds] . ' ' . $dictionary[100];
            if ($remainder) $string .= $conjunction . numberToWords($remainder);
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000))); $numBaseUnits = (int) ($number / $baseUnit); $remainder = $number % $baseUnit;
            $string = numberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) { $string .= $remainder < 100 ? $conjunction : $separator; $string .= numberToWords($remainder); }
            break;
    }
    return $string;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Receipt - <?= htmlspecialchars($f['student_name']) ?></title>
    <style>
        @page { size: portrait; margin: 10mm; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; margin: 0; padding: 0; color: #000; }
        
        .receipt-container { width: 100%; max-width: 190mm; margin: 0 auto; position: relative; border: 2px solid #000; padding: 25px; box-sizing: border-box; }
        
        .header { display: flex; align-items: center; gap: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { width: 70px; height: 70px; object-fit: contain; }
        .school-info { flex: 1; text-align: center; }
        .school-info h1 { font-size: 22px; margin: 0; font-weight: 800; text-transform: uppercase; }
        .school-info p { margin: 2px 0; font-size: 12px; font-weight: 600; }
        
        .receipt-title { text-align: center; font-size: 18px; font-weight: 800; text-decoration: underline; margin-bottom: 20px; }
        
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .meta-item { display: flex; border-bottom: 1px solid #ccc; padding: 5px 0; }
        .meta-label { font-weight: 700; width: 40%; }
        
        .fee-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .fee-table th, .fee-table td { border: 1px solid #000; padding: 10px; text-align: left; }
        .fee-table th { background: #f0f0f0; font-weight: 800; }
        .fee-table td:last-child { text-align: right; width: 30%; }
        
        .total-row { font-weight: 800; font-size: 15px; background: #f9f9f9; }
        .amt-words { font-size: 13px; font-weight: 700; margin-bottom: 30px; border-bottom: 1px solid #000; padding-bottom: 5px; }
        
        .footer-sigs { display: flex; justify-content: space-between; margin-top: 50px; }
        .sig-box { text-align: center; border-top: 1px solid #000; width: 200px; padding-top: 5px; font-weight: 700; }

        /* PAID STAMP */
        .paid-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            border: 6px solid rgba(16, 185, 129, 0.4);
            color: rgba(16, 185, 129, 0.4);
            font-size: 80px;
            font-weight: 900;
            padding: 10px 30px;
            border-radius: 15px;
            text-transform: uppercase;
            pointer-events: none;
            user-select: none;
            display: <?= $paid_amount > 0 ? 'block' : 'none' ?>;
        }

        .btn-print { 
            position: fixed; top: 20px; right: 20px; padding: 10px 20px; 
            background: #000; color: #fff; border: none; border-radius: 5px; 
            cursor: pointer; font-weight: bold; z-index: 1000;
        }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">PRINT RECEIPT</button>

<div class="receipt-container">
    <div class="paid-stamp">PAID</div>
    
    <div class="header">
        <img src="<?= $school_logo ?>" class="logo" onerror="this.style.display='none'">
        <div class="school-info">
            <h1><?= htmlspecialchars($school_name) ?></h1>
            <p>SKARDU | Phone : <?= htmlspecialchars($school_phone) ?></p>
        </div>
    </div>
    
    <div class="receipt-title">FEE RECEIPT</div>
    
    <div class="meta-grid">
        <div class="meta-item"><span class="meta-label">Student Name:</span><span><?= htmlspecialchars($f['student_name']) ?></span></div>
        <div class="meta-item"><span class="meta-label">Reg#:</span><span><?= htmlspecialchars($f['admission_no']) ?></span></div>
        <div class="meta-item"><span class="meta-label">Father Name:</span><span><?= htmlspecialchars($f['father_name']) ?></span></div>
        <div class="meta-item"><span class="meta-label">Class:</span><span><?= htmlspecialchars($f['class_name'] . ' - ' . $f['section']) ?></span></div>
        <div class="meta-item"><span class="meta-label">Month:</span><span><?= htmlspecialchars($f['month']) ?></span></div>
        <div class="meta-item"><span class="meta-label">Date:</span><span><?= date('d-M-Y', strtotime($f['payment_date'] ?? date('Y-m-d'))) ?></span></div>
    </div>
    
    <table class="fee-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Tuition Fee</td><td><?= number_format($f['tuition_fee'] ?? 0, 0) ?></td></tr>
            <?php if(($f['admission_fee'] ?? 0) > 0): ?><tr><td>Admission Fee</td><td><?= number_format($f['admission_fee'], 0) ?></td></tr><?php endif; ?>
            <?php if(($f['prev_pending'] ?? 0) > 0): ?><tr><td>Previous Month Due</td><td><?= number_format($f['prev_pending'], 0) ?></td></tr><?php endif; ?>
            <?php if(($f['fine_amount'] ?? 0) > 0): ?><tr><td>Fine / Other Charges</td><td><?= number_format($f['fine_amount'], 0) ?></td></tr><?php endif; ?>
            <?php if(($f['discount_amount'] ?? 0) > 0): ?><tr style="color:red"><td>Discount / Scholarship</td><td>-<?= number_format($f['discount_amount'], 0) ?></td></tr><?php endif; ?>
            
            <tr class="total-row">
                <td>Total Payable</td>
                <td><?= number_format($total_payable, 0) ?></td>
            </tr>
            <tr class="total-row" style="color: green;">
                <td>Amount Paid</td>
                <td><?= number_format($paid_amount, 0) ?></td>
            </tr>
            <tr class="total-row" style="color: red;">
                <td>Balance Due</td>
                <td><?= number_format($remaining, 0) ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="amt-words">
        Amount in Words: Rupees <?= numberToWords($paid_amount) ?> Only
    </div>
    
    <div class="footer-sigs">
        <div class="sig-box">Depositor Signature</div>
        <div class="sig-box">Cashier / Office</div>
    </div>
    <div style="text-align:center; font-style:italic; font-size:10px; margin-top:30px; color:#666;">Generated by SIAC TECHNOLOGIES - SmSS</div>
</div>

</body>
</html>




