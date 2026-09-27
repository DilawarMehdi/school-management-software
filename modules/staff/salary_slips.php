<?php
/**
 * SIAX SMSS - Staff Salary Slips
 * Generate, review, and print professional monthly salary slips for staff.
 */
$page_title = 'Salary Slips';
$active_page = 'salary_slips';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$msg = ''; $err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 1. Generate Salary Slip
    if ($_POST['action'] === 'generate') {
        $staff_id = (int)$_POST['staff_member_id'];
        $month = $conn->real_escape_string(trim($_POST['salary_month'] ?? ''));
        
        if ($staff_id && $month) {
            // Check if already generated
            $check = $conn->query("SELECT COUNT(*) as total FROM salary_slips WHERE staff_member_id=$staff_id AND salary_month='$month'");
            if ($check && $check->fetch_assoc()['total'] > 0) {
                $err = "Salary slip for this member has already been generated for $month.";
            } else {
                // Fetch staff and template details
                $staff_q = $conn->query("
                    SELECT s.*, t.house_rent, t.medical_allowance, t.special_allowance, t.provident_fund, t.tax_deduction, t.other_deductions 
                    FROM staff_members s 
                    LEFT JOIN salary_templates t ON s.salary_template_id = t.id 
                    WHERE s.id = $staff_id
                ");
                
                if ($staff_q && $staff_q->num_rows > 0) {
                    $staff = $staff_q->fetch_assoc();
                    $basic = (float)$staff['basic_salary'];
                    
                    $allowances = (float)$staff['house_rent'] + (float)$staff['medical_allowance'] + (float)$staff['special_allowance'];
                    $deductions = (float)$staff['provident_fund'] + (float)$staff['tax_deduction'] + (float)$staff['other_deductions'];
                    $net = $basic + $allowances - $deductions;
                    
                    // Generate unique slip no
                    $slip_no = 'SLP-' . time() . '-' . rand(10, 99);
                    $payment_date = date('Y-m-d');
                    
                    $sql = "INSERT INTO salary_slips 
                            (slip_no, staff_member_id, salary_month, basic_salary, total_allowances, total_deductions, net_salary, payment_date, payment_method, status) 
                            VALUES 
                            ('$slip_no', $staff_id, '$month', $basic, $allowances, $deductions, $net, '$payment_date', 'Cash', 'Paid')";
                    
                    if ($conn->query($sql)) {
                        $msg = "Salary slip generated and marked as Paid for " . htmlspecialchars($staff['name']) . ".";
                    } else {
                        $err = "Database error: " . $conn->error;
                    }
                } else {
                    $err = "Staff member not found.";
                }
            }
        } else {
            $err = "Staff member and Month are required.";
        }
    }

    // 2. Delete Slip
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            if ($conn->query("DELETE FROM salary_slips WHERE id=$id")) {
                $msg = "Salary slip deleted successfully.";
            } else {
                $err = "Error deleting salary slip: " . $conn->error;
            }
        }
    }
}

