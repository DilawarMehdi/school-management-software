<?php
/**
 * SIAX SMSS - Staff Attendance Management
 * Daily attendance tracking, status logs, and monthly reports.
 */
$page_title = 'Staff Attendance';
$active_page = 'staff_attendance';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$today = date('Y-m-d');
$att_date = $_GET['date'] ?? $today;
$dept_id  = (int)($_GET['dept_id'] ?? 0);
$view_mode= $_GET['view'] ?? 'daily'; // 'daily' or 'monthly'
$month_sel= $_GET['month'] ?? date('Y-m');

// Ensure staff_attendance table exists
$conn->query("CREATE TABLE IF NOT EXISTS staff_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present','Absent','Late','Half Day','Leave') NOT NULL DEFAULT 'Present',
    time_in TIME NULL,
    time_out TIME NULL,
    notes VARCHAR(255) NULL,
    marked_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_staff_date (staff_id, date),
    FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Handle POST request to save daily attendance
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $post_date = $_POST['att_date'] ?? $today;
    $attendance_data = $_POST['attendance'] ?? [];
    $uid = current_uid();

    $saved_count = 0;
    foreach ($attendance_data as $st_id => $data) {
        $st_id    = (int)$st_id;
        $status   = $conn->real_escape_string($data['status'] ?? 'Present');
        $time_in  = !empty($data['time_in']) ? "'".$conn->real_escape_string($data['time_in'])."'" : "NULL";
        $time_out = !empty($data['time_out']) ? "'".$conn->real_escape_string($data['time_out'])."'" : "NULL";
        $notes    = $conn->real_escape_string(trim($data['notes'] ?? ''));

        $sql = "INSERT INTO staff_attendance (staff_id, date, status, time_in, time_out, notes, marked_by)
                VALUES ($st_id, '$post_date', '$status', $time_in, $time_out, '$notes', $uid)
                ON DUPLICATE KEY UPDATE 
                status='$status', time_in=$time_in, time_out=$time_out, notes='$notes', marked_by=$uid";
        if ($conn->query($sql)) {
            $saved_count++;
        }
    }
    $msg = "Staff attendance saved successfully for " . date('d M Y', strtotime($post_date)) . " ($saved_count records).";
    $att_date = $post_date;
}

// Handle QR Scan POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_scan_attendance'])) {
    header('Content-Type: application/json');
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $staff_code = $conn->real_escape_string(trim($_POST['staff_code'] ?? ''));
    $scan_date = date('Y-m-d');
    $scan_time = date('H:i:s');
    $uid = current_uid();

    if ($staff_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
        exit;
    }

    // Verify staff exists
    $staff_check = $conn->query("SELECT id, name, staff_code FROM staff_members WHERE id = $staff_id AND status = 'Active'");
    if (!$staff_check || $staff_check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Staff member not found or inactive.']);
        exit;
    }
    $staff_info = $staff_check->fetch_assoc();

    // Check if already marked today
    $existing = $conn->query("SELECT id, status, time_in FROM staff_attendance WHERE staff_id = $staff_id AND date = '$scan_date'");
    if ($existing && $existing->num_rows > 0) {
        $ex = $existing->fetch_assoc();
        // Update time_out if already marked
        $conn->query("UPDATE staff_attendance SET time_out = '$scan_time', marked_by = $uid WHERE id = {$ex['id']}");
        echo json_encode([
            'success' => true,
            'message' => 'Time-Out updated for ' . $staff_info['name'],
            'staff_name' => $staff_info['name'],
            'staff_code' => $staff_info['staff_code'],
            'status' => $ex['status'],
            'action' => 'time_out',
            'time' => date('h:i A')
        ]);
    } else {
        // Insert new attendance record as Present
        $conn->query("INSERT INTO staff_attendance (staff_id, date, status, time_in, notes, marked_by) 
                      VALUES ($staff_id, '$scan_date', 'Present', '$scan_time', 'QR Scan Entry', $uid)");
        echo json_encode([
            'success' => true,
            'message' => $staff_info['name'] . ' marked Present via QR scan',
            'staff_name' => $staff_info['name'],
            'staff_code' => $staff_info['staff_code'],
            'status' => 'Present',
            'action' => 'check_in',
            'time' => date('h:i A')
        ]);
    }
    exit;
}

