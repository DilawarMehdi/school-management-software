<?php
$page_title = 'Fee Templates';
$active_page = 'fee_templates';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','accountant');

$msg = ''; $err = '';

$fee_templates = [
    'challan' => [
        'label' => 'Challan Style',
        'note'  => 'Official bank challan with due date, student details, fee breakdown, and amount in words.',
        'icon'  => 'fa-file-invoice',
        'color' => '#1a3a5c',
        'accent'=> '#2563eb',
    ],
    'classic' => [
        'label' => 'Classic Style',
        'note'  => 'Traditional fee slip with structured header, itemized list, and summary section.',
        'icon'  => 'fa-file-lines',
        'color' => '#374151',
        'accent'=> '#6366f1',
    ],
    'modern'  => [
        'label' => 'Modern Style',
        'note'  => 'Clean card layout with colored header, bold accents, and rounded design.',
        'icon'  => 'fa-id-card',
        'color' => '#065f46',
        'accent'=> '#00b894',
    ],
    'minimal' => [
        'label' => 'Minimal Style',
        'note'  => 'Simple, compact layout focusing on essential fee details with minimal styling.',
        'icon'  => 'fa-file-alt',
        'color' => '#1f2937',
        'accent'=> '#9ca3af',
    ],
];

$selected_template = $settings['default_fee_template'] ?? 'challan';

// Handle normal form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_template'])) {
    $template_choice = $_POST['template'] ?? '';
    if (isset($fee_templates[$template_choice])) {
        $selected_template = $template_choice;
        $val = $conn->real_escape_string($selected_template);
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('default_fee_template', '$val')
                      ON DUPLICATE KEY UPDATE setting_value = '$val'");
        $msg = 'Default fee template updated to: ' . $fee_templates[$selected_template]['label'];
    } else {
        $err = 'Please select a valid fee template.';
    }
}

require_once __DIR__ . '/../../includes/fee_previews.php';
?>

<style>
/* ── Page Header ── */
.tpl-page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px; flex-wrap: wrap; gap: 10px;
}
.tpl-page-header h1 { font-size: 1.2rem; font-weight: 700; margin: 0; }
.tpl-page-header p  { font-size: .8rem; color: var(--text-muted); margin: 3px 0 0; }

/* ── Active indicator banner ── */
.active-tpl-banner {
    display: flex; align-items: center; gap: 10px;
    background: rgba(0,184,148,.08); border: 1.5px solid rgba(0,184,148,.35);
    border-radius: 10px; padding: 10px 18px; margin-bottom: 22px;
    font-size: .85rem; font-weight: 600; color: #00b894;
}
.active-tpl-banner i { font-size: 1rem; }

/* ── Template Grid ── */
.tpl-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 18px; margin-bottom: 24px;
}

/* ── Template Card ── */
.tpl-card {
    position: relative;
    background: var(--bg-secondary);
    border: 2.5px solid var(--border);
    border-radius: 16px;
    padding: 0;
    cursor: pointer;
    transition: all .25s ease;
    overflow: hidden;
    user-select: none;
}
.tpl-card:hover {
    border-color: #6366f1;
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(99,102,241,.18);
}
.tpl-card.tpl-selected {
    border-color: #00b894;
    box-shadow: 0 0 0 3px rgba(0,184,148,.18), 0 8px 24px rgba(0,184,148,.15);
}

/* ── Selected Checkmark Overlay ── */
.tpl-check-overlay {
    position: absolute;
    top: 12px; right: 12px;
    width: 30px; height: 30px;
    background: #00b894;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: .85rem;
    box-shadow: 0 3px 10px rgba(0,184,148,.45);
    opacity: 0;
    transform: scale(.5) rotate(-45deg);
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    z-index: 5;
    pointer-events: none;
}
.tpl-card.tpl-selected .tpl-check-overlay {
    opacity: 1;
    transform: scale(1) rotate(0deg);
}

/* ── Card Top (coloured preview bar) ── */
.tpl-card-top {
    height: 80px;
    display: flex; align-items: center; justify-content: center;
    position: relative; overflow: hidden;
    border-radius: 13px 13px 0 0;
}
.tpl-card-top .tpl-icon {
    font-size: 2rem;
    color: #fff;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,.25));
    z-index: 2; position: relative;
}
.tpl-card-top::after {
    content: '';
    position: absolute; inset: 0;
    background: repeating-linear-gradient(
        -45deg,
        rgba(255,255,255,.04) 0px, rgba(255,255,255,.04) 1px,
        transparent 1px, transparent 12px
    );
    pointer-events: none;
}

