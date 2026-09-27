<?php
/**
 * SIAX SMSS - Generate New Invoice
 * Implements the layout from the provided screenshot.
 */
$page_title = 'Generate New Invoice';
$active_page = 'monthly_fee_invoices';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

// Fetch Fine Policies
$fine_policies = $conn->query("SELECT * FROM fine_policies ORDER BY title");

// Fetch Fee Criteria (Step 2)
$criteria_list = $conn->query("SELECT * FROM fee_criteria ORDER BY created_at DESC");

// Fetch Saved Fee Items
$saved_items_q = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'default_fee_items'");
$saved_items = [];
if ($saved_items_q && $saved_items_q->num_rows > 0) {
    $saved_items = json_decode($saved_items_q->fetch_assoc()['setting_value'], true);
}
if (empty($saved_items)) {
    $saved_items = [
        [
            'title' => 'Tuition Fee',
            'amount' => '',
            'apply_type' => 'Different',
            'criteria_id' => 0,
            'apply_concession' => 1
        ]
    ];
}
?>

<style>
.gen-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 30px;
    margin-top: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
.gen-section-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 25px;
}
.form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}
.field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.field-group label {
    font-weight: 700;
    font-size: 0.85rem;
    color: #555;
}
.dates-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}
.date-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.date-input-wrapper input {
    width: 100%;
    padding: 10px 12px;
    padding-right: 40px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f5f5f5;
}
.date-icon {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 40px;
    background: #777;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0 4px 4px 0;
}

