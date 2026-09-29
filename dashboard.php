<?php
$page_title = 'Dashboard';
$active_page = 'dashboard';
require_once __DIR__ . '/includes/header.php';

// Stats (session-aware)
$active_session = $settings['session_year'] ?? '2025-2026';
$total_students = $conn->query("
    SELECT COUNT(DISTINCT se.student_id) as c 
    FROM student_enrollments se 
    JOIN students s ON se.student_id = s.id 
    WHERE se.session_year = '$active_session' AND se.status = 'Active' AND s.status = 'Active'
")->fetch_assoc()['c'] ?? 0;
$total_classes = $conn->query("SELECT COUNT(*) as c FROM classes")->fetch_assoc()['c'] ?? 0;
$total_teachers = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='teacher' AND is_active=1")->fetch_assoc()['c'] ?? 0;
$total_users = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_active=1")->fetch_assoc()['c'] ?? 0;

// Fee stats (Direct payments + Monthly invoice payments)
$fee_collected = 0;
$fee_pending = 0;

// 1. Collect from direct fee payments
$collected_direct = 0;
$r1 = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as collected FROM fee_payments WHERE session_year='$active_session'");
if ($r1) $collected_direct = (float)$r1->fetch_assoc()['collected'];

// 2. Collect from monthly invoice payments
$collected_invoices = 0;
$r2 = $conn->query("
    SELECT COALESCE(SUM(smf.paid_amount),0) as collected 
    FROM student_monthly_fees smf
    JOIN fee_invoices fi ON smf.invoice_id = fi.id
    WHERE fi.session_year = '$active_session'
");
if ($r2) $collected_invoices = (float)$r2->fetch_assoc()['collected'];

$fee_collected = $collected_direct + $collected_invoices;

// Today's attendance
$today = date('Y-m-d');
$present_today = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date='$today' AND status='Present'")->fetch_assoc()['c'] ?? 0;
$absent_today = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date='$today' AND status='Absent'")->fetch_assoc()['c'] ?? 0;

// Recent students (session-aware)
$recent_students = $conn->query("
    SELECT s.*, c.name as class_name, c.section 
    FROM student_enrollments se 
    JOIN students s ON se.student_id = s.id 
    LEFT JOIN classes c ON se.class_id = c.id 
    WHERE se.session_year = '$active_session' AND se.status = 'Active' AND s.status = 'Active' 
    ORDER BY s.created_at DESC LIMIT 5
");

// Recent payments (session-aware - combining direct collections and invoice payments)
$recent_payments = $conn->query("
    (
        SELECT 
            fp.receipt_no, 
            s.name as student_name, 
            s.admission_no, 
            fp.paid_amount, 
            fp.payment_date, 
            fp.created_at 
        FROM fee_payments fp 
        JOIN students s ON fp.student_id = s.id 
        WHERE fp.session_year = '$active_session'
    )
    UNION ALL
    (
        SELECT 
            CONCAT('INV-', fph.id) as receipt_no, 
            s.name as student_name, 
            s.admission_no, 
            fph.amount_paid as paid_amount, 
            fph.payment_date, 
            fph.created_at 
        FROM fee_payment_history fph
        JOIN student_monthly_fees smf ON fph.monthly_fee_id = smf.id
        JOIN fee_invoices fi ON smf.invoice_id = fi.id
        JOIN students s ON smf.student_id = s.id
        WHERE fi.session_year = '$active_session'
    )
    ORDER BY created_at DESC 
    LIMIT 5
");

$currency = $settings['currency'] ?? 'PKR';
?>

<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars($user['full_name'] ?? $user['name'] ?? 'User') ?> — <?= date('l, d M Y') ?></p>
  </div>
</div>

<!-- STAT CARDS -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
    <div class="stat-info">
      <h3><?= $total_students ?></h3>
      <p>Active Students</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-school"></i></div>
    <div class="stat-info">
      <h3><?= $total_classes ?></h3>
      <p>Classes</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-user"></i></div>
    <div class="stat-info">
      <h3><?= $total_teachers ?></h3>
      <p>Teachers</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-info">
      <h3><?= $currency ?> <?= number_format($fee_collected) ?></h3>
      <p>Fee Collected</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-circle-check text-success"></i></div>
    <div class="stat-info">
      <h3><?= $present_today ?></h3>
      <p>Present Today</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fa-solid fa-circle-xmark text-danger"></i></div>
    <div class="stat-info">
      <h3><?= $absent_today ?></h3>
      <p>Absent Today</p>
    </div>
  </div>
</div>

<!-- RECENT DATA TABLES -->
<div class="card-grid card-grid-2">
  <!-- Recent Students -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-users"></i> Recent Admissions</h3>
      <a href="<?= BASE_URL ?>modules/students/students.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <?php if ($recent_students && $recent_students->num_rows > 0): ?>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Adm#</th><th>Name</th><th>Class</th><th>Date</th></tr></thead>
        <tbody>
        <?php while($s = $recent_students->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($s['admission_no']) ?></td>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= htmlspecialchars(($s['class_name'] ?? '-') . ' ' . ($s['section'] ?? '')) ?></td>
          <td><?= $s['admission_date'] ? date('d M Y', strtotime($s['admission_date'])) : '-' ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <div class="icon"><i class="fa-solid fa-users"></i></div>
      <h3>No students yet</h3>
      <p>Add students from the Students module.</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent Payments -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-money-bill-wave"></i> Recent Payments</h3>
      <a href="<?= BASE_URL ?>modules/fees/fees.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Receipt#</th><th>Student</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
        <?php while($p = $recent_payments->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($p['receipt_no']) ?></td>
          <td><?= htmlspecialchars($p['student_name']) ?></td>
          <td class="text-success"><?= $currency ?> <?= number_format($p['paid_amount']) ?></td>
          <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <div class="icon"><i class="fa-solid fa-money-bill-wave"></i></div>
      <h3>No payments yet</h3>
      <p>Collect fees from the Fee module.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- RESOURCES & VIDEO TUTORIALS SECTION -->
<div class="card" style="margin-top:24px">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:34px;height:34px;border-radius:8px;background:rgba(0,184,148,0.15);color:#00b894;display:flex;align-items:center;justify-content:center;font-size:1.1rem">
        <i class="fa-solid fa-play"></i>
      </div>
      <div>
        <h3 style="margin:0;font-size:1.05rem;font-weight:700">Resources &amp; Video Tutorials</h3>
        <p style="margin:0;font-size:.78rem;color:var(--text-muted)">Watch step-by-step video guides from the video library</p>
      </div>
    </div>
    <a href="<?= BASE_URL ?>modules/resources/videos.php" class="btn btn-sm btn-primary" style="display:flex;align-items:center;gap:6px">
      <i class="fa-solid fa-video"></i> View All Video Resources
    </a>
  </div>

  <div style="padding:20px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:20px">
      <?php
      $dash_video_dir = __DIR__ . '/videos/';
      $dash_video_web = BASE_URL . 'videos/';
      
      // Query video_resources from database
      $dash_videos = [];
      $dash_v_res = $conn->query("SELECT * FROM video_resources ORDER BY is_featured DESC, sort_order ASC, id DESC LIMIT 3");
      if ($dash_v_res && $dash_v_res->num_rows > 0) {
        while ($r = $dash_v_res->fetch_assoc()) {
          $f_check = $dash_video_dir . $r['file_name'];
          if (file_exists($f_check)) {
            $dash_videos[] = [
              'file' => $r['file_name'],
              'title' => $r['title'],
              'category' => $r['category'],
              'desc' => $r['description'],
              'badge' => $r['badge'] ?: 'Tutorial',
              'badge_color' => $r['badge_color'] ?: '#00b894',
              'icon' => $r['icon'] ?: 'fa-video'
            ];
          }
        }
      }

      if (empty($dash_videos)):
      ?>
      <div style="grid-column:1/-1;text-align:center;padding:36px 20px;color:var(--text-muted);background:var(--bg-secondary);border:1px solid var(--border);border-radius:12px">
        <i class="fa-solid fa-video-slash" style="font-size:2.2rem;opacity:.4;margin-bottom:10px;display:block"></i>
        <h4 style="font-size:1rem;font-weight:700;color:var(--text-primary);margin-bottom:4px">No Active Video Tutorials</h4>
        <p style="font-size:.85rem;margin-bottom:14px">Upload video tutorials to the resource center to display them here.</p>
        <a href="<?= BASE_URL ?>modules/resources/videos.php" class="btn btn-sm btn-primary" style="display:inline-flex;align-items:center;gap:6px">
          <i class="fa-solid fa-upload"></i> Go to Video Resource Center
        </a>
      </div>
      <?php else: foreach($dash_videos as $dv):
        $dv_path = $dash_video_dir . $dv['file'];
        $dv_url  = $dash_video_web . rawurlencode($dv['file']);
        $dv_size = file_exists($dv_path) ? filesize($dv_path) : 0;
        $dv_size_str = ($dv_size >= 1048576) ? number_format($dv_size / 1048576, 1) . ' MB' : number_format($dv_size / 1024, 0) . ' KB';
      ?>
      <div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--shadow);transition:transform .2s ease">
        <div style="position:relative;width:100%;aspect-ratio:16/9;background:#090e17;overflow:hidden;cursor:pointer;display:flex;align-items:center;justify-content:center" onclick="openDashVideo('<?= htmlspecialchars($dv_url) ?>', '<?= htmlspecialchars(addslashes($dv['title'])) ?>', '<?= htmlspecialchars($dv['category']) ?>')">
          <video preload="metadata" muted playsinline style="width:100%;height:100%;object-fit:cover;opacity:.85">
            <source src="<?= htmlspecialchars($dv_url) ?>" type="video/mp4">
          </video>
          <div style="position:absolute;top:10px;left:10px;background:<?= $dv['badge_color'] ?>;color:#fff;font-size:.65rem;font-weight:700;padding:3px 8px;border-radius:4px;text-transform:uppercase;z-index:2">
            <?= htmlspecialchars($dv['badge']) ?>
          </div>
          <div style="position:absolute;bottom:8px;right:10px;background:rgba(0,0,0,0.75);color:#fff;font-size:.65rem;font-weight:700;padding:2px 6px;border-radius:4px;z-index:2">
            <?= $dv_size_str ?>
          </div>
          <div style="position:absolute;width:48px;height:48px;border-radius:50%;background:#00b894;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;box-shadow:0 4px 15px rgba(0,0,0,0.5);border:2px solid rgba(255,255,255,0.4);z-index:2">
            <i class="fa-solid fa-play" style="margin-left:2px"></i>
          </div>
        </div>

        <div style="padding:16px;flex:1;display:flex;flex-direction:column">
          <div style="font-size:.72rem;font-weight:700;color:#00b894;text-transform:uppercase;margin-bottom:6px;display:flex;align-items:center;gap:6px">
            <i class="fa-solid <?= $dv['icon'] ?>"></i> <?= htmlspecialchars($dv['category']) ?>
          </div>
          <h4 style="font-size:.95rem;font-weight:700;color:var(--text-primary);margin-bottom:6px;line-height:1.35"><?= htmlspecialchars($dv['title']) ?></h4>
          <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px;line-height:1.45;flex:1"><?= htmlspecialchars($dv['desc']) ?></p>
          
          <div style="display:flex;gap:8px">
            <button class="btn btn-sm btn-primary" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px" onclick="openDashVideo('<?= htmlspecialchars($dv_url) ?>', '<?= htmlspecialchars(addslashes($dv['title'])) ?>', '<?= htmlspecialchars($dv['category']) ?>')">
              <i class="fa-solid fa-play"></i> Watch Video
            </button>
            <a href="<?= htmlspecialchars($dv_url) ?>" download class="btn btn-sm btn-secondary" title="Download Video">
              <i class="fa-solid fa-download"></i>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- DASHBOARD VIDEO PLAYER MODAL -->
<div id="dashVideoModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);z-index:99999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#0f172a;border:1px solid rgba(255,255,255,0.15);border-radius:16px;width:100%;max-width:920px;box-shadow:0 20px 60px rgba(0,0,0,0.8);overflow:hidden;display:flex;flex-direction:column">
    <div style="padding:14px 20px;background:#1e293b;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.1)">
      <div style="display:flex;align-items:center;gap:10px;overflow:hidden">
        <span id="dashModalBadge" style="background:#00b894;color:#fff;font-size:.68rem;font-weight:700;padding:3px 8px;border-radius:4px;text-transform:uppercase">Tutorial</span>
        <div id="dashModalTitle" style="font-size:1rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Video Title</div>
      </div>
      <button onclick="closeDashVideo()" style="background:rgba(255,255,255,0.1);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    
    <div style="position:relative;width:100%;aspect-ratio:16/9;background:#000">
      <video id="dashModalPlayer" controls preload="auto" style="width:100%;height:100%">
        <source id="dashModalSrc" src="" type="video/mp4">
      </video>
    </div>
    
    <div style="padding:10px 20px;background:#1e293b;display:flex;align-items:center;justify-content:space-between;font-size:.78rem;color:#94a3b8">
      <span><i class="fa-solid fa-circle-info"></i> SIAX SMSS Resource Center</span>
      <a href="<?= BASE_URL ?>modules/resources/videos.php" style="color:#00b894;text-decoration:underline">Explore full video library &rarr;</a>
    </div>
  </div>
</div>

<script>
function openDashVideo(url, title, category) {
  const modal = document.getElementById('dashVideoModal');
  const player = document.getElementById('dashModalPlayer');
  document.getElementById('dashModalTitle').innerText = title;
  document.getElementById('dashModalBadge').innerText = category;
  player.src = url;
  modal.style.display = 'flex';
  player.play().catch(e => console.log(e));
}

function closeDashVideo() {
  const modal = document.getElementById('dashVideoModal');
  const player = document.getElementById('dashModalPlayer');
  player.pause();
  player.src = '';
  modal.style.display = 'none';
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeDashVideo();
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>




