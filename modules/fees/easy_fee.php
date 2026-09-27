<?php
$page_title = 'Quick Fee Pay';
$active_page = 'easy_fee';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','accountant');
?>

<style>
    .quick-pay-container { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; margin-top: 20px; min-height: 500px; }
    .search-section { background: var(--bg-card); padding: 20px; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 20px; position: relative; }
    .search-results { position: absolute; top: 100%; left: 20px; right: 20px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; z-index: 100; box-shadow: var(--shadow-lg); display: none; max-height: 300px; overflow-y: auto; }
    .search-item { padding: 12px 20px; cursor: pointer; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; }
    .search-item:hover { background: var(--bg-secondary); }

    .history-card, .payment-card { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); padding: 20px; }
    .card-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: var(--accent); }
    
    .history-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .history-table th, .history-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
    .history-table th { background: var(--bg-secondary); font-weight: 700; }

    .due-info { background: var(--bg-secondary); padding: 15px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid var(--accent); }
    .due-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
    .total-due { font-size: 1.2rem; color: var(--accent); border-top: 1px solid var(--border); padding-top: 10px; margin-top: 10px; }

    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: var(--text-secondary); }
    .form-control { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-primary); font-weight: 600; }
    
    .btn-pay { width: 100%; padding: 14px; background: var(--accent); color: #fff; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: 0.3s; }
    .btn-pay:hover { opacity: 0.9; transform: translateY(-2px); }

    .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: var(--text-muted); text-align: center; }
    .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.3; }
</style>

<div class="page-header">
    <div>
        <h1><i class="fa-solid fa-bolt"></i> Quick Fee Pay</h1>
        <p>Instant student search and fee collection</p>
    </div>
</div>

<div class="search-section">
    <div style="position: relative;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
        <input type="text" id="studentSearch" class="form-control" placeholder="Type Student Name or Registration Number (e.g. 349-26)..." style="padding-left: 45px; font-size: 1rem;">
    </div>
    <div id="searchResults" class="search-results"></div>
</div>

<div id="quickPayInterface" class="quick-pay-container" style="display: none;">
    <!-- LEFT: HISTORY -->
    <div class="history-card">
        <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Payment History</div>
        <div id="historyContent">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Month</th>
                        <th>Paid</th>
                        <th>Fine</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="historyBody"></tbody>
            </table>
        </div>
    </div>

    <!-- RIGHT: PAYMENT FORM -->
    <div class="payment-card">
        <div class="card-title"><i class="fa-solid fa-credit-card"></i> Current Dues</div>
        
        <div id="paymentContent">
            <div class="due-info">
                <div class="due-row"><span>Student:</span><span id="disp_name">---</span></div>
                <div class="due-row"><span>Class:</span><span id="disp_class">---</span></div>
                <div class="due-row"><span>Month:</span><span id="disp_month">---</span></div>
                <div class="due-row total-due"><span>Total Balance:</span><span id="disp_total">0.00</span></div>
            </div>

            <form id="payForm">
                <input type="hidden" name="action" value="quick_pay">
                <input type="hidden" name="monthly_fee_id" id="form_mid">
                
                <div class="form-group">
                    <label>Amount to Receive (Fee)</label>
                    <input type="number" name="amount_paid" id="in_amt" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Fine to Receive (If any)</label>
                    <input type="number" name="fine_paid" id="in_fine" class="form-control" value="0">
                </div>
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <button type="submit" class="btn-pay" id="payBtn">SAVE & MARK PAID</button>
            </form>
        </div>
    </div>
</div>

<div id="emptyInterface" class="empty-state">
    <i class="fa-solid fa-user-graduate"></i>
    <h3>No Student Selected</h3>
    <p>Search for a student using the bar above to load their fee record.</p>
</div>

<!-- SweetAlert2 for nice alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const searchInp = document.getElementById('studentSearch');
const resultsBox = document.getElementById('searchResults');
const interface = document.getElementById('quickPayInterface');
const emptyState = document.getElementById('emptyInterface');

