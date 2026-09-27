<?php
$page_title = 'Students List';
$active_page = 'students';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin','principal','accountant','teacher');

$msg = ''; $err = '';

// Delete
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM students WHERE id=".(int)$_GET['delete']);
    header("Location: " . BASE_URL . "modules/students/students.php?class_id=" . ($_GET['class_id']??0) . "&msg=deleted"); exit;
}
if (isset($_GET['msg']) && $_GET['msg']==='deleted') $msg = 'Student deleted successfully.';

// Bulk Import Students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {
    $cid = (int)$_POST['class_id'];
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === 0) {
        $file = $_FILES['import_file']['tmp_name'];
        $content = file_get_contents($file);
        // Remove UTF-8 BOM if present
        $content = str_replace("\xEF\xBB\xBF", '', $content);
        $lines = explode("\n", str_replace("\r", "", $content));
        
        $count = 0; $row_idx = 0;
        $mapping = ['reg' => -1, 'roll' => -1, 'name' => -1, 'father' => -1, 'gender' => -1, 'dob' => -1, 'adm_date' => -1, 'religion' => -1, 'cnic' => -1, 'occup' => -1, 'address' => -1, 'p_address' => -1, 'g_name' => -1, 'g_phone' => -1];
        
        // Detect delimiter
        $first_line = $lines[0] ?? '';
        $delimiter = (strpos($first_line, ';') !== false) ? ';' : ',';

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $data = str_getcsv($line, $delimiter);
            $row_idx++;
            
            if ($row_idx === 1) {
                foreach ($data as $idx => $h) {
                    $h = strtolower(trim($h));
                    if (preg_match('/(reg|adm|enroll|sr|id)/i', $h) && !preg_match('/(date|class|type)/i', $h)) $mapping['reg'] = $idx;
                    if (preg_match('/(roll)/i', $h)) $mapping['roll'] = $idx;
                    if (preg_match('/(name|st_name|student)/i', $h) && !preg_match('/(father|guardian|mother|parent)/i', $h)) $mapping['name'] = $idx;
                    if (preg_match('/(father|f_name|parent)/i', $h) && !preg_match('/(occup|desig|phone)/i', $h)) $mapping['father'] = $idx;
                    if (preg_match('/(gender|sex)/i', $h)) $mapping['gender'] = $idx;
                    if (preg_match('/(birth|dob|bday)/i', $h)) $mapping['dob'] = $idx;
                    if (preg_match('/(adm)/i', $h) && preg_match('/(date)/i', $h)) $mapping['adm_date'] = $idx;
                    if (preg_match('/(religion|faith)/i', $h)) $mapping['religion'] = $idx;
                    if (preg_match('/(cnic|nic|form|b-form)/i', $h)) $mapping['cnic'] = $idx;
                    if (preg_match('/(occup)/i', $h)) $mapping['occup'] = $idx;
                    if (preg_match('/(present|current|address)/i', $h) && !preg_match('/(perm)/i', $h)) $mapping['address'] = $idx;
                    if (preg_match('/(perm|home)/i', $h) && preg_match('/(address)/i', $h)) $mapping['p_address'] = $idx;
                    if (preg_match('/(guardian)/i', $h) && preg_match('/(name)/i', $h)) $mapping['g_name'] = $idx;
                    if (preg_match('/(guardian|parent|emergency)/i', $h) && (preg_match('/(contact|phone|cell|no)/i', $h))) $mapping['g_phone'] = $idx;
                }
                if ($mapping['name'] === -1) { $mapping['reg'] = 0; $mapping['roll'] = 1; $mapping['name'] = 2; $mapping['father'] = 3; $mapping['gender'] = 4; $mapping['dob'] = 5; $mapping['adm_date'] = 6; }
                continue; 
            }
            
            $name = ($mapping['name'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['name']] ?? '')) : '';
            if (!$name || strlen($name) < 2) continue;

            $adm_no = ($mapping['reg'] !== -1 && !empty(trim($data[$mapping['reg']] ?? ''))) ? $conn->real_escape_string(trim($data[$mapping['reg']])) : generate_admission_no($conn);
            $roll = ($mapping['roll'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['roll']] ?? '')) : '';
            $father = ($mapping['father'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['father']] ?? '')) : '';
            $gender = ($mapping['gender'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['gender']] ?? 'Male')) : 'Male';
            $dob = ($mapping['dob'] !== -1 && !empty(trim($data[$mapping['dob']] ?? ''))) ? date('Y-m-d', strtotime(str_replace(['/','.'], '-', $data[$mapping['dob']]))) : NULL;
            $adm_date = ($mapping['adm_date'] !== -1 && !empty(trim($data[$mapping['adm_date']] ?? ''))) ? date('Y-m-d', strtotime(str_replace(['/','.'], '-', $data[$mapping['adm_date']]))) : date('Y-m-d');
            $religion = ($mapping['religion'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['religion']] ?? 'Islam')) : 'Islam';
            $cnic = ($mapping['cnic'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['cnic']] ?? '')) : '';
            $occup = ($mapping['occup'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['occup']] ?? '')) : '';
            $addr = ($mapping['address'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['address']] ?? '')) : '';
            $p_addr = ($mapping['p_address'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['p_address']] ?? '')) : '';
            $g_name = ($mapping['g_name'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['g_name']] ?? '')) : '';
            $g_phone = ($mapping['g_phone'] !== -1) ? $conn->real_escape_string(trim($data[$mapping['g_phone']] ?? '')) : '';

            $sql = "INSERT INTO students (admission_no, name, father_name, gender, dob, admission_date, religion, cnic, father_occupation, address, permanent_address, guardian_name, guardian_contact, class_id, roll_no, status) 
                    VALUES ('$adm_no', '$name', '$father', '$gender', ".($dob?"'$dob'":"NULL").", '$adm_date', '$religion', '$cnic', '$occup', '$addr', '$p_addr', '$g_name', '$g_phone', $cid, '$roll', 'Active')
                    ON DUPLICATE KEY UPDATE name='$name', roll_no='$roll', class_id=$cid, status='Active'";
            
            if ($conn->query($sql)) {
                $student_id = $conn->insert_id;
                if (!$student_id) {
                    $id_q = $conn->query("SELECT id FROM students WHERE admission_no = '$adm_no'");
                    if ($id_q && $id_q->num_rows > 0) {
                        $student_id = (int)$id_q->fetch_assoc()['id'];
                    }
                }
                if ($student_id) {
                    $active_session = $settings['session_year'] ?? '2025-2026';
                    $check_enr = $conn->query("SELECT id FROM student_enrollments WHERE student_id = $student_id AND class_id = $cid AND session_year = '$active_session'");
                    if ($check_enr && $check_enr->num_rows > 0) {
                        $enr_id = $check_enr->fetch_assoc()['id'];
                        $conn->query("UPDATE student_enrollments SET roll_no = '$roll', status = 'Active' WHERE id = $enr_id");
                    } else {
                        $conn->query("INSERT INTO student_enrollments (student_id, class_id, session_year, roll_no, enrollment_date, status) VALUES ($student_id, $cid, '$active_session', '$roll', '$adm_date', 'Active')");
                    }
                }
                $count++;
            }
        }
        $found_cols = array_filter($mapping, function($v){return $v!==-1;});
        header("Location: " . BASE_URL . "modules/students/students.php?class_id=$cid&import_msg=$count&cols=".count($found_cols)); exit;
    } else {
        $err = "Error uploading file.";
    }
}
if (isset($_GET['import_msg'])) $msg = "Import Result: ".(int)$_GET['import_msg']." students added. System detected ".(int)($_GET['cols']??0)." columns.";

