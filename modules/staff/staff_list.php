<?php
/**
 * SIAX SMSS - Staff Members List
 * Manage school staff records with details, salary associations, and active statuses.
 */
$page_title = 'Staff List';
$active_page = 'staff_list';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal');

$msg = ''; $err = '';

// Load dropdown data
$depts_q = $conn->query("SELECT * FROM staff_departments ORDER BY name");
$depts = [];
if ($depts_q) while($d=$depts_q->fetch_assoc()) $depts[]=$d;

$desigs_q = $conn->query("SELECT * FROM staff_designations ORDER BY title");
$desigs = [];
if ($desigs_q) while($d=$desigs_q->fetch_assoc()) $desigs[]=$d;

$templates_q = $conn->query("SELECT * FROM salary_templates ORDER BY name");
$templates = [];
if ($templates_q) while($t=$templates_q->fetch_assoc()) $templates[]=$t;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $staff_code = $conn->real_escape_string(trim($_POST['staff_code'] ?? ''));
    $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $father_name = $conn->real_escape_string(trim($_POST['father_name'] ?? ''));
    $gender = $conn->real_escape_string($_POST['gender'] ?? 'Male');
    $dob = $conn->real_escape_string($_POST['dob'] ?? '');
    $joining_date = $conn->real_escape_string($_POST['joining_date'] ?? '');
    $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $address = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $dept_id = (int)($_POST['department_id'] ?? 0);
    $desig_id = (int)($_POST['designation_id'] ?? 0);
    $tmpl_id = (int)($_POST['salary_template_id'] ?? 0);
    $qualification = $conn->real_escape_string(trim($_POST['qualification'] ?? ''));
    $experience = $conn->real_escape_string(trim($_POST['experience'] ?? ''));
    $basic_salary = (float)($_POST['basic_salary'] ?? 0.00);
    $status = $conn->real_escape_string($_POST['status'] ?? 'Active');

    if ($_POST['action'] === 'add') {
        if ($staff_code && $name) {
            // Check uniqueness
            $check = $conn->query("SELECT COUNT(*) as total FROM staff_members WHERE staff_code='$staff_code'");
            if ($check && $check->fetch_assoc()['total'] > 0) {
                $err = "Staff Code '$staff_code' is already assigned to another member.";
            } else {
                $sql = "INSERT INTO staff_members 
                        (staff_code, name, father_name, gender, dob, joining_date, phone, email, address, department_id, designation_id, salary_template_id, qualification, experience, basic_salary, status) 
                        VALUES 
                        ('$staff_code', '$name', '$father_name', '$gender', " . ($dob ? "'$dob'" : "NULL") . ", " . ($joining_date ? "'$joining_date'" : "NULL") . ", '$phone', '$email', '$address', " . ($dept_id ?: "NULL") . ", " . ($desig_id ?: "NULL") . ", " . ($tmpl_id ?: "NULL") . ", '$qualification', '$experience', $basic_salary, '$status')";
                if ($conn->query($sql)) {
                    $msg = "Staff member '$name' registered successfully.";
                } else {
                    $err = "Error adding staff: " . $conn->error;
                }
            }
        } else {
            $err = "Staff Code and Name are required.";
        }
    }

    if ($_POST['action'] === 'update') {
        $id = (int)$_POST['id'];
        if ($id && $staff_code && $name) {
            // Check uniqueness
            $check = $conn->query("SELECT COUNT(*) as total FROM staff_members WHERE staff_code='$staff_code' AND id != $id");
            if ($check && $check->fetch_assoc()['total'] > 0) {
                $err = "Staff Code '$staff_code' is already assigned to another member.";
            } else {
                $sql = "UPDATE staff_members SET 
                        staff_code='$staff_code', 
                        name='$name', 
                        father_name='$father_name', 
                        gender='$gender', 
                        dob=" . ($dob ? "'$dob'" : "NULL") . ", 
                        joining_date=" . ($joining_date ? "'$joining_date'" : "NULL") . ", 
                        phone='$phone', 
                        email='$email', 
                        address='$address', 
                        department_id=" . ($dept_id ?: "NULL") . ", 
                        designation_id=" . ($desig_id ?: "NULL") . ", 
                        salary_template_id=" . ($tmpl_id ?: "NULL") . ", 
                        qualification='$qualification', 
                        experience='$experience', 
                        basic_salary=$basic_salary, 
                        status='$status' 
                        WHERE id=$id";
                if ($conn->query($sql)) {
                    $msg = "Staff details updated successfully.";
                } else {
                    $err = "Error updating staff: " . $conn->error;
                }
            }
        } else {
            $err = "Staff Code, Name and ID are required.";
        }
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            if ($conn->query("DELETE FROM staff_members WHERE id=$id")) {
                $msg = "Staff member deleted successfully.";
            } else {
                $err = "Error deleting staff: " . $conn->error;
            }
        }
    }
}

