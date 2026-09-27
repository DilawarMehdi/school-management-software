<?php
$page_title  = 'Digital Student ID Cards';
$active_page = 'student_id_cards';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','accountant','teacher');

/* ── POST handlers ── */
$bg_key      = 'id_card_bg';
$bg_val      = $settings[$bg_key] ?? '';
$valid_until = $settings['id_card_valid_until'] ?? date('Y').'-12-31';
$bg_mode     = $settings['id_card_bg_mode'] ?? 'center';
$bg_opacity  = $settings['id_card_bg_opacity'] ?? '0.15';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['card_bg']['name']) && $_FILES['card_bg']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['card_bg']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $fname = 'id_card_bg_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['card_bg']['tmp_name'], UPLOAD_PATH.$fname)) {
                if ($bg_val && file_exists(UPLOAD_PATH.$bg_val)) unlink(UPLOAD_PATH.$bg_val);
                $esc = $conn->real_escape_string($fname);
                $conn->query("INSERT INTO settings (setting_key,setting_value) VALUES ('$bg_key','$esc')
                              ON DUPLICATE KEY UPDATE setting_value='$esc'");
                $bg_val = $fname;
            }
        }
    }
    if (isset($_POST['remove_bg'])) {
        if ($bg_val && file_exists(UPLOAD_PATH.$bg_val)) unlink(UPLOAD_PATH.$bg_val);
        $conn->query("DELETE FROM settings WHERE setting_key='$bg_key'");
        $bg_val = '';
    }
    if (!empty($_POST['valid_until'])) {
        $esc = $conn->real_escape_string($_POST['valid_until']);
        $conn->query("INSERT INTO settings (setting_key,setting_value) VALUES ('id_card_valid_until','$esc')
                      ON DUPLICATE KEY UPDATE setting_value='$esc'");
        $valid_until = $_POST['valid_until'];
    }
    if (isset($_POST['bg_mode'])) {
        $esc = $conn->real_escape_string($_POST['bg_mode']);
        $conn->query("INSERT INTO settings (setting_key,setting_value) VALUES ('id_card_bg_mode','$esc')
                      ON DUPLICATE KEY UPDATE setting_value='$esc'");
        $bg_mode = $_POST['bg_mode'];
    }
    if (isset($_POST['bg_opacity'])) {
        $esc = $conn->real_escape_string($_POST['bg_opacity']);
        $conn->query("INSERT INTO settings (setting_key,setting_value) VALUES ('id_card_bg_opacity','$esc')
                      ON DUPLICATE KEY UPDATE setting_value='$esc'");
        $bg_opacity = $_POST['bg_opacity'];
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
}

$bg_url   = ($bg_val && file_exists(UPLOAD_PATH.$bg_val))
            ? BASE_URL.'uploads/'.htmlspecialchars($bg_val) : '';
$logo_url = '';
if (!empty($settings['school_logo']) && file_exists(UPLOAD_PATH.$settings['school_logo']))
    $logo_url = BASE_URL.'uploads/'.htmlspecialchars($settings['school_logo']);

// Determine watermark image source
$watermark_url = $bg_url ?: $logo_url;

/* ── Filters & Search ── */
$filter_class   = (int)($_GET['class_id'] ?? 0);
$filter_student = (int)($_GET['student_id'] ?? 0);
$search_term    = trim($_GET['search'] ?? '');
$card_theme     = $_GET['theme'] ?? 'emerald';

// Classes (Naturally sorted)
$classes_arr = get_all_classes($conn);


// All Students Dropdown
$students_dropdown_q = $conn->query("SELECT id, name, admission_no FROM students WHERE status='Active' ORDER BY name");
$all_students_arr = [];
if ($students_dropdown_q) while($s = $students_dropdown_q->fetch_assoc()) $all_students_arr[] = $s;

// Query students for ID cards
$where = ["s.status='Active'"];
if ($filter_student > 0) {
    $where[] = "s.id = $filter_student";
} elseif ($filter_class > 0) {
    $where[] = "s.class_id = $filter_class";
}
if (!empty($search_term)) {
    $st_esc = $conn->real_escape_string($search_term);
    $where[] = "(s.name LIKE '%$st_esc%' OR s.admission_no LIKE '%$st_esc%' OR s.roll_no LIKE '%$st_esc%' OR s.father_name LIKE '%$st_esc%')";
}

$students = null;
if ($filter_student > 0 || $filter_class > 0 || !empty($search_term)) {
    $w_sql = implode(' AND ', $where);
    $students = $conn->query("
        SELECT s.*, c.name AS class_name, c.section
        FROM students s LEFT JOIN classes c ON s.class_id=c.id
        WHERE $w_sql
        ORDER BY s.roll_no+0, s.name ASC
    ");
}

/* ── Barcode helper ── */
function genBars(string $seed, int $n = 48): array {
    mt_srand(crc32($seed));
    $b = [];
    for ($i = 0; $i < $n; $i++)
        $b[] = ['h' => mt_rand(10, 22), 'w' => ($i % 4 === 0 ? 2 : 1)];
    mt_srand();
    return $b;
}

// Theme Gradient Map
$theme_gradients = [
    'emerald' => 'linear-gradient(135deg, #059669, #0284c7)',
    'royal'   => 'linear-gradient(135deg, #1d4ed8, #4f46e5)',
    'indigo'  => 'linear-gradient(135deg, #4338ca, #6d28d9)',
    'crimson' => 'linear-gradient(135deg, #9f1239, #be123c)',
    'dark'    => 'linear-gradient(135deg, #0f172a, #334155)',
];
$header_bg = $theme_gradients[$card_theme] ?? $theme_gradients['emerald'];
?>
<style>
/* ═══ CONTROLS ═══ */
.ctrl-wrap{display:flex;flex-wrap:wrap;gap:15px;margin-bottom:24px}
.ctrl-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:18px 22px;flex:1;min-width:240px}
.ctrl-card h5{font-size:.78rem;text-transform:uppercase;letter-spacing:.8px;color:var(--accent-light);margin:0 0 12px;font-weight:700;display:flex;align-items:center;gap:6px}
.ctrl-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.ctrl-row label{font-size:.72rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;display:block;margin-bottom:4px}
.ctrl-row select,
.ctrl-row input[type=text],
.ctrl-row input[type=date]{background:var(--bg-secondary);border:1px solid var(--border);color:var(--text-primary);border-radius:var(--radius-xs);padding:8px 12px;font-size:.82rem;outline:none;transition:all var(--transition)}
.ctrl-row select:focus,.ctrl-row input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.ctrl-row input[type=file]{background:var(--bg-secondary);border:1px solid var(--border);color:var(--text-muted);border-radius:var(--radius-xs);padding:6px 10px;font-size:.78rem}

.btn-action{background:var(--accent);color:#fff;border:none;padding:8px 18px;border-radius:var(--radius-xs);font-weight:700;cursor:pointer;font-size:.82rem;transition:all var(--transition);display:inline-flex;align-items:center;gap:6px}
.btn-action:hover{opacity:.9;transform:translateY(-1px)}
.btn-print-all{background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;padding:10px 24px;border-radius:var(--radius-xs);font-weight:700;cursor:pointer;font-size:.88rem;box-shadow:0 4px 15px rgba(16,185,129,.25)}
.btn-print-all:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(16,185,129,.35)}
.bg-prev{width:72px;height:46px;object-fit:cover;border-radius:6px;border:2px solid var(--accent)}
.n-badge{background:var(--accent-glow);border:1px solid var(--border);color:var(--accent-light);border-radius:20px;padding:6px 16px;font-size:.78rem;font-weight:700}

/* ═══ CARD GRID ═══ */
.idc-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(350px, 1fr));
    gap:24px;
    margin-top:10px;
}

/* ═══ THE PROFESSIONAL ID CARD (ISO CR80 Landscape Format) ═══ */
.idc-wrapper{
    position:relative;
}
.idc-action-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:8px;
    padding:0 4px;
}
.idc-action-bar span{
    font-size:.75rem;
    font-weight:600;
    color:var(--text-muted);
}

