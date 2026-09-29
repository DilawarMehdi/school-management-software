<?php
$conn = new mysqli('localhost', 'root', '', 'siax_smss');
echo "--- latest invoices ---\n";
$res = $conn->query("SELECT id, title, created_at FROM fee_invoices ORDER BY id DESC LIMIT 5");
while($row = $res->fetch_assoc()) {
    $id = $row['id'];
    $cnt_q = $conn->query("SELECT COUNT(*) as cnt FROM student_monthly_fees WHERE invoice_id = $id");
    $cnt = $cnt_q->fetch_assoc()['cnt'];
    echo "ID: $id | Title: {$row['title']} | Records: $cnt | Date: {$row['created_at']}\n";
}
?>



