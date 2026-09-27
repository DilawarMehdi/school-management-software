<?php
/**
 * SIAX SMSS - Bulk Enrollment & Promotion
 * Premium dual-pane UI design matching SIAX premium aesthetics.
 */
$page_title = 'Bulk Enrollment';
$active_page = 'bulk_enrollment';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal');

$msg = ''; $err = '';

// Load settings for sessions
$settings = all_settings($conn);
$active_session = $settings['session_year'] ?? '2025-2026';
$session_list_str = $settings['session_list'] ?? '2024-2025,2025-2026,2026-2027';
$sessions = array_filter(array_map('trim', explode(',', $session_list_str)));
if (!in_array($active_session, $sessions)) {
    $sessions[] = $active_session;
}
$sessions = array_unique($sessions);

// Load all classes (naturally sorted)
$classes = get_all_classes($conn);

// Group unique class names and sections
$class_names = [];
$class_sections = [];
foreach ($classes as $c) {
    if (!in_array($c['name'], $class_names)) {
        $class_names[] = $c['name'];
    }
    if (!isset($class_sections[$c['name']])) {
        $class_sections[$c['name']] = [];
    }
    $class_sections[$c['name']][] = [
        'id' => $c['id'],
        'section' => $c['section']
    ];
}
// Ensure class names are naturally sorted
usort($class_names, function($a, $b) {
    return compare_classes_naturally(['name' => $a], ['name' => $b]);
});


// Initial source parameters
$source_session = $_REQUEST['source_session'] ?? $active_session;
$source_class_id = (int)($_REQUEST['source_class_id'] ?? ($classes[0]['id'] ?? 0));
$source_shift = $_REQUEST['source_shift'] ?? 'Morning';
$discharge_date = $_REQUEST['discharge_date'] ?? date('Y-m-d');

// Initial target parameters
$target_session = $_REQUEST['target_session'] ?? ($sessions[1] ?? $active_session);
$target_class_id = (int)($_REQUEST['target_class_id'] ?? ($classes[1]['id'] ?? ($classes[0]['id'] ?? 0)));
$target_shift = $_REQUEST['target_shift'] ?? 'Morning';
$enrollment_date = $_REQUEST['enrollment_date'] ?? date('Y-m-d', strtotime('+1 day'));