.idc{
    width:100%;
    aspect-ratio:85.6/54;
    border-radius:12px;
    overflow:hidden;
    font-family:'Inter',system-ui,-apple-system,sans-serif;
    display:flex;
    flex-direction:column;
    box-shadow:0 10px 30px rgba(0,0,0,.15);
    position:relative;
    background:#ffffff;
    border:1px solid #e2e8f0;
    transition:transform .2s ease, box-shadow .2s ease;
}
.idc:hover{
    transform:translateY(-2px);
    box-shadow:0 14px 35px rgba(0,0,0,.22);
}

/* Watermark Background Overlay */
<?php if($bg_mode === 'cover'): ?>
.idc-watermark{
    position:absolute;inset:0;
    width:100%;height:100%;
    background-size:cover;background-position:center;background-repeat:no-repeat;
    opacity:<?= (float)$bg_opacity ?>;
    z-index:0;pointer-events:none;
}
<?php else: ?>
.idc-watermark{
    position:absolute;
    top:54%;left:58%;
    transform:translate(-50%,-50%);
    width:55%;height:55%;
    background-size:contain;background-position:center;background-repeat:no-repeat;
    opacity:<?= (float)$bg_opacity ?>;
    z-index:0;pointer-events:none;
}
<?php endif; ?>