searchInp.addEventListener('input', function() {
    const q = this.value.trim();
    if(q.length < 2) {
        resultsBox.style.display = 'none';
        return;
    }

    fetch('<?= BASE_URL ?>api/data.php?action=search_students&q=' + encodeURIComponent(q))
    .then(r => r.json())
    .then(res => {
        if(res.success && res.data.length > 0) {
            resultsBox.innerHTML = '';
            res.data.forEach(s => {
                const div = document.createElement('div');
                div.className = 'search-item';
                div.innerHTML = `<span><strong>${s.admission_no}</strong> | ${s.name}</span> <small>${s.class_name} ${s.section}</small>`;
                div.onclick = () => loadStudent(s);
                resultsBox.appendChild(div);
            });
            resultsBox.style.display = 'block';
        } else {
            resultsBox.style.display = 'none';
        }
    });
});

function loadStudent(student) {
    resultsBox.style.display = 'none';
    searchInp.value = student.name;
    
    // Fetch Dues and History
    fetch('<?= BASE_URL ?>api/data.php?action=quick_fee_data&student_id=' + student.id)
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            interface.style.display = 'grid';
            emptyState.style.display = 'none';
            
            // Populate Current Due (Most recent unpaid)
            if(res.due) {
                document.getElementById('disp_name').innerText = student.name;
                document.getElementById('disp_class').innerText = student.class_name + ' ' + student.section;
                document.getElementById('disp_month').innerText = res.due.month;
                
                const balance = (parseFloat(res.due.tuition_fee) + parseFloat(res.due.prev_pending) + (parseFloat(res.due.admission_fee)||0) + parseFloat(res.due.fine_amount)) - (parseFloat(res.due.discount_amount) + parseFloat(res.due.paid_amount));
                document.getElementById('disp_total').innerText = balance.toFixed(2);
                document.getElementById('in_amt').value = balance;
                document.getElementById('form_mid').value = res.due.id;
                document.getElementById('payBtn').disabled = false;
                document.getElementById('payBtn').innerText = 'SAVE & MARK PAID';
            } else {
                document.getElementById('disp_total').innerText = '0.00 (All Clear)';
                document.getElementById('payBtn').disabled = true;
                document.getElementById('payBtn').innerText = 'NO PENDING DUES';
            }

            // Populate History
            const histBody = document.getElementById('historyBody');
            histBody.innerHTML = '';
            if(res.history.length > 0) {
                res.history.forEach(h => {
                    histBody.innerHTML += `<tr>
                        <td>${h.payment_date}</td>
                        <td>${h.month}</td>
                        <td class="text-success"><strong>${h.amount_paid}</strong></td>
                        <td class="text-danger">${h.fine_paid}</td>
                        <td><span class="badge-siax badge-paid">Paid</span></td>
                    </tr>`;
                });
            } else {
                histBody.innerHTML = '<tr><td colspan="5" class="text-center">No payment history found.</td></tr>';
            }
        }
    });
}

document.getElementById('payForm').onsubmit = function(e) {
    e.preventDefault();
    const btn = document.getElementById('payBtn');
    btn.disabled = true;
    btn.innerText = 'Processing...';

    const formData = new FormData(this);
    fetch('<?= BASE_URL ?>api/quick_pay_save.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            Swal.fire({ icon: 'success', title: 'Payment Saved!', text: 'The fee has been recorded successfully.', showConfirmButton: false, timer: 1500 });
            // Refresh student data
            loadStudent({id: res.student_id, name: searchInp.value, class_name: '', section: ''}); 
        } else {
            alert('Error: ' + res.message);
            btn.disabled = false;
            btn.innerText = 'SAVE & MARK PAID';
        }
    });
};

// Close results when clicking outside
window.onclick = (e) => { if(!e.target.closest('.search-section')) resultsBox.style.display = 'none'; };
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