/* ── Card Body ── */
.tpl-card-body { padding: 14px 16px 16px; }
.tpl-card-label {
    font-size: .92rem; font-weight: 700;
    color: var(--text-primary); margin-bottom: 5px;
    display: flex; align-items: center; gap: 7px;
}
.tpl-card-label .tpl-selected-badge {
    font-size: .62rem; font-weight: 800;
    background: #00b894; color: #fff;
    padding: 2px 8px; border-radius: 20px;
    letter-spacing: .3px;
    opacity: 0; transform: scale(.7);
    transition: all .25s ease;
    display: inline-block;
}
.tpl-card.tpl-selected .tpl-card-label .tpl-selected-badge {
    opacity: 1; transform: scale(1);
}
.tpl-card-note {
    font-size: .73rem; color: var(--text-muted);
    line-height: 1.5; margin-bottom: 12px;
}
.tpl-card-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 10px; border-top: 1px solid var(--border);
    font-size: .72rem; color: var(--text-muted);
}
.tpl-card-footer .tpl-use-btn {
    font-size: .72rem; font-weight: 700;
    color: #6366f1; cursor: pointer;
    background: none; border: none; padding: 0;
    transition: color .2s;
}
.tpl-card-footer .tpl-use-btn:hover { color: #4f46e5; }
.tpl-card.tpl-selected .tpl-card-footer .tpl-use-btn { color: #00b894; }

/* ── Saving indicator ── */
#tplSaveStatus {
    font-size: .78rem; font-weight: 600;
    padding: 6px 14px; border-radius: 8px;
    transition: all .25s;
    display: none;
}
#tplSaveStatus.saving { display:block; background: rgba(99,102,241,.1); color: #6366f1; }
#tplSaveStatus.saved  { display:block; background: rgba(0,184,148,.1); color: #00b894; }
#tplSaveStatus.error  { display:block; background: rgba(239,68,68,.1); color: #ef4444; }

/* ── Preview Section ── */
.preview-card {
    background: var(--bg-secondary);
    border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden;
    margin-bottom: 8px;
}
.preview-card-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--border);
}
.preview-card-header h3 { font-size: .9rem; font-weight: 700; margin: 0; }
#previewTitle {
    font-size: .75rem; color: var(--text-muted); font-weight: 600;
}
.preview-card-body {
    padding: 20px;
    transition: opacity .25s;
    max-height: 600px; overflow-y: auto;
}

