<?php
$page_title = 'Fee Management';
$active_page = 'fees';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','accountant');

$msg = ''; $err = '';
$currency = $settings['currency'] ?? 'PKR';
$session_year = $settings['session_year'] ?? '2025-2026';

// Handle fee collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_fee') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $payment_date = $conn->real_escape_string($_POST['payment_date'] ?? date('Y-m-d'));
    $month = $conn->real_escape_string($_POST['month'] ?? '');
    $discount = (float)($_POST['discount'] ?? 0);
    $fine = (float)($_POST['fine'] ?? 0);
    $payment_mode = $conn->real_escape_string($_POST['payment_mode'] ?? 'Cash');
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $fee_heads = $_POST['fee_head'] ?? [];
    $amounts = $_POST['amount'] ?? [];

    if ($student_id > 0 && !empty($fee_heads)) {
        $subtotal = 0; $items = [];
        foreach ($fee_heads as $i => $fh_id) {
            $amt = (float)($amounts[$i] ?? 0);
            if ($amt > 0 && $fh_id) {
                $fhr = $conn->query("SELECT name FROM fee_heads WHERE id=".(int)$fh_id);
                $fh_name = $fhr ? $conn->real_escape_string($fhr->fetch_assoc()['name'] ?? '') : '';
                $items[] = ['fee_head_id' => (int)$fh_id, 'fee_head_name' => $fh_name, 'amount' => $amt];
                $subtotal += $amt;
            }
        }
        $total = $subtotal - $discount + $fine;
        $receipt_no = generate_receipt_no($conn);
        $uid = current_uid();
        $conn->query("INSERT INTO fee_payments (receipt_no,student_id,payment_date,month,session_year,subtotal,discount,fine,total_amount,paid_amount,balance,payment_mode,collected_by,notes) VALUES ('$receipt_no',$student_id,'$payment_date','$month','$session_year',$subtotal,$discount,$fine,$total,$total,0,'$payment_mode',$uid,'$notes')");
        $pay_id = $conn->insert_id;
        foreach ($items as $it) {
            $conn->query("INSERT INTO fee_payment_items (payment_id,fee_head_id,fee_head_name,amount) VALUES ($pay_id,{$it['fee_head_id']},'{$it['fee_head_name']}',{$it['amount']})");
        }
        
        // SYNC: Update student_monthly_fees record for this month
        $conn->query("UPDATE student_monthly_fees 
                      SET paid_amount = paid_amount + $total, 
                          status = 'Paid', 
                          payment_date = '$payment_date' 
                      WHERE student_id = $student_id AND month = '$month'");

        header("Location: " . BASE_URL . "modules/fees/fees.php?receipt=$pay_id"); exit;
    } else { $err = 'Please select a student and add fee items.'; }
}

if (isset($_GET['delete']) && current_role() === 'admin') {
    $conn->query("DELETE FROM fee_payments WHERE id=".(int)$_GET['delete']);
    $msg = 'Payment deleted.';
}

$filter_month = $conn->real_escape_string($_GET['month'] ?? '');
$search = $conn->real_escape_string($_GET['search'] ?? '');
$where = "fp.session_year='$session_year'";
if ($filter_month) $where .= " AND fp.month='$filter_month'";
if ($search) $where .= " AND (s.name LIKE '%$search%' OR s.admission_no LIKE '%$search%' OR fp.receipt_no LIKE '%$search%')";

