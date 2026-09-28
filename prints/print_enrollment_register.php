<?php
/**
 * SIAX SMSS - Student Enrollment Register (Printable)
 * Professional formal document for student enrollment records.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid Student ID.");

$q = $conn->query("SELECT s.*, c.name as class_name, c.section, 
                    ac.name as admission_class_name,
                    p.name as principal_name
                   FROM students s 
                   LEFT JOIN classes c ON s.class_id = c.id 
                   LEFT JOIN classes ac ON s.admission_class_id = ac.id
                   LIMIT 1");

$student = $q ? $q->fetch_assoc() : null;
if (!$student) die("Student not found.");

$settings = all_settings($conn);
$school_name = $settings['school_name'] ?? 'SIAX SCHOOL';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Register - <?= htmlspecialchars($student['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #1e293b; --border-color: #334155; }
        body { font-family: 'Times New Roman', serif; background: #f1f5f9; margin: 0; padding: 40px; color: #1e293b; }
        .no-print-bar { background: #fff; padding: 15px 30px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; max-width: 1000px; margin-left: auto; margin-right: auto; }
        
        .register-page { background: #fff; width: 1000px; margin: 0 auto; padding: 60px; border: 1px solid #cbd5e1; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative; }
        .header { text-align: center; border-bottom: 4px double var(--primary-color); padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { font-size: 28px; margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .header p { font-size: 14px; margin: 5px 0; color: #64748b; }
        
        .title-box { text-align: center; margin-bottom: 40px; }
        .title-box h2 { display: inline-block; border: 2px solid var(--primary-color); padding: 10px 40px; text-transform: uppercase; letter-spacing: 4px; font-size: 22px; }
        
        .register-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .register-table th, .register-table td { border: 1px solid var(--border-color); padding: 12px 15px; text-align: left; vertical-align: top; }
        .register-table th { background: #f8fafc; font-size: 13px; text-transform: uppercase; width: 30%; color: #475569; }
        .register-table td { font-size: 16px; font-weight: 600; }
        
        .section-header { background: #1e293b; color: #fff; padding: 8px 15px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin-top: 20px; }
        
        .footer-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 40px; margin-top: 80px; text-align: center; }
        .sign-box { border-top: 2px solid var(--primary-color); padding-top: 10px; font-size: 14px; font-weight: 700; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .no-print-bar { display: none; }
            .register-page { box-shadow: none; border: none; width: 100%; padding: 20px; }
            .register-table th { background: #eee !important; -webkit-print-color-adjust: exact; }
        }
        
        .btn { padding: 10px 25px; border-radius: 6px; cursor: pointer; text-decoration: none; font-weight: 600; font-family: sans-serif; border: none; }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn-secondary { background: #64748b; color: #fff; }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div>
        <h3 style="margin:0; font-family:sans-serif;">Enrollment Register Preview</h3>
        <p style="margin:0; font-size:12px; color:#64748b; font-family:sans-serif;">Formal admission record for administrative filing</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Print Register</button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
</div>

<div class="register-page">
    <div class="header">
        <h1><?= htmlspecialchars($school_name) ?></h1>
        <p><?= htmlspecialchars($settings['school_address'] ?? '') ?></p>
        <p>Phone: <?= htmlspecialchars($settings['school_phone'] ?? '') ?> | Email: <?= htmlspecialchars($settings['school_email'] ?? '') ?></p>
    </div>

    <div class="title-box">
        <h2>Admission &amp; Withdrawal Register</h2>
    </div>

    <table class="register-table">
        <tr>
            <th>Admission Number</th>
            <td colspan="3"><?= htmlspecialchars($student['admission_no']) ?></td>
        </tr>
        <tr>
            <th>Full Name of Student</th>
            <td colspan="3"><?= htmlspecialchars($student['name']) ?></td>
        </tr>
        <tr>
            <th>Father's Name</th>
            <td colspan="3"><?= htmlspecialchars($student['father_name'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Father's Occupation</th>
            <td colspan="3"><?= htmlspecialchars($student['father_occupation'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Date of Birth (In Figures)</th>
            <td><?= $student['dob'] ? date('d-m-Y', strtotime($student['dob'])) : '-' ?></td>
            <th>Date of Birth (In Words)</th>
            <td><?= $student['dob'] ? numberToWords(date('d', strtotime($student['dob']))) . ' ' . date('F', strtotime($student['dob'])) . ' ' . numberToWords(date('Y', strtotime($student['dob']))) : '-' ?></td>
        </tr>
        <tr>
            <th>Religion / Nationality</th>
            <td><?= htmlspecialchars($student['religion'] ?? '-') ?> / <?= htmlspecialchars($student['nationality'] ?? '-') ?></td>
            <th>Gender</th>
            <td><?= htmlspecialchars($student['gender'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Date of Admission</th>
            <td><?= $student['admission_date'] ? date('d-m-Y', strtotime($student['admission_date'])) : '-' ?></td>
            <th>Class Admitted In</th>
            <td><?= htmlspecialchars($student['admission_class_name'] ?? ($student['class_name'] ?? '-')) ?></td>
        </tr>
        <tr>
            <th>Present Address</th>
            <td colspan="3"><?= nl2br(htmlspecialchars($student['address'] ?? '-')) ?></td>
        </tr>
        <tr>
            <th>Permanent Address</th>
            <td colspan="3"><?= nl2br(htmlspecialchars($student['permanent_address'] ?? '-')) ?></td>
        </tr>
        <tr>
            <th>Family ID / Number</th>
            <td><?= htmlspecialchars($student['family_id'] ?? '-') ?></td>
            <th>B-Form / CNIC No.</th>
            <td><?= htmlspecialchars($student['cnic'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Guardian Name</th>
            <td><?= htmlspecialchars($student['guardian_name'] ?? '-') ?></td>
            <th>Guardian Contact</th>
            <td><?= htmlspecialchars($student['guardian_contact'] ?? ($student['father_phone'] ?? '-')) ?></td>
        </tr>
    </table>

    <div class="section-header">Withdrawal Information</div>
    <table class="register-table">
        <tr>
            <th style="width:30%">Date of Leaving</th>
            <td style="height:40px"></td>
            <th style="width:20%">Reason</th>
            <td></td>
        </tr>
        <tr>
            <th>Conduct &amp; Character</th>
            <td colspan="3"></td>
        </tr>
    </table>

    <div class="footer-grid">
        <div class="sign-box">Clerk / Office Asst.</div>
        <div class="sign-box">Class Teacher</div>
        <div class="sign-box">Principal / Headmaster</div>
    </div>

    <div style="margin-top:50px; text-align:right; font-size:10px; color:#94a3b8; font-style:italic;">
        Generated by SIAC Technologies | Printed on <?= date('d M Y H:i:s') ?>
    </div>
</div>

</body>
</html>
