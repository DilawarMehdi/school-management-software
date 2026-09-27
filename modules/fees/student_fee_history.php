<?php
$page_title = 'Fee Progress Report';
$active_page = 'student_fee_history';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$sel_student = (int)($_GET['student_id'] ?? $_GET['id'] ?? 0);
$sel_class   = (int)($_GET['class_id'] ?? 0);
$search_term = trim($_GET['search'] ?? '');

// For student/parent: restrict to own profile
$role = current_role();
if (in_array($role, ['student','parent'])) {
    $uid = current_uid();
    $sr = $conn->query("SELECT id FROM students WHERE user_id=$uid LIMIT 1");
    if ($sr && $sr->num_rows) $sel_student = (int)$sr->fetch_assoc()['id'];
}

// Fetch all classes for dropdown filter (Naturally sorted)
$classes_arr = get_all_classes($conn);


// Fetch all active students for direct select dropdown
$all_students_q = $conn->query("SELECT id, name, admission_no, roll_no FROM students WHERE status='Active' ORDER BY name");
$all_students_arr = [];
if ($all_students_q) while($s = $all_students_q->fetch_assoc()) $all_students_arr[] = $s;

// Filtered students list (if class_id or search_term is set)
$filtered_students = [];
if (($sel_class > 0 || !empty($search_term)) && !in_array($role, ['student','parent'])) {
    $where = ["s.status='Active'"];
    if ($sel_class > 0) {
        $where[] = "s.class_id = $sel_class";
    }
    if (!empty($search_term)) {
        $st_esc = $conn->real_escape_string($search_term);
        $where[] = "(s.name LIKE '%$st_esc%' OR s.admission_no LIKE '%$st_esc%' OR s.father_name LIKE '%$st_esc%' OR s.roll_no LIKE '%$st_esc%')";
    }
    $w_sql = implode(' AND ', $where);
    $fq = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE $w_sql ORDER BY s.roll_no+0, s.name ASC");
    if ($fq) while($row = $fq->fetch_assoc()) $filtered_students[] = $row;
}

// Student profile & fee history
$student = null;
$fee_history = [];
$total_invoiced = 0;
$total_paid = 0;
$total_discount = 0;
$total_dues = 0;
$overall_ratio = 1.00;
$overall_pct = 100;
$status_badge_text = 'No Dues';
$status_badge_bg = '#10b981';

if ($sel_student > 0) {
    $sq = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=$sel_student");
    if ($sq && $sq->num_rows) {
        $student = $sq->fetch_assoc();
        
        // Fetch all monthly fee records for this student (filtered by active session)
        $active_session = $settings['session_year'] ?? '2025-2026';
        $mfq = $conn->query("SELECT smf.* FROM student_monthly_fees smf JOIN fee_invoices fi ON smf.invoice_id = fi.id WHERE smf.student_id = $sel_student AND fi.session_year = '$active_session' ORDER BY smf.created_at ASC, smf.id ASC");
        $temp_history = [];
        $total_paid_pool = 0;
        if ($mfq) {
            while ($row = $mfq->fetch_assoc()) {
                // Unique billed amount for this month (excluding prev_pending to avoid double counting in overall statistics)
                $unique_billed = (float)$row['tuition_fee'] + (float)($row['admission_fee']??0) + (float)$row['fine_amount'] - (float)$row['discount_amount'];
                if ($unique_billed < 0) $unique_billed = 0;
                
                // Add the actual cash paid to our paid pool
                $total_paid_pool += (float)$row['paid_amount'];
                
                $row['unique_billed'] = $unique_billed;
                $temp_history[] = $row;
            }
        }
        
        // Chronologically allocate total paid pool to unique monthly billed amounts
        $remaining_paid = $total_paid_pool;
        foreach ($temp_history as $row) {
            $ub = $row['unique_billed'];
            $allocated = min($remaining_paid, $ub);
            $remaining_paid -= $allocated;
            
            $balance = Math_max_zero($ub - $allocated);
            $pct = $ub > 0 ? round(($allocated / $ub) * 100, 1) : 100;
            $ratio = $ub > 0 ? round($allocated / $ub, 2) : 1.00;
            
            $total_invoiced += $ub;
            $total_paid += $allocated;
            $total_discount += (float)$row['discount_amount'];
            $total_dues += $balance;
            
            $row['payable'] = $ub;
            $row['paid_amount'] = $allocated;
            $row['balance'] = $balance;
            $row['pct'] = $pct;
            $row['ratio'] = $ratio;
            
            // Adjust status to reflect actual cleared status
            if ($balance == 0 && $ub > 0) {
                $row['status'] = 'Paid';
            } elseif ($allocated > 0) {
                $row['status'] = 'Partially Paid';
            } else {
                $row['status'] = 'Due';
            }
            
            $fee_history[] = $row;
        }
        
        $overall_pct = $total_invoiced > 0 ? round(($total_paid / $total_invoiced) * 100, 1) : 100;
        $overall_ratio = $total_invoiced > 0 ? round($total_paid / $total_invoiced, 2) : 1.00;
        
        if ($total_dues == 0) {
            $status_badge_text = 'Fully Cleared (Regular Payer)';
            $status_badge_bg = '#10b981';
        } elseif ($total_paid > 0) {
            $status_badge_text = 'Partially Cleared (Pending Dues)';
            $status_badge_bg = '#f59e0b';
        } else {
            $status_badge_text = 'Fee Defaulter (Unpaid Dues)';
            $status_badge_bg = '#ef4444';
        }
    }
}

