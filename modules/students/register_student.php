<?php
/**
 * SIAX SMSS - Dedicated Student Registration Page
 * Premium design for student admission.
 */
ob_start();
$page_title = 'Register New Student';
$active_page = 'register_student';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant', 'teacher');

$msg = ''; $err = '';

/**
 * Helper to check if a field is enabled in Admission Settings
 */
function is_field_enabled($field_key, $settings) {
    return (isset($settings['field_' . $field_key]) && $settings['field_' . $field_key] === '0') ? false : true;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $father_name       = $conn->real_escape_string(trim($_POST['father_name'] ?? ''));
    $mother_name       = $conn->real_escape_string(trim($_POST['mother_name'] ?? ''));
    $father_occupation = $conn->real_escape_string(trim($_POST['father_occupation'] ?? ''));
    $cnic              = $conn->real_escape_string(trim($_POST['cnic'] ?? ''));
    $dob               = $conn->real_escape_string($_POST['dob'] ?? '');
    $gender            = $conn->real_escape_string($_POST['gender'] ?? 'Male');
    $religion          = $conn->real_escape_string($_POST['religion'] ?? 'Islam');
    $nationality       = $conn->real_escape_string($_POST['nationality'] ?? 'Pakistani');
    $address           = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $permanent_address = $conn->real_escape_string(trim($_POST['permanent_address'] ?? ''));
    $phone             = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $father_phone      = $conn->real_escape_string(trim($_POST['father_phone'] ?? ''));
    $email             = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $class_id          = (int)($_POST['class_id'] ?? 0);
    $admission_class_id= (int)($_POST['admission_class_id'] ?? 0);
    $roll_no           = $conn->real_escape_string(trim($_POST['roll_no'] ?? ''));
    $admission_no      = $conn->real_escape_string(trim($_POST['admission_no'] ?? '')); 
    // Auto-assign family_id based on father_phone match
    $family_id = '';
    if (!empty($father_phone)) {
        // Check if another student already has this father_phone
        $fam_check = $conn->query("SELECT family_id FROM students WHERE father_phone = '$father_phone' AND family_id IS NOT NULL AND family_id != '' LIMIT 1");
        if ($fam_check && $fam_check->num_rows > 0) {
            $family_id = $fam_check->fetch_assoc()['family_id'];
        } else {
            // Generate next family_id number
            $max_fam = $conn->query("SELECT family_id FROM students WHERE family_id LIKE 'FAM-%' ORDER BY CAST(SUBSTRING(family_id, 5) AS UNSIGNED) DESC LIMIT 1");
            if ($max_fam && $max_fam->num_rows > 0) {
                $last = $max_fam->fetch_assoc()['family_id'];
                $num = (int)substr($last, 4) + 1;
            } else {
                $num = 1;
            }
            $family_id = 'FAM-' . str_pad($num, 4, '0', STR_PAD_LEFT);
        }
    }
    $guardian_name     = $conn->real_escape_string(trim($_POST['guardian_name'] ?? ''));
    $guardian_contact  = $conn->real_escape_string(trim($_POST['guardian_contact'] ?? ''));
    $admission_date    = $conn->real_escape_string($_POST['admission_date'] ?? date('Y-m-d'));
    $status            = $conn->real_escape_string($_POST['status'] ?? 'Active');
    
    // Photo upload
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $filename = 'student_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_PATH . $filename);
            $photo = $filename;
        }
    }

    if ($name === '' || $class_id === 0) {
        $err = 'Student name and Class are required.';
    } else {
        if (empty($admission_no)) {
            $admission_no = generate_admission_no($conn);
        }
        
        $sql = "INSERT INTO students (
                    admission_no, family_id, name, father_name, father_occupation, mother_name, 
                    guardian_name, guardian_contact, cnic, dob, gender, religion, nationality, 
                    address, permanent_address, phone, father_phone, email, 
                    class_id, admission_class_id, roll_no, admission_date, status, photo
                ) VALUES (
                    '$admission_no', '$family_id', '$name', '$father_name', '$father_occupation', '$mother_name', 
                    '$guardian_name', '$guardian_contact', '$cnic', '$dob', '$gender', '$religion', '$nationality', 
                    '$address', '$permanent_address', '$phone', '$father_phone', '$email', 
                    $class_id, $admission_class_id, '$roll_no', '$admission_date', '$status', '$photo'
                )";
        
        if ($conn->query($sql)) {
            $student_id = $conn->insert_id;
            $active_session = $settings['session_year'] ?? '2025-2026';
            $conn->query("INSERT INTO student_enrollments (student_id, class_id, session_year, roll_no, enrollment_date, status) VALUES ($student_id, $class_id, '$active_session', '$roll_no', '$admission_date', 'Active')");
            
            $action_type = $_POST['action_type'] ?? 'finalize';
            if ($action_type === 'finalize_and_print') {
                header("Location: " . BASE_URL . "modules/students/admission_form.php?id=$student_id&auto_print=1");
                exit;
            } else {
                header("Location: " . BASE_URL . "modules/students/students.php?class_id=$class_id&msg=added");
                exit;
            }
        } else {
            $err = 'Database error: ' . $conn->error;
        }
    }
}

