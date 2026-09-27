<?php
/**
 * SIAX SMSS - Fine Policy Management
 * List view matching SC1.
 */
$page_title = 'Fine Policies';
$active_page = 'fine_policy';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin', 'principal', 'accountant');

// Handle Deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM fine_policies WHERE id = $id");
    header("Location: " . BASE_URL . "modules/fees/fine_policy.php");
    exit;
}

$policies = $conn->query("SELECT * FROM fine_policies ORDER BY created_at DESC");
?>

<div class="main-container">
    <div class="page-header">
        <h1>Fine Policies</h1>
        <a href="manage_fine_policy.php" class="btn btn-primary">Add New Fine Policy</a>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-header-bar" style="background: var(--bg-secondary); padding: 10px 15px; border-bottom: 1px solid var(--border);">
            <i class="fa-solid fa-table"></i>
        </div>
        <div class="table-wrapper" style="border: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Fine Policy Title</th>
                        <th style="width: 20%;">Created On</th>
                        <th style="width: 20%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($policies && $policies->num_rows > 0): while($row = $policies->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                            <td><?= date('d-M-Y', strtotime($row['created_at'])) ?></td>
                            <td>
                                <div class="action-links">
                                    <a href="manage_fine_policy.php?id=<?= $row['id'] ?>">Update</a>
                                    <span class="action-sep">|</span>
                                    <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this policy?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="3" class="text-center text-muted" style="padding: 40px;">No fine policies defined yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