// Update Roll Number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_roll_no') {
    $sid = (int)$_POST['student_id'];
    $new_roll = $conn->real_escape_string(trim($_POST['new_roll_no']));
    $cid = (int)$_POST['class_id'];
    
    $check = $conn->query("SELECT id FROM students WHERE class_id=$cid AND roll_no='$new_roll' AND id != $sid");
    if ($check && $check->num_rows > 0) {
        $err = "Roll number '$new_roll' is already assigned to another student in this class.";
    } else {
        $conn->query("UPDATE students SET roll_no='$new_roll' WHERE id=$sid");
        $msg = "Roll number updated successfully!";
    }
}

// Add / Edit student via modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && !isset($_POST['add_class_action'])) {
    $id          = (int)($_POST['id'] ?? 0);
    $name        = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $father_name = $conn->real_escape_string(trim($_POST['father_name'] ?? ''));
    $mother_name = $conn->real_escape_string(trim($_POST['mother_name'] ?? ''));
    $cnic        = $conn->real_escape_string(trim($_POST['cnic'] ?? ''));
    $dob         = $conn->real_escape_string($_POST['dob'] ?? '');
    $gender      = $conn->real_escape_string($_POST['gender'] ?? 'Male');
    $religion    = $conn->real_escape_string($_POST['religion'] ?? 'Islam');
    $nationality = $conn->real_escape_string($_POST['nationality'] ?? 'Pakistani');
    $address     = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $phone       = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $father_phone= $conn->real_escape_string(trim($_POST['father_phone'] ?? ''));
    $email       = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $class_id    = (int)($_POST['class_id'] ?? 0);
    $roll_no     = $conn->real_escape_string(trim($_POST['roll_no'] ?? ''));
    $admission_date = $conn->real_escape_string($_POST['admission_date'] ?? date('Y-m-d'));
    $status      = $conn->real_escape_string($_POST['status'] ?? 'Active');
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $filename = 'student_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_PATH . $filename);
            $photo = $filename;
        }
    }
    if ($name === '') {
        $err = 'Student name is required.';
    } else {
        if ($id > 0) {
            $sql = "UPDATE students SET name='$name',father_name='$father_name',mother_name='$mother_name',cnic='$cnic',dob='$dob',gender='$gender',religion='$religion',nationality='$nationality',address='$address',phone='$phone',father_phone='$father_phone',email='$email',class_id=$class_id,roll_no='$roll_no',admission_date='$admission_date',status='$status'";
            if ($photo) $sql .= ",photo='$photo'";
            $sql .= " WHERE id=$id";
            $conn->query($sql);
            $msg = 'Student updated successfully.';
        } else {
            $adm_no = generate_admission_no($conn);
            if ($conn->query("INSERT INTO students (admission_no,name,father_name,mother_name,cnic,dob,gender,religion,nationality,address,phone,father_phone,email,class_id,roll_no,admission_date,status,photo) VALUES ('$adm_no','$name','$father_name','$mother_name','$cnic','$dob','$gender','$religion','$nationality','$address','$phone','$father_phone','$email',$class_id,'$roll_no','$admission_date','$status','$photo')")) {
                $student_id = $conn->insert_id;
                $active_session = $settings['session_year'] ?? '2025-2026';
                $conn->query("INSERT INTO student_enrollments (student_id, class_id, session_year, roll_no, enrollment_date, status) VALUES ($student_id, $class_id, '$active_session', '$roll_no', '$admission_date', 'Active')");
            }
            $msg = "Student added! Adm#: $adm_no";
        }
    }
}

// Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class_action'])) {
    $cn  = $conn->real_escape_string(trim($_POST['class_name'] ?? ''));
    $sec = $conn->real_escape_string(trim($_POST['class_section'] ?? 'A'));
    if ($cn) {
        $conn->query("INSERT IGNORE INTO classes (name,section) VALUES ('$cn','$sec')");
        $new_id = $conn->insert_id;
        $msg = 'Class "'.$cn.' '.$sec.'" added.';
        header("Location: " . BASE_URL . "modules/students/students.php?class_id=" . $new_id); exit;
    }
}
// Delete Class
if (isset($_GET['del_class']) && current_role() === 'admin') {
    $conn->query("DELETE FROM classes WHERE id=".(int)$_GET['del_class']);
    header("Location: " . BASE_URL . "modules/students/students.php"); exit;
}

// Classes for matrix (Naturally sorted)
$classes_arr = get_all_classes($conn);

// Group classes into a multi-column structure for the matrix
$class_groups = [];
foreach ($classes_arr as $c) {
    $cName = $c['name'];
    if (!isset($class_groups[$cName])) {
        $class_groups[$cName] = [];
    }
    $class_groups[$cName][] = $c;
}

$sel_class = (int)($_GET['class_id'] ?? ($classes_arr[0]['id'] ?? 0));
$search = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$sel_class_info = null;
foreach($classes_arr as $c) { if($c['id']==$sel_class){$sel_class_info=$c;break;} }

$active_session = $settings['session_year'] ?? '2025-2026';
$where = "se.class_id=$sel_class AND se.session_year='$active_session' AND se.status='Active'";
if ($search) $where .= " AND (s.name LIKE '%$search%' OR s.admission_no LIKE '%$search%' OR s.father_name LIKE '%$search%')";
$students = $conn->query("SELECT s.*, c.name as class_name, c.section, se.roll_no as enrollment_roll_no FROM student_enrollments se JOIN students s ON se.student_id = s.id LEFT JOIN classes c ON se.class_id = c.id WHERE $where ORDER BY (se.roll_no+0), s.name");

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $r = $conn->query("SELECT * FROM students WHERE id=".(int)$_GET['edit']);
    if ($r && $r->num_rows) $edit = $r->fetch_assoc();
}

