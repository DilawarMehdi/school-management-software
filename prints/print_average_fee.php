<?php
/**
 * SIAX SMSS - Average Fee Report (Print View)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'SIAX SMSS';
$school_logo = $settings['school_logo'] ?? '';

$current_month = $_GET['month'] ?? date('F');
$sel_criteria_id = (int)($_GET['criteria_id'] ?? 0);
$session_year = $settings['session_year'] ?? '2025-2026';

// Fetch classes and calculate averages
$report_q = $conn->query("
    SELECT 
        c.name as class_name, 
        c.section, 
        COUNT(se.student_id) as student_count,
        COALESCE(fd.standard_fee, 0) as criteria_fee,
        COALESCE(SUM(fp.paid_amount), 0) as total_paid
    FROM classes c
    LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.session_year = '$session_year' AND se.status = 'Active'
    LEFT JOIN students s ON se.student_id = s.id AND s.status = 'Active'
    LEFT JOIN fee_criteria_details fd ON c.id = fd.class_id AND fd.criteria_id = $sel_criteria_id
    LEFT JOIN (
        SELECT student_id, SUM(paid_amount) as paid_amount 
        FROM fee_payments 
        WHERE month = '$current_month' AND session_year = '$session_year'
        GROUP BY student_id
    ) fp ON s.id = fp.student_id
    GROUP BY c.id, c.name, c.section, fd.standard_fee
    ORDER BY c.name, c.section
");

$report_data = [];
$total_students = 0;
$total_fees = 0;
if ($report_q) {
    while ($row = $report_q->fetch_assoc()) {
        $row['average'] = ($sel_criteria_id > 0) ? $row['criteria_fee'] : ($row['student_count'] > 0 ? $row['total_paid'] / $row['student_count'] : 0);
        $report_data[] = $row;
        $total_students += $row['student_count'];
        $total_fees += ($sel_criteria_id > 0) ? ($row['criteria_fee'] * $row['student_count']) : $row['total_paid'];
    }
}
$grand_average = $total_students > 0 ? $total_fees / $total_students : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Average Fee Report - <?= $current_month ?> <?= date('Y') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; padding: 30px; color: #000; }
        .print-header { display: flex; align-items: center; justify-content: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .school-info { text-align: center; }
        .school-info h1 { font-size: 24px; margin-bottom: 5px; }
        .school-info p { font-size: 12px; color: #444; }
        
        .report-title { text-align: center; font-size: 18px; font-weight: 700; margin: 20px 0; border: 1px solid #000; padding: 8px; background: #eee; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 10px; font-size: 12px; }
        .data-table th { background: #f2f2f2; text-align: left; }
        .label-sub { display: block; font-size: 10px; color: #666; font-weight: 700; margin-bottom: 2px; }
        
        .footer-row { font-weight: 800; background: #eee; }
        .footer-summary { text-align: right; }

        .no-print { margin-bottom: 20px; text-align: right; }
        .btn { padding: 8px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; border: none; font-size: 14px; background: #1a5276; color: #fff; }

        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">Print Report</button>
    </div>

    <div class="print-header">
        <div class="school-info">
            <h1><?= htmlspecialchars($school_name) ?></h1>
            <p><?= htmlspecialchars($settings['school_address'] ?? '') ?></p>
        </div>
    </div>

    <div class="report-title">Average Fee Summary Report - <?= $current_month ?> <?= date('Y') ?></div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Class</th>
                <th>Section</th>
                <th>No-of-Students</th>
                <th>Average-Fee</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data as $row): ?>
                <tr>
                    <td><span class="label-sub">Class</span><?= htmlspecialchars($row['class_name']) ?></td>
                    <td><span class="label-sub">Section</span><?= htmlspecialchars($row['section']) ?></td>
                    <td><span class="label-sub">No-of-Students</span><?= $row['student_count'] ?></td>
                    <td><span class="label-sub">Average-Fee</span><?= number_format($row['average'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="footer-row">
                <td colspan="2">Total Students</td>
                <td><?= $total_students ?></td>
                <td class="footer-summary">
                    <div>Total Fee: <?= number_format($total_fees, 2) ?> /-</div>
                    <div>Average Fee: <?= number_format($grand_average, 2) ?></div>
                </td>
            </tr>
        </tfoot>
    </table>
</body>
</html>