// Handle standard form fallback submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'promote_standard') {
    $src_session = $conn->real_escape_string(trim($_POST['source_session'] ?? ''));
    $tgt_session = $conn->real_escape_string(trim($_POST['target_session'] ?? ''));
    $src_cid = (int)($_POST['source_class_id'] ?? 0);
    $tgt_cid = (int)($_POST['target_class_id'] ?? 0);
    $d_date = $conn->real_escape_string(trim($_POST['discharge_date'] ?? date('Y-m-d')));
    $e_date = $conn->real_escape_string(trim($_POST['enrollment_date'] ?? date('Y-m-d')));
    $t_shift = $conn->real_escape_string(trim($_POST['target_shift'] ?? 'Morning'));
    $selected_students = $_POST['selected_students'] ?? [];

    if (empty($selected_students)) {
        $err = "Please select at least one student to promote/enroll.";
    } elseif (!$tgt_cid) {
        $err = "Please select destination target class.";
    } elseif (!$tgt_session) {
        $err = "Please select destination target academic session.";
    } else {
        $conn->begin_transaction();
        try {
            $success_count = 0;
            foreach ($selected_students as $student_id) {
                $student_id = (int)$student_id;
                if ($student_id <= 0) continue;

                // 1. Fetch current details
                $st_q = $conn->query("
                    SELECT se.roll_no, se.class_id 
                    FROM student_enrollments se 
                    WHERE se.student_id = $student_id AND se.session_year = '$src_session' AND se.status = 'Active'
                    LIMIT 1
                ");
                if ($st_q && $st_q->num_rows > 0) {
                    $st_data = $st_q->fetch_assoc();
                    $old_roll_no = $st_data['roll_no'];
                    $old_class_id = $st_data['class_id'];
                } else {
                    $st_q2 = $conn->query("SELECT roll_no, class_id FROM students WHERE id = $student_id LIMIT 1");
                    $st_data2 = $st_q2 ? $st_q2->fetch_assoc() : [];
                    $old_roll_no = $st_data2['roll_no'] ?? '';
                    $old_class_id = (int)($st_data2['class_id'] ?? $src_cid);
                }

                // 2. Determine target roll number
                $taken_q = $conn->query("
                    SELECT id FROM student_enrollments 
                    WHERE class_id = $tgt_cid AND session_year = '$tgt_session' AND roll_no = '$old_roll_no' AND student_id != $student_id
                ");
                if ($old_roll_no && (!$taken_q || $taken_q->num_rows == 0)) {
                    $new_roll_no = $old_roll_no;
                } else {
                    $roll_res = $conn->query("
                        SELECT roll_no FROM student_enrollments 
                        WHERE class_id = $tgt_cid AND session_year = '$tgt_session' AND roll_no != '' 
                        ORDER BY CAST(roll_no AS UNSIGNED) DESC LIMIT 1
                    ");
                    $new_roll_no = 1;
                    if ($roll_res && $roll_res->num_rows > 0) {
                        $new_roll_no = ((int)$roll_res->fetch_assoc()['roll_no']) + 1;
                    }
                }

                // 3. Mark old enrollment promoted
                if ($old_class_id) {
                    $conn->query("
                        UPDATE student_enrollments 
                        SET status = 'Promoted', discharge_date = '$d_date' 
                        WHERE student_id = $student_id AND class_id = $old_class_id AND status = 'Active'
                    ");
                }

                // 4. Update main students table
                $conn->query("UPDATE students SET class_id = $tgt_cid, roll_no = '$new_roll_no' WHERE id = $student_id");

                // 5. Insert or update target enrollment
                $check_q = $conn->query("
                    SELECT id FROM student_enrollments 
                    WHERE student_id = $student_id AND session_year = '$tgt_session'
                ");
                if ($check_q && $check_q->num_rows > 0) {
                    $exist_id = (int)$check_q->fetch_assoc()['id'];
                    $conn->query("
                        UPDATE student_enrollments 
                        SET class_id = $tgt_cid, roll_no = '$new_roll_no', shift = '$t_shift', 
                            enrollment_date = '$e_date', discharge_date = NULL, status = 'Active' 
                        WHERE id = $exist_id
                    ");
                } else {
                    $conn->query("
                        INSERT INTO student_enrollments (student_id, class_id, session_year, roll_no, shift, enrollment_date, status) 
                        VALUES ($student_id, $tgt_cid, '$tgt_session', '$new_roll_no', '$t_shift', '$e_date', 'Active')
                    ");
                }

                $success_count++;
            }
            $conn->commit();
            $msg = "Successfully promoted/enrolled $success_count student" . ($success_count > 1 ? 's' : '') . " to the destination class.";
        } catch (Exception $e) {
            $conn->rollback();
            $err = "An error occurred during promotion: " . $e->getMessage();
        }
    }
}
?>

<style>
/* SIAX Premium Bulk Enrollment Styles */
.bulk-header-wrap {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    gap: 16px;
    flex-wrap: wrap;
}
.bulk-header-wrap .title-area h1 {
    font-size: 1.85rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.bulk-header-wrap .title-area p {
    color: var(--text-muted);
    font-size: 0.88rem;
    margin: 0;
    line-height: 1.4;
}
.bulk-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #00cec9;
    color: #fff;
    font-weight: 600;
    font-size: 0.85rem;
    padding: 8px 22px;
    border-radius: var(--radius-xs);
    border: none;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0, 206, 201, 0.25);
    transition: all var(--transition);
    text-decoration: none;
}
.bulk-back-btn:hover {
    background: #00b894;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(0, 184, 148, 0.35);
}

/* Dual Column Stage */
.bulk-stage-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 60px minmax(0, 1fr);
    gap: 20px;
    align-items: stretch;
    margin-bottom: 30px;
}
@media (max-width: 1024px) {
    .bulk-stage-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
}

/* Panel Card */
.bulk-panel-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: border-color var(--transition), box-shadow var(--transition);
}
.bulk-panel-card:hover {
    border-color: var(--border-hover);
}

/* Panel Mini Header Tab */
.panel-tab-strip {
    background: rgba(99, 102, 241, 0.04);
    border-bottom: 1px solid var(--border);
    padding: 6px 14px;
    display: flex;
    align-items: center;
}
.panel-tab-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
}

/* Panel Title Area */
.panel-title-bar {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.01);
}
.panel-title-bar h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Panel Filters Form (Aligned Two-Column Grid like reference) */
.panel-form-body {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.015);
}
.field-row {
    display: grid;
    grid-template-columns: 130px 1fr;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.field-row:last-child {
    margin-bottom: 0;
}
.field-label {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-align: right;
    padding-right: 6px;
}
.field-input-wrap {
    position: relative;
    width: 100%;
}
.field-input-wrap .form-control {
    width: 100%;
    height: 38px;
    padding: 6px 12px;
    font-size: 0.85rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xs);
    color: var(--text-primary);
    transition: all var(--transition);
}
.field-input-wrap .form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}