// Fetch classes (Naturally sorted)
$classes_arr = get_all_classes($conn);


// Fetch last registered student
$last_std_q = $conn->query("SELECT admission_no, name FROM students ORDER BY id DESC LIMIT 1");
$last_std = $last_std_q ? $last_std_q->fetch_assoc() : null;
?>

<style>
.registration-container { max-width: 1100px; margin: 0 auto; padding-bottom: 50px; }
.reg-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-md); }
.reg-header { background: linear-gradient(135deg, #00b894, #0984e3); padding: 25px 40px; color: #fff; display: flex; justify-content: space-between; align-items: center; }
.reg-header h2 { margin: 0; font-size: 1.6rem; font-weight: 800; }
.reg-header p { margin: 3px 0 0; opacity: 0.9; font-size: 0.85rem; }
.last-reg-info { background: rgba(255,255,255,0.15); padding: 8px 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); text-align: right; backdrop-filter: blur(5px); }
.last-reg-info div { font-size: 0.65rem; text-transform: uppercase; font-weight: 700; opacity: 0.8; margin-bottom: 2px; }
.last-reg-info strong { font-size: 1rem; display: block; }
.reg-body { padding: 35px 40px; }
.form-section { margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 25px; }
.form-section:last-child { border-bottom: none; padding-bottom: 0; }
.section-title { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; color: var(--accent-light); }
.section-title i { font-size: 1.1rem; }
.section-title h3 { margin: 0; font-size: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
.reg-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px 25px; }
.photo-upload-container { grid-column: span 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 2px dashed var(--border); border-radius: var(--radius); padding: 15px; background: var(--bg-secondary); }
.photo-preview { width: 100px; height: 120px; border-radius: 6px; background: var(--border); margin-bottom: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--text-muted); overflow: hidden; }
.photo-preview img { width: 100%; height: 100%; object-fit: cover; }
.full-width { grid-column: span 4; }
.span-2 { grid-column: span 2; }
.span-3 { grid-column: span 3; }
@media (max-width: 992px) { .reg-grid { grid-template-columns: repeat(3, 1fr); } .span-3, .full-width { grid-column: span 3; } }
@media (max-width: 768px) { .reg-grid { grid-template-columns: 1fr 1fr; } .span-3, .full-width, .span-2 { grid-column: span 2; } .reg-header { flex-direction: column; align-items: flex-start; gap: 15px; } .last-reg-info { text-align: left; width: 100%; } }
</style>

