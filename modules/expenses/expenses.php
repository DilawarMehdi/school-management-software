<?php
$page_title = 'Institution Expenses';
$active_page = 'expenses';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','accountant');

$msg = ''; $err = '';
$currency = $settings['currency'] ?? 'PKR';

// Handle Category Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = $conn->real_escape_string($_POST['category_name'] ?? '');
    $desc = $conn->real_escape_string($_POST['category_desc'] ?? '');
    if ($name) {
        $conn->query("INSERT INTO expense_categories (name, description) VALUES ('$name', '$desc')");
        $msg = 'Category added successfully.';
    }
}

// Handle Expense Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_expense') {
    $cat_id = (int)($_POST['category_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $date = $conn->real_escape_string($_POST['expense_date'] ?? date('Y-m-d'));
    $desc = $conn->real_escape_string($_POST['description'] ?? '');
    $uid = current_uid();

    if ($amount > 0 && $cat_id > 0) {
        $conn->query("INSERT INTO expenses (category_id, amount, description, expense_date, created_by) VALUES ($cat_id, $amount, '$desc', '$date', $uid)");
        $msg = 'Expense recorded successfully.';
    } else {
        $err = 'Please provide valid amount and category.';
    }
}

// Handle Delete
if (isset($_GET['delete']) && current_role() === 'admin') {
    $conn->query("DELETE FROM expenses WHERE id=".(int)$_GET['delete']);
    $msg = 'Expense record deleted.';
}

// Filters
$filter_cat = (int)($_GET['category'] ?? 0);
$filter_start = $conn->real_escape_string($_GET['start_date'] ?? '');
$filter_end = $conn->real_escape_string($_GET['end_date'] ?? '');

$where = "1=1";
if ($filter_cat) $where .= " AND e.category_id=$filter_cat";
if ($filter_start) $where .= " AND e.expense_date >= '$filter_start'";
if ($filter_end) $where .= " AND e.expense_date <= '$filter_end'";

$expenses = $conn->query("SELECT e.*, c.name as category_name FROM expenses e LEFT JOIN expense_categories c ON e.category_id=c.id WHERE $where ORDER BY e.expense_date DESC, e.created_at DESC");
$categories = $conn->query("SELECT * FROM expense_categories ORDER BY name");
$cat_arr = [];
while($c = $categories->fetch_assoc()) $cat_arr[] = $c;

// Stats
$total_all = $conn->query("SELECT SUM(amount) as t FROM expenses")->fetch_assoc()['t'] ?? 0;
$total_month = $conn->query("SELECT SUM(amount) as t FROM expenses WHERE MONTH(expense_date)=MONTH(NOW()) AND YEAR(expense_date)=YEAR(NOW())")->fetch_assoc()['t'] ?? 0;
$total_today = $conn->query("SELECT SUM(amount) as t FROM expenses WHERE DATE(expense_date)=CURDATE()")->fetch_assoc()['t'] ?? 0;
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-wallet"></i> Institution Expenses</h1>
    <p>Track and manage school expenditures and categories</p>
  </div>
  <div class="header-actions">
    <button class="btn btn-secondary" onclick="toggleForm('categoryForm')"><i class="fa-solid fa-tags"></i> Categories</button>
    <button class="btn btn-primary" onclick="toggleForm('expenseForm')"><i class="fa-solid fa-plus"></i> Record Expense</button>
  </div>
</div>

<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="login-error"><?= $err ?></div><?php endif; ?>

<div class="stat-cards" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(9,132,227,.1);color:#0984e3"><i class="fa-solid fa-chart-line"></i></div>
    <div class="stat-info"><h3><?= $currency ?> <?= number_format($total_all,2) ?></h3><p>Total Expenditure</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(255,118,117,.1);color:#ff7675"><i class="fa-solid fa-calendar-days"></i></div>
    <div class="stat-info"><h3><?= $currency ?> <?= number_format($total_month,2) ?></h3><p>This Month</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(0,184,148,.1);color:#00b894"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-info"><h3><?= $currency ?> <?= number_format($total_today,2) ?></h3><p>Spent Today</p></div>
  </div>
</div>

<!-- Category Management Form -->
<div class="card mb-3" id="categoryForm" style="display:none">
  <div class="card-header"><h3><i class="fa-solid fa-tags"></i> Manage Categories</h3></div>
  <form method="POST" class="p-3">
    <input type="hidden" name="action" value="add_category">
    <div class="form-row">
      <div class="form-group"><label>Category Name *</label><input type="text" name="category_name" class="form-control" required placeholder="e.g. Utility Bills, Stationary"></div>
      <div class="form-group"><label>Description</label><input type="text" name="category_desc" class="form-control" placeholder="Optional notes..."></div>
      <div class="form-group" style="display:flex;align-items:flex-end"><button type="submit" class="btn btn-primary">Add Category</button></div>
    </div>
  </form>
  <div class="p-3 pt-0">
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach($cat_arr as $c): ?>
      <span class="badge badge-info" style="padding:6px 12px;font-size:.75rem"><?= htmlspecialchars($c['name']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Expense Recording Form -->
<div class="card mb-3" id="expenseForm" style="display:none">
  <div class="card-header"><h3><i class="fa-solid fa-plus-circle"></i> Record New Expense</h3></div>
  <form method="POST" class="p-3">
    <input type="hidden" name="action" value="save_expense">
    <div class="form-row">
      <div class="form-group">
        <label>Category *</label>
        <select name="category_id" class="form-control" required>
          <option value="">-- Select Category --</option>
          <?php foreach($cat_arr as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Amount (<?= $currency ?>) *</label>
        <input type="number" name="amount" class="form-control" step="0.01" min="0" required placeholder="0.00">
      </div>
      <div class="form-group">
        <label>Expense Date *</label>
        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
      </div>
    </div>
    <div class="form-group">
      <label>Description / Remarks</label>
      <textarea name="description" class="form-control" rows="2" placeholder="What was this expense for?"></textarea>
    </div>
    <div class="btn-group mt-2">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Expense</button>
      <button type="button" class="btn btn-secondary" onclick="toggleForm('expenseForm')">Cancel</button>
    </div>
  </form>
</div>

<!-- Filter Bar -->
<div class="filter-bar card p-3 mb-3">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:0">
    <div class="form-group" style="flex:1;margin-bottom:0">
      <label style="font-size:.7rem;margin-bottom:4px;display:block">Category</label>
      <select name="category" class="form-control">
        <option value="">All Categories</option>
        <?php foreach($cat_arr as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $filter_cat===$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="flex:1;margin-bottom:0">
      <label style="font-size:.7rem;margin-bottom:4px;display:block">Start Date</label>
      <input type="date" name="start_date" class="form-control" value="<?= $filter_start ?>">
    </div>
    <div class="form-group" style="flex:1;margin-bottom:0">
      <label style="font-size:.7rem;margin-bottom:4px;display:block">End Date</label>
      <input type="date" name="end_date" class="form-control" value="<?= $filter_end ?>">
    </div>
    <div style="display:flex;gap:5px">
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="<?= BASE_URL ?>modules/expenses/expenses.php" class="btn btn-secondary">Reset</a>
    </div>
  </form>
</div>

<!-- Expenses Table -->
<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Category</th>
          <th>Description</th>
          <th>Amount</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($expenses && $expenses->num_rows > 0): while($e = $expenses->fetch_assoc()): ?>
        <tr>
          <td><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($e['category_name'] ?? 'Uncategorized') ?></span></td>
          <td><?= htmlspecialchars($e['description']) ?: '<span class="text-muted">No description</span>' ?></td>
          <td class="text-danger"><strong><?= $currency ?> <?= number_format($e['amount'],2) ?></strong></td>
          <td class="table-actions">
            <?php if(current_role()==='admin'): ?>
            <button class="btn btn-xs btn-danger" onclick="confirmDelete('?delete=<?= $e['id'] ?>', 'Expense of <?= $currency ?> <?= $e['amount'] ?>')"><i class="fa-solid fa-trash-can"></i></button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:40px">No expense records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleForm(id) {
    const el = document.getElementById(id);
    el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




