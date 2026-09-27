<?php
/**
 * SIAX SMSS - Preview & Generate Invoice
 * Step 2 of the Invoice Generation process.
 */
$page_title = 'Review Invoice Criteria';
$active_page = 'monthly_fee_invoices';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "modules/fees/generate_invoice.php");
    exit;
}

$title = $_POST['title'] ?? '';
$fine_policy_id = (int)($_POST['fine_policy_id'] ?? 0);
$created_at = $_POST['created_at'] ?? '';
$due_date = $_POST['due_date'] ?? '';
$valid_till = $_POST['valid_till'] ?? '';
$fee_items = $_POST['fee_items'] ?? [];

// PERSIST fee items structure for future invocations
if (!empty($fee_items)) {
    $fee_items_json = $conn->real_escape_string(json_encode($fee_items));
    $conn->query("INSERT INTO settings (setting_key, setting_value) 
                  VALUES ('default_fee_items', '$fee_items_json')
                  ON DUPLICATE KEY UPDATE setting_value = '$fee_items_json'");
}

// Fetch Classes (Naturally sorted)
$classes_list = get_all_classes($conn);
$classes_data = [];

foreach ($classes_list as $c) {

    $cid = $c['id'];
    $session_year = $settings['session_year'] ?? '2025-2026';
    // Count students (session-aware)
    $s_count_q = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM student_enrollments se 
        JOIN students s ON se.student_id = s.id 
        WHERE se.class_id = $cid AND se.session_year = '$session_year' AND se.status = 'Active' AND s.status = 'Active'
    ");
    $s_count = $s_count_q->fetch_assoc()['cnt'];

    $class_total_tuition = 0;
    $class_total_discount = 0;
    $class_items_breakdown = []; // Store detailed items
    
    // 1. Calculate Per Item Fee for this class
    $total_std_fee_for_class = 0;
    foreach ($fee_items as $item) {
        $item_amt = 0;
        if ($item['apply_type'] === 'Same') {
            $item_amt = (float)$item['amount'];
        } else {
            $crit_id = (int)($item['criteria_id'] ?? 0);
            if ($crit_id > 0) {
                $crit_amt_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $crit_id AND class_id = $cid");
                if ($crit_amt_q && $crit_amt_q->num_rows > 0) {
                    $item_amt = (float)$crit_amt_q->fetch_assoc()['standard_fee'];
                }
            }
        }
        $total_std_fee_for_class += $item_amt;
        $class_items_breakdown[] = [
            'title' => $item['title'],
            'amount' => $item_amt,
            'subtotal' => $item_amt * $s_count
        ];
    }
    $class_total_tuition = ($total_std_fee_for_class * $s_count);

    // 2. Calculate Total Discount for this class (session-aware)
    $class_concession_base = 0;
    foreach ($fee_items as $item) {
        $is_concession = false;
        if (isset($item['apply_concession']) && ($item['apply_concession'] === 'on' || $item['apply_concession'] == 1)) {
            $is_concession = true;
        }
        if ($is_concession) {
            $item_amt = 0;
            if ($item['apply_type'] === 'Same') {
                $item_amt = (float)$item['amount'];
            } else {
                $crit_id = (int)($item['criteria_id'] ?? 0);
                if ($crit_id > 0) {
                    $crit_amt_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $crit_id AND class_id = $cid");
                    if ($crit_amt_q && $crit_amt_q->num_rows > 0) {
                        $item_amt = (float)$crit_amt_q->fetch_assoc()['standard_fee'];
                    }
                }
            }
            $class_concession_base += $item_amt;
        }
    }
    // Fallback to full standard fee if no concession flags set
    $preview_disc_base = $class_concession_base > 0 ? $class_concession_base : $total_std_fee_for_class;

    $s_discounts_q = $conn->query("
        SELECT s.discount_amount, s.discount_type 
        FROM student_enrollments se 
        JOIN students s ON se.student_id = s.id 
        WHERE se.class_id = $cid AND se.session_year = '$session_year' AND se.status = 'Active' AND s.status = 'Active'
    ");
    while($sd = $s_discounts_q->fetch_assoc()){
        if($sd['discount_type'] === 'Percentage'){
            $class_total_discount += ($preview_disc_base * (float)$sd['discount_amount'] / 100);
        } else {
            $class_total_discount += (float)$sd['discount_amount'];
        }
    }

    // 3. Calculate Total Previous Pending for this class
    $class_prev_pending = 0;
    $prev_pending_q = $conn->query("
        SELECT COALESCE(SUM(
            (smf.tuition_fee + smf.admission_fee + COALESCE(smf.prev_pending,0) + COALESCE(smf.fine_amount,0))
            - COALESCE(smf.discount_amount,0)
            - COALESCE(smf.paid_amount,0)
        ), 0) as total_pending
        FROM student_monthly_fees smf
        JOIN student_enrollments se ON smf.student_id = se.student_id AND se.session_year = '$session_year'
        JOIN students s ON se.student_id = s.id
        WHERE se.class_id = $cid
          AND smf.status IN ('Due', 'Partial')
    ");
    if ($prev_pending_q) $class_prev_pending = (float)$prev_pending_q->fetch_assoc()['total_pending'];
    if ($class_prev_pending < 0) $class_prev_pending = 0;

    $c['student_count'] = $s_count;
    $c['total_fee'] = $class_total_tuition;
    $c['total_discount'] = $class_total_discount;
    $c['prev_pending'] = $class_prev_pending;
    $c['items_breakdown'] = $class_items_breakdown;
    $classes_data[] = $c;
}

$grand_total_fee = array_sum(array_column($classes_data, 'total_fee'));
$grand_total_disc = array_sum(array_column($classes_data, 'total_discount'));
$grand_prev_pending = array_sum(array_column($classes_data, 'prev_pending'));
$grand_payable = $grand_total_fee - $grand_total_disc;
?>

<style>
.preview-container {
    background: #fff;
    padding: 25px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: 20px;
}
.preview-title {
    font-size: 0.9rem;
    color: #333;
    margin-bottom: 20px;
    font-weight: 600;
}
.btn-back {
    background: #3498db;
    color: #fff;
    border: none;
    padding: 6px 20px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 700;
    float: right;
    text-decoration: none;
}
.preview-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #ddd;
    margin-bottom: 20px;
}
.preview-table th, .preview-table td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
    font-size: 0.8rem;
}
.preview-table th {
    background: #fdfdfd;
    font-weight: 700;
    color: #555;
}
.breakdown-text {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
}
.breakdown-sub {
    border-top: 1px solid #ccc;
    padding-top: 4px;
    display: block;
}
.btn-finalize {
    background: #27ae60;
    color: #fff;
    border: none;
    padding: 10px 30px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    float: right;
    margin-top: 10px;
}
.batch-month-chip.selected {
    background: rgba(245, 158, 11, 0.15) !important;
    color: #f59e0b !important;
    border-color: #f59e0b !important;
    font-weight: 700;
}
</style>

<div class="main-container">
    <a href="<?= BASE_URL ?>modules/fees/generate_invoice.php" class="btn-back">Back</a>
    <div class="preview-title">Invoice will be Generated according to following criteria</div>

    <form action="save_invoice.php" method="POST">
        <!-- Hidden meta data -->
        <input type="hidden" name="title" value="<?= htmlspecialchars($title) ?>">
        <input type="hidden" name="fine_policy_id" value="<?= $fine_policy_id ?>">
        <input type="hidden" name="created_at" value="<?= htmlspecialchars($created_at) ?>">
        <input type="hidden" name="due_date" value="<?= htmlspecialchars($due_date) ?>">
        <input type="hidden" name="valid_till" value="<?= htmlspecialchars($valid_till) ?>">
        <?php foreach($fee_items as $i => $item): ?>
            <input type="hidden" name="fee_items[<?= $i ?>][title]" value="<?= htmlspecialchars($item['title']) ?>">
            <input type="hidden" name="fee_items[<?= $i ?>][amount]" value="<?= $item['amount'] ?>">
            <input type="hidden" name="fee_items[<?= $i ?>][apply_type]" value="<?= $item['apply_type'] ?>">
            <input type="hidden" name="fee_items[<?= $i ?>][criteria_id]" value="<?= $item['criteria_id'] ?? 0 ?>">
            <input type="hidden" name="fee_items[<?= $i ?>][apply_concession]" value="<?= isset($item['apply_concession']) ? 1 : 0 ?>">
        <?php endforeach; ?>

        <div class="preview-container">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th colspan="2"></th>
                        <th style="width: 22%;">Fee Breakdown</th>
                        <th style="width: 13%;">Current Fee Total</th>
                        <th style="width: 13%;">Concession / Scholarship</th>
                        <th style="width: 12%;" class="text-warning">Prev Pending</th>
                        <th style="width: 13%;">Payable Fee</th>
                        <th style="width: 12%;">Want to Generate Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes_data as $c): ?>
                        <tr>
                            <td style="font-weight: 700;"><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['section']) ?></td>
                            <td style="text-align: left; font-size: 0.75rem; line-height: 1.4;">
                                <?php if ($c['student_count'] > 0): ?>
                                    <?php foreach ($c['items_breakdown'] as $item): ?>
                                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f1f1; padding: 2px 0;">
                                            <span><?= htmlspecialchars($item['title']) ?> (<?= number_format($item['amount'], 0) ?>X<?= $c['student_count'] ?>)</span>
                                            <span style="font-weight: 600;">= <?= number_format($item['subtotal'], 0) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; color: #2c3e50;"><?= $c['student_count'] > 0 ? number_format($c['total_fee'], 0) : '-' ?></td>
                            <td><?= $c['student_count'] > 0 ? number_format($c['total_discount'], 0) : '-' ?></td>
                            <td style="font-weight: 700; color: #e67e22;"><?= $c['student_count'] > 0 && $c['prev_pending'] > 0 ? number_format($c['prev_pending'], 0) : '-' ?></td>
                            <td style="font-weight: 800; color: #27ae60;"><?= $c['student_count'] > 0 ? number_format($c['total_fee'] - $c['total_discount'], 0) : '-' ?></td>
                            <td>
                                <?php if ($c['student_count'] > 0): ?>
                                    <label style="cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                        <input type="checkbox" name="generate_for[<?= $c['id'] ?>]" value="1" checked> Yes
                                    </label>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background: #fafafa; font-weight: 800;">
                        <td colspan="2">Total</td>
                        <td></td>
                        <td><?= number_format($grand_total_fee, 0) ?></td>
                        <td><?= number_format($grand_total_disc, 0) ?></td>
                        <td style="color: #e67e22;"><?= $grand_prev_pending > 0 ? number_format($grand_prev_pending, 0) : '-' ?></td>
                        <td><?= number_format($grand_payable, 0) ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <!-- Add Advance Fee Section -->
            <?php
            // Generate next 12 months for batch advance
            $month_options = [];
            $current_month = (int)date('n');
            $current_year = (int)date('Y');
            for ($i = 1; $i <= 12; $i++) {
                $dt = new DateTime();
                $dt->modify("+$i months");
                $month_options[] = [
                    'value' => $dt->format('F') . '-' . $dt->format('Y'),
                    'label' => $dt->format('F Y')
                ];
            }
            ?>
            <div style="background: #fafafa; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; text-align: left;">
                <div style="font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #333;">
                    <i class="fa-solid fa-forward" style="color: #f59e0b;"></i>
                    Add Advance Fee?
                </div>
                <div style="display: flex; gap: 20px; margin-bottom: 12px; font-size: 0.85rem;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                        <input type="radio" name="add_advance_batch" value="No" checked onclick="toggleBatchAdvance(false)"> No
                    </label>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600; color: #f59e0b;">
                        <input type="radio" name="add_advance_batch" value="Yes" onclick="toggleBatchAdvance(true)"> Yes
                    </label>
                </div>
                
                <div id="batchAdvanceOptions" style="display: none; border-top: 1px solid #eee; padding-top: 15px;">
                    <label style="font-weight: 700; font-size: 0.8rem; display: block; margin-bottom: 8px; color: #555;">Select Advance Month(s) to add to this invoice:</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach($month_options as $mo): ?>
                            <label style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; background: #fff;" class="batch-month-chip" onclick="event.preventDefault(); var cb = this.querySelector('input'); cb.checked = !cb.checked; this.classList.toggle('selected', cb.checked);">
                                <input type="checkbox" name="advance_months_batch[]" value="<?= $mo['value'] ?>" style="display: none;">
                                <?= $mo['label'] ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <span style="font-size: 0.72rem; color: #666; margin-top: 8px; display: block;">* Note: The advance fee charged per student will match their standard tuition fee.</span>
                </div>
            </div>

            <script>
            function toggleBatchAdvance(show) {
                document.getElementById('batchAdvanceOptions').style.display = show ? 'block' : 'none';
                if (!show) {
                    // Uncheck all when turning off
                    document.querySelectorAll('.batch-month-chip').forEach(c => {
                        c.classList.remove('selected');
                        c.querySelector('input').checked = false;
                    });
                }
            }
            </script>
            
            <div style="overflow: hidden;">
                <button type="submit" class="btn-finalize">Generate Invoice</button>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




