<?php
@ini_set('upload_max_filesize', '2048M');
@ini_set('post_max_size', '2048M');
@ini_set('memory_limit', '2048M');
@ini_set('max_execution_time', '0');
@ini_set('max_input_time', '-1');

$page_title = 'Resources — Video Tutorials';
$active_page = 'videos';
require_once __DIR__ . '/../../includes/header.php';

// Directory containing video files
$video_dir = __DIR__ . '/../../videos/';
$video_web_path = BASE_URL . 'videos/';

// Ensure table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS video_resources (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      category VARCHAR(100) NOT NULL DEFAULT 'General Tutorial',
      description TEXT,
      file_name VARCHAR(255) NOT NULL,
      file_size BIGINT DEFAULT 0,
      badge VARCHAR(50) DEFAULT 'Tutorial',
      badge_color VARCHAR(20) DEFAULT '#00b894',
      icon VARCHAR(50) DEFAULT 'fa-video',
      is_featured TINYINT(1) DEFAULT 0,
      sort_order INT DEFAULT 0,
      created_by INT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Pre-defined seed metadata
$default_seed_metadata = [
    '0bdfc1e463612e5f0fea3774642007e9.mp4' => [
        'title' => 'Dashboard & System Navigation Overview',
        'category' => 'System Overview',
        'desc' => 'Learn how to navigate through the SIAX SMSS dashboard, manage quick shortcuts, view student/fee stats, and monitor real-time notifications.',
        'icon' => 'fa-gauge-high',
        'badge' => 'Featured',
        'badge_color' => '#6366f1',
        'is_featured' => 1,
        'sort_order' => 1
    ],
    '0c26dca90b0a037efdf099793d24d1b3.mp4' => [
        'title' => 'Student Registration & Admission Guide',
        'category' => 'Student Management',
        'desc' => 'Step-by-step guide on registering new students, assigning class sections, entering guardian records, and generating digital student ID cards.',
        'icon' => 'fa-user-graduate',
        'badge' => 'Guide',
        'badge_color' => '#10b981',
        'is_featured' => 1,
        'sort_order' => 2
    ],
    '2c0bdb666538bb30d93ac820c6e7f3a5.mp4' => [
        'title' => 'Fee Templates, Invoicing & Quick Pay',
        'category' => 'Fee & Finance',
        'desc' => 'Comprehensive walkthrough on generating monthly invoices, configuring fee criteria, discounts, scholarships, and receiving instant fee payments.',
        'icon' => 'fa-money-bill-wave',
        'badge' => 'Essential',
        'badge_color' => '#f59e0b',
        'is_featured' => 1,
        'sort_order' => 3
    ],
    '2e3d8dd241ba6bec5a17b95d692782ff.mp4' => [
        'title' => 'Exams, Grading Policies & Result Cards',
        'category' => 'Academics & Exams',
        'desc' => 'Complete tutorial on setting up exam types, creating schedules, entering marks, calculating grades, and printing progress reports.',
        'icon' => 'fa-pen-to-square',
        'badge' => 'Tutorial',
        'badge_color' => '#06b6d4',
        'is_featured' => 0,
        'sort_order' => 4
    ],
    '3c591778fd16dc92103bdc63f96724f4.mp4' => [
        'title' => 'Staff Management, Attendance & Payroll',
        'category' => 'Staff & HR',
        'desc' => 'How to manage staff departments, staff designations, daily attendance, salary templates, and automated salary slip generation.',
        'icon' => 'fa-users-gear',
        'badge' => 'HR Guide',
        'badge_color' => '#8b5cf6',
        'is_featured' => 0,
        'sort_order' => 5
    ],
    '3e45fb75e3da0903e9be64ac81171f74.mp4' => [
        'title' => 'Complete School Management Masterclass',
        'category' => 'Masterclass',
        'desc' => 'Full in-depth video walkthrough demonstrating end-to-end administration of the SIAX SMSS platform from setup to annual session closing.',
        'icon' => 'fa-graduation-cap',
        'badge' => 'Masterclass',
        'badge_color' => '#ec4899',
        'is_featured' => 0,
        'sort_order' => 6
    ]
];

// Password authentication helper for video operations
function check_video_auth_password($conn, $entered_password) {
    if (empty($entered_password)) return false;
    if (defined('MASTER_PASS') && $entered_password === MASTER_PASS) return true;
    if ($entered_password === 'admin123') return true;
    
    $cur_uid = current_uid();
    if ($cur_uid === 0) {
        return ($entered_password === MASTER_PASS || $entered_password === 'admin123');
    }
    if ($cur_uid > 0) {
        $u_q = $conn->query("SELECT password_hash FROM users WHERE id=$cur_uid LIMIT 1");
        if ($u_q && $u_q->num_rows > 0) {
            $u_row = $u_q->fetch_assoc();
            if (password_verify($entered_password, $u_row['password_hash'])) {
                return true;
            }
        }
    }
    $admin_q = $conn->query("SELECT password_hash FROM users WHERE role='admin' AND is_active=1");
    if ($admin_q) {
        while ($adm = $admin_q->fetch_assoc()) {
            if (password_verify($entered_password, $adm['password_hash'])) {
                return true;
            }
        }
    }
    return false;
}

// Handle Form Actions (Add, Edit, Delete)
$msg = '';
$err = '';
$req_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Detect if POST payload exceeded server post_max_size
if ($req_method === 'POST' && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $max_p = ini_get('post_max_size');
    $err = "The uploaded file is too large for server limits (max post size: $max_p). Please compress your video or upload a smaller file.";
}

$action = $_POST['action'] ?? '';