$show_form = isset($_GET['action']) && $_GET['action']==='new';
if ($edit || $err) $show_form = true;
?>

<style>
/* ============================================================
   SIAX SMSS — Premium Students List Styling
   ============================================================ */
.std-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    gap: 16px;
    flex-wrap: wrap;
}
.std-page-title h1 {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 5px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.5px;
}
.std-page-title .create-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #0984e3;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all var(--transition);
}
.std-page-title .create-link:hover {
    color: #00cec9;
    transform: translateX(2px);
}
.register-student-btn {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #000 !important;
    font-weight: 800;
    font-size: 0.88rem;
    padding: 11px 22px;
    border-radius: var(--radius-xs);
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
    transition: all var(--transition);
    text-decoration: none;
}
.register-student-btn:hover {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #000 !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.45);
}

/* Classes & Sections Matrix Card */
.matrix-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow);
    margin-bottom: 22px;
    overflow: hidden;
    transition: border-color var(--transition);
}
.matrix-card:hover {
    border-color: var(--border-hover);
}
.matrix-card-header {
    background: #0f172a;
    color: #f8fafc;
    padding: 11px 18px;
    font-size: 0.85rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
[data-theme="light"] .matrix-card-header {
    background: #1e293b;
    color: #ffffff;
}
.matrix-grid-scroll {
    overflow-x: auto;
    padding: 12px 18px 16px;
    scrollbar-width: thin;
    scrollbar-color: var(--accent) transparent;
}
.matrix-grid-scroll::-webkit-scrollbar {
    height: 5px;
}
.matrix-grid-scroll::-webkit-scrollbar-thumb {
    background: var(--accent);
    border-radius: 4px;
}
.matrix-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 14px 6px;
    text-align: center;
}
.matrix-table th {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 6px 12px 10px;
    white-space: nowrap;
    border-bottom: 2px solid var(--border);
}
.matrix-table td {
    vertical-align: top;
    padding: 6px 2px;
    white-space: nowrap;
}
.section-pill-link {
    display: inline-block;
    padding: 5px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #3b82f6;
    text-decoration: none;
    border-radius: var(--radius-xs);
    transition: all var(--transition);
    margin: 2px 0;
    border: 1px solid transparent;
}
.section-pill-link:hover {
    background: rgba(59, 130, 246, 0.12);
    color: #2563eb;
    border-color: rgba(59, 130, 246, 0.25);
    transform: translateY(-1px);
}
.section-pill-link.active {
    background: #f59e0b !important;
    color: #000 !important;
    font-weight: 800 !important;
    border: 2px solid #000;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.4);
    transform: translateY(-1px);
}

/* Active Class Card & Action Toolbar */
.active-class-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow);
    margin-bottom: 28px;
    overflow: hidden;
    transition: border-color var(--transition);
}
.active-class-card:hover {
    border-color: var(--border-hover);
}
.active-class-header {
    background: #0f172a;
    color: #ffffff;
    padding: 10px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
[data-theme="light"] .active-class-header {
    background: #1e293b;
    color: #ffffff;
}
.active-class-title {
    font-size: 0.9rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #f8fafc;
}
.active-class-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.action-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    text-decoration: none;
    cursor: pointer;
    transition: all var(--transition);
    white-space: nowrap;
}
.action-pill-btn:hover {
    background: #f1f5f9;
    color: #0984e3;
    border-color: #94a3b8;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
[data-theme="dark"] .action-pill-btn {
    background: rgba(30, 41, 59, 0.85);
    color: #f1f5f9;
    border-color: rgba(99, 102, 241, 0.25);
}
[data-theme="dark"] .action-pill-btn:hover {
    background: rgba(99, 102, 241, 0.25);
    color: #fff;
    border-color: rgba(99, 102, 241, 0.5);
}

/* Search Bar Area */
.search-bar-wrap {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.015);
}
.search-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.search-input-field {
    width: 100%;
    height: 40px;
    padding: 8px 14px;
    font-size: 0.86rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xs);
    color: var(--text-primary);
    transition: all var(--transition);
}
.search-input-field:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
    outline: none;
}

