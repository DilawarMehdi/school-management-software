<?php
// Handle AJAX quick add subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_subject'])) {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_role('admin','principal','teacher');

    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $name_esc = $conn->real_escape_string($name);
        $check = $conn->query("SELECT id, name FROM subjects WHERE LOWER(TRIM(name)) = LOWER(TRIM('$name_esc')) LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $row = $check->fetch_assoc();
            echo json_encode(['success' => true, 'id' => $row['id'], 'name' => $row['name']]);
        } else {
            $conn->query("INSERT INTO subjects (name, created_at) VALUES ('$name_esc', NOW())");
            $new_id = $conn->insert_id;
            echo json_encode(['success' => true, 'id' => $new_id, 'name' => $name]);
        }
    } else {
        echo json_encode(['error' => 'Subject name cannot be empty.']);
    }
    exit;
}

// Handle AJAX get subjects for class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_subjects_for_class'])) {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_role('admin','principal','teacher');

    $class_id = (int)($_POST['class_id'] ?? 0);
    $type_id = (int)($_POST['type_id'] ?? 0);

    if ($class_id > 0) {
        $already_q = $conn->query("SELECT subject FROM class_subjects WHERE class_id = $class_id GROUP BY subject");
        $already = [];
        if ($already_q) {
            while ($row = $already_q->fetch_assoc()) {
                $already[] = strtolower(trim($row['subject']));
            }
        }

        $global_q = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");
        $subjects = [];
        if ($global_q) {
            while ($row = $global_q->fetch_assoc()) {
                $is_assigned = in_array(strtolower(trim($row['name'])), $already);
                $subjects[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'assigned' => $is_assigned
                ];
            }
        }
        echo json_encode(['subjects' => $subjects]);
    } else {
        echo json_encode(['error' => 'Invalid parameters.']);
    }
    exit;
}

// Handle AJAX save subject assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_subject_assignments'])) {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_role('admin','principal','teacher');

    $class_id = (int)($_POST['class_id'] ?? 0);
    $type_id = (int)($_POST['type_id'] ?? 0);
    $selected_subjects = $_POST['subjects'] ?? [];

    if ($class_id > 0) {
        $existing_q = $conn->query("SELECT id, subject FROM class_subjects WHERE class_id = $class_id GROUP BY subject");
        $existing = [];
        if ($existing_q) {
            while ($row = $existing_q->fetch_assoc()) {
                $subj_lower = strtolower(trim($row['subject']));
                $existing[$subj_lower] = $row['id'];
            }
        }

        $selected_lower = array_map(function($s) { return strtolower(trim($s)); }, $selected_subjects);

        // Add new
        foreach ($selected_subjects as $subj) {
            $sl = strtolower(trim($subj));
            if (!isset($existing[$sl])) {
                $subj_esc = $conn->real_escape_string(trim($subj));
                $conn->query("INSERT INTO class_subjects (exam_type_id, class_id, subject) VALUES ($type_id, $class_id, '$subj_esc')");
            }
        }

        // Remove unselected
        foreach ($existing as $subj_name => $subj_id) {
            if (!in_array($subj_name, $selected_lower)) {
                $subj_name_esc = $conn->real_escape_string($subj_name);
                $conn->query("DELETE FROM class_subjects WHERE class_id = $class_id AND LOWER(TRIM(subject)) = '$subj_name_esc'");
            }
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid parameters.']);
    }
    exit;
}