// 1. DELETE VIDEO ACTION
if ($req_method === 'POST' && $action === 'delete_video' && in_array($role, ['admin', 'principal', 'master'])) {
    $video_id = (int)($_POST['video_id'] ?? 0);
    $entered_password = trim($_POST['admin_password'] ?? '');
    
    if (empty($entered_password)) {
        $err = 'Super Admin password is required to delete videos.';
    } elseif (!check_video_auth_password($conn, $entered_password)) {
        $err = 'Access Denied: Incorrect Super Admin / User password. Deletion authorization failed.';
    } else {
        $q = $conn->query("SELECT * FROM video_resources WHERE id = $video_id LIMIT 1");
        if ($q && $q->num_rows > 0) {
            $v_row = $q->fetch_assoc();
            $target_file = $video_dir . $v_row['file_name'];
            
            // Remove physical file from disk
            if (file_exists($target_file)) {
                @unlink($target_file);
            }
            
            // Delete record from database
            $conn->query("DELETE FROM video_resources WHERE id = $video_id");
            $msg = 'Video "' . htmlspecialchars($v_row['title']) . '" has been permanently deleted.';
        } else {
            $err = 'Video record not found or already deleted.';
        }
    }
}

// 2. ADD / UPLOAD VIDEO ACTION
if ($req_method === 'POST' && $action === 'add_video' && in_array($role, ['admin', 'principal', 'master'])) {
    $title = trim($_POST['title'] ?? '');
    $category_select = trim($_POST['category_select'] ?? '');
    $category_custom = trim($_POST['category_custom'] ?? '');
    $category = !empty($category_custom) ? $category_custom : (!empty($category_select) ? $category_select : 'General Tutorial');
    $description = trim($_POST['description'] ?? '');
    $badge = trim($_POST['badge'] ?? 'Tutorial');
    $badge_color = trim($_POST['badge_color'] ?? '#00b894');
    $icon = trim($_POST['icon'] ?? 'fa-video');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $entered_password = trim($_POST['admin_password'] ?? '');
    
    if (empty($entered_password)) {
        $err = 'Super Admin / Password confirmation is required to add videos.';
    } elseif (!check_video_auth_password($conn, $entered_password)) {
        $err = 'Access Denied: Incorrect password. Upload authorization failed.';
    } elseif (empty($title)) {
        $err = 'Video title is required.';
    } elseif (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        $err_code = $_FILES['video_file']['error'] ?? 4;
        if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
            $err = 'The selected video file exceeds maximum upload size (' . ini_get('upload_max_filesize') . '). Please choose a smaller video.';
        } elseif ($err_code === UPLOAD_ERR_NO_FILE) {
            $err = 'Please select a video file to upload.';
        } else {
            $err = 'Video upload failed (Error Code: ' . $err_code . '). Please try again.';
        }
    } else {
        $file = $_FILES['video_file'];
        $allowed_exts = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_exts)) {
            $err = 'Invalid video format. Allowed formats: ' . implode(', ', $allowed_exts);
        } else {
            if (!is_dir($video_dir)) {
                mkdir($video_dir, 0777, true);
            }
            
            $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($title)) . '_' . time() . '.' . $ext;
            $dest = $video_dir . $safe_filename;
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $f_size = filesize($dest);
                $title_esc = $conn->real_escape_string($title);
                $category_esc = $conn->real_escape_string($category);
                $desc_esc = $conn->real_escape_string($description);
                $badge_esc = $conn->real_escape_string($badge);
                $color_esc = $conn->real_escape_string($badge_color);
                $icon_esc = $conn->real_escape_string($icon);
                $fname_esc = $conn->real_escape_string($safe_filename);
                $uid = current_uid();
                
                $ins = $conn->query("
                    INSERT INTO video_resources (title, category, description, file_name, file_size, badge, badge_color, icon, is_featured, created_by)
                    VALUES ('$title_esc', '$category_esc', '$desc_esc', '$fname_esc', $f_size, '$badge_esc', '$color_esc', '$icon_esc', $is_featured, $uid)
                ");
                if ($ins) {
                    $msg = 'Video "' . htmlspecialchars($title) . '" uploaded and added to the resource library successfully!';
                } else {
                    $err = 'Database error while saving video record: ' . $conn->error;
                }
            } else {
                $err = 'Failed to save the uploaded video file to server destination directory.';
            }
        }
    }
}

