<?php
/**
 * SIAC SMSS - Advance Fee Management
 * Allows adding advance monthly fees for students that get included in invoices/receipts.
 */
$page_title = 'Advance Fee';
$active_page = 'advance_fee';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS advance_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  invoice_id INT DEFAULT NULL,
  advance_month VARCHAR(30) NOT NULL,
  advance_year INT NOT NULL,
  amount DECIMAL(10,2) DEFAULT 0,
  status ENUM('Pending','Applied') DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  applied_at DATETIME DEFAULT NULL,
  notes TEXT DEFAULT NULL
)");

$msg = ''; $err = '';

// Handle Add Advance Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_advance'])) {
    $student_id = (int)$_POST['student_id'];
    $class_id = (int)$_POST['class_id'];
    $months = $_POST['advance_months'] ?? [];
    $amount = (float)$_POST['amount'];
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');

    if ($student_id > 0 && $amount > 0 && !empty($months)) {
        $count = 0;
        foreach ($months as $m) {
            $parts = explode('-', $m);
            $month_name = $conn->real_escape_string($parts[0]);
            $year = (int)$parts[1];
            
            // Check if already exists
            $exists = $conn->query("SELECT id FROM advance_fees WHERE student_id=$student_id AND advance_month='$month_name' AND advance_year=$year AND status='Pending'");
            if ($exists && $exists->num_rows > 0) continue;
            
            $conn->query("INSERT INTO advance_fees (student_id, class_id, advance_month, advance_year, amount, notes) 
                          VALUES ($student_id, $class_id, '$month_name', $year, $amount, '$notes')");
            $count++;
        }
        $msg = "Advance fee for $count month(s) added successfully! Amount: " . number_format($amount) . " per month.";
    } else {
        $err = "Please select a student, at least one month, and enter a valid amount.";
    }
}

// Handle Delete
if (isset($_GET['delete_adv'])) {
    $del_id = (int)$_GET['delete_adv'];
    $conn->query("DELETE FROM advance_fees WHERE id=$del_id AND status='Pending'");
    $cid = (int)($_GET['class_id'] ?? 0);
    header("Location: " . BASE_URL . "modules/fees/advance_fee.php?class_id=$cid&msg=deleted"); exit;
}
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $msg = 'Advance fee entry deleted.';

// Fetch classes (Naturally sorted)
$classes_arr = get_all_classes($conn);


$sel_class_id = (int)($_GET['class_id'] ?? ($classes_arr[0]['id'] ?? 0));
$sel_class_info = null;
foreach ($classes_arr as $c) { if ($c['id'] == $sel_class_id) { $sel_class_info = $c; break; } }

