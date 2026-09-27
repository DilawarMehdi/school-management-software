<?php
/**
 * SIAX SMSS - Average Fee Report (SIAX Premium Style)
 * Shows average fee per class/section from invoice data
 */
$page_title = 'Average Fee';
$active_page = 'average_fee';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$session_year  = $settings['session_year'] ?? '2025-2026';
$currency      = $settings['currency'] ?? 'Rs.';

// Fetch all invoices for dropdown
$invoices_q = $conn->query("SELECT id, title, month, year FROM fee_invoices WHERE session_year = '$session_year' ORDER BY id DESC");
$invoices   = [];
if ($invoices_q) while ($inv = $invoices_q->fetch_assoc()) $invoices[] = $inv;

$sel_invoice_id = (int)($_GET['invoice_id'] ?? 0);
// Default to latest invoice
if (!$sel_invoice_id && !empty($invoices)) {
    $sel_invoice_id = (int)$invoices[0]['id'];
}

// Fetch classes in natural order
$classes_arr = get_all_classes($conn);

// For each class, calculate: students enrolled this session, average fee from the selected invoice
$report_data    = [];
$total_students = 0;
$total_fee_sum  = 0;

foreach ($classes_arr as $cls) {
    $cid = (int)$cls['id'];

    // Count active enrolled students this session
    $cnt_q = $conn->query("SELECT COUNT(*) as cnt FROM student_enrollments se
                           JOIN students s ON se.student_id = s.id
                           WHERE se.class_id = $cid AND se.session_year = '$session_year'
                             AND se.status = 'Active' AND s.status = 'Active'");
    $student_count = $cnt_q ? (int)$cnt_q->fetch_assoc()['cnt'] : 0;

    // Average fee from invoice records for this class
    $avg_fee = 0;
    if ($sel_invoice_id > 0 && $student_count > 0) {
        $avg_q = $conn->query("SELECT AVG(tuition_fee + COALESCE(admission_fee, 0) + prev_pending - discount_amount) as avg_fee
                               FROM student_monthly_fees
                               WHERE invoice_id = $sel_invoice_id AND class_id = $cid");
        if ($avg_q) {
            $avg_row = $avg_q->fetch_assoc();
            $avg_fee = (float)($avg_row['avg_fee'] ?? 0);
        }
    } elseif ($sel_invoice_id === 0) {
        // No invoice: use fee criteria standard fee if available
        $crit_q = $conn->query("SELECT MAX(fcd.standard_fee) as sf FROM fee_criteria_details fcd WHERE fcd.class_id = $cid");
        if ($crit_q) {
            $crit_row = $crit_q->fetch_assoc();
            $avg_fee  = (float)($crit_row['sf'] ?? 0);
        }
    }

    $report_data[] = [
        'class_name'    => $cls['name'],
        'section'       => $cls['section'],
        'student_count' => $student_count,
        'average_fee'   => $avg_fee,
    ];

    $total_students += $student_count;
    $total_fee_sum  += $avg_fee * $student_count;
}

$grand_average = $total_students > 0 ? $total_fee_sum / $total_students : 0;

// Selected invoice label
$sel_invoice_label = '';
foreach ($invoices as $inv) {
    if ((int)$inv['id'] === $sel_invoice_id) {
        $sel_invoice_label = $inv['title'];
        break;
    }
}
?>

<style>
/* =============================================
   SIAX Premium — Average Fee Page
   ============================================= */
.avgfee-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 12px;
}
.avgfee-page-header h1 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Invoice Filter Card */
.avgfee-filter-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 18px 22px;
    margin-bottom: 22px;
    box-shadow: var(--shadow);
}
.avgfee-filter-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 8px;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}
.avgfee-filter-row {
    display: flex;
    gap: 12px;
    align-items: center;
}
.avgfee-filter-row select {
    flex: 1;
    height: 40px;
    padding: 0 14px;
    border: 1px solid var(--border);
    border-radius: var(--radius-xs);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 600;
    transition: all var(--transition);
}
.avgfee-filter-row select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
    outline: none;
}
.avgfee-search-btn {
    height: 40px;
    padding: 0 26px;
    background: #27ae60;
    color: #fff;
    border: none;
    border-radius: var(--radius-xs);
    font-weight: 800;
    font-size: 0.88rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all var(--transition);
    white-space: nowrap;
}
.avgfee-search-btn:hover {
    background: #219150;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(39,174,96,0.3);
}