/* Central Action Column */
.bulk-action-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
}
.transfer-trigger-btn {
    width: 54px;
    height: 44px;
    background: linear-gradient(135deg, #0984e3, #6c5ce7);
    color: #fff;
    border: none;
    border-radius: var(--radius-xs);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(9, 132, 227, 0.35);
    transition: all var(--transition);
    user-select: none;
}
.transfer-trigger-btn:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 20px rgba(108, 92, 231, 0.45);
    background: linear-gradient(135deg, #00b894, #0984e3);
}
.transfer-trigger-btn:active {
    transform: scale(0.96);
}
.transfer-count-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    background: var(--accent-glow);
    color: var(--accent);
    border: 1px solid var(--border);
    white-space: nowrap;
}

/* Student Table Section */
.panel-table-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 280px;
    max-height: 480px;
    overflow-y: auto;
    position: relative;
}
.panel-table-toolbar {
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}
.panel-table-toolbar input[type="text"] {
    max-width: 200px;
    height: 32px;
    padding: 4px 10px;
    font-size: 0.8rem;
    border-radius: var(--radius-xs);
}
.student-data-table {
    width: 100%;
    border-collapse: collapse;
}
.student-data-table th {
    position: sticky;
    top: 0;
    background: var(--bg-secondary);
    z-index: 10;
    padding: 10px 14px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    text-align: left;
}
.student-data-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.student-data-table tr.selected-row {
    background: rgba(9, 132, 227, 0.08) !important;
}
.student-data-table tr:hover {
    background: var(--accent-glow);
}

/* Loading dolphins/spinner indicator */
.dolphins-loader {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(var(--bg-card), 0.7);
    backdrop-filter: blur(3px);
    z-index: 30;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 12px;
}
.dolphins-loader.active {
    display: flex;
}
.dolphins-spinner {
    width: 44px;
    height: 44px;
    border: 3px solid rgba(0, 184, 148, 0.15);
    border-top: 3px solid #e84393;
    border-right: 3px solid #0984e3;
    border-bottom: 3px solid #00b894;
    border-radius: 50%;
    animation: dolphinSpin 0.9s linear infinite;
}
@keyframes dolphinSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Toast Message */
#bulkToast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 14px 22px;
    background: #10b981;
    color: #fff;
    font-weight: 600;
    font-size: 0.9rem;
    border-radius: var(--radius-xs);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
    z-index: 9999;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 10px;
    pointer-events: none;
}
#bulkToast.show {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}
#bulkToast.error {
    background: #ef4444;
}
</style>

