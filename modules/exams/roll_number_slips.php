<?php
/**
 * SIAX SMSS — Roll-Number Slips Management
 * 1. Roll Number Slip List (Default View)
 * 2. Update Roll-Number Slip / Schedule Matrix (action=show)
 * 3. Print Ready Roll Number Slips (action=print_slips)
 */
$page_title  = 'Roll Number Slips';
$active_page = 'roll_number_slips';

// Handle AJAX update subject date and time before header output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject_schedule_time'])) {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_role('admin','principal','teacher');

    $sched_id  = (int)($_POST['sched_id'] ?? 0);
    $class_id  = (int)($_POST['class_id'] ?? 0);
    $subject   = trim($_POST['subject'] ?? '');
    $exam_date = trim($_POST['exam_date'] ?? '');
    $exam_time = trim($_POST['exam_time'] ?? '');

    if ($sched_id > 0 && $class_id > 0 && $subject !== '') {
        $subj_esc = $conn->real_escape_string($subject);
        $date_esc = $conn->real_escape_string($exam_date);
        $time_esc = $conn->real_escape_string($exam_time);

        $conn->query("
            INSERT INTO exam_subject_dates (schedule_id, class_id, subject, exam_date, exam_time)
            VALUES ($sched_id, $class_id, '$subj_esc', '$date_esc', '$time_esc')
            ON DUPLICATE KEY UPDATE exam_date = '$date_esc', exam_time = '$time_esc'
        ");

        echo json_encode(['status' => 'success', 'date' => $exam_date, 'time' => $exam_time]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
    }
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','teacher');

$action = $_GET['action'] ?? 'list';
$sched_id = (int)($_GET['sched_id'] ?? 0);
$sel_class = (int)($_GET['class_id'] ?? 0);

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_slip'])) {
        $type_id = (int)($_POST['exam_type_id'] ?? 0);
        $title = $conn->real_escape_string($_POST['title']);
        $instructions = $conn->real_escape_string($_POST['instructions'] ?? '');
        $active_session = $settings['session_year'] ?? '2025-2026';
        if ($title != '') {
            $conn->query("INSERT INTO exam_schedules (exam_type_id, title, instructions, session_year) VALUES ($type_id, '$title', '$instructions', '$active_session')");
        }
        header("Location: roll_number_slips.php");
        exit;
    }
    if (isset($_POST['update_slip'])) {
        $id = (int)$_POST['id'];
        $title = $conn->real_escape_string($_POST['title']);
        $instructions = $conn->real_escape_string($_POST['instructions'] ?? '');
        $type_id = (int)($_POST['exam_type_id'] ?? 0);
        if ($id > 0 && $title != '') {
            $conn->query("UPDATE exam_schedules SET title='$title', instructions='$instructions', exam_type_id=$type_id WHERE id=$id");
        }
        header("Location: roll_number_slips.php");
        exit;
    }
}

if (isset($_GET['del'])) {
    $del = (int)$_GET['del'];
    $conn->query("DELETE FROM exam_schedules WHERE id=$del");
    $conn->query("DELETE FROM exam_subject_dates WHERE schedule_id=$del");
    header("Location: roll_number_slips.php");
    exit;
}

// Fetch all exam schedules & types
$active_session = $settings['session_year'] ?? '2025-2026';
$slips_q = $conn->query("SELECT es.*, et.title as type_title FROM exam_schedules es LEFT JOIN exam_types et ON es.exam_type_id = et.id WHERE es.session_year='$active_session' ORDER BY es.id DESC");
$slips = [];
if ($slips_q) while($s = $slips_q->fetch_assoc()) $slips[] = $s;

$types_q = $conn->query("SELECT * FROM exam_types ORDER BY id DESC");
$types = [];
if ($types_q) while($t = $types_q->fetch_assoc()) $types[] = $t;


// ══════════════════════════════════════════════════════════
// VIEW 1: ROLL NUMBER SLIP LIST (DEFAULT VIEW)
// ══════════════════════════════════════════════════════════
if ($action === 'list'):
?>
<style>
.list-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.list-page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }

.btn-create-slip {
    background: #e0f2fe;
    color: #0284c7;
    border: 1px solid #7dd3fc;
    padding: 9px 18px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-create-slip:hover {
    background: #bae6fd;
    color: #0369a1;
    border-color: #38bdf8;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(2,132,199,0.15);
}

.siax-list-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    overflow: hidden;
}
.siax-list-header {
    padding: 10px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    color: #64748b;
}

.slip-list-table {
    width: 100%;
    border-collapse: collapse;
}
.slip-list-table th, .slip-list-table td {
    border: 1px solid #e2e8f0;
    padding: 14px 18px;
    text-align: left;
    vertical-align: top;
}
.slip-list-table th {
    background: #f8fafc;
    font-weight: 700;
    color: #475569;
    font-size: 0.85rem;
    text-transform: capitalize;
}
.slip-list-table td {
    font-size: 0.88rem;
    color: #334155;
}
.slip-list-table tr:hover td {
    background: #fafbfc;
}

.slip-title-cell {
    font-weight: 700;
    color: #1e293b;
    font-size: 0.95rem;
}
.slip-category-badge {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 4px;
    margin-top: 4px;
    border: 1px solid #e2e8f0;
}
.slip-instructions-cell {
    white-space: pre-line;
    line-height: 1.6;
    color: #475569;
    font-size: 0.85rem;
}

