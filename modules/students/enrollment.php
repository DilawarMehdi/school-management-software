<?php
/**
 * SIAX SMSS - Student Enrollment Management
 * Premium design based on user request.
 */
ob_start();
$page_title = 'Student Enrollment';
$active_page = 'students';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

$student_id = (int)($_GET['id'] ?? 0);
if ($student_id <= 0) {
    header("Location: " . BASE_URL . "modules/students/students.php");
    exit;
}

// Fetch student
$sq = $conn->query("SELECT name FROM students WHERE id = $student_id");
$student = $sq ? $sq->fetch_assoc() : null;
if (!$student) die("Student not found.");

$msg = ''; $err = '';

// Handle Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'enroll') {
        $class_id = (int)$_POST['class_id'];
        $session = $conn->real_escape_string($_POST['session_year']);
        $roll = $conn->real_escape_string($_POST['roll_no']);
        $shift = $conn->real_escape_string($_POST['shift']);
        $date = $conn->real_escape_string($_POST['enrollment_date']);
        
        // Deactivate previous active enrollments
        $conn->query("UPDATE student_enrollments SET status='Promoted', discharge_date='$date' WHERE student_id=$student_id AND status='Active'");
        
        // Insert new
        if ($conn->query("INSERT INTO student_enrollments (student_id, class_id, session_year, roll_no, shift, enrollment_date, status) VALUES ($student_id, $class_id, '$session', '$roll', '$shift', '$date', 'Active')")) {
            // Update main student table
            $conn->query("UPDATE students SET class_id=$class_id, roll_no='$roll' WHERE id=$student_id");
            $msg = "Student enrolled successfully.";
        }
    }
    
    if ($_POST['action'] === 'discharge') {
        if ($conn->query("DELETE FROM students WHERE id=$student_id")) {
            header("Location: " . BASE_URL . "modules/students/students.php?msg=deleted");
            exit;
        } else {
            $err = "Error deleting student: " . $conn->error;
        }
    }
}

// Fetch history
$history = $conn->query("SELECT e.*, c.name as class_name, c.section 
                         FROM student_enrollments e 
                         JOIN classes c ON e.class_id = c.id 
                         WHERE e.student_id = $student_id 
                         ORDER BY e.enrollment_date DESC");

// Fetch classes for dropdown (Naturally sorted)
$classes_arr = get_all_classes($conn);

?>

<style>
.enrollment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}
.enrollment-header h2 {
    font-size: 1.8rem;
    font-weight: 500;
    color: #444;
}
.enrollment-header h2 span {
    color: #3b82f6;
}

.enroll-btn {
    background: #fff;
    border: 1px solid #3b82f6;
    color: #3b82f6;
    padding: 8px 20px;
    border-radius: 4px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
}
.enroll-btn:hover {
    background: #3b82f6;
    color: #fff;
}

.enroll-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.card-tabs {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 0 15px;
    display: flex;
}
.card-tab {
    padding: 12px 20px;
    border-right: 1px solid #e2e8f0;
    border-left: 1px solid #e2e8f0;
    background: #fff;
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: -1px;
}
.card-tab i { font-size: 1rem; color: #64748b; }

.enroll-table {
    width: 100%;
    border-collapse: collapse;
}
.enroll-table th {
    background: #fff;
    color: #333;
    font-weight: 700;
    font-size: 0.85rem;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
}
.enroll-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.85rem;
    color: #444;
}
.action-links a, .action-links button {
    color: #3b82f6;
    text-decoration: none;
    background: none;
    border: none;
    padding: 0;
    font-size: 0.85rem;
    cursor: pointer;
}
.action-links span { color: #ccc; margin: 0 5px; }

/* Modal Style */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: #fff;
    width: 500px;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
}
</style>

<div class="main-container">
    <div class="enrollment-header">
        <h2>Enrollment of <span><?= htmlspecialchars($student['name']) ?></span></h2>
        <button class="enroll-btn" onclick="openModal('enrollModal')">Enroll Student</button>
    </div>

    <?php if($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;margin-bottom:15px"><?= $msg ?></div><?php endif; ?>

    <div class="enroll-card">
        <div class="card-tabs">
            <div class="card-tab">
                <i class="fa-solid fa-table-cells"></i> Enrollment
            </div>
        </div>
        
        <div class="table-wrapper" style="border:none">
            <table class="enroll-table">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Roll No</th>
                        <th>Shift</th>
                        <th>Enrollment-Date</th>
                        <th>Discharge-Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($history && $history->num_rows > 0): while($h = $history->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['session_year']) ?></td>
                        <td><?= htmlspecialchars($h['class_name']) ?></td>
                        <td><?= htmlspecialchars($h['section']) ?></td>
                        <td><?= htmlspecialchars($h['roll_no'] ?: '--') ?></td>
                        <td><?= htmlspecialchars($h['shift']) ?></td>
                        <td><?= date('d/m/Y', strtotime($h['enrollment_date'])) ?></td>
                        <td><?= $h['discharge_date'] ? date('d/m/Y', strtotime($h['discharge_date'])) : '--' ?></td>
                        <td class="action-links">
                            <?php if($h['status'] === 'Active'): ?>
                            <a href="#" onclick="openUpdateModal(<?= $h['id'] ?>, '<?= $h['roll_no'] ?>')">Update</a>
                            <span>|</span>
                            <a href="#" onclick="openDischargeModal(<?= $h['id'] ?>)">Discharge-Student</a>
                            <?php else: ?>
                            <span style="color:#888"><?= $h['status'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:#999">No enrollment history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Enroll Modal -->
<div id="enrollModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0">Enroll in New Class</h3>
        <form method="POST">
            <input type="hidden" name="action" value="enroll">
            <div class="form-group">
                <label>Academic Session</label>
                <input type="text" name="session_year" class="form-control" value="<?= $settings['session_year'] ?? date('Y').'-'.(date('Y')+1) ?>">
            </div>
            <div class="form-group">
                <label>Class</label>
                <select name="class_id" class="form-control" required>
                    <?php foreach($classes_arr as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'].' - '.$c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Roll Number</label>
                <input type="text" name="roll_no" class="form-control" placeholder="Roll No">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Shift</label>
                    <select name="shift" class="form-control">
                        <option>Morning</option><option>Evening</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Enrollment Date</label>
                    <input type="date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="btn-group mt-3">
                <button type="submit" class="btn btn-primary">Enroll Student</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('enrollModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Discharge Modal -->
<div id="dischargeModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0">Discharge Student</h3>
        <form method="POST">
            <input type="hidden" name="action" value="discharge">
            <input type="hidden" name="enrollment_id" id="discharge_eid">
            <div class="form-group">
                <label>Discharge Date</label>
                <input type="date" name="discharge_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <p style="font-size:0.85rem; color:#666">This will mark the student as inactive and discharge them from the current class.</p>
            <div class="btn-group mt-3">
                <button type="submit" class="btn btn-danger">Confirm Discharge</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('dischargeModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function openDischargeModal(eid) {
    document.getElementById('discharge_eid').value = eid;
    openModal('dischargeModal');
}
function openUpdateModal(eid, roll) {
    // Reuse enroll modal or create update one. For now, just alert.
    alert('Update feature coming soon. You can re-enroll to change class.');
}
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
