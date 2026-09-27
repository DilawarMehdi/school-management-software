<?php
/**
 * SIAX SMSS - Import Previous Pending API
 * Calculates each student's real unpaid balance from prior invoices
 * and writes it into student_monthly_fees.prev_pending for the given invoice.
 * Also updates fee_invoices.prev_pending with the total.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
if (!$invoice_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid invoice ID.']);
    exit;
}

// Fetch all students in this invoice
$students_q = $conn->query("SELECT id, student_id FROM student_monthly_fees WHERE invoice_id = $invoice_id");
if (!$students_q) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch students.']);
    exit;
}

$total_prev_pending = 0;
$updated_count = 0;

while ($row = $students_q->fetch_assoc()) {
    $smf_id = (int)$row['id'];
    $sid    = (int)$row['student_id'];

    // Sum all unpaid balances from PREVIOUS invoices for this student
    $pending_q = $conn->query("
        SELECT COALESCE(SUM(
            (tuition_fee + admission_fee + COALESCE(prev_pending, 0) + COALESCE(fine_amount, 0))
            - COALESCE(discount_amount, 0)
            - COALESCE(paid_amount, 0)
        ), 0) AS total_due
        FROM student_monthly_fees
        WHERE student_id = $sid
          AND invoice_id != $invoice_id
          AND status IN ('Due', 'Partial')
    ");

    $prev_pending = 0;
    if ($pending_q) {
        $prev_pending = max(0, (float)$pending_q->fetch_assoc()['total_due']);
    }

    // Update this student's record in the current invoice
    $conn->query("UPDATE student_monthly_fees SET prev_pending = $prev_pending WHERE id = $smf_id");
    $total_prev_pending += $prev_pending;
    $updated_count++;
}

// Update the invoice-level summary
$conn->query("UPDATE fee_invoices SET prev_pending = $total_prev_pending WHERE id = $invoice_id");

echo json_encode([
    'success'       => true,
    'message'       => "Prev pending imported for $updated_count students.",
    'total_pending' => $total_prev_pending,
    'invoice_id'    => $invoice_id,
]);