.action-links {
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}
.action-links a {
    color: #0284c7;
    text-decoration: none;
    transition: color 0.15s;
}
.action-links a:hover {
    color: #0369a1;
    text-decoration: underline;
}
.action-links a.del-link {
    color: #ef4444;
}
.action-links a.del-link:hover {
    color: #dc2626;
}
.action-links .sep {
    color: #cbd5e1;
    margin: 0 6px;
}

/* Modal Overlay */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; z-index: 1050; }
.modal-overlay.active { display: flex; }
.modal-card { background: #fff; width: 520px; max-width: 92%; border-radius: 10px; box-shadow: 0 20px 35px rgba(0,0,0,0.2); overflow: hidden; animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalPop { 0% { transform: scale(0.95); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.modal-hdr { padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.modal-hdr h3 { margin: 0; font-size: 1.1rem; color: #1e293b; font-weight: 700; }
.modal-close-btn { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b; transition: color 0.2s; }
.modal-close-btn:hover { color: #ef4444; }
.modal-body { padding: 22px; }
.form-grp { margin-bottom: 16px; }
.form-grp label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.85rem; color: #334155; }
.form-grp input, .form-grp textarea, .form-grp select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 0.9rem; color: #1e293b; box-sizing: border-box; }
.form-grp input:focus, .form-grp textarea:focus, .form-grp select:focus { outline: none; border-color: #0284c7; box-shadow: 0 0 0 3px rgba(2,132,199,0.15); }
.modal-ftr { padding: 14px 22px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc; }
.btn-save-primary { background: #0284c7; color: #fff; border: none; padding: 9px 22px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; transition: background 0.2s; }
.btn-save-primary:hover { background: #0369a1; }
.btn-cancel-modal { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 9px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
.btn-cancel-modal:hover { background: #e2e8f0; }
</style>

<div class="list-page-header">
    <h1 class="list-page-title">Roll Number Slip List</h1>
    <button class="btn-create-slip" onclick="openSlipModal('add')">
        <i class="fa-solid fa-plus"></i> Create New Roll Number Slip
    </button>
</div>

<div class="siax-list-card">
    <div class="siax-list-header">
        <i class="fa-solid fa-table-cells"></i>
    </div>
    <table class="slip-list-table">
        <thead>
            <tr>
                <th style="width: 22%">Title</th>
                <th style="width: 58%">Instructions</th>
                <th style="width: 20%">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($slips)): ?>
            <tr>
                <td colspan="3" style="text-align:center; padding: 40px; color: #94a3b8;">
                    <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                    No roll number slips created yet. Click "Create New Roll Number Slip" above to add one.
                </td>
            </tr>
            <?php else: ?>
            <?php foreach($slips as $s): ?>
            <tr>
                <td>
                    <div class="slip-title-cell"><?= htmlspecialchars($s['title']) ?></div>
                    <?php if(!empty($s['type_title'])): ?>
                    <span class="slip-category-badge"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($s['type_title']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="slip-instructions-cell"><?= htmlspecialchars($s['instructions'] ?? '') ?></td>
                <td class="action-links">
                    <a href="#" onclick="openSlipModal('edit', <?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['title'])) ?>', '<?= htmlspecialchars(addslashes($s['instructions'] ?? '')) ?>', <?= (int)$s['exam_type_id'] ?>); return false;">Update</a>
                    <span class="sep">|</span>
                    <a href="?del=<?= $s['id'] ?>" class="del-link" onclick="return confirm('Are you sure you want to delete this roll number slip?');">Delete</a>
                    <span class="sep">|</span>
                    <a href="?action=show&sched_id=<?= $s['id'] ?>">Show Slips</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Create / Edit Roll Number Slip -->
<div class="modal-overlay" id="slipModal">
    <div class="modal-card">
        <div class="modal-hdr">
            <h3 id="slipModalTitle">Create New Roll Number Slip</h3>
            <button class="modal-close-btn" onclick="closeSlipModal()"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" id="form_slip_id">
            <input type="hidden" name="add_slip" id="form_slip_action" value="1">
            <div class="modal-body">
                <div class="form-grp">
                    <label>Exam Type (Category)</label>
                    <select name="exam_type_id" id="form_exam_type_id" required>
                        <option value="">Select Exam Type</option>
                        <?php foreach($types as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grp">
                    <label>Name or Title</label>
                    <input type="text" name="title" id="form_slip_title" placeholder="e.g. Mid Term Exam 2026" required>
                </div>
                <div class="form-grp">
                    <label>Instructions for Students</label>
                    <textarea name="instructions" id="form_slip_instructions" rows="5" placeholder="1. All students should reach the examination center half an hour before..."></textarea>
                </div>
            </div>
            <div class="modal-ftr">
                <button type="button" class="btn-cancel-modal" onclick="closeSlipModal()">Cancel</button>
                <button type="submit" class="btn-save-primary">Save Slip</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSlipModal(mode, id=0, title='', instructions='', typeId=0) {
    document.getElementById('slipModal').classList.add('active');
    if (mode === 'add') {
        document.getElementById('slipModalTitle').innerText = 'Create New Roll Number Slip';
        document.getElementById('form_slip_action').name = 'add_slip';
        document.getElementById('form_slip_id').value = '';
        document.getElementById('form_slip_title').value = '';
        document.getElementById('form_slip_instructions').value = '';
        document.getElementById('form_exam_type_id').value = '';
    } else {
        document.getElementById('slipModalTitle').innerText = 'Update Roll Number Slip';
        document.getElementById('form_slip_action').name = 'update_slip';
        document.getElementById('form_slip_id').value = id;
        document.getElementById('form_slip_title').value = title;
        document.getElementById('form_slip_instructions').value = instructions;
        document.getElementById('form_exam_type_id').value = typeId;
    }
}
function closeSlipModal() {
    document.getElementById('slipModal').classList.remove('active');
}
window.addEventListener('click', function(e) {
    var sm = document.getElementById('slipModal');
    if (e.target === sm) closeSlipModal();
});
</script>

<?php
// ══════════════════════════════════════════════════════════
// VIEW 2: UPDATE ROLL-NUMBER SLIP / CLASSES & SECTIONS TABLE (action=show)
// ══════════════════════════════════════════════════════════
elseif ($action === 'show' && $sched_id > 0):
    $sched_info = null;
    foreach ($slips as $s) {
        if ((int)$s['id'] === $sched_id) {
            $sched_info = $s;
            break;
        }
    }
    if (!$sched_info) {
        echo "<div style='padding:30px;background:#fff;border-radius:8px;border:1px solid #e2e8f0;margin:20px 0;'><h3>Schedule not found.</h3><a href='roll_number_slips.php' class='btn btn-primary' style='display:inline-block;margin-top:10px;'>Back to List</a></div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }

    $classes_arr = get_all_classes($conn);

    // Fetch all dates and times for this schedule
    $all_dates_map = [];
    $dq = $conn->query("SELECT class_id, subject, exam_date, exam_time FROM exam_subject_dates WHERE schedule_id=$sched_id");
    if ($dq) {
        while($d=$dq->fetch_assoc()) {
            $cid = (int)$d['class_id'];
            $sub_k = strtolower(trim($d['subject']));
            $all_dates_map[$cid][$sub_k] = $d;
        }
    }
?>
<style>
/* Header & Toolbar */
.page-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 14px; }
.page-main-heading { font-size: 1.45rem; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px; }

.toolbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.schedule-select-wrap { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.schedule-select-wrap label { font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.schedule-select-wrap select { border: none; font-size: 0.9rem; font-weight: 600; color: #0284c7; outline: none; background: transparent; cursor: pointer; padding: 4px 0; }

.btn-header-action { background: #0284c7; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.2s; box-shadow: 0 2px 4px rgba(2,132,199,0.25); }
.btn-header-action:hover { background: #0369a1; transform: translateY(-1px); }
.btn-header-secondary { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.2s; }
.btn-header-secondary:hover { background: #f1f5f9; color: #0f172a; }

/* Main Classes & Sections Table Card */
.siax-table-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); overflow: hidden; margin-top: 10px; }
.siax-card-header { padding: 12px 18px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
.siax-card-header .title { font-size: 0.95rem; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 8px; }
.siax-card-header .icon-btn { color: #64748b; font-size: 1.1rem; }

.siax-roll-table { width: 100%; border-collapse: collapse; }
.siax-roll-table thead th { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 10px 16px; font-size: 0.8rem; font-weight: 700; color: #475569; text-align: left; text-transform: uppercase; letter-spacing: 0.5px; }
.siax-roll-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.siax-roll-table tbody tr:hover { background: #f8fafc; }
.siax-roll-table tbody tr:last-child { border-bottom: none; }

.siax-roll-table td { padding: 14px 16px; vertical-align: middle; }
.td-class { width: 14%; font-weight: 700; color: #1e293b; font-size: 0.95rem; }
.td-section { width: 10%; color: #64748b; font-weight: 600; font-size: 0.9rem; }
.td-subjects { width: 62%; }
.td-action { width: 14%; text-align: right; }

/* Subject Badges / Cards Grid (matching screenshot) */
.subject-badges-wrap { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }

.subject-badge-card {
    display: inline-flex;
    flex-direction: column;
    width: 125px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}
.subject-badge-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    border-color: #0284c7;
}

.badge-top-header {
    background: #0284c7;
    color: #ffffff;
    padding: 5px 8px;
    font-size: 11px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.theme-green .badge-top-header { background: #15803d; }
.theme-blue .badge-top-header { background: #0284c7; }

.badge-top-header .edit-pen { opacity: 0; transition: opacity 0.2s; font-size: 9px; }
.subject-badge-card:hover .badge-top-header .edit-pen { opacity: 1; }

.badge-bottom-info {
    padding: 6px 8px;
    background: #ffffff;
    text-align: center;
}
.badge-bottom-info .date-text {
    font-size: 11px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
    margin-bottom: 2px;
}
.badge-bottom-info .time-text {
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    line-height: 1.2;
}

/* "Show Slips" Pill Button (matching screenshot) */
.btn-show-slips {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #00a8ff;
    color: #ffffff !important;
    font-size: 11.5px;
    font-weight: 700;
    padding: 6px 18px;
    border-radius: 20px;
    text-decoration: none !important;
    box-shadow: 0 2px 6px rgba(0, 168, 255, 0.35);
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    white-space: nowrap;
}
.btn-show-slips:hover {
    background: #0097e6;
    box-shadow: 0 4px 12px rgba(0, 168, 255, 0.45);
    transform: translateY(-1px);
    color: #fff !important;
}

/* Modal Overlay */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; z-index: 1050; }
.modal-overlay.active { display: flex; }
.modal-card { background: #fff; width: 480px; max-width: 92%; border-radius: 10px; box-shadow: 0 20px 35px rgba(0,0,0,0.2); overflow: hidden; animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalPop { 0% { transform: scale(0.95); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.modal-hdr { padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.modal-hdr h3 { margin: 0; font-size: 1.1rem; color: #1e293b; font-weight: 700; }
.modal-close-btn { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b; transition: color 0.2s; }
.modal-close-btn:hover { color: #ef4444; }
.modal-body { padding: 22px; }
.form-grp { margin-bottom: 16px; }
.form-grp label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.85rem; color: #334155; }
.form-grp input, .form-grp textarea, .form-grp select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 0.9rem; color: #1e293b; box-sizing: border-box; }
.form-grp input:focus, .form-grp textarea:focus, .form-grp select:focus { outline: none; border-color: #0284c7; box-shadow: 0 0 0 3px rgba(2,132,199,0.15); }
.modal-ftr { padding: 14px 22px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc; }
.btn-save-primary { background: #0284c7; color: #fff; border: none; padding: 9px 22px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; transition: background 0.2s; }
.btn-save-primary:hover { background: #0369a1; }
.btn-cancel-modal { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 9px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
.btn-cancel-modal:hover { background: #e2e8f0; }

.quick-time-btns { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
.quick-time-btn { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 3px 8px; font-size: 10px; font-weight: 600; color: #475569; cursor: pointer; transition: all 0.15s; }
.quick-time-btn:hover { background: #e0f2fe; color: #0284c7; border-color: #7dd3fc; }
</style>

<!-- Top Bar -->
<div class="page-top-bar">
    <div>
        <h1 class="page-main-heading">Update Roll-Number Slip</h1>
        <p style="font-size:0.85rem; color:#64748b; margin:4px 0 0 0">
            Selected: <strong style="color:#0284c7"><?= htmlspecialchars($sched_info['title']) ?></strong> &bull; Configure dates, times, and generate slips
        </p>
    </div>
    <div class="toolbar-actions">
        <a href="roll_number_slips.php" class="btn-header-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Slip List
        </a>
        <div class="schedule-select-wrap">
            <label><i class="fa-solid fa-calendar-check" style="color:#0284c7"></i> Exam Slip:</label>
            <select onchange="window.location.href='roll_number_slips.php?action=show&sched_id='+this.value">
                <?php foreach($slips as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id']==$sched_id?'selected':'' ?>>
                    <?= htmlspecialchars($s['title']) ?> (<?= htmlspecialchars($s['type_title'] ?? 'Exam') ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn-header-action" onclick="openSlipModal('edit', <?= $sched_info['id'] ?>, '<?= htmlspecialchars(addslashes($sched_info['title'])) ?>', '<?= htmlspecialchars(addslashes($sched_info['instructions']??'')) ?>', <?= (int)$sched_info['exam_type_id'] ?>)">
            <i class="fa-solid fa-pen"></i> Edit Slip Title
        </button>
    </div>
</div>

<!-- Main Classes & Sections Table Card (Matching Screenshot) -->
<div class="siax-table-card">
    <div class="siax-card-header">
        <div class="title">
            <i class="fa-solid fa-table-cells" style="color:#0284c7"></i> Classes &amp; Sections
        </div>
        <div class="icon-btn">
            <i class="fa-solid fa-layer-group"></i>
        </div>
    </div>
    
    <table class="siax-roll-table">
        <thead>
            <tr>
                <th style="width: 14%">Class</th>
                <th style="width: 10%">Section</th>
                <th style="width: 62%">Subjects &amp; Schedule</th>
                <th style="width: 14%; text-align: right">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($classes_arr)): ?>
            <tr>
                <td colspan="4" style="text-align:center; padding: 40px; color: #94a3b8;">
                    <i class="fa-solid fa-school" style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.4;"></i>
                    No classes found. Please create classes first.
                </td>
            </tr>
            <?php else: ?>
            <?php 
            $row_idx = 0;
            foreach($classes_arr as $cls): 
                $row_idx++;
                // Alternate between blue and green like the screenshot
                $theme_class = ($row_idx % 2 === 1) ? 'theme-blue' : 'theme-green';
                
                // Get all subjects assigned to this class across all exams
                $cls_id = (int)$cls['id'];
                $subjs_q = $conn->query("SELECT * FROM class_subjects WHERE class_id = $cls_id GROUP BY subject ORDER BY subject");
                $cls_subjects = [];
                if($subjs_q) while($sb = $subjs_q->fetch_assoc()) $cls_subjects[] = $sb;
            ?>
            <tr>
                <td class="td-class"><?= htmlspecialchars($cls['name']) ?></td>
                <td class="td-section"><?= htmlspecialchars($cls['section'] ?? '') ?></td>
                <td class="td-subjects">
                    <?php if(empty($cls_subjects)): ?>
                        <span style="color:#94a3b8; font-size: 0.85rem; font-style: italic;">
                            No subjects added yet. <a href="exams.php" style="color:#0284c7; font-weight:600; text-decoration:none"><i class="fa-solid fa-plus-circle"></i> Add in Add Result</a>
                        </span>
                    <?php else: ?>
                        <div class="subject-badges-wrap <?= $theme_class ?>">
                            <?php foreach($cls_subjects as $sub): 
                                $sub_name = $sub['subject'];
                                $sub_k = strtolower(trim($sub_name));
                                $sched_data = $all_dates_map[$cls_id][$sub_k] ?? null;
                                $display_date = !empty($sched_data['exam_date']) ? $sched_data['exam_date'] : date('d/m/Y');
                                $display_time = !empty($sched_data['exam_time']) ? $sched_data['exam_time'] : '09:00 AM';
                                $badge_id = "badge_{$cls_id}_" . md5($sub_k);
                            ?>
                            <div class="subject-badge-card" id="<?= $badge_id ?>" 
                                 onclick="openTimeModal(<?= $sched_id ?>, <?= $cls_id ?>, '<?= htmlspecialchars(addslashes($sub_name)) ?>', '<?= htmlspecialchars(addslashes($display_date)) ?>', '<?= htmlspecialchars(addslashes($display_time)) ?>', '<?= $badge_id ?>')"
                                 title="Click to edit date &amp; time">
                                <div class="badge-top-header">
                                    <span><?= htmlspecialchars($sub_name) ?> ,</span>
                                    <i class="fa-solid fa-pen edit-pen"></i>
                                </div>
                                <div class="badge-bottom-info">
                                    <div class="date-text" id="dt_<?= $badge_id ?>"><?= htmlspecialchars($display_date) ?></div>
                                    <div class="time-text" id="tm_<?= $badge_id ?>"><?= htmlspecialchars($display_time) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="td-action">
                    <a href="?action=print_slips&sched_id=<?= $sched_id ?>&class_id=<?= $cls['id'] ?>" class="btn-show-slips">
                        Show Slips
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal 1: Edit Date & Time for Subject -->
<div class="modal-overlay" id="timeModal">
    <div class="modal-card">
        <div class="modal-hdr">
            <h3><i class="fa-solid fa-clock" style="color:#0284c7;margin-right:6px"></i>Set Exam Date &amp; Time</h3>
            <button class="modal-close-btn" onclick="closeTimeModal()"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grp">
                <label>Subject</label>
                <input type="text" id="modal_subject_name" readonly style="background:#f8fafc;font-weight:700;color:#1e293b">
            </div>
            <div class="form-grp">
                <label>Exam Date (e.g. 23 Aug 2026 or 14/07/2026)</label>
                <input type="text" id="modal_exam_date" placeholder="e.g. 23 Aug 2026">
                <div class="quick-time-btns">
                    <button type="button" class="quick-time-btn" onclick="document.getElementById('modal_exam_date').value = '<?= date('d M Y') ?>'">Today (<?= date('d M Y') ?>)</button>
                    <button type="button" class="quick-time-btn" onclick="document.getElementById('modal_exam_date').value = '<?= date('d/m/Y') ?>'"><?= date('d/m/Y') ?></button>
                </div>
            </div>
            <div class="form-grp">
                <label>Exam Time (e.g. 09:00 AM, 03 13 PM)</label>
                <input type="text" id="modal_exam_time" placeholder="e.g. 09:00 AM">
                <div class="quick-time-btns">
                    <button type="button" class="quick-time-btn" onclick="document.getElementById('modal_exam_time').value = '09:00 AM'">09:00 AM</button>
                    <button type="button" class="quick-time-btn" onclick="document.getElementById('modal_exam_time').value = '10:00 AM'">10:00 AM</button>
                    <button type="button" class="quick-time-btn" onclick="document.getElementById('modal_exam_time').value = '01:30 PM'">01:30 PM</button>
                    <button type="button" class="quick-time-btn" onclick="document.getElementById('modal_exam_time').value = '03:13 PM'">03:13 PM</button>
                </div>
            </div>
        </div>
        <div class="modal-ftr">
            <button type="button" class="btn-cancel-modal" onclick="closeTimeModal()">Cancel</button>
            <button type="button" class="btn-save-primary" id="saveTimeBtn" onclick="saveSubjectTime()">
                <i class="fa-solid fa-check"></i> Save Schedule
            </button>
        </div>
    </div>
</div>

<!-- Modal 2: Edit Roll Number Slip Title & Instructions -->
<div class="modal-overlay" id="slipModal">
    <div class="modal-card">
        <div class="modal-hdr">
            <h3 id="slipModalTitle">Update Roll Number Slip</h3>
            <button class="modal-close-btn" onclick="closeSlipModal()"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" id="form_slip_id" value="<?= $sched_info['id'] ?>">
            <input type="hidden" name="update_slip" id="form_slip_action" value="1">
            <div class="modal-body">
                <div class="form-grp">
                    <label>Exam Type (Category)</label>
                    <select name="exam_type_id" id="form_exam_type_id" required>
                        <option value="">Select Exam Type</option>
                        <?php foreach($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id']==$sched_info['exam_type_id']?'selected':'' ?>><?= htmlspecialchars($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grp">
                    <label>Name or Title</label>
                    <input type="text" name="title" id="form_slip_title" value="<?= htmlspecialchars($sched_info['title']) ?>" required>
                </div>
                <div class="form-grp">
                    <label>Instructions for Students</label>
                    <textarea name="instructions" id="form_slip_instructions" rows="5"><?= htmlspecialchars($sched_info['instructions']??'') ?></textarea>
                </div>
            </div>
            <div class="modal-ftr">
                <button type="button" class="btn-cancel-modal" onclick="closeSlipModal()">Cancel</button>
                <button type="submit" class="btn-save-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Time & Date Modal Handler
var _currentSchedId = 0;
var _currentClassId = 0;
var _currentSubject = '';
var _currentBadgeId = '';

function openTimeModal(schedId, classId, subject, currentDate, currentTime, badgeId) {
    _currentSchedId = schedId;
    _currentClassId = classId;
    _currentSubject = subject;
    _currentBadgeId = badgeId;

    document.getElementById('modal_subject_name').value = subject;
    document.getElementById('modal_exam_date').value = currentDate;
    document.getElementById('modal_exam_time').value = currentTime;
    document.getElementById('timeModal').classList.add('active');
}

function closeTimeModal() {
    document.getElementById('timeModal').classList.remove('active');
}

function saveSubjectTime() {
    var dateVal = document.getElementById('modal_exam_date').value.trim();
    var timeVal = document.getElementById('modal_exam_time').value.trim();
    var btn = document.getElementById('saveTimeBtn');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

    var fd = new FormData();
    fd.append('update_subject_schedule_time', '1');
    fd.append('sched_id', _currentSchedId);
    fd.append('class_id', _currentClassId);
    fd.append('subject', _currentSubject);
    fd.append('exam_date', dateVal);
    fd.append('exam_time', timeVal);

    fetch(window.location.href, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Save Schedule';
        if (data.status === 'success') {
            if (_currentBadgeId) {
                var dtEl = document.getElementById('dt_' + _currentBadgeId);
                var tmEl = document.getElementById('tm_' + _currentBadgeId);
                if (dtEl) dtEl.innerText = dateVal || '--';
                if (tmEl) tmEl.innerText = timeVal || '--';
            }
            closeTimeModal();
        } else {
            alert(data.message || 'Could not save date & time.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Save Schedule';
        alert('Network or server error.');
    });
}

// Slip Modal Handler
function openSlipModal(mode, id=0, title='', instructions='', typeId=0) {
    document.getElementById('slipModal').classList.add('active');
    document.getElementById('form_slip_id').value = id;
    document.getElementById('form_slip_title').value = title;
    document.getElementById('form_slip_instructions').value = instructions;
    document.getElementById('form_exam_type_id').value = typeId;
}

function closeSlipModal() {
    document.getElementById('slipModal').classList.remove('active');
}

window.addEventListener('click', function(e) {
    var tm = document.getElementById('timeModal');
    var sm = document.getElementById('slipModal');
    if (e.target === tm) closeTimeModal();
    if (e.target === sm) closeSlipModal();
});
</script>

<?php
// ══════════════════════════════════════════════════════════
// VIEW 3: PRINTABLE ROLL NUMBER SLIPS (action=print_slips)
// ══════════════════════════════════════════════════════════
elseif ($action === 'print_slips' && $sched_id > 0 && $sel_class > 0):
    $sched_info = null;
    foreach ($slips as $s) {
        if ((int)$s['id'] === $sched_id) {
            $sched_info = $s;
            break;
        }
    }
    $classes_arr = get_all_classes($conn);
    $class_info = null;
    foreach($classes_arr as $c){ if($c['id']==$sel_class){ $class_info=$c; break; } }

    $active_session = $settings['session_year'] ?? '2025-2026';
    $students = [];
    $sq = $conn->query("
        SELECT s.*, se.roll_no as enrollment_roll_no 
        FROM student_enrollments se 
        JOIN students s ON se.student_id = s.id 
        WHERE se.class_id=$sel_class AND se.session_year='$active_session' AND se.status='Active' AND s.status='Active' 
        ORDER BY (se.roll_no+0), s.name
    ");
    if ($sq) while($s=$sq->fetch_assoc()) $students[]=$s;

    // Subjects assigned to this class across all exams
    $subjects = [];
    $sq = $conn->query("SELECT * FROM class_subjects WHERE class_id=$sel_class GROUP BY subject ORDER BY subject");
    if ($sq) while($s=$sq->fetch_assoc()) $subjects[]=$s;

    // Date and time map for this schedule and class
    $dates_map = [];
    $dq = $conn->query("SELECT subject, exam_date, exam_time FROM exam_subject_dates WHERE schedule_id=$sched_id AND class_id=$sel_class");
    if ($dq) {
        while($d=$dq->fetch_assoc()) {
            $dates_map[strtolower(trim($d['subject']))] = $d;
        }
    }
?>
<style>
.slips-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px; margin-top: 8px; }
.slip { background: #ffffff; color: #1a1a2e; border: 1px solid #d1d5db; border-radius: 10px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; break-inside: avoid; page-break-inside: avoid; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
.slip-hdr { background: linear-gradient(135deg, #1e3a5f 0%, #2d5f8a 50%, #1e3a5f 100%); color: #fff; padding: 14px 20px; display: flex; align-items: center; gap: 14px; position: relative; }
.slip-hdr::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #f59e0b, #10b981, #6366f1); }
.slip-logo { width: 48px; height: 48px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 18px; color: #1e3a5f; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.2); overflow: hidden; }
.slip-logo img { width: 100%; height: 100%; object-fit: contain; }
.slip-school-name { font-size: 13px; font-weight: 800; text-transform: uppercase; line-height: 1.3; letter-spacing: 0.5px; text-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.slip-doc-title { font-size: 10px; opacity: 0.85; margin-top: 2px; font-weight: 500; letter-spacing: 1px; }
.slip-roll { text-align: right; font-size: 26px; font-weight: 900; letter-spacing: 1px; line-height: 1; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.slip-roll small { display: block; font-size: 8px; font-weight: 500; opacity: 0.75; letter-spacing: 1.5px; text-transform: uppercase; margin-top: 3px; }
.slip-body { padding: 16px 20px 12px; }
.slip-exam-name { font-size: 14px; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 1.5px; color: #1e3a5f; padding: 8px 0; margin-bottom: 14px; border-top: 2px solid #e5e7eb; border-bottom: 2px solid #e5e7eb; }
.slip-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; margin-bottom: 16px; }
.slip-info-grid .slip-field-full { grid-column: 1 / -1; }
.slip-field { display: flex; align-items: baseline; gap: 8px; }
.slip-field .lbl { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; flex-shrink: 0; }
.slip-field .val { flex: 1; font-size: 12px; font-weight: 600; color: #1a1a2e; border-bottom: 1.5px solid #d1d5db; padding-bottom: 2px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.slip-subj-tbl { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 4px; font-size: 11px; border: 1px solid #94a3b8; border-radius: 6px; overflow: hidden; }
.slip-subj-tbl th { padding: 8px 10px; background: linear-gradient(180deg, #f1f5f9, #e2e8f0); font-weight: 800; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #334155; border-bottom: 2px solid #94a3b8; }
.slip-subj-tbl th:not(:last-child), .slip-subj-tbl td:not(:last-child) { border-right: 1px solid #cbd5e1; }
.slip-subj-tbl td { padding: 7px 10px; text-align: center; color: #334155; font-weight: 500; border-bottom: 1px solid #e2e8f0; }
.slip-subj-tbl td:first-child { text-align: left; font-weight: 600; }
.slip-subj-tbl tbody tr:last-child td { border-bottom: none; }
.slip-subj-tbl tbody tr:nth-child(even) td { background: #f8fafc; }
.slip-inst { margin-top: 15px; font-size: 9px; color: #475569; padding: 10px; background: #f1f5f9; border-radius: 6px; border: 1px dashed #cbd5e1; white-space: pre-line; }
.slip-footer { display: flex; justify-content: space-between; align-items: flex-end; padding: 12px 20px 14px; margin-top: 8px; border-top: 1px solid #e2e8f0; }
.slip-sign { text-align: center; min-width: 100px; }
.slip-sign-line { border-top: 1.5px solid #1a1a2e; margin-top: 30px; padding-top: 5px; font-size: 10px; font-weight: 700; color: #334155; letter-spacing: 0.3px; }
.slip-branding { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-top: 1px solid #e2e8f0; padding: 8px 20px; text-align: center; font-size: 8px; color: #94a3b8; letter-spacing: 0.5px; display: flex; align-items: center; justify-content: center; gap: 6px; }
.slip-branding strong { color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
.slip-branding .brand-dot { width: 3px; height: 3px; background: #94a3b8; border-radius: 50%; display: inline-block; }

@media print {
    .no-print, .sidebar, .top-header, #showMenuBtn { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
    .page-content { padding: 0 !important; margin: 0 !important; }
    .main-container { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    .slips-grid { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .slip { width: 100% !important; max-width: 100% !important; margin: 0 !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; page-break-after: always !important; page-break-inside: avoid !important; display: flex !important; flex-direction: column !important; justify-content: flex-start !important; min-height: 100vh !important; }
    .slip-hdr { padding: 24px 40px !important; gap: 20px !important; }
    .slip-hdr::after { height: 4px !important; }
    .slip-logo { width: 60px !important; height: 60px !important; font-size: 24px !important; }
    .slip-school-name { font-size: 18px !important; letter-spacing: 1px !important; }
    .slip-doc-title { font-size: 12px !important; letter-spacing: 2px !important; }
    .slip-roll { font-size: 38px !important; }
    .slip-roll small { font-size: 10px !important; }
    .slip-body { padding: 30px 40px 20px !important; flex: 1 !important; }
    .slip-exam-name { font-size: 20px !important; letter-spacing: 3px !important; padding: 14px 0 !important; margin-bottom: 24px !important; border-top-width: 3px !important; border-bottom-width: 3px !important; }
    .slip-info-grid { gap: 14px 40px !important; margin-bottom: 28px !important; }
    .slip-field .lbl { font-size: 12px !important; }
    .slip-field .val { font-size: 15px !important; border-bottom-width: 2px !important; padding-bottom: 4px !important; }
    .slip-subj-tbl { font-size: 14px !important; margin-top: 8px !important; }
    .slip-subj-tbl th { padding: 12px 16px !important; font-size: 12px !important; letter-spacing: 1px !important; }
    .slip-subj-tbl td { padding: 10px 16px !important; font-size: 14px !important; }
    .slip-inst { font-size: 11px !important; margin-top: 20px !important; padding: 15px !important; }
    .slip-footer { padding: 20px 40px 24px !important; margin-top: 16px !important; }
    .slip-sign { min-width: 140px !important; }
    .slip-sign-line { margin-top: 50px !important; padding-top: 8px !important; font-size: 13px !important; border-top-width: 2px !important; }
    .slip-branding { padding: 14px 40px !important; font-size: 10px !important; margin-top: auto !important; }
    .slip-branding strong { font-size: 10px !important; }
}
</style>

<div class="no-print" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;background:#fff;padding:14px 20px;border-radius:8px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
  <div>
    <h1 style="font-size:1.25rem;font-weight:700;color:#1e293b;margin:0">
      <i class="fa-solid fa-id-card" style="color:#0284c7;margin-right:8px"></i>Roll Number Slips: <?= htmlspecialchars($class_info['name'].' '.($class_info['section']??'')) ?>
    </h1>
    <p style="font-size:.85rem;color:#64748b;margin:4px 0 0 0">Exam: <strong><?= htmlspecialchars($sched_info['title'] ?? 'Exam') ?></strong> &bull; Total Students: <?= count($students) ?></p>
  </div>
  <div style="display:flex;gap:10px">
    <a href="roll_number_slips.php?action=show&sched_id=<?= $sched_id ?>" class="btn btn-secondary" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:6px">
      <i class="fa-solid fa-arrow-left"></i> Back to Classes
    </a>
    <?php if(!empty($students)): ?>
    <button class="btn btn-primary" onclick="window.print()" style="background:#0284c7;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 6px rgba(2,132,199,0.3)">
      <i class="fa-solid fa-print"></i> Print All Slips
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if(empty($students)): ?>
<div class="empty-state" style="padding:50px;text-align:center;background:#fff;border-radius:8px;border:1px solid #e2e8f0;margin-top:20px">
    <i class="fa-solid fa-users-slash" style="font-size:3rem;color:#cbd5e1"></i>
    <h3 style="margin-top:15px;color:#475569;font-weight:600">No active students found in this class</h3>
    <p style="color:#94a3b8;font-size:0.9rem">Please make sure students are enrolled in this class for the active session.</p>
</div>
<?php else: ?>
<div class="slips-grid">
<?php foreach($students as $s): ?>
<div class="slip">
  <div class="slip-hdr">
    <div class="slip-logo">
      <?php if(!empty($settings['school_logo']) && file_exists(UPLOAD_PATH.$settings['school_logo'])): ?>
        <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($settings['school_logo']) ?>">
      <?php else: ?>
        <?= strtoupper(substr($school_name,0,1)) ?>
      <?php endif; ?>
    </div>
    <div style="flex:1">
      <div class="slip-school-name"><?= htmlspecialchars($school_name) ?></div>
      <div class="slip-doc-title">Roll Number Slip</div>
    </div>
    <div style="text-align:right">
      <div class="slip-roll"><?= htmlspecialchars($s['enrollment_roll_no'] ?? ($s['roll_no'] ?? '--')) ?><small>Roll No.</small></div>
    </div>
  </div>

  <div class="slip-body">
    <div class="slip-exam-name"><?= htmlspecialchars($sched_info['title'] ?? 'EXAMINATION') ?></div>
    
    <div class="slip-info-grid">
      <div class="slip-field slip-field-full">
        <span class="lbl">Student Name:</span>
        <span class="val"><?= htmlspecialchars($s['name']) ?></span>
      </div>
      <div class="slip-field slip-field-full">
        <span class="lbl">Father Name:</span>
        <span class="val"><?= htmlspecialchars($s['father_name']??'') ?></span>
      </div>
      <div class="slip-field">
        <span class="lbl">Reg #:</span>
        <span class="val"><?= htmlspecialchars($s['admission_no']) ?></span>
      </div>
      <div class="slip-field">
        <span class="lbl">Class:</span>
        <span class="val"><?= htmlspecialchars(($class_info['name']??'').' '.($class_info['section']??'')) ?></span>
      </div>
      <div class="slip-field">
        <span class="lbl">Session:</span>
        <span class="val"><?= htmlspecialchars($settings['session_year']??date('Y')) ?></span>
      </div>
    </div>

    <table class="slip-subj-tbl">
      <thead>
        <tr>
            <th style="width:50%;text-align:left;">Subject</th>
            <th style="width:25%;text-align:center;">Date</th>
            <th style="width:25%;text-align:center;">Time</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($subjects)): ?>
        <tr><td colspan="3" style="text-align:center;color:#94a3b8;font-style:italic">No subjects assigned</td></tr>
      <?php else: ?>
          <?php foreach($subjects as $sub): 
              $sub_k = strtolower(trim($sub['subject']));
              $date_val = $dates_map[$sub_k]['exam_date'] ?? '';
              $time_val = $dates_map[$sub_k]['exam_time'] ?? '';
          ?>
          <tr>
            <td><?= htmlspecialchars($sub['subject']) ?></td>
            <td style="color:#475569;font-weight:600"><?= $date_val ? htmlspecialchars($date_val) : '--' ?></td>
            <td style="color:#475569;font-weight:600"><?= $time_val ? htmlspecialchars($time_val) : '--' ?></td>
          </tr>
          <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
    
    <?php if(!empty($sched_info['instructions'])): ?>
    <div class="slip-inst"><strong>Instructions:</strong><br><?= nl2br(htmlspecialchars($sched_info['instructions'])) ?></div>
    <?php endif; ?>
  </div>

  <div class="slip-footer">
    <div class="slip-sign">
      <div class="slip-sign-line">Student's Signature</div>
    </div>
    <div style="text-align:center; align-self:flex-end;">
      <div style="font-size:9px; color:#94a3b8; margin-bottom:2px;"><?= date('d M Y') ?></div>
    </div>
    <div class="slip-sign">
      <div class="slip-sign-line"><?= htmlspecialchars($settings['principal_name']??'Principal') ?></div>
    </div>
  </div>

  <div class="slip-branding">
    <span class="brand-dot"></span>
    <strong>Generated by SIAX Technologies — SMSS</strong>
    <span class="brand-dot"></span>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php 
endif;

require_once __DIR__ . '/../../includes/footer.php';
?>