/* Session Bar */
.avgfee-session-bar {
    background: #1e293b;
    color: #f8fafc;
    padding: 10px 18px;
    border-radius: var(--radius-xs) var(--radius-xs) 0 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: 0.3px;
}
[data-theme="light"] .avgfee-session-bar {
    background: #1e293b;
    color: #ffffff;
}

/* Average Fee Table */
.avgfee-table-wrap {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-top: none;
    border-radius: 0 0 var(--radius-sm) var(--radius-sm);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 28px;
}
.avgfee-table {
    width: 100%;
    border-collapse: collapse;
}
.avgfee-table thead tr th {
    background: #f8fafc;
    border-bottom: 2px solid var(--border);
    padding: 12px 18px;
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    text-align: left;
}
[data-theme="dark"] .avgfee-table thead tr th {
    background: rgba(15, 23, 42, 0.6);
    color: #94a3b8;
}
.avgfee-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background var(--transition);
}
.avgfee-table tbody tr:last-child {
    border-bottom: none;
}
.avgfee-table tbody tr:hover {
    background: rgba(59, 130, 246, 0.04);
}
.avgfee-table tbody td {
    padding: 10px 18px;
    vertical-align: middle;
}
.cell-label {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 3px;
}
.cell-val {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-primary);
}
.cell-val-link {
    font-size: 0.92rem;
    font-weight: 700;
    color: #3b82f6;
    text-decoration: none;
}
.cell-val-link:hover {
    text-decoration: underline;
    color: #2563eb;
}
.cell-count {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
}
.cell-fee {
    font-size: 1.05rem;
    font-weight: 800;
    color: #10b981;
}

/* Footer / Total Row */
.avgfee-table tfoot tr {
    background: #1e293b;
    color: #f8fafc;
}
[data-theme="light"] .avgfee-table tfoot tr {
    background: #1e293b;
    color: #ffffff;
}
.avgfee-table tfoot td {
    padding: 12px 18px;
    font-weight: 800;
    font-size: 0.9rem;
    color: #f8fafc;
}
.tfoot-total-label {
    font-size: 0.8rem;
    opacity: 0.7;
    display: block;
    font-weight: 600;
    margin-bottom: 2px;
}
.tfoot-total-val {
    font-size: 1rem;
    font-weight: 900;
    color: #f59e0b;
}

