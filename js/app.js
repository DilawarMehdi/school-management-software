/* ============================================================
   SIAX SMSS — Global JavaScript
   ============================================================ */

// Sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const mainWrapper = document.getElementById('mainWrapper');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const mobileToggle = document.getElementById('mobileToggle');

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      if (sidebar.classList.contains('collapsed')) {
        sidebar.style.width = '72px';
        mainWrapper.style.marginLeft = '72px';
        document.querySelectorAll('.nav-label,.logo-text,.user-info,.nav-section,.sidebar-user').forEach(el => el.style.display = 'none');
      } else {
        sidebar.style.width = '';
        mainWrapper.style.marginLeft = '';
        document.querySelectorAll('.nav-label,.logo-text,.user-info,.nav-section,.sidebar-user').forEach(el => el.style.display = '');
      }
    });
  }

  if (mobileToggle) {
    mobileToggle.addEventListener('click', function() {
      sidebar.classList.toggle('open');
    });
  }

  // Close sidebar on outside click (mobile)
  document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
      if (!sidebar.contains(e.target) && e.target !== mobileToggle) {
        sidebar.classList.remove('open');
      }
    }
  });
});

// Toast notifications
function showToast(message, type = 'success', duration = 4000) {
  const container = document.getElementById('toastContainer');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = 'toast toast-' + type;
  toast.innerHTML = message;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(40px)';
    toast.style.transition = 'all .3s';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// Modal
function openModal(title, body, onConfirm) {
  const modal = document.getElementById('globalModal');
  document.getElementById('globalModalTitle').textContent = title;
  document.getElementById('globalModalBody').innerHTML = body;
  const confirmBtn = document.getElementById('globalModalConfirm');
  confirmBtn.onclick = function() {
    if (onConfirm) onConfirm();
    closeModal();
  };
  modal.style.display = 'flex';
}
function closeModal() {
  document.getElementById('globalModal').style.display = 'none';
}

// Confirm delete
function confirmDelete(url, name) {
  openModal('Confirm Delete',
    '<p>Are you sure you want to delete <strong>' + name + '</strong>? This cannot be undone.</p>',
    function() { window.location.href = url; }
  );
}

// Tabs
function initTabs() {
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
      const group = this.closest('.tabs').parentElement;
      group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      group.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      const target = document.getElementById(this.dataset.tab);
      if (target) target.classList.add('active');
    });
  });
}
document.addEventListener('DOMContentLoaded', initTabs);

// Format currency
function formatCurrency(amount, currency) {
  currency = currency || 'PKR';
  return currency + ' ' + parseFloat(amount).toLocaleString('en-PK', {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

// Live search for tables
function tableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(filter) ? '' : 'none';
    });
  });
}

// Print helper
function printArea(elementId) {
  const content = document.getElementById(elementId);
  if (!content) return;
  const win = window.open('', '_blank');
  win.document.write('<html><head><title>Print</title>');
  win.document.write('<link rel="stylesheet" href="css/main.css">');
  win.document.write('<link rel="stylesheet" href="css/print.css">');
  win.document.write('<style>body{background:#fff;color:#000;padding:20px}</style>');
  win.document.write('</head><body>');
  win.document.write(content.innerHTML);
  win.document.write('</body></html>');
  win.document.close();
  setTimeout(() => { win.print(); }, 500);
}