// Fetch departments for filter
$depts_q = $conn->query("SELECT * FROM staff_departments ORDER BY name ASC");
$depts = [];
if ($depts_q) while($d = $depts_q->fetch_assoc()) $depts[] = $d;

// Build query for staff members
$where = ["sm.status = 'Active'"];
if ($dept_id > 0) {
    $where[] = "sm.department_id = $dept_id";
}
$where_sql = implode(' AND ', $where);

$staff_members = [];
$sm_q = $conn->query("
    SELECT sm.*, d.name as dept_name, des.title as desig_title
    FROM staff_members sm
    LEFT JOIN staff_departments d ON sm.department_id = d.id
    LEFT JOIN staff_designations des ON sm.designation_id = des.id
    WHERE $where_sql
    ORDER BY sm.staff_code ASC
");
if ($sm_q) while($row = $sm_q->fetch_assoc()) $staff_members[] = $row;

// Fetch existing attendance for selected date
$existing_att = [];
$att_q = $conn->query("SELECT * FROM staff_attendance WHERE date = '$att_date'");
if ($att_q) {
    while($r = $att_q->fetch_assoc()) {
        $existing_att[$r['staff_id']] = $r;
    }
}

// Daily stats
$total_staff = count($staff_members);
$cnt_present  = 0;
$cnt_absent   = 0;
$cnt_late     = 0;
$cnt_leave    = 0;

foreach ($staff_members as $st) {
    // If not scanned or marked, default to Absent
    $st_status = $existing_att[$st['id']]['status'] ?? 'Absent';
    if ($st_status === 'Present') $cnt_present++;
    elseif ($st_status === 'Absent') $cnt_absent++;
    elseif ($st_status === 'Late') $cnt_late++;
    elseif ($st_status === 'Leave' || $st_status === 'Half Day') $cnt_leave++;
}
?>
<style>
.att-status-group { display: flex; gap: 4px; border-radius: 6px; overflow: hidden; }
.att-status-btn { 
    flex: 1; padding: 6px 10px; font-size: 0.76rem; font-weight: 700; border: 1px solid var(--border); 
    background: var(--bg-secondary); color: var(--text-muted); cursor: pointer; text-align: center;
    transition: all var(--transition); user-select: none;
}
.att-status-btn input { display: none; }
.att-status-btn:hover { background: var(--bg-primary); }

.att-status-btn.st-present input:checked + span, .att-status-btn.st-present.active { background: #10b981; color: #fff; border-color: #10b981; }
.att-status-btn.st-absent input:checked + span, .att-status-btn.st-absent.active { background: #ef4444; color: #fff; border-color: #ef4444; }
.att-status-btn.st-late input:checked + span, .att-status-btn.st-late.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }
.att-status-btn.st-half input:checked + span, .att-status-btn.st-half.active { background: #0284c7; color: #fff; border-color: #0284c7; }
.att-status-btn.st-leave input:checked + span, .att-status-btn.st-leave.active { background: #8b5cf6; color: #fff; border-color: #8b5cf6; }

.stat-box {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 16px; display: flex; align-items: center; gap: 16px;
}
.stat-box-icon {
    width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
}
</style>

<div class="page-header">
    <div>
        <h1><i class="fa-solid fa-clipboard-user"></i> Staff Attendance</h1>
        <p>Manage daily attendance logs, entry/exit times, and monthly reports for school staff</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button onclick="openQrScanner()" class="btn" style="background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none; font-weight:700; display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:8px;">
            <i class="fa-solid fa-camera"></i> Scan QR Code
        </button>
        <a href="staff_attendance.php?view=daily" class="btn <?= $view_mode==='daily'?'btn-primary':'btn-secondary' ?>">
            <i class="fa-solid fa-calendar-day"></i> Daily Attendance
        </a>
        <a href="staff_attendance.php?view=monthly" class="btn <?= $view_mode==='monthly'?'btn-primary':'btn-secondary' ?>">
            <i class="fa-solid fa-calendar-days"></i> Monthly Log
        </a>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<?php if ($view_mode === 'daily'): ?>
<!-- DAILY ATTENDANCE INTERFACE -->

<!-- Date & Filter Header -->
<div class="card mb-3">
    <form method="GET" action="staff_attendance.php" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="view" value="daily">
        <div style="flex: 1; min-width: 180px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
                <i class="fa-solid fa-calendar"></i> Attendance Date
            </label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($att_date) ?>" onchange="this.form.submit()">
        </div>

        <div style="flex: 1; min-width: 200px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
                <i class="fa-solid fa-building"></i> Department
            </label>
            <select name="dept_id" class="form-control" onchange="this.form.submit()">
                <option value="">-- All Departments --</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $dept_id == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
            <button type="button" class="btn btn-success" onclick="markAllPresent()"><i class="fa-solid fa-check-double"></i> Mark All Present</button>
        </div>
    </form>
</div>

<!-- Daily Summary Counters -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="stat-box">
        <div class="stat-box-icon" style="background: rgba(99,102,241,.15); color: #6366f1;"><i class="fa-solid fa-users"></i></div>
        <div>
            <h3 style="margin:0; font-size:1.4rem; font-weight:800;"><?= $total_staff ?></h3>
            <p style="margin:0; font-size:0.78rem; color:var(--text-muted);">Total Staff</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-box-icon" style="background: rgba(16,185,129,.15); color: #10b981;"><i class="fa-solid fa-user-check"></i></div>
        <div>
            <h3 style="margin:0; font-size:1.4rem; font-weight:800; color:#10b981;"><?= $cnt_present ?></h3>
            <p style="margin:0; font-size:0.78rem; color:var(--text-muted);">Present</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-box-icon" style="background: rgba(239,68,68,.15); color: #ef4444;"><i class="fa-solid fa-user-xmark"></i></div>
        <div>
            <h3 style="margin:0; font-size:1.4rem; font-weight:800; color:#ef4444;"><?= $cnt_absent ?></h3>
            <p style="margin:0; font-size:0.78rem; color:var(--text-muted);">Absent</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-box-icon" style="background: rgba(245,158,11,.15); color: #f59e0b;"><i class="fa-solid fa-clock"></i></div>
        <div>
            <h3 style="margin:0; font-size:1.4rem; font-weight:800; color:#f59e0b;"><?= $cnt_late ?></h3>
            <p style="margin:0; font-size:0.78rem; color:var(--text-muted);">Late Entry</p>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-box-icon" style="background: rgba(139,92,246,.15); color: #8b5cf6;"><i class="fa-solid fa-plane-departure"></i></div>
        <div>
            <h3 style="margin:0; font-size:1.4rem; font-weight:800; color:#8b5cf6;"><?= $cnt_leave ?></h3>
            <p style="margin:0; font-size:0.78rem; color:var(--text-muted);">Leave / Half Day</p>
        </div>
    </div>
</div>

<!-- Attendance Form -->
<form method="POST" action="staff_attendance.php?date=<?= urlencode($att_date) ?>&dept_id=<?= $dept_id ?>">
    <input type="hidden" name="att_date" value="<?= htmlspecialchars($att_date) ?>">
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-list-check"></i> Attendance Register for <?= date('l, d F Y', strtotime($att_date)) ?></h3>
            <span class="text-muted" style="font-size:0.8rem">Select status &amp; optional in/out times for each staff member</span>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">Staff Code</th>
                        <th>Staff Member</th>
                        <th>Department</th>
                        <th style="width: 320px; text-align: center;">Status</th>
                        <th style="width: 130px;">Time In</th>
                        <th style="width: 130px;">Time Out</th>
                        <th>Notes / Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staff_members)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                            No active staff members found.
                        </td>
                    </tr>
                    <?php else: foreach ($staff_members as $st): 
                        $cur = $existing_att[$st['id']] ?? null;
                        // If not scanned or marked, default to Absent
                        $status = $cur['status'] ?? 'Absent';
                        $t_in   = $cur['time_in'] ?? ($status === 'Present' ? '08:00' : '');
                        $t_out  = $cur['time_out'] ?? ($status === 'Present' ? '14:00' : '');
                        $notes  = $cur['notes'] ?? '';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($st['staff_code']) ?></strong></td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($st['name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($st['desig_title'] ?? 'Staff') ?></small>
                        </td>
                        <td><?= htmlspecialchars($st['dept_name'] ?? 'General') ?></td>
                        <td>
                            <div class="att-status-group">
                                <label class="att-status-btn st-present">
                                    <input type="radio" name="attendance[<?= $st['id'] ?>][status]" value="Present" <?= $status==='Present'?'checked':'' ?>>
                                    <span>Present</span>
                                </label>
                                <label class="att-status-btn st-absent">
                                    <input type="radio" name="attendance[<?= $st['id'] ?>][status]" value="Absent" <?= $status==='Absent'?'checked':'' ?>>
                                    <span>Absent</span>
                                </label>
                                <label class="att-status-btn st-late">
                                    <input type="radio" name="attendance[<?= $st['id'] ?>][status]" value="Late" <?= $status==='Late'?'checked':'' ?>>
                                    <span>Late</span>
                                </label>
                                <label class="att-status-btn st-half">
                                    <input type="radio" name="attendance[<?= $st['id'] ?>][status]" value="Half Day" <?= $status==='Half Day'?'checked':'' ?>>
                                    <span>Half</span>
                                </label>
                                <label class="att-status-btn st-leave">
                                    <input type="radio" name="attendance[<?= $st['id'] ?>][status]" value="Leave" <?= $status==='Leave'?'checked':'' ?>>
                                    <span>Leave</span>
                                </label>
                            </div>
                        </td>
                        <td>
                            <input type="time" name="attendance[<?= $st['id'] ?>][time_in]" class="form-control form-control-sm" value="<?= htmlspecialchars($t_in) ?>">
                        </td>
                        <td>
                            <input type="time" name="attendance[<?= $st['id'] ?>][time_out]" class="form-control form-control-sm" value="<?= htmlspecialchars($t_out) ?>">
                        </td>
                        <td>
                            <input type="text" name="attendance[<?= $st['id'] ?>][notes]" class="form-control form-control-sm" placeholder="Optional notes..." value="<?= htmlspecialchars($notes) ?>">
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($staff_members)): ?>
        <div style="padding: 16px 20px; border-top: 1px solid var(--border); text-align: right;">
            <button type="submit" name="save_attendance" value="1" class="btn btn-primary" style="padding: 10px 30px; font-weight: 700;">
                <i class="fa-solid fa-save"></i> Save Attendance Records
            </button>
        </div>
        <?php endif; ?>
    </div>
</form>



<?php else: ?>
<!-- MONTHLY LOG REPORT INTERFACE -->
<div class="card mb-3">
    <form method="GET" action="staff_attendance.php" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="view" value="monthly">
        <div style="flex: 1; min-width: 200px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
                <i class="fa-solid fa-calendar-days"></i> Select Month
            </label>
            <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month_sel) ?>" onchange="this.form.submit()">
        </div>
        <div style="flex: 1; min-width: 200px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
                <i class="fa-solid fa-building"></i> Department
            </label>
            <select name="dept_id" class="form-control" onchange="this.form.submit()">
                <option value="">-- All Departments --</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $dept_id == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> View Log</button>
            <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Monthly Log</button>
        </div>
    </form>
</div>

<?php
$days_in_month = date('t', strtotime($month_sel . '-01'));
$month_start   = $month_sel . '-01';
$month_end     = $month_sel . '-' . $days_in_month;

// Fetch all attendance for this month
$m_att_q = $conn->query("SELECT * FROM staff_attendance WHERE date BETWEEN '$month_start' AND '$month_end'");
$m_att = [];
if ($m_att_q) {
    while ($r = $m_att_q->fetch_assoc()) {
        $m_att[$r['staff_id']][$r['date']] = $r['status'];
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-table"></i> Monthly Attendance Summary — <?= date('F Y', strtotime($month_sel . '-01')) ?></h3>
    </div>
    <div class="table-wrapper" style="overflow-x: auto;">
        <table class="data-table" style="font-size: 0.78rem;">
            <thead>
                <tr>
                    <th style="min-width: 140px;">Staff Name</th>
                    <?php for($d = 1; $d <= $days_in_month; $d++): ?>
                    <th style="text-align: center; min-width: 28px; padding: 4px;"><?= $d ?></th>
                    <?php endfor; ?>
                    <th style="text-align: center;">P</th>
                    <th style="text-align: center;">A</th>
                    <th style="text-align: center;">L</th>
                    <th style="text-align: center;">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_members as $st): 
                    $p_cnt = 0; $a_cnt = 0; $l_cnt = 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($st['name']) ?></strong></td>
                    <?php for($d = 1; $d <= $days_in_month; $d++): 
                        $day_str = sprintf('%s-%02d', $month_sel, $d);
                        $day_ts  = strtotime($day_str);
                        $is_sunday = date('N', $day_ts) == 7;
                        $is_past_or_today = $day_str <= $today;
                        $st_val  = $m_att[$st['id']][$day_str] ?? null;
                        
                        $char = '-'; $color = '#94a3b8';
                        if ($st_val === 'Present') { $char = 'P'; $color = '#10b981'; $p_cnt++; }
                        elseif ($st_val === 'Absent') { $char = 'A'; $color = '#ef4444'; $a_cnt++; }
                        elseif ($st_val === 'Late') { $char = 'L'; $color = '#f59e0b'; $l_cnt++; }
                        elseif ($st_val === 'Half Day') { $char = 'H'; $color = '#0284c7'; $p_cnt += 0.5; }
                        elseif ($st_val === 'Leave') { $char = 'V'; $color = '#8b5cf6'; }
                        elseif ($is_sunday) {
                            $char = 'Sun'; $color = '#cbd5e1';
                        } elseif ($is_past_or_today) {
                            // Working day in the past or today where staff didn't scan or mark attendance -> Absent
                            $char = 'A'; $color = '#ef4444'; $a_cnt++;
                        }
                    ?>
                    <td style="text-align: center; font-weight: 800; color: <?= $color ?>; padding: 4px;"><?= $char ?></td>
                    <?php endfor; ?>

                    <?php 
                    $total_days = $p_cnt + $a_cnt + $l_cnt;
                    $pct = $total_days > 0 ? round(($p_cnt / $total_days) * 100) : 0;
                    ?>
                    <td style="text-align: center; font-weight: 700; color: #10b981;"><?= $p_cnt ?></td>
                    <td style="text-align: center; font-weight: 700; color: #ef4444;"><?= $a_cnt ?></td>
                    <td style="text-align: center; font-weight: 700; color: #f59e0b;"><?= $l_cnt ?></td>
                    <td style="text-align: center; font-weight: 800; color: <?= $pct>=75?'#10b981':'#ef4444' ?>"><?= $pct ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- QR SCANNER MODAL -->
<div id="qrScannerModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.75); z-index:1060; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; max-width:520px; width:95%; box-shadow:0 25px 50px rgba(0,0,0,0.3); overflow:hidden; max-height:92vh; display:flex; flex-direction:column;">
        <!-- Header -->
        <div style="background:linear-gradient(135deg,#f59e0b,#d97706); padding:18px 24px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; color:#fff; font-size:1.05rem; font-weight:700;"><i class="fa-solid fa-camera" style="margin-right:8px;"></i> QR Attendance Scanner</h3>
            <button onclick="closeQrScanner()" style="background:rgba(255,255,255,0.2); border:none; color:#fff; cursor:pointer; font-size:1.1rem; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Scanner Area -->
        <div style="padding:20px;">
            <div id="qr_reader" style="width:100%; border-radius:12px; overflow:hidden; border:3px solid var(--border);"></div>
            
            <!-- Scan Status -->
            <div id="scanStatus" style="margin-top:16px; display:none;">
                <div id="scanStatusContent" style="padding:14px 18px; border-radius:10px; display:flex; align-items:center; gap:12px; font-weight:600;"></div>
            </div>

            <!-- Scan Log -->
            <div style="margin-top:16px;">
                <h4 style="margin:0 0 10px 0; font-size:0.85rem; font-weight:700; color:var(--text-secondary); display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-list-check"></i> Scan Log <span id="scanCount" style="background:#f59e0b; color:#fff; padding:2px 8px; border-radius:10px; font-size:0.72rem;">0</span>
                </h4>
                <div id="scanLog" style="max-height:200px; overflow-y:auto; border:1px solid var(--border); border-radius:10px; background:var(--bg-secondary);">
                    <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:0.82rem;">
                        <i class="fa-solid fa-qrcode" style="font-size:2rem; display:block; margin-bottom:8px; opacity:0.3;"></i>
                        Point camera at a staff QR code to scan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- html5-qrcode Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
function markAllPresent() {
    document.querySelectorAll('.st-present input[type="radio"]').forEach(function(radio) {
        radio.checked = true;
    });
}

// ========== QR SCANNER ==========
let html5QrScanner = null;
let scannedStaffIds = new Set();
let scanLogCount = 0;

function openQrScanner() {
    document.getElementById('qrScannerModal').style.display = 'flex';
    scannedStaffIds.clear();
    scanLogCount = 0;
    document.getElementById('scanCount').textContent = '0';
    document.getElementById('scanLog').innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:0.82rem;"><i class="fa-solid fa-qrcode" style="font-size:2rem; display:block; margin-bottom:8px; opacity:0.3;"></i>Point camera at a staff QR code to scan</div>';
    document.getElementById('scanStatus').style.display = 'none';

    // Initialize scanner
    html5QrScanner = new Html5Qrcode('qr_reader');
    html5QrScanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 },
        onQrCodeSuccess,
        function() {} // silence errors
    ).catch(function(err) {
        // Try front camera as fallback
        html5QrScanner.start(
            { facingMode: 'user' },
            { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 },
            onQrCodeSuccess,
            function() {}
        ).catch(function(err2) {
            document.getElementById('qr_reader').innerHTML = '<div style="padding:40px; text-align:center; color:#ef4444;"><i class="fa-solid fa-video-slash" style="font-size:2rem; display:block; margin-bottom:10px;"></i><strong>Camera not available</strong><br><small>Please allow camera access or use a device with a camera.</small></div>';
        });
    });
}

function closeQrScanner() {
    if (html5QrScanner) {
        html5QrScanner.stop().then(function() {
            html5QrScanner.clear();
        }).catch(function() {});
    }
    document.getElementById('qrScannerModal').style.display = 'none';
}

function onQrCodeSuccess(decodedText) {
    try {
        const data = JSON.parse(decodedText);
        if (data.type !== 'SIAX_STAFF_ATT' || !data.staff_id) {
            showScanStatus('error', 'Invalid QR code format', 'fa-circle-exclamation');
            return;
        }

        // Prevent duplicate scans within same session
        if (scannedStaffIds.has(data.staff_id)) {
            showScanStatus('warning', data.name + ' already scanned', 'fa-triangle-exclamation');
            return;
        }
        scannedStaffIds.add(data.staff_id);

        // Play success sound 
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            oscillator.frequency.value = 880;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.3;
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.15);
        } catch(e) {}

        // Send AJAX to mark attendance
        showScanStatus('loading', 'Processing ' + data.name + '...', 'fa-spinner fa-spin');

        const formData = new FormData();
        formData.append('qr_scan_attendance', '1');
        formData.append('staff_id', data.staff_id);
        formData.append('staff_code', data.staff_code);

        fetch('staff_attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                const isCheckIn = result.action === 'check_in';
                showScanStatus(
                    'success',
                    result.message,
                    isCheckIn ? 'fa-user-check' : 'fa-right-from-bracket'
                );
                addScanLogEntry(result);
            } else {
                showScanStatus('error', result.message, 'fa-circle-exclamation');
                scannedStaffIds.delete(data.staff_id);
            }
        })
        .catch(err => {
            showScanStatus('error', 'Network error. Please try again.', 'fa-wifi');
            scannedStaffIds.delete(data.staff_id);
        });

    } catch(e) {
        showScanStatus('error', 'Not a valid SIAX QR code', 'fa-circle-exclamation');
    }
}