/* Student Table Custom Badges */
.std-photo-thumb {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid var(--border);
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.std-photo-placeholder-sq {
    width: 44px;
    height: 44px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-muted);
}
.reg-pill {
    display: inline-block;
    padding: 3px 8px;
    background: rgba(59, 130, 246, 0.08);
    border: 1px solid rgba(59, 130, 246, 0.25);
    color: #3b82f6;
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 4px;
}
.roll-pill {
    display: inline-block;
    padding: 2px 10px;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: var(--text-primary);
    font-size: 0.82rem;
    font-weight: 700;
    border-radius: 4px;
}
.action-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all var(--transition);
    text-decoration: none;
    color: #fff !important;
}
.action-icon-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
</style>

<div class="main-container">

    <!-- Top Header -->
    <div class="std-page-header">
        <div class="std-page-title">
            <h1><i class="fa-solid fa-graduation-cap" style="color:#f59e0b;"></i> Students List</h1>
            <a href="<?= BASE_URL ?>modules/students/classes.php" class="create-link">
                <i class="fa-solid fa-circle-plus"></i> Create More Classes &amp; Sections
            </a>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/students/register_student.php?class_id=<?= $sel_class ?>" class="register-student-btn" title="Open Student Registration Form">
                <i class="fa-solid fa-user-plus"></i> Register New Student
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($msg): ?>
        <div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:16px;padding:12px 18px;border-radius:var(--radius-xs);display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="login-error" style="background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#f87171;margin-bottom:16px;padding:12px 18px;border-radius:var(--radius-xs);display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>

    <!-- Classes & Sections Matrix Card -->
    <div class="matrix-card">
        <div class="matrix-card-header">
            <i class="fa-solid fa-table-cells"></i> Classes &amp; Sections
        </div>
        <div class="matrix-grid-scroll">
            <table class="matrix-table">
                <thead>
                    <tr>
                        <?php foreach($class_groups as $cName => $secs): ?>
                            <th><?= htmlspecialchars($cName) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach($class_groups as $cName => $secs): ?>
                            <td>
                                <?php foreach($secs as $s): ?>
                                    <div>
                                        <a href="?class_id=<?= $s['id'] ?>" class="section-pill-link <?= $sel_class == $s['id'] ? 'active' : '' ?>">
                                            <?= htmlspecialchars($s['section'] ?: 'Default') ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($sel_class_info): ?>
    <!-- Active Class Action Card & Students Table -->
    <div class="active-class-card">
        
        <!-- Header Strip with Quick Action Buttons -->
        <div class="active-class-header">
            <div class="active-class-title">
                <i class="fa-solid fa-table-cells" style="color:#f59e0b;"></i> Class — <?= htmlspecialchars($sel_class_info['name']) ?> &nbsp;|&nbsp; Section — <?= htmlspecialchars($sel_class_info['section']) ?>
            </div>
            <div class="active-class-actions">
                <a href="<?= BASE_URL ?>modules/students/student_id_cards.php?class_id=<?= $sel_class ?>" class="action-pill-btn">
                    <i class="fa-solid fa-id-card" style="color:#0984e3;"></i> Digital ID Cards
                </a>
                <a href="<?= BASE_URL ?>prints/print_award_list.php?class_id=<?= $sel_class ?>" target="_blank" class="action-pill-btn">
                    <i class="fa-solid fa-trophy" style="color:#f59e0b;"></i> Award List
                </a>
                <a href="<?= BASE_URL ?>prints/print_students.php?class_id=<?= $sel_class ?>" target="_blank" class="action-pill-btn">
                    <i class="fa-solid fa-print" style="color:#0984e3;"></i> Print Student List
                </a>
                <a href="<?= BASE_URL ?>prints/print_enrollment_register.php?class_id=<?= $sel_class ?>" target="_blank" class="action-pill-btn">
                    <i class="fa-solid fa-list-check" style="color:#10b981;"></i> Detailed List
                </a>
                <a href="javascript:void(0)" onclick="exportTableToCSV()" class="action-pill-btn">
                    <i class="fa-solid fa-file-excel" style="color:#10b981;"></i> Print Excel
                </a>
                <a href="<?= BASE_URL ?>modules/exams/results.php?class_id=<?= $sel_class ?>" class="action-pill-btn">
                    <i class="fa-solid fa-bullseye" style="color:#ef4444;"></i> Target Grades
                </a>
                <a href="javascript:void(0)" onclick="openImportModal()" class="action-pill-btn">
                    <i class="fa-solid fa-file-arrow-up" style="color:#10b981;"></i> Import Excel Sheet
                </a>
                <a href="javascript:void(0)" onclick="openRollNoModal()" class="action-pill-btn">
                    <i class="fa-solid fa-arrow-down-1-9" style="color:#6366f1;"></i> Change Roll-No
                </a>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar-wrap">
            <div class="search-label">
                <i class="fa-solid fa-magnifying-glass" style="color:#f59e0b;"></i> Search Student
            </div>
            <input type="text" id="studentSearchFilter" placeholder="Search by name, roll no, admission no, father name..." class="search-input-field" onkeyup="filterStudentRows()">
        </div>

        <!-- Student Table -->
        <div class="table-wrapper" style="border:none; border-radius:0;">
            <table class="data-table" id="studentsMainTable">
                <thead style="background:#0f172a; color:#fff;">
                    <tr>
                        <th style="width:60px; text-align:center; background:#0f172a; color:#fff;">PICTURE</th>
                        <th style="width:110px; background:#0f172a; color:#fff;">REG #</th>
                        <th style="width:80px; text-align:center; background:#0f172a; color:#fff;">ROLL NO</th>
                        <th style="background:#0f172a; color:#fff;">NAME</th>
                        <th style="background:#0f172a; color:#fff;">FATHER NAME</th>
                        <th style="background:#0f172a; color:#fff;">GUARDIAN CONTACT</th>
                        <th style="width:200px; text-align:center; background:#0f172a; color:#fff;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="studentRowsBody">
                    <?php if($students && $students->num_rows > 0): while($s=$students->fetch_assoc()): ?>
                    <tr class="std-row">
                        <td style="text-align:center;">
                            <?php if($s['photo']): ?>
                                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($s['photo']) ?>" class="std-photo-thumb" alt="">
                            <?php else: ?>
                                <div class="std-photo-placeholder-sq"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="reg-pill"># <?= htmlspecialchars($s['admission_no']) ?></span>
                        </td>
                        <td style="text-align:center;">
                            <span class="roll-pill"><?= htmlspecialchars($s['enrollment_roll_no'] ?: ($s['roll_no'] ?: '-')) ?></span>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-user-graduate" style="color:#00b894; font-size:0.9rem;"></i>
                                <a href="<?= BASE_URL ?>modules/students/student_profile.php?id=<?= $s['id'] ?>" style="font-weight:700; color:var(--text-primary); text-decoration:none; font-size:0.88rem;">
                                    <?= htmlspecialchars($s['name']) ?>
                                </a>
                            </div>
                        </td>
                        <td style="font-weight:600; color:var(--text-secondary);">
                            <?= htmlspecialchars($s['father_name'] ?: '-') ?>
                        </td>
                        <td style="color:var(--text-secondary); font-size:0.82rem;">
                            <?= htmlspecialchars($s['father_phone'] ?: ($s['phone'] ?: '-')) ?>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:inline-flex; gap:5px; align-items:center; justify-content:center;">
                                <button class="action-icon-btn" style="background:#00b894;" onclick="openStudentForm(<?= $s['id'] ?>)" title="Edit Student">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <a href="<?= BASE_URL ?>modules/students/student_profile.php?id=<?= $s['id'] ?>" class="action-icon-btn" style="background:#6366f1;" title="View Profile">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>modules/fees/fees.php?student_id=<?= $s['id'] ?>" class="action-icon-btn" style="background:#0984e3;" title="Fee Voucher &amp; History">
                                    <i class="fa-solid fa-money-bill-wave"></i>
                                </a>
                                <a href="<?= BASE_URL ?>modules/students/enrollment.php?id=<?= $s['id'] ?>" class="action-icon-btn" style="background:#f59e0b;" title="Enrollment History">
                                    <i class="fa-solid fa-user-plus"></i>
                                </a>
                                <a href="<?= BASE_URL ?>prints/print_enrollment_register.php?id=<?= $s['id'] ?>" target="_blank" class="action-icon-btn" style="background:#10b981;" title="Print Form">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                                <?php if(current_role() === 'admin'): ?>
                                <button class="action-icon-btn" style="background:#ef4444;" onclick="confirmDelete('?class_id=<?= $sel_class ?>&delete=<?= $s['id'] ?>','<?= htmlspecialchars(addslashes($s['name'])) ?>')" title="Delete Student">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:50px; color:var(--text-muted);">
                            <i class="fa-solid fa-user-graduate" style="font-size:2rem; margin-bottom:10px; display:block; opacity:0.4;"></i>
                            No students found in this class.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
    <?php endif; ?>

