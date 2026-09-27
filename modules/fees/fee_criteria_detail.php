<?php
/**
 * SIAX SMSS - Fee Criteria Detail (Print View)
 * Beautiful PDF-style view for fee criteria details.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("Criteria ID not provided.");
}

// Fetch Settings
$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'SIAX SMSS';
$school_logo = $settings['school_logo'] ?? '';

// Fetch Criteria
$res = $conn->query("SELECT * FROM fee_criteria WHERE id = $id");
$criteria = $res->fetch_assoc();
if (!$criteria) {
    die("Criteria not found.");
}

// Ensure details table exists
$conn->query("CREATE TABLE IF NOT EXISTS fee_criteria_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  criteria_id INT NOT NULL,
  class_id INT NOT NULL,
  standard_fee DECIMAL(10,2) DEFAULT 0,
  UNIQUE KEY unique_criteria_class (criteria_id, class_id)
)");

// Fetch Fee Details
$details_q = $conn->query("
    SELECT c.name as class_name, c.section, fd.standard_fee 
    FROM classes c 
    LEFT JOIN fee_criteria_details fd ON c.id = fd.class_id AND fd.criteria_id = $id 
    ORDER BY c.name, c.section
");
$details = [];
if ($details_q) {
    while ($row = $details_q->fetch_assoc()) {
        $details[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Criteria Detail - <?= htmlspecialchars($criteria['title']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; color: #000; padding: 40px; }
        
        /* Print Header */
        .print-header { display: flex; align-items: center; justify-content: center; margin-bottom: 30px; border-bottom: 1px solid #ddd; padding-bottom: 20px; position: relative; }
        .logo { width: 120px; height: 120px; position: absolute; left: 0; top: 0; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        .school-info { text-align: center; }
        .school-info h1 { font-size: 28px; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; }
        .school-info h2 { font-size: 18px; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; color: #444; }
        .school-info p { font-size: 13px; color: #666; margin: 2px 0; }

        /* Meta Info */
        .meta-info { font-size: 15px; margin-bottom: 20px; display: flex; gap: 10px; }
        .meta-info strong { font-weight: 700; }

        /* Main Container */
        .detail-box { border: 1px solid #ccc; border-radius: 4px; overflow: hidden; }
        .detail-header { background: #f8f9fa; padding: 10px 15px; font-weight: 700; border-bottom: 1px solid #ccc; font-size: 16px; }
        .detail-body { padding: 15px; background: #fff; }

        /* Class Row Item */
        .class-item { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px; padding: 15px; }
        .class-title { font-weight: 700; font-size: 15px; margin-bottom: 10px; color: #333; }
        .fee-bar { display: flex; gap: 5px; }
        .bar-segment { background: #7f7f7f; color: #fff; padding: 6px 12px; font-size: 13px; font-weight: 600; border-radius: 2px; }
        .bar-segment.section { min-width: 150px; }
        .bar-segment.fee { flex: 1; }

        /* Buttons */
        .no-print { margin-bottom: 20px; text-align: right; }
        .btn { padding: 8px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; border: none; font-size: 14px; }
        .btn-print { background: #1a5276; color: #fff; }
        .btn-close { background: #777; color: #fff; margin-left: 10px; }

        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
            .detail-box { border: 1px solid #000; }
            .class-item { border: 1px solid #999; page-break-inside: avoid; }
            .bar-segment { background: #7f7f7f !important; color: #fff !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">Print PDF</button>
        <button class="btn btn-close" onclick="window.close()">Close</button>
    </div>

    <!-- SCHOOL HEADER -->
    <div class="print-header">
        <div class="logo">
            <?php if($school_logo): ?>
                <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($school_logo) ?>" alt="Logo">
            <?php else: ?>
                <div style="width:100%; height:100%; background:#1a5276; color:#fff; display:flex; align-items:center; justify-content:center; border-radius:50%; font-size:40px; font-weight:800;">
                    <?= strtoupper(substr($school_name,0,1)) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="school-info">
            <h1><?= htmlspecialchars($school_name) ?></h1>
            <h2><?= htmlspecialchars($school_name) ?></h2>
            <p>Phone : <?= htmlspecialchars($settings['school_phone'] ?? '05815-452005') ?></p>
            <p><?= htmlspecialchars($settings['school_website'] ?? 'www.siaxsmss.com') ?></p>
        </div>
    </div>

    <!-- META INFO -->
    <div class="meta-info">
        Fee Criteria Title <strong><?= htmlspecialchars($criteria['title']) ?></strong> 
        Created On <strong><?= date('d/M/Y', strtotime($criteria['created_at'])) ?></strong>
    </div>

    <!-- DETAIL BOX -->
    <div class="detail-box">
        <div class="detail-header">Fee Criteria Detail</div>
        <div class="detail-body">
            <?php if (count($details) > 0): ?>
                <?php foreach ($details as $row): ?>
                    <div class="class-item">
                        <div class="class-title"><?= htmlspecialchars($row['class_name']) ?></div>
                        <div class="fee-bar">
                            <div class="bar-segment section"><?= htmlspecialchars($row['section']) ?></div>
                            <div class="bar-segment fee">Standard Fee <?= number_format($row['standard_fee'] ?? 0) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">No class fee details defined.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>