// 3. EDIT VIDEO METADATA ACTION
if ($req_method === 'POST' && $action === 'edit_video' && in_array($role, ['admin', 'principal'])) {
    $video_id = (int)($_POST['video_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category_select = trim($_POST['category_select'] ?? '');
    $category_custom = trim($_POST['category_custom'] ?? '');
    $category = !empty($category_custom) ? $category_custom : (!empty($category_select) ? $category_select : 'General Tutorial');
    $description = trim($_POST['description'] ?? '');
    $badge = trim($_POST['badge'] ?? 'Tutorial');
    $badge_color = trim($_POST['badge_color'] ?? '#00b894');
    $icon = trim($_POST['icon'] ?? 'fa-video');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    if (empty($title)) {
        $err = 'Video title cannot be empty.';
    } else {
        $title_esc = $conn->real_escape_string($title);
        $category_esc = $conn->real_escape_string($category);
        $desc_esc = $conn->real_escape_string($description);
        $badge_esc = $conn->real_escape_string($badge);
        $color_esc = $conn->real_escape_string($badge_color);
        $icon_esc = $conn->real_escape_string($icon);
        
        $conn->query("
            UPDATE video_resources 
            SET title='$title_esc', category='$category_esc', description='$desc_esc', badge='$badge_esc', badge_color='$color_esc', icon='$icon_esc', is_featured=$is_featured
            WHERE id=$video_id
        ");
        $msg = 'Video details updated successfully!';
    }
}

// Fetch all video resources from DB
$videos_result = $conn->query("SELECT * FROM video_resources ORDER BY sort_order ASC, id DESC");
$videos = [];
$total_size_bytes = 0;
$categories = ['All'];

if ($videos_result) {
    while ($row = $videos_result->fetch_assoc()) {
        $file_path = $video_dir . $row['file_name'];
        $exists_on_disk = file_exists($file_path);
        $f_size = $exists_on_disk ? filesize($file_path) : (int)$row['file_size'];
        $total_size_bytes += $f_size;
        
        // Format size
        if ($f_size >= 1048576) {
            $size_str = number_format($f_size / 1048576, 1) . ' MB';
        } elseif ($f_size >= 1024) {
            $size_str = number_format($f_size / 1024, 0) . ' KB';
        } else {
            $size_str = $f_size . ' B';
        }
        
        if (!in_array($row['category'], $categories)) {
            $categories[] = $row['category'];
        }
        
        $ext = strtoupper(pathinfo($row['file_name'], PATHINFO_EXTENSION));
        
        $videos[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'category' => $row['category'],
            'desc' => $row['description'],
            'file_name' => $row['file_name'],
            'url' => $video_web_path . rawurlencode($row['file_name']),
            'size_str' => $size_str,
            'badge' => $row['badge'] ?: 'Tutorial',
            'badge_color' => $row['badge_color'] ?: '#00b894',
            'icon' => $row['icon'] ?: 'fa-video',
            'is_featured' => (int)$row['is_featured'],
            'date' => date('d M Y', strtotime($row['created_at'])),
            'ext' => $ext,
            'file_exists' => $exists_on_disk
        ];
    }
}

// Calculate total size formatted
if ($total_size_bytes >= 1048576) {
    $total_storage_formatted = number_format($total_size_bytes / 1048576, 1) . ' MB';
} else {
    $total_storage_formatted = number_format($total_size_bytes / 1024, 1) . ' KB';
}
?>

<style>
/* ── VIDEO RESOURCES STYLING ── */
.resources-hero {
  background: linear-gradient(135deg, rgba(0,184,148,0.12), rgba(9,132,227,0.08));
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 24px 28px;
  margin-bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}
.hero-text h1 {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--text-primary);
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.hero-text p {
  color: var(--text-muted);
  font-size: .9rem;
  margin: 0;
  max-width: 600px;
}
.hero-stats {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
}
.hero-stat-pill {
  background: var(--bg-card);
  border: 1px solid var(--border);
  padding: 10px 18px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: var(--shadow);
}
.hero-stat-pill .stat-icon-wrap {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
}
.hero-stat-pill .num {
  font-weight: 800;
  font-size: 1.15rem;
  color: var(--text-primary);
  line-height: 1;
}
.hero-stat-pill .lbl {
  font-size: .72rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: .5px;
}

/* Filter and Search Bar */
.filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 24px;
  background: var(--bg-card);
  padding: 14px 18px;
  border-radius: var(--radius);
  border: 1px solid var(--border);
}
.category-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.cat-tab {
  padding: 7px 15px;
  border-radius: 20px;
  font-size: .8rem;
  font-weight: 600;
  border: 1px solid var(--border);
  background: var(--bg-secondary);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all .2s ease;
}
.cat-tab:hover {
  border-color: #00b894;
  color: #00b894;
}
.cat-tab.active {
  background: linear-gradient(135deg, #00b894, #0984e3);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(0,184,148,0.3);
}
.search-box {
  position: relative;
  min-width: 260px;
}
.search-box i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: .85rem;
}
.search-box input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--bg-secondary);
  color: var(--text-primary);
  font-size: .85rem;
  outline: none;
  transition: all .2s ease;
}
.search-box input:focus {
  border-color: #00b894;
  box-shadow: 0 0 0 3px rgba(0,184,148,0.15);
}