/* ── HEADER BAR ── */
.idc-header{
    background: <?= $header_bg ?>;
    display:flex;align-items:center;
    padding:8px 12px;
    height:29%;
    gap:10px;
    position:relative;z-index:1;
    flex-shrink:0;
}
.idc-h-logo{
    width:36px;height:36px;border-radius:50%;
    background:#ffffff;
    border:2px solid rgba(255,255,255,.9);
    overflow:hidden;display:flex;align-items:center;justify-content:center;
    flex-shrink:0;
    box-shadow:0 2px 6px rgba(0,0,0,.2);
}
.idc-h-logo img{width:100%;height:100%;object-fit:contain}
.idc-h-logo-txt{font-size:16px;font-weight:900;color:#0f172a}
.idc-h-school{flex:1;overflow:hidden}
.idc-h-sname{font-size:.76rem;font-weight:800;color:#ffffff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-transform:uppercase;letter-spacing:.5px;line-height:1.2}
.idc-h-ssub{font-size:.5rem;color:rgba(255,255,255,.85);text-transform:uppercase;letter-spacing:.8px;margin-top:1px;font-weight:700}
.idc-h-type{
    background:rgba(255,255,255,.25);
    border:1px solid rgba(255,255,255,.5);
    color:#ffffff;font-size:.48rem;font-weight:800;
    padding:3px 8px;border-radius:20px;
    text-transform:uppercase;letter-spacing:.8px;white-space:nowrap;
    flex-shrink:0;
    backdrop-filter:blur(4px);
}

/* ── BODY ── */
.idc-body{
    flex:1;display:flex;
    position:relative;z-index:1;
    overflow:hidden;
    background:transparent;
}

/* Photo Column */
.idc-photo-col{
    width:32%;
    background:rgba(248,250,252,0.85);
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    border-right:1px solid #e2e8f0;
    padding:6px;
    flex-shrink:0;
}
.idc-photo-box{
    width:62px;height:74px;
    border-radius:8px;overflow:hidden;
    background:#cbd5e1;
    border:2px solid #ffffff;
    box-shadow:0 3px 10px rgba(0,0,0,.15);
    display:flex;align-items:center;justify-content:center;
}
.idc-photo-box img{width:100%;height:100%;object-fit:cover}
.idc-photo-init{font-size:26px;font-weight:800;color:#64748b;line-height:1}
.idc-role-tag{
    margin-top:4px;
    font-size:.42rem;
    font-weight:800;
    color:#0f172a;
    background:#e2e8f0;
    padding:1px 6px;
    border-radius:4px;
    text-transform:uppercase;
    letter-spacing:.6px;
}

/* Info Column */
.idc-info-col{
    flex:1;padding:8px 12px 6px;
    display:flex;flex-direction:column;justify-content:center;
    gap:3px;overflow:hidden;
    background:transparent;
}
.idc-name{font-size:.86rem;font-weight:800;color:#0f172a;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2}
.idc-field{display:flex;align-items:center;gap:6px;font-size:.56rem;line-height:1.3;white-space:nowrap;overflow:hidden}
.idc-field-lbl{color:#64748b;font-weight:700;min-width:54px;flex-shrink:0;text-transform:uppercase;font-size:.48rem;letter-spacing:.3px}
.idc-field-val{color:#1e293b;font-weight:700;overflow:hidden;text-overflow:ellipsis}
.idc-class-badge{background:#f1f5f9;border:1px solid #cbd5e1;padding:1px 6px;border-radius:4px;color:#0f172a;font-weight:800}

/* ── FOOTER BAR ── */
.idc-footer{
    background:rgba(248,250,252,0.9);
    border-top:1px solid #e2e8f0;
    display:flex;align-items:center;justify-content:space-between;
    padding:4px 12px;
    height:21%;
    gap:10px;
    position:relative;z-index:1;
    flex-shrink:0;
}
/* Barcode */
.idc-barcode-wrap{display:flex;flex-direction:column;align-items:flex-start;justify-content:center}
.idc-barcode{display:flex;align-items:flex-end;gap:1px;height:16px}
.idc-barcode span{display:inline-block;background:#1e293b;border-radius:.5px;flex-shrink:0}
.idc-barcode-num{font-size:.44rem;font-weight:800;color:#475569;letter-spacing:1px;margin-top:1px}

.idc-center-branding{font-size:.42rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.5px;text-align:center}

/* Valid date & Sign */
.idc-valid{text-align:right;white-space:nowrap;flex-shrink:0}
.idc-valid small{display:block;font-size:.42rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.idc-valid strong{display:block;font-size:.62rem;font-weight:900;color:#dc2626;line-height:1.1}

/* ═══ PRINT OPTIMIZATION (Standard ISO A4 8 Cards/Sheet Grid) ═══ */
@media print{
    .no-print, .top-header, .sidebar, #showMenuBtn, .ctrl-wrap, .page-header, .idc-action-bar { display:none!important; }
    *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
    body, html, .main-wrapper, .page-content { margin:0!important; padding:0!important; background:#fff!important; }
    
    @page { size: A4 portrait; margin: 8mm 6mm; }
    
    .idc-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 85.6mm) !important;
        gap: 5mm 6mm !important;
        justify-content: center !important;
        align-content: start !important;
        width: 100% !important;
    }
    .idc-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
    .idc {
        width: 85.6mm !important;
        height: 54mm !important;
        box-shadow: none !important;
        border: 1px dashed #cbd5e1 !important;
        border-radius: 3mm !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
}
</style>

<!-- PAGE HEADER -->
<div class="page-header no-print">
    <div>
        <h1><i class="fa-solid fa-id-card"></i> Student ID Card Generator</h1>
        <p>Professional ISO CR80 Student ID Cards with Official Watermark</p>
    </div>
    <?php if($students && $students->num_rows > 0): ?>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <span class="n-badge"><i class="fa-solid fa-users"></i>&nbsp; <?= $students->num_rows ?> Cards Loaded</span>
        <button class="btn-print-all" onclick="window.print()"><i class="fa-solid fa-print"></i>&nbsp; Print All Cards</button>
    </div>
    <?php endif; ?>
</div>

<!-- CONTROLS & FILTERS -->
<div class="ctrl-wrap no-print">

    <!-- Class Filter -->
    <div class="ctrl-card">
        <h5><i class="fa-solid fa-school"></i>&nbsp; Filter by Class</h5>
        <form method="GET" class="ctrl-row">
            <input type="hidden" name="theme" value="<?= htmlspecialchars($card_theme) ?>">
            <div>
                <label>Class</label>
                <select name="class_id" onchange="this.form.submit()">
                    <option value="">-- Choose Class --</option>
                    <?php foreach($classes_arr as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filter_class==$c['id']?'selected':'' ?>>
                        Class <?= htmlspecialchars($c['name'].' '.$c['section']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><button type="submit" class="btn-action"><i class="fa-solid fa-filter"></i> Filter</button></div>
        </form>
    </div>

    <!-- Search Student -->
    <div class="ctrl-card">
        <h5><i class="fa-solid fa-magnifying-glass"></i>&nbsp; Search Student</h5>
        <form method="GET" class="ctrl-row">
            <input type="hidden" name="theme" value="<?= htmlspecialchars($card_theme) ?>">
            <div>
                <label>Name or Reg No.</label>
                <input type="text" name="search" placeholder="Type name or reg no..." value="<?= htmlspecialchars($search_term) ?>">
            </div>
            <div><button type="submit" class="btn-action"><i class="fa-solid fa-search"></i> Search</button></div>
        </form>
    </div>

    <!-- Direct Student Picker -->
    <div class="ctrl-card">
        <h5><i class="fa-solid fa-user"></i>&nbsp; Single Student</h5>
        <form method="GET" class="ctrl-row">
            <input type="hidden" name="theme" value="<?= htmlspecialchars($card_theme) ?>">
            <div>
                <label>Select Student</label>
                <select name="student_id" onchange="this.form.submit()">
                    <option value="">-- Choose Student --</option>
                    <?php foreach($all_students_arr as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= $filter_student==$st['id']?'selected':'' ?>>
                        <?= htmlspecialchars($st['admission_no'].' — '.$st['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><button type="submit" class="btn-action"><i class="fa-solid fa-check"></i> Load</button></div>
        </form>
    </div>

    <!-- Theme & Validity -->
    <div class="ctrl-card">
        <h5><i class="fa-solid fa-palette"></i>&nbsp; Theme &amp; Validity</h5>
        <form method="GET" class="ctrl-row" style="margin-bottom: 8px;">
            <?php if($filter_class): ?><input type="hidden" name="class_id" value="<?= $filter_class ?>"><?php endif; ?>
            <?php if($filter_student): ?><input type="hidden" name="student_id" value="<?= $filter_student ?>"><?php endif; ?>
            <?php if($search_term): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>"><?php endif; ?>
            <div>
                <label>Theme</label>
                <select name="theme" onchange="this.form.submit()">
                    <option value="emerald" <?= $card_theme==='emerald'?'selected':'' ?>>Emerald Teal</option>
                    <option value="royal" <?= $card_theme==='royal'?'selected':'' ?>>Royal Blue</option>
                    <option value="indigo" <?= $card_theme==='indigo'?'selected':'' ?>>Corporate Indigo</option>
                    <option value="crimson" <?= $card_theme==='crimson'?'selected':'' ?>>Deep Crimson</option>
                    <option value="dark" <?= $card_theme==='dark'?'selected':'' ?>>Midnight Dark</option>
                </select>
            </div>
        </form>

        <form method="POST" class="ctrl-row">
            <div>
                <label>Valid Upto</label>
                <input type="date" name="valid_until" value="<?= htmlspecialchars($valid_until) ?>">
            </div>
            <div><button type="submit" class="btn-action"><i class="fa-solid fa-save"></i> Save</button></div>
        </form>
    </div>

    <!-- Background Watermark Settings -->
    <div class="ctrl-card" style="flex: 2; min-width: 320px;">
        <h5><i class="fa-solid fa-stamp"></i>&nbsp; Watermark Settings</h5>
        <form method="POST" enctype="multipart/form-data" class="ctrl-row">
            <?php if($bg_url): ?>
            <img src="<?= $bg_url ?>" class="bg-prev" alt="Watermark Image">
            <?php endif; ?>
            <div>
                <label><?= $bg_url ? 'Change Watermark' : 'Upload Image' ?> (PNG/JPG)</label>
                <input type="file" name="card_bg" accept="image/jpeg,image/png,image/webp">
            </div>
            <div>
                <label>Mode</label>
                <select name="bg_mode">
                    <option value="center" <?= $bg_mode==='center'?'selected':'' ?>>Centered Watermark</option>
                    <option value="cover" <?= $bg_mode==='cover'?'selected':'' ?>>Full Card Background</option>
                </select>
            </div>
            <div>
                <label>Opacity</label>
                <select name="bg_opacity">
                    <option value="0.10" <?= $bg_opacity==='0.10'?'selected':'' ?>>10% (Light)</option>
                    <option value="0.15" <?= $bg_opacity==='0.15'?'selected':'' ?>>15% (Standard)</option>
                    <option value="0.25" <?= $bg_opacity==='0.25'?'selected':'' ?>>25% (Medium)</option>
                    <option value="0.40" <?= $bg_opacity==='0.40'?'selected':'' ?>>40% (Strong)</option>
                </select>
            </div>
            <div><button type="submit" class="btn-action"><i class="fa-solid fa-upload"></i> Apply</button></div>
            <?php if($bg_url): ?>
            <div>
                <button type="submit" name="remove_bg" value="1" class="btn-action" style="background:#ef4444;" onclick="return confirm('Remove custom watermark image?')">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

</div>

<!-- ID CARDS GRID -->
<?php if($students && $students->num_rows > 0): ?>
<div class="idc-grid">
<?php while($s = $students->fetch_assoc()):
    $photo_url = (!empty($s['photo']) && file_exists(UPLOAD_PATH.$s['photo']))
                 ? BASE_URL.'uploads/'.htmlspecialchars($s['photo']) : '';
    $valid_fmt = $valid_until ? date('M Y', strtotime($valid_until)) : '—';
    $bars      = genBars($s['admission_no'].$s['name']);
    $initials  = strtoupper(substr($s['name'],0,1));
?>
<div class="idc-wrapper">
    <div class="idc-action-bar no-print">
        <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($s['name']) ?></span>
        <button onclick="printSingleCard(this)" class="btn btn-xs btn-primary"><i class="fa-solid fa-print"></i> Print This Card</button>
    </div>

    <div class="idc">
        <?php if($watermark_url): ?>
        <div class="idc-watermark" style="background-image:url('<?= addslashes($watermark_url) ?>')"></div>
        <?php endif; ?>

        <!-- HEADER -->
        <div class="idc-header">
            <div class="idc-h-logo">
                <?php if($logo_url): ?>
                    <img src="<?= $logo_url ?>" alt="Logo">
                <?php else: ?>
                    <span class="idc-h-logo-txt"><?= strtoupper(substr($school_name,0,1)) ?></span>
                <?php endif; ?>
            </div>
            <div class="idc-h-school">
                <div class="idc-h-sname"><?= htmlspecialchars($school_name) ?></div>
                <div class="idc-h-ssub">ACADEMIC STUDENT IDENTITY CARD</div>
            </div>
            <div class="idc-h-type">STUDENT</div>
        </div>

        <!-- BODY -->
        <div class="idc-body">
            <!-- Photo Column -->
            <div class="idc-photo-col">
                <div class="idc-photo-box">
                    <?php if($photo_url): ?>
                        <img src="<?= $photo_url ?>" alt="Photo">
                    <?php else: ?>
                        <span class="idc-photo-init"><?= $initials ?></span>
                    <?php endif; ?>
                </div>
                <span class="idc-role-tag">STUDENT</span>
            </div>

            <!-- Info Column -->
            <div class="idc-info-col">
                <div class="idc-name"><?= htmlspecialchars($s['name']) ?></div>
                <div class="idc-field">
                    <span class="idc-field-lbl">Father</span>
                    <span class="idc-field-val"><?= htmlspecialchars($s['father_name']??'—') ?></span>
                </div>
                <div class="idc-field">
                    <span class="idc-field-lbl">Class</span>
                    <span class="idc-field-val idc-class-badge"><?= htmlspecialchars(($s['class_name']??'—').' '.($s['section']??'')) ?></span>
                </div>
                <div class="idc-field">
                    <span class="idc-field-lbl">Roll No</span>
                    <span class="idc-field-val"><?= htmlspecialchars($s['roll_no']??'—') ?></span>
                </div>
                <div class="idc-field">
                    <span class="idc-field-lbl">Reg No</span>
                    <span class="idc-field-val"><?= htmlspecialchars($s['admission_no']??'—') ?></span>
                </div>
                <?php if(!empty($s['father_phone']) || !empty($s['phone'])): ?>
                <div class="idc-field">
                    <span class="idc-field-lbl">Phone</span>
                    <span class="idc-field-val"><?= htmlspecialchars($s['father_phone'] ?: $s['phone']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="idc-footer">
            <div class="idc-barcode-wrap">
                <div class="idc-barcode">
                    <?php foreach($bars as $bar): ?>
                    <span style="height:<?= $bar['h'] ?>px;width:<?= $bar['w'] ?>px"></span>
                    <?php endforeach; ?>
                </div>
                <div class="idc-barcode-num"><?= htmlspecialchars($s['admission_no']) ?></div>
            </div>
            <div class="idc-center-branding">Generated by SIAC Technologies</div>
            <div class="idc-valid">
                <small>Valid Until</small>
                <strong><?= htmlspecialchars($valid_fmt) ?></strong>
            </div>
        </div>
    </div>
</div>
<?php endwhile; ?>
</div>

<?php elseif($filter_class > 0 || $filter_student > 0 || !empty($search_term)): ?>
<div class="empty-state no-print">
    <div class="icon"><i class="fa-solid fa-user-xmark"></i></div>
    <h3>No active students found matching your filter</h3>
</div>
<?php else: ?>
<div class="empty-state no-print">
    <div class="icon"><i class="fa-solid fa-id-card"></i></div>
    <h3>Select a Class, Student, or Search to generate ID cards</h3>
    <p>Professional ISO CR80 standard format · 8 cards print automatically per A4 page.</p>
</div>
<?php endif; ?>

<script>
function printSingleCard(btn) {
    var wrapper = btn.closest('.idc-wrapper');
    var allWrappers = document.querySelectorAll('.idc-wrapper');
    
    // Hide all other wrappers during print
    allWrappers.forEach(function(w) {
        if (w !== wrapper) {
            w.classList.add('no-print');
        }
    });
    
    window.print();
    
    // Restore wrappers
    allWrappers.forEach(function(w) {
        w.classList.remove('no-print');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
