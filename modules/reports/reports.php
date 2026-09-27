<?php
$page_title = 'Reports';
$active_page = 'reports';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','accountant');

$currency = $settings['currency'] ?? 'PKR';
$session_year = $settings['session_year'] ?? '2025-2026';
$principal = $settings['principal_name'] ?? 'Principal';

$report_type = $_GET['report'] ?? 'yearly';

// --- Student Report ---
$student_report = null;
if ($report_type === 'students') {
    $filter_class = (int)($_GET['class_id'] ?? 0);
    $filter_status = $conn->real_escape_string($_GET['status'] ?? 'Active');
    $where = "1=1";
    if ($filter_class > 0) $where .= " AND se.class_id=$filter_class";
    if ($filter_status) $where .= " AND se.status='$filter_status'";
    $student_report = $conn->query("
        SELECT s.*, se.roll_no as enrollment_roll_no, se.status as enrollment_status, c.name as class_name, c.section 
        FROM student_enrollments se 
        JOIN students s ON se.student_id = s.id 
        LEFT JOIN classes c ON se.class_id = c.id 
        WHERE $where AND se.session_year = '$session_year' AND s.status = 'Active' 
        ORDER BY c.name, c.section, (se.roll_no+0), s.name
    ");
}

// --- Fee Report ---
$fee_report = null; $fee_total = 0;
if ($report_type === 'fees') {
    $fr_month = $conn->real_escape_string($_GET['fr_month'] ?? '');
    $fr_year  = $conn->real_escape_string($_GET['fr_year'] ?? date('Y'));
    $where = "fp.session_year='$session_year' AND YEAR(fp.payment_date)='$fr_year'";
    if ($fr_month) $where .= " AND fp.month='$fr_month'";
    $fee_report = $conn->query("SELECT fp.*, s.name as student_name, s.admission_no, c.name as class_name FROM fee_payments fp JOIN students s ON fp.student_id=s.id LEFT JOIN classes c ON s.class_id=c.id WHERE $where ORDER BY fp.payment_date DESC");
    $ft = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as t FROM fee_payments fp WHERE $where");
    if ($ft) $fee_total = $ft->fetch_assoc()['t'];
}

// --- Attendance Report ---
$att_report = null;
if ($report_type === 'attendance') {
    $att_class = (int)($_GET['att_class'] ?? 0);
    $att_from  = $conn->real_escape_string($_GET['att_from'] ?? date('Y-m-01'));
    $att_to    = $conn->real_escape_string($_GET['att_to'] ?? date('Y-m-d'));
    if ($att_class > 0) {
        $att_report = $conn->query("SELECT s.name, s.roll_no, COUNT(CASE WHEN a.status='Present' THEN 1 END) as present, COUNT(CASE WHEN a.status='Absent' THEN 1 END) as absent, COUNT(CASE WHEN a.status='Late' THEN 1 END) as late, COUNT(a.id) as total FROM students s LEFT JOIN attendance a ON a.student_id=s.id AND a.class_id=$att_class AND a.date BETWEEN '$att_from' AND '$att_to' WHERE s.class_id=$att_class AND s.status='Active' GROUP BY s.id ORDER BY s.roll_no,s.name");
    }
}

// --- Expenses Report ---
$exp_report = null; $exp_total = 0;
if ($report_type === 'expenses') {
    $exp_cat   = (int)($_GET['exp_cat'] ?? 0);
    $exp_from  = $conn->real_escape_string($_GET['exp_from'] ?? date('Y-m-01'));
    $exp_to    = $conn->real_escape_string($_GET['exp_to'] ?? date('Y-m-d'));
    $where = "e.expense_date BETWEEN '$exp_from' AND '$exp_to'";
    if ($exp_cat > 0) $where .= " AND e.category_id=$exp_cat";
    $exp_report = $conn->query("SELECT e.*, c.name as category_name FROM expenses e LEFT JOIN expense_categories c ON e.category_id=c.id WHERE $where ORDER BY e.expense_date DESC");
    $et = $conn->query("SELECT SUM(amount) as t FROM expenses e WHERE $where");
    if ($et) $exp_total = $et->fetch_assoc()['t'] ?? 0;
}

// --- Build Available Sessions List ---
$available_sessions = [];
$session_list_str = $settings['session_list'] ?? '';
if ($session_list_str) {
    $available_sessions = array_filter(array_map('trim', explode(',', $session_list_str)));
}
// Also pull distinct sessions from fee_invoices for completeness
$db_sessions_q = $conn->query("SELECT DISTINCT session_year FROM fee_invoices ORDER BY session_year DESC");
if ($db_sessions_q) {
    while ($sr = $db_sessions_q->fetch_assoc()) {
        if (!in_array($sr['session_year'], $available_sessions)) {
            $available_sessions[] = $sr['session_year'];
        }
    }
}
// Also pull from student_enrollments
$db_enroll_q = $conn->query("SELECT DISTINCT session_year FROM student_enrollments ORDER BY session_year DESC");
if ($db_enroll_q) {
    while ($sr = $db_enroll_q->fetch_assoc()) {
        if (!in_array($sr['session_year'], $available_sessions)) {
            $available_sessions[] = $sr['session_year'];
        }
    }
}
if (!in_array($session_year, $available_sessions)) {
    $available_sessions[] = $session_year;
}
// Sort sessions descending
rsort($available_sessions);

// --- Yearly Comprehensive Report (Live Auto-Sync — Full DB) ---
$yearly_data = null;
if ($report_type === 'yearly') {
    $sel_session = $conn->real_escape_string($_GET['session'] ?? $session_year);
    // Derive year(s) from the session string (e.g. '2026-2027' -> use 2026 and 2027)
    $session_parts = explode('-', $sel_session);
    $sel_year = (int)($session_parts[0] ?? date('Y'));
    $sel_year2 = isset($session_parts[1]) ? (int)$session_parts[1] : $sel_year;
    // For short format like '2027-28', expand to full year
    if ($sel_year2 < 100) $sel_year2 = (int)(substr((string)$sel_year, 0, 2) . str_pad((string)$sel_year2, 2, '0', STR_PAD_LEFT));
    
    // ═══════════════════════════════════════════════════════════
    // 1. Fee Revenue Metrics (BOTH direct payments + invoice payments)
    // ═══════════════════════════════════════════════════════════
    
    // A) Direct fee_payments
    $direct_stats = ['collected' => 0, 'discount' => 0, 'fine' => 0, 'receipts' => 0];
    $dq = $conn->query("
        SELECT COALESCE(SUM(paid_amount),0) as collected,
               COALESCE(SUM(discount),0) as discount,
               COALESCE(SUM(fine),0) as fine,
               COUNT(*) as receipts
        FROM fee_payments
        WHERE (YEAR(payment_date) = $sel_year OR YEAR(payment_date) = $sel_year2) AND session_year = '$sel_session'
    ");
    if ($dq && $dq->num_rows > 0) $direct_stats = $dq->fetch_assoc();
    
    // B) Monthly invoice payments (fee_payment_history)
    $invoice_stats = ['collected' => 0, 'fine_paid' => 0, 'receipts' => 0];
    $iq = $conn->query("
        SELECT COALESCE(SUM(fph.amount_paid),0) as collected,
               COALESCE(SUM(fph.fine_paid),0) as fine_paid,
               COUNT(fph.id) as receipts
        FROM fee_payment_history fph
        JOIN student_monthly_fees smf ON fph.monthly_fee_id = smf.id
        JOIN fee_invoices fi ON smf.invoice_id = fi.id
        WHERE (YEAR(fph.payment_date) = $sel_year OR YEAR(fph.payment_date) = $sel_year2) AND fi.session_year = '$sel_session'
    ");
    if ($iq && $iq->num_rows > 0) $invoice_stats = $iq->fetch_assoc();
    
    // Combined Fee Stats
    $fee_stats = [
        'total_collected' => (float)$direct_stats['collected'] + (float)$invoice_stats['collected'],
        'total_discount'  => (float)$direct_stats['discount'],
        'total_fine'      => (float)$direct_stats['fine'] + (float)$invoice_stats['fine_paid'],
        'total_receipts'  => (int)$direct_stats['receipts'] + (int)$invoice_stats['receipts'],
        'direct_collected'  => (float)$direct_stats['collected'],
        'invoice_collected' => (float)$invoice_stats['collected'],
    ];
    
    // C) Total Invoiced Amount (what was billed)
    $total_invoiced_amt = 0;
    $inv_q = $conn->query("
        SELECT COALESCE(SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount), 0) as total_billed
        FROM student_monthly_fees smf
        JOIN fee_invoices fi ON smf.invoice_id = fi.id
        WHERE fi.session_year = '$sel_session'
    ");
    if ($inv_q && $r = $inv_q->fetch_assoc()) $total_invoiced_amt = (float)$r['total_billed'];
    
    // D) Total Discounts/Concessions given via invoices
    $total_discount_given = 0;
    $disc_q = $conn->query("
        SELECT COALESCE(SUM(smf.discount_amount), 0) as total_disc
        FROM student_monthly_fees smf
        JOIN fee_invoices fi ON smf.invoice_id = fi.id
        WHERE fi.session_year = '$sel_session'
    ");
    if ($disc_q && $r = $disc_q->fetch_assoc()) $total_discount_given = (float)$r['total_disc'];
    $total_discount_given += (float)$direct_stats['discount'];
    
    // E) Advance Fees collected
    $total_advance = 0;
    $adv_q = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM advance_fees WHERE YEAR(applied_at) = $sel_year OR YEAR(applied_at) IS NULL");
    if ($adv_q && $r = $adv_q->fetch_assoc()) $total_advance = (float)$r['t'];
    
    // Collection Rate
    $collection_rate = $total_invoiced_amt > 0 ? round(($fee_stats['invoice_collected'] / $total_invoiced_amt) * 100, 1) : 100;
    
    // ═══════════════════════════════════════════════════════════
    // 2. Operational Expenses
    // ═══════════════════════════════════════════════════════════
    $exp_stats = ['total_exp' => 0, 'exp_count' => 0];
    $eq = $conn->query("
        SELECT COALESCE(SUM(amount),0) as total_exp, COUNT(*) as exp_count
        FROM expenses
        WHERE (YEAR(expense_date) = $sel_year OR YEAR(expense_date) = $sel_year2)
    ");
    if ($eq && $eq->num_rows > 0) $exp_stats = $eq->fetch_assoc();
    
    // 3. Staff Salary Payroll
    $salary_stats = ['total_salary' => 0, 'slips_count' => 0];
    $sq = $conn->query("
        SELECT COALESCE(SUM(net_salary),0) as total_salary, COUNT(*) as slips_count
        FROM salary_slips
        WHERE (YEAR(payment_date) = $sel_year OR YEAR(payment_date) = $sel_year2 OR salary_month LIKE '%$sel_year%' OR salary_month LIKE '%$sel_year2%') AND status='Paid'
    ");
    if ($sq && $sq->num_rows > 0) $salary_stats = $sq->fetch_assoc();
    
    // ═══════════════════════════════════════════════════════════
    // 4. Totals & Financial Balance
    // ═══════════════════════════════════════════════════════════
    $total_income = (float)$fee_stats['total_collected'];
    $total_outflow = (float)$exp_stats['total_exp'] + (float)$salary_stats['total_salary'];
    $net_balance = $total_income - $total_outflow;
    $profit_margin = $total_income > 0 ? round(($net_balance / $total_income) * 100, 1) : 0;
    
    // ═══════════════════════════════════════════════════════════
    // 5. Month-by-Month 12-Month Flow (Jan - Dec) — SYNCED
    // ═══════════════════════════════════════════════════════════
    $monthly_flow = [];
    $months_names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    for ($m = 1; $m <= 12; $m++) {
        $m_fee = 0; $m_exp = 0; $m_sal = 0;
        
        // Direct fee_payments
        $mf_q = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as amt FROM fee_payments WHERE MONTH(payment_date) = $m AND YEAR(payment_date) = $sel_year");
        if ($mf_q) $m_fee = (float)$mf_q->fetch_assoc()['amt'];
        
        // Invoice payments (fee_payment_history)
        $mi_q = $conn->query("SELECT COALESCE(SUM(fph.amount_paid),0) as amt FROM fee_payment_history fph JOIN student_monthly_fees smf ON fph.monthly_fee_id = smf.id JOIN fee_invoices fi ON smf.invoice_id = fi.id WHERE MONTH(fph.payment_date) = $m AND YEAR(fph.payment_date) = $sel_year");
        if ($mi_q) $m_fee += (float)$mi_q->fetch_assoc()['amt'];
        
        // Expenses
        $me_q = $conn->query("SELECT COALESCE(SUM(amount),0) as amt FROM expenses WHERE MONTH(expense_date) = $m AND YEAR(expense_date) = $sel_year");
        if ($me_q) $m_exp = (float)$me_q->fetch_assoc()['amt'];
        
        // Salaries
        $ms_q = $conn->query("SELECT COALESCE(SUM(net_salary),0) as amt FROM salary_slips WHERE MONTH(payment_date) = $m AND YEAR(payment_date) = $sel_year AND status='Paid'");
        if ($ms_q) $m_sal = (float)$ms_q->fetch_assoc()['amt'];
        
        $m_outflow = $m_exp + $m_sal;
        $m_net = $m_fee - $m_outflow;
        
        $monthly_flow[] = [
            'month_num' => $m,
            'month_name' => $months_names[$m-1],
            'fee_income' => $m_fee,
            'expenses' => $m_exp,
            'salaries' => $m_sal,
            'total_outflow' => $m_outflow,
            'net_balance' => $m_net
        ];
    }
    
    // ═══════════════════════════════════════════════════════════
    // 6. Expense Category Breakdown
    // ═══════════════════════════════════════════════════════════
    $cat_breakdown = [];
    $cb_q = $conn->query("
        SELECT COALESCE(c.name, 'General / Miscellaneous') as category_name,
               COALESCE(SUM(e.amount),0) as cat_amount,
               COUNT(e.id) as trans_count
        FROM expenses e
        LEFT JOIN expense_categories c ON e.category_id = c.id
        WHERE YEAR(e.expense_date) = $sel_year
        GROUP BY c.id
        ORDER BY cat_amount DESC
    ");
    if ($cb_q) {
        while ($cr = $cb_q->fetch_assoc()) {
            $cr['pct'] = $exp_stats['total_exp'] > 0 ? round(($cr['cat_amount'] / $exp_stats['total_exp']) * 100, 1) : 0;
            $cat_breakdown[] = $cr;
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // 7. Academic & Operational Stats
    // ═══════════════════════════════════════════════════════════
    $academic_stats = [];
    
    $st_q = $conn->query("SELECT COUNT(DISTINCT student_id) as c FROM student_enrollments WHERE session_year = '$sel_session' AND status = 'Active'");
    $academic_stats['enrolled_students'] = $st_q ? $st_q->fetch_assoc()['c'] : 0;
    
    $adm_q = $conn->query("SELECT COUNT(*) as c FROM students WHERE YEAR(admission_date) = $sel_year");
    $academic_stats['new_admissions'] = $adm_q ? $adm_q->fetch_assoc()['c'] : 0;
    
    $cl_q = $conn->query("SELECT COUNT(*) as c FROM classes");
    $academic_stats['total_classes'] = $cl_q ? $cl_q->fetch_assoc()['c'] : 0;
    
    $stf_q = $conn->query("SELECT COUNT(*) as c FROM staff_members WHERE status = 'Active'");
    $academic_stats['total_staff'] = $stf_q ? $stf_q->fetch_assoc()['c'] : 0;
    
    $crt_q = $conn->query("SELECT COUNT(*) as c FROM certificates WHERE YEAR(issue_date) = $sel_year");
    $academic_stats['certificates_issued'] = $crt_q ? $crt_q->fetch_assoc()['c'] : 0;
    
    $at_q = $conn->query("SELECT COUNT(CASE WHEN status='Present' THEN 1 END) as p, COUNT(*) as t FROM attendance WHERE YEAR(date) = $sel_year");
    if ($at_q && $at_row = $at_q->fetch_assoc()) {
        $academic_stats['attendance_rate'] = $at_row['t'] > 0 ? round(($at_row['p'] / $at_row['t']) * 100, 1) : 0;
    } else {
        $academic_stats['attendance_rate'] = 0;
    }
    
    $ex_q = $conn->query("SELECT COUNT(DISTINCT exam_type_id) as ex_count FROM marks WHERE exam_type_id > 0");
    $academic_stats['exams_count'] = $ex_q ? $ex_q->fetch_assoc()['ex_count'] : 0;
    
    $mk_q = $conn->query("SELECT COUNT(CASE WHEN marks_obtained >= pass_marks AND is_absent=0 THEN 1 END) as passed, COUNT(*) as total FROM marks WHERE exam_type_id > 0");
    if ($mk_q && $mk_row = $mk_q->fetch_assoc()) {
        $academic_stats['exam_pass_rate'] = $mk_row['total'] > 0 ? round(($mk_row['passed'] / $mk_row['total']) * 100, 1) : 0;
    } else {
        $academic_stats['exam_pass_rate'] = 0;
    }
    
    // ═══════════════════════════════════════════════════════════
    // 8. Payment Modes Breakdown (Direct + Invoice combined)
    // ═══════════════════════════════════════════════════════════
    $modes_breakdown = [];
    $mb_q = $conn->query("
        SELECT payment_mode, COUNT(*) as cnt, SUM(paid_amount) as total 
        FROM fee_payments 
        WHERE YEAR(payment_date) = $sel_year AND session_year = '$sel_session'
        GROUP BY payment_mode
    ");
    $modes_map = [];
    if ($mb_q) {
        while ($mr = $mb_q->fetch_assoc()) {
            $modes_map[$mr['payment_mode']] = ['cnt' => (int)$mr['cnt'], 'total' => (float)$mr['total']];
        }
    }
    // Add invoice payments as "Invoice" mode
    if ((float)$invoice_stats['collected'] > 0) {
        $modes_map['Monthly Invoice'] = ['cnt' => (int)$invoice_stats['receipts'], 'total' => (float)$invoice_stats['collected']];
    }
    foreach ($modes_map as $mode => $data) {
        $modes_breakdown[] = ['payment_mode' => $mode, 'cnt' => $data['cnt'], 'total' => $data['total']];
    }
    
    // ═══════════════════════════════════════════════════════════
    // 9. Class-wise Fee Collection Summary (from invoices)
    // ═══════════════════════════════════════════════════════════
    $class_fee_summary = [];
    $cfs_q = $conn->query("
        SELECT c.name as class_name, c.section,
               COUNT(DISTINCT smf.student_id) as student_count,
               COALESCE(SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount), 0) as total_billed,
               COALESCE(SUM(smf.paid_amount), 0) as total_paid,
               COALESCE(SUM(smf.discount_amount), 0) as total_discount
        FROM student_monthly_fees smf
        JOIN fee_invoices fi ON smf.invoice_id = fi.id
        JOIN classes c ON smf.class_id = c.id
        WHERE fi.session_year = '$sel_session'
        GROUP BY smf.class_id
        ORDER BY c.name, c.section
    ");
    if ($cfs_q) {
        while ($cr = $cfs_q->fetch_assoc()) {
            $cr['pending'] = (float)$cr['total_billed'] - (float)$cr['total_paid'];
            $cr['rate'] = (float)$cr['total_billed'] > 0 ? round(((float)$cr['total_paid'] / (float)$cr['total_billed']) * 100, 1) : 100;
            $class_fee_summary[] = $cr;
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // 10. Top Fee Defaulters (highest pending balance)
    // ═══════════════════════════════════════════════════════════
    $top_defaulters = [];
    $td_q = $conn->query("
        SELECT s.name, s.admission_no, s.father_name, c.name as class_name, c.section,
               SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount) as total_billed,
               SUM(smf.paid_amount) as total_paid
        FROM student_monthly_fees smf
        JOIN fee_invoices fi ON smf.invoice_id = fi.id
        JOIN students s ON smf.student_id = s.id
        LEFT JOIN classes c ON smf.class_id = c.id
        WHERE fi.session_year = '$sel_session'
        GROUP BY smf.student_id
        HAVING (SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount) - SUM(smf.paid_amount)) > 0
        ORDER BY (SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount) - SUM(smf.paid_amount)) DESC
        LIMIT 10
    ");
    if ($td_q) {
        while ($dr = $td_q->fetch_assoc()) {
            $dr['pending'] = (float)$dr['total_billed'] - (float)$dr['total_paid'];
            $top_defaulters[] = $dr;
        }
    }
    
    // Max monthly income for visual chart scaling
    $max_monthly = 1;
    foreach ($monthly_flow as $mf) {
        if ($mf['fee_income'] > $max_monthly) $max_monthly = $mf['fee_income'];
        if ($mf['total_outflow'] > $max_monthly) $max_monthly = $mf['total_outflow'];
    }
}

// --- Defaulters List Report ---
$defaulters_data = [];
if ($report_type === 'defaulters') {
    $sel_session = $conn->real_escape_string($_GET['session'] ?? $session_year);
    $sel_month = $conn->real_escape_string($_GET['month'] ?? '');
    
    $where_clause = "fi.session_year = '$sel_session'";
    if (!empty($sel_month)) {
        $where_clause .= " AND fi.month = '$sel_month'";
    }

    $q = $conn->query("
        SELECT s.id, s.name, s.admission_no, s.father_name, s.phone, s.father_phone, 
               c.name as class_name, c.section,
               SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount) as total_billed,
               SUM(smf.paid_amount) as total_paid,
               GROUP_CONCAT(CASE WHEN smf.status = 'Pending' OR smf.status = 'Partial' THEN fi.month END SEPARATOR ', ') as unpaid_months
        FROM student_monthly_fees smf
        JOIN fee_invoices fi ON smf.invoice_id = fi.id
        JOIN students s ON smf.student_id = s.id
        LEFT JOIN classes c ON smf.class_id = c.id
        WHERE $where_clause
        GROUP BY smf.student_id
        HAVING (SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount) - SUM(smf.paid_amount)) > 0
        ORDER BY (SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount) - SUM(smf.paid_amount)) DESC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $r['pending'] = (float)$r['total_billed'] - (float)$r['total_paid'];
            $defaulters_data[] = $r;
        }
    }
}

// --- Top Paying Students Report ---
$top_payers_data = [];
if ($report_type === 'top_payers') {
    $sel_session = $conn->real_escape_string($_GET['session'] ?? $session_year);
    
    // Get students who have paid the most (from invoices)
    $q = $conn->query("
        SELECT s.id, s.name, s.admission_no, s.father_name, c.name as class_name, c.section,
               SUM(smf.tuition_fee + smf.admission_fee + smf.prev_pending - smf.discount_amount) as total_billed,
               SUM(smf.paid_amount) as total_paid,
               MAX(fph.payment_date) as last_payment_date
        FROM student_monthly_fees smf
        JOIN fee_invoices fi ON smf.invoice_id = fi.id
        JOIN students s ON smf.student_id = s.id
        LEFT JOIN classes c ON smf.class_id = c.id
        LEFT JOIN fee_payment_history fph ON fph.monthly_fee_id = smf.id
        WHERE fi.session_year = '$sel_session'
        GROUP BY smf.student_id
        HAVING total_paid > 0
        ORDER BY total_paid DESC
        LIMIT 100
    ");
    
    // Get advance fees sum per student for the session
    $adv_q = $conn->query("SELECT student_id, SUM(amount) as adv_total FROM advance_fees GROUP BY student_id");
    $adv_map = [];
    if ($adv_q) {
        while ($ar = $adv_q->fetch_assoc()) $adv_map[$ar['student_id']] = (float)$ar['adv_total'];
    }
    
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $r['advance'] = $adv_map[$r['id']] ?? 0;
            $r['total_contribution'] = (float)$r['total_paid'] + $r['advance'];
            $top_payers_data[] = $r;
        }
    }
    
    // Re-sort by total contribution (paid + advance)
    usort($top_payers_data, function($a, $b) {
        return $b['total_contribution'] <=> $a['total_contribution'];
    });
}

// Classes (Naturally sorted)
$classes_arr = get_all_classes($conn);

$months_list = ['January','February','March','April','May','June','July','August','September','October','November','December'];

// Available Years for filter
$years_list = [];
$y_curr = (int)date('Y');
for ($y = $y_curr + 1; $y >= $y_curr - 5; $y--) {
    $years_list[] = $y;
}
?>

<style>
/* Yearly Report Styling */
.yearly-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
    color: #fff;
    padding: 24px;
    border-radius: 14px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(49, 46, 129, 0.2);
}
.yearly-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.live-sync-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.4);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.live-sync-dot {
    width: 8px; height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulseSync 1.5s infinite;
}
@keyframes pulseSync {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.5; }
}

.financial-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.fin-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px;
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.fin-card .top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.fin-card .icon-box {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
}
.fin-card h3 {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 800;
}
.fin-card p {
    margin: 0;
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 600;
}

.monthly-table th {
    background: var(--bg-secondary);
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.progress-bar-container {
    width: 100%;
    height: 8px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
}

@media print {
    .no-print, .sb-brand, .sidebar, .top-header, .filter-bar, .card-grid.no-print {
        display: none !important;
    }
    body, .main-wrapper, .page-content, .main-container {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .yearly-hero {
        background: #1e1b4b !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        box-shadow: none !important;
    }
    .fin-card, .card {
        box-shadow: none !important;
        border: 1px solid #cbd5e1 !important;
    }
}
</style>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-chart-line" style="color:#6366f1;"></i> Comprehensive Reports</h1>
    <p>Generate, review, and print live auto-synced institutional &amp; financial reports</p>
  </div>
  <?php if($report_type): ?>
    <button class="btn btn-primary no-print" onclick="window.print()">
      <i class="fa-solid fa-print"></i> Print Official Report
    </button>
  <?php endif; ?>
</div>

<!-- Report Type Navigation -->
<div class="card-grid no-print" style="grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));margin-bottom:24px;gap:12px;">
  <a href="?report=yearly" class="stat-card" style="text-decoration:none;<?= $report_type==='yearly'?'border:2px solid #6366f1;background:rgba(99,102,241,0.06);':'' ?>">
    <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:#6366f1;"><i class="fa-solid fa-chart-pie"></i></div>
    <div class="stat-info">
      <h3 style="font-size:0.95rem;">Yearly Report</h3>
      <p style="font-size:0.75rem;">Annual Auto-Audit</p>
    </div>
  </a>
  <a href="?report=fees" class="stat-card" style="text-decoration:none;<?= $report_type==='fees'?'border:2px solid var(--accent);':'' ?>">
    <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-info"><h3>Fees</h3><p>Collections</p></div>
  </a>
  <a href="?report=defaulters" class="stat-card" style="text-decoration:none;<?= $report_type==='defaulters'?'border:2px solid #ef4444;background:rgba(239,68,68,0.06);':'' ?>">
    <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div class="stat-info"><h3>Defaulters</h3><p>Pending Dues</p></div>
  </a>
  <a href="?report=top_payers" class="stat-card" style="text-decoration:none;<?= $report_type==='top_payers'?'border:2px solid #10b981;background:rgba(16,185,129,0.06);':'' ?>">
    <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:#10b981;"><i class="fa-solid fa-crown"></i></div>
    <div class="stat-info"><h3>Top Payers</h3><p>Fully Paid List</p></div>
  </a>
  <a href="?report=expenses" class="stat-card" style="text-decoration:none;<?= $report_type==='expenses'?'border:2px solid var(--accent);':'' ?>">
    <div class="stat-icon"><i class="fa-solid fa-wallet text-danger"></i></div>
    <div class="stat-info"><h3>Expenses</h3><p>Outflow</p></div>
  </a>
  <a href="?report=students" class="stat-card" style="text-decoration:none;<?= $report_type==='students'?'border:2px solid var(--accent);':'' ?>">
    <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
    <div class="stat-info"><h3>Students</h3><p>Enrollment</p></div>
  </a>
  <a href="?report=attendance" class="stat-card" style="text-decoration:none;<?= $report_type==='attendance'?'border:2px solid var(--accent);':'' ?>">
    <div class="stat-icon"><i class="fa-solid fa-calendar-check text-success"></i></div>
    <div class="stat-info"><h3>Attendance</h3><p>Summary</p></div>
  </a>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!-- YEARLY REPORT / ANNUAL AUTO-AUDIT -->
<!-- ════════════════════════════════════════════════════════════════ -->
<?php if ($report_type === 'yearly'): ?>

  <!-- Year / Session Filter Bar -->
  <div class="filter-bar no-print" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <form method="GET" style="display:flex; align-items:center; gap:12px; margin:0;">
      <input type="hidden" name="report" value="yearly">
      <div style="display:flex; align-items:center; gap:6px;">
        <label style="font-size:0.82rem; font-weight:700; color:var(--text-primary);"><i class="fa-solid fa-calendar"></i> Year:</label>
        <select name="year" class="form-control" style="width:110px;" onchange="this.form.submit()">
          <?php foreach($years_list as $y): ?>
          <option value="<?= $y ?>" <?= $sel_year == $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex; align-items:center; gap:6px;">
        <label style="font-size:0.82rem; font-weight:700; color:var(--text-primary);"><i class="fa-solid fa-graduation-cap"></i> Session:</label>
        <input type="text" name="session" class="form-control" value="<?= htmlspecialchars($sel_session) ?>" style="width:140px;" placeholder="e.g. 2025-2026">
      </div>
      <button type="submit" class="btn btn-primary" style="padding:7px 16px;">
        <i class="fa-solid fa-arrows-rotate"></i> Sync Report
      </button>
    </form>

    <div>
      <span class="live-sync-badge">
        <span class="live-sync-dot"></span> Auto-Synced with Database &bull; <?= date('d M Y, h:i A') ?>
      </span>
    </div>
  </div>

  <!-- Hero Banner -->
  <div class="yearly-hero">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
      <div>
        <span style="background:rgba(255,255,255,0.15); padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px;">
          Institutional Annual Audit
        </span>
        <h2 style="margin:8px 0 4px 0; font-size:1.6rem; font-weight:900;">
          Yearly Executive Report &mdash; <?= $sel_year ?> (<?= htmlspecialchars($sel_session) ?>)
        </h2>
        <p style="margin:0; opacity:0.85; font-size:0.85rem;">
          <?= htmlspecialchars($school_name) ?> &bull; Consolidated Financial Revenue, Expenditure, Payroll &amp; Operations.
        </p>
      </div>
      <div style="text-align:right;">
        <div style="font-size:0.75rem; opacity:0.75; text-transform:uppercase; font-weight:700;">Net Annual Balance</div>
        <div style="font-size:2rem; font-weight:900; color:<?= $net_balance >= 0 ? '#34d399' : '#f87171' ?>;">
          <?= $net_balance >= 0 ? '+' : '' ?><?= $currency ?> <?= number_format($net_balance, 2) ?>
        </div>
        <div style="font-size:0.8rem; font-weight:700;">
          <span class="badge" style="background:<?= $net_balance >= 0 ? '#10b981' : '#ef4444' ?>; color:#fff;">
            <?= $net_balance >= 0 ? 'Surplus (+'.$profit_margin.'%)' : 'Deficit ('.$profit_margin.'%)' ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════
       FINANCIAL KPI CARDS — 8 Cards (2 Rows)
  ════════════════════════════════════════════ -->
  <div class="financial-grid" style="grid-template-columns: repeat(4, 1fr);">
    <!-- Revenue -->
    <div class="fin-card">
      <div class="top-row">
        <p>Total Revenue (All Sources)</p>
        <div class="icon-box" style="background:#dcfce7; color:#16a34a;"><i class="fa-solid fa-sack-dollar"></i></div>
      </div>
      <h3 style="color:#16a34a;"><?= $currency ?> <?= number_format($total_income, 2) ?></h3>
      <div style="font-size:0.72rem; color:var(--text-muted);">
        <strong><?= number_format($fee_stats['total_receipts']) ?></strong> paid receipts &bull; Fine: <?= number_format((float)$fee_stats['total_fine']) ?>
      </div>
      <div style="font-size:0.68rem; color:var(--text-muted); border-top:1px solid var(--border); padding-top:6px; margin-top:4px;">
        Direct: <?= $currency ?> <?= number_format($fee_stats['direct_collected'],0) ?> &bull; Invoice: <?= $currency ?> <?= number_format($fee_stats['invoice_collected'],0) ?>
      </div>
    </div>

    <!-- Expenses -->
    <div class="fin-card">
      <div class="top-row">
        <p>Operational Expenses</p>
        <div class="icon-box" style="background:#fee2e2; color:#dc2626;"><i class="fa-solid fa-receipt"></i></div>
      </div>
      <h3 style="color:#dc2626;"><?= $currency ?> <?= number_format((float)$exp_stats['total_exp'], 2) ?></h3>
      <div style="font-size:0.72rem; color:var(--text-muted);">
        <strong><?= number_format($exp_stats['exp_count']) ?></strong> logged expense vouchers
      </div>
    </div>

    <!-- Staff Payroll -->
    <div class="fin-card">
      <div class="top-row">
        <p>Staff Payroll / Salaries</p>
        <div class="icon-box" style="background:#fef3c7; color:#d97706;"><i class="fa-solid fa-user-tie"></i></div>
      </div>
      <h3 style="color:#d97706;"><?= $currency ?> <?= number_format((float)$salary_stats['total_salary'], 2) ?></h3>
      <div style="font-size:0.72rem; color:var(--text-muted);">
        <strong><?= number_format($salary_stats['slips_count']) ?></strong> paid salary slips
      </div>
    </div>

    <!-- Total Outflow -->
    <div class="fin-card">
      <div class="top-row">
        <p>Total Institutional Outflow</p>
        <div class="icon-box" style="background:#f3e8ff; color:#9333ea;"><i class="fa-solid fa-wallet"></i></div>
      </div>
      <h3 style="color:#9333ea;"><?= $currency ?> <?= number_format($total_outflow, 2) ?></h3>
      <div style="font-size:0.72rem; color:var(--text-muted);">
        Expenses + Staff Salaries combined
      </div>
    </div>
  </div>

  <!-- Row 2: Collection Rate, Invoiced, Discounts, Advance -->
  <div class="financial-grid" style="grid-template-columns: repeat(4, 1fr); margin-top:-8px;">
    <!-- Collection Rate -->
    <div class="fin-card">
      <div class="top-row">
        <p>Invoice Collection Rate</p>
        <div class="icon-box" style="background:<?= $collection_rate >= 80 ? '#dcfce7' : ($collection_rate >= 50 ? '#fef3c7' : '#fee2e2') ?>; color:<?= $collection_rate >= 80 ? '#16a34a' : ($collection_rate >= 50 ? '#d97706' : '#dc2626') ?>;"><i class="fa-solid fa-bullseye"></i></div>
      </div>
      <h3 style="color:<?= $collection_rate >= 80 ? '#16a34a' : ($collection_rate >= 50 ? '#d97706' : '#dc2626') ?>;"><?= $collection_rate ?>%</h3>
      <div style="background:rgba(0,0,0,0.06); border-radius:10px; height:8px; overflow:hidden; margin-top:4px;">
        <div style="width:<?= min(100, $collection_rate) ?>%; height:100%; border-radius:10px; background:<?= $collection_rate >= 80 ? '#10b981' : ($collection_rate >= 50 ? '#f59e0b' : '#ef4444') ?>;"></div>
      </div>
    </div>

    <!-- Total Invoiced -->
    <div class="fin-card">
      <div class="top-row">
        <p>Total Invoiced (Billed)</p>
        <div class="icon-box" style="background:#e0e7ff; color:#4f46e5;"><i class="fa-solid fa-file-invoice"></i></div>
      </div>
      <h3 style="color:#4f46e5;"><?= $currency ?> <?= number_format($total_invoiced_amt, 0) ?></h3>
      <div style="font-size:0.72rem; color:var(--text-muted);">
        Outstanding: <strong style="color:#ef4444;"><?= $currency ?> <?= number_format(max(0, $total_invoiced_amt - $fee_stats['invoice_collected']), 0) ?></strong>
      </div>
    </div>

    <!-- Discounts -->
    <div class="fin-card">
      <div class="top-row">
        <p>Discounts / Concessions</p>
        <div class="icon-box" style="background:#e0f2fe; color:#0284c7;"><i class="fa-solid fa-tags"></i></div>
      </div>
      <h3 style="color:#0284c7;"><?= $currency ?> <?= number_format($total_discount_given, 0) ?></h3>
      <div style="font-size:0.72rem; color:var(--text-muted);">
        Total waivers &amp; scholarships given
      </div>
    </div>

    <!-- Advance Fees -->
    <div class="fin-card">
      <div class="top-row">
        <p>Advance Fees Collected</p>
        <div class="icon-box" style="background:#faf5ff; color:#7c3aed;"><i class="fa-solid fa-forward-fast"></i></div>
      </div>
      <h3 style="color:#7c3aed;"><?= $currency ?> <?= number_format($total_advance, 0) ?></h3>
      <div style="font-size:0.72rem; color:var(--text-muted);">
        Pre-paid months by students
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════
       REVENUE VS OUTFLOW VISUAL BAR CHART
  ════════════════════════════════════════════ -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h3 style="margin:0;"><i class="fa-solid fa-chart-bar text-primary"></i> Revenue vs Outflow — Monthly Visual (<?= $sel_year ?>)</h3>
    </div>
    <div style="padding:20px; display:flex; flex-direction:column; gap:14px;">
      <?php foreach ($monthly_flow as $mf): 
        $income_pct = $max_monthly > 0 ? round(($mf['fee_income'] / $max_monthly) * 100) : 0;
        $outflow_pct = $max_monthly > 0 ? round(($mf['total_outflow'] / $max_monthly) * 100) : 0;
      ?>
      <div>
        <div style="display:flex; justify-content:space-between; font-size:0.78rem; font-weight:700; margin-bottom:4px;">
          <span><?= $mf['month_name'] ?></span>
          <span style="color:<?= $mf['net_balance'] >= 0 ? '#10b981' : '#ef4444' ?>;">
            Net: <?= $mf['net_balance'] >= 0 ? '+' : '' ?><?= number_format($mf['net_balance'],0) ?>
          </span>
        </div>
        <div style="display:flex; gap:4px; align-items:center;">
          <div style="flex:1; background:rgba(0,0,0,0.04); border-radius:6px; height:16px; overflow:hidden; position:relative;">
            <div style="width:<?= max(2, $income_pct) ?>%; height:100%; background:linear-gradient(90deg,#10b981,#34d399); border-radius:6px; transition:width .3s;"></div>
          </div>
          <span style="font-size:0.68rem; width:70px; text-align:right; color:#10b981; font-weight:700;"><?= number_format($mf['fee_income'],0) ?></span>
        </div>
        <div style="display:flex; gap:4px; align-items:center; margin-top:2px;">
          <div style="flex:1; background:rgba(0,0,0,0.04); border-radius:6px; height:10px; overflow:hidden; position:relative;">
            <div style="width:<?= max(2, $outflow_pct) ?>%; height:100%; background:linear-gradient(90deg,#ef4444,#f87171); border-radius:6px; transition:width .3s;"></div>
          </div>
          <span style="font-size:0.68rem; width:70px; text-align:right; color:#ef4444; font-weight:600;"><?= number_format($mf['total_outflow'],0) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <div style="display:flex; gap:20px; font-size:0.75rem; font-weight:700; padding-top:8px; border-top:1px solid var(--border);">
        <span><span style="display:inline-block;width:12px;height:12px;background:#10b981;border-radius:3px;margin-right:4px;vertical-align:middle;"></span> Revenue (Income)</span>
        <span><span style="display:inline-block;width:12px;height:8px;background:#ef4444;border-radius:3px;margin-right:4px;vertical-align:middle;"></span> Outflow (Expenses + Salaries)</span>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Month-by-Month 12 Month Table -->
    <div class="col-md-8 mb-4">
      <div class="card" style="height:100%;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
          <h3 style="margin:0;"><i class="fa-solid fa-calendar-days text-primary"></i> 12-Month Financial Flow (<?= $sel_year ?>)</h3>
          <span style="font-size:0.75rem; color:var(--text-muted);">Revenue vs Outflows</span>
        </div>
        <div class="table-wrapper">
          <table class="data-table monthly-table">
            <thead>
              <tr>
                <th>Month</th>
                <th>Fee Income</th>
                <th>Expenses</th>
                <th>Staff Salaries</th>
                <th>Total Outflow</th>
                <th>Net Balance</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($monthly_flow as $mf): ?>
              <tr>
                <td><strong><?= $mf['month_name'] ?> <?= $sel_year ?></strong></td>
                <td style="color:#16a34a; font-weight:700;"><?= $currency ?> <?= number_format($mf['fee_income'], 2) ?></td>
                <td style="color:#dc2626;"><?= $currency ?> <?= number_format($mf['expenses'], 2) ?></td>
                <td style="color:#d97706;"><?= $currency ?> <?= number_format($mf['salaries'], 2) ?></td>
                <td style="color:#9333ea; font-weight:600;"><?= $currency ?> <?= number_format($mf['total_outflow'], 2) ?></td>
                <td>
                  <strong style="color:<?= $mf['net_balance'] >= 0 ? '#16a34a' : '#dc2626' ?>;">
                    <?= $mf['net_balance'] >= 0 ? '+' : '' ?><?= $currency ?> <?= number_format($mf['net_balance'], 2) ?>
                  </strong>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background:var(--bg-secondary); font-weight:800;">
                <td>TOTAL ANNUAL</td>
                <td style="color:#16a34a;"><?= $currency ?> <?= number_format($total_income, 2) ?></td>
                <td style="color:#dc2626;"><?= $currency ?> <?= number_format((float)$exp_stats['total_exp'], 2) ?></td>
                <td style="color:#d97706;"><?= $currency ?> <?= number_format((float)$salary_stats['total_salary'], 2) ?></td>
                <td style="color:#9333ea;"><?= $currency ?> <?= number_format($total_outflow, 2) ?></td>
                <td style="color:<?= $net_balance >= 0 ? '#16a34a' : '#dc2626' ?>;">
                  <?= $net_balance >= 0 ? '+' : '' ?><?= $currency ?> <?= number_format($net_balance, 2) ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Right Side: Academic & Operational Milestones -->
    <div class="col-md-4 mb-4">
      <!-- Operational Highlights -->
      <div class="card mb-4">
        <div class="card-header">
          <h3 style="margin:0;"><i class="fa-solid fa-school text-primary"></i> Academic &amp; Operations</h3>
        </div>
        <div style="padding:18px;">
          <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
            <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fa-solid fa-users text-primary me-2"></i> Enrolled Students</span>
            <strong style="font-size:1.05rem;"><?= number_format($academic_stats['enrolled_students']) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
            <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fa-solid fa-user-plus text-success me-2"></i> New Admissions in <?= $sel_year ?></span>
            <strong style="font-size:1.05rem; color:#10b981;"><?= number_format($academic_stats['new_admissions']) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
            <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fa-solid fa-chalkboard text-info me-2"></i> Total Classes &amp; Sections</span>
            <strong style="font-size:1.05rem;"><?= number_format($academic_stats['total_classes']) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
            <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fa-solid fa-user-tie text-warning me-2"></i> Active Staff Members</span>
            <strong style="font-size:1.05rem;"><?= number_format($academic_stats['total_staff']) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
            <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fa-solid fa-award text-purple me-2"></i> Certificates Issued</span>
            <strong style="font-size:1.05rem;"><?= number_format($academic_stats['certificates_issued']) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
            <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fa-solid fa-calendar-check text-success me-2"></i> Avg Attendance Rate</span>
            <strong style="font-size:1.05rem; color:#10b981;"><?= $academic_stats['attendance_rate'] ?>%</strong>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0;">
            <span style="font-size:0.85rem; color:var(--text-muted);"><i class="fa-solid fa-trophy text-amber me-2"></i> Exam Pass Rate</span>
            <strong style="font-size:1.05rem; color:#6366f1;"><?= $academic_stats['exam_pass_rate'] ?>%</strong>
          </div>
        </div>
      </div>

      <!-- Expense Categories Breakdown -->
      <div class="card mb-4">
        <div class="card-header">
          <h3 style="margin:0;"><i class="fa-solid fa-chart-pie text-danger"></i> Expense Categories</h3>
        </div>
        <div style="padding:16px;">
          <?php if(!empty($cat_breakdown)): ?>
            <?php foreach($cat_breakdown as $cb): ?>
            <div style="margin-bottom:12px;">
              <div style="display:flex; justify-content:space-between; font-size:0.82rem; margin-bottom:4px;">
                <strong><?= htmlspecialchars($cb['category_name']) ?></strong>
                <span><?= $currency ?> <?= number_format($cb['cat_amount'], 2) ?> (<?= $cb['pct'] ?>%)</span>
              </div>
              <div class="progress-bar-container">
                <div style="width:<?= min(100, $cb['pct']) ?>%; background:linear-gradient(90deg,#ef4444,#f59e0b);"></div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-muted" style="margin:0; font-size:0.85rem;">No category-wise expenses logged for this year.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Payment Modes Breakdown -->
      <div class="card">
        <div class="card-header">
          <h3 style="margin:0;"><i class="fa-solid fa-credit-card text-primary"></i> Payment Modes</h3>
        </div>
        <div style="padding:16px;">
          <?php if(!empty($modes_breakdown)): ?>
            <?php 
              $mode_colors = ['Cash'=>'#10b981','Online'=>'#3b82f6','Cheque'=>'#f59e0b','Monthly Invoice'=>'#6366f1','Bank Transfer'=>'#0ea5e9'];
              foreach($modes_breakdown as $mb): 
                $mode_pct = $total_income > 0 ? round(($mb['total'] / $total_income) * 100, 1) : 0;
                $mcolor = $mode_colors[$mb['payment_mode']] ?? '#64748b';
            ?>
            <div style="margin-bottom:12px;">
              <div style="display:flex; justify-content:space-between; font-size:0.82rem; margin-bottom:4px;">
                <strong><i class="fa-solid fa-circle" style="font-size:0.5rem;color:<?= $mcolor ?>;vertical-align:middle;margin-right:6px;"></i><?= htmlspecialchars($mb['payment_mode']) ?></strong>
                <span><?= $currency ?> <?= number_format($mb['total'],0) ?> (<?= $mode_pct ?>%)</span>
              </div>
              <div class="progress-bar-container">
                <div style="width:<?= min(100, max(3, $mode_pct)) ?>%; background:<?= $mcolor ?>;border-radius:10px;"></div>
              </div>
              <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;"><?= number_format($mb['cnt']) ?> transactions</div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-muted" style="margin:0; font-size:0.85rem;">No payment data available.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <!-- ════════════════════════════════════════════
       CLASS-WISE FEE COLLECTION SUMMARY
  ════════════════════════════════════════════ -->
  <?php if (!empty($class_fee_summary)): ?>
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0;"><i class="fa-solid fa-school text-primary"></i> Class-wise Fee Collection Summary (<?= htmlspecialchars($sel_session) ?>)</h3>
      <span style="font-size:0.75rem; color:var(--text-muted);"><?= count($class_fee_summary) ?> classes</span>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Class</th>
            <th style="text-align:center;">Students</th>
            <th style="text-align:right;">Total Billed</th>
            <th style="text-align:right;">Collected</th>
            <th style="text-align:right;">Discount</th>
            <th style="text-align:right;">Pending</th>
            <th style="text-align:center; width:140px;">Collection %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($class_fee_summary as $cfs): ?>
          <tr>
            <td><strong><?= htmlspecialchars($cfs['class_name'] . ' ' . ($cfs['section'] ?? '')) ?></strong></td>
            <td style="text-align:center;"><?= $cfs['student_count'] ?></td>
            <td style="text-align:right;"><?= $currency ?> <?= number_format($cfs['total_billed'],0) ?></td>
            <td style="text-align:right; color:#10b981; font-weight:700;"><?= $currency ?> <?= number_format($cfs['total_paid'],0) ?></td>
            <td style="text-align:right; color:#0284c7;"><?= $currency ?> <?= number_format($cfs['total_discount'],0) ?></td>
            <td style="text-align:right; color:<?= $cfs['pending'] > 0 ? '#ef4444' : '#10b981' ?>; font-weight:700;"><?= $currency ?> <?= number_format($cfs['pending'],0) ?></td>
            <td style="text-align:center;">
              <div style="display:flex; align-items:center; gap:8px; justify-content:center;">
                <div style="flex:1; max-width:80px; background:rgba(0,0,0,0.06); border-radius:10px; height:8px; overflow:hidden;">
                  <div style="width:<?= min(100, $cfs['rate']) ?>%; height:100%; border-radius:10px; background:<?= $cfs['rate'] >= 80 ? '#10b981' : ($cfs['rate'] >= 50 ? '#f59e0b' : '#ef4444') ?>;"></div>
                </div>
                <strong style="font-size:0.8rem; color:<?= $cfs['rate'] >= 80 ? '#10b981' : ($cfs['rate'] >= 50 ? '#f59e0b' : '#ef4444') ?>;"><?= $cfs['rate'] ?>%</strong>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ════════════════════════════════════════════
       TOP FEE DEFAULTERS
  ════════════════════════════════════════════ -->
  <?php if (!empty($top_defaulters)): ?>
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0;"><i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i> Top Fee Defaulters — Outstanding Balances</h3>
      <span class="badge" style="background:#fee2e2; color:#dc2626; font-size:0.72rem;"><?= count($top_defaulters) ?> students with pending dues</span>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:35px;">#</th>
            <th>Adm#</th>
            <th>Student Name</th>
            <th>Father Name</th>
            <th>Class</th>
            <th style="text-align:right;">Total Billed</th>
            <th style="text-align:right;">Total Paid</th>
            <th style="text-align:right;">Outstanding</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_defaulters as $di => $df): ?>
          <tr>
            <td style="font-weight:700; color:var(--text-muted);"><?= $di + 1 ?></td>
            <td style="font-size:0.82rem;"><?= htmlspecialchars($df['admission_no']) ?></td>
            <td><strong><?= htmlspecialchars($df['name']) ?></strong></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($df['father_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars(($df['class_name'] ?? '-') . ' ' . ($df['section'] ?? '')) ?></td>
            <td style="text-align:right;"><?= $currency ?> <?= number_format($df['total_billed'],0) ?></td>
            <td style="text-align:right; color:#10b981; font-weight:600;"><?= $currency ?> <?= number_format($df['total_paid'],0) ?></td>
            <td style="text-align:right; color:#ef4444; font-weight:800; font-size:1rem;"><?= $currency ?> <?= number_format($df['pending'],0) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Print Footer Sign-off (Shown on print) -->
  <div style="margin-top:40px; display:flex; justify-content:space-between; padding-top:20px; border-top:2px solid #cbd5e1;">
    <div style="text-align:center; width:200px;">
      <div style="height:35px;"></div>
      <div style="border-top:1.5px solid #334155; padding-top:6px; font-weight:700; font-size:0.85rem;">Accountant Signature</div>
    </div>
    <div style="text-align:center; width:200px;">
      <div style="height:35px;"></div>
      <div style="border-top:1.5px solid #334155; padding-top:6px; font-weight:700; font-size:0.85rem;">Auditor Signature</div>
    </div>
    <div style="text-align:center; width:200px;">
      <div style="height:35px;"></div>
      <div style="border-top:1.5px solid #334155; padding-top:6px; font-weight:700; font-size:0.85rem;"><?= htmlspecialchars($principal ?: 'Principal Signature') ?></div>
    </div>
  </div>

<?php elseif ($report_type === 'students'): ?>
<!-- Student Report -->
<div class="filter-bar no-print">
  <form method="GET" class="filter-bar" style="margin-bottom:0">
    <input type="hidden" name="report" value="students">
    <select name="class_id" class="form-control">
      <option value="">All Classes</option>
      <?php foreach($classes_arr as $c): ?><option value="<?= $c['id'] ?>" <?= ($_GET['class_id']??0)==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'].' '.$c['section']) ?></option><?php endforeach; ?>
    </select>
    <select name="status" class="form-control">
      <?php foreach(['Active','Left','Transferred','Passed',''] as $st): ?><option value="<?= $st ?>" <?= ($_GET['status']??'Active')===$st?'selected':'' ?>><?= $st ?: 'All Status' ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Generate</button>
  </form>
</div>
<?php if($student_report && $student_report->num_rows > 0): ?>
<div class="card">
  <div class="card-header"><h3>Student Report — <?= date('d M Y') ?></h3><span class="text-muted"><?= $student_report->num_rows ?> records</span></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Adm#</th><th>Name</th><th>Father</th><th>Class</th><th>Roll</th><th>Gender</th><th>Phone</th><th>Status</th></tr></thead>
      <tbody>
      <?php while($s=$student_report->fetch_assoc()): ?>
      <tr><td><?= htmlspecialchars($s['admission_no']) ?></td><td><?= htmlspecialchars($s['name']) ?></td><td><?= htmlspecialchars($s['father_name']??'-') ?></td><td><?= htmlspecialchars(($s['class_name']??'-').' '.($s['section']??'')) ?></td><td><?= htmlspecialchars($s['enrollment_roll_no']??'-') ?></td><td><?= $s['gender'] ?></td><td><?= htmlspecialchars($s['phone']?:($s['father_phone']??'-')) ?></td><td><span class="badge badge-<?= $s['enrollment_status']==='Active'?'success':'warning' ?>"><?= $s['enrollment_status'] ?></span></td></tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-user-graduate"></i></div><h3>No students found</h3></div>
<?php endif; ?>

<?php elseif ($report_type === 'fees'): ?>
<!-- Fee Report -->
<div class="filter-bar no-print">
  <form method="GET" class="filter-bar" style="margin-bottom:0">
    <input type="hidden" name="report" value="fees">
    <select name="fr_month" class="form-control">
      <option value="">All Months</option>
      <?php foreach($months_list as $m): ?><option value="<?= $m ?>" <?= ($_GET['fr_month']??'')===$m?'selected':'' ?>><?= $m ?></option><?php endforeach; ?>
    </select>
    <input type="number" name="fr_year" class="form-control" value="<?= htmlspecialchars($_GET['fr_year']??date('Y')) ?>" placeholder="Year" min="2020" max="2030" style="width:100px">
    <button type="submit" class="btn btn-primary">Generate</button>
  </form>
</div>
<div class="stat-cards" style="grid-template-columns:repeat(2,1fr)">
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-info"><h3><?= $currency ?> <?= number_format($fee_total) ?></h3><p>Total Collected</p></div></div>
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-receipt"></i></div><div class="stat-info"><h3><?= $fee_report ? $fee_report->num_rows : 0 ?></h3><p>Total Transactions</p></div></div>
</div>
<?php if($fee_report && $fee_report->num_rows>0): ?>
<div class="card">
  <div class="card-header"><h3>Fee Collection Report</h3><span class="text-muted"><?= date('d M Y') ?></span></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Receipt#</th><th>Student</th><th>Class</th><th>Month</th><th>Amount</th><th>Mode</th><th>Date</th></tr></thead>
      <tbody>
      <?php while($p=$fee_report->fetch_assoc()): ?>
      <tr><td><?= htmlspecialchars($p['receipt_no']) ?></td><td><?= htmlspecialchars($p['student_name']) ?></td><td><?= htmlspecialchars($p['class_name']??'-') ?></td><td><?= htmlspecialchars($p['month']?:'-') ?></td><td class="text-success"><strong><?= $currency ?> <?= number_format($p['paid_amount'],2) ?></strong></td><td><?= $p['payment_mode'] ?></td><td><?= date('d M Y', strtotime($p['payment_date'])) ?></td></tr>
      <?php endwhile; ?>
      </tbody>
      <tfoot><tr><th colspan="4" style="text-align:right">Total:</th><th class="text-success"><?= $currency ?> <?= number_format($fee_total,2) ?></th><th colspan="2"></th></tr></tfoot>
    </table>
  </div>
</div>
<?php else: ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-money-bill-wave"></i></div><h3>No payments found</h3></div>
<?php endif; ?>

<?php elseif($report_type === 'attendance'): ?>
<!-- Attendance Report -->
<div class="filter-bar no-print">
  <form method="GET" class="filter-bar" style="margin-bottom:0">
    <input type="hidden" name="report" value="attendance">
    <select name="att_class" class="form-control">
      <option value="">-- Select Class --</option>
      <?php foreach($classes_arr as $c): ?><option value="<?= $c['id'] ?>" <?= ($_GET['att_class']??0)==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'].' '.$c['section']) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="att_from" class="form-control" value="<?= htmlspecialchars($_GET['att_from']??date('Y-m-01')) ?>">
    <input type="date" name="att_to" class="form-control" value="<?= htmlspecialchars($_GET['att_to']??date('Y-m-d')) ?>">
    <button type="submit" class="btn btn-primary">Generate</button>
  </form>
</div>
<?php if($att_report && $att_report->num_rows>0): ?>
<div class="card">
  <div class="card-header"><h3>Attendance Report</h3><span class="text-muted"><?= htmlspecialchars(($_GET['att_from']??'').' to '.($_GET['att_to']??'')) ?></span></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Roll</th><th>Student</th><th style="color:#34d399">Present</th><th style="color:#ef4444">Absent</th><th style="color:#f59e0b">Late</th><th>Total Days</th><th>%</th></tr></thead>
      <tbody>
      <?php while($r=$att_report->fetch_assoc()):
        $total=$r['total']; $pct=$total>0?round(($r['present']/$total)*100):0;
        $color=$pct>=75?'#34d399':($pct>=50?'#f59e0b':'#ef4444');
      ?>
      <tr><td><?= htmlspecialchars($r['roll_no']??'-') ?></td><td><?= htmlspecialchars($r['name']) ?></td><td><span class="badge badge-success"><?= $r['present'] ?></span></td><td><span class="badge badge-danger"><?= $r['absent'] ?></span></td><td><span class="badge badge-warning"><?= $r['late'] ?></span></td><td><?= $total ?></td><td><strong style="color:<?= $color ?>"><?= $pct ?>%</strong></td></tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif(isset($_GET['att_class'])): ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-circle-check text-success"></i></div><h3>No attendance data for selected period</h3></div>
<?php else: ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-circle-check text-success"></i></div><h3>Select a class and date range</h3></div>
<?php endif; ?>

<?php elseif($report_type === 'expenses'): ?>
<!-- Expenses Report -->
<?php 
$cats = $conn->query("SELECT * FROM expense_categories ORDER BY name");
$cats_arr = []; if($cats) while($c=$cats->fetch_assoc()) $cats_arr[]=$c;
?>
<div class="filter-bar no-print">
  <form method="GET" class="filter-bar" style="margin-bottom:0">
    <input type="hidden" name="report" value="expenses">
    <select name="exp_cat" class="form-control">
      <option value="">All Categories</option>
      <?php foreach($cats_arr as $c): ?><option value="<?= $c['id'] ?>" <?= ($_GET['exp_cat']??0)==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="exp_from" class="form-control" value="<?= htmlspecialchars($_GET['exp_from']??date('Y-m-01')) ?>">
    <input type="date" name="exp_to" class="form-control" value="<?= htmlspecialchars($_GET['exp_to']??date('Y-m-d')) ?>">
    <button type="submit" class="btn btn-primary">Generate</button>
  </form>
</div>
<div class="stat-cards" style="grid-template-columns:repeat(2,1fr)">
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-wallet"></i></div><div class="stat-info"><h3><?= $currency ?> <?= number_format($exp_total,2) ?></h3><p>Total Expenses</p></div></div>
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-list-ul"></i></div><div class="stat-info"><h3><?= $exp_report ? $exp_report->num_rows : 0 ?></h3><p>Total Records</p></div></div>
</div>
<?php if($exp_report && $exp_report->num_rows>0): ?>
<div class="card">
  <div class="card-header"><h3>Expense Report</h3><span class="text-muted"><?= htmlspecialchars(($_GET['exp_from']??'').' to '.($_GET['exp_to']??'')) ?></span></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead>
      <tbody>
      <?php while($e=$exp_report->fetch_assoc()): ?>
      <tr><td><?= date('d M Y', strtotime($e['expense_date'])) ?></td><td><span class="badge badge-info"><?= htmlspecialchars($e['category_name']??'Uncategorized') ?></span></td><td><?= htmlspecialchars($e['description']) ?></td><td class="text-danger"><strong><?= $currency ?> <?= number_format($e['amount'],2) ?></strong></td></tr>
      <?php endwhile; ?>
      </tbody>
      <tfoot><tr><th colspan="3" style="text-align:right">Total Expenditure:</th><th class="text-danger"><?= $currency ?> <?= number_format($exp_total,2) ?></th></tr></tfoot>
    </table>
  </div>
</div>
<?php else: ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-wallet"></i></div><h3>No expenses found for selected period</h3></div>
<?php endif; ?>

<?php elseif($report_type === 'defaulters'): ?>
<!-- Defaulters List Report -->
<div class="filter-bar no-print">
  <form method="GET" class="filter-bar" style="margin-bottom:0; display:flex; gap:12px; align-items:center;">
    <input type="hidden" name="report" value="defaulters">
    <div style="display:flex; align-items:center; gap:6px;">
      <label style="font-size:0.82rem; font-weight:700;"><i class="fa-solid fa-graduation-cap"></i> Session:</label>
      <input type="text" name="session" class="form-control" value="<?= htmlspecialchars($_GET['session'] ?? $session_year) ?>" style="width:140px;">
    </div>
    <div style="display:flex; align-items:center; gap:6px;">
      <label style="font-size:0.82rem; font-weight:700;"><i class="fa-solid fa-calendar-alt"></i> Month:</label>
      <select name="month" class="form-control" style="width:140px;">
        <option value="">All Months</option>
        <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
          <option value="<?= $m ?>" <?= ($_GET['month']??'') === $m ? 'selected' : '' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Load Defaulters</button>
  </form>
</div>

<?php if(!empty($defaulters_data)): ?>
<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h3 style="margin:0; color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Detailed Defaulters List</h3>
      <span class="text-muted" style="font-size:0.8rem;">Session: <?= htmlspecialchars($_GET['session'] ?? $session_year) ?></span>
    </div>
    <span class="badge" style="background:#fee2e2; color:#dc2626; font-size:0.8rem; padding:6px 12px;"><?= count($defaulters_data) ?> students in default</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr style="background:var(--bg-secondary);">
          <th style="width:40px;">#</th>
          <th>Student Details</th>
          <th>Class</th>
          <th>Unpaid Months</th>
          <th style="text-align:right;">Total Billed</th>
          <th style="text-align:right;">Total Paid</th>
          <th style="text-align:right;">Deficit (Pending)</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $sum_billed = 0; $sum_paid = 0; $sum_pending = 0;
        foreach($defaulters_data as $i => $d): 
            $sum_billed += $d['total_billed'];
            $sum_paid += $d['total_paid'];
            $sum_pending += $d['pending'];
        ?>
        <tr>
          <td style="font-weight:700; color:var(--text-muted);"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($d['name']) ?></div>
            <div style="font-size:0.75rem; color:var(--text-secondary);">
              Adm: <?= htmlspecialchars($d['admission_no']) ?> | F: <?= htmlspecialchars($d['father_name']?:'-') ?>
            </div>
            <div style="font-size:0.75rem; color:var(--text-secondary); margin-top:2px;">
              <i class="fa-solid fa-phone" style="font-size:0.6rem;"></i> <?= htmlspecialchars($d['phone'] ?: ($d['father_phone']?:'N/A')) ?>
            </div>
          </td>
          <td><span class="badge badge-info"><?= htmlspecialchars($d['class_name'] . ' ' . ($d['section']??'')) ?></span></td>
          <td style="font-size:0.8rem; color:#dc2626; max-width:200px;"><?= htmlspecialchars($d['unpaid_months'] ?: 'Partial/Arrears') ?></td>
          <td style="text-align:right;"><?= $currency ?> <?= number_format($d['total_billed'],0) ?></td>
          <td style="text-align:right; color:#10b981;"><?= $currency ?> <?= number_format($d['total_paid'],0) ?></td>
          <td style="text-align:right; color:#ef4444; font-weight:800; font-size:1.05rem;"><?= $currency ?> <?= number_format($d['pending'],0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--bg-secondary); font-weight:800; font-size:1.05rem;">
          <td colspan="4" style="text-align:right;">TOTAL DEFAULT (SESSION):</td>
          <td style="text-align:right;"><?= $currency ?> <?= number_format($sum_billed,0) ?></td>
          <td style="text-align:right; color:#10b981;"><?= $currency ?> <?= number_format($sum_paid,0) ?></td>
          <td style="text-align:right; color:#ef4444;"><?= $currency ?> <?= number_format($sum_pending,0) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php else: ?>
<div class="empty-state">
  <div class="icon" style="color:#10b981; background:#dcfce7;"><i class="fa-solid fa-check-circle"></i></div>
  <h3 style="color:#16a34a;">No Defaulters Found</h3>
  <p>All students have cleared their dues for this session!</p>
</div>
<?php endif; ?>

<?php elseif($report_type === 'top_payers'): ?>
<!-- Top Payers Report -->
<div class="filter-bar no-print">
  <form method="GET" class="filter-bar" style="margin-bottom:0; display:flex; gap:12px; align-items:center;">
    <input type="hidden" name="report" value="top_payers">
    <div style="display:flex; align-items:center; gap:6px;">
      <label style="font-size:0.82rem; font-weight:700;"><i class="fa-solid fa-graduation-cap"></i> Session:</label>
      <input type="text" name="session" class="form-control" value="<?= htmlspecialchars($_GET['session'] ?? $session_year) ?>" style="width:140px;">
    </div>
    <button type="submit" class="btn btn-primary">Load Top Payers</button>
  </form>
</div>

<?php if(!empty($top_payers_data)): ?>
<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h3 style="margin:0; color:#10b981;"><i class="fa-solid fa-crown"></i> Top Paying Students</h3>
      <span class="text-muted" style="font-size:0.8rem;">Session: <?= htmlspecialchars($_GET['session'] ?? $session_year) ?></span>
    </div>
    <span class="badge" style="background:#dcfce7; color:#16a34a; font-size:0.8rem; padding:6px 12px;"><?= count($top_payers_data) ?> students listed</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr style="background:var(--bg-secondary);">
          <th style="width:40px;">Rank</th>
          <th>Student Details</th>
          <th>Class</th>
          <th style="text-align:right;">Invoice Paid</th>
          <th style="text-align:right;">Advance Fees</th>
          <th style="text-align:right; font-size:0.95rem; color:#16a34a;">Total Contribution</th>
          <th>Last Payment</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $sum_paid = 0; $sum_adv = 0; $sum_total = 0;
        foreach($top_payers_data as $i => $t): 
            $sum_paid += $t['total_paid'];
            $sum_adv += $t['advance'];
            $sum_total += $t['total_contribution'];
        ?>
        <tr>
          <td style="font-weight:800; color:<?= $i<3 ? '#f59e0b' : 'var(--text-muted)' ?>; font-size:<?= $i<3 ? '1.2rem' : '1rem' ?>;">#<?= $i+1 ?></td>
          <td>
            <div style="font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($t['name']) ?></div>
            <div style="font-size:0.75rem; color:var(--text-secondary);">
              Adm: <?= htmlspecialchars($t['admission_no']) ?> | F: <?= htmlspecialchars($t['father_name']?:'-') ?>
            </div>
          </td>
          <td><span class="badge badge-primary"><?= htmlspecialchars($t['class_name'] . ' ' . ($t['section']??'')) ?></span></td>
          <td style="text-align:right;"><?= $currency ?> <?= number_format($t['total_paid'],0) ?></td>
          <td style="text-align:right; color:#7c3aed;"><?= $currency ?> <?= number_format($t['advance'],0) ?></td>
          <td style="text-align:right; color:#10b981; font-weight:800; font-size:1.05rem;"><?= $currency ?> <?= number_format($t['total_contribution'],0) ?></td>
          <td style="font-size:0.82rem; color:var(--text-muted);"><?= $t['last_payment_date'] ? date('d M Y', strtotime($t['last_payment_date'])) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--bg-secondary); font-weight:800; font-size:1.05rem;">
          <td colspan="3" style="text-align:right;">TOTAL TOP PAYERS CONTRIBUTION:</td>
          <td style="text-align:right;"><?= $currency ?> <?= number_format($sum_paid,0) ?></td>
          <td style="text-align:right; color:#7c3aed;"><?= $currency ?> <?= number_format($sum_adv,0) ?></td>
          <td style="text-align:right; color:#10b981;"><?= $currency ?> <?= number_format($sum_total,0) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php else: ?>
<div class="empty-state">
  <div class="icon" style="color:#f59e0b; background:#fef3c7;"><i class="fa-solid fa-coins"></i></div>
  <h3 style="color:#d97706;">No Payment Records Found</h3>
  <p>No fee payments have been logged for this session yet.</p>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-chart-bar"></i></div><h3>Choose a report type above</h3><p>Select Yearly, Fees, Expenses, Students, or Attendance report.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