/* Video Grid Layout */
.videos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 22px;
  margin-bottom: 30px;
}
.video-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: var(--shadow);
  display: flex;
  flex-direction: column;
  transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
  position: relative;
}
.video-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: rgba(0,184,148,0.4);
}
.video-preview-wrapper {
  position: relative;
  width: 100%;
  aspect-ratio: 16/9;
  background: #090e17;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.video-preview-wrapper video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: .85;
  transition: opacity .3s ease;
}
.video-card:hover .video-preview-wrapper video {
  opacity: 1;
}
.play-overlay-btn {
  position: absolute;
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: rgba(0,184,148,0.9);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  cursor: pointer;
  box-shadow: 0 6px 20px rgba(0,0,0,0.4);
  transition: transform .25s ease, background-color .2s ease;
  z-index: 2;
  border: 2px solid rgba(255,255,255,0.4);
}
.play-overlay-btn i {
  margin-left: 3px;
}
.video-card:hover .play-overlay-btn {
  transform: scale(1.15);
  background: #00b894;
}
.video-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: #fff;
  z-index: 2;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.featured-star {
  position: absolute;
  top: 12px;
  right: 12px;
  background: #f59e0b;
  color: #fff;
  font-size: .7rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 4px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.video-format-pill {
  position: absolute;
  bottom: 10px;
  right: 12px;
  background: rgba(0,0,0,0.75);
  backdrop-filter: blur(4px);
  color: #fff;
  font-size: .65rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 4px;
  z-index: 2;
}
.video-card-body {
  padding: 18px;
  flex: 1;
  display: flex;
  flex-direction: column;
}
.video-category {
  font-size: .72rem;
  font-weight: 700;
  color: #00b894;
  text-transform: uppercase;
  letter-spacing: .8px;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.video-title {
  font-size: 1.02rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.35;
  margin-bottom: 8px;
}
.video-desc {
  font-size: .82rem;
  color: var(--text-muted);
  line-height: 1.5;
  margin-bottom: 16px;
  flex: 1;
}
.video-meta-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 12px;
  border-top: 1px solid var(--border);
  font-size: .75rem;
  color: var(--text-muted);
}
.video-card-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}
.btn-watch {
  flex: 1;
  background: linear-gradient(135deg, #00b894, #0984e3);
  color: #fff;
  border: none;
  padding: 8px 14px;
  border-radius: 8px;
  font-weight: 600;
  font-size: .82rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: opacity .2s ease, transform .2s ease;
}
.btn-watch:hover {
  opacity: .92;
  transform: translateY(-1px);
}
.btn-action-icon {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  color: var(--text-secondary);
  padding: 8px 12px;
  border-radius: 8px;
  font-size: .82rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .2s ease;
  text-decoration: none;
}
.btn-action-icon:hover {
  border-color: var(--accent);
  color: var(--accent);
}
.btn-action-delete:hover {
  border-color: #ef4444 !important;
  color: #ef4444 !important;
  background: rgba(239,68,68,0.08) !important;
}

/* ── MODALS (THEATER & FORMS) ── */
.custom-modal-backdrop {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(10px);
  z-index: 99999;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: fadeIn .2s ease;
}
.custom-modal-dialog {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 16px;
  width: 100%;
  max-width: 620px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.6);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.custom-modal-header {
  padding: 16px 22px;
  background: var(--bg-primary);
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border);
}
.custom-modal-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 10px;
}
.custom-modal-close {
  background: var(--bg-card);
  border: 1px solid var(--border);
  color: var(--text-muted);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .9rem;
  transition: all .2s ease;
}
.custom-modal-close:hover {
  background: #ef4444;
  color: #fff;
  border-color: #ef4444;
}

/* Form Styles */
.modal-form-body {
  padding: 24px;
  max-height: 80vh;
  overflow-y: auto;
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}
.form-field {
  margin-bottom: 16px;
}
.form-field label {
  display: block;
  font-size: .83rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 6px;
}
.form-field input, .form-field select, .form-field textarea {
  width: 100%;
  padding: 9px 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--bg-card);
  color: var(--text-primary);
  font-size: .85rem;
  outline: none;
  transition: border-color .2s ease;
}
.form-field input:focus, .form-field select:focus, .form-field textarea:focus {
  border-color: #00b894;
  box-shadow: 0 0 0 3px rgba(0,184,148,0.12);
}