// Fetch staff members with related details
$q = $conn->query("
    SELECT s.*, d.name as dept_name, des.title as desig_title, t.name as template_name 
    FROM staff_members s 
    LEFT JOIN staff_departments d ON s.department_id = d.id 
    LEFT JOIN staff_designations des ON s.designation_id = des.id 
    LEFT JOIN salary_templates t ON s.salary_template_id = t.id 
    ORDER BY s.staff_code
");
$staff_list = [];
if ($q) while($row = $q->fetch_assoc()) $staff_list[] = $row;

// Generate helper staff code
$last_q = $conn->query("SELECT staff_code FROM staff_members ORDER BY id DESC LIMIT 1");
$last_code = $last_q && $last_q->num_rows > 0 ? $last_q->fetch_assoc()['staff_code'] : 'STF-1000';
$num = (int)filter_var($last_code, FILTER_SANITIZE_NUMBER_INT);
$suggested_code = 'STF-' . ($num + 1);
?>

<div class="main-container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0; color: var(--text-primary);">Staff List</h1>
            <p>Manage school instructors, staff profiles, salaries and active status logs.</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn btn-secondary" onclick="openAllQrModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 700; border-radius: 6px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border: none;">
                <i class="fa-solid fa-qrcode"></i> Generate All QR Codes
            </button>
            <button class="btn btn-primary" onclick="openAddModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-weight: 700; border-radius: 6px;">
                <i class="fa-solid fa-user-plus"></i> Register Staff
            </button>
        </div>
    </div>

    <?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:20px;"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="login-error" style="margin-bottom:20px;"><?= $err ?></div><?php endif; ?>

    <!-- Main Card containing the Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0; overflow: hidden; margin-bottom: 30px;">
        <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; background: rgba(255,255,255,0.02); display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-users" style="color: #00b894; font-size: 1.1rem;"></i>
            <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary);">Staff Directory</h3>
        </div>

        <div class="table-wrapper" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 12px 15px; text-align: center; font-weight: 700; width: 5%;">QR</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: 700; width: 9%;">Code</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: 700; width: 18%;">Name</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: 700; width: 13%;">Department</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: 700; width: 13%;">Designation</th>
                        <th style="padding: 12px 15px; text-align: right; font-weight: 700; width: 11%;">Basic Salary</th>
                        <th style="padding: 12px 15px; text-align: left; font-weight: 700; width: 11%;">Salary Template</th>
                        <th style="padding: 12px 15px; text-align: center; font-weight: 700; width: 7%;">Status</th>
                        <th style="padding: 12px 15px; text-align: center; font-weight: 700; width: 8%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staff_list)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">No staff members found. Click the button above to add one.</td>
                        </tr>
                    <?php else: foreach ($staff_list as $s): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px 15px; text-align: center;">
                                <button onclick='showQrModal(<?= json_encode(["id" => $s["id"], "staff_code" => $s["staff_code"], "name" => $s["name"], "designation" => $s["desig_title"] ?? "", "department" => $s["dept_name"] ?? ""]) ?>)' 
                                    style="background: linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; border:none; border-radius:8px; width:38px; height:38px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; transition: transform 0.2s, box-shadow 0.2s;" 
                                    onmouseover="this.style.transform='scale(1.15)'; this.style.boxShadow='0 4px 15px rgba(99,102,241,.4)'" 
                                    onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none'" 
                                    title="View QR Code">
                                    <i class="fa-solid fa-qrcode"></i>
                                </button>
                            </td>
                            <td style="padding: 15px 15px; font-weight: 700; color: var(--text-primary); font-size: 0.9rem;">
                                <?= htmlspecialchars($s['staff_code']) ?>
                            </td>
                            <td style="padding: 15px 15px; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                <?= htmlspecialchars($s['name']) ?>
                                <small style="display:block; color: var(--text-secondary); font-weight: normal; font-size: 0.75rem; margin-top:2px;">Ph: <?= htmlspecialchars($s['phone'] ?: '-') ?></small>
                            </td>
                            <td style="padding: 15px 15px; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= htmlspecialchars($s['dept_name'] ?: '-') ?>
                            </td>
                            <td style="padding: 15px 15px; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= htmlspecialchars($s['desig_title'] ?: '-') ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: right; font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                                <?= number_format($s['basic_salary'], 2) ?>
                            </td>
                            <td style="padding: 15px 15px; color: #00b894; font-weight: 600; font-size: 0.9rem;">
                                <?= htmlspecialchars($s['template_name'] ?: 'None (Custom)') ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: center; font-size: 0.8rem;">
                                <?php if ($s['status'] === 'Active'): ?>
                                    <span class="badge" style="background: rgba(0, 184, 148, 0.15); color: #00b894; padding: 4px 8px; border-radius: 4px; font-weight: 700;">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 4px 8px; border-radius: 4px; font-weight: 700;"><?= $s['status'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px 15px; text-align: center; font-size: 0.85rem;">
                                <a onclick="openUpdateModal(<?= htmlspecialchars(json_encode($s)) ?>)" style="color: #0984e3; margin-right: 12px; cursor: pointer; font-weight:600;"><i class="fa-solid fa-edit"></i> Edit</a>
                                <a onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')" style="color: #ef4444; cursor: pointer; font-weight:600;"><i class="fa-solid fa-trash-can"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- REGISTER STAFF MODAL -->
