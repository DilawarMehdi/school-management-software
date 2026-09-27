<?php
/**
 * SIAX SMSS - Salary Templates
 * Create and manage salary structures (allowances/deductions) for school staff.
 */
$page_title = 'Salary Template';
$active_page = 'salary_templates';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal');

$msg = ''; $err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $house_rent = (float)($_POST['house_rent'] ?? 0);
    $med_allow = (float)($_POST['medical_allowance'] ?? 0);
    $spec_allow = (float)($_POST['special_allowance'] ?? 0);
    $pf = (float)($_POST['provident_fund'] ?? 0);
    $tax = (float)($_POST['tax_deduction'] ?? 0);
    $other_ded = (float)($_POST['other_deductions'] ?? 0);

    if ($_POST['action'] === 'add') {
        if ($name) {
            $sql = "INSERT INTO salary_templates 
                    (name, house_rent, medical_allowance, special_allowance, provident_fund, tax_deduction, other_deductions) 
                    VALUES ('$name', $house_rent, $med_allow, $spec_allow, $pf, $tax, $other_ded)";
            if ($conn->query($sql)) {
                $msg = "Salary template '$name' created successfully.";
            } else {
                $err = "Error adding template: " . $conn->error;
            }
        } else {
            $err = "Template name is required.";
        }
    }

    if ($_POST['action'] === 'update') {
        $id = (int)$_POST['id'];
        if ($id && $name) {
            $sql = "UPDATE salary_templates SET 
                    name='$name', 
                    house_rent=$house_rent, 
                    medical_allowance=$med_allow, 
                    special_allowance=$spec_allow, 
                    provident_fund=$pf, 
                    tax_deduction=$tax, 
                    other_deductions=$other_ded 
                    WHERE id=$id";
            if ($conn->query($sql)) {
                $msg = "Salary template updated successfully.";
            } else {
                $err = "Error updating template: " . $conn->error;
            }
        } else {
            $err = "Template name and ID are required.";
        }
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            // Check if linked to any staff
            $check = $conn->query("SELECT COUNT(*) as total FROM staff_members WHERE salary_template_id=$id");
            $linked = $check ? $check->fetch_assoc()['total'] : 0;
            if ($linked > 0) {
                $err = "Cannot delete template. It is currently assigned to $linked staff members.";
            } else {
                if ($conn->query("DELETE FROM salary_templates WHERE id=$id")) {
                    $msg = "Salary template deleted successfully.";
                } else {
                    $err = "Error deleting template: " . $conn->error;
                }
            }
        }
    }
}

// Fetch all templates
$q = $conn->query("SELECT * FROM salary_templates ORDER BY name");
$templates = [];
if ($q) while($row = $q->fetch_assoc()) $templates[] = $row;
?>

