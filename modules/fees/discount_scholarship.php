<?php
/**
 * SIAX SMSS - Discount & Scholarships Management
 * Enhanced with Current Fee and Fee After Scholarship real-time calculations.
 */
$page_title = 'Discount & Scholarships';
$active_page = 'discount_scholarship';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

// Ensure database columns exist
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS discount_type ENUM('Fixed','Percentage') DEFAULT 'Fixed'");

$msg = ''; $err = '';

// Handle Bulk Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_discounts'])) {
    $discount_data = $_POST['discounts'] ?? [];
    if (!empty($discount_data)) {
        $active_session = $settings['session_year'] ?? '2025-2026';
        foreach ($discount_data as $sid => $data) {
            $amount = (float)($data['amount'] ?? 0);
            $type = $conn->real_escape_string($data['type'] ?? 'Fixed');
            $sid = (int)$sid;
            
            // 1. Update student profile
            if (!$conn->query("UPDATE students SET discount_amount = $amount, discount_type = '$type' WHERE id = $sid")) {
                $err = "Error updating record for student ID $sid: " . $conn->error;
            }
            
            // 2. Also update open monthly fee records in active session so invoices reflect the scholarship
            $smfq = $conn->query("SELECT id, tuition_fee, admission_fee, prev_pending, fine_amount, paid_amount FROM student_monthly_fees WHERE student_id = $sid AND status != 'Paid'");
            if ($smfq) {
                while ($mf = $smfq->fetch_assoc()) {
                    $mf_id = $mf['id'];
                    $tuition = (float)$mf['tuition_fee'];
                    $disc_val = ($type === 'Percentage') ? ($tuition * $amount / 100) : $amount;
                    
                    // Recalculate status
                    $payable = $tuition + (float)$mf['prev_pending'] + (float)($mf['admission_fee']??0) + (float)$mf['fine_amount'] - $disc_val;
                    $paid = (float)$mf['paid_amount'];
                    $new_status = 'Unpaid';
                    if ($paid >= $payable && $payable > 0) {
                        $new_status = 'Paid';
                    } elseif ($paid > 0) {
                        $new_status = 'Partially Paid';
                    }
                    
                    $conn->query("UPDATE student_monthly_fees SET discount_amount = $disc_val, status = '$new_status' WHERE id = $mf_id");
                }
            }
        }
        if (!$err) $msg = 'Discounts and scholarships updated and applied to student fee records successfully.';
    }
}

// Selection logic
$sel_class_id = (int)($_GET['class_id'] ?? 0);

// Fetch all classes for the selection grid (Naturally sorted)
$classes_all = get_all_classes($conn);
$classes_grouped = [];
foreach ($classes_all as $c) {
    $classes_grouped[$c['name']][] = $c;
}