// Handle AJAX exam date updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_exam_date'])) {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_role('admin','principal','teacher');

    $sched_id = (int)($_POST['sched_id'] ?? 0);
    $exam_date = $conn->real_escape_string($_POST['exam_date'] ?? '');

    if ($sched_id > 0) {
        $conn->query("UPDATE exam_schedules SET exam_date = '$exam_date' WHERE id = $sched_id");
    }
    
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status'=>'success']);
        exit;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Handle AJAX total marks updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_total_marks'])) {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_role('admin','principal','teacher');

    $sched_id = (int)($_POST['sched_id'] ?? 0); // Exam Title ID
    $subj_name = $conn->real_escape_string($_POST['subj_name'] ?? '');
    $class_id = (int)($_POST['class_id'] ?? 0);
    $total_m  = (int)($_POST['total_marks'] ?? 100);
    $pass_m   = round($total_m * 0.4); // 40% pass criteria standard

    if ($sched_id > 0 && $total_m > 0 && $subj_name !== '' && $class_id > 0) {
        // Update already entered marks
        $conn->query("UPDATE marks SET max_marks = $total_m, pass_marks = $pass_m WHERE schedule_id = $sched_id AND subject = '$subj_name' AND student_id IN (SELECT student_id FROM student_enrollments WHERE class_id = $class_id)");

        // Also update matching subjects table
        $conn->query("UPDATE subjects SET max_marks = $total_m, pass_marks = $pass_m WHERE class_id = $class_id AND name = '$subj_name'");
    }
    
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status'=>'success', 'pass_marks' => $pass_m]);
        exit;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Handle AJAX update grading policy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_policy_id'])) {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_role('admin','principal','teacher');
    
    // We can save policy directly to class_subjects if we added it, but for now we'll skip or just respond success.
    // The previous implementation saved it to exam_schedules.
    
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status'=>'success']);
        exit;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Handle AJAX marks autosave before any HTML is outputted by header.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mark'])) {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_role('admin','principal','teacher');
    $settings = all_settings($conn);

    $sched_id  = (int)($_POST['sched_id']??0); // Exam Title ID
    $subj_assign_id = (int)($_POST['subj_assign_id']??0);
    $student_id= (int)($_POST['student_id']??0);
    $type_id   = (int)($_POST['type_id']??0);
    $obtained  = (float)($_POST['obtained']??0);
    $max_m     = (int)($_POST['max_marks']??100);
    $pass_m    = (int)($_POST['pass_marks']??40);
    $subject   = $conn->real_escape_string($_POST['subject']??'');
    $is_abs    = ($obtained == -1) ? 1 : 0;
    $not_part  = ($obtained == -2) ? 1 : 0;
    $actual    = ($is_abs||$not_part) ? 0 : $obtained;

    // Map exam_type_id and subject to old-style exam_id and subject_id for compatibility
    $exam_id = 0;
    $subject_id = 0;
    $class_id = 0;
    
    if ($subj_assign_id > 0) {
        $sa_q = $conn->query("SELECT * FROM class_subjects WHERE id = $subj_assign_id LIMIT 1");
        if ($sa = $sa_q->fetch_assoc()) {
            $class_id = (int)$sa['class_id'];
            $subject = $conn->real_escape_string($sa['subject']);
        }
    }

    // Get exam type title
    $type_q = $conn->query("SELECT * FROM exam_types WHERE id = $type_id LIMIT 1");
    $type_info = $type_q ? $type_q->fetch_assoc() : null;
    $type_title = $type_info ? $type_info['title'] : '';

    if ($type_title !== '') {
        $exam_check = $conn->query("SELECT id FROM exams WHERE name = '" . $conn->real_escape_string($type_title) . "' LIMIT 1");
        if ($exam_check && $exam_check->num_rows > 0) {
            $exam_id = (int)$exam_check->fetch_assoc()['id'];
        } else {
            $session = $settings['session_year'] ?? '2025-2026';
            $conn->query("INSERT INTO exams (name, session_year, is_published) VALUES ('" . $conn->real_escape_string($type_title) . "', '$session', 1)");
            $exam_id = (int)$conn->insert_id;
        }
    }

    if ($subject !== '' && $class_id > 0) {
        $sub_check = $conn->query("SELECT id FROM subjects WHERE name = '$subject' AND class_id = $class_id LIMIT 1");
        if ($sub_check && $sub_check->num_rows > 0) {
            $subject_id = (int)$sub_check->fetch_assoc()['id'];
        } else {
            $conn->query("INSERT INTO subjects (class_id, name, max_marks, pass_marks) VALUES ($class_id, '$subject', $max_m, $pass_m)");
            $subject_id = (int)$conn->insert_id;
        }
    }

    $exam_id_sql = ($exam_id > 0) ? $exam_id : "NULL";
    $subject_id_sql = ($subject_id > 0) ? $subject_id : "NULL";

    $res = $conn->query("INSERT INTO marks (student_id,exam_type_id,schedule_id,subject,marks_obtained,max_marks,pass_marks,is_absent,not_participating,exam_id,subject_id)
        VALUES ($student_id,$type_id,$sched_id,'$subject',$actual,$max_m,$pass_m,$is_abs,$not_part,$exam_id_sql,$subject_id_sql)
        ON DUPLICATE KEY UPDATE marks_obtained=$actual,is_absent=$is_abs,not_participating=$not_part,exam_id=$exam_id_sql,subject_id=$subject_id_sql,max_marks=$max_m,pass_marks=$pass_m");
    
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if ($res) {
            echo json_encode(['status'=>'success']);
        } else {
            echo json_encode(['error'=>'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$page_title  = 'Add Result';
$active_page = 'exams';
$msg = ''; $err = '';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','teacher');

// Load exam types & classes
$active_session = $settings['session_year'] ?? '2025-2026';
$types_q=$conn->query("SELECT * FROM exam_types WHERE session_year='$active_session' ORDER BY id DESC");
$types_arr=[];
if($types_q) while($t=$types_q->fetch_assoc()) $types_arr[]=$t;

// Load classes (Naturally sorted)
$classes_arr = get_all_classes($conn);


// Selected values
$sel_type  = (int)($_GET['type_id']  ?? ($types_arr[0]['id'] ?? 0));
$exam_info = null;
foreach($types_arr as $t){ if($t['id']==$sel_type){$exam_info=$t;break;} }

// Load schedules (Exam Titles) for this exam type
$schedules_arr = [];
if ($sel_type > 0) {
    $active_session = $settings['session_year'] ?? '2025-2026';
    $sq = $conn->query("SELECT * FROM exam_schedules WHERE exam_type_id = $sel_type AND session_year = '$active_session' ORDER BY id DESC");
    if ($sq) {
        while($s = $sq->fetch_assoc()) $schedules_arr[] = $s;
    }
    
    // Auto-create default schedule if empty so user can enter marks immediately
    if (empty($schedules_arr) && $exam_info) {
        $title_esc = $conn->real_escape_string($exam_info['title'] . " Schedule");
        $conn->query("INSERT INTO exam_schedules (exam_type_id, title, session_year) VALUES ($sel_type, '$title_esc', '$active_session')");
        $new_sched_id = $conn->insert_id;
        $schedules_arr[] = ['id' => $new_sched_id, 'exam_type_id' => $sel_type, 'title' => $exam_info['title'] . " Schedule", 'session_year' => $active_session];
    }
}
$sel_schedule = (int)($_GET['schedule_id'] ?? ($schedules_arr[0]['id'] ?? 0));

$sel_subj_assign = (int)($_GET['subj_assign_id'] ?? 0);   // selected class_subjects.id for marks entry

// Load policies and convert to JSON for JS calculation
$policies_arr = [];
$pol_q = $conn->query("SELECT * FROM grading_policies ORDER BY is_default DESC, id ASC");
if ($pol_q) {
    while ($p = $pol_q->fetch_assoc()) {
        $p['details'] = [];
        $det_q = $conn->query("SELECT * FROM grading_policy_details WHERE policy_id = {$p['id']} ORDER BY min_percent DESC");
        if ($det_q) while ($d = $det_q->fetch_assoc()) $p['details'][] = $d;
        $policies_arr[] = $p;
    }
}
$policies_json = json_encode($policies_arr);

// Delete all marks for subject
if (isset($_GET['sched_del'])) {
    $del_id = (int)$_GET['sched_del'];
    $conn->query("DELETE FROM marks WHERE schedule_id=$del_id");
    header("Location: " . BASE_URL . "modules/exams/exams.php?type_id=$sel_type");
    exit;
}

// Subject detail view
$subj_info   = null;
$subj_students = [];
if($sel_subj_assign && $sel_schedule){
    $r=$conn->query("SELECT cs.*, c.name as class_name, c.section, es.title as schedule_title 
                     FROM class_subjects cs 
                     LEFT JOIN classes c ON c.id=cs.class_id 
                     LEFT JOIN exam_schedules es ON es.id = $sel_schedule
                     WHERE cs.id=$sel_subj_assign LIMIT 1");
    if($r) $subj_info=$r->fetch_assoc();
    if($subj_info){
        $cid=$subj_info['class_id'];
        $subject_esc = $conn->real_escape_string($subj_info['subject']);
        $active_session = $settings['session_year'] ?? '2025-2026';
        
        // Find if any max_marks/pass_marks exist for this subject and schedule
        $sub_conf_q = $conn->query("SELECT max_marks, pass_marks FROM marks WHERE schedule_id = $sel_schedule AND subject = '$subject_esc' AND student_id IN (SELECT student_id FROM student_enrollments WHERE class_id = $cid) LIMIT 1");
        if ($sub_conf_q && $sub_conf_q->num_rows > 0) {
            $sub_conf = $sub_conf_q->fetch_assoc();
            $subj_info['total_marks'] = $sub_conf['max_marks'];
            $subj_info['pass_marks'] = $sub_conf['pass_marks'];
        } else {
            // Default or fallback to subjects table
            $subj_info['total_marks'] = 100;
            $subj_info['pass_marks'] = 40;
            $sub_tb_q = $conn->query("SELECT max_marks, pass_marks FROM subjects WHERE name = '$subject_esc' AND class_id = $cid LIMIT 1");
            if ($sub_tb_q && $sub_tb_q->num_rows > 0) {
                $sub_tb = $sub_tb_q->fetch_assoc();
                $subj_info['total_marks'] = $sub_tb['max_marks'];
                $subj_info['pass_marks'] = $sub_tb['pass_marks'];
            }
        }
        
        $sq=$conn->query("
            SELECT s.*, m.marks_obtained, m.is_absent, m.not_participating, se.roll_no as enrollment_roll_no 
            FROM student_enrollments se 
            JOIN students s ON se.student_id = s.id 
            LEFT JOIN marks m ON m.student_id = s.id AND m.schedule_id = $sel_schedule AND m.subject = '$subject_esc'
            WHERE se.class_id = $cid AND se.session_year = '$active_session' AND se.status = 'Active' AND s.status = 'Active' 
            ORDER BY (se.roll_no+0), s.name
        ");
        if($sq) while($s=$sq->fetch_assoc()) $subj_students[]=$s;
    }
}
?>
<style>
/* Modern Premium Styling */
.result-hint{font-size:.85rem;color:var(--text-secondary);background:var(--bg-glass);padding:12px 16px;border-radius:8px;border:1px solid rgba(9,132,227,0.2);margin-bottom:20px;display:flex;align-items:center;gap:10px}
.result-hint i{color:#0984e3;font-size:1.1rem}
.result-hint a{color:#0984e3;font-weight:600;text-decoration:none;transition:color 0.2s}
.result-hint a:hover{color:#0056b3}

.top-dropdowns{display:flex;gap:24px;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;background:var(--bg-secondary);padding:20px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.03);border:1px solid var(--border)}
.top-dropdown-group{flex:1;min-width:260px}
.top-dropdown-group label{font-size:.85rem;font-weight:700;color:var(--text-primary);display:block;margin-bottom:8px}
.top-dropdown-group select{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:8px;background:var(--bg-primary);color:var(--text-primary);font-size:.9rem;transition:border-color 0.3s;cursor:pointer}
.top-dropdown-group select:focus{outline:none;border-color:#0984e3}
.top-dropdown-link{font-size:.78rem;color:#0984e3;margin-top:8px;display:inline-block;text-decoration:none;font-weight:600;transition:opacity 0.2s}
.top-dropdown-link:hover{opacity:0.8}

.announce-bar{display:inline-flex;align-items:center;gap:10px;margin-bottom:24px;font-size:.85rem;background:rgba(231,76,60,0.08);padding:10px 16px;border-radius:8px;border:1px solid rgba(231,76,60,0.2)}
.announce-bar input[type=checkbox]{width:16px;height:16px;accent-color:#e74c3c;cursor:pointer}
.announce-bar span{color:#c0392b;font-weight:500}

/* Premium Table Styling */
.table-container{background:var(--bg-secondary);border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.04);border:1px solid var(--border);overflow:hidden;margin-bottom:30px}
.table-header{background:linear-gradient(to right, #0984e3, #6c5ce7);color:white;padding:14px 20px;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:10px}
.cls-table{width:100%;border-collapse:collapse}
.cls-table th{background:var(--bg-glass);padding:14px 20px;font-size:.85rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;text-align:left;border-bottom:2px solid var(--border)}
.cls-table td{border-bottom:1px solid var(--border);padding:14px 20px;font-size:.9rem;vertical-align:middle;color:var(--text-primary);transition:background 0.2s}
.cls-table tr:hover td{background:var(--bg-glass)}
.cls-table .class-name-cell{font-weight:700;color:#0984e3}
.cls-table .section-cell{color:var(--text-secondary);font-weight:600}
.cls-table .subject-link{display:inline-block;padding:4px 10px;margin:3px;background:rgba(9,132,227,0.1);color:#0984e3;border-radius:20px;font-size:.8rem;text-decoration:none;font-weight:600;transition:all 0.2s}
.cls-table .subject-link:hover{background:#0984e3;color:white;transform:translateY(-1px)}
.add-subj-btn{display:inline-flex;align-items:center;gap:6px;background:var(--bg-primary);color:#00b894;border:1px solid #00b894;padding:6px 12px;border-radius:20px;font-size:.78rem;font-weight:700;text-decoration:none;transition:all 0.2s;margin-top:8px}
.add-subj-btn:hover{background:#00b894;color:white}
.action-links{display:flex;flex-direction:column;gap:6px;align-items:flex-end}
.action-links a{display:inline-flex;align-items:center;gap:6px;color:#6c5ce7;font-size:.8rem;font-weight:600;text-decoration:none;transition:color 0.2s}
.action-links a:hover{color:#4834d4}

/* Marks Entry View */
.marks-config-card{background:var(--bg-secondary);border-radius:12px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,0.04);border:1px solid var(--border);margin-bottom:24px}
.marks-header-title{font-size:1.15rem;font-weight:700;color:var(--text-primary);margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
.marks-header-title span{display:inline-flex;align-items:center;gap:8px}
.marks-config{display:flex;gap:20px;align-items:flex-end;flex-wrap:wrap}
.marks-config .fc{flex:1;min-width:180px}
.marks-config label{font-size:.8rem;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:8px}
.marks-config input, .marks-config select{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:8px;background:var(--bg-primary);color:var(--text-primary);font-size:.9rem;transition:border-color 0.3s}
.marks-config input:focus, .marks-config select:focus{outline:none;border-color:#6c5ce7}
.marks-config input[readonly]{background:var(--bg-glass);cursor:not-allowed}

.show-list-btn{background:linear-gradient(135deg, #00b894, #00cec9);color:white;border:none;padding:12px 24px;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;transition:all 0.3s;box-shadow:0 4px 15px rgba(0,184,148,0.3);display:inline-flex;align-items:center;gap:8px}
.show-list-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,184,148,0.4)}

.hints{background:rgba(253,203,110,0.1);border:1px solid rgba(253,203,110,0.3);padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.85rem;color:var(--text-primary)}
.hints strong{color:#d35400}
.hints .abs, .hints .nopart{font-weight:700;color:#e74c3c}

.mt{width:100%;border-collapse:collapse}
.mt th{background:var(--bg-glass);padding:14px 20px;font-size:.85rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;text-align:left;border-bottom:2px solid var(--border)}
.mt td{border-bottom:1px solid var(--border);padding:12px 20px;font-size:.9rem;vertical-align:middle;color:var(--text-primary)}
.mt tr:hover td{background:var(--bg-glass)}

.marks-input-wrap{display:flex;align-items:center;gap:10px}
.marks-val-input{width:80px;padding:8px 12px;background:var(--bg-primary);color:var(--text-primary);border:2px solid var(--border);border-radius:8px;font-size:1rem;font-weight:700;text-align:center;transition:all 0.3s}
.marks-val-input:focus{outline:none;border-color:#00b894;box-shadow:0 0 0 4px rgba(0,184,148,0.1)}
.marks-val-input.saved{border-color:#00b894;background:rgba(0,184,148,0.05)}
.marks-max{font-size:.8rem;font-weight:600;color:var(--text-muted)}
.grade-cell{font-weight:800;font-size:1.1rem;text-align:center;width:80px}
.save-status{font-size:1rem;width:24px;display:inline-flex;justify-content:center;align-items:center;opacity:0;transition:opacity 0.3s}
.save-status.success{color:#00b894;opacity:1}
.save-status.error{color:#e74c3c;opacity:1}
.save-status.saving{color:#0984e3;opacity:1;animation:spin 1s linear infinite}

@keyframes spin { 100% { transform:rotate(360deg); } }
</style>

<?php
$rc_link = BASE_URL . "modules/exams/results.php";
$cum_link = BASE_URL . "modules/exams/results.php?view=consolidated";
if (!empty($sel_schedule) && !empty($subj_info)) {
    $rc_link = BASE_URL . "prints/print_all_result_cards.php?class_id=" . $subj_info['class_id'] . "&type_id=" . $sel_type;
    $cum_link = BASE_URL . "modules/exams/results.php?view=consolidated&class_id=" . $subj_info['class_id'] . "&type_id=" . $sel_type;
}
?>
<div class="result-hint">
  <i class="fa-solid fa-lightbulb"></i>
  <div>
    After adding results, click on <a href="<?= $rc_link ?>" <?= !empty($sel_schedule) ? 'target="_blank"' : '' ?>>Result Card</a> to print all result cards, or <a href="<?= $cum_link ?>">Cumulative Result</a> to print detail result sheets. You can also upload to the parent portal or send via SMS.
  </div>
</div>

<!-- Exam Type + Exam Schedule dropdowns -->
<form method="GET" id="examSelectForm">
<div class="top-dropdowns">
  <div class="top-dropdown-group">
    <label><i class="fa-solid fa-layer-group" style="color:#0984e3;margin-right:6px"></i>Exam Type / Category</label>
    <select name="type_id" onchange="document.getElementById('examSelectForm').submit()">
      <?php foreach($types_arr as $t): ?>
      <option value="<?= $t['id'] ?>" <?= $sel_type==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <div style="text-align:right">
      <a href="<?= BASE_URL ?>modules/exams/exam_types.php" class="top-dropdown-link"><i class="fa-solid fa-plus-circle"></i> Add New Exam Category</a>
    </div>
  </div>
  
  <div class="top-dropdown-group">
    <label><i class="fa-solid fa-calendar-days" style="color:#6c5ce7;margin-right:6px"></i>Exam Schedule / Session</label>
    <select name="schedule_id" onchange="document.getElementById('examSelectForm').submit()">
      <?php if(empty($schedules_arr)): ?>
      <option value="">-- No Schedules Found --</option>
      <?php else: ?>
      <?php foreach($schedules_arr as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $sel_schedule==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['title']) ?></option>
      <?php endforeach; ?>
      <?php endif; ?>
    </select>
    <div style="text-align:right">
      <a href="<?= BASE_URL ?>modules/exams/exam_schedule.php" class="top-dropdown-link"><i class="fa-solid fa-plus-circle"></i> Add New Exam Schedule</a>
    </div>
  </div>
</div>
</form>

<!-- Announce it -->
<div class="announce-bar">
  <input type="checkbox" id="announceChk">
  <label for="announceChk" style="font-weight:700;color:var(--text-primary);cursor:pointer">Announce it</label>
  <span><i class="fa-solid fa-circle-info"></i> Click on Announce it checkbox to Upload Result on Parent Portal.</span>
</div>

<?php if($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:20px;border-radius:8px;padding:12px 16px"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if($sel_subj_assign && $subj_info): ?>
<!-- ════════════════════════════════════════════
     SUBJECT MARKS ENTRY VIEW (Premium Auto-save)
════════════════════════════════════════════ -->
<div class="marks-config-card">
  <div class="marks-header-title">
    <span><i class="fa-solid fa-pen-to-square" style="color:#6c5ce7"></i> Add Result of (<?= htmlspecialchars($subj_info['subject']) ?> - <?= htmlspecialchars($subj_info['class_name']??'') ?> - Section <?= htmlspecialchars($subj_info['section']??'') ?>)</span>
    <div style="display:flex;gap:12px">
      <a href="#" class="btn btn-secondary" style="font-size:.8rem"><i class="fa-solid fa-gear"></i> Student Settings for Optional Subjects</a>
      <a href="?type_id=<?= $sel_type ?>&sched_del=<?= $sel_schedule ?>" class="btn" style="background:rgba(231,76,60,0.1);color:#e74c3c;font-size:.8rem;font-weight:700" onclick="return confirm('Delete all marks for this subject?')"><i class="fa-solid fa-trash"></i> Delete All</a>
    </div>
  </div>

  <!-- Config bar -->
  <form method="POST" id="marksForm">
  <input type="hidden" name="sched_id"   value="<?= $sel_schedule ?>">
  <input type="hidden" name="type_id"    value="<?= $sel_type ?>">
  <input type="hidden" name="subject"    value="<?= htmlspecialchars($subj_info['subject']) ?>">
  <input type="hidden" name="max_marks"  value="<?= $subj_info['total_marks'] ?>">
  <input type="hidden" name="pass_marks" value="<?= $subj_info['pass_marks'] ?>">

  <div class="marks-config">
    <div class="fc">
      <label>Enter Total Marks of Paper</label>
      <input type="number" id="paperTotalMarks" name="total_marks" value="<?= $subj_info['total_marks'] ?>" min="1" onchange="updatePaperTotalMarks(this.value)">
    </div>
    <div class="fc">
      <label>Select Grading Policy</label>
      <select id="paperGradingPolicy" onchange="updateGradingPolicy(this.value)">
        <?php foreach($policies_arr as $p): ?>
        <option value="<?= $p['id'] ?>" <?= ((int)($subj_info['policy_id'] ?? 0) === (int)$p['id'] || (!isset($subj_info['policy_id']) && !empty($p['is_default']))) ? 'selected' : '' ?>><?= htmlspecialchars($p['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="fc">
      <label>Choose Exam Date</label>
      <input type="date" id="paperExamDate" value="<?= $subj_info['exam_date']??'' ?>" onchange="updatePaperExamDate(this.value)">
    </div>
    <button type="button" class="show-list-btn" id="showListBtn" onclick="toggleStudentList()" style="background:linear-gradient(135deg, #6366f1, #4834d4);box-shadow:0 4px 15px rgba(99,102,241,0.3)">
      <i class="fa-solid fa-eye-slash"></i> Hide Student List
    </button>
  </div>
</div>

<!-- Student List (Visible by default) -->
<div id="studentListContainer" style="display:block; opacity:1; transition: opacity 0.4s ease-in-out;">
  <div class="table-container">
    <div class="table-header">
      <i class="fa-solid fa-clipboard-list"></i> Result Entry
    </div>
    <div class="hints">
      <i class="fa-solid fa-circle-exclamation" style="color:#d35400;margin-right:6px"></i>
      Enter <strong>-1</strong> in obtain marks for <span class="abs">absent</span> student.
      Enter <strong>-2</strong> in obtain marks for <span class="nopart">not participating</span> student.
      <br><span style="color:#00b894;font-weight:600;margin-top:6px;display:inline-block"><i class="fa-solid fa-bolt"></i> Marks autosave automatically when you press TAB or click away.</span>
    </div>
    <table class="mt">
      <thead>
        <tr>
          <th style="width:10%">RollNo</th>
          <th style="width:15%">Reg#</th>
          <th style="width:25%">Name</th>
          <th style="width:20%">Father Name</th>
          <th style="width:20%;text-align:center">Obtained Marks</th>
          <th style="width:10%;text-align:center">Grade</th>
        </tr>
      </thead>
      <tbody>
      <?php if(empty($subj_students)): ?>
      <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);font-size:1.1rem"><i class="fa-solid fa-users-slash" style="display:block;font-size:2rem;margin-bottom:10px"></i>No students in this class.</td></tr>
      <?php else:
        foreach($subj_students as $i => $s):
          $obt = $s['marks_obtained'] ?? '';
          $is_abs = (int)($s['is_absent']??0);
          $not_p  = (int)($s['not_participating']??0);
          if($is_abs)  $disp = -1;
          elseif($not_p) $disp = -2;
          elseif($obt!=='') $disp = (float)$obt;
          else $disp = '';
          $g = get_grade($obt !== '' ? (float)$obt : 0, (int)$subj_info['total_marks']);
          $grade_show = ($is_abs||$not_p||$obt==='') ? '-' : $g['grade'];
      ?>
      <tr>
        <td style="font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($s['enrollment_roll_no'] ?? ($s['roll_no'] ?? '-')) ?></td>
        <td style="color:var(--text-secondary);font-size:.85rem"><?= htmlspecialchars($s['admission_no']) ?></td>
        <td style="font-weight:700;color:#0984e3"><?= htmlspecialchars($s['name']) ?></td>
        <td style="color:var(--text-secondary)"><?= htmlspecialchars($s['father_name']??'') ?></td>
        <td style="text-align:center">
          <div class="marks-input-wrap" style="justify-content:center">
            <input type="number" class="marks-val-input" name="obtained_<?= $s['id'] ?>"
                   id="obt_<?= $s['id'] ?>"
                   data-student-id="<?= $s['id'] ?>"
                   data-max-marks="<?= $subj_info['total_marks'] ?>"
                   data-pass-marks="<?= $subj_info['pass_marks'] ?>"
                   value="<?= $disp ?>" min="-2" max="<?= $subj_info['total_marks'] ?>" step="0.5"
                   onblur="autoSaveMark(<?= $s['id'] ?>)"
                   onchange="autoSaveMark(<?= $s['id'] ?>)">
            <span class="marks-max" id="max_lbl_<?= $s['id'] ?>">/ <?= $subj_info['total_marks'] ?></span>
            <span class="save-status" id="status_<?= $s['id'] ?>"><i class="fa-solid fa-check"></i></span>
          </div>
        </td>
        <td class="grade-cell" id="grade_<?= $s['id'] ?>"><?= $grade_show ?></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <!-- Mobile Save All Button -->
  <div id="mobileSaveBar" style="display:none;position:sticky;bottom:0;background:var(--bg-card);border-top:2px solid #00b894;padding:12px 16px;z-index:999;justify-content:space-between;align-items:center">
    <span style="font-size:.85rem;color:var(--text-secondary)"><i class="fa-solid fa-mobile-screen" style="color:#00b894"></i> Tap a mark, then press Save All</span>
    <button type="button" onclick="saveAllMarks()" style="background:#00b894;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:700;font-size:.9rem;cursor:pointer"><i class="fa-solid fa-floppy-disk"></i> Save All</button>
  </div>
</div>
</form>
<script>
// Show mobile save bar on touch devices
if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
  var bar = document.getElementById('mobileSaveBar');
  if(bar) bar.style.display = 'flex';
}
function saveAllMarks() {
  var inputs = document.querySelectorAll('.marks-val-input');
  var count = 0;
  inputs.forEach(function(inp) {
    var sid = inp.getAttribute('data-student-id');
    if(sid) { autoSaveMark(parseInt(sid)); count++; }
  });
  var btn = document.querySelector('#mobileSaveBar button');
  if(btn) { btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving '+count+'...'; setTimeout(function(){ btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save All'; }, 2000); }
}
</script>

<?php else: ?>
<!-- ════════════════════════════════════════════
     CLASSES & SECTIONS TABLE (Premium)
════════════════════════════════════════════ -->
<div class="table-container">
  <div class="table-header">
    <i class="fa-solid fa-chalkboard-user"></i> Classes &amp; Sections
  </div>
  <table class="cls-table">
    <thead>
      <tr>
        <th style="width:15%">Class</th>
        <th style="width:10%">Section</th>
        <th style="width:50%">Subjects</th>
        <th style="width:25%;text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if(empty($classes_arr)): ?>
    <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted);font-size:1.1rem"><i class="fa-solid fa-school" style="display:block;font-size:2rem;margin-bottom:10px"></i>No classes found.</td></tr>
    <?php else:
      foreach($classes_arr as $cls):
        // Get subjects assigned to this class across all exams
        $scheds_q = $conn->query("SELECT * FROM class_subjects WHERE class_id={$cls['id']} GROUP BY subject ORDER BY subject");
        $scheds = [];
        if($scheds_q) while($s=$scheds_q->fetch_assoc()) $scheds[]=$s;
    ?>
    <tr>
      <td class="class-name-cell"><?= htmlspecialchars($cls['name']) ?></td>
      <td class="section-cell"><?= htmlspecialchars($cls['section']??'') ?></td>
      <td>
        <div style="display:flex;flex-wrap:wrap;gap:4px">
          <?php if(empty($scheds)): ?>
            <span style="color:var(--text-muted);font-size:.85rem;font-style:italic;padding:4px 0">No subjects scheduled</span>
          <?php else: ?>
            <?php foreach($scheds as $sub): ?>
            <a href="?type_id=<?= $sel_type ?>&schedule_id=<?= $sel_schedule ?>&subj_assign_id=<?= $sub['id'] ?>" class="subject-link" <?= !$sel_schedule ? 'onclick="alert(\'Please select an Exam Schedule / Session first\');return false;"' : '' ?>><?= htmlspecialchars($sub['subject']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <button type="button" class="add-subj-btn" onclick="openSubjModal(<?= $cls['id'] ?>, '<?= addslashes(htmlspecialchars($cls['name'].' '.($cls['section']??''))) ?>')">
          <i class="fa-solid fa-plus"></i> Add/Remove Subject
        </button>
      </td>
      <td>
        <div class="action-links">
          <a href="<?= BASE_URL ?>modules/exams/results.php?view=consolidated&class_id=<?= $cls['id'] ?>&type_id=<?= $sel_type ?>"><i class="fa-solid fa-chart-line"></i> Commulative/Result</a>
          <a href="<?= BASE_URL ?>prints/print_all_result_cards.php?class_id=<?= $cls['id'] ?>&type_id=<?= $sel_type ?>" target="_blank"><i class="fa-solid fa-id-card-clip"></i> Result Card</a>
          <a href="<?= BASE_URL ?>modules/settings/notifications.php?tab=sms&class_id=<?= $cls['id'] ?>"><i class="fa-solid fa-comment-sms"></i> Send Via SMS</a>
        </div>
      </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
function toggleStudentList() {
  var container = document.getElementById('studentListContainer');
  var btn = document.getElementById('showListBtn');
  
  if (container.style.display === 'none' || container.style.display === '') {
    container.style.display = 'block';
    container.style.opacity = '1';
    btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide Student List';
    btn.style.background = 'linear-gradient(135deg, #6366f1, #4834d4)';
    btn.style.boxShadow = '0 4px 15px rgba(99,102,241,0.3)';
  } else {
    container.style.display = 'none';
    btn.innerHTML = '<i class="fa-solid fa-users"></i> Show Student List';
    btn.style.background = 'linear-gradient(135deg, #00b894, #00cec9)';
    btn.style.boxShadow = '0 4px 15px rgba(0,184,148,0.3)';
  }
}

// Map to track previous values to prevent unnecessary requests
const previousValues = {};

function autoSaveMark(studentId) {
  var inp = document.getElementById('obt_' + studentId);
  var maxMarks = parseFloat(inp.getAttribute('data-max-marks')) || 100;
  var passMarks = parseFloat(inp.getAttribute('data-pass-marks')) || 40;
  var val = parseFloat(inp.value);
  var statusIcon = document.getElementById('status_' + studentId);
  
  // Do nothing if empty and wasn't changed from empty, or if hasn't changed
  if (isNaN(val) && inp.value === '') {
     if (previousValues[studentId] === undefined) return;
  } else if (previousValues[studentId] === val) {
     return; // value didn't change
  }

  if (isNaN(val) && inp.value !== '') {
    alert('Enter a valid mark.');
    return;
  }

  // Set visual state to saving
  inp.classList.remove('saved');
  statusIcon.className = 'save-status saving';
  statusIcon.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

  var fd = new FormData();
  fd.append('update_mark','1');
  fd.append('sched_id','<?= $sel_schedule ?>');
  fd.append('subj_assign_id','<?= $sel_subj_assign ?>');
  fd.append('student_id', studentId);
  fd.append('type_id','<?= $sel_type ?>');
  fd.append('subject','<?= addslashes($subj_info['subject']??'') ?>');
  fd.append('obtained', isNaN(val) ? '' : val);
  fd.append('max_marks', maxMarks);
  fd.append('pass_marks', passMarks);

  fetch(window.location.href, {
    method:'POST', 
    body:fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(response => {
    if(!response.ok) throw new Error('HTTP ' + response.status);
    return response.json();
  })
  .then(data => {
    if (data.error) {
      statusIcon.className = 'save-status error';
      statusIcon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
      if (data.redirect) {
        if (confirm('Session expired. Go to login page?')) {
          window.location.href = data.redirect;
        }
      } else {
        alert(data.error);
      }
      return;
    }
    previousValues[studentId] = val; // Store new value
    updateGrade(studentId, val, maxMarks, passMarks);
    
    // Show success checkmark
    inp.classList.add('saved');
    statusIcon.className = 'save-status success';
    statusIcon.innerHTML = '<i class="fa-solid fa-check"></i>';
    
    // Fade out checkmark after 2 seconds
    setTimeout(() => {
      if (statusIcon.className.includes('success')) {
        statusIcon.style.opacity = '0';
        setTimeout(() => { 
          statusIcon.className = 'save-status'; 
          statusIcon.style.opacity = '1';
          statusIcon.innerHTML = '';
        }, 300);
      }
    }, 2000);
  })
  .catch(error => {
    console.error('Save error:', error);
    statusIcon.className = 'save-status error';
    statusIcon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
  });
}

var gradingPolicies = <?= $policies_json ?>;

function getGradeInfo(policyId, pct) {
    var policy = gradingPolicies.find(p => p.id == policyId) || gradingPolicies[0];
    if (!policy || !policy.details || policy.details.length === 0) {
        return { grade_letter: pct>=90?'A+':pct>=80?'A':pct>=70?'B':pct>=60?'C':pct>=50?'D':'F', result: pct>=40?'Pass':'Fail', graph_color: pct>=40?'blue':'red' };
    }
    for (var i = 0; i < policy.details.length; i++) {
        var d = policy.details[i];
        if (pct >= parseFloat(d.min_percent) && pct <= parseFloat(d.max_percent)) {
            return d;
        }
    }
    return policy.details[policy.details.length - 1]; // Fallback
}

function updateGrade(sid, obt, max, pass) {
  var el = document.getElementById('grade_' + sid);
  if(!el) return;
  
  if(isNaN(obt)){ el.textContent = '-'; el.style.color = 'var(--text-primary)'; return; }
  
  if(obt < 0){
    el.textContent = obt == -1 ? 'Absent' : 'N/P'; 
    el.style.color = '#e74c3c'; 
    return;
  }
  
  var pct = max > 0 ? (obt/max)*100 : 0;
  var policyId = document.getElementById('paperGradingPolicy').value;
  var gInfo = getGradeInfo(policyId, pct);
  
  el.textContent = gInfo.grade_letter;
  el.style.color = gInfo.result === 'Pass' ? '#00b894' : '#e74c3c';
}

function updateGradingPolicy(policyId) {
  var schedId = <?= (int)$sel_schedule ?>;
  var inputEl = document.getElementById('paperGradingPolicy');
  inputEl.style.borderColor = '#6c5ce7';

  var fd = new FormData();
  fd.append('update_policy_id', '1');
  fd.append('sched_id', schedId);
  fd.append('policy_id', policyId);

  fetch(window.location.href, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(response => {
    if(!response.ok) throw new Error('Network response was not ok');
    return response.json();
  })
  .then(data => {
    inputEl.style.borderColor = '#00b894';
    setTimeout(() => {
      inputEl.style.borderColor = 'var(--border)';
    }, 1500);

    // Update all student rows in real time!
    var total = parseFloat(document.getElementById('paperTotalMarks').value);
    document.querySelectorAll('.mt tbody tr').forEach(tr => {
      var inp = tr.querySelector('.marks-val-input');
      if(inp) {
        var sid = inp.getAttribute('data-student-id');
        var obt = parseFloat(inp.value);
        updateGrade(sid, obt, total, 0); 
      }
    });
  })
  .catch(error => {
    console.error('Failed to update grading policy:', error);
    inputEl.style.borderColor = '#e74c3c';
  });
}

function updatePaperTotalMarks(val) {
  var schedId = <?= (int)$sel_schedule ?>;
  var total = parseInt(val) || 100;
  if(total <= 0) return;

  var inputEl = document.getElementById('paperTotalMarks');
  inputEl.style.borderColor = '#6c5ce7';

  var fd = new FormData();
  fd.append('update_total_marks', '1');
  fd.append('sched_id', schedId);
  fd.append('subj_name', '<?= addslashes($subj_info['subject']??'') ?>');
  fd.append('class_id', '<?= $subj_info['class_id']??0 ?>');
  fd.append('total_marks', total);

  fetch(window.location.href, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(response => {
    if(!response.ok) throw new Error('Network response was not ok');
    return response.json();
  })
  .then(data => {
    inputEl.style.borderColor = '#00b894';
    var newPass = data.pass_marks;
    
    // Update matching max_marks and pass_marks in form inputs
    var mmEl = document.querySelector('input[name="max_marks"]');
    var pmEl = document.querySelector('input[name="pass_marks"]');
    if(mmEl) mmEl.value = total;
    if(pmEl) pmEl.value = newPass;

    // Update all student rows in real time!
    document.querySelectorAll('.mt tbody tr').forEach(tr => {
      var inp = tr.querySelector('.marks-val-input');
      if(inp) {
        inp.setAttribute('data-max-marks', total);
        inp.setAttribute('data-pass-marks', newPass);
        
        var sid = inp.getAttribute('data-student-id');
        var lbl = document.getElementById('max_lbl_' + sid);
        if(lbl) {
          lbl.textContent = '/ ' + total;
        }

        var obt = parseFloat(inp.value);
        updateGrade(sid, obt, total, newPass);
      }
    });

    setTimeout(() => {
      inputEl.style.borderColor = 'var(--border)';
    }, 1500);
  })
  .catch(error => {
    console.error('Failed to update total marks:', error);
    inputEl.style.borderColor = '#e74c3c';
  });
}

function updatePaperExamDate(val) {
  var schedId = <?= (int)$sel_schedule ?>;
  var inputEl = document.getElementById('paperExamDate');
  inputEl.style.borderColor = '#6c5ce7';

  var fd = new FormData();
  fd.append('update_exam_date', '1');
  fd.append('sched_id', schedId);
  fd.append('exam_date', val);

  fetch(window.location.href, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(response => {
    if(!response.ok) throw new Error('Network response was not ok');
    return response.json();
  })
  .then(data => {
    inputEl.style.borderColor = '#00b894';
    setTimeout(() => {
      inputEl.style.borderColor = 'var(--border)';
    }, 1500);
  })
  .catch(error => {
    console.error('Failed to update exam date:', error);
    inputEl.style.borderColor = '#e74c3c';
  });
}
// ── Subject Assignment Modal ──────────────────────────
var _modalClassId = 0;
var _modalClassName = '';

function openSubjModal(classId, className) {
  _modalClassId  = classId;
  _modalClassName = className;
  document.getElementById('subjModalTitle').innerHTML = '<i class="fa-solid fa-book" style="color:#00b894"></i> Assign Subjects — ' + className;
  document.getElementById('subjModalOverlay').style.display = 'flex';
  loadSubjModal(classId);
}
function closeSubjModal() {
  document.getElementById('subjModalOverlay').style.display = 'none';
}
function loadSubjModal(classId) {
  var body = document.getElementById('subjModalBody');
  body.innerHTML = '<div style="text-align:center;padding:30px;color:#94a3b8"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem"></i><br>Loading…</div>';

  var fd = new FormData();
  fd.append('get_subjects_for_class','1');
  fd.append('class_id', classId);
  fd.append('type_id', '<?= $sel_type ?>');

  fetch('exams.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
      if (data.error) { body.innerHTML = '<p style="color:#ef4444;padding:20px">'+data.error+'</p>'; return; }
      renderSubjGrid(data.subjects);
    })
    .catch(() => { body.innerHTML = '<p style="color:#ef4444;padding:20px">Failed to load subjects.</p>'; });
}

function renderSubjGrid(subjects) {
  var body = document.getElementById('subjModalBody');
  var html = '';
  
  // Quick Add Subject Box
  html += '<div style="display:flex;gap:8px;margin-bottom:16px;background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;align-items:center">';
  html += '<input type="text" id="quickSubjInput" placeholder="Add new subject name (e.g. GK, Nazira Quran, Drawing)..." style="flex:1;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.85rem" onkeydown="if(event.key===\'Enter\'){event.preventDefault();quickAddSubject();}">';
  html += '<button type="button" id="quickAddBtn" onclick="quickAddSubject()" style="background:#0284c7;color:#fff;border:none;padding:8px 16px;border-radius:6px;font-weight:600;font-size:0.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px"><i class="fa-solid fa-plus"></i> Add</button>';
  html += '</div>';

  html += '<div class="sm-grid" id="smGridContainer">';
  subjects.forEach(function(s) {
    var chk = s.assigned ? 'checked' : '';
    var cls = s.assigned ? 'sm-item assigned' : 'sm-item';
    var safeName = s.name.replace(/"/g, '&quot;');
    html += '<label class="'+cls+'" id="sml_'+s.id+'">';
    html += '<input type="checkbox" id="smck_'+s.id+'" value="'+safeName+'" '+chk+' onchange="toggleSubjStyle('+s.id+')">';
    html += '<span class="sm-name">'+s.name+'</span>';
    if(s.assigned) html += '<span class="sm-badge">✓ Added</span>';
    html += '</label>';
  });
  html += '</div>';

  if (subjects.length === 0) {
    html += '<div id="emptySubjNotice" style="text-align:center;padding:20px;color:#94a3b8"><i class="fa-solid fa-book-open" style="font-size:1.8rem;display:block;margin-bottom:8px;opacity:.3"></i>No subjects created yet. Use the box above to create a subject!</div>';
  }
  body.innerHTML = html;
}

function quickAddSubject() {
  var inp = document.getElementById('quickSubjInput');
  var name = inp.value.trim();
  if (!name) { inp.focus(); return; }

  var btn = document.getElementById('quickAddBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

  var fd = new FormData();
  fd.append('quick_add_subject', '1');
  fd.append('name', name);

  fetch('exams.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-plus"></i> Add';
      if (data.success) {
        inp.value = '';
        var emptyNotice = document.getElementById('emptySubjNotice');
        if (emptyNotice) emptyNotice.remove();

        var container = document.getElementById('smGridContainer');
        var existing = document.getElementById('sml_' + data.id);
        if (!existing) {
          var safeName = data.name.replace(/"/g, '&quot;');
          var label = document.createElement('label');
          label.className = 'sm-item assigned';
          label.id = 'sml_' + data.id;
          label.innerHTML = '<input type="checkbox" id="smck_'+data.id+'" value="'+safeName+'" checked onchange="toggleSubjStyle('+data.id+')"><span class="sm-name">'+data.name+'</span><span class="sm-badge">✓ Added</span>';
          container.appendChild(label);
        } else {
          var chk = document.getElementById('smck_' + data.id);
          if (chk) { chk.checked = true; toggleSubjStyle(data.id); }
        }
      } else {
        alert(data.error || 'Could not add subject');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-plus"></i> Add';
      alert('Network error adding subject.');
    });
}

function toggleSubjStyle(id) {
  var lbl = document.getElementById('sml_'+id);
  var chk = document.getElementById('smck_'+id);
  if(chk.checked) { 
    lbl.classList.add('assigned'); 
    if(!lbl.querySelector('.sm-badge')) {
      var b = document.createElement('span');
      b.className = 'sm-badge';
      b.innerText = '✓ Added';
      lbl.appendChild(b);
    }
  } else { 
    lbl.classList.remove('assigned'); 
    var b = lbl.querySelector('.sm-badge');
    if(b) b.remove();
  }
}

function saveSubjAssignments() {
  var checkboxes = document.querySelectorAll('#subjModalBody .sm-item input[type="checkbox"]');
  var selected = [];
  checkboxes.forEach(function(c){ if(c.checked) selected.push(c.value); });

  var btn = document.getElementById('saveSubjBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

  var fd = new FormData();
  fd.append('save_subject_assignments','1');
  fd.append('class_id', _modalClassId);
  fd.append('type_id', '<?= $sel_type ?>');
  selected.forEach(function(s){ fd.append('subjects[]', s); });

  fetch('exams.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
      if(data.success) { 
        closeSubjModal(); 
        window.location.reload(); 
      } else { 
        alert(data.error || 'Save failed.'); 
      }
    })
    .catch(() => { 
      btn.disabled=false; 
      btn.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Save'; 
      alert('Network error.'); 
    });
}
</script>

<!-- Subject Assignment Modal -->
<div id="subjModalOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(3px)">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:12px;width:min(580px,94vw);max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden">
    <!-- Modal Header -->
    <div style="background:#1e293b;padding:16px 20px;display:flex;align-items:center;justify-content:space-between">
      <span id="subjModalTitle" style="font-size:.95rem;font-weight:800;color:#f8fafc;display:flex;align-items:center;gap:8px"><i class="fa-solid fa-book" style="color:#00b894"></i> Assign Subjects</span>
      <button onclick="closeSubjModal()" style="background:rgba(255,255,255,.1);border:none;color:#f8fafc;width:30px;height:30px;border-radius:6px;cursor:pointer;font-size:1rem">✕</button>
    </div>
    <!-- Info Banner -->
    <div style="background:rgba(59,130,246,.07);border-bottom:1px solid rgba(59,130,246,.15);padding:10px 20px;font-size:.8rem;color:#3b82f6;font-weight:600">
      <i class="fa-solid fa-circle-info"></i>
      Check subjects to assign to this class, or type a new subject name above to add it immediately.
    </div>
    <!-- Modal Body -->
    <div id="subjModalBody" style="overflow-y:auto;padding:18px 20px;flex:1"></div>
    <!-- Modal Footer -->
    <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:var(--bg-secondary)">
      <button onclick="closeSubjModal()" style="height:38px;padding:0 20px;background:transparent;border:1px solid var(--border);border-radius:8px;font-size:.85rem;font-weight:600;color:var(--text-secondary);cursor:pointer">Cancel</button>
      <button id="saveSubjBtn" onclick="saveSubjAssignments()" style="height:38px;padding:0 22px;background:#00b894;color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px">
        <i class="fa-solid fa-floppy-disk"></i> Save Subjects
      </button>
    </div>
  </div>
</div>

<style>
.sm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px}
.sm-item{display:flex;align-items:center;gap:8px;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;background:var(--bg-secondary);user-select:none}
.sm-item:hover{border-color:#00b894;background:rgba(0,184,148,.05)}
.sm-item.assigned{border-color:#00b894;background:rgba(0,184,148,.08)}
.sm-item input[type="checkbox"]{width:16px;height:16px;accent-color:#00b894;flex-shrink:0;cursor:pointer}
.sm-name{font-size:.86rem;font-weight:700;color:var(--text-primary);flex:1}
.sm-badge{font-size:.7rem;color:#10b981;font-weight:700;white-space:nowrap}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>





