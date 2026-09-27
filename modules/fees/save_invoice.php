<?php
/**
 * SIAX SMSS - Save Invoice Logic
 * Final step to persist the generated invoice.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $fine_policy_id = (int)$_POST['fine_policy_id'];
    
    // Date conversion for SQL
    $created_at = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['created_at'])));
    $due_date = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['due_date'])));
    $valid_till = date('Y-m-d', strtotime(str_replace('/', '-', $_POST['valid_till'])));
    
    $fee_items = $_POST['fee_items'] ?? [];
    $generate_for = $_POST['generate_for'] ?? []; // Map of class_id => 1

    $total_students = 0;
    $total_current_fee = 0;
    $total_waived_fee = 0;
    $total_prev_pending = 0; // Auto-calculated from unpaid prior invoices
    $month_str = date('F', strtotime($created_at));
    $session_year = all_settings($conn)['session_year'] ?? '2025-2026';

    // Batch advance months array
    $batch_advance_months = [];
    if (isset($_POST['add_advance_batch']) && $_POST['add_advance_batch'] === 'Yes' && !empty($_POST['advance_months_batch'])) {
        foreach ($_POST['advance_months_batch'] as $bm) {
            $batch_advance_months[] = $conn->real_escape_string($bm);
        }
    }

    // Calculate totals from the selected classes
    foreach ($generate_for as $class_id => $val) {
        $class_id = (int)$class_id;
        
        // Count students
        $s_count_q = $conn->query("
            SELECT COUNT(*) as cnt 
            FROM student_enrollments se 
            JOIN students s ON se.student_id = s.id 
            WHERE se.class_id = $class_id AND se.session_year = '$session_year' AND se.status = 'Active' AND s.status = 'Active'
        ");
        $s_count = $s_count_q->fetch_assoc()['cnt'];
        $total_students += $s_count;

        // 1. Calculate Standard Fee per student in this class
        $std_fee = 0;
        foreach ($fee_items as $item) {
            if ($item['apply_type'] === 'Same') {
                $std_fee += (float)$item['amount'];
            } else {
                $crit_id = (int)($item['criteria_id'] ?? 0);
                $crit_amt_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $crit_id AND class_id = $class_id");
                if ($crit_amt_q && $crit_amt_q->num_rows > 0) {
                    $std_fee += (float)$crit_amt_q->fetch_assoc()['standard_fee'];
                }
            }
        }
        $total_current_fee += ($std_fee * $s_count);

        // Add batch advance fee to current fee total
        if (!empty($batch_advance_months)) {
            $tuition_item_amt = 0;
            foreach ($fee_items as $item) {
                if (stripos($item['title'], 'Tuition') !== false) {
                    if ($item['apply_type'] === 'Same') {
                        $tuition_item_amt += (float)$item['amount'];
                    } else {
                        $crit_id = (int)($item['criteria_id'] ?? 0);
                        $crit_amt_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $crit_id AND class_id = $class_id");
                        if ($crit_amt_q && $crit_amt_q->num_rows > 0) {
                            $tuition_item_amt += (float)$crit_amt_q->fetch_assoc()['standard_fee'];
                        }
                    }
                }
            }
            if ($tuition_item_amt <= 0) $tuition_item_amt = $std_fee;
            
            $total_current_fee += ($tuition_item_amt * count($batch_advance_months) * $s_count);
        }

        // Add student-specific pending advance fees to current fee total
        $stud_adv_q = $conn->query("SELECT SUM(amount) as amt FROM advance_fees WHERE class_id = $class_id AND status = 'Pending'");
        if ($stud_adv_q) {
            $total_current_fee += (float)$stud_adv_q->fetch_assoc()['amt'];
        }

        // 2. Calculate Discounts for students in this class (session-aware)
        // Build concession-eligible base for this class
        $summary_concession_base = 0;
        foreach ($fee_items as $item) {
            if (!empty($item['apply_concession']) && $item['apply_concession'] != '0') {
                if ($item['apply_type'] === 'Same') {
                    $summary_concession_base += (float)$item['amount'];
                } else {
                    $crit_id = (int)($item['criteria_id'] ?? 0);
                    $crit_amt_q2 = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $crit_id AND class_id = $class_id");
                    if ($crit_amt_q2 && $crit_amt_q2->num_rows > 0) {
                        $summary_concession_base += (float)$crit_amt_q2->fetch_assoc()['standard_fee'];
                    }
                }
            }
        }
        // Fallback to full std_fee if no concession flags set (backward compatibility)
        $summary_disc_base = $summary_concession_base > 0 ? $summary_concession_base : $std_fee;

        $s_discounts_q = $conn->query("
            SELECT s.discount_amount, s.discount_type 
            FROM student_enrollments se 
            JOIN students s ON se.student_id = s.id 
            WHERE se.class_id = $class_id AND se.session_year = '$session_year' AND se.status = 'Active' AND s.status = 'Active'
        ");
        while($sd = $s_discounts_q->fetch_assoc()){
            if($sd['discount_type'] === 'Percentage'){
                $total_waived_fee += ($summary_disc_base * (float)$sd['discount_amount'] / 100);
            } else {
                $total_waived_fee += (float)$sd['discount_amount'];
            }
        }
    }

    // Insert Invoice Record
    $sql = "INSERT INTO fee_invoices (title, month, session_year, created_at, due_date, valid_till, student_count, current_fee, waived_fee) 
            VALUES ('$title', '$month_str', '$session_year', '$created_at', '$due_date', '$valid_till', $total_students, $total_current_fee, $total_waived_fee)";
    
    if ($conn->query($sql)) {
        $invoice_id = $conn->insert_id;

        // --- Generate individual student fee entries ---
        foreach ($generate_for as $class_id => $val) {
            $class_id = (int)$class_id;
            
            // Calculate Class Standard Fee
            $std_fee = 0;
            foreach ($fee_items as $item) {
                if ($item['apply_type'] === 'Same') {
                    $std_fee += (float)$item['amount'];
                } else {
                    $crit_id = (int)($item['criteria_id'] ?? 0);
                    $crit_amt_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $crit_id AND class_id = $class_id");
                    if ($crit_amt_q && $crit_amt_q->num_rows > 0) {
                        $std_fee += (float)$crit_amt_q->fetch_assoc()['standard_fee'];
                    }
                }
            }

            // Fetch Active Students in this Class for the selected Session Year
            $students_q = $conn->query("
                SELECT s.* 
                FROM student_enrollments se 
                JOIN students s ON se.student_id = s.id 
                WHERE se.class_id = $class_id AND se.session_year = '$session_year' AND se.status = 'Active' AND s.status = 'Active'
            ");
            while ($s = $students_q->fetch_assoc()) {
                $sid = $s['id'];
                
                // Calculate individual fee items for this student
                $student_total_std = 0;
                $student_admission_fee = 0;
                $concession_base = 0; // Only fee items with apply_concession enabled
                $details = [];

                foreach ($fee_items as $item) {
                    $amt = 0;
                    if ($item['apply_type'] === 'Same') {
                        $amt = (float)$item['amount'];
                    } else {
                        $crit_id = (int)($item['criteria_id'] ?? 0);
                        $crit_amt_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $crit_id AND class_id = $class_id");
                        if ($crit_amt_q && $crit_amt_q->num_rows > 0) {
                            $amt = (float)$crit_amt_q->fetch_assoc()['standard_fee'];
                        }
                    }
                    
                    if (stripos($item['title'], 'Admission') !== false) {
                        $student_admission_fee += $amt;
                        // Include admission fee in concession base if flagged
                        if (!empty($item['apply_concession']) && $item['apply_concession'] != '0') {
                            $concession_base += $amt;
                        }
                    } else {
                        $student_total_std += $amt;
                        // Track concession-eligible amount
                        if (!empty($item['apply_concession']) && $item['apply_concession'] != '0') {
                            $concession_base += $amt;
                        }
                    }
                    $details[] = ['title' => $item['title'], 'amount' => $amt];
                }

                // Apply batch advance fees if selected
                if (!empty($batch_advance_months)) {
                    $tuition_item_amt = 0;
                    foreach ($fee_items as $item) {
                        if (stripos($item['title'], 'Tuition') !== false) {
                            if ($item['apply_type'] === 'Same') {
                                $tuition_item_amt += (float)$item['amount'];
                            } else {
                                $crit_id = (int)($item['criteria_id'] ?? 0);
                                $crit_amt_q = $conn->query("SELECT standard_fee FROM fee_criteria_details WHERE criteria_id = $crit_id AND class_id = $class_id");
                                if ($crit_amt_q && $crit_amt_q->num_rows > 0) {
                                    $tuition_item_amt += (float)$crit_amt_q->fetch_assoc()['standard_fee'];
                                }
                            }
                        }
                    }
                    if ($tuition_item_amt <= 0) $tuition_item_amt = $student_total_std;

                    foreach ($batch_advance_months as $bam) {
                        $month_display = str_replace('-', ' ', $bam);
                        $details[] = [
                            'title' => "Tuition Fee (Advance: $month_display)",
                            'amount' => $tuition_item_amt
                        ];
                        $student_total_std += $tuition_item_amt;
                        
                        // Insert record to advance_fees as 'Applied'
                        $month_parts = explode('-', $bam);
                        $m_name = $month_parts[0];
                        $y_num = (int)$month_parts[1];
                        $notes_escaped = $conn->real_escape_string("Applied in Batch Invoice #$invoice_id");
                        $conn->query("INSERT INTO advance_fees (student_id, class_id, invoice_id, advance_month, advance_year, amount, status, applied_at, notes) 
                                      VALUES ($sid, $class_id, $invoice_id, '$m_name', $y_num, $tuition_item_amt, 'Applied', NOW(), '$notes_escaped')");
                    }
                }

                // Apply individual pending advance fees from advance_fees table
                $s_adv_q = $conn->query("SELECT * FROM advance_fees WHERE student_id = $sid AND status = 'Pending'");
                if ($s_adv_q && $s_adv_q->num_rows > 0) {
                    while ($adv_row = $s_adv_q->fetch_assoc()) {
                        $adv_id = $adv_row['id'];
                        $adv_amt = (float)$adv_row['amount'];
                        $adv_m = $adv_row['advance_month'];
                        $adv_y = $adv_row['advance_year'];
                        
                        $details[] = [
                            'title' => "Tuition Fee (Advance: $adv_m $adv_y)",
                            'amount' => $adv_amt
                        ];
                        $student_total_std += $adv_amt;
                        
                        // Update status to Applied
                        $conn->query("UPDATE advance_fees SET status = 'Applied', invoice_id = $invoice_id, applied_at = NOW() WHERE id = $adv_id");
                    }
                }
                
                $fee_details_json = $conn->real_escape_string(json_encode($details));

                // Calculate individual discount
                // Use concession_base (only items with apply_concession checked)
                // Fallback to full amount if no items have the flag (backward compatibility)
                $disc_base = $concession_base > 0 ? $concession_base : ($student_total_std + $student_admission_fee);
                $s_disc = 0;
                if ($s['discount_type'] === 'Percentage') {
                    $s_disc = ($disc_base * (float)$s['discount_amount'] / 100);
                } else {
                    $s_disc = (float)$s['discount_amount'];
                }

                // AUTO-CALCULATE previous pending: sum of all unpaid balances from prior invoices
                $prev_q = $conn->query("
                    SELECT COALESCE(SUM(
                        (tuition_fee + admission_fee + COALESCE(prev_pending,0) + COALESCE(fine_amount,0))
                        - COALESCE(discount_amount,0)
                        - COALESCE(paid_amount,0)
                    ), 0) as total_pending
                    FROM student_monthly_fees
                    WHERE student_id = $sid
                      AND invoice_id != $invoice_id
                      AND status IN ('Due', 'Partial')
                ");
                $prev_pending = $prev_q ? max(0, (float)$prev_q->fetch_assoc()['total_pending']) : 0;
                $total_prev_pending += $prev_pending; // Accumulate for invoice summary

                if (!$conn->query("INSERT INTO student_monthly_fees (invoice_id, student_id, class_id, month, fee_details, tuition_fee, admission_fee, prev_pending, discount_amount) 
                               VALUES ($invoice_id, $sid, $class_id, '$month_str', '$fee_details_json', $student_total_std, $student_admission_fee, $prev_pending, $s_disc)")) {
                    die("Error creating student fee record: " . $conn->error);
                }
            }
        }

        // Update invoice summary with auto-calculated prev_pending total
        $conn->query("UPDATE fee_invoices SET prev_pending = $total_prev_pending WHERE id = $invoice_id");

        header("Location: " . BASE_URL . "modules/fees/monthly_fee_invoices.php?msg=generated");
    } else {
        die("Error saving invoice: " . $conn->error);
    }
} else {
    header("Location: " . BASE_URL . "modules/fees/monthly_fee_invoices.php");
}




