<?php
$page_title = 'Notifications';
$active_page = 'notifications';
require_once __DIR__ . '/../../includes/header.php';

$uid  = current_uid();
$role = current_role();
$msg  = '';

// Mark as read
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$nid AND (user_id=$uid OR role='$role' OR role='all')");
    header("Location: " . BASE_URL . "modules/settings/notifications.php"); exit;
}
if (isset($_GET['read_all'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE (user_id=$uid OR role='$role' OR role='all')");
    $msg = 'All notifications marked as read.';
}

// Send notification (Master Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_notif') {
    if ($role === 'master') {
        $title   = $conn->real_escape_string(trim($_POST['title'] ?? ''));
        $message = $conn->real_escape_string(trim($_POST['message'] ?? ''));
        $to_role = $conn->real_escape_string($_POST['to_role'] ?? 'all');
        $to_uid  = (int)($_POST['to_user_id'] ?? 0);
        if ($title) {
            if ($to_uid > 0) {
                $conn->query("INSERT INTO notifications (user_id,role,title,message) VALUES ($to_uid,NULL,'$title','$message')");
            } else {
                $conn->query("INSERT INTO notifications (user_id,role,title,message) VALUES (NULL,'$to_role','$title','$message')");
            }
            $msg = 'Notification sent.';
        }
    }
}

if (isset($_GET['delete']) && in_array($role,['admin'])) {
    $conn->query("DELETE FROM notifications WHERE id=".(int)$_GET['delete']);
    $msg = 'Notification deleted.';
}

$notifs = $conn->query("SELECT n.*, u.full_name as sender_name FROM notifications n LEFT JOIN users u ON u.id=$uid WHERE (n.user_id=$uid OR n.role='$role' OR n.role='all') ORDER BY n.created_at DESC");
$users_q = $conn->query("SELECT id,full_name,role FROM users WHERE is_active=1 ORDER BY full_name");
$users_arr = [];
if ($users_q) while($u=$users_q->fetch_assoc()) $users_arr[]=$u;
?>

<div class="page-header">
  <div><h1><i class="fa-solid fa-bell"></i> Notifications</h1><p>View and manage system notifications</p></div>
  <div class="btn-group">
    <?php if($role === 'master'): ?>
    <button class="btn btn-primary" onclick="toggleForm('notifForm')"><i class="fa-solid fa-plus"></i> Send Notification</button>
    <?php endif; ?>
    <a href="?read_all=1" class="btn btn-secondary"><i class="fa-solid fa-check"></i> Mark All Read</a>
  </div>
</div>

<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;"><?= $msg ?></div><?php endif; ?>

<!-- SEND FORM (Master Only) -->
<?php if($role === 'master'): ?>
<div class="card mb-3" id="notifForm" style="display:none">
  <div class="card-header"><h3><i class="fa-solid fa-bullhorn"></i> Send Notification</h3></div>
  <form method="POST">
    <input type="hidden" name="action" value="send_notif">
    <div class="form-row">
      <div class="form-group"><label>Title *</label><input type="text" name="title" class="form-control" required placeholder="Notification title"></div>
      <div class="form-group"><label>Send To Role</label>
        <select name="to_role" class="form-control">
          <option value="all">All Users</option>
          <?php foreach(['admin','principal','accountant','teacher','student','parent'] as $r): ?><option value="<?= $r ?>"><?= ucfirst($r) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Or Specific User</label>
        <select name="to_user_id" class="form-control">
          <option value="0">-- All in role --</option>
          <?php foreach($users_arr as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= $u['role'] ?>)</option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group"><label>Message</label><textarea name="message" class="form-control" rows="3" placeholder="Optional message body..."></textarea></div>
    <div class="btn-group mt-2">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-bullhorn"></i> Send</button>
      <button type="button" class="btn btn-secondary" onclick="toggleForm('notifForm')">Cancel</button>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- NOTIFICATIONS LIST -->
<div class="card">
  <?php if($notifs && $notifs->num_rows > 0): ?>
  <div style="display:flex;flex-direction:column">
    <?php while($n=$notifs->fetch_assoc()): ?>
    <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border);background:<?= $n['is_read']?'transparent':'rgba(99,102,241,.07)' ?>;transition:background .2s">
      <div style="font-size:24px;flex-shrink:0"><?= $n['is_read'] ? '<i class="fa-solid fa-bell"></i>' : '<i class="fa-solid fa-circle text-danger" style="font-size:0.8em"></i>' ?></div>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <strong style="<?= $n['is_read']?'':'color:var(--accent)' ?>"><?= htmlspecialchars($n['title']) ?></strong>
          <?php if(!$n['is_read']): ?><span class="badge badge-info">New</span><?php endif; ?>
        </div>
        <?php if($n['message']): ?><p style="margin:0 0 6px;color:var(--text-muted);font-size:13px"><?= htmlspecialchars($n['message']) ?></p><?php endif; ?>
        <div style="font-size:12px;color:var(--text-muted)"><?= date('d M Y H:i', strtotime($n['created_at'])) ?> · <?= $n['role'] ? 'To: '.ucfirst($n['role']) : 'Personal' ?></div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <?php if(!$n['is_read']): ?><a href="?read=<?= $n['id'] ?>" class="btn btn-xs btn-success" title="Mark Read"><i class="fa-solid fa-check"></i></a><?php endif; ?>
        <?php if(current_role()==='admin'): ?><button class="btn btn-xs btn-danger" onclick="confirmDelete('?delete=<?= $n['id'] ?>','notification')"><i class="fa-solid fa-trash-can"></i>️</button><?php endif; ?>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
  <?php else: ?>
  <div class="empty-state"><div class="icon"><i class="fa-solid fa-bell"></i></div><h3>No notifications</h3><p>You're all caught up!</p></div>
  <?php endif; ?>
</div>

<script>
function toggleForm(id){const el=document.getElementById(id);el.style.display=el.style.display==='none'?'block':'none';}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




