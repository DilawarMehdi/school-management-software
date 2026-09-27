<?php
/**
 * SIAX SMSS - FALL Term/Semester Student Overview
 * View and manage students enrolled in the academic year/session.
 */
$page_title = 'FALL Semester';
$active_page = 'fall';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'teacher', 'accountant');

$settings = all_settings($conn);
$active_session = $settings['session_year'] ?? '2025-2026';

// Get total students enrolled in this session year
// In this database, students are linked to a class. Let's count active students.
$stats = [];
$r = $conn->query("SELECT COUNT(*) as total FROM students WHERE status='Active'");
$stats['total_active'] = $r ? $r->fetch_assoc()['total'] : 0;

$r = $conn->query("SELECT COUNT(*) as total FROM classes");
$stats['total_classes'] = $r ? $r->fetch_assoc()['total'] : 0;

// Search filter
$search = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$class_id = (int)($_GET['class_id'] ?? 0);

// Fetch classes (Naturally sorted)
$classes_arr = get_all_classes($conn);


// Build query
$where = "s.status='Active'";
if ($search) {
    $where .= " AND (s.name LIKE '%$search%' OR s.admission_no LIKE '%$search%' OR s.father_name LIKE '%$search%')";
}
if ($class_id) {
    $where .= " AND s.class_id=$class_id";
}

$students_q = $conn->query("
    SELECT s.*, c.name as class_name, c.section 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    WHERE $where 
    ORDER BY c.name, (s.roll_no+0), s.name
    LIMIT 100
");
?>

<div class="main-container">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-snowflake" style="color: #00b894;"></i> FALL Admission &amp; Semester</h1>
            <p>Overview of active admissions and student enrollment details for session: <strong><?= htmlspecialchars($active_session) ?></strong></p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?= BASE_URL ?>modules/students/register_student.php" class="btn btn-primary">
                <i class="fa-solid fa-user-plus"></i> New Admission
            </a>
            <a href="<?= BASE_URL ?>modules/fall/session_management.php" class="btn btn-secondary">
                <i class="fa-solid fa-calendar-check"></i> Manage Sessions
            </a>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px;">
        <div style="background: var(--bg-card); border: 1px solid var(--border); padding: 20px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(0, 184, 148, 0.1); color: #00b894; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Active Students</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin-top: 3px;"><?= $stats['total_active'] ?></div>
            </div>
        </div>

        <div style="background: var(--bg-card); border: 1px solid var(--border); padding: 20px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(9, 132, 227, 0.1); color: #0984e3; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fa-solid fa-school"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Total Classes</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin-top: 3px;"><?= $stats['total_classes'] ?></div>
            </div>
        </div>

        <div style="background: var(--bg-card); border: 1px solid var(--border); padding: 20px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(108, 92, 231, 0.1); color: #6c5ce7; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Current Session</div>
                <div style="font-size: 1.2rem; font-weight: 800; color: var(--text-primary); margin-top: 5px;"><?= htmlspecialchars($active_session) ?></div>
            </div>
        </div>
    </div>

    <!-- Student List Card -->
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <h3 style="margin: 0; color: var(--text-primary);"><i class="fa-solid fa-list-check" style="color: #00b894; margin-right: 8px;"></i> Student Directory</h3>
            
            <form method="GET" style="display: flex; gap: 10px; margin: 0; flex-wrap: wrap;">
                <select name="class_id" class="form-control" style="width: 180px; padding: 6px 12px;">
                    <option value="">-- All Classes --</option>
                    <?php foreach ($classes_arr as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $class_id == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name'] . ' ' . $c['section']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="search" class="form-control" style="width: 200px; padding: 6px 12px;" placeholder="Search student name, reg#..." value="<?= htmlspecialchars($search) ?>">

                <button type="submit" class="btn btn-primary" style="padding: 6px 15px;">Search</button>
                <?php if($search || $class_id): ?>
                    <a href="fall.php" class="btn btn-secondary" style="padding: 6px 15px; text-decoration: none; display: flex; align-items: center;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrapper" style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">Roll No</th>
                        <th>Reg#</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Class</th>
                        <th>Admission Date</th>
                        <th>Contact No</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students_q && $students_q->num_rows > 0): while ($s = $students_q->fetch_assoc()): ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700;"><?= htmlspecialchars($s['roll_no'] ?: '-') ?></td>
                            <td><strong><?= htmlspecialchars($s['admission_no']) ?></strong></td>
                            <td>
                                <a href="<?= BASE_URL ?>modules/students/student_profile.php?id=<?= $s['id'] ?>" style="font-weight: 600; color: var(--accent-light); text-decoration: none;">
                                    <?= htmlspecialchars($s['name']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($s['father_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($s['class_name'] . ' ' . $s['section']) ?></td>
                            <td><?= $s['admission_date'] !== '0000-00-00' ? date('d M Y', strtotime($s['admission_date'])) : '-' ?></td>
                            <td><?= htmlspecialchars($s['father_phone'] ?: ($s['phone'] ?: '-')) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No active students found matching filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