.fee-detail-header {
    background: #f8f9fa;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-bottom: none;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.fee-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #ddd;
}
.fee-table th {
    padding: 10px;
    background: #fff;
    border: 1px solid #ddd;
    font-size: 0.85rem;
    text-align: left;
}
.fee-table td {
    padding: 15px;
    border: 1px solid #ddd;
    vertical-align: middle;
}
.fee-table input[type="text"], .fee-table input[type="number"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.apply-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-size: 0.8rem;
}
.apply-options label {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}
.apply-options label.green-text { color: #27ae60; font-weight: 700; }

.btn-add-row {
    background: #4b6600;
    color: #fff;
    border: none;
    padding: 6px 20px;
    font-weight: 700;
    font-size: 0.8rem;
    cursor: pointer;
    float: right;
    margin: 10px;
}
.btn-next {
    background: #3498db;
    color: #fff;
    border: none;
    padding: 12px 60px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 30px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}
.criteria-link {
    font-size: 0.75rem;
    color: #3498db;
    text-decoration: none;
    float: right;
}
</style>

<div class="main-container">
    <div class="gen-card">
        <div class="gen-section-title">Generate New Invoice</div>
        
        <form id="generateInvoiceForm" method="POST" action="preview_invoice.php">
            <div style="padding: 10px; border: 1px solid #ddd; border-bottom: none; background: #fff; width: fit-content;">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            
            <div style="border: 1px solid #ddd; padding: 25px; margin-bottom: 30px;">
                <div class="form-grid">
                    <div class="field-group">
                        <label>Invoice Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Invoice#20-May-2026" value="Invoice#<?= date('d-M-Y') ?>" required>
                    </div>
                    <div class="field-group">
                        <label>Fine Policy <a href="#" class="criteria-link">Show Fine Policy Detail</a></label>
                        <select name="fine_policy_id" class="form-control">
                            <option value="0">None</option>
                            <?php if ($fine_policies): while($fp = $fine_policies->fetch_assoc()): ?>
                                <option value="<?= $fp['id'] ?>"><?= htmlspecialchars($fp['title']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>

                <div class="dates-grid">
                    <div class="field-group">
                        <label>Invoice Generated Date</label>
                        <div class="date-input-wrapper">
                            <input type="text" name="created_at" value="<?= date('d/m/Y') ?>">
                            <div class="date-icon"><i class="fa-solid fa-calendar-days"></i></div>
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Fee Due Date</label>
                        <div class="date-input-wrapper">
                            <input type="text" name="due_date" value="<?= date('d/m/Y') ?>">
                            <div class="date-icon"><i class="fa-solid fa-calendar-days"></i></div>
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Invoice Valid Till</label>
                        <div class="date-input-wrapper">
                            <input type="text" name="valid_till" value="<?= date('d/m/Y') ?>">
                            <div class="date-icon"><i class="fa-solid fa-calendar-days"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fee-detail-header">
                <i class="fa-solid fa-th-large"></i> Fee Detail
            </div>
            <div style="border: 1px solid #ddd;">
                <table class="fee-table" id="feeDetailTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 40%;">Title</th>
                            <th style="width: 15%;">Amount</th>
                            <th style="width: 25%;">Apply on</th>
                            <th style="width: 20%;">Fee Criteria</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saved_items as $index => $item): 
                            $apply_type = $item['apply_type'] ?? 'Different';
                            $criteria_id = (int)($item['criteria_id'] ?? 0);
                            $apply_concession = 0;
                            if (isset($item['apply_concession']) && ($item['apply_concession'] === 'on' || $item['apply_concession'] == 1)) {
                                $apply_concession = 1;
                            }
                            $amount = $item['amount'] ?? '';
                        ?>
                        <tr id="row-<?= $index ?>">
                            <td><?= $index + 1 ?></td>
                            <td><input type="text" name="fee_items[<?= $index ?>][title]" value="<?= htmlspecialchars($item['title']) ?>" required></td>
                            <td><input type="number" name="fee_items[<?= $index ?>][amount]" value="<?= htmlspecialchars($amount) ?>" id="amount-<?= $index ?>" <?= $apply_type === 'Different' ? 'disabled style="opacity:0.5"' : '' ?> placeholder="<?= $apply_type === 'Different' ? 'From Criteria' : '' ?>"></td>
                            <td>
                                <div class="apply-options">
                                    <label id="label-same-<?= $index ?>" class="<?= $apply_type === 'Same' ? 'green-text' : '' ?>"><input type="radio" name="fee_items[<?= $index ?>][apply_type]" value="Same" <?= $apply_type === 'Same' ? 'checked' : '' ?> onclick="toggleApplyType(<?= $index ?>, 'Same')"> Same Amount for All Classes</label>
                                    <label id="label-diff-<?= $index ?>" class="<?= $apply_type === 'Different' ? 'green-text' : '' ?>"><input type="radio" name="fee_items[<?= $index ?>][apply_type]" value="Different" <?= $apply_type === 'Different' ? 'checked' : '' ?> onclick="toggleApplyType(<?= $index ?>, 'Different')"> Different Amount for Classes</label>
                                </div>
                            </td>
                            <td>
                                <div id="criteria-box-<?= $index ?>" style="display: <?= $apply_type === 'Different' ? 'block' : 'none' ?>;">
                                    <select name="fee_items[<?= $index ?>][criteria_id]" class="form-control" style="font-size: 0.8rem; padding: 4px 8px;">
                                        <option value="0">-- Select Criteria --</option>
                                        <?php 
                                        $criteria_list->data_seek(0);
                                        while($c = $criteria_list->fetch_assoc()): ?>
                                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $criteria_id ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <a href="#" class="criteria-link" style="float: none; display: block; text-align: center; margin-top: 5px;">Show Fee Criteria Detail</a>
                                </div>
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; height: 100%; margin-top: 5px;">
                                    <label style="font-size: 0.8rem; display: flex; align-items: center; gap: 6px;"><input type="checkbox" name="fee_items[<?= $index ?>][apply_concession]" <?= $apply_concession ? 'checked' : '' ?>> Apply Concession</label>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="overflow: hidden;">
                    <button type="button" class="btn-add-row" onclick="addFeeRow()">Add Row</button>
                </div>
            </div>

    <script>
    document.getElementById('generateInvoiceForm').addEventListener('submit', function(e) {
        const feeRows = document.querySelectorAll('#feeDetailTable tbody tr');
        let valid = true;
        feeRows.forEach((row, i) => {
            const applyType = row.querySelector(`input[name="fee_items[${i}][apply_type]"]:checked`).value;
            if (applyType === 'Different') {
                const criteriaId = row.querySelector(`select[name="fee_items[${i}][criteria_id]"]`).value;
                if (criteriaId == "0") {
                    alert(`Please select a Fee Criteria for row #${i+1} (${row.querySelector('input[type="text"]').value})`);
                    valid = false;
                }
            }
        });
        if (!valid) e.preventDefault();
    });
    </script>
    
    <button type="submit" class="btn-next">Next</button>
</form>
    </div>
</div>

<script>
let rowCount = <?= count($saved_items) ?>;

function toggleApplyType(index, type) {
    const amountInput = document.getElementById('amount-' + index);
    const criteriaBox = document.getElementById('criteria-box-' + index);
    const labelSame = document.getElementById('label-same-' + index);
    const labelDiff = document.getElementById('label-diff-' + index);

    if (type === 'Same') {
        amountInput.disabled = false;
        amountInput.style.opacity = '1';
        criteriaBox.style.display = 'none';
        labelSame.classList.add('green-text');
        labelDiff.classList.remove('green-text');
    } else {
        amountInput.value = '';
        amountInput.disabled = true;
        amountInput.style.opacity = '0.5';
        criteriaBox.style.display = 'block';
        labelSame.classList.remove('green-text');
        labelDiff.classList.add('green-text');
    }
}

function addFeeRow() {
    const index = rowCount;
    rowCount++;
    const tbody = document.querySelector('#feeDetailTable tbody');
    const tr = document.createElement('tr');
    tr.id = 'row-' + index;
    
    // Fetch criteria options once (or pass them via JS)
    let criteriaOptions = '<option value="0">-- Select Criteria --</option>';
    <?php 
    $criteria_list->data_seek(0);
    while($c = $criteria_list->fetch_assoc()): ?>
        criteriaOptions += '<option value="<?= $c['id'] ?>"><?= addslashes(htmlspecialchars($c['title'])) ?></option>';
    <?php endwhile; ?>

    tr.innerHTML = `
        <td>${index + 1}</td>
        <td><input type="text" name="fee_items[${index}][title]" placeholder="New Fee Head" required></td>
        <td><input type="number" name="fee_items[${index}][amount]" value="0" id="amount-${index}"></td>
        <td>
            <div class="apply-options">
                <label id="label-same-${index}" class="green-text"><input type="radio" name="fee_items[${index}][apply_type]" value="Same" checked onclick="toggleApplyType(${index}, 'Same')"> Same Amount for All Classes</label>
                <label id="label-diff-${index}"><input type="radio" name="fee_items[${index}][apply_type]" value="Different" onclick="toggleApplyType(${index}, 'Different')"> Different Amount for Classes</label>
            </div>
        </td>
        <td>
            <div id="criteria-box-${index}" style="display: none;">
                <select name="fee_items[${index}][criteria_id]" class="form-control" style="font-size: 0.8rem; padding: 4px 8px;">
                    ${criteriaOptions}
                </select>
                <a href="#" class="criteria-link" style="float: none; display: block; text-align: center; margin-top: 5px;">Show Fee Criteria Detail</a>
            </div>
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; height: 100%; margin-top: 5px;">
                <label style="font-size: 0.8rem; display: flex; align-items: center; gap: 6px;"><input type="checkbox" name="fee_items[${index}][apply_concession]" checked> Apply Concession</label>
            </div>
        </td>
    `;
    tbody.appendChild(tr);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




