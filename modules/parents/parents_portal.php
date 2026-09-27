<?php
/**
 * SIAX SMSS - Parents Portal Management & Result Announcement Center
 */
ob_start();
$page_title = 'Parents Portal Management';
$active_page = 'parents_portal';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'teacher', 'accountant');

// Ensure exam_types table & announcement columns exist
$conn->query("CREATE TABLE IF NOT EXISTS exam_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    session_year VARCHAR(20) DEFAULT '2025-2026',
    is_announced TINYINT(1) DEFAULT 0,
    result_token VARCHAR(64) DEFAULT NULL,
    announced_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("ALTER TABLE exam_types ADD COLUMN IF NOT EXISTS is_announced TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE exam_types ADD COLUMN IF NOT EXISTS result_token VARCHAR(64) DEFAULT NULL");
$conn->query("ALTER TABLE exam_types ADD COLUMN IF NOT EXISTS announced_at DATETIME DEFAULT NULL");

$active_session = $settings['session_year'] ?? '2025-2026';
$selected_session = $_GET['session'] ?? $active_session;

$msg = '';
$err = '';

// Handle POST Announcement Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcements'])) {
    $announced_ids = $_POST['announce_exam'] ?? [];
    
    // Fetch all exams for selected session
    $all_exams_res = $conn->query("SELECT * FROM exam_types WHERE session_year = '$selected_session'");
    
    $updated_count = 0;
    if ($all_exams_res) {
        while ($ex = $all_exams_res->fetch_assoc()) {
            $eid = (int)$ex['id'];
            $should_announce = in_array($eid, $announced_ids) ? 1 : 0;
            
            // Verify if exam has marks before enabling announcement
            $marks_cnt_q = $conn->query("
                SELECT COUNT(*) as c FROM marks 
                WHERE exam_type_id = $eid 
                   OR schedule_id IN (SELECT id FROM exam_schedules WHERE exam_type_id = $eid) 
                   OR exam_id = $eid
            ");
            $has_marks = ($marks_cnt_q && $marks_cnt_q->fetch_assoc()['c'] > 0);
            
            if ($should_announce && !$has_marks) {
                // Cannot announce exam with no marks entered
                $should_announce = 0;
            }
            
            if ($should_announce) {
                // Ensure unique result token exists
                $token = $ex['result_token'];
                if (empty($token)) {
                    $token = 'res_' . bin2hex(random_bytes(10));
                }
                $conn->query("UPDATE exam_types SET is_announced = 1, result_token = '$token', announced_at = NOW() WHERE id = $eid");
                $updated_count++;
            } else {
                $conn->query("UPDATE exam_types SET is_announced = 0 WHERE id = $eid");
            }
        }
        $msg = 'Parents Portal result announcements updated successfully!';
    }
}

// Fetch exams for current session
$exams_q = $conn->query("SELECT * FROM exam_types WHERE session_year = '$selected_session' ORDER BY id DESC");
$exams_list = [];
$total_announced = 0;
$total_with_marks = 0;

if ($exams_q) {
    while ($row = $exams_q->fetch_assoc()) {
        $eid = (int)$row['id'];
        
        // Count marks entered
        $mc_q = $conn->query("
            SELECT COUNT(*) as c FROM marks 
            WHERE exam_type_id = $eid 
               OR schedule_id IN (SELECT id FROM exam_schedules WHERE exam_type_id = $eid) 
               OR exam_id = $eid
        ");
        $mark_count = $mc_q ? (int)$mc_q->fetch_assoc()['c'] : 0;
        
        // Count distinct students with marks
        $sc_q = $conn->query("
            SELECT COUNT(DISTINCT student_id) as c FROM marks 
            WHERE exam_type_id = $eid 
               OR schedule_id IN (SELECT id FROM exam_schedules WHERE exam_type_id = $eid) 
               OR exam_id = $eid
        ");
        $student_count = $sc_q ? (int)$sc_q->fetch_assoc()['c'] : 0;
        
        $row['mark_count'] = $mark_count;
        $row['student_count'] = $student_count;
        
        if ($mark_count > 0) $total_with_marks++;
        if ($row['is_announced']) $total_announced++;
        
        // Generate token display link if token exists
        if (empty($row['result_token'])) {
            $row['public_url'] = '';
            $row['campus_url'] = '';
        } else {
            $row['public_url'] = BASE_URL . "public_result.php?token=" . $row['result_token'];
            $row['campus_url'] = get_campus_base_url() . "public_result.php?token=" . $row['result_token'];
        }
        
        $exams_list[] = $row;
    }
}

// Fetch available sessions
$sessions_q = $conn->query("SELECT DISTINCT session_year FROM exam_types WHERE session_year != '' UNION SELECT '$active_session' ORDER BY session_year DESC");
$sessions_arr = [];
if ($sessions_q) {
    while ($s = $sessions_q->fetch_assoc()) {
        if (!in_array($s['session_year'], $sessions_arr)) $sessions_arr[] = $s['session_year'];
    }
}
?>

<style>
.parents-container { max-width: 1200px; margin: 0 auto; padding-bottom: 50px; }
.portal-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-md); margin-bottom: 25px; }
.portal-header { background: linear-gradient(135deg, #0984e3, #6c5ce7); padding: 25px 35px; color: #fff; display: flex; justify-content: space-between; align-items: center; }
.portal-header h2 { margin: 0; font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; gap: 12px; }
.portal-header p { margin: 4px 0 0; opacity: 0.9; font-size: 0.88rem; }

.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding: 25px 35px; background: var(--bg-secondary); border-bottom: 1px solid var(--border); }
.stat-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 22px; display: flex; align-items: center; gap: 18px; }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #fff; flex-shrink: 0; }
.stat-info .num { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
.stat-info .label { font-size: 0.78rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

/* Instructions Section */
.instructions-card { background: rgba(9, 132, 227, 0.05); border: 1px solid rgba(9, 132, 227, 0.2); border-radius: var(--radius-lg); padding: 22px 28px; margin-bottom: 25px; }
.instructions-header { display: flex; align-items: center; gap: 12px; color: #0984e3; font-weight: 800; font-size: 1.1rem; margin-bottom: 14px; }
.steps-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-top: 12px; }
.step-item { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; position: relative; }
.step-badge { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; background: #0984e3; color: #fff; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-bottom: 6px; }
.step-title { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
.step-desc { font-size: 0.78rem; color: var(--text-secondary); line-height: 1.4; }

/* Exams Table */
.portal-body { padding: 30px 35px; }
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius); }
.portal-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
.portal-table th { background: var(--bg-secondary); padding: 14px 18px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
.portal-table td { padding: 16px 18px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text-primary); }
.portal-table tr:last-child td { border-bottom: none; }
.portal-table tr:hover { background: rgba(0,0,0,0.015); }

/* Custom Toggle Switch */
.switch { position: relative; display: inline-block; width: 48px; height: 26px; vertical-align: middle; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .3s; border-radius: 50%; }
input:checked + .slider { background-color: #00b894; }
input:focus + .slider { box-shadow: 0 0 1px #00b894; }
input:checked + .slider:before { transform: translateX(22px); }
input:disabled + .slider { opacity: 0.45; cursor: not-allowed; }

.link-input-group { display: flex; align-items: center; gap: 6px; }
.link-input { font-size: 0.78rem; padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-secondary); width: 260px; font-family: monospace; color: var(--text-primary); }
.btn-copy { background: #0984e3; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.2s; }
.btn-copy:hover { background: #074b83; }
.btn-open { background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; font-size: 0.78rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 4px; }
.btn-open:hover { background: var(--border); color: var(--text-primary); }

.toast-popup { position: fixed; bottom: 25px; right: 25px; background: #00b894; color: #fff; padding: 12px 22px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: none; z-index: 9999; align-items: center; gap: 10px; }

@media (max-width: 768px) {
    .portal-header { flex-direction: column; align-items: flex-start; gap: 15px; }
    .stats-grid { grid-template-columns: 1fr; }
    .portal-body { padding: 20px; }
    .link-input { width: 160px; }
}
</style>

<div class="parents-container">
    
    <!-- Top Card -->
    <div class="portal-card">
        <div class="portal-header">
            <div>
                <h2><i class="fa-solid fa-users-viewfinder"></i> Parents Portal Management</h2>
                <p>Announce student examination results publicly for parents via secure, unique links.</p>
            </div>
            
            <!-- Session Selector Filter -->
            <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                <label style="color:#fff; font-weight:700; font-size:0.85rem;">Academic Session:</label>
                <select name="session" class="form-control" style="width: auto; background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.3); font-weight: 700;" onchange="this.form.submit()">
                    <?php foreach($sessions_arr as $s_yr): ?>
                    <option value="<?= htmlspecialchars($s_yr) ?>" <?= $s_yr === $selected_session ? 'selected' : '' ?> style="color:#000;"><?= htmlspecialchars($s_yr) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <!-- Stats Summary -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon" style="background: linear-gradient(135deg, #0984e3, #74b9ff);">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div class="stat-info">
                    <div class="num"><?= count($exams_list) ?></div>
                    <div class="label">Total Created Exams</div>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e, #e17055);">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div class="stat-info">
                    <div class="num"><?= $total_with_marks ?></div>
                    <div class="label">Exams With Marks Entered</div>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #55efc4);">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div class="stat-info">
                    <div class="num"><?= $total_announced ?></div>
                    <div class="label">Announced on Parents Portal</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Instructions Section -->
    <div class="instructions-card">
        <div class="instructions-header">
            <i class="fa-solid fa-circle-info"></i> Instructions — How Result Announcement Works
        </div>
        <p style="margin:0; font-size:0.85rem; color:var(--text-secondary); line-height:1.5;">
            Follow these steps to safely announce examination results to parents through isolated, unique result-checking URLs.
        </p>

        <div class="steps-list">
            <div class="step-item">
                <span class="step-badge">Step 1</span>
                <div class="step-title">Create &amp; Schedule Exam</div>
                <div class="step-desc">Create your exam category/test in <strong>Academic &gt; Add Exam Type</strong>.</div>
            </div>
            
            <div class="step-item">
                <span class="step-badge">Step 2</span>
                <div class="step-title">Enter Student Marks</div>
                <div class="step-desc">Go to <strong>Academic &gt; Add Result</strong> and enter subject marks for students.</div>
            </div>
            
            <div class="step-item">
                <span class="step-badge" style="background:#00b894;">Step 3</span>
                <div class="step-title">Enable Announcement</div>
                <div class="step-desc">Turn on <strong>Announce Result on Parents Portal</strong> toggle below &amp; click Save.</div>
            </div>

            <div class="step-item">
                <span class="step-badge" style="background:#6c5ce7;">Step 4</span>
                <div class="step-title">Share Unique Result Link</div>
                <div class="step-desc">Copy the generated unique link and share with parents via WhatsApp, SMS, or notice.</div>
            </div>
        </div>
    </div>

    <!-- Feedback Message -->
    <?php if ($msg): ?>
        <div class="login-success" style="margin-bottom: 20px; padding: 14px 20px; background: rgba(0,184,148,0.12); border: 1px solid #00b894; color: #00b894; border-radius: 8px; font-weight:700;">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Main Announcements Form Table -->
    <div class="portal-card">
        <div class="portal-body">
            <form method="POST">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-primary);">
                            Result Announcements for Session <?= htmlspecialchars($selected_session) ?>
                        </h3>
                        <p style="margin: 3px 0 0; font-size: 0.82rem; color: var(--text-muted);">
                            Toggle announcement state for each exam. Unique public links are generated automatically.
                        </p>
                    </div>
                    
                    <button type="submit" name="save_announcements" class="btn btn-primary" style="padding: 10px 24px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Announcement Settings
                    </button>
                </div>

                <!-- Campus Network IP Info Banner -->
                <div style="background: rgba(9, 132, 227, 0.08); border: 1px solid rgba(9, 132, 227, 0.2); padding: 10px 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem;">
                    <div>
                        <i class="fa-solid fa-network-wired" style="color:#0984e3; margin-right: 6px;"></i>
                        <strong>Detected Campus Server IP:</strong> <code style="background:var(--bg-card); padding:3px 8px; border-radius:4px; font-weight:700; border:1px solid var(--border); color:#0984e3;"><?= htmlspecialchars(get_server_lan_ip()) ?></code>
                        <span style="color:var(--text-muted); margin-left:8px;">(Generated result links below use this IP so parents on campus Wi-Fi / mobile phones can access directly)</span>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">#</th>
                                <th>Exam / Test Title</th>
                                <th>Session</th>
                                <th>Marks Status</th>
                                <th style="text-align: center;">Announce on Parents Portal</th>
                                <th>Unique Result Link (Shareable)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($exams_list)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fa-solid fa-folder-open" style="font-size: 2.2rem; margin-bottom: 10px; display: block;"></i>
                                    No exams found for session <strong><?= htmlspecialchars($selected_session) ?></strong>. Go to <strong>Academic &gt; Add Exam Type</strong> to create an exam first.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($exams_list as $idx => $ex): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: var(--text-muted);"><?= $idx + 1 ?></td>
                                
                                <td>
                                    <strong style="font-size: 0.98rem; color: var(--text-primary); display: block;">
                                        <?= htmlspecialchars($ex['title']) ?>
                                    </strong>
                                    <?php if(!empty($ex['description'])): ?>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($ex['description']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span style="font-weight: 700; font-size: 0.82rem; background: var(--bg-secondary); padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border);">
                                        <?= htmlspecialchars($ex['session_year']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($ex['mark_count'] > 0): ?>
                                        <span style="color: #00b894; font-weight: 700; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; background: rgba(0,184,148,0.1); padding: 5px 12px; border-radius: 20px;">
                                            <i class="fa-solid fa-circle-check"></i> <?= $ex['mark_count'] ?> marks entered (<?= $ex['student_count'] ?> students)
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #e17055; font-weight: 700; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; background: rgba(225,112,85,0.1); padding: 5px 12px; border-radius: 20px;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> No marks entered yet
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="text-align: center;">
                                    <label class="switch">
                                        <input type="checkbox" name="announce_exam[]" value="<?= $ex['id'] ?>" 
                                            <?= $ex['is_announced'] ? 'checked' : '' ?> 
                                            <?= $ex['mark_count'] == 0 ? 'disabled' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                    
                                    <?php if ($ex['mark_count'] == 0): ?>
                                        <div style="font-size: 0.65rem; color: #e17055; margin-top: 4px; font-weight: 700;">
                                            Disabled (Enter marks first)
                                        </div>
                                    <?php elseif ($ex['is_announced']): ?>
                                        <div style="font-size: 0.65rem; color: #00b894; margin-top: 4px; font-weight: 800;">
                                            LIVE ON PORTAL
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 4px;">
                                            Not Announced
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($ex['is_announced'] && !empty($ex['campus_url'])): ?>
                                        <div class="link-input-group">
                                            <input type="text" readonly class="link-input" id="url_<?= $ex['id'] ?>" value="<?= htmlspecialchars($ex['campus_url']) ?>" data-campus="<?= htmlspecialchars($ex['campus_url']) ?>" data-local="<?= htmlspecialchars($ex['public_url']) ?>">
                                            <button type="button" class="btn-copy" onclick="copyResultUrl('url_<?= $ex['id'] ?>')">
                                                <i class="fa-solid fa-copy"></i> Copy Link
                                            </button>
                                            <a href="<?= htmlspecialchars($ex['campus_url']) ?>" target="_blank" class="btn-open" title="Preview Public Result Page">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                            </a>
                                        </div>
                                        <div style="font-size: 0.65rem; color: #0984e3; margin-top: 3px; font-weight: 700;">
                                            <i class="fa-solid fa-wifi"></i> Campus LAN IP Link (Ready for Parents)
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.82rem; font-style: italic;">
                                            <i class="fa-solid fa-lock" style="margin-right: 4px;"></i> Link will be activated upon announcement
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($exams_list)): ?>
                <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
                    <button type="submit" name="save_announcements" class="btn btn-primary" style="padding: 12px 30px; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Announcement Settings
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Toast Popup Notification -->
<div id="toastPopup" class="toast-popup">
    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
    <span>Result link copied to clipboard! Share it with parents.</span>
</div>

<script>
function copyResultUrl(inputId) {
    var inputEl = document.getElementById(inputId);
    if (!inputEl) return;
    
    inputEl.select();
    inputEl.setSelectionRange(0, 99999); // Mobile
    
    navigator.clipboard.writeText(inputEl.value).then(function() {
        showToast("Result link copied to clipboard!");
    }).catch(function() {
        // Fallback for older browsers
        document.execCommand("copy");
        showToast("Result link copied to clipboard!");
    });
}

function showToast(message) {
    var toast = document.getElementById("toastPopup");
    toast.querySelector("span").innerText = message;
    toast.style.display = "flex";
    setTimeout(function() {
        toast.style.display = "none";
    }, 3500);
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