/* Stats cards */
.avgfee-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}
.avgfee-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 18px 22px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all var(--transition);
}
.avgfee-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--border-hover);
}
.avgfee-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-xs);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.avgfee-stat-info h3 {
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0 0 2px 0;
    color: var(--text-primary);
}
.avgfee-stat-info p {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin: 0;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

@media (max-width: 768px) {
    .avgfee-stats { grid-template-columns: 1fr; }
    .avgfee-filter-row { flex-direction: column; }
    .avgfee-filter-row select { width: 100%; }
}
</style>

<div class="main-container">

    <!-- Page Header -->
    <div class="avgfee-page-header">
        <h1>
            <i class="fa-solid fa-chart-bar" style="color:#f59e0b;"></i>
            Average Fee
        </h1>
        <a href="<?= BASE_URL ?>prints/print_average_fee.php?invoice_id=<?= $sel_invoice_id ?>" target="_blank" class="btn btn-primary">
            <i class="fa-solid fa-print"></i> Print Report
        </a>
    </div>

    <!-- Invoice Filter Card -->
    <div class="avgfee-filter-card">
        <div class="avgfee-filter-label"><i class="fa-solid fa-file-invoice" style="color:#f59e0b;"></i> Select Invoice</div>
        <form method="GET" class="avgfee-filter-row">
            <select name="invoice_id" id="invoiceSelect">
                <option value="0">-- All / Fee Criteria --</option>
                <?php foreach ($invoices as $inv): ?>
                    <option value="<?= $inv['id'] ?>" <?= $sel_invoice_id == $inv['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($inv['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="avgfee-search-btn">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
        </form>
    </div>

    <!-- Summary Stat Cards -->
    <div class="avgfee-stats">
        <div class="avgfee-stat-card">
            <div class="avgfee-stat-icon" style="background:rgba(59,130,246,0.12); color:#3b82f6;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="avgfee-stat-info">
                <h3><?= number_format($total_students) ?></h3>
                <p>Total Students Enrolled</p>
            </div>
        </div>
        <div class="avgfee-stat-card">
            <div class="avgfee-stat-icon" style="background:rgba(16,185,129,0.12); color:#10b981;">
                <i class="fa-solid fa-money-bill-trend-up"></i>
            </div>
            <div class="avgfee-stat-info">
                <h3><?= $currency ?> <?= number_format($total_fee_sum, 0) ?></h3>
                <p>Total Fee (All Classes)</p>
            </div>
        </div>
        <div class="avgfee-stat-card">
            <div class="avgfee-stat-icon" style="background:rgba(245,158,11,0.12); color:#f59e0b;">
                <i class="fa-solid fa-calculator"></i>
            </div>
            <div class="avgfee-stat-info">
                <h3><?= $currency ?> <?= number_format($grand_average, 2) ?></h3>
                <p>Grand Average Fee / Student</p>
            </div>
        </div>
    </div>

    <!-- Session Bar + Table -->
    <div class="avgfee-session-bar">
        <i class="fa-solid fa-table-cells-large"></i>
        Fall/Session &mdash; <?= htmlspecialchars($session_year) ?>
        <?php if ($sel_invoice_label): ?>
            &nbsp;&bull;&nbsp; <?= htmlspecialchars($sel_invoice_label) ?>
        <?php endif; ?>
    </div>

    <div class="avgfee-table-wrap">
        <table class="avgfee-table">
            <thead>
                <tr>
                    <th style="width:25%;">Class</th>
                    <th style="width:30%;">Section</th>
                    <th style="width:22%;">No. of Students</th>
                    <th style="width:23%;">Average Fee</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data as $row): ?>
                <tr>
                    <td>
                        <span class="cell-label">Class</span>
                        <a href="<?= BASE_URL ?>modules/students/students.php" class="cell-val-link">
                            <?= htmlspecialchars($row['class_name']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="cell-label">Section</span>
                        <span class="cell-val"><?= htmlspecialchars($row['section'] ?: '-') ?></span>
                    </td>
                    <td>
                        <span class="cell-label">No-of-Students</span>
                        <span class="cell-count"><?= $row['student_count'] ?></span>
                    </td>
                    <td>
                        <span class="cell-label">Average-Fee</span>
                        <span class="cell-fee"><?= number_format($row['average_fee'], 0) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($report_data)): ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted);">
                        <i class="fa-solid fa-chart-bar" style="font-size:2rem; display:block; margin-bottom:10px; opacity:0.3;"></i>
                        No class data found. Please add classes to the system first.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="font-size:0.92rem; font-weight:800; letter-spacing:0.3px;">
                        <i class="fa-solid fa-sigma" style="margin-right:6px; color:#f59e0b;"></i> Total Students
                    </td>
                    <td>
                        <span class="tfoot-total-val"><?= number_format($total_students) ?></span>
                    </td>
                    <td>
                        <span class="tfoot-total-label">Total Fee</span>
                        <span class="tfoot-total-val"><?= $currency ?> <?= number_format($total_fee_sum, 0) ?> /-</span>
                        <span class="tfoot-total-label" style="margin-top:4px;">Average Fee</span>
                        <span class="tfoot-total-val"><?= number_format($grand_average, 2) ?></span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
