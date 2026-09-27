<?php
/**
 * SIAX SMSS - Invoice Detail
 * Redesigned to the Premium SIAX Design System.
 */
ob_start();
$page_title = 'Invoice Detail';
$active_page = 'monthly_fee_invoices';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: " . BASE_URL . "modules/fees/monthly_fee_invoices.php");
    exit;
}

// Fetch Invoice
$invoice_q = $conn->query("SELECT * FROM fee_invoices WHERE id = $id");
$invoice = $invoice_q->fetch_assoc();
if (!$invoice) die("Invoice not found.");

// Fetch Classes for Selector (Only those included in this invoice)
$classes_q = $conn->query("SELECT DISTINCT c.name 
                           FROM student_monthly_fees sf 
                           JOIN classes c ON sf.class_id = c.id 
                           WHERE sf.invoice_id = $id 
                           ORDER BY c.name");

$sections_q = $conn->query("SELECT DISTINCT c.id, c.name, c.section 
                            FROM student_monthly_fees sf 
                            JOIN classes c ON sf.class_id = c.id 
                            WHERE sf.invoice_id = $id 
                            ORDER BY c.name, c.section");

$selected_class_id = (int)($_GET['class_id'] ?? 0);
if (!$selected_class_id) {
    // If no class selected, default to the first class in this invoice
    $first_class_q = $conn->query("SELECT class_id FROM student_monthly_fees WHERE invoice_id = $id LIMIT 1");
    if ($first_class_q && $first_class_q->num_rows > 0) {
        $selected_class_id = $first_class_q->fetch_assoc()['class_id'];
    }
}

// Fetch Students (session-aware)
$students_fees = [];
if ($selected_class_id) {
    $active_session = $settings['session_year'] ?? '2025-2026';
    $sf_q = $conn->query("
        SELECT sf.*, s.name as student_name, s.father_name, s.admission_no, se.roll_no as enrollment_roll_no, s.roll_no 
        FROM student_monthly_fees sf 
        JOIN students s ON sf.student_id = s.id 
        LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.session_year = '$active_session' AND se.status = 'Active' 
        WHERE sf.invoice_id = $id AND sf.class_id = $selected_class_id 
        ORDER BY s.name
    ");
    while ($row = $sf_q->fetch_assoc()) $students_fees[] = $row;
}

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_payment') {
    $mid = (int)$_POST['monthly_fee_id'];
    $amt_paid = (float)$_POST['amount_paid'];
    $fine_paid = (float)$_POST['fine_paid'];
    $waived_fee = isset($_POST['waive_fee']) ? (float)$_POST['waive_fee_val'] : 0;
    $waived_fine = isset($_POST['waive_fine']) ? (float)$_POST['waive_fine_val'] : 0;
    $p_date = $_POST['payment_date'];
    $status = $_POST['fee_status'];
    $uid = current_uid();

    $conn->query("INSERT INTO fee_payment_history (monthly_fee_id, amount_paid, fine_paid, waived_fee, waived_fine, payment_date, received_by) 
                  VALUES ($mid, $amt_paid, $fine_paid, $waived_fee, $waived_fine, '$p_date', $uid)");
    
    // Update main record
    $conn->query("UPDATE student_monthly_fees SET 
                  paid_amount = paid_amount + $amt_paid,
                  fine_amount = fine_amount - $fine_paid,
                  status = '$status',
                  payment_date = '$p_date'
                  WHERE id = $mid");

    // Sync fee_invoices.received_fee & waived_fee
    $conn->query("
        UPDATE fee_invoices fi
        SET fi.received_fee = (
            SELECT COALESCE(SUM(smf.paid_amount), 0)
            FROM student_monthly_fees smf
            WHERE smf.invoice_id = fi.id
        ),
        fi.waived_fee = (
            SELECT COALESCE(SUM(smf.discount_amount), 0)
            FROM student_monthly_fees smf
            WHERE smf.invoice_id = fi.id
        )
        WHERE fi.id = $id
    ");
    
    header("Location: " . BASE_URL . "modules/fees/invoice_detail.php?id=$id&class_id=$selected_class_id&msg=paid");
    exit;
}
?>

<style>
/* PREMIUM SIAX STYLE FOR INVOICE DETAIL */
.siax-glass-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 25px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 25px;
}

.siax-selector {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 10px;
    margin-bottom: 30px;
}
.class-tabs {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 10px;
}
.class-tab {
    padding: 8px 16px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-secondary);
    cursor: pointer;
    white-space: nowrap;
    border-radius: 8px;
    transition: all 0.2s;
}
.class-tab:hover { background: var(--border); color: var(--text-primary); }

.section-pills {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.section-pill {
    padding: 6px 14px;
    font-size: 0.7rem;
    font-weight: 700;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s;
}
.section-pill:hover { border-color: var(--accent-light); color: var(--accent-light); }
.section-pill.active {
    background: var(--accent-glow);
    color: var(--accent-light);
    border-color: var(--accent-light);
}

.toolbar-siax {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 25px;
}
.btn-siax-tool {
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-primary);
    padding: 8px 18px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    cursor: pointer;
}
.btn-siax-tool:hover {
    background: var(--bg-secondary);
    border-color: var(--accent-light);
    transform: translateY(-2px);
}

.data-table th { background: var(--bg-secondary); text-transform: uppercase; font-size: 0.65rem; color: var(--text-muted); }

.st-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--accent-glow);
    color: var(--accent-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.badge-siax {
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
}
.badge-due { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.badge-paid { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.badge-pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

.search-wrapper {
    position: relative;
    max-width: 350px;
    margin-bottom: 20px;
}
.search-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.search-wrapper input {
    width: 100%;
    padding: 10px 15px 10px 40px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    font-size: 0.85rem;
    color: var(--text-primary);
}

.btn-action-siax:hover { border-color: var(--accent-light); }

/* Row Action Dropdown */
.row-action-wrapper { position: relative; display: inline-block; }
.row-action-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: #2d3436; /* Dark background as in SC2 */
    border-radius: 8px;
    min-width: 160px;
    box-shadow: var(--shadow-lg);
    z-index: 1000;
    margin-top: 5px;
    padding: 8px 0;
}
.row-action-item {
    padding: 10px 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #fff;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: background 0.2s;
}
.row-action-item:hover { background: rgba(255,255,255,0.1); }
.row-action-item i { width: 16px; text-align: center; }

/* MODAL STYLES */
.siax-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    backdrop-filter: blur(5px);
}
.siax-modal {
    background: var(--bg-card);
    width: 100%;
    max-width: 650px;
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
    overflow: hidden;
    animation: modalSlide 0.3s ease-out;
}
@keyframes modalSlide { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 1.1rem; margin: 0; font-weight: 700; color: var(--text-primary); }
.modal-close { cursor: pointer; color: var(--text-muted); font-size: 1.2rem; }

.modal-body { padding: 24px; max-height: 80vh; overflow-y: auto; }
.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
.modal-field { display: flex; flex-direction: column; gap: 6px; }
.modal-field label { font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); }
.modal-field input { 
    padding: 10px 14px; 
    border-radius: 10px; 
    border: 1px solid var(--border); 
    background: var(--bg-secondary); 
    color: var(--text-primary);
    font-weight: 600;
}
.modal-field input[readonly] { background: rgba(0,0,0,0.05); color: var(--text-muted); }

.waive-info { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }
.checkbox-row { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 600; }

.status-selector { display: flex; gap: 20px; margin: 20px 0; }
.status-option { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; }

.history-section { border: 1px solid var(--border); border-radius: 12px; margin-top: 20px; }
.history-header { background: var(--bg-secondary); padding: 10px 15px; font-size: 0.8rem; font-weight: 700; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.history-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
.history-table th, .history-table td { padding: 10px; text-align: center; border-bottom: 1px solid var(--border); }
.history-table th { font-weight: 800; background: rgba(0,0,0,0.02); }

.modal-footer { padding: 18px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }
.btn-save { background: #22c55e; color: #fff; padding: 10px 30px; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; }
.btn-cancel { background: #ef4444; color: #fff; padding: 10px 30px; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; }
</style>

<div class="main-container">
    <div class="page-header">
        <div>
            <h1>Invoice Detail</h1>
            <p><?= htmlspecialchars($invoice['title']) ?> — Due: <?= date('d M Y', strtotime($invoice['due_date'])) ?></p>
        </div>
        <a href="<?= BASE_URL ?>modules/fees/monthly_fee_invoices.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="siax-selector">
        <div class="class-tabs">
            <?php 
            $classes_array = [];
            while($c = $classes_q->fetch_assoc()) $classes_array[] = $c['name'];
            foreach ($classes_array as $name): ?>
                <div class="class-tab"><?= htmlspecialchars($name) ?></div>
            <?php endforeach; ?>
            <div class="class-tab" style="color: var(--accent-light);">Family Wise</div>
        </div>
        <div class="section-pills">
            <?php while($s = $sections_q->fetch_assoc()): ?>
                <a href="?id=<?= $id ?>&class_id=<?= $s['id'] ?>" class="section-pill <?= $selected_class_id == $s['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($s['name'] . ' ' . $s['section']) ?>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="toolbar-siax">
        <button class="btn-siax-tool" onclick="window.open('<?= BASE_URL ?>prints/print_challan_all.php?invoice_id=<?= $id ?>&class_id=<?= $selected_class_id ?>', '_blank')">
            <i class="fa-solid fa-print" style="color: #0d9488;"></i> Challan (All Copies)
        </button>
        <button class="btn-siax-tool" onclick="window.open('<?= BASE_URL ?>prints/print_challan_single.php?invoice_id=<?= $id ?>&class_id=<?= $selected_class_id ?>', '_blank')">
            <i class="fa-solid fa-file-lines" style="color: #10b981;"></i> Challan Old (Single)
        </button>
        <button class="btn-siax-tool" onclick="window.open('<?= BASE_URL ?>prints/print_challan_all.php?invoice_id=<?= $id ?>&class_id=<?= $selected_class_id ?>', '_blank')">
            <i class="fa-solid fa-layer-group" style="color: #64748b;"></i> Challan Old (All)
        </button>
        <button class="btn-siax-tool" onclick="window.open('<?= BASE_URL ?>prints/print_challan_vertical.php?invoice_id=<?= $id ?>&class_id=<?= $selected_class_id ?>', '_blank')">
            <i class="fa-solid fa-bars-staggered" style="color: #06b6d4;"></i> Vertical Challan
        </button>
        <button class="btn-siax-tool" onclick="window.open('<?= BASE_URL ?>prints/print_fee_list.php?invoice_id=<?= $id ?>&class_id=<?= $selected_class_id ?>', '_blank')">
            <i class="fa-solid fa-list-ul" style="color: #ef4444;"></i> Fee List
        </button>
    </div>

    <div class="siax-glass-card">
        <div class="search-wrapper">
            <i class="fa-solid fa-search"></i>
            <input type="text" placeholder="Search student or invoice...">
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Info</th>
                        <th>Prev Pending</th>
                        <th>Current Fee</th>
                        <th>Admission</th>
                        <th>Discount</th>
                        <th>Total Fee</th>
                        <th>Fine</th>
                        <th>Paid Amount</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students_fees) > 0): 
                        $count = 1;
                        foreach ($students_fees as $f): 
                    ?>
                    <tr>
                        <td><?= $count++ ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="st-avatar"><i class="fa-solid fa-user"></i></div>
                                <div>
                                    <div style="font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($f['student_name']) ?></div>
                                    <div style="font-size: 0.65rem; color: var(--text-muted); margin-bottom: 4px;">S/O <?= htmlspecialchars($f['father_name']) ?></div>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <span class="badge badge-info" style="font-size: 0.6rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6;"># <?= htmlspecialchars($f['admission_no']) ?></span>
                                        <span class="badge badge-info" style="font-size: 0.6rem; background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">Roll: <?= htmlspecialchars($f['enrollment_roll_no'] ?? ($f['roll_no'] ?? '-')) ?></span>
                                        <span class="badge badge-info" style="font-size: 0.6rem; background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fa-solid fa-barcode"></i> 1020<?= str_pad($f['student_id'], 3, '0', STR_PAD_LEFT) ?></span>
                                        <span class="badge badge-info" style="font-size: 0.6rem; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">Inv: <?= $f['invoice_id'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><?= number_format($f['prev_pending'], 0) ?></td>
                        <td style="font-weight: 700;">
                            <?php 
                            $breakdown = json_decode($f['fee_details'], true);
                            if (!empty($breakdown)):
                                $tip = "";
                                foreach($breakdown as $b) $tip .= htmlspecialchars($b['title']) . ": " . number_format($b['amount'], 0) . "\n";
                            ?>
                            <span title="<?= trim($tip) ?>" style="cursor:help; border-bottom:1px dotted #ccc;">
                                <?= number_format($f['tuition_fee'], 0) ?>
                            </span>
                            <?php else: ?>
                                <?= number_format($f['tuition_fee'], 0) ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 700; color: #10b981;"><?= number_format($f['admission_fee'] ?? 0, 0) ?></td>
                        <td class="text-danger">-<?= number_format($f['discount_amount'], 0) ?></td>
                        <td style="font-weight: 700; color: var(--accent-light);">
                            <?= number_format(($f['tuition_fee'] + $f['prev_pending'] + ($f['admission_fee'] ?? 0)) - $f['discount_amount'], 0) ?>
                        </td>
                        <td class="text-danger"><?= number_format($f['fine_amount'], 0) ?></td>
                        <td style="text-align: right;">
                            <div style="font-size: 0.65rem; margin-bottom: 2px;">Paid Fee : <span class="text-success"><?= number_format($f['paid_amount'], 0) ?></span></div>
                            <div style="font-size: 0.65rem; margin-bottom: 2px;">Waived : <span class="text-info"><?= number_format($f['discount_amount'], 0) ?></span></div>
                            <div style="font-size: 0.65rem;">Paid Fine : <span class="text-success"><?= number_format($f['fine_amount'], 0) ?></span></div>
                        </td>
                        <td style="font-weight: 800;">
                            <?= number_format(($f['tuition_fee'] + $f['prev_pending'] + ($f['admission_fee'] ?? 0) + $f['fine_amount']) - ($f['discount_amount'] + $f['paid_amount']), 0) ?>
                        </td>
                        <td>
                            <span class="badge-siax <?= $f['status'] == 'Paid' ? 'badge-paid' : ($f['paid_amount'] > 0 ? 'badge-pending' : 'badge-due') ?>">
                                <?= $f['status'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="row-action-wrapper">
                                <button class="btn-action-siax" onclick="toggleRowAction(<?= $f['id'] ?>)">Action <i class="fa-solid fa-chevron-down" style="font-size: 0.6rem;"></i></button>
                                <div class="row-action-menu" id="row-menu-<?= $f['id'] ?>">
                                    <a href="javascript:void(0)" class="row-action-item" onclick="openReceiveModal(<?= htmlspecialchars(json_encode($f)) ?>)"><i class="fa-solid fa-hand-holding-dollar" style="color: #fcc419;"></i> Receive Fee</a>
                                    <a href="<?= BASE_URL ?>prints/print_challan_single.php?invoice_id=<?= $id ?>&student_id=<?= $f['student_id'] ?>" target="_blank" class="row-action-item"><i class="fa-solid fa-file-invoice" style="color: #3b82f6;"></i> Challan Form</a>
                                    <a href="<?= BASE_URL ?>prints/print_receipt.php?monthly_fee_id=<?= $f['id'] ?>" target="_blank" class="row-action-item"><i class="fa-solid fa-receipt" style="color: #10b981;"></i> Print Receipt</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="10" class="text-center" style="padding: 50px; color: var(--text-muted);">Select a class to view fee details.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<!-- RECEIVE FEE MODAL -->
<div id="receiveFeeModal" class="siax-modal-overlay">
    <div class="siax-modal">
        <form method="POST" id="receiveFeeForm">
            <input type="hidden" name="action" value="save_payment">
            <input type="hidden" name="monthly_fee_id" id="modal_mid">
            
            <div class="modal-header">
                <h3 id="modal_student_name">Student Name</h3>
                <div class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></div>
            </div>
            
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="modal-field">
                        <label>Total Fee :</label>
                        <input type="text" id="modal_total_fee" readonly>
                    </div>
                    <div class="modal-field">
                        <label>Total Fine :</label>
                        <input type="text" id="modal_total_fine" readonly>
                    </div>
                    <div class="modal-field">
                        <label>Total Received Fee :</label>
                        <input type="text" id="modal_prev_paid" readonly>
                        <div class="waive-info">Waived Off Fee: <span id="waived_fee_info">0</span></div>
                    </div>
                    <div class="modal-field">
                        <label>Total Received Fine :</label>
                        <input type="text" id="modal_prev_fine" readonly>
                        <div class="waive-info">Waived Off Fine: <span id="waived_fine_info">0</span></div>
                    </div>
                </div>

                <div class="modal-grid" style="grid-template-columns: 1fr 1fr 1.2fr;">
                    <div class="modal-field">
                        <label>Received Remaining Fee :</label>
                        <input type="number" name="amount_paid" id="modal_receive_amt" value="0" oninput="checkStatus()">
                        <div class="checkbox-row mt-1">
                            <input type="checkbox" name="waive_fee" id="waive_fee" onchange="checkStatus()"> Waived Off this fee
                            <input type="hidden" name="waive_fee_val" id="waive_fee_val">
                        </div>
                    </div>
                    <div class="modal-field">
                        <label>Received Remaining Fine :</label>
                        <input type="number" name="fine_paid" id="modal_receive_fine" value="0" oninput="checkStatus()">
                        <div class="checkbox-row mt-1">
                            <input type="checkbox" name="waive_fine" id="waive_fine" onchange="checkStatus()"> Waived Off this fine
                            <input type="hidden" name="waive_fine_val" id="waive_fine_val">
                        </div>
                    </div>
                    <div class="modal-field">
                        <label>Received On</label>
                        <input type="date" name="payment_date" id="modal_p_date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="status-selector">
                    <strong>Fee Status:</strong>
                    <label class="status-option"><input type="radio" name="fee_status" value="Paid" id="status_paid"> Fully Paid</label>
                    <label class="status-option"><input type="radio" name="fee_status" value="Partially Paid" id="status_partial"> Partially Paid</label>
                </div>

                <div class="history-section">
                    <div class="history-header">
                        <i class="fa-solid fa-table-list"></i> Fee Receive History
                    </div>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Fee Paid</th>
                                <th>Fine Paid</th>
                                <th>Waived Off Fee</th>
                                <th>Waived Off Fine</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="history_body">
                            <tr><td colspan="6">No history found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn-save">Save</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentStudent = null;

function openReceiveModal(data) {
    currentStudent = data;
    document.getElementById('modal_mid').value = data.id;
    document.getElementById('modal_student_name').innerText = data.admission_no + ' | ' + data.student_name;
    
    const totalFee = parseFloat(data.tuition_fee) + parseFloat(data.prev_pending) + (parseFloat(data.admission_fee) || 0) - parseFloat(data.discount_amount);
    document.getElementById('modal_total_fee').value = totalFee;
    document.getElementById('modal_total_fine').value = data.fine_amount;
    
    const paidSoFar = parseFloat(data.paid_amount) || 0;
    document.getElementById('modal_prev_paid').value = paidSoFar;
    document.getElementById('modal_prev_fine').value = 0;
    
    const remainingFee = Math.max(0, totalFee - paidSoFar);
    document.getElementById('modal_receive_amt').value = remainingFee;
    document.getElementById('modal_receive_fine').value = data.fine_amount;
    document.getElementById('waive_fee').checked = false;
    document.getElementById('waive_fine').checked = false;
    
    fetchHistory(data.id);
    checkStatus();
    
    document.getElementById('receiveFeeModal').style.display = 'flex';
}

function fetchHistory(mid) {
    fetch('<?= BASE_URL ?>api/data.php?action=fee_history&monthly_fee_id=' + mid)
    .then(r => r.json())
    .then(res => {
        const body = document.getElementById('history_body');
        body.innerHTML = '';
        let totalWaivedFee = 0;
        let totalWaivedFine = 0;
        
        if(res.success && res.data.length > 0) {
            res.data.forEach(h => {
                totalWaivedFee += parseFloat(h.waived_fee || 0);
                totalWaivedFine += parseFloat(h.waived_fine || 0);
                body.innerHTML += `<tr>
                    <td>${h.payment_date}</td>
                    <td>${h.amount_paid}</td>
                    <td>${h.fine_paid}</td>
                    <td>${h.waived_fee}</td>
                    <td>${h.waived_fine}</td>
                    <td><i class="fa-solid fa-trash text-danger" style="cursor:pointer" onclick="deleteHistory(${h.id}, ${mid})" title="Delete Payment Record"></i></td>
                </tr>`;
            });
        } else {
            body.innerHTML = '<tr><td colspan="6">No history found.</td></tr>';
        }
        document.getElementById('waived_fee_info').innerText = totalWaivedFee;
        document.getElementById('waived_fine_info').innerText = totalWaivedFine;
    });
}

function deleteHistory(hid, mid) {
    if (!confirm('Are you sure you want to delete this payment record? This will adjust the total paid amount.')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_fee_history');
    formData.append('id', hid);
    
    fetch('<?= BASE_URL ?>api/data.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('modal_prev_paid').value = res.new_paid;
            const totalFee = parseFloat(document.getElementById('modal_total_fee').value) || 0;
            const remaining = Math.max(0, totalFee - res.new_paid);
            document.getElementById('modal_receive_amt').value = remaining;
            
            fetchHistory(mid);
            checkStatus();
        } else {
            alert('Error deleting payment record: ' + (res.message || 'Unknown error'));
        }
    });
}

function checkStatus() {
    const totalFee = parseFloat(document.getElementById('modal_total_fee').value) || 0;
    const paidSoFar = parseFloat(document.getElementById('modal_prev_paid').value) || 0;
    const receiving = parseFloat(document.getElementById('modal_receive_amt').value) || 0;
    const isWaived = document.getElementById('waive_fee').checked;
    
    let waiving = 0;
    if (isWaived) {
        waiving = Math.max(0, totalFee - paidSoFar - receiving);
    }
    document.getElementById('waive_fee_val').value = waiving;
    
    const totalCovered = paidSoFar + receiving + waiving;
    
    if (totalCovered >= totalFee && totalFee > 0) {
        document.getElementById('status_paid').checked = true;
    } else {
        document.getElementById('status_partial').checked = true;
    }
}

function closeModal() {
    document.getElementById('receiveFeeModal').style.display = 'none';
}

function toggleRowAction(id) {
    const menus = document.querySelectorAll('.row-action-menu');
    menus.forEach(m => {
        if(m.id !== 'row-menu-'+id) m.style.display = 'none';
    });
    const el = document.getElementById('row-menu-'+id);
    el.style.display = el.style.display === 'block' ? 'none' : 'block';
}
window.onclick = function(event) {
    if (!event.target.closest('.row-action-wrapper')) {
        document.querySelectorAll('.row-action-menu').forEach(m => m.style.display = 'none');
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>




