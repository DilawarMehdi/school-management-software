<?php
/**
 * SIAX SMSS — Subject Management
 * Add / Update (name only) / Delete subjects
 */
$page_title  = 'Subject Management';
$active_page = 'subjects';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'teacher');

$success = $error = '';

/* ─── ACTIONS ───────────────────────────────────────── */

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name = trim($conn->real_escape_string($_POST['name'] ?? ''));
    if ($name === '') {
        $error = 'Subject name cannot be empty.';
    } else {
        $conn->query("INSERT INTO subjects (name, created_at) VALUES ('$name', NOW())");
        if ($conn->affected_rows > 0) {
            $success = "Subject <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
        } else {
            $error = 'Could not save subject. DB error: ' . ($conn->error ?: 'unknown');
        }
    }
}

// UPDATE (name only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id   = (int)($_POST['id']   ?? 0);
    $name = trim($conn->real_escape_string($_POST['name'] ?? ''));
    if ($id < 1 || $name === '') {
        $error = 'Invalid subject data.';
    } else {
        $conn->query("UPDATE subjects SET name='$name' WHERE id=$id");
        $success = "Subject updated to <strong>" . htmlspecialchars($name) . "</strong>.";
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $conn->query("DELETE FROM subjects WHERE id=$id");
        $success = 'Subject deleted successfully.';
    }
}

// Fetch all subjects
$subjects_q = $conn->query("SELECT id, name, created_at FROM subjects ORDER BY name ASC");
$subjects   = [];
if ($subjects_q) while ($s = $subjects_q->fetch_assoc()) $subjects[] = $s;

// Subject to edit
$edit_subject = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $eq  = $conn->query("SELECT id, name FROM subjects WHERE id=$eid LIMIT 1");
    if ($eq && $eq->num_rows) $edit_subject = $eq->fetch_assoc();
}
?>

<style>
/* ============================================
   SIAX Premium — Subject Management
   ============================================ */
.subj-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 12px;
}
.subj-header h1 {
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Add / Edit Card */
.subj-form-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 22px 24px;
    margin-bottom: 22px;
    box-shadow: var(--shadow);
    display: none; /* toggled by JS */
}
.subj-form-card.open { display: block; }
.subj-form-card h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.subj-form-row {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.subj-form-group {
    flex: 1;
    min-width: 220px;
}
.subj-form-group label {
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.subj-form-group input[type="text"] {
    width: 100%;
    height: 42px;
    padding: 0 14px;
    border: 1px solid var(--border);
    border-radius: var(--radius-xs);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 500;
    transition: all var(--transition);
    box-sizing: border-box;
}
.subj-form-group input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
    outline: none;
}
.subj-form-actions {
    display: flex;
    gap: 8px;
}
.btn-save {
    height: 42px;
    padding: 0 24px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: var(--radius-xs);
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all var(--transition);
    white-space: nowrap;
}
.btn-save:hover {
    opacity: 0.9;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px var(--accent-glow);
}
.btn-cancel-form {
    height: 42px;
    padding: 0 18px;
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xs);
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all var(--transition);
}
.btn-cancel-form:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

/* Table Section */
.subj-table-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.subj-table-top {
    background: #1e293b;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.88rem;
    font-weight: 700;
    color: #f8fafc;
}
.subj-table-top .subj-count-badge {
    background: rgba(255,255,255,0.15);
    color: #fff;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-left: auto;
}

/* Search bar */
.subj-search-row {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
}
.subj-search-wrap {
    position: relative;
    max-width: 320px;
}
.subj-search-wrap i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.8rem;
}
.subj-search-wrap input {
    width: 100%;
    height: 36px;
    padding: 0 12px 0 34px;
    border: 1px solid var(--border);
    border-radius: var(--radius-xs);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 0.85rem;
    box-sizing: border-box;
    transition: all var(--transition);
}
.subj-search-wrap input:focus {
    border-color: var(--accent);
    outline: none;
    box-shadow: 0 0 0 3px var(--accent-glow);
}

/* Table */
.subj-table {
    width: 100%;
    border-collapse: collapse;
}
.subj-table thead th {
    padding: 11px 18px;
    background: #f8fafc;
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    text-align: left;
    border-bottom: 2px solid var(--border);
}
[data-theme="dark"] .subj-table thead th {
    background: rgba(15,23,42,0.5);
    color: #64748b;
}
.subj-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background var(--transition);
}
.subj-table tbody tr:last-child { border-bottom: none; }
.subj-table tbody tr:hover { background: rgba(59,130,246,0.04); }
.subj-table tbody td {
    padding: 11px 18px;
    font-size: 0.88rem;
    color: var(--text-primary);
    vertical-align: middle;
}
.subj-row-num {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 600;
    width: 40px;
}
.subj-name {
    font-weight: 600;
    color: var(--text-primary);
}
.subj-actions {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
}
.btn-edit-subj {
    height: 30px;
    padding: 0 14px;
    background: rgba(59,130,246,0.1);
    color: #3b82f6;
    border: 1px solid rgba(59,130,246,0.2);
    border-radius: 6px;
    font-size: 0.77rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    transition: all var(--transition);
}
.btn-edit-subj:hover {
    background: rgba(59,130,246,0.18);
    border-color: #3b82f6;
    color: #2563eb;
}
.btn-del-subj {
    height: 30px;
    padding: 0 14px;
    background: rgba(239,68,68,0.08);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.18);
    border-radius: 6px;
    font-size: 0.77rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    transition: all var(--transition);
}
.btn-del-subj:hover {
    background: rgba(239,68,68,0.16);
    border-color: #ef4444;
}