<div id="addModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 650px; width: 95%; overflow: hidden; box-shadow: var(--shadow-lg); max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-user-plus" style="color: #0984e3; margin-right: 8px;"></i> Register Staff Member</h3>
            <button onclick="closeAddModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px; overflow-y: auto;">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Staff Code *</label>
                        <input type="text" name="staff_code" class="form-control" value="<?= $suggested_code ?>" required>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Full Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="Muhammad Ali" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Father Name</label>
                        <input type="text" name="father_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Gender</label>
                        <select name="gender" class="form-control">
                            <option>Male</option>
                            <option>Female</option>
                            <option>Other</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Date of Birth</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Joining Date</label>
                        <input type="date" name="joining_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="03XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="ali@school.com">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Department</label>
                        <select name="department_id" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach ($depts as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Designation</label>
                        <select name="designation_id" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach ($desigs as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Basic Salary *</label>
                        <input type="number" step="0.01" name="basic_salary" class="form-control" value="0.00" required>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Salary Template</label>
                        <select name="salary_template_id" class="form-control">
                            <option value="">-- Custom --</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Qualification</label>
                        <input type="text" name="qualification" class="form-control" placeholder="M.Phil English">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Experience</label>
                        <input type="text" name="experience" class="form-control" placeholder="5 Years">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Residential Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Status</label>
                    <select name="status" class="form-control">
                        <option>Active</option>
                        <option>Resigned</option>
                        <option>Suspended</option>
                        <option>Terminated</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 15px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- UPDATE STAFF MODAL -->
<div id="updateModal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="modal-box" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); max-width: 650px; width: 95%; overflow: hidden; box-shadow: var(--shadow-lg); max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-pen-to-square" style="color: #00b894; margin-right: 8px;"></i> Edit Staff Details</h3>
            <button onclick="closeUpdateModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px; overflow-y: auto;">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Staff Code *</label>
                        <input type="text" name="staff_code" id="edit_staff_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Full Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Father Name</label>
                        <input type="text" name="father_name" id="edit_father_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Gender</label>
                        <select name="gender" id="edit_gender" class="form-control">
                            <option>Male</option>
                            <option>Female</option>
                            <option>Other</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Date of Birth</label>
                        <input type="date" name="dob" id="edit_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Joining Date</label>
                        <input type="date" name="joining_date" id="edit_joining_date" class="form-control">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Department</label>
                        <select name="department_id" id="edit_department_id" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach ($depts as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Designation</label>
                        <select name="designation_id" id="edit_designation_id" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach ($desigs as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Basic Salary *</label>
                        <input type="number" step="0.01" name="basic_salary" id="edit_basic_salary" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Salary Template</label>
                        <select name="salary_template_id" id="edit_salary_template_id" class="form-control">
                            <option value="">-- Custom --</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Qualification</label>
                        <input type="text" name="qualification" id="edit_qualification" class="form-control">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Experience</label>
                        <input type="text" name="experience" id="edit_experience" class="form-control">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Residential Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 0.8rem; margin-bottom: 5px; display: block;">Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option>Active</option>
                        <option>Resigned</option>
                        <option>Suspended</option>
                        <option>Terminated</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 15px;">
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #00b894;">Update Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE FORM -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<!-- SINGLE QR CODE MODAL -->
<div id="qrModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.65); z-index:1060; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; max-width:440px; width:95%; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6); padding:18px 24px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; color:#fff; font-size:1.05rem; font-weight:700;"><i class="fa-solid fa-qrcode" style="margin-right:8px;"></i> Staff QR Code</h3>
            <button onclick="closeQrModal()" style="background:rgba(255,255,255,0.2); border:none; color:#fff; cursor:pointer; font-size:1.1rem; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:30px; text-align:center;">
            <div id="qr_staff_info" style="margin-bottom:20px;">
                <h2 id="qr_staff_name" style="margin:0 0 4px 0; font-size:1.2rem; font-weight:800; color:var(--text-primary);"></h2>
                <p id="qr_staff_code" style="margin:0 0 2px 0; font-size:0.85rem; color:#6366f1; font-weight:700;"></p>
                <p id="qr_staff_desig" style="margin:0; font-size:0.8rem; color:var(--text-muted);"></p>
            </div>
            <div id="qr_canvas_wrap" style="display:inline-block; padding:20px; background:#fff; border-radius:12px; border:2px solid #e5e7eb; margin-bottom:20px;"></div>
            <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                <button onclick="downloadQrPng()" class="btn" style="background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:10px 22px; font-weight:700; border-radius:8px; display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                    <i class="fa-solid fa-image"></i> Download PNG
                </button>
                <button onclick="downloadQrPdf()" class="btn" style="background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border:none; padding:10px 22px; font-weight:700; border-radius:8px; display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                    <i class="fa-solid fa-file-pdf"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ALL QR CODES MODAL -->
<div id="allQrModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.65); z-index:1060; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:16px; max-width:900px; width:95%; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden; max-height:90vh; display:flex; flex-direction:column;">
        <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6); padding:18px 24px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; color:#fff; font-size:1.05rem; font-weight:700;"><i class="fa-solid fa-qrcode" style="margin-right:8px;"></i> All Staff QR Codes</h3>
            <div style="display:flex; gap:10px; align-items:center;">
                <button onclick="downloadAllPdf()" style="background:rgba(255,255,255,0.2); border:none; color:#fff; cursor:pointer; font-weight:700; padding:8px 18px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; font-size:0.85rem;"><i class="fa-solid fa-file-pdf"></i> Download All PDF</button>
                <button onclick="closeAllQrModal()" style="background:rgba(255,255,255,0.2); border:none; color:#fff; cursor:pointer; font-size:1.1rem; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div id="allQrGrid" style="padding:24px; overflow-y:auto; display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:20px;"></div>
    </div>
</div>

<!-- QR Code Libraries -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
let currentQrStaff = null;

function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}
function openUpdateModal(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_staff_code').value = s.staff_code;
    document.getElementById('edit_name').value = s.name;
    document.getElementById('edit_father_name').value = s.father_name;
    document.getElementById('edit_gender').value = s.gender;
    document.getElementById('edit_dob').value = s.dob;
    document.getElementById('edit_joining_date').value = s.joining_date;
    document.getElementById('edit_phone').value = s.phone;
    document.getElementById('edit_email').value = s.email;
    document.getElementById('edit_address').value = s.address;
    document.getElementById('edit_department_id').value = s.department_id || '';
    document.getElementById('edit_designation_id').value = s.designation_id || '';
    document.getElementById('edit_salary_template_id').value = s.salary_template_id || '';
    document.getElementById('edit_basic_salary').value = s.basic_salary;
    document.getElementById('edit_qualification').value = s.qualification;
    document.getElementById('edit_experience').value = s.experience;
    document.getElementById('edit_status').value = s.status;
    document.getElementById('updateModal').style.display = 'flex';
}
function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
}
function confirmDelete(id, name) {
    if (confirm("Are you sure you want to delete staff member '" + name + "'?")) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// ========== QR CODE FUNCTIONS ==========

function showQrModal(staffData) {
    currentQrStaff = staffData;
    document.getElementById('qr_staff_name').textContent = staffData.name;
    document.getElementById('qr_staff_code').textContent = staffData.staff_code;
    document.getElementById('qr_staff_desig').textContent = [staffData.designation, staffData.department].filter(Boolean).join(' — ') || 'Staff Member';

    const wrap = document.getElementById('qr_canvas_wrap');
    wrap.innerHTML = '';

    const qrPayload = JSON.stringify({
        type: 'SIAX_STAFF_ATT',
        staff_id: staffData.id,
        staff_code: staffData.staff_code,
        name: staffData.name
    });

    new QRCode(wrap, {
        text: qrPayload,
        width: 220,
        height: 220,
        colorDark: '#1e1b4b',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });

    document.getElementById('qrModal').style.display = 'flex';
}

function closeQrModal() {
    document.getElementById('qrModal').style.display = 'none';
}

function downloadQrPng() {
    const canvas = document.querySelector('#qr_canvas_wrap canvas');
    if (!canvas) { alert('QR code not generated yet.'); return; }

    // Create a branded PNG with staff info
    const exportCanvas = document.createElement('canvas');
    const ctx = exportCanvas.getContext('2d');
    const padding = 40;
    const qrSize = 220;
    const headerHeight = 80;
    const footerHeight = 40;
    exportCanvas.width = qrSize + (padding * 2);
    exportCanvas.height = headerHeight + qrSize + (padding * 2) + footerHeight;

    // White background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);

    // Header gradient
    const grad = ctx.createLinearGradient(0, 0, exportCanvas.width, 0);
    grad.addColorStop(0, '#6366f1');
    grad.addColorStop(1, '#8b5cf6');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, exportCanvas.width, headerHeight);

    // Staff Name
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 16px Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(currentQrStaff.name, exportCanvas.width / 2, 35);

    // Staff Code
    ctx.font = '13px Arial, sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.85)';
    ctx.fillText(currentQrStaff.staff_code, exportCanvas.width / 2, 58);

    // QR Code
    ctx.drawImage(canvas, padding, headerHeight + 20);

    // Border around QR
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 2;
    ctx.strokeRect(padding - 5, headerHeight + 15, qrSize + 10, qrSize + 10);

    // Footer branding
    ctx.fillStyle = '#94a3b8';
    ctx.font = '10px Arial, sans-serif';
    ctx.fillText('Generated by SIAC Technologies', exportCanvas.width / 2, exportCanvas.height - 15);

    const link = document.createElement('a');
    link.download = 'QR_' + currentQrStaff.staff_code + '.png';
    link.href = exportCanvas.toDataURL('image/png');
    link.click();
}

function downloadQrPdf() {
    const canvas = document.querySelector('#qr_canvas_wrap canvas');
    if (!canvas) { alert('QR code not generated yet.'); return; }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: [100, 130] });

    // Header gradient bar
    doc.setFillColor(99, 102, 241);
    doc.rect(0, 0, 100, 22, 'F');

    // Staff Name
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(14);
    doc.setFont(undefined, 'bold');
    doc.text(currentQrStaff.name, 50, 10, { align: 'center' });

    // Staff Code
    doc.setFontSize(10);
    doc.setFont(undefined, 'normal');
    doc.text(currentQrStaff.staff_code, 50, 18, { align: 'center' });

    // QR Code image
    const qrImg = canvas.toDataURL('image/png');
    doc.addImage(qrImg, 'PNG', 18, 28, 64, 64);

    // Border around QR
    doc.setDrawColor(200, 200, 200);
    doc.rect(16, 26, 68, 68);

    // Designation
    doc.setTextColor(100, 100, 100);
    doc.setFontSize(9);
    const desigText = [currentQrStaff.designation, currentQrStaff.department].filter(Boolean).join(' — ') || 'Staff Member';
    doc.text(desigText, 50, 102, { align: 'center' });

    // Footer branding
    doc.setTextColor(160, 160, 160);
    doc.setFontSize(7);
    doc.text('Generated by SIAC Technologies', 50, 126, { align: 'center' });

    doc.save('QR_' + currentQrStaff.staff_code + '.pdf');
}