function showScanStatus(type, message, icon) {
    const el = document.getElementById('scanStatus');
    const content = document.getElementById('scanStatusContent');
    el.style.display = 'block';

    const colors = {
        success: { bg: 'rgba(16,185,129,0.12)', border: '#10b981', text: '#10b981' },
        error: { bg: 'rgba(239,68,68,0.12)', border: '#ef4444', text: '#ef4444' },
        warning: { bg: 'rgba(245,158,11,0.12)', border: '#f59e0b', text: '#f59e0b' },
        loading: { bg: 'rgba(99,102,241,0.12)', border: '#6366f1', text: '#6366f1' }
    };
    const c = colors[type] || colors.loading;
    content.style.background = c.bg;
    content.style.border = '1px solid ' + c.border;
    content.style.color = c.text;
    content.innerHTML = '<i class="fa-solid ' + icon + '" style="font-size:1.2rem;"></i> <span>' + message + '</span>';

    // Auto-hide after 3s
    if (type !== 'loading') {
        setTimeout(() => { el.style.display = 'none'; }, 3000);
    }
}

function addScanLogEntry(result) {
    const log = document.getElementById('scanLog');
    scanLogCount++;
    document.getElementById('scanCount').textContent = scanLogCount;

    // Clear placeholder if first entry
    if (scanLogCount === 1) {
        log.innerHTML = '';
    }

    const isCheckIn = result.action === 'check_in';
    const entry = document.createElement('div');
    entry.style.cssText = 'padding:12px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; animation: slideIn 0.3s ease;';
    entry.innerHTML = 
        '<div style="width:36px; height:36px; border-radius:50%; background:' + (isCheckIn ? 'rgba(16,185,129,0.15)' : 'rgba(99,102,241,0.15)') + '; display:flex; align-items:center; justify-content:center; color:' + (isCheckIn ? '#10b981' : '#6366f1') + '; font-size:0.9rem; flex-shrink:0;">' +
        '<i class="fa-solid ' + (isCheckIn ? 'fa-user-check' : 'fa-right-from-bracket') + '"></i></div>' +
        '<div style="flex:1; min-width:0;">' +
        '<div style="font-weight:700; font-size:0.85rem; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + result.staff_name + '</div>' +
        '<div style="font-size:0.72rem; color:var(--text-muted);">' + result.staff_code + ' &bull; ' + (isCheckIn ? 'Check In' : 'Time Out') + '</div>' +
        '</div>' +
        '<div style="text-align:right; flex-shrink:0;">' +
        '<span style="background:' + (isCheckIn ? '#10b981' : '#6366f1') + '; color:#fff; padding:3px 10px; border-radius:6px; font-size:0.7rem; font-weight:700;">' + result.time + '</span>' +
        '</div>';

    log.insertBefore(entry, log.firstChild);
}
</script>

<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