<div class="main-container">

    <!-- Top Page Header -->
    <div class="bulk-header-wrap">
        <div class="title-area">
            <h1>Bulk Enrollment</h1>
            <p>Bulk Enrollment feature helps you to promote multiple students from previouse class to next class or you can merg one section into other etc</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/fall/session_management.php" class="bulk-back-btn" title="Go back to Sessions">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($msg): ?>
        <div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:20px;padding:12px 18px;border-radius:var(--radius-xs);display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="login-error" style="background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#f87171;margin-bottom:20px;padding:12px 18px;border-radius:var(--radius-xs);display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>

    <!-- Dual-Pane Promotion Stage -->
    <form id="bulkEnrollForm" method="POST" action="">
        <input type="hidden" name="action" value="promote_standard">

        <div class="bulk-stage-grid">
            
            <!-- LEFT PANEL: DISCHARGE FROM -->
            <div class="bulk-panel-card">
                <div class="panel-tab-strip">
                    <div class="panel-tab-pill">
                        <i class="fa-solid fa-table-cells" style="color:#0984e3;"></i>
                    </div>
                </div>

                <div class="panel-title-bar">
                    <h2>Discharge From</h2>
                </div>

                <div class="panel-form-body">
                    <!-- Fall / Session -->
                    <div class="field-row">
                        <label class="field-label">Fall/Session</label>
                        <div class="field-input-wrap">
                            <select name="source_session" id="source_session" class="form-control" onchange="loadSourceStudents()">
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= $s === $source_session ? 'selected' : '' ?>>
                                        Session <?= htmlspecialchars($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Class -->
                    <div class="field-row">
                        <label class="field-label">Class</label>
                        <div class="field-input-wrap">
                            <select name="source_class_name" id="source_class_name" class="form-control" onchange="onSourceClassNameChange()">
                                <?php foreach ($class_names as $cn): ?>
                                    <option value="<?= htmlspecialchars($cn) ?>">
                                        <?= htmlspecialchars($cn) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Section -->
                    <div class="field-row">
                        <label class="field-label">Section</label>
                        <div class="field-input-wrap">
                            <select name="source_class_id" id="source_class_id" class="form-control" onchange="loadSourceStudents()">
                                <!-- Dynamically populated by JS -->
                            </select>
                        </div>
                    </div>

                    <!-- Shift -->
                    <div class="field-row">
                        <label class="field-label">Shift</label>
                        <div class="field-input-wrap">
                            <select name="source_shift" id="source_shift" class="form-control" onchange="loadSourceStudents()">
                                <option value="Morning" <?= $source_shift === 'Morning' ? 'selected' : '' ?>>Morning</option>
                                <option value="Evening" <?= $source_shift === 'Evening' ? 'selected' : '' ?>>Evening</option>
                                <option value="Afternoon" <?= $source_shift === 'Afternoon' ? 'selected' : '' ?>>Afternoon</option>
                                <option value="All">All Shifts</option>
                            </select>
                        </div>
                    </div>

                    <!-- Discharge Date -->
                    <div class="field-row">
                        <label class="field-label">Discharge Date</label>
                        <div class="field-input-wrap">
                            <input type="date" name="discharge_date" id="discharge_date" class="form-control" value="<?= htmlspecialchars($discharge_date) ?>">
                        </div>
                    </div>
                </div>

                <!-- Student Checklist Table -->
                <div class="panel-table-wrap" id="sourceTableWrap">
                    <div class="dolphins-loader" id="sourceLoader">
                        <div class="dolphins-spinner"></div>
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Loading students...</div>
                    </div>

                    <div class="panel-table-toolbar">
                        <div style="font-size: 0.8rem; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="selectAllSource" onclick="toggleSelectAllSource(this)" style="transform: scale(1.15); cursor: pointer;">
                            <label for="selectAllSource" style="cursor: pointer; font-weight: 600; font-size: 0.78rem;">Select All (<span id="sourceSelectedCount">0</span>/<span id="sourceTotalCount">0</span>)</label>
                        </div>
                        <div>
                            <input type="text" id="sourceSearchInput" placeholder="Filter list..." class="form-control" onkeyup="filterSourceTable()">
                        </div>
                    </div>

                    <table class="student-data-table" id="sourceStudentsTable">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">#</th>
                                <th style="width: 75px;">Roll No</th>
                                <th style="width: 105px;">Adm No</th>
                                <th>Student Name</th>
                                <th>Father Name</th>
                            </tr>
                        </thead>
                        <tbody id="sourceStudentsBody">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 8px; display: block; color: var(--accent);"></i>
                                    Fetching student records...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CENTRAL ACTION COLUMN -->
            <div class="bulk-action-column">
                <button type="button" class="transfer-trigger-btn" id="transferBtn" onclick="triggerTransfer()" title="Promote / Transfer Selected Students">
                    &gt;&gt;
                </button>
                <div class="transfer-count-badge" id="actionCountBadge">0 Selected</div>
            </div>

            <!-- RIGHT PANEL: ENROLL IN -->
            <div class="bulk-panel-card">
                <div class="panel-tab-strip">
                    <div class="panel-tab-pill">
                        <i class="fa-solid fa-table-cells" style="color:#00b894;"></i>
                    </div>
                </div>

                <div class="panel-title-bar">
                    <h2>Enroll In</h2>
                </div>

                <div class="panel-form-body">
                    <!-- Fall / Session -->
                    <div class="field-row">
                        <label class="field-label">Fall/Session</label>
                        <div class="field-input-wrap">
                            <select name="target_session" id="target_session" class="form-control" onchange="loadTargetStudents()">
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= $s === $target_session ? 'selected' : '' ?>>
                                        Session <?= htmlspecialchars($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Class -->
                    <div class="field-row">
                        <label class="field-label">Class</label>
                        <div class="field-input-wrap">
                            <select name="target_class_name" id="target_class_name" class="form-control" onchange="onTargetClassNameChange()">
                                <?php foreach ($class_names as $cn): ?>
                                    <option value="<?= htmlspecialchars($cn) ?>">
                                        <?= htmlspecialchars($cn) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Section -->
                    <div class="field-row">
                        <label class="field-label">Section</label>
                        <div class="field-input-wrap">
                            <select name="target_class_id" id="target_class_id" class="form-control" onchange="loadTargetStudents()">
                                <!-- Dynamically populated by JS -->
                            </select>
                        </div>
                    </div>

                    <!-- Shift -->
                    <div class="field-row">
                        <label class="field-label">Shift</label>
                        <div class="field-input-wrap">
                            <select name="target_shift" id="target_shift" class="form-control" onchange="loadTargetStudents()">
                                <option value="Morning" <?= $target_shift === 'Morning' ? 'selected' : '' ?>>Morning</option>
                                <option value="Evening" <?= $target_shift === 'Evening' ? 'selected' : '' ?>>Evening</option>
                                <option value="Afternoon" <?= $target_shift === 'Afternoon' ? 'selected' : '' ?>>Afternoon</option>
                            </select>
                        </div>
                    </div>

                    <!-- Enrollment Date -->
                    <div class="field-row">
                        <label class="field-label">Enrollment Date</label>
                        <div class="field-input-wrap">
                            <input type="date" name="enrollment_date" id="enrollment_date" class="form-control" value="<?= htmlspecialchars($enrollment_date) ?>">
                        </div>
                    </div>
                </div>

                <!-- Enrolled Destination Roster Table -->
                <div class="panel-table-wrap" id="targetTableWrap">
                    <div class="dolphins-loader" id="targetLoader">
                        <div class="dolphins-spinner"></div>
                        <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Loading destination roster...</div>
                    </div>

                    <div class="panel-table-toolbar">
                        <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">
                            <i class="fa-solid fa-user-check" style="color: #00b894; margin-right: 4px;"></i> Enrolled Roster (<span id="targetTotalCount">0</span>)
                        </div>
                        <div>
                            <input type="text" id="targetSearchInput" placeholder="Filter destination..." class="form-control" onkeyup="filterTargetTable()">
                        </div>
                    </div>

                    <table class="student-data-table" id="targetStudentsTable">
                        <thead>
                            <tr>
                                <th style="width: 75px;">Roll No</th>
                                <th style="width: 105px;">Adm No</th>
                                <th>Student Name</th>
                                <th>Father Name</th>
                                <th style="width: 80px;">Shift</th>
                            </tr>
                        </thead>
                        <tbody id="targetStudentsBody">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    Select destination class to inspect roster.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </form>

</div>

<!-- Animated Toast Component -->
<div id="bulkToast"><i class="fa-solid fa-circle-check"></i> <span id="bulkToastMsg">Message</span></div>

<script>
// Data structure passed from PHP
const classSectionsMap = <?= json_encode($class_sections) ?>;
const initialSourceClassId = <?= (int)$source_class_id ?>;
const initialTargetClassId = <?= (int)$target_class_id ?>;

// Initialize Class & Section Dropdowns on page load
document.addEventListener('DOMContentLoaded', function() {
    initClassDropdowns();
    loadSourceStudents();
    loadTargetStudents();
});

function initClassDropdowns() {
    // 1. Setup source dropdowns
    const srcClassSelect = document.getElementById('source_class_name');
    let foundSrcClass = '';
    
    // Find class name containing initialSourceClassId
    for (let cName in classSectionsMap) {
        for (let s of classSectionsMap[cName]) {
            if (s.id == initialSourceClassId) {
                foundSrcClass = cName;
                break;
            }
        }
        if (foundSrcClass) break;
    }
    if (foundSrcClass) {
        srcClassSelect.value = foundSrcClass;
    }
    populateSections('source', initialSourceClassId);

    // 2. Setup target dropdowns
    const tgtClassSelect = document.getElementById('target_class_name');
    let foundTgtClass = '';
    for (let cName in classSectionsMap) {
        for (let s of classSectionsMap[cName]) {
            if (s.id == initialTargetClassId) {
                foundTgtClass = cName;
                break;
            }
        }
        if (foundTgtClass) break;
    }
    if (foundTgtClass) {
        tgtClassSelect.value = foundTgtClass;
    }
    populateSections('target', initialTargetClassId);
}

function onSourceClassNameChange() {
    populateSections('source');
    loadSourceStudents();
}

function onTargetClassNameChange() {
    populateSections('target');
    loadTargetStudents();
}

function populateSections(side, selectId = null) {
    const className = document.getElementById(side + '_class_name').value;
    const sectionSelect = document.getElementById(side + '_class_id');
    sectionSelect.innerHTML = '';

    const sections = classSectionsMap[className] || [];
    sections.forEach((sec, idx) => {
        const opt = document.createElement('option');
        opt.value = sec.id;
        opt.textContent = sec.section ? 'Section ' + sec.section : 'Default Section';
        if (selectId && sec.id == selectId) {
            opt.selected = true;
        } else if (!selectId && idx === 0) {
            opt.selected = true;
        }
        sectionSelect.appendChild(opt);
    });
}

// Fetch Source Students via AJAX
function loadSourceStudents() {
    const session = document.getElementById('source_session').value;
    const classId = document.getElementById('source_class_id').value;
    const shift = document.getElementById('source_shift').value;
    const loader = document.getElementById('sourceLoader');
    const tbody = document.getElementById('sourceStudentsBody');

    if (!classId) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted);">No class selected.</td></tr>';
        updateSourceCounts(0, 0);
        return;
    }

    loader.classList.add('active');

    fetch(`<?= BASE_URL ?>api/data.php?action=get_bulk_students&session_year=${encodeURIComponent(session)}&class_id=${classId}&shift=${encodeURIComponent(shift)}&no_fallback=1`)
        .then(res => res.json())
        .then(data => {
            loader.classList.remove('active');
            if (data.success && data.students && data.students.length > 0) {
                renderSourceRows(data.students);
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);"><i class="fa-solid fa-folder-open" style="font-size:1.8rem;margin-bottom:8px;display:block;opacity:0.5;"></i>No active students found for this class and session.</td></tr>';
                updateSourceCounts(0, 0);
            }
        })
        .catch(err => {
            loader.classList.remove('active');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:#ef4444;">Error loading student records.</td></tr>';
        });
}