/* Alert styles */
.alert-success { background: rgba(0,184,148,.08); border:1px solid rgba(0,184,148,.3); color:#00b894; border-radius:10px; padding:10px 16px; font-size:.83rem; font-weight:600; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.alert-error   { background: rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.3); color:#ef4444; border-radius:10px; padding:10px 16px; font-size:.83rem; font-weight:600; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
</style>

<!-- Page Header -->
<div class="tpl-page-header no-print">
    <div>
        <h1><i class="fa-solid fa-palette" style="color:#6366f1;margin-right:8px"></i>Fee Templates</h1>
        <p>Choose a slip design. The selected template is used for all printed fee challans.</p>
    </div>
    <span id="tplSaveStatus"><i class="fa-solid fa-circle-notch fa-spin"></i> <span id="tplSaveMsg">Saving…</span></span>
</div>

<!-- Alerts -->
<?php if ($msg): ?>
<div class="alert-success no-print"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
<?php elseif ($err): ?>
<div class="alert-error no-print"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<!-- Active Template Banner -->
<div class="active-tpl-banner no-print" id="activeBanner">
    <i class="fa-solid fa-circle-check"></i>
    <span>Currently active template: <strong id="activeTplName"><?= htmlspecialchars($fee_templates[$selected_template]['label']) ?></strong></span>
    <span style="margin-left:auto;font-size:.72rem;color:var(--text-muted);font-weight:400">All fee slips will print in this format</span>
</div>

<!-- Template Grid -->
<form id="templateForm" method="post">
<div class="tpl-grid no-print">
<?php foreach ($fee_templates as $key => $tpl):
    $is_sel = ($selected_template === $key);
?>
    <div class="tpl-card <?= $is_sel ? 'tpl-selected' : '' ?>"
         id="card-<?= $key ?>"
         onclick="selectTemplate('<?= $key ?>')">

        <!-- Checkmark Overlay -->
        <div class="tpl-check-overlay">
            <i class="fa-solid fa-check"></i>
        </div>

        <!-- Coloured Top Bar -->
        <div class="tpl-card-top" style="background: linear-gradient(135deg, <?= $tpl['color'] ?> 0%, <?= $tpl['accent'] ?> 100%);">
            <i class="fa-solid <?= $tpl['icon'] ?> tpl-icon"></i>
        </div>

        <!-- Card Body -->
        <div class="tpl-card-body">
            <div class="tpl-card-label">
                <?= htmlspecialchars($tpl['label']) ?>
                <span class="tpl-selected-badge">✓ Active</span>
            </div>
            <div class="tpl-card-note"><?= htmlspecialchars($tpl['note']) ?></div>
            <div class="tpl-card-footer">
                <span>Preview below</span>
                <button type="button" class="tpl-use-btn" onclick="event.stopPropagation();selectTemplate('<?= $key ?>')">
                    <?= $is_sel ? '<i class="fa-solid fa-circle-check"></i> In Use' : 'Use This' ?>
                </button>
            </div>
        </div>

        <input type="radio" name="template" value="<?= $key ?>" <?= $is_sel ? 'checked' : '' ?> hidden>
    </div>
<?php endforeach; ?>
</div>
</form>

<!-- Preview Section -->
<div class="preview-card">
    <div class="preview-card-header">
        <h3><i class="fa-solid fa-eye" style="color:#6366f1;margin-right:6px"></i>Live Preview</h3>
        <span id="previewTitle">
            <i class="fa-solid fa-<?= htmlspecialchars($fee_templates[$selected_template]['icon']) ?>"></i>
            <?= htmlspecialchars($fee_templates[$selected_template]['label']) ?>
        </span>
    </div>
    <div class="preview-card-body" id="previewContainer">
        <?= renderSlipPreview($selected_template) ?>
    </div>
</div>

<script>
const templates = <?= json_encode($fee_templates, JSON_HEX_TAG) ?>;
let currentSelected = '<?= $selected_template ?>';

function selectTemplate(key) {
    if (currentSelected === key) return; // already selected, no need to re-save

    currentSelected = key;

    // 1. Update all cards visual state
    document.querySelectorAll('.tpl-card').forEach(card => {
        card.classList.remove('tpl-selected');
    });
    const activeCard = document.getElementById('card-' + key);
    if (activeCard) activeCard.classList.add('tpl-selected');

    // 2. Update radio inputs
    document.querySelectorAll('input[name="template"]').forEach(r => r.checked = false);
    const radio = activeCard ? activeCard.querySelector('input[type="radio"]') : null;
    if (radio) radio.checked = true;

    // 3. Update "Use This" / "In Use" button text on all cards
    document.querySelectorAll('.tpl-use-btn').forEach(btn => {
        btn.innerHTML = 'Use This';
    });
    if (activeCard) {
        const btn = activeCard.querySelector('.tpl-use-btn');
        if (btn) btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> In Use';
    }

    // 4. Update active banner
    const nameEl = document.getElementById('activeTplName');
    if (nameEl && templates[key]) nameEl.textContent = templates[key].label;

    // 5. Update preview title
    const prevTitle = document.getElementById('previewTitle');
    if (prevTitle && templates[key]) {
        prevTitle.innerHTML = '<i class="fa-solid ' + templates[key].icon + '"></i> ' + templates[key].label;
    }

    // 6. Show saving status
    setStatus('saving', '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…');

    // 7. Fade out preview while loading
    const container = document.getElementById('previewContainer');
    container.style.opacity = '0.4';
    container.style.pointerEvents = 'none';

    // 8. Save + preview via api/data.php
    const savePromise = fetch('<?= BASE_URL ?>api/data.php?action=save_template', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'template=' + encodeURIComponent(key)
    }).then(r => r.json());

    const previewPromise = fetch('<?= BASE_URL ?>api/data.php?action=render_template&template=' + encodeURIComponent(key))
        .then(r => r.text());

    Promise.all([savePromise, previewPromise])
        .then(([saveResult, html]) => {
            container.innerHTML = html;
            container.style.opacity = '1';
            container.style.pointerEvents = '';
            if (saveResult.success) {
                setStatus('saved', '<i class="fa-solid fa-circle-check"></i> Saved — ' + saveResult.label);
                setTimeout(() => hideStatus(), 3000);
            } else {
                setStatus('error', '<i class="fa-solid fa-triangle-exclamation"></i> Save failed');
                setTimeout(() => hideStatus(), 4000);
            }
        })
        .catch(err => {
            container.style.opacity = '1';
            container.style.pointerEvents = '';
            setStatus('error', '<i class="fa-solid fa-triangle-exclamation"></i> Connection error');
            console.error('Template switch error:', err);
            setTimeout(() => hideStatus(), 4000);
        });
}

function setStatus(type, html) {
    const el = document.getElementById('tplSaveStatus');
    el.className = type;
    el.innerHTML = html;
}
function hideStatus() {
    const el = document.getElementById('tplSaveStatus');
    el.style.display = 'none';
    setTimeout(() => { el.style.display = ''; el.className = ''; }, 100);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