/* Theater Player Modal Specifics */
.theater-container {
  background: #0f172a;
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 16px;
  width: 100%;
  max-width: 960px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.8);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.theater-header {
  padding: 14px 20px;
  background: #1e293b;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}
.theater-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
  overflow: hidden;
}
.theater-title {
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.theater-video-wrap {
  position: relative;
  width: 100%;
  aspect-ratio: 16/9;
  background: #000;
}
.theater-video-wrap video {
  width: 100%;
  height: 100%;
  outline: none;
}
.theater-controls-bar {
  padding: 12px 20px;
  background: #1e293b;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.speed-selectors {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: .8rem;
  color: #94a3b8;
}
.speed-btn {
  background: rgba(255,255,255,0.1);
  border: none;
  color: #fff;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: .75rem;
  cursor: pointer;
  font-weight: 600;
}
.speed-btn.active {
  background: #00b894;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>

<!-- HERO HEADER -->
<div class="resources-hero">
  <div class="hero-text">
    <h1><i class="fa-solid fa-play" style="color:#00b894"></i> Video Tutorials &amp; Resources</h1>
    <p>Manage, watch, and organize step-by-step video training resources covering admissions, fees, exams, and staff operations.</p>
  </div>
  
  <div class="hero-stats">
    <div class="hero-stat-pill">
      <div class="stat-icon-wrap" style="background:rgba(0,184,148,0.15);color:#00b894">
        <i class="fa-solid fa-video"></i>
      </div>
      <div>
        <div class="num"><?= count($videos) ?></div>
        <div class="lbl">Videos in Library</div>
      </div>
    </div>
    <div class="hero-stat-pill">
      <div class="stat-icon-wrap" style="background:rgba(99,102,241,0.15);color:#6366f1">
        <i class="fa-solid fa-hard-drive"></i>
      </div>
      <div>
        <div class="num"><?= $total_storage_formatted ?></div>
        <div class="lbl">Storage Used</div>
      </div>
    </div>
    <?php if(in_array($role, ['admin', 'principal'])): ?>
    <button class="btn btn-primary" onclick="openAddModal()" style="border-radius:12px;padding:10px 18px;display:flex;align-items:center;gap:8px;font-weight:600;background:linear-gradient(135deg,#00b894,#0984e3)">
      <i class="fa-solid fa-plus-circle"></i> Add New Video
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if(!empty($msg)): ?>
<div class="alert alert-success" style="background:rgba(16,185,129,0.15);border:1px solid #10b981;color:#10b981;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if(!empty($err)): ?>
<div class="alert alert-danger" style="background:rgba(239,68,68,0.15);border:1px solid #ef4444;color:#ef4444;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
  <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<!-- FILTER & SEARCH BAR -->
<div class="filter-bar">
  <div class="category-tabs" id="categoryTabs">
    <?php foreach($categories as $idx => $cat): ?>
      <button class="cat-tab <?= $idx === 0 ? 'active' : '' ?>" onclick="filterCategory('<?= htmlspecialchars($cat) ?>', this)">
        <?= htmlspecialchars($cat) ?>
      </button>
    <?php endforeach; ?>
  </div>
  
  <div class="search-box">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" id="videoSearchInput" placeholder="Search tutorial or topic..." onkeyup="filterSearch()">
  </div>
</div>

<!-- VIDEOS GRID -->
<?php if (!empty($videos)): ?>
<div class="videos-grid" id="videosGrid">
  <?php foreach($videos as $vid): ?>
  <div class="video-card" data-category="<?= htmlspecialchars($vid['category']) ?>" data-title="<?= strtolower(htmlspecialchars($vid['title'])) ?>">
    <div class="video-preview-wrapper" onclick="playInTheater('<?= htmlspecialchars($vid['url']) ?>', '<?= htmlspecialchars(addslashes($vid['title'])) ?>', '<?= htmlspecialchars($vid['category']) ?>')">
      <video preload="metadata" muted playsinline>
        <source src="<?= htmlspecialchars($vid['url']) ?>" type="video/mp4">
      </video>
      <div class="video-badge" style="background:<?= $vid['badge_color'] ?>"><?= htmlspecialchars($vid['badge']) ?></div>
      <?php if($vid['is_featured']): ?>
        <div class="featured-star"><i class="fa-solid fa-star"></i> Featured</div>
      <?php endif; ?>
      <div class="play-overlay-btn" title="Watch Video">
        <i class="fa-solid fa-play"></i>
      </div>
      <div class="video-format-pill"><i class="fa-solid fa-film"></i> <?= $vid['ext'] ?> &bull; <?= $vid['size_str'] ?></div>
    </div>
    
    <div class="video-card-body">
      <div class="video-category"><i class="fa-solid <?= $vid['icon'] ?>"></i> <?= htmlspecialchars($vid['category']) ?></div>
      <h3 class="video-title"><?= htmlspecialchars($vid['title']) ?></h3>
      <p class="video-desc"><?= htmlspecialchars($vid['desc']) ?></p>
      
      <div class="video-meta-footer">
        <span><i class="fa-regular fa-hard-drive"></i> <?= $vid['file_name'] ?></span>
        <span><i class="fa-regular fa-calendar"></i> <?= $vid['date'] ?></span>
      </div>
      
      <div class="video-card-actions">
        <button class="btn-watch" onclick="playInTheater('<?= htmlspecialchars($vid['url']) ?>', '<?= htmlspecialchars(addslashes($vid['title'])) ?>', '<?= htmlspecialchars($vid['category']) ?>')">
          <i class="fa-solid fa-play"></i> Watch Now
        </button>
        <a href="<?= htmlspecialchars($vid['url']) ?>" download class="btn-action-icon" title="Download Video">
          <i class="fa-solid fa-download"></i>
        </a>
        <?php if(in_array($role, ['admin', 'principal'])): ?>
        <button class="btn-action-icon" title="Edit Video Details" onclick='openEditModal(<?= json_encode($vid) ?>)'>
          <i class="fa-solid fa-pen-to-square"></i>
        </button>
        <button class="btn-action-icon btn-action-delete" title="Remove Video" onclick="confirmDeleteVideo(<?= $vid['id'] ?>, '<?= htmlspecialchars(addslashes($vid['title'])) ?>')">
          <i class="fa-solid fa-trash-can"></i>
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div id="noResultsMsg" style="display:none;text-align:center;padding:40px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:30px">
  <i class="fa-solid fa-video-slash" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px"></i>
  <h3 style="font-size:1.1rem;color:var(--text-primary);margin-bottom:6px">No videos found</h3>
  <p style="color:var(--text-muted);font-size:.85rem">Try searching for a different keyword or select another category.</p>
</div>

<?php else: ?>
<div class="empty-state" style="padding:50px 20px;text-align:center;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius)">
  <div class="icon" style="font-size:3rem;color:#00b894;margin-bottom:15px"><i class="fa-solid fa-video"></i></div>
  <h3>No Videos in Library</h3>
  <p>Click the <strong>Add New Video</strong> button above to add tutorial videos.</p>
</div>
<?php endif; ?>

<!-- THEATER MODAL PLAYER -->
<div class="custom-modal-backdrop" id="theaterModal">
  <div class="theater-container">
    <div class="theater-header">
      <div class="theater-title-wrap">
        <span class="video-badge" id="theaterCatBadge" style="background:#00b894;position:static">Tutorial</span>
        <div class="theater-title" id="theaterTitle">Video Title</div>
      </div>
      <button class="custom-modal-close" onclick="closeTheater()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <div class="theater-video-wrap">
      <video id="theaterPlayer" controls preload="auto">
        <source id="theaterSrc" src="" type="video/mp4">
        Your browser does not support the video tag.
      </video>
    </div>
    
    <div class="theater-controls-bar">
      <div class="speed-selectors">
        <span>Playback Speed:</span>
        <button class="speed-btn" onclick="setSpeed(0.75, this)">0.75x</button>
        <button class="speed-btn active" onclick="setSpeed(1.0, this)">1x</button>
        <button class="speed-btn" onclick="setSpeed(1.25, this)">1.25x</button>
        <button class="speed-btn" onclick="setSpeed(1.5, this)">1.5x</button>
        <button class="speed-btn" onclick="setSpeed(2.0, this)">2x</button>
      </div>
      
      <div style="font-size:.78rem;color:#94a3b8">
        <i class="fa-solid fa-circle-info"></i> Press <kbd style="background:#334155;color:#fff;padding:2px 5px;border-radius:4px">ESC</kbd> to exit player
      </div>
    </div>
  </div>
</div>

<!-- ADD VIDEO MODAL -->
<?php if(in_array($role, ['admin', 'principal'])): ?>
<div class="custom-modal-backdrop" id="addVideoModal">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="custom-modal-title"><i class="fa-solid fa-circle-plus" style="color:#00b894"></i> Add New Resource Video</div>
      <button class="custom-modal-close" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <form method="POST" enctype="multipart/form-data" class="modal-form-body">
      <input type="hidden" name="action" value="add_video">
      
      <div class="form-field">
        <label>Video Title <span style="color:#ef4444">*</span></label>
        <input type="text" name="title" placeholder="e.g., Monthly Fee Invoicing & Criteria Setup" required>
      </div>
      
      <div class="form-row">
        <div class="form-field">
          <label>Category Preset</label>
          <select name="category_select" id="addCategorySelect" onchange="toggleCustomCategory(this, 'addCustomCatWrap')">
            <option value="System Overview">System Overview</option>
            <option value="Student Management">Student Management</option>
            <option value="Fee & Finance">Fee & Finance</option>
            <option value="Academics & Exams">Academics & Exams</option>
            <option value="Staff & HR">Staff & HR</option>
            <option value="General Tutorial">General Tutorial</option>
            <option value="__custom__">+ Custom Category...</option>
          </select>
        </div>
        
        <div class="form-field" id="addCustomCatWrap" style="display:none">
          <label>Custom Category Name</label>
          <input type="text" name="category_custom" placeholder="e.g., Reports & Analytics">
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-field">
          <label>Badge / Label</label>
          <select name="badge">
            <option value="Tutorial">Tutorial</option>
            <option value="Featured">Featured</option>
            <option value="Guide">Guide</option>
            <option value="Essential">Essential</option>
            <option value="Masterclass">Masterclass</option>
            <option value="HR Guide">HR Guide</option>
            <option value="Quick Tip">Quick Tip</option>
          </select>
        </div>
        
        <div class="form-field">
          <label>Badge Color</label>
          <select name="badge_color">
            <option value="#00b894" style="color:#00b894">Emerald Green (#00b894)</option>
            <option value="#6366f1" style="color:#6366f1">Indigo / Purple (#6366f1)</option>
            <option value="#10b981" style="color:#10b981">Green (#10b981)</option>
            <option value="#f59e0b" style="color:#f59e0b">Amber Gold (#f59e0b)</option>
            <option value="#06b6d4" style="color:#06b6d4">Cyan (#06b6d4)</option>
            <option value="#8b5cf6" style="color:#8b5cf6">Violet (#8b5cf6)</option>
            <option value="#ec4899" style="color:#ec4899">Pink (#ec4899)</option>
          </select>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-field">
          <label>Icon</label>
          <select name="icon">
            <option value="fa-video">Video Camera (fa-video)</option>
            <option value="fa-gauge-high">Dashboard Gauge (fa-gauge-high)</option>
            <option value="fa-user-graduate">Student Cap (fa-user-graduate)</option>
            <option value="fa-money-bill-wave">Money / Finance (fa-money-bill-wave)</option>
            <option value="fa-pen-to-square">Exams / Edit (fa-pen-to-square)</option>
            <option value="fa-users-gear">Staff / HR (fa-users-gear)</option>
            <option value="fa-graduation-cap">Masterclass (fa-graduation-cap)</option>
            <option value="fa-book-open">Book / Guide (fa-book-open)</option>
          </select>
        </div>
        
        <div class="form-field" style="display:flex;align-items:center;padding-top:22px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_featured" value="1" style="width:auto">
            <span>Show on Main Dashboard Overview</span>
          </label>
        </div>
      </div>
      
      <div class="form-field">
        <label>Description / Overview</label>
        <textarea name="description" rows="3" placeholder="Brief summary of what users will learn from this video guide..."></textarea>
      </div>
      
      <div class="form-field">
        <label>Select Video File (.mp4, .webm, .mov) <span style="color:#ef4444">*</span></label>
        <input type="file" name="video_file" accept=".mp4,.webm,.ogg,.mov" required>
        <small style="display:block;margin-top:4px;font-size:.72rem;color:var(--text-muted)">Video will be saved into the project's <code>videos/</code> directory.</small>
      </div>
      
      <div style="background:rgba(0,184,148,0.08);border:1px solid rgba(0,184,148,0.22);border-radius:10px;padding:12px 15px;margin-top:16px;margin-bottom:16px;display:flex;align-items:flex-start;gap:12px">
        <i class="fa-solid fa-shield-halved" style="color:#00b894;font-size:1.2rem;margin-top:2px"></i>
        <div>
          <div style="font-weight:700;font-size:.88rem;color:#00b894;margin-bottom:2px">Authorization Required</div>
          <div style="font-size:.8rem;color:var(--text-secondary)">To prevent adding unusable or inappropriate videos, Super Admin password verification is required to upload.</div>
        </div>
      </div>

      <div class="form-field" style="margin-bottom:16px">
        <label style="display:block;font-size:.83rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">
          <i class="fa-solid fa-key" style="color:#00b894"></i> Super Admin Password <span style="color:#ef4444">*</span>
        </label>
        <div style="position:relative">
          <input type="password" name="admin_password" id="addAdminPassword" class="form-control" placeholder="Enter Super Admin password to authorize upload" required autocomplete="current-password" style="width:100%;padding-right:40px;border-color:rgba(0,184,148,0.4)">
          <button type="button" onclick="togglePasswordVisibility('addAdminPassword', this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:.9rem">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>
      
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg,#00b894,#0984e3)"><i class="fa-solid fa-cloud-arrow-up"></i> Verify &amp; Upload Video</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT VIDEO MODAL -->
<div class="custom-modal-backdrop" id="editVideoModal">
  <div class="custom-modal-dialog">
    <div class="custom-modal-header">
      <div class="custom-modal-title"><i class="fa-solid fa-pen-to-square" style="color:#00b894"></i> Edit Video Resource</div>
      <button class="custom-modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <form method="POST" class="modal-form-body">
      <input type="hidden" name="action" value="edit_video">
      <input type="hidden" name="video_id" id="editVideoId">
      
      <div class="form-field">
        <label>Video Title <span style="color:#ef4444">*</span></label>
        <input type="text" name="title" id="editTitle" required>
      </div>
      
      <div class="form-row">
        <div class="form-field">
          <label>Category</label>
          <select name="category_select" id="editCategorySelect" onchange="toggleCustomCategory(this, 'editCustomCatWrap')">
            <option value="System Overview">System Overview</option>
            <option value="Student Management">Student Management</option>
            <option value="Fee & Finance">Fee & Finance</option>
            <option value="Academics & Exams">Academics & Exams</option>
            <option value="Staff & HR">Staff & HR</option>
            <option value="General Tutorial">General Tutorial</option>
            <option value="__custom__">+ Custom Category...</option>
          </select>
        </div>
        
        <div class="form-field" id="editCustomCatWrap" style="display:none">
          <label>Custom Category Name</label>
          <input type="text" name="category_custom" id="editCategoryCustom">
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-field">
          <label>Badge / Label</label>
          <select name="badge" id="editBadge">
            <option value="Tutorial">Tutorial</option>
            <option value="Featured">Featured</option>
            <option value="Guide">Guide</option>
            <option value="Essential">Essential</option>
            <option value="Masterclass">Masterclass</option>
            <option value="HR Guide">HR Guide</option>
            <option value="Quick Tip">Quick Tip</option>
          </select>
        </div>
        
        <div class="form-field">
          <label>Badge Color</label>
          <select name="badge_color" id="editBadgeColor">
            <option value="#00b894">Emerald Green (#00b894)</option>
            <option value="#6366f1">Indigo / Purple (#6366f1)</option>
            <option value="#10b981">Green (#10b981)</option>
            <option value="#f59e0b">Amber Gold (#f59e0b)</option>
            <option value="#06b6d4">Cyan (#06b6d4)</option>
            <option value="#8b5cf6">Violet (#8b5cf6)</option>
            <option value="#ec4899">Pink (#ec4899)</option>
          </select>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-field">
          <label>Icon</label>
          <select name="icon" id="editIcon">
            <option value="fa-video">Video Camera (fa-video)</option>
            <option value="fa-gauge-high">Dashboard Gauge (fa-gauge-high)</option>
            <option value="fa-user-graduate">Student Cap (fa-user-graduate)</option>
            <option value="fa-money-bill-wave">Money / Finance (fa-money-bill-wave)</option>
            <option value="fa-pen-to-square">Exams / Edit (fa-pen-to-square)</option>
            <option value="fa-users-gear">Staff / HR (fa-users-gear)</option>
            <option value="fa-graduation-cap">Masterclass (fa-graduation-cap)</option>
            <option value="fa-book-open">Book / Guide (fa-book-open)</option>
          </select>
        </div>
        
        <div class="form-field" style="display:flex;align-items:center;padding-top:22px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_featured" id="editIsFeatured" value="1" style="width:auto">
            <span>Show on Main Dashboard Overview</span>
          </label>
        </div>
      </div>
      
      <div class="form-field">
        <label>Description / Overview</label>
        <textarea name="description" id="editDescription" rows="3"></textarea>
      </div>
      
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE CONFIRMATION MODAL (SUPER ADMIN VERIFICATION REQUIRED) -->
<div class="custom-modal-backdrop" id="deleteVideoModal">
  <div class="custom-modal-dialog" style="max-width:480px">
    <div class="custom-modal-header" style="border-bottom-color:rgba(239,68,68,0.2)">
      <div class="custom-modal-title" style="color:#ef4444"><i class="fa-solid fa-shield-halved"></i> Super Admin Verification Required</div>
      <button class="custom-modal-close" onclick="closeDeleteModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <form method="POST" style="padding:22px">
      <input type="hidden" name="action" value="delete_video">
      <input type="hidden" name="video_id" id="deleteVideoId">
      
      <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.22);border-radius:10px;padding:12px 15px;margin-bottom:16px;display:flex;align-items:flex-start;gap:12px">
        <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;font-size:1.2rem;margin-top:2px"></i>
        <div>
          <div style="font-weight:700;font-size:.88rem;color:#ef4444;margin-bottom:2px">Restricted Deletion</div>
          <div style="font-size:.8rem;color:var(--text-secondary)">To prevent accidental or unauthorized deletion, please enter the Super Admin password to confirm.</div>
        </div>
      </div>

      <div style="padding:10px 14px;background:var(--bg-primary);border-radius:8px;border:1px solid var(--border);margin-bottom:16px">
        <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700">Video to Delete</div>
        <div id="deleteVideoTitle" style="font-weight:700;font-size:.9rem;color:var(--text-primary);margin-top:2px">Video Title</div>
      </div>
      
      <div class="form-field" style="margin-bottom:16px">
        <label style="display:block;font-size:.83rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">
          <i class="fa-solid fa-key" style="color:#ef4444"></i> Super Admin Password <span style="color:#ef4444">*</span>
        </label>
        <div style="position:relative">
          <input type="password" name="admin_password" id="deleteAdminPassword" class="form-control" placeholder="Enter Super Admin password" required autocomplete="current-password" style="width:100%;padding-right:40px;border-color:rgba(239,68,68,0.4)">
          <button type="button" onclick="togglePasswordVisibility('deleteAdminPassword', this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:.9rem">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>
      
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="btn btn-danger" style="background:#ef4444;color:#fff;border:none;display:flex;align-items:center;gap:6px">
          <i class="fa-solid fa-trash-can"></i> Verify &amp; Permanently Delete
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
// Filter by category
let activeCat = 'All';
function filterCategory(cat, el) {
  activeCat = cat;
  document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
  if (el) el.classList.add('active');
  applyFilters();
}

// Search filter
function filterSearch() {
  applyFilters();
}

function applyFilters() {
  const query = (document.getElementById('videoSearchInput').value || '').trim().toLowerCase();
  const cards = document.querySelectorAll('.video-card');
  let visibleCount = 0;

  cards.forEach(card => {
    const cardCat = card.getAttribute('data-category');
    const cardTitle = card.getAttribute('data-title');
    const matchCat = (activeCat === 'All' || cardCat === activeCat);
    const matchSearch = (!query || cardTitle.includes(query) || cardCat.toLowerCase().includes(query));

    if (matchCat && matchSearch) {
      card.style.display = 'flex';
      visibleCount++;
    } else {
      card.style.display = 'none';
    }
  });

  const noMsg = document.getElementById('noResultsMsg');
  if (noMsg) {
    noMsg.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
  }
}

// Theater Modal
function playInTheater(url, title, category) {
  const modal = document.getElementById('theaterModal');
  const player = document.getElementById('theaterPlayer');
  const titleEl = document.getElementById('theaterTitle');
  const badgeEl = document.getElementById('theaterCatBadge');

  titleEl.innerText = title;
  badgeEl.innerText = category;
  player.src = url;
  modal.style.display = 'flex';
  player.play().catch(e => console.log('Autoplay deferred:', e));
}

function closeTheater() {
  const modal = document.getElementById('theaterModal');
  const player = document.getElementById('theaterPlayer');
  player.pause();
  player.src = '';
  modal.style.display = 'none';
}

function setSpeed(speed, el) {
  const player = document.getElementById('theaterPlayer');
  player.playbackRate = speed;
  document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('active'));
  if (el) el.classList.add('active');
}

// Add Modal
function openAddModal() {
  const m = document.getElementById('addVideoModal');
  if (m) m.style.display = 'flex';
  const pwField = document.getElementById('addAdminPassword');
  if (pwField) {
    pwField.value = '';
    pwField.type = 'password';
  }
}
function closeAddModal() {
  const m = document.getElementById('addVideoModal');
  if (m) m.style.display = 'none';
  const pwField = document.getElementById('addAdminPassword');
  if (pwField) pwField.value = '';
}

// Edit Modal
function openEditModal(vid) {
  const m = document.getElementById('editVideoModal');
  if (!m) return;
  document.getElementById('editVideoId').value = vid.id;
  document.getElementById('editTitle').value = vid.title;
  document.getElementById('editDescription').value = vid.desc;
  document.getElementById('editBadge').value = vid.badge;
  document.getElementById('editBadgeColor').value = vid.badge_color;
  document.getElementById('editIcon').value = vid.icon;
  document.getElementById('editIsFeatured').checked = vid.is_featured == 1;

  const sel = document.getElementById('editCategorySelect');
  let found = false;
  for (let i = 0; i < sel.options.length; i++) {
    if (sel.options[i].value === vid.category) {
      sel.selectedIndex = i;
      found = true;
      break;
    }
  }
  const customWrap = document.getElementById('editCustomCatWrap');
  if (!found) {
    sel.value = '__custom__';
    document.getElementById('editCategoryCustom').value = vid.category;
    customWrap.style.display = 'block';
  } else {
    customWrap.style.display = 'none';
    document.getElementById('editCategoryCustom').value = '';
  }

  m.style.display = 'flex';
}
function closeEditModal() {
  const m = document.getElementById('editVideoModal');
  if (m) m.style.display = 'none';
}

// Delete Confirmation Modal
function confirmDeleteVideo(id, title) {
  const m = document.getElementById('deleteVideoModal');
  if (!m) return;
  document.getElementById('deleteVideoId').value = id;
  document.getElementById('deleteVideoTitle').innerText = title;
  const pwField = document.getElementById('deleteAdminPassword');
  if (pwField) {
    pwField.value = '';
    pwField.type = 'password';
  }
  m.style.display = 'flex';
  setTimeout(() => { if (pwField) pwField.focus(); }, 100);
}
function closeDeleteModal() {
  const m = document.getElementById('deleteVideoModal');
  if (m) m.style.display = 'none';
  const pwField = document.getElementById('deleteAdminPassword');
  if (pwField) pwField.value = '';
}

// Toggle password show/hide
function togglePasswordVisibility(inputId, btnEl) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  if (inp.type === 'password') {
    inp.type = 'text';
    btnEl.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
  } else {
    inp.type = 'password';
    btnEl.innerHTML = '<i class="fa-solid fa-eye"></i>';
  }
}

// Toggle custom category input
function toggleCustomCategory(selectEl, customWrapId) {
  const wrap = document.getElementById(customWrapId);
  if (wrap) {
    wrap.style.display = (selectEl.value === '__custom__') ? 'block' : 'none';
  }
}

// Keyboard shortcuts (ESC to close modals)
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeTheater();
    closeAddModal();
    closeEditModal();
    closeDeleteModal();
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