// ========== ALL QR CODES ==========

const allStaffData = <?= json_encode(array_map(function($s) {
    return [
        'id' => $s['id'],
        'staff_code' => $s['staff_code'],
        'name' => $s['name'],
        'designation' => $s['desig_title'] ?? '',
        'department' => $s['dept_name'] ?? ''
    ];
}, $staff_list)) ?>;

function openAllQrModal() {
    const grid = document.getElementById('allQrGrid');
    grid.innerHTML = '';

    allStaffData.forEach(function(staff) {
        const card = document.createElement('div');
        card.style.cssText = 'background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; text-align:center;';

        card.innerHTML = '<h4 style="margin:0 0 2px 0; font-size:0.9rem; font-weight:800; color:#1e1b4b;">' + escapeHtml(staff.name) + '</h4>' +
            '<p style="margin:0 0 10px 0; font-size:0.75rem; color:#6366f1; font-weight:600;">' + escapeHtml(staff.staff_code) + '</p>' +
            '<div class="qr-item" data-staff-id="' + staff.id + '" data-staff-code="' + escapeHtml(staff.staff_code) + '" data-staff-name="' + escapeHtml(staff.name) + '"></div>' +
            '<div style="margin-top:10px; display:flex; gap:6px; justify-content:center;">' +
            '<button onclick="downloadSingleQrPng(this)" style="background:#10b981; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:0.72rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;"><i class="fa-solid fa-image"></i> PNG</button>' +
            '<button onclick="downloadSingleQrPdf(this)" style="background:#ef4444; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:0.72rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;"><i class="fa-solid fa-file-pdf"></i> PDF</button>' +
            '</div>';

        grid.appendChild(card);

        // Generate QR inside the card
        const qrDiv = card.querySelector('.qr-item');
        const qrPayload = JSON.stringify({
            type: 'SIAX_STAFF_ATT',
            staff_id: staff.id,
            staff_code: staff.staff_code,
            name: staff.name
        });

        new QRCode(qrDiv, {
            text: qrPayload,
            width: 140,
            height: 140,
            colorDark: '#1e1b4b',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    });

    document.getElementById('allQrModal').style.display = 'flex';
}

function closeAllQrModal() {
    document.getElementById('allQrModal').style.display = 'none';
}

function downloadSingleQrPng(btn) {
    const card = btn.closest('div').parentElement;
    const qrDiv = card.querySelector('.qr-item');
    const canvas = qrDiv.querySelector('canvas');
    const staffCode = qrDiv.dataset.staffCode;
    const staffName = qrDiv.dataset.staffName;
    if (!canvas) return;

    const exportCanvas = document.createElement('canvas');
    const ctx = exportCanvas.getContext('2d');
    exportCanvas.width = 220;
    exportCanvas.height = 280;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 220, 280);

    const grad = ctx.createLinearGradient(0, 0, 220, 0);
    grad.addColorStop(0, '#6366f1');
    grad.addColorStop(1, '#8b5cf6');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 220, 50);

    ctx.fillStyle = '#fff';
    ctx.font = 'bold 13px Arial';
    ctx.textAlign = 'center';
    ctx.fillText(staffName, 110, 25);
    ctx.font = '11px Arial';
    ctx.fillStyle = 'rgba(255,255,255,0.8)';
    ctx.fillText(staffCode, 110, 42);

    ctx.drawImage(canvas, 40, 60, 140, 140);

    ctx.fillStyle = '#94a3b8';
    ctx.font = '8px Arial';
    ctx.fillText('Generated by SIAC Technologies', 110, 270);

    const link = document.createElement('a');
    link.download = 'QR_' + staffCode + '.png';
    link.href = exportCanvas.toDataURL('image/png');
    link.click();
}

