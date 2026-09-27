<?php
/**
 * SIAX SMSS - Monthly Fee Invoices
 * Updated to the Premium SIAX Design System.
 */
ob_start();
$page_title = 'Monthly Fee Invoices';
$active_page = 'monthly_fee_invoices';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$msg = ''; $err = '';

if (isset($_GET['msg']) && $_GET['msg'] == 'generated') $msg = 'Invoices generated successfully.';

// Handle Deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM fee_invoices WHERE id = $id");
    $conn->query("DELETE FROM student_monthly_fees WHERE invoice_id = $id");
    header("Location: " . BASE_URL . "modules/fees/monthly_fee_invoices.php");
    exit;
}

$active_session = $settings['session_year'] ?? '2025-2026';

$invoices = $conn->query("
    SELECT 
        fi.id,
        fi.title,
        fi.month,
        fi.session_year,
        fi.created_at,
        fi.due_date,
        fi.valid_till,
        fi.student_count,
        COALESCE(SUM(smf.prev_pending), 0) as prev_pending,
        COALESCE(SUM(smf.tuition_fee + smf.admission_fee), 0) as current_fee,
        COALESCE(SUM(smf.paid_amount), 0) as received_fee,
        COALESCE(SUM(smf.discount_amount), 0) as waived_fee,
        COALESCE(SUM(smf.fine_amount), 0) as fine_amount
    FROM fee_invoices fi
    LEFT JOIN student_monthly_fees smf ON fi.id = smf.invoice_id
    WHERE fi.session_year = '$active_session'
    GROUP BY fi.id
    ORDER BY fi.created_at DESC
");
?>

<style>
/* PREMIUM SIAX STYLE OVERRIDES */
.invoice-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-top: 25px;
}
.invoice-item-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: var(--shadow-sm);
}
.invoice-item-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--accent-glow);
}

.inv-main-info {
    display: flex;
    align-items: center;
    padding: 20px;
    gap: 25px;
}
.inv-icon-box {
    width: 60px;
    height: 60px;
    background: var(--accent-glow);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--accent-light);
}

.inv-details { flex: 1; }
.inv-title-row { display: flex; align-items: center; gap: 15px; margin-bottom: 8px; }
.inv-title-row h3 { font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-primary); }
.inv-meta-row { display: flex; gap: 15px; font-size: 0.8rem; color: var(--text-secondary); }
.inv-meta-row span { display: flex; align-items: center; gap: 6px; }

.fee-stats-container {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    background: var(--bg-secondary);
    padding: 15px 20px;
    border-top: 1px solid var(--border);
}
.fee-stat-item {
    text-align: center;
    border-right: 1px solid var(--border);
}
.fee-stat-item:last-child { border-right: none; }
.stat-label { font-size: 0.7rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 4px; }
.stat-value { font-size: 1rem; font-weight: 700; color: var(--text-primary); }

