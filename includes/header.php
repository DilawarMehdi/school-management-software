<?php
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_login();
$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'SIAX SMSS';
$notif_count = unread_notifications($conn);
$user = current_user();
$role = current_role();

$page_title  = $page_title  ?? 'Dashboard';
$active_page = $active_page ?? '';

// Auto-sync missing student enrollments for the active session
$global_active_session = $settings['session_year'] ?? '2025-2026';
$conn->query("CREATE TABLE IF NOT EXISTS student_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    session_year VARCHAR(50) NOT NULL,
    roll_no VARCHAR(20),
    shift VARCHAR(50) DEFAULT 'Morning',
    enrollment_date DATE,
    discharge_date DATE DEFAULT NULL,
    status ENUM('Active', 'Discharged', 'Promoted') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
)");
$conn->query("
    INSERT INTO student_enrollments (student_id, class_id, session_year, roll_no, enrollment_date, status)
    SELECT id, class_id, '$global_active_session', roll_no, admission_date, 'Active'
    FROM students s
    WHERE NOT EXISTS (
        SELECT 1 FROM student_enrollments se WHERE se.student_id = s.id
    ) AND s.class_id > 0
");

// Auto-fix marks table unique indexes if flawed uniq_mark exists
@$conn->query("ALTER TABLE marks DROP KEY uniq_mark");
@$conn->query("ALTER TABLE marks ADD UNIQUE KEY uniq_student_sched_subject (student_id, schedule_id, subject)");

// Determine which category tab is active
$student_pages = ['students','student_profile','register_student','classes','student_id_cards','admission_form','enrollment','attendance'];
$finance_pages = ['fees', 'fee_criteria', 'advance_fee', 'discount_scholarship', 'average_fee', 'fine_policy', 'monthly_fee_invoices', 'easy_fee', 'student_fee_history', 'chalan_form_settings', 'fee_sms_history', 'sms_connection_settings', 'student_fee_detail'];
$academic_pages = ['exams','results','exam_types','exam_schedule','roll_number_slips','grading_policy','exam_settings'];
$expense_pages  = ['expenses','expense_categories'];
$doc_pages      = ['certificates','reports'];
$system_pages   = ['settings','notifications'];
$portal_pages   = ['portal'];
$fall_pages     = ['fall', 'session_management', 'bulk_enrollment'];
$staff_pages    = ['staff_list', 'staff_attendance', 'departments', 'designations', 'salary_templates', 'salary_slips'];
$parents_pages  = ['parents_portal'];

$dashboard_pages = ['dashboard', 'notifications', 'videos', 'resources'];

if (in_array($active_page, $student_pages))  $active_tab = 'students';
elseif (in_array($active_page, $finance_pages))  $active_tab = 'finance';
elseif (in_array($active_page, $academic_pages)) $active_tab = 'academics';
elseif (in_array($active_page, $parents_pages))  $active_tab = 'parents';
elseif (in_array($active_page, $expense_pages))  $active_tab = 'expenses';
elseif (in_array($active_page, $doc_pages))      $active_tab = 'documents';
elseif (in_array($active_page, $system_pages))   $active_tab = 'system';
elseif (in_array($active_page, $portal_pages))   $active_tab = 'portal';
elseif (in_array($active_page, $fall_pages))     $active_tab = 'fall';
elseif (in_array($active_page, $staff_pages))    $active_tab = 'staff';
else $active_tab = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — <?= htmlspecialchars($school_name) ?></title>
<meta name="description" content="SIAX SMSS School Management Software System">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Font Awesome – local copy (works offline) -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/main.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/print.css" media="print">
<script>
(function() {
  var saved = localStorage.getItem('smss_theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
})();
</script>
<style>
/* Offline font fallback – used when Google Fonts CDN is unreachable */
:root{--font-main:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;}
body{font-family:var(--font-main);}
</style>
<style>
/* FA icon sizing helpers */
.nav-icon-fa{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
.pill-icon{font-size:.72rem;width:16px;text-align:center;flex-shrink:0;opacity:.7}
.sc-icon-fa{font-size:1rem;margin-bottom:2px}
</style>
<style>
/* ── NEW SIDEBAR DESIGN ── */
.sidebar{width:260px;background:#ffffff;border-right:1px solid #e2e8f0;display:flex;flex-direction:column;height:100vh;position:fixed;top:0;left:0;z-index:1000;transition:transform .3s ease, background-color .25s ease, border-color .25s ease}
.sb-brand{padding:15px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:12px; background:#f8fafc;}
.sb-brand-name{color:#0f172a; font-weight:800; font-size:.78rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-transform:uppercase; letter-spacing:0.5px;}
.sb-profile{padding:12px 14px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px}
.sb-avatar{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#00b894,#0984e3);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden}
.sb-avatar img{width:100%;height:100%;object-fit:cover}
.sb-user-name{font-weight:700;font-size:.85rem;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-user-sub{font-size:.65rem;color:#00b894;margin-top:1px;cursor:pointer}
.sb-hide-btn{margin-left:auto;background:none;border:none;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px;flex-shrink:0}
.sb-hide-btn span{display:block;width:16px;height:2px;background:#ef4444;border-radius:2px}
.sb-hide-btn .lbl{font-size:.55rem;color:#ef4444;margin-top:1px}

/* Menu Container */
.sb-menu{flex:1;overflow-y:auto;padding:8px 6px;scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
.sb-menu::-webkit-scrollbar{width:4px}
.sb-menu::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}

/* Menu Item (Category) */
.menu-item{margin-bottom:2px}
.menu-header{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:#475569;font-size:.82rem;font-weight:500;cursor:pointer;transition:all .2s;user-select:none}
.menu-header:hover{background:#f1f5f9;color:#0f172a}
.menu-header.active{color:#00b894;background:rgba(0,184,148,.08)}
.menu-header .m-icon{font-size:1rem;width:24px;text-align:center;color:#00b894}
.menu-header .m-arrow{margin-left:auto;font-size:.7rem;transition:transform .3s;opacity:.5}
.menu-item.expanded .m-arrow{transform:rotate(90deg)}
.menu-item.expanded .menu-header{color:#0f172a;font-weight:600}

/* Submenu */
.submenu{display:none;padding-left:34px;margin-top:2px;margin-bottom:6px}
.menu-item.expanded .submenu{display:block}

/* Nav Pills (Submenu Links) */
.nav-pill{display:flex;align-items:center;gap:8px;padding:6px 12px;margin-bottom:1px;border-radius:6px;color:#64748b;font-size:.78rem;font-weight:500;text-decoration:none;transition:all .2s;border:1px solid transparent}
.nav-pill:hover{color:#0f172a;background:#f1f5f9}
.nav-pill.active{background:rgba(0,184,148,.12);color:#00b894;font-weight:600;border-color:rgba(0,184,148,.25)}
.nav-pill .pill-icon{font-size:.65rem;opacity:.5}
.nav-pill.active .pill-icon{opacity:1}

/* Sidebar Section Mini Heading */
.nav-section-heading{display:flex;align-items:center;gap:7px;padding:10px 14px 4px;font-size:.68rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;margin-top:4px}
.nav-section-heading i{font-size:.72rem;color:#00b894}
[data-theme="dark"] .nav-section-heading{color:#475569}
[data-theme="dark"] .nav-section-heading i{color:#00b894}


/* Bottom Shortcuts Grid */
.sb-shortcuts{border-top:1px solid #e2e8f0;padding:6px 4px}
.sb-shortcut-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:2px}
.sb-shortcut{display:flex;flex-direction:column;align-items:center;gap:1px;padding:5px 1px;border-radius:4px;background:#f8fafc;color:#64748b;font-size:.55rem;text-decoration:none;transition:all .2s;border:1px solid #e2e8f0;text-align:center}
.sb-shortcut:hover{background:#f1f5f9;color:#00b894;border-color:#cbd5e1}
.sb-shortcut .sc-icon-fa{font-size:.82rem;margin-bottom:0}

/* Dark Theme Overrides for Sidebar */
[data-theme="dark"] .sidebar{background:#0d0d0d;border-right-color:#1a1a1a}
[data-theme="dark"] .sb-brand{background:#111;border-bottom-color:#1e1e1e}
[data-theme="dark"] .sb-brand-name{color:#fff}
[data-theme="dark"] .sb-profile{border-bottom-color:#1e1e1e}
[data-theme="dark"] .sb-user-name{color:#fff}
[data-theme="dark"] .sb-menu{scrollbar-color:#222 transparent}
[data-theme="dark"] .sb-menu::-webkit-scrollbar-thumb{background:#222}
[data-theme="dark"] .menu-header{color:#aaa}
[data-theme="dark"] .menu-header:hover{background:#1a1a1a;color:#fff}
[data-theme="dark"] .menu-item.expanded .menu-header{color:#fff}
[data-theme="dark"] .nav-pill{color:#888}
[data-theme="dark"] .nav-pill:hover{color:#fff;background:#1a1a1a}
[data-theme="dark"] .nav-pill.active{background:rgba(0,184,148,.15);color:#00b894;border-color:rgba(0,184,148,.2)}
[data-theme="dark"] .sb-shortcuts{border-top-color:#1e1e1e}
[data-theme="dark"] .sb-shortcut{background:#141414;color:#666;border-color:transparent}
[data-theme="dark"] .sb-shortcut:hover{background:#1a1a1a;color:#00b894;border-color:#222}
</style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">

  <!-- School Branding -->
  <div class="sb-brand">
    <div style="width:40px; height:40px; border-radius:8px; background:#fff; overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <?php if(!empty($settings['school_logo']) && file_exists(UPLOAD_PATH.$settings['school_logo'])): ?>
        <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($settings['school_logo']) ?>" style="width:100%; height:100%; object-fit:contain;">
      <?php else: ?>
        <i class="fa-solid fa-school" style="color:#00b894; font-size:20px;"></i>
      <?php endif; ?>
    </div>
    <div style="overflow:hidden">
      <div class="sb-brand-name"><?= htmlspecialchars($school_name) ?></div>
      <div style="color:#00b894; font-size:.58rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-top:2px;">Academic System</div>
    </div>
    <button type="button" class="mobile-close-btn" id="mobileCloseBtn" onclick="closeMobileSidebar()" aria-label="Close Navigation" style="display:none; margin-left:auto; background:none; border:none; font-size:1.4rem; color:var(--text-muted); cursor:pointer; padding:6px;">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>



  <!-- Menu List -->
  <div class="sb-menu" id="sbMenu">
    
    <!-- 1. Dashboard -->
    <div class="menu-item <?= $active_tab==='dashboard'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='dashboard'?'active':'' ?>" onclick="toggleMenu(this, 'dashboard')">
        <i class="fa-solid fa-gauge-high m-icon"></i>
        <span>Dashboard</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>dashboard.php" class="nav-pill <?= $active_page==='dashboard'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Overview</span>
        </a>
        <a href="<?= BASE_URL ?>modules/resources/videos.php" class="nav-pill <?= in_array($active_page, ['videos', 'resources'])?'active':'' ?>">
          <i class="fa-solid fa-play-circle pill-icon"></i><span>Resources (Videos)</span>
        </a>
        <a href="<?= BASE_URL ?>modules/settings/notifications.php" class="nav-pill <?= $active_page==='notifications'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Notifications <?php if($notif_count>0): ?><span style="background:#ef4444;color:#fff;font-size:.6rem;padding:0 5px;border-radius:10px;margin-left:4px"><?= $notif_count ?></span><?php endif; ?></span>
        </a>
      </div>
    </div>

    <!-- 2. Students -->
    <?php if(has_module_access('students')): ?>
    <div class="menu-item <?= $active_tab==='students'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='students'?'active':'' ?>" onclick="toggleMenu(this, 'students')">
        <i class="fa-solid fa-user-graduate m-icon"></i>
        <span>Students</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/students/students.php" class="nav-pill <?= $active_page==='students'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Student List</span>
        </a>
        <a href="<?= BASE_URL ?>modules/students/register_student.php" class="nav-pill <?= $active_page==='register_student'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Register Student</span>
        </a>
        <a href="<?= BASE_URL ?>modules/students/classes.php" class="nav-pill <?= $active_page==='classes'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Classes &amp; Subjects</span>
        </a>
        <a href="<?= BASE_URL ?>modules/students/student_id_cards.php" class="nav-pill <?= $active_page==='student_id_cards'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Digital ID Cards</span>
        </a>
        <a href="<?= BASE_URL ?>modules/students/admission_form.php" class="nav-pill <?= $active_page==='admission_form'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Admission Form</span>
        </a>
        <a href="<?= BASE_URL ?>modules/reports/certificates.php" class="nav-pill <?= $active_page==='certificates'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Certificates</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- 3. Academic -->
    <?php if(has_module_access('academics')): ?>
    <div class="menu-item <?= $active_tab==='academics'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='academics'?'active':'' ?>" onclick="toggleMenu(this, 'academics')">
        <i class="fa-solid fa-pen-to-square m-icon"></i>
        <span>Academic</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/exams/grading_policy.php" class="nav-pill <?= $active_page==='grading_policy'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Grading Policy</span>
        </a>
        <a href="<?= BASE_URL ?>modules/exams/exam_types.php" class="nav-pill <?= $active_page==='exam_types'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Add Exam Type (Step-1)</span>
        </a>
        <a href="<?= BASE_URL ?>modules/exams/exam_schedule.php" class="nav-pill <?= $active_page==='exam_schedule'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Add Exam Schedule (Step-2)</span>
        </a>
        <a href="<?= BASE_URL ?>modules/exams/exams.php" class="nav-pill <?= $active_page==='exams'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Add Result (Step-3)</span>
        </a>
        <a href="<?= BASE_URL ?>modules/exams/roll_number_slips.php" class="nav-pill <?= $active_page==='roll_number_slips'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Roll-Number Slips</span>
        </a>
        <a href="<?= BASE_URL ?>modules/exams/results.php" class="nav-pill <?= $active_page==='results'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Progress Reports</span>
        </a>
        <a href="<?= BASE_URL ?>modules/settings/exam_settings.php" class="nav-pill <?= $active_page==='exam_settings'?'active':'' ?>">
          <i class="fa-solid fa-sliders pill-icon"></i><span>Result Card Settings</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Parents Portal -->
    <?php if(has_module_access('parents')): ?>
    <div class="menu-item <?= $active_tab==='parents'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='parents'?'active':'' ?>" onclick="toggleMenu(this, 'parents')">
        <i class="fa-solid fa-users-viewfinder m-icon"></i>
        <span>Parents Portal</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/parents/parents_portal.php" class="nav-pill <?= $active_page==='parents_portal'?'active':'' ?>">
          <i class="fa-solid fa-bullhorn pill-icon"></i><span>Parents Portal Management</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Subjects -->
    <?php if(has_module_access('academics')): ?>
    <div class="menu-item <?= $active_tab==='subjects'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='subjects'?'active':'' ?>" onclick="toggleMenu(this, 'subjects')">
        <i class="fa-solid fa-book m-icon"></i>
        <span>Subjects</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/exams/subjects.php" class="nav-pill <?= $active_page==='subjects'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Subject Management</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- 4. Fee -->
    <?php if(has_module_access('finance')): ?>
    <div class="menu-item <?= $active_tab==='finance'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='finance'?'active':'' ?>" onclick="toggleMenu(this, 'finance')">
        <i class="fa-solid fa-money-bill-wave m-icon"></i>
        <span>Fee</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/fees/fee_criteria.php" class="nav-pill <?= $active_page==='fee_criteria'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Fee Criteria</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fees/monthly_fee_invoices.php" class="nav-pill <?= $active_page==='monthly_fee_invoices'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Monthly Invoices</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fees/easy_fee.php" class="nav-pill <?= $active_page==='easy_fee'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Quick Fee Pay</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fees/advance_fee.php" class="nav-pill <?= $active_page==='advance_fee'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Advance Fees</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fees/discount_scholarship.php" class="nav-pill <?= $active_page==='discount_scholarship'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Scholarships</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fees/fine_policy.php" class="nav-pill <?= $active_page==='fine_policy'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Fine Policy</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fees/student_fee_history.php" class="nav-pill <?= $active_page==='student_fee_history'?'active':'' ?>">
          <i class="fa-solid fa-chart-line pill-icon"></i><span>Fee Progress Report</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fees/average_fee.php" class="nav-pill <?= $active_page==='average_fee'?'active':'' ?>">
          <i class="fa-solid fa-chart-bar pill-icon"></i><span>Average Fee</span>
        </a>
        <a href="<?= BASE_URL ?>modules/settings/chalan_form_settings.php" class="nav-pill <?= $active_page==='chalan_form_settings'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Slip Settings</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- 5. Staff -->
    <?php if(has_module_access('staff')): ?>
    <div class="menu-item <?= $active_tab==='staff'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='staff'?'active':'' ?>" onclick="toggleMenu(this, 'staff')">
        <i class="fa-solid fa-users-gear m-icon"></i>
        <span>Staff</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/staff/departments.php" class="nav-pill <?= $active_page==='departments'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Department List</span>
        </a>
        <a href="<?= BASE_URL ?>modules/staff/designations.php" class="nav-pill <?= $active_page==='designations'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Designation List</span>
        </a>
        <a href="<?= BASE_URL ?>modules/staff/salary_templates.php" class="nav-pill <?= $active_page==='salary_templates'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Salary Template</span>
        </a>
        <a href="<?= BASE_URL ?>modules/staff/staff_list.php" class="nav-pill <?= $active_page==='staff_list'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Staff List</span>
        </a>
        <a href="<?= BASE_URL ?>modules/staff/staff_attendance.php" class="nav-pill <?= $active_page==='staff_attendance'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Staff Attendance</span>
        </a>
        <a href="<?= BASE_URL ?>modules/staff/salary_slips.php" class="nav-pill <?= $active_page==='salary_slips'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Salary Slips</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- 6. Reports & Docs -->
    <?php if(has_module_access('documents')): ?>
    <div class="menu-item <?= $active_tab==='documents'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='documents'?'active':'' ?>" onclick="toggleMenu(this, 'documents')">
        <i class="fa-solid fa-file-lines m-icon"></i>
        <span>Reports &amp; Docs</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/reports/reports.php" class="nav-pill <?= $active_page==='reports'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>All Reports</span>
        </a>
        <a href="<?= BASE_URL ?>modules/reports/reports.php?report=yearly" class="nav-pill">
          <i class="fa-solid fa-chart-pie pill-icon"></i><span>Yearly Report</span>
        </a>
        <a href="<?= BASE_URL ?>modules/reports/certificates.php" class="nav-pill <?= $active_page==='certificates'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>All Certificates</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- 7. Institute Expenses -->
    <?php if(has_module_access('expenses')): ?>
    <div class="menu-item <?= $active_tab==='expenses'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='expenses'?'active':'' ?>" onclick="toggleMenu(this, 'expenses')">
        <i class="fa-solid fa-wallet m-icon"></i>
        <span>Institute Expenses</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/expenses/expenses.php" class="nav-pill <?= $active_page==='expenses'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Manage Expenses</span>
        </a>
        <a href="<?= BASE_URL ?>modules/expenses/expenses.php?action=categories" class="nav-pill">
          <i class="fa-solid fa-circle pill-icon"></i><span>Expense Categories</span>
        </a>
        <a href="<?= BASE_URL ?>modules/reports/reports.php?report=expenses" class="nav-pill">
          <i class="fa-solid fa-circle pill-icon"></i><span>Expense Reports</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- 8. Fall -->
    <?php if(has_module_access('academics')): ?>
    <div class="menu-item <?= $active_tab==='fall'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='fall'?'active':'' ?>" onclick="toggleMenu(this, 'fall')">
        <i class="fa-solid fa-snowflake m-icon"></i>
        <span>FALL</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>modules/fall/fall.php" class="nav-pill <?= $active_page==='fall'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>FALL</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fall/session_management.php" class="nav-pill <?= $active_page==='session_management'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Session Management</span>
        </a>
        <a href="<?= BASE_URL ?>modules/fall/bulk_enrollment.php" class="nav-pill <?= $active_page==='bulk_enrollment'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Bulk Enrollment</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- 9. System Settings -->
    <?php if(has_module_access('system')): ?>
    <div class="menu-item <?= $active_tab==='system'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='system'?'active':'' ?>" onclick="toggleMenu(this, 'system')">
        <i class="fa-solid fa-sliders m-icon"></i>
        <span>System Settings</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu <?= (strpos($active_page, 'settings') !== false || $active_page === 'admission_settings' || $active_page === 'users') ? 'show' : '' ?>">
        <a href="<?= BASE_URL ?>modules/settings/users.php" class="nav-pill <?= $active_page==='users'?'active':'' ?>">
          <i class="fa-solid fa-users-gear pill-icon"></i><span>User Accounts &amp; Permissions</span>
        </a>
        <a href="<?= BASE_URL ?>modules/settings/settings.php" class="nav-pill <?= $active_page==='settings'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>General Settings</span>
        </a>
        <a href="<?= BASE_URL ?>modules/settings/admission_settings.php" class="nav-pill <?= $active_page==='admission_settings'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Admission Form Settings</span>
        </a>
        <a href="<?= BASE_URL ?>modules/settings/sms_connection_settings.php" class="nav-pill <?= $active_page==='sms_connection_settings'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>SMS Gateway</span>
        </a>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- 10. Software License -->
    <div class="menu-item <?= $active_tab==='subscription'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='subscription'?'active':'' ?>" onclick="toggleMenu(this, 'subscription')">
        <i class="fa-solid fa-key m-icon"></i>
        <span>Software License</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <?php if($role === 'master'): ?>
          <a href="<?= BASE_URL ?>subscription_mgmt.php" class="nav-pill <?= $active_page==='subscription_mgmt'?'active':'' ?>">
            <i class="fa-solid fa-circle pill-icon"></i><span>Manage Renewal</span>
          </a>
          <a href="<?= BASE_URL ?>signup_requests.php" class="nav-pill <?= $active_page==='signup_requests'?'active':'' ?>">
            <i class="fa-solid fa-circle pill-icon"></i><span>Signup Requests</span>
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>subscription_status.php" class="nav-pill <?= $active_page==='subscription_status'?'active':'' ?>">
          <i class="fa-solid fa-circle pill-icon"></i><span>Renewal Status</span>
        </a>
      </div>
    </div>

    <!-- My Portal (for ALL logged-in users) -->
    <div class="menu-item <?= $active_tab==='portal'?'expanded':'' ?>">
      <div class="menu-header <?= $active_tab==='portal'?'active':'' ?>" onclick="toggleMenu(this, 'portal')">
        <i class="fa-solid fa-id-badge m-icon"></i>
        <span>My Portal</span>
        <i class="fa-solid fa-chevron-right m-arrow"></i>
      </div>
      <div class="submenu">
        <a href="<?= BASE_URL ?>portal.php" class="nav-pill <?= $active_page==='portal'?'active':'' ?>">
          <i class="fa-solid fa-user pill-icon"></i><span>My Profile</span>
        </a>
        <?php if(has_module_access('academics') || in_array($role, ['student','parent'])): ?>
        <a href="<?= BASE_URL ?>portal.php?tab=results" class="nav-pill <?= ($active_page==='portal' && ($_GET['tab']??'')==='results')?'active':'' ?>">
          <i class="fa-solid fa-trophy pill-icon"></i><span>My Results</span>
        </a>
        <?php endif; ?>
        <?php if(has_module_access('finance') || in_array($role, ['student','parent'])): ?>
        <a href="<?= BASE_URL ?>portal.php?tab=fees" class="nav-pill <?= ($active_page==='portal' && ($_GET['tab']??'')==='fees')?'active':'' ?>">
          <i class="fa-solid fa-receipt pill-icon"></i><span>Fee Reports</span>
        </a>
        <?php endif; ?>
        <?php if(has_module_access('expenses')): ?>
        <a href="<?= BASE_URL ?>portal.php?tab=expenses" class="nav-pill <?= ($active_page==='portal' && ($_GET['tab']??'')==='expenses')?'active':'' ?>">
          <i class="fa-solid fa-wallet pill-icon"></i><span>Expense Reports</span>
        </a>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Bottom Shortcut Grid -->
  <div class="sb-shortcuts">
    <div class="sb-shortcut-grid">
      <a href="<?= BASE_URL ?>modules/exams/results.php"      class="sb-shortcut"><i class="fa-solid fa-trophy sc-icon-fa"></i>Result</a>
      <a href="<?= BASE_URL ?>modules/fees/fees.php"         class="sb-shortcut"><i class="fa-solid fa-receipt sc-icon-fa"></i>Fee</a>
      <a href="<?= BASE_URL ?>modules/reports/reports.php"      class="sb-shortcut"><i class="fa-solid fa-chart-bar sc-icon-fa"></i>Reports</a>
      <a href="<?= BASE_URL ?>modules/exams/exams.php"        class="sb-shortcut"><i class="fa-solid fa-pen-to-square sc-icon-fa"></i>Exams</a>
      <a href="<?= BASE_URL ?>modules/reports/certificates.php" class="sb-shortcut"><i class="fa-solid fa-file-lines sc-icon-fa"></i>Certs</a>
      <?php if(in_array($role,['student','parent'])): ?>
      <a href="<?= BASE_URL ?>portal.php"       class="sb-shortcut"><i class="fa-solid fa-graduation-cap sc-icon-fa"></i>Portal</a>
      <?php else: ?>
      <a href="<?= BASE_URL ?>modules/settings/settings.php"     class="sb-shortcut"><i class="fa-solid fa-gear sc-icon-fa"></i>Settings</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>modules/students/students.php"     class="sb-shortcut"><i class="fa-solid fa-users sc-icon-fa"></i>Students</a>
      <a href="<?= BASE_URL ?>modules/students/classes.php"      class="sb-shortcut"><i class="fa-solid fa-chalkboard sc-icon-fa"></i>Classes</a>
      <a href="<?= BASE_URL ?>index.php?action=logout" class="sb-shortcut" style="color:#ef4444"><i class="fa-solid fa-right-from-bracket sc-icon-fa"></i>Logout</a>
    </div>
  </div>

</aside>
<!-- Mobile Backdrop Overlay -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- MAIN CONTENT -->
<div class="main-wrapper" id="mainWrapper">
  <!-- TOP HEADER -->
  <header class="top-header">
    <div class="header-left">
      <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Navigation"><i class="fa-solid fa-bars"></i></button>
      <div class="breadcrumb">
        <span class="school-name-header"><?= htmlspecialchars($school_name) ?></span>
        <span class="breadcrumb-sep">›</span>
        <span><?= htmlspecialchars($page_title) ?></span>
      </div>
    </div>
    <div class="header-right">
      <?php 
        $campus_url = rtrim(get_campus_base_url(), '/');
        $lan_ip = get_server_lan_ip();
        $lan_port = $_SERVER['SERVER_PORT'] ?? 80;
        $short_lan_display = ($lan_port != 80 && $lan_port != 443 && strpos($lan_ip, ':') === false) ? ($lan_ip . ':' . $lan_port) : $lan_ip;
      ?>
      <div class="campus-link-badge" onclick="copyCampusLink('<?= htmlspecialchars($campus_url) ?>', this)" title="Campus LAN Link (<?= htmlspecialchars($campus_url) ?>) - Click to Copy">
        <span class="campus-live-dot" title="Campus Network Auto-Synced"></span>
        <span class="campus-url-text"><i class="fa-solid fa-wifi" style="color:#0984e3;margin-right:2px"></i> <?= htmlspecialchars($short_lan_display) ?></span>
        <button type="button" class="campus-copy-btn"><i class="fa-solid fa-copy"></i> Copy</button>
      </div>
      <div class="header-date"><?= date('d M Y') ?></div>
      <button class="theme-toggle" id="themeToggle" title="Toggle Light/Dark"><i class="fa-solid fa-moon"></i> Dark</button>
      <a href="<?= BASE_URL ?>modules/settings/notifications.php" class="notif-btn" title="Notifications">
        <i class="fa-solid fa-bell"></i>
        <?php if($notif_count > 0): ?>
        <span class="notif-badge"><?= $notif_count ?></span>
        <?php endif; ?>
      </a>
      <div class="header-user">
        <div class="user-avatar-sm"><?= strtoupper(substr($user['full_name'] ?? $user['name'] ?? 'U',0,1)) ?></div>
        <span><?= htmlspecialchars(explode(' ',$user['full_name'] ?? $user['name'] ?? 'User')[0]) ?></span>
      </div>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main class="page-content">

<script>
// Accordion toggle
function toggleMenu(header, tab) {
  const item = header.parentElement;
  const isExpanded = item.classList.contains('expanded');
  
  if (isExpanded) {
    item.classList.remove('expanded');
  } else {
    item.classList.add('expanded');
  }
  
  localStorage.setItem('smss_active_tab', tab);
}

// Global Mobile Sidebar Functions
function openMobileSidebar() {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (sidebar) {
    sidebar.classList.add('open');
    sidebar.style.transform = 'translateX(0)';
  }
  if (backdrop) backdrop.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (sidebar) {
    sidebar.classList.remove('open');
    sidebar.style.transform = 'translateX(-100%)';
  }
  if (backdrop) backdrop.classList.remove('active');
  document.body.style.overflow = '';
}

// Mobile sidebar toggle & backdrop
document.addEventListener('DOMContentLoaded', function() {
  const mobileToggle = document.getElementById('mobileToggle');
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');

  if (mobileToggle) {
    mobileToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      if (sidebar && sidebar.classList.contains('open')) {
        closeMobileSidebar();
      } else {
        openMobileSidebar();
      }
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeMobileSidebar);
  }

  // Close sidebar on navigation link tap on mobile
  document.querySelectorAll('.nav-pill, .sb-shortcut').forEach(function(link) {
    link.addEventListener('click', function() {
      if (window.innerWidth <= 768) {
        closeMobileSidebar();
      }
    });
  });

  // Theme toggle
  var btn = document.getElementById('themeToggle');
  var saved = localStorage.getItem('smss_theme') || 'light';
  applyTheme(saved);
  if (btn) {
    btn.addEventListener('click', function() {
      var current = (document.documentElement.getAttribute('data-theme') === 'dark' || document.body.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
      applyTheme(current);
      localStorage.setItem('smss_theme', current);
    });
  }
});

function applyTheme(theme) {
  var btn = document.getElementById('themeToggle');
  if (theme === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    document.body.setAttribute('data-theme', 'dark');
    if (btn) btn.innerHTML = '<i class="fa-solid fa-sun"></i> Light';
  } else {
    document.documentElement.setAttribute('data-theme', 'light');
    document.body.setAttribute('data-theme', 'light');
    if (btn) btn.innerHTML = '<i class="fa-solid fa-moon"></i> Dark';
  }
}

// Copy Campus Access Link to Clipboard with Toast Notification
function copyCampusLink(url, el) {
  if (!url) return;
  
  function doNotify() {
    showToastNotification('Campus LAN Link copied to clipboard!<br><strong style="color:#0984e3;word-break:break-all;">' + url + '</strong>');
    if (el) {
      var copyBtn = el.querySelector('.campus-copy-btn');
      if (copyBtn) {
        var orig = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        copyBtn.style.background = '#10b981';
        setTimeout(function() {
          copyBtn.innerHTML = orig;
          copyBtn.style.background = '';
        }, 2200);
      }
    }
  }

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(doNotify).catch(function() {
      fallbackCopy(url, doNotify);
    });
  } else {
    fallbackCopy(url, doNotify);
  }
}

function fallbackCopy(text, callback) {
  var textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.left = "-999999px";
  textArea.style.top = "-999999px";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand('copy');
    if (callback) callback();
  } catch (err) {
    alert("Campus Link: " + text);
  }
  document.body.removeChild(textArea);
}

function showToastNotification(msg) {
  var toast = document.getElementById('globalToast');
  if(!toast) {
    toast = document.createElement('div');
    toast.id = 'globalToast';
    toast.className = 'global-toast';
    document.body.appendChild(toast);
  }
  toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> <div>' + msg + '</div>';
  toast.classList.add('show');
  setTimeout(function() {
    toast.classList.remove('show');
  }, 4000);
}
</script>

<!-- Show Menu Button (when sidebar is hidden) -->
<button id="showMenuBtn" style="display:none;position:fixed;top:50%;left:0;transform:translateY(-50%);z-index:1001;background:#00b894;color:#fff;border:none;border-radius:0 8px 8px 0;padding:12px 8px;cursor:pointer;flex-direction:column;align-items:center;gap:4px;font-size:.65rem;font-weight:600" onclick="document.getElementById('sidebar').style.transform='translateX(0)';document.getElementById('mainWrapper').style.marginLeft='280px';this.style.display='none'">
  <span style="font-size:1.2rem">☰</span>Menu
</button>