// Fetch base fee structure for the selected class if set
$base_class_fee = 0;
$active_session = $settings['session_year'] ?? '2025-2026';
if ($sel_class_id > 0) {
    // 1. Check fee_criteria_details for standard_fee assigned to this class
    $fc_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE class_id = $sel_class_id AND standard_fee > 0 ORDER BY id DESC LIMIT 1");
    if ($fc_q && $fc_q->num_rows > 0) {
        $base_class_fee = (float)$fc_q->fetch_assoc()['standard_fee'];
    }

    // 2. Fallback to fee_structures if 0
    if ($base_class_fee <= 0) {
        $fs_q = $conn->query("
            SELECT SUM(fs.amount) as base_fee 
            FROM fee_structures fs 
            JOIN fee_heads fh ON fs.fee_head_id = fh.id 
            WHERE fs.class_id = $sel_class_id AND fs.session_year = '$active_session' AND fh.type = 'Monthly'
        ");
        if ($fs_q && $r = $fs_q->fetch_assoc()) {
            $base_class_fee = (float)($r['base_fee'] ?? 0);
        }
    }
}

// Fetch students for the selected class (session-aware via student_enrollments)
$students = [];
if ($sel_class_id > 0) {
    $students_q = $conn->query("
        SELECT s.*, c.name as class_name, c.section, se.roll_no as enrollment_roll_no
        FROM student_enrollments se 
        JOIN students s ON se.student_id = s.id 
        LEFT JOIN classes c ON se.class_id = c.id 
        WHERE se.class_id = $sel_class_id AND se.session_year = '$active_session' AND se.status = 'Active' AND s.status = 'Active'
        ORDER BY (se.roll_no+0), s.name
    ");
    if ($students_q) {
        while ($s = $students_q->fetch_assoc()) {
            // Find current monthly fee for this student (from recent invoice or fee criteria)
            $smf_q = $conn->query("SELECT tuition_fee FROM student_monthly_fees WHERE student_id = {$s['id']} AND tuition_fee > 0 ORDER BY id DESC LIMIT 1");
            $s_fee = ($smf_q && $smf_q->num_rows > 0) ? (float)$smf_q->fetch_assoc()['tuition_fee'] : 0;
            $s['current_fee'] = $s_fee > 0 ? $s_fee : $base_class_fee;
            $students[] = $s;
        }
    }
}
?>

<style>
/* Class Tabs - Horizontal scrollable */
.disc-class-tabs {
    display: flex;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--accent) transparent;
    border-bottom: 1px solid var(--border);
    padding: 0 8px;
    align-items: center;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
}
.disc-class-tab {
    flex-shrink: 0;
    padding: 10px 16px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
    white-space: nowrap;
    text-decoration: none;
    display: block;
}
.disc-class-tab:hover {
    color: var(--text-primary);
    border-bottom-color: rgba(99,102,241,.3);
}
.disc-class-tab.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
    background: var(--accent-glow);
    font-weight: 700;
}