function Math_max_zero($val) {
    return $val > 0 ? $val : 0;
}
?>

<style>
.search-filter-card { margin-bottom: 20px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
.search-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end; }

.fee-progress-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 28px;
    box-shadow: var(--shadow);
    margin-bottom: 24px;
}
.metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.metric-box {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
    text-align: center;
}
.metric-box .val { font-size: 1.5rem; font-weight: 800; margin-bottom: 4px; }
.metric-box .lbl { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }

.progress-bar-wrap {
    background: rgba(0,0,0,0.08);
    border-radius: 10px;
    height: 14px;
    overflow: hidden;
    margin-top: 4px;
}
.progress-bar-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.4s ease;
}

@media print {
    .no-print, .top-header, .sidebar, #showMenuBtn { display:none!important; }
    body, .main-wrapper, .page-content { padding:0!important; margin:0!important; background:#fff!important; color:#000!important; }
    .fee-progress-card { border:1px solid #000!important; box-shadow:none!important; padding:15px!important; }
    .metric-box { border:1px solid #000!important; }
}
</style>

<div class="page-header no-print">
    <div>
        <h1><i class="fa-solid fa-chart-line"></i> Student Fee Progress Report</h1>
        <p>Track month-by-month fee payments, clearance ratios, and overall financial progress</p>
    </div>
    <?php if($student): ?>
    <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Fee Progress Report</button>
    <?php endif; ?>
</div>

<!-- SEARCH & FILTER CARD -->
<?php if (!in_array($role, ['student','parent'])): ?>
<div class="search-filter-card no-print">
    <form method="GET" action="student_fee_history.php">
        <div class="search-filter-grid">
            <!-- 1. Filter by Class -->
            <div>
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
                    <i class="fa-solid fa-school"></i> Filter by Class
                </label>
                <select name="class_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- All Classes --</option>
                    <?php foreach($classes_arr as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $sel_class == $c['id'] ? 'selected' : '' ?>>Class <?= htmlspecialchars($c['name'].' '.$c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 2. Search Name / Reg No. -->
            <div>
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
                    <i class="fa-solid fa-magnifying-glass"></i> Search Name / Reg No.
                </label>
                <input type="text" name="search" class="form-control" placeholder="Enter Student Name or Reg No..." value="<?= htmlspecialchars($search_term) ?>">
            </div>

            <!-- 3. Direct Select Student -->
            <div>
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
                    <i class="fa-solid fa-user"></i> Direct Select Student
                </label>
                <select name="student_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Select Student --</option>
                    <?php foreach($all_students_arr as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sel_student == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['admission_no'].' — '.$s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-search"></i> Search</button>
                <a href="student_fee_history.php" class="btn btn-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            </div>
        </div>
    </form>
</div>

<!-- MATCHING STUDENTS TABLE -->
<?php if (!empty($filtered_students)): ?>
<div class="card no-print" style="margin-bottom: 24px;">
    <div class="card-header" style="padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid var(--border);">
        <h3 style="font-size: 1rem; font-weight: 700;">
            <i class="fa-solid fa-users"></i> Matching Students (<?= count($filtered_students) ?>)
        </h3>
        <span class="text-muted" style="font-size: 0.78rem;">Click <i class="fa-solid fa-chart-line" style="color:#10b981;"></i> to view Fee Progress Report</span>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 70px; text-align: center;">Report</th>
                    <th>Reg No.</th>
                    <th>Roll No.</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Class</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($filtered_students as $fs): ?>
                <tr>
                    <td style="text-align: center;">
                        <a href="student_fee_history.php?student_id=<?= $fs['id'] ?>&class_id=<?= $sel_class ?>&search=<?= urlencode($search_term) ?>" style="color: #10b981; font-size: 1.2rem;" title="View Fee Progress Report">
                            <i class="fa-solid fa-chart-line"></i>
                        </a>
                    </td>
                    <td><strong><?= htmlspecialchars($fs['admission_no']) ?></strong></td>
                    <td><?= htmlspecialchars($fs['roll_no'] ?? '-') ?></td>
                    <td><strong><?= htmlspecialchars($fs['name']) ?></strong></td>
                    <td><?= htmlspecialchars($fs['father_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(($fs['class_name'] ?? '').' '.($fs['section'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- FEE PROGRESS REPORT CARD -->
<?php if ($student): ?>
<div class="fee-progress-card">
    <!-- Student Info Header -->
    <div style="border-bottom: 2px solid var(--border); padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin:0 0 4px 0; font-size: 1.4rem; font-weight: 800; color: var(--text-primary);"><?= htmlspecialchars($student['name']) ?></h2>
            <p style="margin:0; font-size: 0.85rem; color: var(--text-muted);">
                Father Name: <strong><?= htmlspecialchars($student['father_name'] ?? '-') ?></strong> &bull; 
                Reg No: <strong><?= htmlspecialchars($student['admission_no']) ?></strong> &bull; 
                Roll No: <strong><?= htmlspecialchars($student['roll_no'] ?? '-') ?></strong> &bull; 
                Class: <strong><?= htmlspecialchars(($student['class_name']??'').' '.($student['section']??'')) ?></strong>
            </p>
        </div>
        <div>
            <span style="display:inline-block; padding: 6px 16px; border-radius: 20px; font-weight: 800; font-size: 0.82rem; background:<?= $status_badge_bg ?>; color:#fff;">
                <?= $status_badge_text ?>
            </span>
        </div>
    </div>

    <!-- Key Financial Metrics -->
    <div class="metric-grid">
        <div class="metric-box">
            <div class="val" style="color: #6366f1;"><?= number_format($total_invoiced, 0) ?></div>
            <div class="lbl">Total Invoiced Dues</div>
        </div>
        <div class="metric-box">
            <div class="val" style="color: #10b981;"><?= number_format($total_paid, 0) ?></div>
            <div class="lbl">Total Fees Cleared</div>
        </div>
        <div class="metric-box">
            <div class="val" style="color: <?= $total_dues > 0 ? '#ef4444' : '#10b981' ?>;"><?= number_format($total_dues, 0) ?></div>
            <div class="lbl">Outstanding Balance</div>
        </div>
        <div class="metric-box">
            <div class="val" style="color: #f59e0b;"><?= $overall_pct ?>% <small style="font-size:0.75rem;">(<?= $overall_ratio ?>x)</small></div>
            <div class="lbl">Overall Clearance Progress</div>
        </div>
    </div>

    <!-- Detailed Fee Progress Table -->
    <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 14px; color: var(--text-primary);">
        <i class="fa-solid fa-list-check" style="color: #6366f1; margin-right: 6px;"></i> Month-by-Month Fee Progress Breakdown
    </h3>

    <?php if (empty($fee_history)): ?>
        <div style="text-align: center; padding: 35px; color: var(--text-muted);">No fee invoices or payment records found for this student.</div>
    <?php else: ?>
        <div class="table-wrapper mb-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th>Fee Month</th>
                        <th style="text-align: right;">Tuition Fee</th>
                        <th style="text-align: right;">Fine / Extra</th>
                        <th style="text-align: right;">Discount</th>
                        <th style="text-align: right;">Net Payable</th>
                        <th style="text-align: right;">Amount Paid</th>
                        <th style="text-align: right;">Balance Dues</th>
                        <th style="width: 120px; text-align: center;">Clearance %</th>
                        <th style="width: 110px; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fee_history as $idx => $fh): 
                    $st_class = $fh['status'] === 'Paid' ? 'status-paid' : ($fh['status'] === 'Partially Paid' ? 'status-partial' : 'status-due');
                ?>
                    <tr>
                        <td style="text-align: center; font-weight: 700;"><?= $idx + 1 ?></td>
                        <td><strong><?= htmlspecialchars($fh['month']) ?></strong></td>
                        <td style="text-align: right;"><?= number_format((float)$fh['tuition_fee'] + (float)($fh['admission_fee'] ?? 0), 0) ?></td>
                        <td style="text-align: right;"><?= number_format((float)$fh['fine_amount'], 0) ?></td>
                        <td style="text-align: right; color: #0284c7;"><?= number_format((float)$fh['discount_amount'], 0) ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format($fh['payable'], 0) ?></td>
                        <td style="text-align: right; font-weight: 800; color: #10b981;"><?= number_format((float)$fh['paid_amount'], 0) ?></td>
                        <td style="text-align: right; font-weight: 800; color: <?= $fh['balance'] > 0 ? '#ef4444' : '#10b981' ?>;"><?= number_format($fh['balance'], 0) ?></td>
                        <td style="text-align: center; font-weight: 800; color: #6366f1;"><?= $fh['pct'] ?>%</td>
                        <td style="text-align: center;"><span class="status-pill <?= $st_class ?>"><?= $fh['status'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Visual Monthly Progress Bars -->
        <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 14px; color: var(--text-primary); margin-top: 25px;">
            <i class="fa-solid fa-chart-column" style="color: #10b981; margin-right: 6px;"></i> Visual Fee Clearance Progress Trend
        </h3>
        <div style="display: flex; flex-direction: column; gap: 12px; background: var(--bg-secondary); padding: 20px; border-radius: var(--radius); border: 1px solid var(--border);">
            <?php foreach ($fee_history as $fh): 
                $bar_color = $fh['pct'] >= 100 ? '#10b981' : ($fh['pct'] > 0 ? '#f59e0b' : '#ef4444');
            ?>
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; font-weight: 700; margin-bottom: 4px;">
                    <span>Month: <?= htmlspecialchars($fh['month']) ?></span>
                    <span>Paid: <?= number_format($fh['paid_amount'],0) ?> / <?= number_format($fh['payable'],0) ?> (<?= $fh['pct'] ?>% Cleared)</span>
                </div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar-fill" style="width: <?= min(100, max(3, $fh['pct'])) ?>%; background: <?= $bar_color ?>;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Overall Evaluation Box -->
        <div style="margin-top: 25px; padding: 20px; background: rgba(16,185,129,0.06); border: 1.5px dashed #10b981; border-radius: var(--radius); display: flex; align-items: center; gap: 16px;">
            <i class="fa-solid fa-shield-check" style="font-size: 2.2rem; color: #10b981;"></i>
            <div>
                <h4 style="margin: 0 0 4px 0; font-size: 0.95rem; font-weight: 800; color: var(--text-primary);">Financial Progress &amp; Compliance Evaluation</h4>
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5;">
                    Student has cleared a cumulative total of <strong><?= number_format($total_paid, 0) ?></strong> out of <strong><?= number_format($total_invoiced, 0) ?></strong> invoiced dues, achieving an overall fee clearance progress ratio of <strong><?= $overall_pct ?>%</strong> (Progress Ratio: <strong><?= $overall_ratio ?>x</strong>). Total outstanding dues: <strong><?= number_format($total_dues, 0) ?></strong>.
                </p>
            </div>
        </div>

        <div style="margin-top: 30px; text-align: center; font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid var(--border); padding-top: 12px;">
            Generated by SIAC Technologies — SMSS Financial Analytics Engine &bull; <?= date('d M Y') ?>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="fee-progress-card no-print" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
    <i class="fa-solid fa-chart-line" style="font-size: 3.5rem; color: #10b981; opacity: 0.3; margin-bottom: 15px;"></i>
    <h3>Select a Student to View Fee Progress Report</h3>
    <p>Use the Class filter, Search input, or Direct Select dropdown above to view any student's comprehensive fee progress report.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