$payments = $conn->query("
    SELECT fp.*, s.name as student_name, s.admission_no, c.name as class_name, c.section 
    FROM fee_payments fp 
    JOIN students s ON fp.student_id=s.id 
    LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.session_year = '$session_year' AND se.status = 'Active' 
    LEFT JOIN classes c ON COALESCE(se.class_id, s.class_id) = c.id 
    WHERE $where ORDER BY fp.created_at DESC
");
$students_q = $conn->query("
    SELECT DISTINCT s.id, s.name, s.admission_no 
    FROM student_enrollments se 
    JOIN students s ON se.student_id = s.id 
    WHERE se.session_year = '$session_year' AND se.status = 'Active' AND s.status = 'Active' 
    ORDER BY s.name
");
$students_arr = [];
if ($students_q) while($s = $students_q->fetch_assoc()) $students_arr[] = $s;
$fee_heads_q = $conn->query("SELECT * FROM fee_heads WHERE is_active=1 ORDER BY type,name");
$fee_heads_arr = [];
if ($fee_heads_q) while($f = $fee_heads_q->fetch_assoc()) $fee_heads_arr[] = $f;

$total_collected = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as t FROM fee_payments WHERE session_year='$session_year'")->fetch_assoc()['t'] ?? 0;
$month_collected = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as t FROM fee_payments WHERE MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW()) AND session_year='$session_year'")->fetch_assoc()['t'] ?? 0;
$today_collected = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as t FROM fee_payments WHERE DATE(payment_date)=CURDATE() AND session_year='$session_year'")->fetch_assoc()['t'] ?? 0;

$receipt_data = null;
if (isset($_GET['receipt'])) {
    $rid = (int)$_GET['receipt'];
    $rq = $conn->query("
        SELECT fp.*, s.name as student_name, s.admission_no, s.father_name, c.name as class_name, c.section 
        FROM fee_payments fp 
        JOIN students s ON fp.student_id=s.id 
        LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.session_year = '$session_year' 
        LEFT JOIN classes c ON COALESCE(se.class_id, s.class_id) = c.id 
        WHERE fp.id=$rid
    ");
    if ($rq && $rq->num_rows) {
        $receipt_data = $rq->fetch_assoc();
        $receipt_data['items'] = [];
        $iq = $conn->query("SELECT * FROM fee_payment_items WHERE payment_id=$rid");
        while($it = $iq->fetch_assoc()) $receipt_data['items'][] = $it;
    }
}
$months_list = ['January','February','March','April','May','June','July','August','September','October','November','December'];
?>

<?php if ($receipt_data): ?>
<style>
.receipt-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;overflow-y:auto;display:flex;flex-direction:column;align-items:center;padding:20px}
.receipt-container{background:#fff;width:100%;max-width:720px;border-radius:8px;overflow:hidden;color:#111}
.receipt-actions{padding:12px 20px;background:#1a2035;display:flex;gap:10px}
.receipt-copy{padding:22px 28px}
.receipt-divider{border-bottom:3px dashed #999;margin-bottom:0;padding-bottom:22px}
.receipt-header{text-align:center;margin-bottom:14px;border-bottom:2px solid #333;padding-bottom:10px}
.receipt-header h2{font-size:17px;font-weight:700;margin:0}
.receipt-header p{font-size:11px;color:#555;margin:3px 0}
.receipt-header h3{font-size:12px;font-weight:600;background:#111;color:#fff;display:inline-block;padding:2px 12px;border-radius:3px;margin-top:7px}
.receipt-info{display:grid;grid-template-columns:1fr 1fr;gap:3px 14px;margin-bottom:12px;font-size:12px}
.receipt-row{display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #eee}
.receipt-table{width:100%;border-collapse:collapse;font-size:12px;margin:10px 0}
.receipt-table th,.receipt-table td{border:1px solid #ddd;padding:4px 7px}
.receipt-table th{background:#f0f0f0;font-weight:600}
.receipt-totals{margin-top:7px;font-size:12px}
.receipt-total-row{font-size:14px;font-weight:700;border-top:2px solid #333;padding-top:5px}
.receipt-footer{margin-top:14px;font-size:11px;text-align:right;color:#555}
@media print{.no-print{display:none!important}.receipt-overlay{position:static;background:none;padding:0}.receipt-container{box-shadow:none;max-width:none}}
</style>
<div class="receipt-overlay" id="receiptOverlay">
  <div class="receipt-container">
    <div class="receipt-actions no-print">
      <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i>️ Print 3 Copies</button>
      <a href="<?= BASE_URL ?>modules/fees/fees.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Close</a>
    </div>
    <?php for($copy=1; $copy<=3; $copy++): $labels=['SCHOOL COPY','STUDENT COPY','BANK COPY']; ?>
    <div class="receipt-copy <?= $copy < 3 ? 'receipt-divider' : '' ?>">
      <div class="receipt-header">
        <h2><?= htmlspecialchars($school_name) ?></h2>
        <p><?= htmlspecialchars($settings['school_address'] ?? '') ?> | Ph: <?= htmlspecialchars($settings['school_phone'] ?? '') ?></p>
        <h3>FEE RECEIPT — <?= $labels[$copy-1] ?></h3>
      </div>
      <div class="receipt-info">
        <div class="receipt-row"><span>Receipt No:</span><strong><?= htmlspecialchars($receipt_data['receipt_no']) ?></strong></div>
        <div class="receipt-row"><span>Date:</span><span><?= date('d M Y', strtotime($receipt_data['payment_date'])) ?></span></div>
        <div class="receipt-row"><span>Student:</span><strong><?= htmlspecialchars($receipt_data['student_name']) ?></strong></div>
        <div class="receipt-row"><span>Adm#:</span><span><?= htmlspecialchars($receipt_data['admission_no']) ?></span></div>
        <div class="receipt-row"><span>Father:</span><span><?= htmlspecialchars($receipt_data['father_name'] ?? '-') ?></span></div>
        <div class="receipt-row"><span>Class:</span><span><?= htmlspecialchars(($receipt_data['class_name'] ?? '-') . ' ' . ($receipt_data['section'] ?? '')) ?></span></div>
        <div class="receipt-row"><span>Month:</span><span><?= htmlspecialchars($receipt_data['month'] ?: '-') ?></span></div>
        <div class="receipt-row"><span>Mode:</span><span><?= $receipt_data['payment_mode'] ?></span></div>
      </div>
      <table class="receipt-table">
        <thead><tr><th>#</th><th>Description</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach($receipt_data['items'] as $i=>$it): ?>
        <tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($it['fee_head_name']) ?></td><td><?= $currency ?> <?= number_format($it['amount'],2) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="receipt-totals">
        <div class="receipt-row"><span>Subtotal:</span><span><?= $currency ?> <?= number_format($receipt_data['subtotal'],2) ?></span></div>
        <?php if($receipt_data['discount']>0): ?><div class="receipt-row" style="color:green"><span>Discount:</span><span>- <?= $currency ?> <?= number_format($receipt_data['discount'],2) ?></span></div><?php endif; ?>
        <?php if($receipt_data['fine']>0): ?><div class="receipt-row" style="color:red"><span>Fine:</span><span>+ <?= $currency ?> <?= number_format($receipt_data['fine'],2) ?></span></div><?php endif; ?>
        <div class="receipt-row receipt-total-row"><span>TOTAL PAID:</span><strong><?= $currency ?> <?= number_format($receipt_data['paid_amount'],2) ?></strong></div>
      </div>
      <div class="receipt-footer"><div>Authorized Signature: _________________</div><div>SIAC Technologies | <?= date('d M Y H:i') ?></div></div>
    </div>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<div class="page-header">
  <div><h1><i class="fa-solid fa-money-bill-wave"></i> Fee Management</h1><p>Collect fees, generate receipts, and track payments</p></div>
  <button class="btn btn-primary" onclick="toggleForm('feeForm')"><i class="fa-solid fa-plus"></i> Collect Fee</button>
</div>

<?php if ($msg && !$receipt_data): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-error"><?= $err ?></div><?php endif; ?>

<div class="stat-cards" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-chart-bar"></i></div><div class="stat-info"><h3><?= $currency ?> <?= number_format($total_collected) ?></h3><p>Session Total</p></div></div>
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-calendar"></i></div><div class="stat-info"><h3><?= $currency ?> <?= number_format($month_collected) ?></h3><p>This Month</p></div></div>
  <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div><div class="stat-info"><h3><?= $currency ?> <?= number_format($today_collected) ?></h3><p>Today</p></div></div>
</div>

<div class="card mb-3" id="feeForm" style="display:none">
  <div class="card-header"><h3><i class="fa-solid fa-credit-card"></i> New Fee Collection</h3></div>
  <form method="POST">
    <input type="hidden" name="action" value="collect_fee">
    <div class="form-row">
      <div class="form-group">
        <label>Student *</label>
        <select name="student_id" class="form-control" required>
          <option value="">-- Select Student --</option>
          <?php foreach($students_arr as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['admission_no'] . ' — ' . $s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Month</label>
        <select name="month" class="form-control">
          <option value="">-- Select --</option>
          <?php foreach($months_list as $m): ?><option value="<?= $m ?>" <?= date('F')===$m?'selected':'' ?>><?= $m ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Payment Date</label>
        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
      </div>
    </div>
    <div style="margin-bottom:10px;display:flex;align-items:center;gap:12px">
      <strong>Fee Items</strong>
      <button type="button" class="btn btn-sm btn-success" onclick="addFeeRow()">+ Add Row</button>
    </div>
    <div id="feeRows">
      <div class="fee-row form-row" style="align-items:flex-end">
        <div class="form-group"><label>Fee Head</label>
          <select name="fee_head[]" class="form-control">
            <option value="">-- Select --</option>
            <?php foreach($fee_heads_arr as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= $f['type'] ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Amount (<?= $currency ?>)</label>
          <input type="number" name="amount[]" class="form-control fee-amount" min="0" step="0.01" placeholder="0" onchange="calcTotal()">
        </div>
        <div class="form-group" style="flex:0.3"><label>&nbsp;</label>
          <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.fee-row').remove();calcTotal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </div>
    </div>
    <div class="form-row" style="background:var(--surface-2);padding:12px;border-radius:8px;margin-top:10px">
      <div class="form-group"><label>Discount (<?= $currency ?>)</label><input type="number" name="discount" id="discount" class="form-control" value="0" min="0" onchange="calcTotal()"></div>
      <div class="form-group"><label>Fine (<?= $currency ?>)</label><input type="number" name="fine" id="fine" class="form-control" value="0" min="0" onchange="calcTotal()"></div>
      <div class="form-group"><label>Total</label><input type="text" id="totalDisplay" class="form-control" readonly value="<?= $currency ?> 0.00" style="font-weight:700;color:var(--accent)"></div>
      <div class="form-group"><label>Payment Mode</label><select name="payment_mode" class="form-control"><option>Cash</option><option>Online</option><option>Cheque</option></select></div>
    </div>
    <div class="form-row"><div class="form-group" style="grid-column:1/-1"><label>Notes</label><input type="text" name="notes" class="form-control" placeholder="Optional..."></div></div>
    <div class="btn-group mt-2">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-credit-card"></i> Collect & Print Receipt</button>
      <button type="button" class="btn btn-secondary" onclick="toggleForm('feeForm')">Cancel</button>
    </div>
  </form>
</div>

<div class="filter-bar">
  <form method="GET" class="filter-bar" style="margin-bottom:0">
    <div class="search-box"><input type="text" name="search" class="form-control" placeholder="Search name, receipt..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"></div>
    <select name="month" class="form-control">
      <option value="">All Months</option>
      <?php foreach($months_list as $m): ?><option value="<?= $m ?>" <?= $filter_month===$m?'selected':'' ?>><?= $m ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="<?= BASE_URL ?>modules/fees/fees.php" class="btn btn-secondary">Reset</a>
  </form>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Receipt#</th><th>Student</th><th>Class</th><th>Month</th><th>Total</th><th>Mode</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if ($payments && $payments->num_rows > 0): while($p = $payments->fetch_assoc()): ?>
        <tr>
          <td><strong><?= htmlspecialchars($p['receipt_no']) ?></strong></td>
          <td><?= htmlspecialchars($p['student_name']) ?> <small class="text-muted"><?= htmlspecialchars($p['admission_no']) ?></small></td>
          <td><?= htmlspecialchars(($p['class_name']??'-').' '.($p['section']??'')) ?></td>
          <td><?= htmlspecialchars($p['month']?:'-') ?></td>
          <td class="text-success"><strong><?= $currency ?> <?= number_format($p['paid_amount'],2) ?></strong></td>
          <td><span class="badge badge-info"><?= $p['payment_mode'] ?></span></td>
          <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
          <td class="table-actions">
            <a href="?receipt=<?= $p['id'] ?>" class="btn btn-xs btn-secondary" title="Print Receipt"><i class="fa-solid fa-print"></i>️</a>
            <?php if(current_role()==='admin'): ?>
            <button class="btn btn-xs btn-danger" onclick="confirmDelete('?delete=<?= $p['id'] ?>','<?= htmlspecialchars($p['receipt_no']) ?>')"><i class="fa-solid fa-trash-can"></i>️</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No payment records found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleForm(id){const el=document.getElementById(id);el.style.display=el.style.display==='none'?'block':'none';}
const fhOpts=`<?php foreach($fee_heads_arr as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars(addslashes($f['name'])) ?> (<?= $f['type'] ?>)</option><?php endforeach; ?>`;
function addFeeRow(name, id, amount){
  const row=document.createElement('div');row.className='fee-row form-row';row.style.alignItems='flex-end';
  row.innerHTML=`<div class="form-group"><label>Fee Head</label><select name="fee_head[]" class="form-control"><option value="">-- Select --</option>${fhOpts}</select></div><div class="form-group"><label>Amount</label><input type="number" name="amount[]" class="form-control fee-amount" min="0" step="0.01" placeholder="0" onchange="calcTotal()"></div><div class="form-group" style="flex:0.3"><label>&nbsp;</label><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.fee-row').remove();calcTotal()"><i class="fa-solid fa-xmark"></i></button></div>`;
  document.getElementById('feeRows').appendChild(row);
  if(id){const sel=row.querySelector('select');for(let o of sel.options){if(o.value==id){o.selected=true;break;}}}
  if(amount){const inp=row.querySelector('.fee-amount');inp.value=amount;}
  calcTotal();
}
function calcTotal(){
  let sub=0;document.querySelectorAll('.fee-amount').forEach(i=>sub+=parseFloat(i.value)||0);
  const disc=parseFloat(document.getElementById('discount').value)||0;
  const fine=parseFloat(document.getElementById('fine').value)||0;
  document.getElementById('totalDisplay').value='<?= $currency ?> '+(sub-disc+fine).toFixed(2);
}
// Auto-load fee structure when student is selected
document.querySelector('select[name="student_id"]').addEventListener('change', function(){
  const sid = this.value;
  if(!sid) return;
    fetch('<?= BASE_URL ?>api/data.php?action=student_info&id='+sid)
    .then(r=>r.json()).then(res=>{
      if(res.success && res.data.class_id){
        const d = res.data;
        // Apply student-specific discount if defined
        if (d.discount_amount > 0) {
            if (d.discount_type === 'Percentage') {
                // We'll calculate this after fee rows are loaded
                window.pendingPercentageDiscount = d.discount_amount;
            } else {
                document.getElementById('discount').value = d.discount_amount;
                window.pendingPercentageDiscount = 0;
            }
        } else {
            document.getElementById('discount').value = 0;
            window.pendingPercentageDiscount = 0;
        }

        // Load fee structure for this class
        fetch('<?= BASE_URL ?>api/data.php?action=fee_structure&class_id='+res.data.class_id)
          .then(r=>r.json()).then(fres=>{
            if(fres.success && fres.data.length>0){
              document.getElementById('feeRows').innerHTML='';
              fres.data.forEach(item=>addFeeRow(item.fee_head_name, item.fee_head_id, item.amount));
              
              // If there was a percentage discount, calculate it now based on subtotal
              if (window.pendingPercentageDiscount > 0) {
                  let sub = 0;
                  document.querySelectorAll('.fee-amount').forEach(i=>sub+=parseFloat(i.value)||0);
                  const disc = (sub * window.pendingPercentageDiscount) / 100;
                  document.getElementById('discount').value = disc.toFixed(2);
                  calcTotal();
              }
            }
          });
      }
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