<div class="registration-container">
    <div class="reg-card">
        <div class="reg-header">
            <div>
                <h2>Student Admission</h2>
                <p>Registering new student for Academic Session <?= $settings['session_year'] ?? date('Y') ?></p>
            </div>
            <?php if($last_std): ?>
            <div class="last-reg-info">
                <div>Reg. No. of Last Regd. Student is</div>
                <strong><?= htmlspecialchars($last_std['admission_no']) ?> — <?= htmlspecialchars($last_std['name']) ?></strong>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="reg-body">
            <?php if ($err): ?><div class="login-error" style="margin-bottom:20px"><?= $err ?></div><?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                
                <!-- Section 1: Registration Basic -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-id-card"></i>
                        <h3>Registration Basic</h3>
                    </div>
                    <div class="reg-grid">
                        <?php if(is_field_enabled('admission_no', $settings)): ?>
                        <div class="form-group span-2">
                            <label>Student Reg./Adm No. *</label>
                            <input type="text" name="admission_no" class="form-control" placeholder="Registration#" value="<?= generate_admission_no($conn) ?>">
                            <small style="color:var(--text-muted); font-size:0.65rem">Auto-generated. You can override if needed.</small>
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('family_id', $settings)): ?>
                        <div class="form-group span-2">
                            <label>Family Number / Family ID</label>
                            <input type="text" name="family_id" id="familyIdInput" class="form-control" placeholder="Auto-assigned" readonly style="background: var(--bg-secondary); cursor: not-allowed;">
                            <small style="color:var(--text-muted); font-size:0.65rem">Auto-assigned when Father Contact is entered. Siblings share the same Family ID.</small>
                        </div>
                        <?php endif; ?>

                        <div class="form-group span-2">
                            <label>Enrollment Class (Current) *</label>
                            <select name="class_id" id="classSelect" class="form-control" required>
                                <option value="">-- Select a Class --</option>
                                <?php foreach($classes_arr as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'].' - '.$c['section']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Roll No</label>
                            <input type="text" name="roll_no" id="rollNoInput" class="form-control" placeholder="Roll No" list="rollSuggestions">
                            <datalist id="rollSuggestions"></datalist>
                            <div id="rollNotice" style="font-size: 0.6rem; color: #00b894; margin-top: 4px; display: none;">Next available suggested.</div>
                            <div id="takenRolls" style="font-size: 0.55rem; color: #888; margin-top: 2px;"></div>
                        </div>
                        <div class="form-group">
                            <label>Admission Date / Enrollment Date</label>
                            <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Student Information -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-user"></i>
                        <h3>Student Information</h3>
                    </div>
                    <div class="reg-grid">
                        <?php if(is_field_enabled('photo', $settings)): ?>
                        <div class="photo-upload-container">
                            <div class="photo-preview" id="photoPreview"><i class="fa-solid fa-camera"></i></div>
                            <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*" style="font-size: 0.65rem;">
                        </div>
                        <?php endif; ?>
                        
                        <div class="<?= is_field_enabled('photo', $settings) ? 'span-3' : 'full-width' ?>">
                            <div class="reg-grid" style="grid-template-columns: repeat(3, 1fr);">
                                <div class="form-group span-2">
                                    <label>Student Name *</label>
                                    <input type="text" name="name" class="form-control" placeholder="Name" required>
                                </div>
                                <?php if(is_field_enabled('gender', $settings)): ?>
                                <div class="form-group">
                                    <label>Student Gender *</label>
                                    <select name="gender" class="form-control">
                                        <option>Male</option>
                                        <option>Female</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <?php if(is_field_enabled('dob', $settings)): ?>
                                <div class="form-group">
                                    <label>Student Date of birth</label>
                                    <input type="date" name="dob" class="form-control">
                                </div>
                                <?php endif; ?>

                                <?php if(is_field_enabled('admission_class', $settings)): ?>
                                <div class="form-group">
                                    <label>Admission Class (At time of entry)</label>
                                    <select name="admission_class_id" class="form-control">
                                        <option value="0">-- Admission Class --</option>
                                        <?php foreach($classes_arr as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <?php if(is_field_enabled('religion', $settings)): ?>
                                <div class="form-group">
                                    <label>Student Religion</label>
                                    <input type="text" name="religion" class="form-control" placeholder="Religion" value="Islam">
                                </div>
                                <?php endif; ?>

                                <?php if(is_field_enabled('cnic', $settings)): ?>
                                <div class="form-group span-2">
                                    <label>Student Form-B / CNIC</label>
                                    <input type="text" name="cnic" class="form-control" placeholder="Student Form-B">
                                </div>
                                <?php endif; ?>

                                <?php if(is_field_enabled('nationality', $settings)): ?>
                                <div class="form-group">
                                    <label>Nationality</label>
                                    <input type="text" name="nationality" class="form-control" value="Pakistani">
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if(is_field_enabled('address', $settings)): ?>
                        <div class="form-group span-2">
                            <label>Present Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Present Address"></textarea>
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('permanent_address', $settings)): ?>
                        <div class="form-group span-2">
                            <label>Permanent Address</label>
                            <textarea name="permanent_address" class="form-control" rows="2" placeholder="Permanent Address"></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 3: Family & Guardian -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fa-solid fa-users"></i>
                        <h3>Family &amp; Guardian Information</h3>
                    </div>
                    <div class="reg-grid">
                        <?php if(is_field_enabled('father_name', $settings)): ?>
                        <div class="form-group span-2">
                            <label>Father Name *</label>
                            <input type="text" name="father_name" class="form-control" placeholder="Father Name" required>
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('father_occupation', $settings)): ?>
                        <div class="form-group span-2">
                            <label>Father Occupation</label>
                            <input type="text" name="father_occupation" class="form-control" placeholder="Father Occupation">
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('mother_name', $settings)): ?>
                        <div class="form-group">
                            <label>Mother's Name</label>
                            <input type="text" name="mother_name" class="form-control" placeholder="Mother Name">
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('father_phone', $settings)): ?>
                        <div class="form-group">
                            <label>Father Contact (Mobile) *</label>
                            <input type="text" name="father_phone" class="form-control" placeholder="Mobile" required>
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('guardian_name', $settings)): ?>
                        <div class="form-group">
                            <label>Guardian Name (If different)</label>
                            <input type="text" name="guardian_name" class="form-control" placeholder="Guardian Name">
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('guardian_contact', $settings)): ?>
                        <div class="form-group">
                            <label>Guardian Contact No-I</label>
                            <input type="text" name="guardian_contact" class="form-control" placeholder="Mobile">
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('emergency_contact', $settings)): ?>
                        <div class="form-group">
                            <label>Other Contact (Emergency)</label>
                            <input type="text" name="phone" class="form-control" placeholder="Emergency Contact">
                        </div>
                        <?php endif; ?>

                        <?php if(is_field_enabled('email', $settings)): ?>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="Email (Optional)">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 20px; border-top:1px solid var(--border); padding-top:30px; flex-wrap: wrap;">
                    <button type="submit" name="action_type" value="finalize" class="btn btn-primary" style="padding: 12px 30px; font-weight: 700; font-size: 0.95rem;">
                        <i class="fa-solid fa-user-plus"></i> Finalize Admission
                    </button>
                    <button type="submit" name="action_type" value="finalize_and_print" class="btn btn-success" style="padding: 12px 30px; font-weight: 700; font-size: 0.95rem; background: linear-gradient(135deg, #10b981, #059669); color: #fff;">
                        <i class="fa-solid fa-print"></i> Finalize Admission &amp; Print
                    </button>
                    <a href="<?= BASE_URL ?>modules/students/students.php" class="btn btn-secondary" style="padding: 12px 25px; display: flex; align-items: center; gap: 8px;">
                        Back to List
                    </a>
                </div>

                <input type="hidden" name="status" value="Active">
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('photoInput')?.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photoPreview').innerHTML = '<img src="' + e.target.result + '">';
        }
        reader.readAsDataURL(this.files[0]);
    }
});