</div>

<!-- ADD / EDIT STUDENT MODAL (Used for Quick Edits) -->
<div id="studentFormModal" class="modal-overlay" style="display:none">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalTitle"><i class="fa-solid fa-user"></i> Edit Student</h3>
      <button class="modal-close" onclick="closeStudentForm()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST" enctype="multipart/form-data" id="studentForm">
        <input type="hidden" name="id" id="form_id" value="0">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" id="form_name" class="form-control" required></div>
          <div class="form-group"><label>Father's Name</label><input type="text" name="father_name" id="form_father" class="form-control"></div>
          <div class="form-group"><label>Mother's Name</label><input type="text" name="mother_name" id="form_mother" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>CNIC / B-Form</label><input type="text" name="cnic" id="form_cnic" class="form-control"></div>
          <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" id="form_dob" class="form-control"></div>
          <div class="form-group"><label>Gender</label>
            <select name="gender" id="form_gender" class="form-control">
              <option>Male</option><option>Female</option><option>Other</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Phone</label><input type="text" name="phone" id="form_phone" class="form-control"></div>
          <div class="form-group"><label>Father's Phone</label><input type="text" name="father_phone" id="form_fphone" class="form-control"></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" id="form_email" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Religion</label><input type="text" name="religion" id="form_religion" class="form-control" value="Islam"></div>
          <div class="form-group"><label>Nationality</label><input type="text" name="nationality" id="form_nat" class="form-control" value="Pakistani"></div>
          <div class="form-group"><label>Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Class</label>
            <select name="class_id" id="form_class" class="form-control">
              <option value="0">-- Select Class --</option>
              <?php foreach($classes_arr as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $sel_class==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'].' - '.$c['section']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Roll No</label><input type="text" name="roll_no" id="form_roll" class="form-control"></div>
          <div class="form-group"><label>Admission Date</label><input type="date" name="admission_date" id="form_admdate" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group" style="grid-column:1/-1"><label>Address</label><textarea name="address" id="form_address" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Status</label>
            <select name="status" id="form_status" class="form-control">
              <?php foreach(['Active','Left','Transferred','Passed'] as $st): ?><option><?= $st ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="btn-group mt-2">
          <button type="submit" class="btn btn-primary" id="formSubmitBtn"><i class="fa-solid fa-floppy-disk"></i> Update Student</button>
          <button type="button" class="btn btn-secondary" onclick="closeStudentForm()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- IMPORT STUDENTS MODAL -->
<div id="importModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h3><i class="fa-solid fa-file-import"></i> Import Student Sheet</h3>
      <button class="modal-close" onclick="closeImportModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info small mb-3">
        <strong>Dynamic Format:</strong> Upload CSV / Excel exported file with columns (Reg#, RollNo, Name, Father Name, etc.).
      </div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_students">
        <input type="hidden" name="class_id" value="<?= $sel_class ?>">
        
        <div class="form-group mb-4">
          <label>Select CSV / Excel File</label>
          <input type="file" name="import_file" class="form-control" accept=".csv" required>
        </div>

        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-upload"></i> Start Bulk Import</button>
      </form>
    </div>
  </div>
</div>

<!-- CHANGE ROLL NO MODAL -->
<div id="rollNoModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <h3><i class="fa-solid fa-list-ol"></i> Change Roll Number</h3>
      <button class="modal-close" onclick="closeRollNoModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_roll_no">
        <input type="hidden" name="class_id" value="<?= $sel_class ?>">
        
        <div class="form-group">
          <label>Select Student</label>
          <select name="student_id" id="roll_student_id" class="form-control" onchange="updateAvailableRolls()" required>
            <option value="">-- Choose Student --</option>
            <?php 
            $stds_q = $conn->query("SELECT id, name, roll_no FROM students WHERE class_id=$sel_class AND status='Active' ORDER BY name");
            $taken_rolls = [];
            while($st = $stds_q->fetch_assoc()): 
              if($st['roll_no']) $taken_rolls[] = $st['roll_no'];
            ?>
              <option value="<?= $st['id'] ?>" data-roll="<?= htmlspecialchars($st['roll_no']) ?>"><?= htmlspecialchars($st['name']) ?> (Roll: <?= $st['roll_no'] ?: 'None' ?>)</option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-group">
          <label>New Roll Number</label>
          <input type="number" name="new_roll_no" id="roll_new_val" class="form-control" placeholder="Enter new roll number" required>
        </div>

        <div id="availableRollsBox" style="margin-top:12px; font-size: 0.75rem; color: var(--text-muted);">
          <strong>Already Taken in this Class:</strong><br>
          <div id="takenRollsList" style="display:flex; flex-wrap:wrap; gap:4px; margin-top:4px;">
            <?php foreach(array_unique($taken_rolls) as $tr): ?>
              <span style="background:var(--bg-glass); border:1px solid var(--border); padding:2px 6px; border-radius:4px;"><?= $tr ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="btn-group mt-4">
          <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-save"></i> Save Roll Number</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Filter Student Rows in Real-Time
function filterStudentRows() {
    const query = document.getElementById('studentSearchFilter').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#studentRowsBody .std-row');
    
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

// Export Table to CSV / Excel
function exportTableToCSV() {
    let csv = [];
    const rows = document.querySelectorAll("#studentsMainTable tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        // Skip picture and action columns
        for (let j = 1; j < cols.length - 1; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/"/g, '""').trim();
            row.push('"' + data + '"');
        }
        if (row.length > 0) csv.push(row.join(","));
    }

    const csvFile = new Blob([csv.join("\n")], {type: "text/csv;charset=utf-8;"});
    const downloadLink = document.createElement("a");
    downloadLink.download = "students_<?= htmlspecialchars($sel_class_info['name'] ?? 'class') ?>_<?= date('Y-m-d') ?>.csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

function openImportModal() {
  document.getElementById('importModal').style.display = 'flex';
}
function closeImportModal() {
  document.getElementById('importModal').style.display = 'none';
}
function openRollNoModal() {
  document.getElementById('rollNoModal').style.display = 'flex';
}
function closeRollNoModal() {
  document.getElementById('rollNoModal').style.display = 'none';
}
function updateAvailableRolls() {
  const sel = document.getElementById('roll_student_id');
  const currentRoll = sel.options[sel.selectedIndex].getAttribute('data-roll');
  document.getElementById('roll_new_val').value = currentRoll || '';
}

function openStudentForm(id) {
  var modal = document.getElementById('studentFormModal');
  var title = document.getElementById('modalTitle');
  var submitBtn = document.getElementById('formSubmitBtn');
  document.getElementById('studentForm').reset();
  document.getElementById('form_id').value = '0';
  document.getElementById('form_class').value = '<?= $sel_class ?>';
  document.getElementById('form_admdate').value = '<?= date('Y-m-d') ?>';
  if (!id) {
    window.location.href = '<?= BASE_URL ?>modules/students/register_student.php?class_id=<?= $sel_class ?>';
    return;
  }
  fetch('<?= BASE_URL ?>api/data.php?action=student_info&id=' + id)
    .then(function(r){return r.json();})
    .then(function(res){
      if (!res.success) return;
      var d = res.data;
      title.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit: ' + d.name;
      submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Student';
      document.getElementById('form_id').value = d.id;
      document.getElementById('form_name').value = d.name || '';
      document.getElementById('form_father').value = d.father_name || '';
      document.getElementById('form_mother').value = d.mother_name || '';
      document.getElementById('form_cnic').value = d.cnic || '';
      document.getElementById('form_dob').value = d.dob || '';
      document.getElementById('form_gender').value = d.gender || 'Male';
      document.getElementById('form_phone').value = d.phone || '';
      document.getElementById('form_fphone').value = d.father_phone || '';
      document.getElementById('form_email').value = d.email || '';
      document.getElementById('form_religion').value = d.religion || 'Islam';
      document.getElementById('form_nat').value = d.nationality || 'Pakistani';
      document.getElementById('form_class').value = d.class_id || '<?= $sel_class ?>';
      document.getElementById('form_roll').value = d.roll_no || '';
      document.getElementById('form_admdate').value = d.admission_date || '<?= date('Y-m-d') ?>';
      document.getElementById('form_address').value = d.address || '';
      document.getElementById('form_status').value = d.status || 'Active';
      modal.style.display = 'flex';
    });
}
function closeStudentForm() {
  document.getElementById('studentFormModal').style.display = 'none';
}
document.getElementById('studentFormModal').addEventListener('click', function(e){
  if (e.target === this) closeStudentForm();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