<div class="main-container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0; color: var(--text-primary);">Salary Template</h1>
            <p>Configure default allowances and deductions templates for staff salaries.</p>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 700; border-radius: 6px;">
            <i class="fa-solid fa-plus-circle"></i> Add New Template
        </button>
    </div>

    <?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:20px;"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="login-error" style="margin-bottom:20px;"><?= $err ?></div><?php endif; ?>

    <!-- Main Card containing the Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0; overflow: hidden; margin-bottom: 30px;">
        <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; background: rgba(255,255,255,0.02); display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-file-invoice-dollar" style="color: #00b894; font-size: 1.1rem;"></i>
            <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary);">Salary Templates</h3>
        </div>

        <div class="table-wrapper" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 12px 15px; text-align: left; font-weight: 700;">Template Name</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">House Rent</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Medical Allow.</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Special Allow.</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Provident Fund</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Tax Deduction</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700;">Net Allowance Diff</th>
                        <th style="padding: 12px 15px; text-align: center; font-weight: 700;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">No templates found. Click the button above to add one.</td>
                        </tr>
                    <?php else: foreach ($templates as $t): 
                        $total_allow = $t['house_rent'] + $t['medical_allowance'] + $t['special_allowance'];
                        $total_ded = $t['provident_fund'] + $t['tax_deduction'] + $t['other_deductions'];
                        $diff = $total_allow - $total_ded;
                    ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 15px 15px; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                <?= htmlspecialchars($t['name']) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= number_format($t['house_rent'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= number_format($t['medical_allowance'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= number_format($t['special_allowance'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; color: #ef4444; font-size: 0.9rem;">
                                <?= number_format($t['provident_fund'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; color: #ef4444; font-size: 0.9rem;">
                                <?= number_format($t['tax_deduction'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; font-weight: 700; color: <?= $diff >= 0 ? '#00b894' : '#ef4444' ?>; font-size: 0.9rem;">
                                <?= ($diff >= 0 ? '+' : '') . number_format($diff, 2) ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: center; font-size: 0.85rem;">
                                <a onclick="openUpdateModal(<?= htmlspecialchars(json_encode($t)) ?>)" style="color: #0984e3; margin-right: 12px; cursor: pointer; font-weight:600;"><i class="fa-solid fa-edit"></i> Edit</a>
                                <a onclick="confirmDelete(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['name'])) ?>')" style="color: #ef4444; cursor: pointer; font-weight:600;"><i class="fa-solid fa-trash-can"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD MODAL -->
<div id="addModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 550px; width: 90%; overflow: hidden; box-shadow: var(--shadow-lg);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-plus-circle" style="color: #0984e3; margin-right: 8px;"></i> Add Salary Template</h3>
            <button onclick="closeAddModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Template Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Standard Staff Template" required>
                </div>
                
                <h4 style="margin: 15px 0 10px 0; color: #00b894; font-size: 0.9rem; text-transform: uppercase;">Allowances</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">House Rent</label>
                        <input type="number" step="0.01" name="house_rent" class="form-control" value="0.00">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Medical Allow.</label>
                        <input type="number" step="0.01" name="medical_allowance" class="form-control" value="0.00">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Special Allow.</label>
                        <input type="number" step="0.01" name="special_allowance" class="form-control" value="0.00">
                    </div>
                </div>

                <h4 style="margin: 15px 0 10px 0; color: #ef4444; font-size: 0.9rem; text-transform: uppercase;">Deductions</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Provident Fund</label>
                        <input type="number" step="0.01" name="provident_fund" class="form-control" value="0.00">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Tax Deduction</label>
                        <input type="number" step="0.01" name="tax_deduction" class="form-control" value="0.00">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Other Deduct.</label>
                        <input type="number" step="0.01" name="other_deductions" class="form-control" value="0.00">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- UPDATE MODAL -->
<div id="updateModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 550px; width: 90%; overflow: hidden; box-shadow: var(--shadow-lg);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-pen-to-square" style="color: #00b894; margin-right: 8px;"></i> Edit Salary Template</h3>
            <button onclick="closeUpdateModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Template Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <h4 style="margin: 15px 0 10px 0; color: #00b894; font-size: 0.9rem; text-transform: uppercase;">Allowances</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">House Rent</label>
                        <input type="number" step="0.01" name="house_rent" id="edit_house_rent" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Medical Allow.</label>
                        <input type="number" step="0.01" name="medical_allowance" id="edit_medical_allowance" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Special Allow.</label>
                        <input type="number" step="0.01" name="special_allowance" id="edit_special_allowance" class="form-control">
                    </div>
                </div>

                <h4 style="margin: 15px 0 10px 0; color: #ef4444; font-size: 0.9rem; text-transform: uppercase;">Deductions</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Provident Fund</label>
                        <input type="number" step="0.01" name="provident_fund" id="edit_provident_fund" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Tax Deduction</label>
                        <input type="number" step="0.01" name="tax_deduction" id="edit_tax_deduction" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.75rem;">Other Deduct.</label>
                        <input type="number" step="0.01" name="other_deductions" id="edit_other_deductions" class="form-control">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #00b894;">Update Template</button>
                </div>
            </form>
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
function openUpdateModal(t) {
    document.getElementById('edit_id').value = t.id;
    document.getElementById('edit_name').value = t.name;
    document.getElementById('edit_house_rent').value = t.house_rent;
    document.getElementById('edit_medical_allowance').value = t.medical_allowance;
    document.getElementById('edit_special_allowance').value = t.special_allowance;
    document.getElementById('edit_provident_fund').value = t.provident_fund;
    document.getElementById('edit_tax_deduction').value = t.tax_deduction;
    document.getElementById('edit_other_deductions').value = t.other_deductions;
    document.getElementById('updateModal').style.display = 'flex';
}
function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
}
function confirmDelete(id, name) {
    if (confirm("Are you sure you want to delete the salary template '" + name + "'?")) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