function downloadSingleQrPdf(btn) {
    const card = btn.closest('div').parentElement;
    const qrDiv = card.querySelector('.qr-item');
    const canvas = qrDiv.querySelector('canvas');
    const staffCode = qrDiv.dataset.staffCode;
    const staffName = qrDiv.dataset.staffName;
    if (!canvas) return;

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: [80, 100] });
    doc.setFillColor(99, 102, 241);
    doc.rect(0, 0, 80, 18, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(11);
    doc.setFont(undefined, 'bold');
    doc.text(staffName, 40, 8, { align: 'center' });
    doc.setFontSize(8);
    doc.setFont(undefined, 'normal');
    doc.text(staffCode, 40, 15, { align: 'center' });
    doc.addImage(canvas.toDataURL('image/png'), 'PNG', 12, 22, 56, 56);
    doc.setTextColor(160, 160, 160);
    doc.setFontSize(6);
    doc.text('Generated by SIAC Technologies', 40, 96, { align: 'center' });
    doc.save('QR_' + staffCode + '.pdf');
}

function downloadAllPdf() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    const pageW = 210;
    const pageH = 297;
    const cols = 3;
    const rows = 4;
    const cardW = 60;
    const cardH = 65;
    const marginX = (pageW - (cols * cardW)) / (cols + 1);
    const marginY = 15;
    const qrItems = document.querySelectorAll('#allQrGrid .qr-item');
    let idx = 0;

    qrItems.forEach(function(qrDiv) {
        if (idx > 0 && idx % (cols * rows) === 0) {
            doc.addPage();
        }
        const pageIdx = idx % (cols * rows);
        const col = pageIdx % cols;
        const row = Math.floor(pageIdx / cols);
        const x = marginX + col * (cardW + marginX);
        const y = marginY + row * (cardH + 8);

        const canvas = qrDiv.querySelector('canvas');
        const staffCode = qrDiv.dataset.staffCode;
        const staffName = qrDiv.dataset.staffName;

        // Card background
        doc.setFillColor(249, 250, 251);
        doc.roundedRect(x, y, cardW, cardH, 3, 3, 'F');
        doc.setDrawColor(229, 231, 235);
        doc.roundedRect(x, y, cardW, cardH, 3, 3, 'S');

        // Header bar
        doc.setFillColor(99, 102, 241);
        doc.rect(x, y, cardW, 12, 'F');

        // Name
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(8);
        doc.setFont(undefined, 'bold');
        doc.text(staffName.substring(0, 20), x + cardW / 2, y + 5, { align: 'center' });
        doc.setFontSize(6);
        doc.setFont(undefined, 'normal');
        doc.text(staffCode, x + cardW / 2, y + 10, { align: 'center' });

        // QR
        if (canvas) {
            doc.addImage(canvas.toDataURL('image/png'), 'PNG', x + 10, y + 15, 40, 40);
        }

        // Footer
        doc.setTextColor(160, 160, 160);
        doc.setFontSize(5);
        doc.text('SIAC Technologies', x + cardW / 2, y + cardH - 3, { align: 'center' });

        idx++;
    });

    doc.save('All_Staff_QR_Codes.pdf');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