// Fetch generated salary slips
$slips_q = $conn->query("
    SELECT s.*, m.name as staff_name, m.staff_code, d.name as dept_name, des.title as desig_title 
    FROM salary_slips s 
    JOIN staff_members m ON s.staff_member_id = m.id 
    LEFT JOIN staff_departments d ON m.department_id = d.id 
    LEFT JOIN staff_designations des ON m.designation_id = des.id 
    ORDER BY s.id DESC
");
$slips = [];
if ($slips_q) while ($row = $slips_q->fetch_assoc()) $slips[] = $row;

// Fetch active staff for dropdown
$staff_dropdown_q = $conn->query("SELECT id, name, staff_code FROM staff_members WHERE status='Active' ORDER BY name");
$staff_arr = [];
if ($staff_dropdown_q) while ($row = $staff_dropdown_q->fetch_assoc()) $staff_arr[] = $row;
?>

<div class="main-container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0; color: var(--text-primary);">Salary Slips</h1>
            <p>Generate, review and print monthly payroll slips for school staff.</p>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 700; border-radius: 6px;">
            <i class="fa-solid fa-calculator"></i> Generate Salary Slip
        </button>
    </div>

    <?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:20px;"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="login-error" style="margin-bottom:20px;"><?= $err ?></div><?php endif; ?>

    <!-- Main Card containing the Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0; overflow: hidden; margin-bottom: 30px;">
        <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; background: rgba(255,255,255,0.02); display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-receipt" style="color: #00b894; font-size: 1.1rem;"></i>
            <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary);">Generated Slips</h3>
        </div>

        <div class="table-wrapper" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 12px 15px; text-align: left; font-weight: 700;">Slip No</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: 700;">Staff Member</th>
                        <th style="padding: 12px 15px; text-align: center; font-weight: 700;">Month</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Basic Salary</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Allowances</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Deductions</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Net Salary</th>
                        <th style="padding: 12px 15px; text-align: center; font-weight: 700;">Status</th>
                        <th style="padding: 12px 15px; text-align: center; font-weight: 700;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($slips)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">No salary slips generated yet. Click the button above to generate one.</td>
                        </tr>
                    <?php else: foreach ($slips as $s): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 15px 15px; font-weight: 700; color: var(--text-primary); font-size: 0.9rem;">
                                <?= htmlspecialchars($s['slip_no']) ?>
                            </td>
                            <td style="padding: 15px 15px; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                <?= htmlspecialchars($s['staff_name']) ?>
                                <small style="display:block; color: var(--text-secondary); font-weight: normal; font-size: 0.75rem; margin-top:2px;"><?= htmlspecialchars($s['desig_title'] ?: '-') ?> (<?= htmlspecialchars($s['staff_code']) ?>)</small>
                            </td>
                            <td style="padding: 15px 15px; text-align: center; font-weight: 700; color: var(--text-primary); font-size: 0.9rem;">
                                <?= date('M Y', strtotime($s['salary_month'] . '-01')) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= number_format($s['basic_salary'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; color: #00b894; font-size: 0.9rem;">
                                +<?= number_format($s['total_allowances'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; color: #ef4444; font-size: 0.9rem;">
                                -<?= number_format($s['total_deductions'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; font-weight: 700; color: #6c5ce7; font-size: 0.9rem;">
                                <?= number_format($s['net_salary'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: center; font-size: 0.8rem;">
                                <span class="badge" style="background: rgba(0, 184, 148, 0.15); color: #00b894; padding: 4px 8px; border-radius: 4px; font-weight: 700;">PAID</span>
                            </td>
                            <td style="padding: 15px 15px; text-align: center; font-size: 0.85rem;">
                                <a onclick="openPrintModal(<?= htmlspecialchars(json_encode($s)) ?>)" style="color: #0984e3; margin-right: 12px; cursor: pointer; font-weight:600;"><i class="fa-solid fa-print"></i> Print Slip</a>
                                <a onclick="confirmDelete(<?= $s['id'] ?>)" style="color: #ef4444; cursor: pointer; font-weight:600;"><i class="fa-solid fa-trash-can"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- GENERATE SLIP MODAL -->
<div id="addModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 450px; width: 90%; overflow: hidden; box-shadow: var(--shadow-lg);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-calculator" style="color: #0984e3; margin-right: 8px;"></i> Generate Salary Slip</h3>
            <button onclick="closeAddModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form method="POST">
                <input type="hidden" name="action" value="generate">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Select Staff Member *</label>
                    <select name="staff_member_id" class="form-control" required>
                        <option value="">-- Choose Staff --</option>
                        <?php foreach ($staff_arr as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['staff_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Salary Month *</label>
                    <input type="month" name="salary_month" class="form-control" value="<?= date('Y-m') ?>" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate &amp; Pay</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PRINT VIEW MODAL -->
<div id="printModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1050; align-items: center; justify-content: center; overflow-y: auto;">
    <div class="modal-box" style="background: #fff; color: #000; border-radius: var(--radius-md); max-width: 600px; width: 95%; box-shadow: var(--shadow-lg); overflow: hidden; margin: 20px auto;">
        <div class="modal-header" style="border-bottom: 1px solid #ddd; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa;">
            <h3 style="margin: 0; color: #333;"><i class="fa-solid fa-print"></i> Salary Slip Preview</h3>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-sm btn-primary" onclick="printSlipContent()" style="padding: 6px 15px; font-weight:600;"><i class="fa-solid fa-print"></i> Print</button>
                <button onclick="closePrintModal()" style="background: none; border: none; color: #666; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div class="modal-body" id="slipPrintArea" style="padding: 30px; font-family: 'Courier New', Courier, monospace; color: #000;">
            <!-- Print Content -->
            <div style="text-align: center; border-bottom: 2px dashed #000; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 style="margin: 0 0 5px 0; font-size: 1.4rem; font-weight: bold;"><?= htmlspecialchars($settings['school_name'] ?? 'SIAX PUBLIC SCHOOL') ?></h2>
                <p style="margin: 0; font-size: 0.85rem; color: #333;"><?= htmlspecialchars($settings['school_address'] ?? 'Skardu, Gilgit Baltistan') ?></p>
                <p style="margin: 5px 0 0 0; font-size: 0.85rem; font-weight: bold;">MONTHLY SALARY SLIP</p>
            </div>

            <table style="width: 100%; font-size: 0.85rem; margin-bottom: 20px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 4px 0; font-weight: bold; width: 30%;">Slip No:</td>
                    <td style="padding: 4px 0;" id="print_slip_no"></td>
                    <td style="padding: 4px 0; font-weight: bold; width: 30%;">Date:</td>
                    <td style="padding: 4px 0;" id="print_date"></td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-weight: bold;">Staff Code:</td>
                    <td style="padding: 4px 0;" id="print_code"></td>
                    <td style="padding: 4px 0; font-weight: bold;">Salary Month:</td>
                    <td style="padding: 4px 0; font-weight: bold;" id="print_month"></td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; font-weight: bold;">Name:</td>
                    <td style="padding: 4px 0; font-weight: bold;" id="print_name"></td>
                    <td style="padding: 4px 0; font-weight: bold;">Designation:</td>
                    <td style="padding: 4px 0;" id="print_designation"></td>
                </tr>
            </table>

            <div style="border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 10px 0; margin-bottom: 20px;">
                <table style="width: 100%; font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 1px dashed #000;">
                            <th style="text-align: left; padding-bottom: 5px;">Description</th>
                            <th style="text-align: right; padding-bottom: 5px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 6px 0;">Basic Salary</td>
                            <td style="text-align: right; padding: 6px 0;" id="print_basic"></td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #333;">Total Allowances (+)</td>
                            <td style="text-align: right; padding: 6px 0; color: #000;" id="print_allowances"></td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #333;">Total Deductions (-)</td>
                            <td style="text-align: right; padding: 6px 0; color: #000;" id="print_deductions"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="text-align: right; font-size: 1.1rem; font-weight: bold; border-bottom: 2px dashed #000; padding-bottom: 15px; margin-bottom: 30px;">
                Net Payable: <span id="print_net"></span>
            </div>

            <table style="width: 100%; font-size: 0.8rem; margin-top: 40px;">
                <tr>
                    <td style="text-align: center; width: 50%;">
                        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px;">Staff Signature</div>
                    </td>
                    <td style="text-align: center; width: 50%;">
                        <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px;">Authorized Cashier</div>
                    </td>
                </tr>
            </table>

            <div style="text-align: center; margin-top: 40px; font-size: 0.7rem; font-style: italic; color: #555;">
                Generated by SIAC TECHNOLOGIES - SmSS
            </div>
        </div>
    </div>
</div>

<!-- DELETE FORM -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}
function openPrintModal(s) {
    document.getElementById('print_slip_no').innerText = s.slip_no;
    document.getElementById('print_date').innerText = s.payment_date;
    document.getElementById('print_code').innerText = s.staff_code;
    
    // Format Month
    const date = new Date(s.salary_month + '-01');
    const monthStr = date.toLocaleString('default', { month: 'long', year: 'numeric' });
    document.getElementById('print_month').innerText = monthStr;
    
    document.getElementById('print_name').innerText = s.staff_name;
    document.getElementById('print_designation').innerText = s.desig_title || '-';
    
    // Format numbers
    document.getElementById('print_basic').innerText = parseFloat(s.basic_salary).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('print_allowances').innerText = parseFloat(s.total_allowances).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('print_deductions').innerText = parseFloat(s.total_deductions).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('print_net').innerText = parseFloat(s.net_salary).toLocaleString(undefined, {minimumFractionDigits: 2});
    
    document.getElementById('printModal').style.display = 'flex';
}
function closePrintModal() {
    document.getElementById('printModal').style.display = 'none';
}
function printSlipContent() {
    const printArea = document.getElementById('slipPrintArea').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="padding: 40px; background: #fff; color: #000; min-height: 100vh;">
            ${printArea}
        </div>
    `;
    
    window.print();
    
    // Restore page
    document.body.innerHTML = originalContent;
    window.location.reload();
}
function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this salary slip record?")) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
