<?php
// API: Get student info (for fee collection AJAX autocomplete)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';


header('Content-Type: application/json');


if ($action === 'student_info') {
    $sid = (int)($_GET['id'] ?? 0);
    if ($sid > 0) {
        $session_year = $conn->real_escape_string($conn->query("SELECT setting_value FROM settings WHERE setting_key='session_year' LIMIT 1")->fetch_assoc()['setting_value'] ?? '2025-2026');
        $r = $conn->query("
            SELECT s.*, se.roll_no as enrollment_roll_no, se.class_id as enrollment_class_id, c.name as class_name, c.section, s.discount_amount, s.discount_type 
            FROM students s 
            LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.session_year = '$session_year' AND se.status = 'Active' 
            LEFT JOIN classes c ON COALESCE(se.class_id, s.class_id) = c.id 
            WHERE s.id=$sid LIMIT 1
        ");
        if ($r && $r->num_rows) {
            $data = $r->fetch_assoc();
            if (isset($data['enrollment_class_id']) && $data['enrollment_class_id'] !== null) {
                $data['class_id'] = $data['enrollment_class_id'];
            }
            if (isset($data['enrollment_roll_no']) && $data['enrollment_roll_no'] !== null) {
                $data['roll_no'] = $data['enrollment_roll_no'];
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
    exit;
}

if ($action === 'fee_structure') {
    $class_id = (int)($_GET['class_id'] ?? 0);
    $session_year = $conn->real_escape_string($conn->query("SELECT setting_value FROM settings WHERE setting_key='session_year' LIMIT 1")->fetch_assoc()['setting_value'] ?? '2025-2026');
    if ($class_id > 0) {
        $r = $conn->query("SELECT fs.*, fh.name as fee_head_name, fh.type FROM fee_structures fs JOIN fee_heads fh ON fs.fee_head_id=fh.id WHERE fs.class_id=$class_id AND fs.session_year='$session_year' ORDER BY fh.type,fh.name");
        $items = [];
        if ($r) while($row=$r->fetch_assoc()) $items[]=$row;
        echo json_encode(['success' => true, 'data' => $items]);
    } else {
        echo json_encode(['success' => true, 'data' => []]);
    }
    exit;
}

if ($action === 'search_students') {
    $q = $conn->real_escape_string(trim($_GET['q'] ?? ''));
    if (strlen($q) >= 2) {
        $session_year = $conn->real_escape_string($conn->query("SELECT setting_value FROM settings WHERE setting_key='session_year' LIMIT 1")->fetch_assoc()['setting_value'] ?? '2025-2026');
        $r = $conn->query("
            SELECT s.id, s.name, s.admission_no, c.name as class_name, c.section 
            FROM student_enrollments se 
            JOIN students s ON se.student_id = s.id 
            LEFT JOIN classes c ON se.class_id = c.id 
            WHERE se.session_year = '$session_year' AND se.status = 'Active' AND s.status = 'Active' AND (s.name LIKE '%$q%' OR s.admission_no LIKE '%$q%') 
            LIMIT 10
        ");
        $results = [];
        if ($r) while($row=$r->fetch_assoc()) $results[]=$row;
        echo json_encode(['success' => true, 'data' => $results]);
    } else {
        echo json_encode(['success' => true, 'data' => []]);
    }
    exit;
}

if ($action === 'fee_history') {
    $mid = (int)($_GET['monthly_fee_id'] ?? 0);
    if ($mid > 0) {
        $r = $conn->query("SELECT * FROM fee_payment_history WHERE monthly_fee_id=$mid ORDER BY created_at DESC");
        $items = [];
        if ($r) while($row=$r->fetch_assoc()) $items[]=$row;
        echo json_encode(['success' => true, 'data' => $items]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'delete_fee_history') {
    $hid = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($hid > 0) {
        $hq = $conn->query("SELECT * FROM fee_payment_history WHERE id = $hid LIMIT 1");
        if ($hq && $hq->num_rows > 0) {
            $h = $hq->fetch_assoc();
            $mid = (int)$h['monthly_fee_id'];
            
            // Delete history entry
            $conn->query("DELETE FROM fee_payment_history WHERE id = $hid");
            
            // Recalculate totals for student_monthly_fees
            $tot_q = $conn->query("SELECT SUM(amount_paid) as total_paid FROM fee_payment_history WHERE monthly_fee_id = $mid");
            $new_paid = 0;
            if ($tot_q && $r = $tot_q->fetch_assoc()) {
                $new_paid = (float)($r['total_paid'] ?? 0);
            }
            
            // Fetch total fee for this monthly invoice
            $mf_q = $conn->query("SELECT * FROM student_monthly_fees WHERE id = $mid LIMIT 1");
            if ($mf_q && $mf = $mf_q->fetch_assoc()) {
                $totalFee = (float)$mf['tuition_fee'] + (float)$mf['prev_pending'] + (float)($mf['admission_fee'] ?? 0) - (float)$mf['discount_amount'];
                $new_status = 'Unpaid';
                if ($new_paid >= $totalFee && $totalFee > 0) {
                    $new_status = 'Paid';
                } elseif ($new_paid > 0) {
                    $new_status = 'Partially Paid';
                }
                
                $conn->query("UPDATE student_monthly_fees SET paid_amount = $new_paid, status = '$new_status' WHERE id = $mid");

                $invoice_id = (int)$mf['invoice_id'];
                if ($invoice_id > 0) {
                    $conn->query("
                        UPDATE fee_invoices fi
                        SET fi.received_fee = (
                            SELECT COALESCE(SUM(smf.paid_amount), 0)
                            FROM student_monthly_fees smf
                            WHERE smf.invoice_id = fi.id
                        ),
                        fi.waived_fee = (
                            SELECT COALESCE(SUM(smf.discount_amount), 0)
                            FROM student_monthly_fees smf
                            WHERE smf.invoice_id = fi.id
                        )
                        WHERE fi.id = $invoice_id
                    ");
                }
            }
            
            echo json_encode(['success' => true, 'monthly_fee_id' => $mid, 'new_paid' => $new_paid]);
        } else {
            echo json_encode(['success' => false, 'message' => 'History record not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
    exit;
}

if ($action === 'quick_fee_data') {
    $sid = (int)($_GET['student_id'] ?? 0);
    if ($sid > 0) {
        $session_year = $conn->real_escape_string($conn->query("SELECT setting_value FROM settings WHERE setting_key='session_year' LIMIT 1")->fetch_assoc()['setting_value'] ?? '2025-2026');

        // Fetch most recent unpaid/partial monthly fee (filtered by active session)
        $due_q = $conn->query("SELECT smf.* FROM student_monthly_fees smf JOIN fee_invoices fi ON smf.invoice_id = fi.id WHERE smf.student_id=$sid AND smf.status != 'Paid' AND fi.session_year = '$session_year' ORDER BY smf.created_at DESC LIMIT 1");
        $due = $due_q ? $due_q->fetch_assoc() : null;
        
        // Fetch payment history (filtered by active session)
        $hist_q = $conn->query("SELECT h.*, sf.month FROM fee_payment_history h 
                                JOIN student_monthly_fees sf ON h.monthly_fee_id = sf.id 
                                JOIN fee_invoices fi ON sf.invoice_id = fi.id
                                WHERE sf.student_id = $sid AND fi.session_year = '$session_year' ORDER BY h.payment_date DESC LIMIT 10");
        $history = [];
        if ($hist_q) while($row = $hist_q->fetch_assoc()) $history[] = $row;
        
        echo json_encode(['success' => true, 'due' => $due, 'history' => $history]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'student_ledger') {
    $sid = (int)($_GET['student_id'] ?? 0);
    if ($sid > 0) {
        $session_year = $conn->real_escape_string($conn->query("SELECT setting_value FROM settings WHERE setting_key='session_year' LIMIT 1")->fetch_assoc()['setting_value'] ?? '2025-2026');
        $q = $conn->query("SELECT smf.* FROM student_monthly_fees smf JOIN fee_invoices fi ON smf.invoice_id = fi.id WHERE smf.student_id = $sid AND fi.session_year = '$session_year' ORDER BY smf.created_at DESC");
        $data = [];
        if($q) while($row = $q->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'student_info') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $q = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s 
                           JOIN classes c ON s.class_id = c.id WHERE s.id = $id");
        $data = $q ? $q->fetch_assoc() : null;
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// render_template and save_template are handled above

if ($action === 'get_next_roll_no') {
    $class_id = (int)($_GET['class_id'] ?? 0);
    if ($class_id > 0) {
        $session_year = $conn->real_escape_string($conn->query("SELECT setting_value FROM settings WHERE setting_key='session_year' LIMIT 1")->fetch_assoc()['setting_value'] ?? '2025-2026');
        $r = $conn->query("SELECT roll_no FROM student_enrollments WHERE class_id = $class_id AND session_year = '$session_year' AND roll_no != '' ORDER BY CAST(roll_no AS UNSIGNED)");
        $taken = [];
        $max = 0;
        if ($r) {
            while($row = $r->fetch_assoc()) {
                $taken[] = $row['roll_no'];
                if (is_numeric($row['roll_no']) && $row['roll_no'] > $max) $max = (int)$row['roll_no'];
            }
        }
        echo json_encode(['success' => true, 'next_roll' => $max + 1, 'taken' => $taken]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'get_bulk_students') {
    $session = $conn->real_escape_string(trim($_GET['session_year'] ?? ''));
    $class_id = (int)($_GET['class_id'] ?? 0);
    $shift = $conn->real_escape_string(trim($_GET['shift'] ?? ''));
    $no_fallback = (int)($_GET['no_fallback'] ?? 0);
    
    if ($class_id > 0) {
        $where = "se.class_id = $class_id AND se.status = 'Active' AND s.status = 'Active'";
        if ($session) {
            $where .= " AND se.session_year = '$session'";
        }
        if ($shift && $shift !== 'All') {
            $where .= " AND (se.shift = '$shift' OR se.shift IS NULL OR se.shift = '')";
        }
        
        $r = $conn->query("
            SELECT s.id, s.name, s.admission_no, s.father_name, se.roll_no, se.shift, c.name as class_name, c.section
            FROM student_enrollments se
            JOIN students s ON se.student_id = s.id
            JOIN classes c ON se.class_id = c.id
            WHERE $where
            ORDER BY (se.roll_no+0), s.name
        ");
        
        $students = [];
        if ($r && $r->num_rows > 0) {
            while ($row = $r->fetch_assoc()) {
                $students[] = $row;
            }
        } elseif (!$no_fallback) {
            // Fallback to students table ONLY if requested session matches the current active session
            $active_session = get_setting($conn, 'session_year', '2025-2026');
            if ($session === $active_session) {
                $fallback_q = $conn->query("
                    SELECT s.id, s.name, s.admission_no, s.father_name, s.roll_no, 'Morning' as shift, c.name as class_name, c.section
                    FROM students s
                    JOIN classes c ON s.class_id = c.id
                    WHERE s.class_id = $class_id AND s.status = 'Active'
                    ORDER BY (s.roll_no+0), s.name
                ");
                if ($fallback_q) {
                    while ($row = $fallback_q->fetch_assoc()) {
                        $students[] = $row;
                    }
                }
            }
        }
        echo json_encode(['success' => true, 'students' => $students, 'count' => count($students)]);
    } else {
        echo json_encode(['success' => true, 'students' => [], 'count' => 0]);
    }
    exit;
}

if ($action === 'get_class_sections') {
    $class_name = $conn->real_escape_string(trim($_GET['class_name'] ?? ''));
    if ($class_name) {
        $r = $conn->query("SELECT id, name, section FROM classes WHERE name = '$class_name'");
    } else {
        $r = $conn->query("SELECT id, name, section FROM classes");
    }
    $classes = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $classes[] = $row;
        }
    }
    sort_classes_naturally($classes);
    echo json_encode(['success' => true, 'classes' => $classes]);
    exit;
}

if ($action === 'bulk_enroll') {
    $source_session = $conn->real_escape_string(trim($_POST['source_session'] ?? ''));
    $target_session = $conn->real_escape_string(trim($_POST['target_session'] ?? ''));
    $source_class_id = (int)($_POST['source_class_id'] ?? 0);
    $target_class_id = (int)($_POST['target_class_id'] ?? 0);
    $discharge_date = $conn->real_escape_string(trim($_POST['discharge_date'] ?? date('Y-m-d')));
    $enrollment_date = $conn->real_escape_string(trim($_POST['enrollment_date'] ?? date('Y-m-d')));
    $target_shift = $conn->real_escape_string(trim($_POST['target_shift'] ?? 'Morning'));
    $selected_students = $_POST['selected_students'] ?? [];
    
    if (is_string($selected_students)) {
        $selected_students = json_decode($selected_students, true) ?: explode(',', $selected_students);
    }
    $selected_students = array_filter(array_map('intval', (array)$selected_students));
    
    if (empty($selected_students)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one student.']);
        exit;
    }
    if (!$target_class_id) {
        echo json_encode(['success' => false, 'message' => 'Please select destination (Enroll In) class.']);
        exit;
    }
    if (!$target_session) {
        echo json_encode(['success' => false, 'message' => 'Please select destination (Enroll In) session.']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        $count = 0;
        foreach ($selected_students as $student_id) {
            if ($student_id <= 0) continue;
            
            // 1. Get current student details
            $st_q = $conn->query("
                SELECT se.roll_no, se.class_id 
                FROM student_enrollments se 
                WHERE se.student_id = $student_id AND se.session_year = '$source_session' AND se.status = 'Active'
                LIMIT 1
            ");
            $old_roll_no = '';
            $old_class_id = $source_class_id;
            if ($st_q && $st_q->num_rows > 0) {
                $st_data = $st_q->fetch_assoc();
                $old_roll_no = $st_data['roll_no'];
                $old_class_id = $st_data['class_id'];
            } else {
                $st_q2 = $conn->query("SELECT roll_no, class_id FROM students WHERE id = $student_id LIMIT 1");
                if ($st_q2 && $st_q2->num_rows > 0) {
                    $st_data2 = $st_q2->fetch_assoc();
                    $old_roll_no = $st_data2['roll_no'] ?? '';
                    $old_class_id = (int)$st_data2['class_id'];
                }
            }
            
            // 2. Allocate roll number in target class & session
            $taken_q = $conn->query("
                SELECT id FROM student_enrollments 
                WHERE class_id = $target_class_id AND session_year = '$target_session' AND roll_no = '$old_roll_no' AND student_id != $student_id
            ");
            if ($old_roll_no && (!$taken_q || $taken_q->num_rows == 0)) {
                $new_roll_no = $old_roll_no;
            } else {
                $roll_res = $conn->query("
                    SELECT roll_no FROM student_enrollments 
                    WHERE class_id = $target_class_id AND session_year = '$target_session' AND roll_no != '' 
                    ORDER BY CAST(roll_no AS UNSIGNED) DESC LIMIT 1
                ");
                $new_roll_no = 1;
                if ($roll_res && $roll_res->num_rows > 0) {
                    $new_roll_no = ((int)$roll_res->fetch_assoc()['roll_no']) + 1;
                }
            }
            
            // 3. Mark old active enrollment record as Promoted / Discharged
            if ($old_class_id) {
                $conn->query("
                    UPDATE student_enrollments 
                    SET status = 'Promoted', discharge_date = '$discharge_date' 
                    WHERE student_id = $student_id AND class_id = $old_class_id AND status = 'Active'
                ");
            }
            
            // 4. Update main students table
            $conn->query("UPDATE students SET class_id = $target_class_id, roll_no = '$new_roll_no' WHERE id = $student_id");
            
            // 5. Insert or update target enrollment record
            $check_q = $conn->query("
                SELECT id FROM student_enrollments 
                WHERE student_id = $student_id AND session_year = '$target_session'
            ");
            if ($check_q && $check_q->num_rows > 0) {
                $exist_id = (int)$check_q->fetch_assoc()['id'];
                $conn->query("
                    UPDATE student_enrollments 
                    SET class_id = $target_class_id, roll_no = '$new_roll_no', shift = '$target_shift', 
                        enrollment_date = '$enrollment_date', discharge_date = NULL, status = 'Active' 
                    WHERE id = $exist_id
                ");
            } else {
                $conn->query("
                    INSERT INTO student_enrollments (student_id, class_id, session_year, roll_no, shift, enrollment_date, status) 
                    VALUES ($student_id, $target_class_id, '$target_session', '$new_roll_no', '$target_shift', '$enrollment_date', 'Active')
                ");
            }
            
            $count++;
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'count' => $count,
            'message' => "Successfully promoted/enrolled $count student" . ($count > 1 ? 's' : '') . " to the destination class."
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error during promotion: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'lookup_family_id') {
    $phone = $conn->real_escape_string(trim($_GET['father_phone'] ?? ''));
    if (strlen($phone) >= 4) {
        // Check if any existing student shares this father_phone
        $fam_check = $conn->query("SELECT family_id, name, father_name FROM students WHERE father_phone = '$phone' AND family_id IS NOT NULL AND family_id != '' LIMIT 1");
        if ($fam_check && $fam_check->num_rows > 0) {
            $row = $fam_check->fetch_assoc();
            echo json_encode(['success' => true, 'family_id' => $row['family_id'], 'is_existing' => true, 'sibling_name' => $row['name'], 'father_name' => $row['father_name']]);
        } else {
            // Generate next family_id
            $max_fam = $conn->query("SELECT family_id FROM students WHERE family_id LIKE 'FAM-%' ORDER BY CAST(SUBSTRING(family_id, 5) AS UNSIGNED) DESC LIMIT 1");
            $num = 1;
            if ($max_fam && $max_fam->num_rows > 0) {
                $last = $max_fam->fetch_assoc()['family_id'];
                $num = (int)substr($last, 4) + 1;
            }
            $new_id = 'FAM-' . str_pad($num, 4, '0', STR_PAD_LEFT);
            echo json_encode(['success' => true, 'family_id' => $new_id, 'is_existing' => false]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Phone too short']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);