/* Empty state */
.subj-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}
.subj-empty i { font-size: 2.5rem; opacity: 0.25; display: block; margin-bottom: 12px; }
.subj-empty p { font-size: 0.9rem; margin: 0; }

/* Alert */
.subj-alert {
    padding: 12px 18px;
    border-radius: var(--radius-xs);
    font-size: 0.88rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}
.subj-alert.success { background: rgba(16,185,129,0.1); color: #059669; border: 1px solid rgba(16,185,129,0.2); }
.subj-alert.error   { background: rgba(239,68,68,0.1);  color: #dc2626; border: 1px solid rgba(239,68,68,0.2);  }
</style>

<div class="main-container">

    <!-- Page Header -->
    <div class="subj-header">
        <h1>
            <i class="fa-solid fa-book" style="color:var(--accent);"></i>
            Subject Management
        </h1>
        <button class="btn btn-primary" onclick="openAddForm()" id="addSubjBtn">
            <i class="fa-solid fa-plus"></i> Add New Subject
        </button>
    </div>

    <!-- Alert -->
    <?php if ($success): ?>
    <div class="subj-alert success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div>
    <?php elseif ($error): ?>
    <div class="subj-alert error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Add / Edit Form Card -->
    <div class="subj-form-card <?= $edit_subject ? 'open' : '' ?>" id="subjFormCard">
        <h3>
            <i class="fa-solid fa-<?= $edit_subject ? 'pen' : 'plus-circle' ?>" style="color:var(--accent);"></i>
            <?= $edit_subject ? 'Update Subject' : 'Add New Subject' ?>
        </h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="<?= $edit_subject ? 'update' : 'add' ?>">
            <?php if ($edit_subject): ?>
                <input type="hidden" name="id" value="<?= $edit_subject['id'] ?>">
            <?php endif; ?>
            <div class="subj-form-row">
                <div class="subj-form-group">
                    <label for="subjectName">Subject Name</label>
                    <input type="text" id="subjectName" name="name"
                           placeholder="e.g. Mathematics"
                           value="<?= htmlspecialchars($edit_subject['name'] ?? '') ?>"
                           autocomplete="off" required>
                </div>
                <div class="subj-form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fa-solid fa-<?= $edit_subject ? 'floppy-disk' : 'plus' ?>"></i>
                        <?= $edit_subject ? 'Save Changes' : 'Add Subject' ?>
                    </button>
                    <a href="<?= BASE_URL ?>modules/exams/subjects.php" class="btn-cancel-form">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Subjects Table Card -->
    <div class="subj-table-card">
        <div class="subj-table-top">
            <i class="fa-solid fa-table-list"></i>
            Subjects
            <span class="subj-count-badge"><?= count($subjects) ?> Total</span>
        </div>

        <div class="subj-search-row">
            <div class="subj-search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="subjSearch" placeholder="Search subjects..." oninput="filterSubjects(this.value)">
            </div>
        </div>

        <table class="subj-table" id="subjTable">
            <thead>
                <tr>
                    <th style="width:48px;">#</th>
                    <th>Title</th>
                    <th style="width:160px; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody id="subjBody">
                <?php if (empty($subjects)): ?>
                <tr>
                    <td colspan="3">
                        <div class="subj-empty">
                            <i class="fa-solid fa-book-open"></i>
                            <p>No subjects found. Click <strong>Add New Subject</strong> to get started.</p>
                        </div>
                    </td>
                </tr>
                <?php else: $i = 1; foreach ($subjects as $subj): ?>
                <tr class="subj-row" data-name="<?= strtolower(htmlspecialchars($subj['name'])) ?>">
                    <td class="subj-row-num"><?= $i++ ?></td>
                    <td class="subj-name"><?= htmlspecialchars($subj['name']) ?></td>
                    <td>
                        <div class="subj-actions">
                            <a href="?edit=<?= $subj['id'] ?>" class="btn-edit-subj">
                                <i class="fa-solid fa-pen-to-square"></i> Update
                            </a>
                            <a href="?delete=<?= $subj['id'] ?>"
                               class="btn-del-subj"
                               onclick="return confirm('Delete subject: <?= addslashes(htmlspecialchars($subj['name'])) ?>?')">
                                <i class="fa-solid fa-trash-can"></i> Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function openAddForm() {
    const card = document.getElementById('subjFormCard');
    card.classList.toggle('open');
    if (card.classList.contains('open')) {
        document.getElementById('subjectName').focus();
    }
}

function filterSubjects(val) {
    const q = val.toLowerCase().trim();
    document.querySelectorAll('#subjBody .subj-row').forEach(row => {
        const name = row.dataset.name || '';
        row.style.display = name.includes(q) ? '' : 'none';
    });
}

// Auto-open form if editing
<?php if ($edit_subject): ?>
document.getElementById('subjFormCard').classList.add('open');
document.getElementById('subjectName').focus();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
