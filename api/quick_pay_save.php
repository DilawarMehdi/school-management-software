<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mid          = (int)($_POST['monthly_fee_id'] ?? 0);
    $amt_paid     = (float)($_POST['amount_paid']  ?? 0);
    $fine_paid    = (float)($_POST['fine_paid']    ?? 0);
    $payment_date = $conn->real_escape_string($_POST['payment_date'] ?? date('Y-m-d'));
    $uid          = current_uid();

    if ($mid > 0) {
        /* ── 1. Load the student_monthly_fees row ── */
        $sq = $conn->query(
            "SELECT invoice_id, student_id, tuition_fee, prev_pending,
                    admission_fee, discount_amount, paid_amount, fine_amount
             FROM student_monthly_fees WHERE id = $mid"
        );
        if (!$sq || $sq->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Fee record not found.']);
            exit;
        }
        $f          = $sq->fetch_assoc();
        $student_id = $f['student_id'];
        $invoice_id = (int)$f['invoice_id'];

        /* ── 2. Record payment history ── */
        $conn->query(
            "INSERT INTO fee_payment_history
                 (monthly_fee_id, amount_paid, fine_paid, payment_date, received_by)
             VALUES ($mid, $amt_paid, $fine_paid, '$payment_date', $uid)"
        );

        /* ── 3. Update student_monthly_fees ── */
        $total_payable  = ($f['tuition_fee'] + $f['prev_pending'] + ($f['admission_fee'] ?? 0))
                          - $f['discount_amount'];
        $new_paid_total = $f['paid_amount'] + $amt_paid;
        $status = ($new_paid_total >= $total_payable) ? 'Paid' : 'Partially Paid';

        $conn->query(
            "UPDATE student_monthly_fees
             SET paid_amount  = $new_paid_total,
                 fine_amount  = fine_amount - $fine_paid,
                 status       = '$status',
                 payment_date = '$payment_date'
             WHERE id = $mid"
        );

        /* ── 4. Sync fee_invoices.received_fee ── */
        // Re-sum ALL paid_amount for every student under this invoice
        if ($invoice_id > 0) {
            $conn->query(
                "UPDATE fee_invoices fi
                 SET fi.received_fee = (
                     SELECT COALESCE(SUM(smf.paid_amount), 0)
                     FROM student_monthly_fees smf
                     WHERE smf.invoice_id = fi.id
                 )
                 WHERE fi.id = $invoice_id"
            );
        }

        echo json_encode(['success' => true, 'student_id' => $student_id]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Record ID']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}