.table-header-bar {
    background: var(--bg-secondary);
    padding: 12px 15px;
    border: 1px solid var(--border);
    border-bottom: none;
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.discount-input-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: 250px;
}
.discount-input-group input[type="number"] {
    width: 130px;
    padding: 6px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-weight: 700;
}
.discount-types {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.82rem;
}
.discount-types label {
    display: flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
}
.discount-types input[type="radio"] {
    accent-color: var(--accent);
}
.fixed-label { color: #27ae60; font-weight: 700; }

.save-all-btn {
    background: #00cec9;
    color: #fff;
    border: none;
    padding: 10px 40px;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 20px;
    float: right;
    box-shadow: 0 4px 12px rgba(0, 206, 201, 0.3);
    transition: transform 0.2s;
}
.save-all-btn:active { transform: scale(0.98); }
</style>

<div class="main-container">
    <div class="page-header">
        <h1>Discount &amp; Scholarships</h1>
    </div>

    <?php if($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:15px"><?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="login-error" style="margin-bottom:15px"><?= $err ?></div><?php endif; ?>

    <!-- CLASS TABS -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:25px;overflow:hidden">
        <div class="disc-class-tabs">
            <?php foreach ($classes_grouped as $cname => $sections): ?>
                <?php foreach ($sections as $sec): ?>
                    <a href="?class_id=<?= $sec['id'] ?>" class="disc-class-tab <?= $sel_class_id == $sec['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cname . ' ' . $sec['section']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($sel_class_id > 0): ?>
        <div class="table-header-bar">
            <i class="fa-solid fa-graduation-cap"></i>
            Student List (Selected Class)
        </div>

        <form method="POST" action="discount_scholarship.php?class_id=<?= $sel_class_id ?>">
            <input type="hidden" name="save_all_discounts" value="1">
            <div class="card" style="padding: 0; overflow: hidden; border-radius: 0 0 var(--radius-sm) var(--radius-sm);">
                <div class="table-wrapper" style="border: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>REG#</th>
                                <th>ROLLNO</th>
                                <th>NAME</th>
                                <th>FATHER NAME</th>
                                <th style="text-align: right;">CURRENT FEE</th>
                                <th>CONCESSION OR SCHOLARSHIP AMOUNT</th>
                                <th style="text-align: right;">FEE AFTER SCHOLARSHIP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): foreach($students as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['admission_no']) ?></td>
                                    <td><?= htmlspecialchars(!empty($s['enrollment_roll_no']) ? $s['enrollment_roll_no'] : ($s['roll_no'] ?? '-')) ?></td>
                                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($s['father_name'] ?? '-') ?></td>
                                    <td style="text-align: right; font-weight: 700; color: var(--text-primary);">
                                        Rs <span id="current_fee_<?= $s['id'] ?>"><?= number_format($s['current_fee'], 0) ?></span>
                                        <input type="hidden" class="current-fee-val" data-sid="<?= $s['id'] ?>" value="<?= $s['current_fee'] ?>">
                                    </td>
                                    <td>
                                        <div class="discount-input-group">
                                            <input type="number" 
                                                   name="discounts[<?= $s['id'] ?>][amount]" 
                                                   id="disc_amt_<?= $s['id'] ?>" 
                                                   value="<?= (float)$s['discount_amount'] ?>" 
                                                   step="0.01" 
                                                   class="disc-amount-input" 
                                                   data-sid="<?= $s['id'] ?>" 
                                                   oninput="calcFeeAfterScholarship(<?= $s['id'] ?>)">
                                            <div class="discount-types">
                                                <label>
                                                    <input type="radio" 
                                                           name="discounts[<?= $s['id'] ?>][type]" 
                                                           value="Percentage" 
                                                           <?= $s['discount_type'] === 'Percentage' ? 'checked' : '' ?> 
                                                           class="disc-type-radio" 
                                                           data-sid="<?= $s['id'] ?>" 
                                                           onchange="calcFeeAfterScholarship(<?= $s['id'] ?>)"> %
                                                </label>
                                                <label>
                                                    <input type="radio" 
                                                           name="discounts[<?= $s['id'] ?>][type]" 
                                                           value="Fixed" 
                                                           <?= $s['discount_type'] === 'Fixed' ? 'checked' : '' ?> 
                                                           class="disc-type-radio" 
                                                           data-sid="<?= $s['id'] ?>" 
                                                           onchange="calcFeeAfterScholarship(<?= $s['id'] ?>)"> <span class="fixed-label">Fixed Amount</span>
                                                </label>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <span style="font-size: 1.05rem; font-weight: 800; color: #10b981;">
                                            Rs <span id="final_fee_<?= $s['id'] ?>">0</span>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center text-muted" style="padding: 50px;">No students found in this class.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (count($students) > 0): ?>
                <button type="submit" class="save-all-btn">Save All</button>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <div class="card" style="padding: 40px; text-align: center; color: var(--text-muted);">
            <h3>Please select a class from the grid above to manage discounts.</h3>
        </div>
    <?php endif; ?>
</div>

<script>
function calcFeeAfterScholarship(sid) {
    const currentFeeEl = document.querySelector(`.current-fee-val[data-sid="${sid}"]`);
    if (!currentFeeEl) return;
    const currentFee = parseFloat(currentFeeEl.value) || 0;
    
    const amtInp = document.getElementById(`disc_amt_${sid}`);
    const amt = parseFloat(amtInp ? amtInp.value : 0) || 0;
    
    const selectedType = document.querySelector(`input[name="discounts[${sid}][type]"]:checked`);
    const type = selectedType ? selectedType.value : 'Fixed';
    
    let discountVal = 0;
    if (type === 'Percentage') {
        discountVal = currentFee * (amt / 100);
    } else {
        discountVal = amt;
    }
    
    const finalFee = Math.max(0, currentFee - discountVal);
    
    const finalEl = document.getElementById(`final_fee_${sid}`);
    if (finalEl) {
        finalEl.innerText = Math.round(finalFee).toLocaleString();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.current-fee-val').forEach(el => {
        const sid = el.getAttribute('data-sid');
        calcFeeAfterScholarship(sid);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