// Fetch students in selected class with their advance fees
$active_session = $settings['session_year'] ?? '2025-2026';
$students_q = null;
if ($sel_class_id) {
    $students_q = $conn->query("
        SELECT s.*, c.name as class_name, c.section, se.roll_no as enrollment_roll_no
        FROM student_enrollments se 
        JOIN students s ON se.student_id = s.id 
        LEFT JOIN classes c ON se.class_id = c.id 
        WHERE se.class_id = $sel_class_id AND se.session_year = '$active_session' AND se.status = 'Active' AND s.status = 'Active'
        ORDER BY (se.roll_no+0), s.name
    ");
}

// Fetch all pending advance fees for display
$advance_fees_q = $conn->query("
    SELECT af.*, s.name as student_name, s.admission_no, s.father_name, c.name as class_name, c.section
    FROM advance_fees af
    JOIN students s ON af.student_id = s.id
    JOIN classes c ON af.class_id = c.id
    WHERE af.class_id = $sel_class_id
    ORDER BY af.status ASC, af.advance_year DESC, FIELD(af.advance_month, 'January','February','March','April','May','June','July','August','September','October','November','December') DESC
");

// Generate month options for next 12 months
$month_options = [];
$current_month = (int)date('n');
$current_year = (int)date('Y');
for ($i = 1; $i <= 12; $i++) {
    $m = ($current_month + $i - 1) % 12 + 1;
    $y = $current_year + intdiv($current_month + $i - 1, 13);
    if ($m <= $current_month && $i > 1) $y = $current_year + 1;
    // Recalculate properly
    $dt = new DateTime();
    $dt->modify("+$i months");
    $month_options[] = [
        'value' => $dt->format('F') . '-' . $dt->format('Y'),
        'label' => $dt->format('F Y')
    ];
}
// Also add current month
$current_dt = new DateTime();
array_unshift($month_options, [
    'value' => $current_dt->format('F') . '-' . $current_dt->format('Y'),
    'label' => $current_dt->format('F Y') . ' (Current)'
]);
?>

<style>
/* Class Tabs */
.adv-class-tabs{display:flex;overflow-x:auto;scrollbar-width:thin;scrollbar-color:var(--accent) transparent;border-bottom:1px solid var(--border);padding:0 8px;align-items:center;background:var(--bg-secondary);border-radius:var(--radius) var(--radius) 0 0}
.adv-class-tab{flex-shrink:0;padding:10px 16px;font-size:.8rem;font-weight:600;color:var(--text-muted);cursor:pointer;border-bottom:3px solid transparent;transition:all .2s;white-space:nowrap;text-decoration:none;display:block}
.adv-class-tab:hover{color:var(--text-primary);border-bottom-color:rgba(99,102,241,.3)}
.adv-class-tab.active{color:#f59e0b;border-bottom-color:#f59e0b;background:rgba(245,158,11,.05)}

/* Stats Cards */
.adv-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:25px}
.adv-stat-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;display:flex;align-items:center;gap:15px}
.adv-stat-icon{width:45px;height:45px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.adv-stat-value{font-size:1.4rem;font-weight:800;color:var(--text-primary)}
.adv-stat-label{font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px}

/* Advance Entry Table */
.adv-badge{padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.adv-badge-pending{background:rgba(245,158,11,.12);color:#f59e0b}
.adv-badge-applied{background:rgba(16,185,129,.12);color:#10b981}

/* Month Chips Selector */
.month-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.month-chip{padding:8px 14px;border-radius:20px;font-size:.78rem;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--bg-secondary);color:var(--text-secondary);transition:all .2s;user-select:none}
.month-chip:hover{border-color:#f59e0b;color:#f59e0b}
.month-chip.selected{background:rgba(245,158,11,.15);color:#f59e0b;border-color:#f59e0b;font-weight:700}
.month-chip input{display:none}
</style>

<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:12px"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-error" style="margin-bottom:12px"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="page-header">
    <div>
        <h1><i class="fa-solid fa-forward" style="color:#f59e0b"></i> Advance Fee</h1>
        <p>Add advance monthly fees for students — they'll appear directly in future invoices & receipts</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addAdvanceModal').style.display='flex'" style="background:linear-gradient(135deg,#f59e0b,#d97706);border:none">
        <i class="fa-solid fa-plus-circle"></i> Add Advance Fee
    </button>
</div>

<!-- CLASS TABS -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px;overflow:hidden">
    <div class="adv-class-tabs">
        <?php foreach($classes_arr as $c): ?>
        <a href="?class_id=<?= $c['id'] ?>" class="adv-class-tab <?= $sel_class_id==$c['id']?'active':'' ?>">
            <?= htmlspecialchars($c['name'].' '.$c['section']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- STATS -->
<?php
$total_pending_q = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM advance_fees WHERE class_id=$sel_class_id AND status='Pending'");
$total_pending = $total_pending_q->fetch_assoc();
$total_applied_q = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM advance_fees WHERE class_id=$sel_class_id AND status='Applied'");
$total_applied = $total_applied_q->fetch_assoc();
?>
<div class="adv-stats">
    <div class="adv-stat-card">
        <div class="adv-stat-icon" style="background:rgba(245,158,11,.12);color:#f59e0b"><i class="fa-solid fa-clock"></i></div>
        <div>
            <div class="adv-stat-value"><?= $total_pending['cnt'] ?></div>
            <div class="adv-stat-label">Pending Entries</div>
        </div>
    </div>
    <div class="adv-stat-card">
        <div class="adv-stat-icon" style="background:rgba(59,130,246,.12);color:#3b82f6"><i class="fa-solid fa-indian-rupee-sign"></i></div>
        <div>
            <div class="adv-stat-value"><?= number_format($total_pending['total'], 0) ?></div>
            <div class="adv-stat-label">Pending Amount</div>
        </div>
    </div>
    <div class="adv-stat-card">
        <div class="adv-stat-icon" style="background:rgba(16,185,129,.12);color:#10b981"><i class="fa-solid fa-check-circle"></i></div>
        <div>
            <div class="adv-stat-value"><?= $total_applied['cnt'] ?></div>
            <div class="adv-stat-label">Applied to Invoices</div>
        </div>
    </div>
    <div class="adv-stat-card">
        <div class="adv-stat-icon" style="background:rgba(139,92,246,.12);color:#8b5cf6"><i class="fa-solid fa-coins"></i></div>
        <div>
            <div class="adv-stat-value"><?= number_format($total_applied['total'], 0) ?></div>
            <div class="adv-stat-label">Total Applied Amount</div>
        </div>
    </div>
</div>

<!-- ADVANCE FEES TABLE -->
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <div style="font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-list" style="color:#f59e0b"></i>
            Advance Fee Entries
            <?php if($sel_class_info): ?>
                <span style="font-weight:400;color:var(--text-muted)">— <?= htmlspecialchars($sel_class_info['name'].' '.$sel_class_info['section']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrapper" style="border:none">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Reg#</th>
                    <th>Father Name</th>
                    <th>Advance Month</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($advance_fees_q && $advance_fees_q->num_rows > 0): $i=1; while($af = $advance_fees_q->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($af['student_name']) ?></strong></td>
                        <td><span style="font-size:.8rem;font-weight:600"><?= htmlspecialchars($af['admission_no']) ?></span></td>
                        <td><?= htmlspecialchars($af['father_name'] ?? '-') ?></td>
                        <td>
                            <span style="font-weight:700;color:var(--accent-light)"><?= htmlspecialchars($af['advance_month']) ?> <?= $af['advance_year'] ?></span>
                        </td>
                        <td style="font-weight:700"><?= number_format($af['amount'], 0) ?></td>
                        <td>
                            <span class="adv-badge <?= $af['status']==='Applied' ? 'adv-badge-applied' : 'adv-badge-pending' ?>">
                                <i class="fa-solid fa-<?= $af['status']==='Applied' ? 'check' : 'clock' ?>"></i>
                                <?= $af['status'] ?>
                            </span>
                        </td>
                        <td style="font-size:.78rem;color:var(--text-muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($af['notes'] ?? '-') ?></td>
                        <td>
                            <?php if($af['status'] === 'Pending'): ?>
                                <a href="?class_id=<?= $sel_class_id ?>&delete_adv=<?= $af['id'] ?>" 
                                   onclick="return confirm('Delete this advance fee entry?')"
                                   style="color:#ef4444;font-size:.8rem;text-decoration:none;font-weight:600">
                                    <i class="fa-solid fa-trash-can"></i> Delete
                                </a>
                            <?php else: ?>
                                <span style="font-size:.72rem;color:var(--text-muted)">Invoice #<?= $af['invoice_id'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="9" class="text-center text-muted" style="padding:50px">
                        <i class="fa-solid fa-inbox" style="font-size:2rem;margin-bottom:10px;display:block;opacity:.3"></i>
                        No advance fee entries found for this class.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD ADVANCE FEE MODAL -->
<div id="addAdvanceModal" class="modal-overlay" style="display:none">
    <div class="modal-box" style="max-width:550px">
        <div class="modal-header">
            <h3><i class="fa-solid fa-forward" style="color:#f59e0b"></i> Add Advance Fee</h3>
            <button class="modal-close" onclick="document.getElementById('addAdvanceModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="advanceFeeForm">
                <input type="hidden" name="add_advance" value="1">
                <input type="hidden" name="class_id" value="<?= $sel_class_id ?>">
                
                <div class="form-group">
                    <label>Select Student *</label>
                    <select name="student_id" class="form-control" required id="advStudentSelect">
                        <option value="">-- Choose Student --</option>
                        <?php if ($students_q): $students_q->data_seek(0); while($s = $students_q->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>" data-fee="<?= $s['tuition_fee'] ?? 0 ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['admission_no']) ?>)
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Advance Month(s) *</label>
                    <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:6px">Click months to select. Each month will be added as a separate fee line item.</p>
                    <div class="month-chips" id="monthChips">
                        <?php foreach($month_options as $mo): ?>
                            <label class="month-chip" onclick="event.preventDefault(); var cb = this.querySelector('input'); cb.checked = !cb.checked; this.classList.toggle('selected', cb.checked); cb.dispatchEvent(new Event('change'));">
                                <input type="checkbox" name="advance_months[]" value="<?= $mo['value'] ?>">
                                <?= $mo['label'] ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row" style="margin-top:15px">
                    <div class="form-group">
                        <label>Amount Per Month (Rs.) *</label>
                        <input type="number" name="amount" class="form-control" required placeholder="e.g. 3000" min="1" id="advAmount">
                        <span style="font-size:.7rem;color:var(--text-muted)">This amount will be added for each selected month</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes / Remarks</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Advance fee for summer vacation months"></textarea>
                </div>

                <!-- Live Preview -->
                <div id="advPreview" style="display:none;margin-top:15px;padding:15px;background:var(--bg-secondary);border-radius:var(--radius);border:1px solid var(--border)">
                    <div style="font-weight:700;font-size:.82rem;margin-bottom:8px;color:#f59e0b"><i class="fa-solid fa-eye"></i> Preview</div>
                    <div id="advPreviewContent" style="font-size:.8rem;color:var(--text-secondary)"></div>
                </div>

                <div class="btn-group mt-3" style="justify-content:flex-end">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addAdvanceModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#f59e0b;border-color:#f59e0b"><i class="fa-solid fa-plus"></i> Add Advance Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Month chip toggle
document.querySelectorAll('.month-chip input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', updatePreview);
});
document.getElementById('advAmount')?.addEventListener('input', updatePreview);
document.getElementById('advStudentSelect')?.addEventListener('change', updatePreview);

function updatePreview() {
    const student = document.getElementById('advStudentSelect');
    const amount = parseFloat(document.getElementById('advAmount')?.value || 0);
    const selected = document.querySelectorAll('.month-chip input:checked');
    const preview = document.getElementById('advPreview');
    const content = document.getElementById('advPreviewContent');
    
    if (student.value && amount > 0 && selected.length > 0) {
        const studentName = student.options[student.selectedIndex].text;
        let html = `<strong>${studentName}</strong><br>`;
        html += `<div style="margin-top:8px">`;
        selected.forEach(cb => {
            const label = cb.parentElement.textContent.trim();
            html += `<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
                <span>Advance Fee — ${label}</span>
                <strong>Rs. ${amount.toLocaleString()}</strong>
            </div>`;
        });
        html += `<div style="display:flex;justify-content:space-between;padding:6px 0;font-weight:800;color:#f59e0b;border-top:2px solid var(--border);margin-top:4px">
            <span>Total Advance</span>
            <span>Rs. ${(amount * selected.length).toLocaleString()}</span>
        </div></div>`;
        content.innerHTML = html;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

// Close modal on backdrop click
document.getElementById('addAdvanceModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