function renderSourceRows(students) {
    const tbody = document.getElementById('sourceStudentsBody');
    tbody.innerHTML = '';
    
    students.forEach(st => {
        const tr = document.createElement('tr');
        tr.className = 'source-student-row';
        tr.dataset.id = st.id;
        tr.dataset.name = (st.name || '').toLowerCase();
        tr.dataset.father = (st.father_name || '').toLowerCase();
        tr.dataset.adm = (st.admission_no || '').toLowerCase();
        tr.dataset.roll = (st.roll_no || '').toLowerCase();

        tr.innerHTML = `
            <td style="text-align: center;">
                <input type="checkbox" name="selected_students[]" value="${st.id}" class="source-chk" style="transform: scale(1.15); cursor: pointer;" onchange="onSourceCheckChange(this)">
            </td>
            <td style="font-weight: 700; color: var(--text-primary);">${escapeHtml(st.roll_no || '-')}</td>
            <td style="color: var(--text-secondary);">${escapeHtml(st.admission_no || '-')}</td>
            <td style="font-weight: 600; color: var(--text-primary);">${escapeHtml(st.name || '')}</td>
            <td style="color: var(--text-secondary);">${escapeHtml(st.father_name || '-')}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('selectAllSource').checked = false;
    updateSourceCounts(0, students.length);
}

// Fetch Target Students (Destination Roster) via AJAX
function loadTargetStudents() {
    const session = document.getElementById('target_session').value;
    const classId = document.getElementById('target_class_id').value;
    const shift = document.getElementById('target_shift').value;
    const loader = document.getElementById('targetLoader');
    const tbody = document.getElementById('targetStudentsBody');

    if (!classId) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted);">No class selected.</td></tr>';
        document.getElementById('targetTotalCount').innerText = 0;
        return;
    }

    loader.classList.add('active');

    fetch(`<?= BASE_URL ?>api/data.php?action=get_bulk_students&session_year=${encodeURIComponent(session)}&class_id=${classId}&shift=${encodeURIComponent(shift)}&no_fallback=1`)
        .then(res => res.json())
        .then(data => {
            loader.classList.remove('active');
            if (data.success && data.students && data.students.length > 0) {
                renderTargetRows(data.students);
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);"><i class="fa-solid fa-graduation-cap" style="font-size:1.8rem;margin-bottom:8px;display:block;opacity:0.4;"></i>Currently no students enrolled in this target class.</td></tr>';
                document.getElementById('targetTotalCount').innerText = 0;
            }
        })
        .catch(err => {
            loader.classList.remove('active');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:#ef4444;">Error loading destination records.</td></tr>';
        });
}

function renderTargetRows(students) {
    const tbody = document.getElementById('targetStudentsBody');
    tbody.innerHTML = '';

    students.forEach(st => {
        const tr = document.createElement('tr');
        tr.className = 'target-student-row';
        tr.dataset.name = (st.name || '').toLowerCase();
        tr.dataset.father = (st.father_name || '').toLowerCase();
        tr.dataset.adm = (st.admission_no || '').toLowerCase();
        tr.dataset.roll = (st.roll_no || '').toLowerCase();

        tr.innerHTML = `
            <td style="font-weight: 700; color: var(--text-primary);">${escapeHtml(st.roll_no || '-')}</td>
            <td style="color: var(--text-secondary);">${escapeHtml(st.admission_no || '-')}</td>
            <td style="font-weight: 600; color: var(--text-primary);">${escapeHtml(st.name || '')}</td>
            <td style="color: var(--text-secondary);">${escapeHtml(st.father_name || '-')}</td>
            <td><span class="badge badge-info" style="font-size:0.7rem;">${escapeHtml(st.shift || 'Morning')}</span></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('targetTotalCount').innerText = students.length;
}

// Checkbox selection helpers
function onSourceCheckChange(chk) {
    const row = chk.closest('tr');
    if (chk.checked) {
        row.classList.add('selected-row');
    } else {
        row.classList.remove('selected-row');
    }
    calculateSourceSelected();
}

function toggleSelectAllSource(master) {
    const checkboxes = document.querySelectorAll('#sourceStudentsBody .source-chk');
    checkboxes.forEach(chk => {
        const row = chk.closest('.source-student-row');
        if (row.style.display !== 'none') {
            chk.checked = master.checked;
            if (master.checked) {
                row.classList.add('selected-row');
            } else {
                row.classList.remove('selected-row');
            }
        }
    });
    calculateSourceSelected();
}

function calculateSourceSelected() {
    const checked = document.querySelectorAll('#sourceStudentsBody .source-chk:checked').length;
    const total = document.querySelectorAll('#sourceStudentsBody .source-student-row').length;
    updateSourceCounts(checked, total);
}

function updateSourceCounts(selected, total) {
    document.getElementById('sourceSelectedCount').innerText = selected;
    document.getElementById('sourceTotalCount').innerText = total;
    document.getElementById('actionCountBadge').innerText = `${selected} Selected`;
}

// Client-Side Real-time Filter
function filterSourceTable() {
    const q = document.getElementById('sourceSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#sourceStudentsBody .source-student-row');
    rows.forEach(r => {
        const match = r.dataset.name.includes(q) || r.dataset.father.includes(q) || r.dataset.adm.includes(q) || r.dataset.roll.includes(q);
        r.style.display = match ? '' : 'none';
        if (!match) {
            const chk = r.querySelector('.source-chk');
            if (chk && chk.checked) {
                chk.checked = false;
                r.classList.remove('selected-row');
            }
        }
    });
    calculateSourceSelected();
}

function filterTargetTable() {
    const q = document.getElementById('targetSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#targetStudentsBody .target-student-row');
    rows.forEach(r => {
        const match = r.dataset.name.includes(q) || r.dataset.father.includes(q) || r.dataset.adm.includes(q) || r.dataset.roll.includes(q);
        r.style.display = match ? '' : 'none';
    });
}

// Central Promotion Transfer Trigger via AJAX
function triggerTransfer() {
    const checkedBoxes = document.querySelectorAll('#sourceStudentsBody .source-chk:checked');
    const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);

    if (selectedIds.length === 0) {
        showToast('Please select at least one student from Discharge From to promote.', true);
        return;
    }

    const sourceSession = document.getElementById('source_session').value;
    const targetSession = document.getElementById('target_session').value;
    const sourceClassId = document.getElementById('source_class_id').value;
    const targetClassId = document.getElementById('target_class_id').value;
    const dischargeDate = document.getElementById('discharge_date').value;
    const enrollmentDate = document.getElementById('enrollment_date').value;
    const targetShift = document.getElementById('target_shift').value;

    if (!targetClassId) {
        showToast('Please select destination class in Enroll In panel.', true);
        return;
    }

    const btn = document.getElementById('transferBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('action', 'bulk_enroll');
    formData.append('source_session', sourceSession);
    formData.append('target_session', targetSession);
    formData.append('source_class_id', sourceClassId);
    formData.append('target_class_id', targetClassId);
    formData.append('discharge_date', dischargeDate);
    formData.append('enrollment_date', enrollmentDate);
    formData.append('target_shift', targetShift);
    selectedIds.forEach(id => formData.append('selected_students[]', id));

    fetch('<?= BASE_URL ?>api/data.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '&gt;&gt;';

        if (res.success) {
            showToast(res.message || 'Promotion completed successfully!');
            // Refresh both tables
            loadSourceStudents();
            loadTargetStudents();
        } else {
            showToast(res.message || 'An error occurred during enrollment.', true);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '&gt;&gt;';
        showToast('Network error while processing bulk enrollment.', true);
    });
}

function showToast(msg, isError = false) {
    const toast = document.getElementById('bulkToast');
    const toastMsg = document.getElementById('bulkToastMsg');
    toastMsg.innerText = msg;
    toast.className = isError ? 'show error' : 'show';
    setTimeout(() => {
        toast.className = '';
    }, 4500);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