/* Color Indicators */
.text-payable { color: #f59e0b; }
.text-received { color: #10b981; }
.text-pending { color: #ef4444; }

.action-bar {
    padding: 15px 20px;
    background: var(--bg-card);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border);
    border-radius: 0 0 var(--radius-md) var(--radius-md);
}

/* Custom Dropdown Styling */
.actions-dropdown {
    position: relative;
    display: inline-block;
}
.btn-actions-trigger {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    color: var(--text-primary);
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.btn-actions-trigger:hover { background: var(--border); }

.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    min-width: 200px;
    box-shadow: var(--shadow-lg);
    z-index: 100;
    margin-top: 8px;
    overflow: hidden;
    backdrop-filter: blur(10px);
}
.dropdown-section { padding: 5px 0; border-bottom: 1px solid var(--border); }
.dropdown-section:last-child { border-bottom: none; }
.dropdown-title { padding: 8px 15px; font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
.dropdown-item {
    padding: 10px 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: background 0.2s;
}
.dropdown-item:hover { background: var(--bg-secondary); }
.dropdown-item i { width: 16px; text-align: center; font-size: 0.9rem; }

.btn-generate-premium {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.9rem;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: transform 0.2s;
}
.btn-generate-premium:hover { transform: translateY(-2px); }
</style>

<div class="main-container">
    <div class="page-header">
        <div>
            <h1>Monthly Fee Invoices</h1>
            <p>Generate, manage and track batch fee collection</p>
        </div>
        <a href="<?= BASE_URL ?>modules/fees/generate_invoice.php" class="btn-generate-premium">
            <i class="fa-solid fa-plus-circle"></i> Generate New Invoice
        </a>
    </div>

    <?php if($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:15px"><?= $msg ?></div><?php endif; ?>

    <div class="invoice-grid">
        <?php if ($invoices && $invoices->num_rows > 0): while($row = $invoices->fetch_assoc()): ?>
            <div class="invoice-item-card">
                <div class="inv-main-info">
                    <div class="inv-icon-box">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <div class="inv-details">
                        <div class="inv-title-row">
                            <h3><?= htmlspecialchars($row['title']) ?></h3>
                            <span class="badge badge-info" style="font-size: 0.7rem; background: var(--accent-glow); color: var(--accent-light);">
                                <i class="fa-solid fa-users"></i> <?= $row['student_count'] ?> Students
                            </span>
                        </div>
                        <div class="inv-meta-row">
                            <span><i class="fa-solid fa-calendar-plus"></i> Created: <?= date('d M Y', strtotime($row['created_at'])) ?></span>
                            <span style="color: #ef4444;"><i class="fa-solid fa-calendar-day"></i> Due: <?= date('d M Y', strtotime($row['due_date'])) ?></span>
                            <span><i class="fa-solid fa-calendar-check"></i> Session: <?= htmlspecialchars($row['session_year']) ?></span>
                        </div>
                    </div>
                    <div class="inv-reminders" style="display: flex; gap: 10px;">
                        <button class="btn btn-sm btn-secondary" style="border-radius: 20px; padding: 6px 16px; background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2); color: #3b82f6;"
                            onclick="importPrevPending(<?= $row['id'] ?>, this)">
                            <i class="fa-solid fa-cloud-download"></i> Import Previous Amount
                        </button>
                        <button class="btn btn-sm btn-secondary" style="border-radius: 20px; padding: 6px 16px;">
                            <i class="fa-solid fa-comment-sms" style="color: #10b981;"></i> Send SMS Reminder
                        </button>
                    </div>
                </div>

                <div class="fee-stats-container">
                    <div class="fee-stat-item">
                        <div class="stat-label">Prev Pending</div>
                        <div class="stat-value" id="prev-pending-<?= $row['id'] ?>"><?= number_format($row['prev_pending'], 0) ?></div>
                    </div>
                    <div class="fee-stat-item">
                        <div class="stat-label">Current Fee</div>
                        <div class="stat-value"><?= number_format($row['current_fee'], 0) ?></div>
                    </div>
                    <div class="fee-stat-item">
                        <div class="stat-label">Total Payable</div>
                        <div class="stat-value text-payable"><?= number_format($row['current_fee'] + $row['fine_amount'], 0) ?></div>
                    </div>
                    <div class="fee-stat-item">
                        <div class="stat-label">Received</div>
                        <div class="stat-value text-received"><?= number_format($row['received_fee'], 0) ?></div>
                    </div>
                    <div class="fee-stat-item">
                        <div class="stat-label">Pending Balance</div>
                        <div class="stat-value text-pending"><?= number_format(($row['current_fee'] + $row['fine_amount']) - ($row['received_fee'] + $row['waived_fee']), 0) ?></div>
                    </div>
                </div>

                <div class="action-bar">
                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                        <i class="fa-solid fa-info-circle"></i> Status: <strong>1BILL OFF</strong>
                    </div>
                    <div class="actions-dropdown">
                        <button class="btn-actions-trigger" onclick="toggleDropdown(<?= $row['id'] ?>)">
                            <i class="fa-solid fa-bars"></i> Actions <i class="fa-solid fa-chevron-down" style="font-size: 0.6rem;"></i>
                        </button>
                        <div class="dropdown-content" id="drop-<?= $row['id'] ?>">
                            <div class="dropdown-section">
                                <div class="dropdown-title">Reports</div>
                                <a href="<?= BASE_URL ?>prints/print_challan_single.php?invoice_id=<?= $row['id'] ?>" target="_blank" class="dropdown-item"><i class="fa-solid fa-file-invoice" style="color: #a855f7;"></i> Print All Slips (New)</a>
                                <a href="<?= BASE_URL ?>modules/fees/invoice_detail.php?id=<?= $row['id'] ?>" class="dropdown-item"><i class="fa-solid fa-eye" style="color: #3b82f6;"></i> Invoice Detail</a>
                                <a href="#" class="dropdown-item"><i class="fa-solid fa-file-invoice" style="color: #a855f7;"></i> Invoice Summary</a>
                                <a href="#" class="dropdown-item"><i class="fa-solid fa-chart-pie" style="color: #f59e0b;"></i> Invoice Status</a>
                                <a href="#" class="dropdown-item"><i class="fa-solid fa-chart-line" style="color: #10b981;"></i> Invoice Reports</a>
                            </div>
                            <div class="dropdown-section">
                                <div class="dropdown-title">Manage</div>
                                <a href="#" class="dropdown-item" style="color: #3b82f6;"><i class="fa-solid fa-edit"></i> Edit Invoice</a>
                                <a href="?delete=<?= $row['id'] ?>" class="dropdown-item" style="color: #ef4444;" onclick="return confirm('Delete this invoice?')">
                                    <i class="fa-solid fa-trash"></i> Delete Invoice
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="empty-state" style="padding: 100px; background: var(--bg-card); border-radius: 12px; border: 1px dashed var(--border);">
                <i class="fa-solid fa-file-invoice-dollar" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px;"></i>
                <h3>No Invoices Found</h3>
                <p>Generate your first batch invoice using the button above.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDropdown(id) {
    const drops = document.querySelectorAll('.dropdown-content');
    drops.forEach(d => {
        if(d.id !== 'drop-'+id) {
            d.style.display = 'none';
            const card = d.closest('.invoice-item-card');
            if (card) card.style.zIndex = '1';
        }
    });
    const el = document.getElementById('drop-'+id);
    const card = el.closest('.invoice-item-card');
    
    if (el.style.display === 'block') {
        el.style.display = 'none';
        if (card) card.style.zIndex = '1';
    } else {
        el.style.display = 'block';
        if (card) card.style.zIndex = '50';
    }
}
window.onclick = function(event) {
    if (!event.target.closest('.actions-dropdown')) {
        document.querySelectorAll('.dropdown-content').forEach(d => {
            d.style.display = 'none';
            const card = d.closest('.invoice-item-card');
            if (card) card.style.zIndex = '1';
        });
    }
}

function importPrevPending(invoiceId, btn) {
    if (!confirm('This will recalculate and import previous unpaid amounts for all students in this invoice. Continue?')) return;
    
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';

    fetch('<?= BASE_URL ?>api/import_prev_pending.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'invoice_id=' + invoiceId
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        if (res.success) {
            // Update the displayed Prev Pending value live
            const el = document.getElementById('prev-pending-' + invoiceId);
            if (el) el.textContent = parseInt(res.total_pending).toLocaleString();
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Imported!';
            btn.style.background = 'rgba(16, 185, 129, 0.15)';
            btn.style.color = '#10b981';
            setTimeout(() => {
                btn.innerHTML = origText;
                btn.style.background = '';
                btn.style.color = '';
            }, 3000);
        } else {
            alert('Error: ' + res.message);
            btn.innerHTML = origText;
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = origText;
        alert('Network error. Please try again.');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>




