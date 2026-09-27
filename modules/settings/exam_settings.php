<?php
/**
 * SIAC SMSS - Exam & Result Card Settings
 * Controls display options like position in result cards.
 */
$page_title  = 'Exam Settings';
$active_tab  = 'academics';
$active_page = 'exam_settings';
require_once __DIR__ . '/../../includes/header.php';

// Only admin/principal can access
if (!in_array($role, ['admin', 'principal'])) {
    header('Location: ' . BASE_URL . 'dashboard.php?err=access_denied');
    exit;
}

$success = '';
$error   = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_exam_settings') {
    $show_position = isset($_POST['show_position_in_result']) ? '1' : '0';

    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('show_position_in_result', '$show_position')
                  ON DUPLICATE KEY UPDATE setting_value='$show_position'");

    $success = 'Exam settings saved successfully!';
    $settings = all_settings($conn); // Reload settings after save
}

// Read current settings
$show_position = $settings['show_position_in_result'] ?? '1';
?>

<div class="main-container">
  <div class="page-header">
    <h1><i class="fa-solid fa-sliders" style="color:#6366f1;"></i> Exam & Result Card Settings</h1>
    <p>Configure display options for exam result cards and reports.</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success" style="border-radius:10px; margin-bottom:20px;">
      <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger" style="border-radius:10px; margin-bottom:20px;">
      <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <div class="row justify-content-center">
    <div class="col-md-7">

      <!-- ── Result Card Settings ── -->
      <div class="siax-card" style="border-radius:14px; margin-bottom:24px;">
        <div class="card-body" style="padding:28px;">
          <div style="display:flex; align-items:center; gap:14px; margin-bottom:22px; border-bottom:1px solid var(--border); padding-bottom:16px;">
            <div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa-solid fa-id-card-clip" style="color:#fff;font-size:1.2rem;"></i>
            </div>
            <div>
              <h5 style="margin:0;font-weight:800;color:var(--text-primary);">Result Card Display Options</h5>
              <small style="color:var(--text-muted);">Control what information appears on printed result cards</small>
            </div>
          </div>

          <form method="POST">
            <input type="hidden" name="action" value="save_exam_settings">

            <!-- Show Position Setting -->
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:18px; background:var(--bg-secondary); border-radius:12px; margin-bottom:16px; border:1.5px solid var(--border);">
              <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                  <i class="fa-solid fa-trophy" style="color:#f59e0b; font-size:1rem;"></i>
                  <strong style="font-size:.95rem; color:var(--text-primary);">Show Class Position in Result Card</strong>
                </div>
                <p style="margin:0; font-size:.83rem; color:var(--text-muted); line-height:1.5;">
                  When enabled, each student's <strong>class position / rank</strong> (e.g. 1st, 2nd, 3rd…) based on their total percentage will be printed on their result card.
                  Disable this if you do not want position rankings to appear on printed cards.
                </p>
              </div>
              <div style="flex-shrink:0; display:flex; align-items:center; gap:10px; margin-top:4px;">
                <label class="toggle-switch" style="position:relative;display:inline-block;width:52px;height:28px;">
                  <input type="checkbox" name="show_position_in_result" id="show_position_check"
                         <?= $show_position === '1' ? 'checked' : '' ?>
                         style="opacity:0;width:0;height:0;">
                  <span class="toggle-slider" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:<?= $show_position==='1'?'#6366f1':'#cbd5e1'?>;border-radius:28px;transition:.3s;" id="toggleSlider"></span>
                  <span style="position:absolute;content:'';height:22px;width:22px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.25);" id="toggleThumb"></span>
                </label>
                <span style="font-size:.82rem; font-weight:700; color:<?= $show_position==='1'?'#6366f1':'#94a3b8'?>;" id="toggleLabel">
                  <?= $show_position==='1' ? 'ON' : 'OFF' ?>
                </span>
              </div>
            </div>

            <!-- Preview hint -->
            <div class="alert" style="background:linear-gradient(135deg,#f0f4ff,#e8ecff); border:1px solid #c7d2fe; border-radius:10px; padding:12px 16px; margin-bottom:22px;">
              <div style="display:flex; gap:10px; align-items:flex-start;">
                <i class="fa-solid fa-circle-info" style="color:#6366f1; margin-top:2px; flex-shrink:0;"></i>
                <div style="font-size:.83rem; color:#3730a3; line-height:1.5;">
                  <strong>Preview:</strong> When <em>Show Position</em> is ON, the result card will display a row like
                  <strong>"Class Position: 3rd out of 40 students"</strong> in the Overall Result banner.
                  Rankings are calculated automatically by comparing total percentage across the whole class.
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-weight:700; border-radius:10px; font-size:.95rem;">
              <i class="fa-solid fa-floppy-disk me-2"></i> Save Exam Settings
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<style>
#show_position_check:checked + .toggle-slider { background: #6366f1 !important; }
</style>

<script>
const cb = document.getElementById('show_position_check');
const slider = document.getElementById('toggleSlider');
const thumb  = document.getElementById('toggleThumb');
const lbl    = document.getElementById('toggleLabel');

function updateToggleUI() {
  if (cb.checked) {
    slider.style.background = '#6366f1';
    thumb.style.left = '27px';
    lbl.textContent = 'ON';
    lbl.style.color = '#6366f1';
  } else {
    slider.style.background = '#cbd5e1';
    thumb.style.left = '3px';
    lbl.textContent = 'OFF';
    lbl.style.color = '#94a3b8';
  }
}

cb.addEventListener('change', updateToggleUI);
// Fix initial thumb position on page load
updateToggleUI();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