// Auto-suggest next roll number
document.getElementById('classSelect').addEventListener('change', function() {
    var classId = this.value;
    var rollInput = document.getElementById('rollNoInput');
    var notice = document.getElementById('rollNotice');
    var takenDiv = document.getElementById('takenRolls');
    var suggestions = document.getElementById('rollSuggestions');
    
    if (classId) {
        fetch('<?= BASE_URL ?>api/data.php?action=get_next_roll_no&class_id=' + classId)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    rollInput.value = res.next_roll;
                    notice.style.display = 'block';
                    
                    if (res.taken && res.taken.length > 0) {
                        takenDiv.innerHTML = 'Taken in this class: ' + res.taken.join(', ');
                    } else {
                        takenDiv.innerHTML = 'No roll numbers assigned yet.';
                    }
                    
                    suggestions.innerHTML = '';
                    for(var i=0; i<5; i++) {
                        var opt = document.createElement('option');
                        opt.value = parseInt(res.next_roll) + i;
                        suggestions.appendChild(opt);
                    }

                    setTimeout(function() { notice.style.display = 'none'; }, 5000);
                }
            });
    } else {
        rollInput.value = '';
        notice.style.display = 'none';
        takenDiv.innerHTML = '';
        suggestions.innerHTML = '';
    }
});

// Auto-lookup Family ID when father phone is entered
(function() {
    var fatherPhoneInput = document.querySelector('input[name="father_phone"]');
    var familyIdInput = document.getElementById('familyIdInput');
    if (!fatherPhoneInput || !familyIdInput) return;
    
    var debounceTimer = null;
    
    fatherPhoneInput.addEventListener('input', function() {
        var phone = this.value.trim();
        clearTimeout(debounceTimer);
        
        if (phone.length < 4) {
            familyIdInput.value = '';
            familyIdInput.style.borderColor = '';
            var oldNotice = document.getElementById('familyNotice');
            if (oldNotice) oldNotice.remove();
            return;
        }
        
        debounceTimer = setTimeout(function() {
            fetch('<?= BASE_URL ?>api/data.php?action=lookup_family_id&father_phone=' + encodeURIComponent(phone))
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        familyIdInput.value = res.family_id;
                        
                        // Remove old notice
                        var oldNotice = document.getElementById('familyNotice');
                        if (oldNotice) oldNotice.remove();
                        
                        // Add notice
                        var notice = document.createElement('div');
                        notice.id = 'familyNotice';
                        notice.style.fontSize = '0.65rem';
                        notice.style.marginTop = '4px';
                        notice.style.padding = '4px 8px';
                        notice.style.borderRadius = '4px';
                        
                        if (res.is_existing) {
                            familyIdInput.style.borderColor = '#00b894';
                            notice.style.color = '#00b894';
                            notice.style.background = 'rgba(0,184,148,0.08)';
                            notice.innerHTML = '<i class="fa-solid fa-link"></i> Sibling found: <strong>' + res.sibling_name + '</strong> (F: ' + res.father_name + ') — Same family group assigned.';
                        } else {
                            familyIdInput.style.borderColor = '#0984e3';
                            notice.style.color = '#0984e3';
                            notice.style.background = 'rgba(9,132,227,0.08)';
                            notice.innerHTML = '<i class="fa-solid fa-plus-circle"></i> New family group will be created.';
                        }
                        familyIdInput.parentNode.appendChild(notice);
                    }
                });
        }, 400);
    });
})();
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
