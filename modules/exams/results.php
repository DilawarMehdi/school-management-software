<?php
$page_title = 'Results';
$active_page = 'results';
require_once __DIR__ . '/../../includes/header.php';

$view = $_GET['view'] ?? '';

if ($view === 'all_exams' || $view === 'progress') {
    $sel_student = (int)($_GET['student_id'] ?? 0);
    $role = current_role();
    if (in_array($role, ['student','parent'])) {
        $uid = current_uid();
        $sr = $conn->query("SELECT id FROM students WHERE user_id=$uid LIMIT 1");
        if ($sr && $sr->num_rows) $sel_student = (int)$sr->fetch_assoc()['id'];
    }

    $student = null;
    if ($sel_student) {
        $sq = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=$sel_student");
        if ($sq && $sq->num_rows) $student = $sq->fetch_assoc();
    }

    // Load all active students for dropdown selector
    $all_students_q = $conn->query("SELECT id, name, admission_no, roll_no FROM students WHERE status='Active' ORDER BY name");
    $all_students_arr = [];
    if ($all_students_q) while($s=$all_students_q->fetch_assoc()) $all_students_arr[]=$s;

    // Fetch all published exams where this student has marks
    $exams_history = [];
    if ($sel_student) {
        $eq = $conn->query("
            SELECT DISTINCT e.* 
            FROM exams e 
            JOIN marks m ON e.id = m.exam_id 
            WHERE m.student_id = $sel_student AND e.is_published = 1 
            ORDER BY e.created_at ASC, e.id ASC
        ");
        if ($eq) {
            while ($ex = $eq->fetch_assoc()) {
                $eid = $ex['id'];
                $mq = $conn->query("SELECT m.*, sub.name as sub_name FROM marks m JOIN subjects sub ON m.subject_id=sub.id WHERE m.exam_id=$eid AND m.student_id=$sel_student");
                $obtained = 0; $max = 0; $fail_cnt = 0;
                while ($m = $mq->fetch_assoc()) {
                    $obtained += (float)$m['marks_obtained'];
                    $max += (float)$m['max_marks'];
                    if (!$m['is_absent'] && (float)$m['marks_obtained'] < (float)$m['pass_marks']) {
                        $fail_cnt++;
                    }
                }
                $pct = $max > 0 ? round(($obtained / $max) * 100, 1) : 0;
                $g = get_grade($obtained, $max);
                $exams_history[] = [
                    'exam' => $ex,
                    'obtained' => $obtained,
                    'max' => $max,
                    'pct' => $pct,
                    'grade' => $g['grade'],
                    'remarks' => $g['remarks'],
                    'fail_cnt' => $fail_cnt,
                    'status' => ($fail_cnt == 0 && $max > 0) ? 'PASS' : 'FAIL'
                ];
            }
        }
    }

    // Calculate progress ratios and percentage changes
    $prev_pct = null;
    foreach ($exams_history as $idx => &$item) {
        if ($prev_pct === null) {
            $item['pct_change'] = 0;
            $item['ratio'] = 1.00;
            $item['trend'] = 'baseline';
        } else {
            $diff = round($item['pct'] - $prev_pct, 1);
            $ratio = $prev_pct > 0 ? round($item['pct'] / $prev_pct, 2) : 1.00;
            $item['pct_change'] = $diff;
            $item['ratio'] = $ratio;
            if ($diff > 0) $item['trend'] = 'up';
            elseif ($diff < 0) $item['trend'] = 'down';
            else $item['trend'] = 'flat';
        }
        $prev_pct = $item['pct'];
    }
    unset($item);

    // Calculate overall stats
    $total_exams_taken = count($exams_history);
    $first_pct = $total_exams_taken > 0 ? $exams_history[0]['pct'] : 0;
    $latest_pct = $total_exams_taken > 0 ? end($exams_history)['pct'] : 0;
    $net_growth = round($latest_pct - $first_pct, 1);
    $overall_ratio = $first_pct > 0 ? round($latest_pct / $first_pct, 2) : 1.00;
    $avg_pct = $total_exams_taken > 0 ? round(array_sum(array_column($exams_history, 'pct')) / $total_exams_taken, 1) : 0;
    
    // Overall trend status
    if ($net_growth > 2) {
        $overall_status = 'Excellent Growth (Upward Trajectory)';
        $status_bg = '#10b981';
    } elseif ($net_growth < -2) {
        $overall_status = 'Needs Attention (Downward Trajectory)';
        $status_bg = '#ef4444';
    } else {
        $overall_status = 'Consistent Performance (Steady)';
        $status_bg = '#0284c7';
    }

    $school_name = $settings['school_name'] ?? 'SIAX SMSS';
    ?>
    <style>
    .progress-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 28px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
    }
    .metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .metric-box {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px;
        text-align: center;
    }
    .metric-box .val { font-size: 1.6rem; font-weight: 800; margin-bottom: 4px; }
    .metric-box .lbl { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
    .trend-badge {
        display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;
    }
    .trend-up { background: rgba(16,185,129,0.15); color: #10b981; }
    .trend-down { background: rgba(239,68,68,0.15); color: #ef4444; }
    .trend-flat { background: rgba(2,132,199,0.15); color: #0284c7; }

    @media print {
        .no-print, .top-header, .sidebar, #showMenuBtn { display:none!important; }
        body, .main-wrapper, .page-content { padding:0!important; margin:0!important; background:#fff!important; color:#000!important; }
        .progress-card { border:1px solid #000!important; box-shadow:none!important; padding:15px!important; }
        .metric-box { border:1px solid #000!important; }
    }
    </style>

    <div class="page-header no-print">
      <div>
        <h1><i class="fa-solid fa-chart-line"></i> All-Exams Student Progress Report</h1>
        <p>Comprehensive performance tracking &amp; percentage growth ratio across all examinations</p>
      </div>
      <div style="display:flex; gap:10px;">
        <a href="results.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Single Results</a>
        <?php if($student): ?>
        <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Progress Report</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Student Selector -->
    <?php if (!in_array($role, ['student','parent'])): ?>
    <div class="search-filter-card no-print">
      <form method="GET" action="results.php">
        <input type="hidden" name="view" value="all_exams">
        <div style="display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
          <div style="flex:1; min-width:250px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
              <i class="fa-solid fa-user"></i> Select Student to View Progress Report
            </label>
            <select name="student_id" class="form-control" onchange="this.form.submit()">
              <option value="">-- Select Student --</option>
              <?php foreach($all_students_arr as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $sel_student == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['admission_no'].' — '.$s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-chart-line"></i> Generate Report</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($student): ?>
    <div class="progress-card">
      <!-- Student Info Header -->
      <div style="border-bottom: 2px solid var(--border); padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
          <h2 style="margin:0 0 4px 0; font-size: 1.4rem; font-weight: 800; color: var(--text-primary);"><?= htmlspecialchars($student['name']) ?></h2>
          <p style="margin:0; font-size: 0.85rem; color: var(--text-muted);">
            Father Name: <strong><?= htmlspecialchars($student['father_name'] ?? '-') ?></strong> &bull; 
            Reg No: <strong><?= htmlspecialchars($student['admission_no']) ?></strong> &bull; 
            Roll No: <strong><?= htmlspecialchars($student['roll_no'] ?? '-') ?></strong> &bull; 
            Class: <strong><?= htmlspecialchars(($student['class_name']??'').' '.($student['section']??'')) ?></strong>
          </p>
        </div>
        <div style="text-align: right;">
          <span style="display:inline-block; padding: 6px 16px; border-radius: 20px; font-weight: 800; font-size: 0.82rem; background:<?= $status_bg ?>; color:#fff;">
            <?= $overall_status ?>
          </span>
        </div>
      </div>

      <!-- Key Metric Counters -->
      <div class="metric-grid">
        <div class="metric-box">
          <div class="val" style="color: #6366f1;"><?= $total_exams_taken ?></div>
          <div class="lbl">Exams Recorded</div>
        </div>
        <div class="metric-box">
          <div class="val" style="color: #00b894;"><?= $avg_pct ?>%</div>
          <div class="lbl">Average Score</div>
        </div>
        <div class="metric-box">
          <div class="val" style="color: <?= $net_growth >= 0 ? '#10b981' : '#ef4444' ?>;">
            <?= $net_growth >= 0 ? '+'.$net_growth.'%' : $net_growth.'%' ?>
          </div>
          <div class="lbl">Net Growth (% Change)</div>
        </div>
        <div class="metric-box">
          <div class="val" style="color: #f59e0b;"><?= $overall_ratio ?>x</div>
          <div class="lbl">Progress Growth Factor</div>
        </div>
      </div>

      <!-- Detailed Exams History Table -->
      <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 14px; color: var(--text-primary);">
        <i class="fa-solid fa-list-check" style="color: #6366f1; margin-right: 6px;"></i> Term-by-Term Examination Performance
      </h3>

      <?php if (empty($exams_history)): ?>
        <div style="text-align: center; padding: 30px; color: var(--text-muted);">No published exam results found for this student.</div>
      <?php else: ?>
        <div class="table-wrapper mb-4">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">#</th>
                <th>Exam Name</th>
                <th style="width: 120px; text-align: center;">Session</th>
                <th style="width: 140px; text-align: center;">Obtained / Max</th>
                <th style="width: 100px; text-align: center;">Percentage</th>
                <th style="width: 80px; text-align: center;">Grade</th>
                <th style="width: 90px; text-align: center;">Status</th>
                <th style="width: 160px; text-align: center;">Progress vs Prev. Exam</th>
                <th style="width: 120px; text-align: center;">Progress Ratio</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($exams_history as $idx => $eh): ?>
              <tr>
                <td style="text-align: center; font-weight: 700;"><?= $idx + 1 ?></td>
                <td><strong><?= htmlspecialchars($eh['exam']['name']) ?></strong></td>
                <td style="text-align: center; font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($eh['exam']['session_year']) ?></td>
                <td style="text-align: center; font-weight: 700;"><?= $eh['obtained'] ?> / <?= $eh['max'] ?></td>
                <td style="text-align: center; font-weight: 800; color: #00b894; font-size: 1rem;"><?= $eh['pct'] ?>%</td>
                <td style="text-align: center; font-weight: 800;"><?= $eh['grade'] ?></td>
                <td style="text-align: center;">
                  <span class="<?= $eh['status']==='PASS'?'badge-pass':'badge-fail' ?>"><?= $eh['status'] ?></span>
                </td>
                <td style="text-align: center;">
                  <?php if ($idx == 0): ?>
                    <span class="trend-badge trend-flat">Baseline</span>
                  <?php elseif ($eh['trend'] === 'up'): ?>
                    <span class="trend-badge trend-up"><i class="fa-solid fa-arrow-trend-up"></i> +<?= $eh['pct_change'] ?>%</span>
                  <?php elseif ($eh['trend'] === 'down'): ?>
                    <span class="trend-badge trend-down"><i class="fa-solid fa-arrow-trend-down"></i> <?= $eh['pct_change'] ?>%</span>
                  <?php else: ?>
                    <span class="trend-badge trend-flat"><i class="fa-solid fa-equals"></i> 0.0%</span>
                  <?php endif; ?>
                </td>
                <td style="text-align: center; font-weight: 800; color: #6366f1;">
                  <?= $eh['ratio'] ?>x
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Visual Trend Bars -->
        <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 14px; color: var(--text-primary); margin-top: 25px;">
          <i class="fa-solid fa-chart-column" style="color: #00b894; margin-right: 6px;"></i> Visual Percentage Trend Comparison
        </h3>
        <div style="display: flex; flex-direction: column; gap: 12px; background: var(--bg-secondary); padding: 20px; border-radius: var(--radius); border: 1px solid var(--border);">
          <?php foreach ($exams_history as $eh): ?>
          <div>
            <div style="display: flex; justify-content: space-between; font-size: 0.82rem; font-weight: 700; margin-bottom: 4px;">
              <span><?= htmlspecialchars($eh['exam']['name']) ?></span>
              <span><?= $eh['pct'] ?>% (Grade <?= $eh['grade'] ?>)</span>
            </div>
            <div style="background: rgba(0,0,0,0.1); border-radius: 10px; height: 16px; overflow: hidden;">
              <div style="width: <?= min(100, max(5, $eh['pct'])) ?>%; height: 100%; background: linear-gradient(90deg, #6366f1, #00b894); border-radius: 10px; transition: width 0.5s;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Progress Summary Evaluation Box -->
        <div style="margin-top: 25px; padding: 20px; background: rgba(99,102,241,0.06); border: 1.5px dashed #6366f1; border-radius: var(--radius); display: flex; align-items: center; gap: 16px;">
          <i class="fa-solid fa-lightbulb" style="font-size: 2.2rem; color: #6366f1;"></i>
          <div>
            <h4 style="margin: 0 0 4px 0; font-size: 0.95rem; font-weight: 800; color: var(--text-primary);">Overall Performance Evaluation &amp; Progress Growth Factor</h4>
            <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5;">
              Student started with a baseline score of <strong><?= $first_pct ?>%</strong> and reached <strong><?= $latest_pct ?>%</strong> in the latest exam, yielding a net progress change of <strong><?= $net_growth >= 0 ? '+'.$net_growth.'%' : $net_growth.'%' ?></strong> (Progress Ratio: <strong><?= $overall_ratio ?>x</strong>). Average score across <?= $total_exams_taken ?> term examinations is <strong><?= $avg_pct ?>%</strong>.
            </p>
          </div>
        </div>

        <div style="margin-top: 30px; text-align: center; font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid var(--border); padding-top: 12px;">
          Generated by SIAC Technologies — SMSS Performance Analytics Engine &bull; <?= date('d M Y') ?>
        </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="progress-card no-print" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
      <i class="fa-solid fa-chart-line" style="font-size: 3rem; color: #6366f1; opacity: 0.3; margin-bottom: 15px;"></i>
      <h3>Select a Student to View Progress Report</h3>
      <p>Use the dropdown menu above or click on any student from the Results table to generate their full progress history report.</p>
    </div>
    <?php endif; ?>

    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($view === 'consolidated') {
    $sel_class = (int)($_GET['class_id'] ?? 0);
    $sel_type  = (int)($_GET['type_id'] ?? 0);

    // Load exam types & classes for filters
    $types_q = $conn->query("SELECT * FROM exam_types ORDER BY id DESC");
    $types_arr = [];
    if ($types_q) while($t=$types_q->fetch_assoc()) $types_arr[]=$t;

    // Classes (Naturally sorted)
    $classes_arr = get_all_classes($conn);


    $class_info = null;
    if ($sel_class) {
        $r = $conn->query("SELECT * FROM classes WHERE id=$sel_class LIMIT 1");
        if ($r) $class_info = $r->fetch_assoc();
    }

    $type_info = null;
    if ($sel_type) {
        $r = $conn->query("SELECT * FROM exam_types WHERE id=$sel_type LIMIT 1");
        if ($r) $type_info = $r->fetch_assoc();
    }

    $subjects = [];
    if ($sel_class) {
        $sq = $conn->query("SELECT id, subject FROM class_subjects WHERE class_id=$sel_class GROUP BY subject ORDER BY subject");
        if ($sq) {
            while ($row = $sq->fetch_assoc()) {
                $subj_esc = $conn->real_escape_string($row['subject']);
                $row['total_marks'] = 100;
                $row['pass_marks'] = 40;
                $mk_conf = $conn->query("SELECT max_marks, pass_marks FROM marks WHERE exam_type_id=$sel_type AND subject='$subj_esc' AND student_id IN (SELECT student_id FROM student_enrollments WHERE class_id=$sel_class) LIMIT 1");
                if ($mk_conf && $mk_conf->num_rows > 0) {
                    $c = $mk_conf->fetch_assoc();
                    $row['total_marks'] = (float)$c['max_marks'];
                    $row['pass_marks'] = (float)$c['pass_marks'];
                } else {
                    $sub_tb = $conn->query("SELECT max_marks, pass_marks FROM subjects WHERE class_id=$sel_class AND name='$subj_esc' LIMIT 1");
                    if ($sub_tb && $sub_tb->num_rows > 0) {
                        $c = $sub_tb->fetch_assoc();
                        $row['total_marks'] = (float)$c['max_marks'];
                        $row['pass_marks'] = (float)$c['pass_marks'];
                    }
                }
                $subjects[] = $row;
            }
        }
    }

    $students = [];
    if ($sel_class) {
        $active_session = $settings['session_year'] ?? '2025-2026';
        $sq = $conn->query("
            SELECT s.id, s.name, s.admission_no, se.roll_no, s.father_name, s.dob, se.roll_no as enrollment_roll_no 
            FROM student_enrollments se 
            JOIN students s ON se.student_id = s.id 
            WHERE se.class_id=$sel_class AND se.session_year='$active_session' AND se.status='Active' AND s.status='Active' 
            ORDER BY (se.roll_no+0), s.name
        ");
        if ($sq) while ($row = $sq->fetch_assoc()) $students[] = $row;
    }

    $marks_lookup = [];
    if ($sel_class && $sel_type) {
        $active_session = $settings['session_year'] ?? '2025-2026';
        $mq = $conn->query("
            SELECT m.* 
            FROM marks m 
            JOIN student_enrollments se ON m.student_id = se.student_id AND se.session_year = '$active_session' 
            WHERE m.exam_type_id=$sel_type AND se.class_id=$sel_class AND se.status='Active'
        ");
        if ($mq) {
            while ($m = $mq->fetch_assoc()) {
                $subj_k = strtolower(trim($m['subject']));
                $marks_lookup[$m['student_id']][$subj_k] = $m;
            }
        }
    }
    
    // Get default grading policy
    $def_policy = [];
    $def_max_fail = null;
    $pol_q = $conn->query("SELECT id, max_fail_subjects FROM grading_policies ORDER BY is_default DESC, id ASC LIMIT 1");
    if ($pol_q && $pol_q->num_rows > 0) {
        $p_row = $pol_q->fetch_assoc();
        $pid = $p_row['id'];
        $def_max_fail = $p_row['max_fail_subjects'];
        $det_q = $conn->query("SELECT * FROM grading_policy_details WHERE policy_id=$pid ORDER BY min_percent DESC");
        if ($det_q) while ($d = $det_q->fetch_assoc()) $def_policy[] = $d;
    }

    // Function to get remark
    if (!function_exists('get_policy_remark')) {
        function get_policy_remark($pct, $policy_details) {
            foreach ($policy_details as $d) {
                if ($pct >= (float)$d['min_percent'] && $pct <= (float)$d['max_percent']) {
                    return $d['remarks'];
                }
            }
            return !empty($policy_details) ? end($policy_details)['remarks'] : '';
        }
    }

    // Pre-calculate to assign positions
    $rank_array = [];
    foreach ($students as &$st) {
        $st_obt = 0; $st_max = 0; $fail_count = 0;
        foreach ($subjects as $sub) {
            $subj_k = strtolower(trim($sub['subject']));
            $m = $marks_lookup[$st['id']][$subj_k] ?? null;
            $st_max += $sub['total_marks'];
            if ($m && !$m['is_absent'] && !$m['not_participating']) {
                $st_obt += (float)$m['marks_obtained'];
                if ((float)$m['marks_obtained'] < (float)$sub['pass_marks']) {
                    $fail_count++;
                }
            } else if ($m && ($m['is_absent'] || $m['not_participating'])) {
                $fail_count++;
            }
        }
        $st['total_obtained'] = $st_obt;
        $st['total_max'] = $st_max;
        $st['pct'] = $st_max > 0 ? round(($st_obt / $st_max) * 100, 1) : 0;
        $st['remark'] = get_policy_remark($st['pct'], $def_policy);
        
        $st['overall_fail'] = false;
        if ($def_max_fail !== null && $fail_count >= (int)$def_max_fail) {
            $st['overall_fail'] = true;
        }

        $rank_array[] = ['id' => $st['id'], 'pct' => $st['pct'], 'overall_fail' => $st['overall_fail']];
    }
    unset($st);

    // Sort to find ranks
    usort($rank_array, function($a, $b) {
        if ($a['overall_fail'] !== $b['overall_fail']) {
            return $a['overall_fail'] ? 1 : -1; // Failed goes to bottom
        }
        return $b['pct'] <=> $a['pct']; // Sort descending
    });

    $positions = [];
    $rank = 1; $prev_pct = -1; $actual_rank = 1;
    foreach ($rank_array as $idx => $r) {
        if ($r['overall_fail']) {
            $positions[$r['id']] = 'FAIL';
        } else {
            if ($r['pct'] != $prev_pct) {
                $rank = $actual_rank;
                $prev_pct = $r['pct'];
            }
            $positions[$r['id']] = $rank;
            $actual_rank++;
        }
    }

    foreach ($students as &$st) {
        $st['position'] = $positions[$st['id']] ?? '-';
        if ($st['position'] === 'FAIL') {
            $st['position_str'] = 'Fail';
        } elseif (is_numeric($st['position'])) {
            $p = $st['position'];
            if ($p % 100 >= 11 && $p % 100 <= 13) $suff = 'th';
            else {
                switch ($p % 10) {
                    case 1: $suff = 'st'; break;
                    case 2: $suff = 'nd'; break;
                    case 3: $suff = 'rd'; break;
                    default: $suff = 'th'; break;
                }
            }
            $st['position_str'] = $p . $suff;
        } else {
            $st['position_str'] = '-';
        }
    }
    unset($st);
    
    $school_name = $settings['school_name'] ?? 'SIAX SMSS';
    ?>
    <style>
    /* Consolidated Sheet Premium Styling */
    .consolidated-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 32px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        border: 1px solid var(--border);
        margin-bottom: 30px;
    }
    .consolidated-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .consolidated-table th {
        background: var(--bg-glass);
        padding: 12px 10px;
        font-size: .8rem;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 1px solid var(--border);
        text-align: center;
    }
    .consolidated-table td {
        padding: 10px;
        font-size: .85rem;
        color: var(--text-primary);
        border: 1px solid var(--border);
        text-align: center;
        vertical-align: middle;
    }
    .consolidated-table tr:hover td {
        background: var(--bg-glass);
    }
    .col-left {
        text-align: left !important;
    }
    .total-highlight {
        font-weight: 700;
        color: #0984e3;
    }
    .pct-highlight {
        font-weight: 800;
        color: #00b894;
    }

    /* Hidden by default on screen */
    .print-header {
        display: none;
    }
    .print-signatures {
        display: none;
    }
    .print-footer {
        display: none;
    }

    @media print {
        @page { size: landscape; }
        .no-print {
            display: none !important;
        }
        body {
            background: #fff !important;
            color: #000 !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        /* Hide dashboard sidebar and header */
        .sidebar, .top-bar, .page-header, .filter-bar, .footer {
            display: none !important;
        }
        .content-wrapper, .main-content {
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
        }
        .consolidated-card {
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .consolidated-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 15px !important;
        }
        .consolidated-table th, .consolidated-table td {
            border: 1px solid #000 !important;
            padding: 6px 4px !important;
            font-size: 10px !important;
            color: #000 !important;
            background: transparent !important;
        }
        .consolidated-table tr:hover td {
            background: transparent !important;
        }
        
        /* Show print structures */
        .print-header {
            display: flex !important;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .print-logo {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            border: 3px solid #1a5276;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg,#1a5276,#2980b9);
            color: #fff;
            font-size: 30px;
            font-weight: 800;
        }
        .print-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .print-hdr-text {
            flex: 1;
            text-align: center;
        }
        .print-school-name {
            font-size: 22px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .5px;
            line-height: 1.2;
        }
        .print-school-sub {
            font-size: 11px;
            color: #333;
            margin-top: 3px;
        }
        .print-title {
            font-size: 14px;
            font-weight: 800;
            margin-top: 6px;
            text-transform: uppercase;
            background: #111;
            color: #fff;
            display: inline-block;
            padding: 3px 16px;
            border-radius: 4px;
        }
        .print-meta {
            font-size: 12px;
            font-weight: 700;
            margin-top: 8px;
        }
        
        .print-signatures {
            display: flex !important;
            justify-content: space-between;
            margin-top: 60px;
        }
        .print-sig-col {
            text-align: center;
            min-width: 160px;
        }
        .print-sig-line {
            border-top: 1px solid #000;
            padding-top: 4px;
            margin-top: 36px;
            font-size: 11px;
            font-weight: 700;
        }
        
        .print-footer {
            display: block !important;
            text-align: center;
            margin-top: 40px;
            font-size: 10px;
            color: #555;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
        }
    }
    </style>

    <div class="page-header no-print">
      <div>
        <h1><i class="fa-solid fa-square-poll-horizontal"></i> Cumulative Result</h1>
        <p>View and print consolidated class tabulation sheet</p>
      </div>
      <?php if($sel_class && $sel_type): ?>
      <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Cumulative Sheet</button>
      <?php endif; ?>
    </div>

    <div class="filter-bar no-print">
      <form method="GET" class="filter-bar" style="margin-bottom:0">
        <input type="hidden" name="view" value="consolidated">
        
        <select name="class_id" class="form-control" required>
          <option value="">-- Select Class --</option>
          <?php foreach($classes_arr as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $sel_class==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'].' '.$c['section']) ?></option>
          <?php endforeach; ?>
        </select>
        
        <select name="type_id" class="form-control" required>
          <option value="">-- Select Exam Category --</option>
          <?php foreach($types_arr as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $sel_type==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['title']) ?></option>
          <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn btn-primary">Generate Sheet</button>
      </form>
    </div>

    <?php if ($sel_class && $sel_type): ?>
    <div class="consolidated-card">
      
      <!-- Print-only school header matching drawing -->
      <div class="print-header">
        <div class="print-logo">
          <?php $logo=$settings['school_logo']??'';
          if($logo && file_exists(UPLOAD_PATH.$logo)): ?>
          <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($logo) ?>" alt="Logo">
          <?php else: ?><?= strtoupper(substr($school_name,0,1)) ?><?php endif; ?>
        </div>
        <div class="print-hdr-text">
          <div class="print-school-name"><?= htmlspecialchars($school_name) ?></div>
          <div class="print-school-sub">
            <?php if(!empty($settings['school_phone'])): ?>Phone: <?= htmlspecialchars($settings['school_phone']) ?><?php endif; ?>
            <?php if(!empty($settings['school_website'])): ?> &nbsp;|&nbsp; Website: <?= htmlspecialchars($settings['school_website']) ?><?php endif; ?>
          </div>
          <div class="print-title">Cumulative Result Sheet</div>
          <div class="print-meta">
            <strong>Class:</strong> <?= htmlspecialchars($class_info['name']??'') ?> <?= htmlspecialchars($class_info['section']??'') ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
            <strong>Exam Category:</strong> <?= htmlspecialchars($type_info['title']??'') ?>
          </div>
        </div>
      </div>

      <div class="no-print" style="margin-bottom: 20px; font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">
        <i class="fa-solid fa-clipboard" style="color: #6c5ce7; margin-right: 8px;"></i>
        Tabulation Sheet &mdash; Class <?= htmlspecialchars($class_info['name']??'') ?> (<?= htmlspecialchars($type_info['title']??'') ?>)
      </div>

      <?php if(empty($students)): ?>
        <div style="text-align:center; padding: 40px; color: var(--text-muted);">No active students found in this class.</div>
      <?php elseif(empty($subjects)): ?>
        <div style="text-align:center; padding: 40px; color: var(--text-muted);">No exam schedules or subjects defined for this exam category in this class.</div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="consolidated-table">
            <thead>
              <tr>
                <th style="width: 80px;">Roll No.</th>
                <th style="width: 100px;">Reg #</th>
                <th class="col-left" style="width: 180px;">Name</th>
                <th class="col-left" style="width: 180px;">Father Name</th>
                <th style="width: 110px;">DOB</th>
                <?php foreach($subjects as $sub): ?>
                <th><?= htmlspecialchars($sub['subject']) ?><br><span style="font-size: 10px; font-weight: normal; text-transform: none;">(Max: <?= $sub['total_marks'] ?>)</span></th>
                <?php endforeach; ?>
                <th style="width: 90px;">Total</th>
                <th style="width: 80px;">%</th>
                <th style="width: 80px;">Position</th>
                <th class="col-left">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              foreach($students as $st): 
                $dob_formatted = (!empty($st['dob']) && $st['dob'] !== '0000-00-00') ? date('d M Y', strtotime($st['dob'])) : '-';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($st['roll_no']??'-') ?></strong></td>
                <td><?= htmlspecialchars($st['admission_no']) ?></td>
                <td class="col-left"><strong><?= htmlspecialchars($st['name']) ?></strong></td>
                <td class="col-left"><?= htmlspecialchars($st['father_name']??'-') ?></td>
                <td><?= $dob_formatted ?></td>
                
                <?php foreach($subjects as $sub): 
                  $subj_k = strtolower(trim($sub['subject']));
                  $m = $marks_lookup[$st['id']][$subj_k] ?? null;
                  
                  if ($m) {
                      if ($m['is_absent'] == 1) {
                          echo '<td style="color:#e74c3c; font-weight:700;">Absent</td>';
                      } elseif ($m['not_participating'] == 1) {
                          echo '<td style="color:#e74c3c; font-weight:700;">N/P</td>';
                      } else {
                          echo '<td>' . (float)$m['marks_obtained'] . '</td>';
                      }
                  } else {
                      echo '<td style="color:var(--text-muted); font-style:italic;">-</td>';
                  }
                endforeach; ?>
                
                <td class="total-highlight"><?= $st['total_obtained'] ?> / <?= $st['total_max'] ?></td>
                <td class="pct-highlight"><?= $st['pct'] ?>%</td>
                <td style="font-weight:800; color:#8e44ad;"><?= htmlspecialchars($st['position_str']) ?></td>
                <td style="font-weight:600; color:var(--text-secondary);"><?= htmlspecialchars($st['remark'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Print-only signature rows matching drawing -->
        <div class="print-signatures">
          <div class="print-sig-col"><div class="print-sig-line">Class Teacher</div></div>
          <div class="print-sig-col"><div class="print-sig-line">In-Charge Exams</div></div>
          <div class="print-sig-col"><div class="print-sig-line"><?= htmlspecialchars($settings['principal_name'] ?? 'Principal') ?></div></div>
        </div>
        
        <div class="print-footer">
          Generated by SIAC Technologies &mdash; Professional School Management System Software
        </div>

      <?php endif; ?>
    </div>
    <?php else: ?>
      <div class="consolidated-card no-print" style="text-align:center; padding: 60px 40px; color: var(--text-muted);">
        <i class="fa-solid fa-square-poll-horizontal" style="font-size: 3.5rem; color: rgba(108, 92, 231, 0.2); margin-bottom: 20px; display: block;"></i>
        <h3>Cumulative Result Tabulation Sheet</h3>
        <p style="margin-top: 10px; font-size: .95rem;">Please select a class and an exam category from the filter bar above to generate and print the cumulative result sheet.</p>
      </div>
    <?php endif; ?>
    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$session_year = $settings['session_year'] ?? '2025-2026';
$currency = $settings['currency'] ?? 'PKR';
$role = current_role();

// Role restriction
if (!in_array($role, ['admin','principal','teacher','accountant','student','parent'])) {
    header("Location: " . BASE_URL . "dashboard.php"); exit;
}

$sel_exam    = (int)($_GET['exam_id'] ?? 0);
$sel_class   = (int)($_GET['class_id'] ?? 0);
$sel_student = (int)($_GET['student_id'] ?? 0);
$search_term = trim($_GET['search'] ?? '');

// For student/parent: restrict to own data
if (in_array($role, ['student','parent'])) {
    $uid = current_uid();
    $sr = $conn->query("SELECT id FROM students WHERE user_id=$uid LIMIT 1");
    if ($sr && $sr->num_rows) $sel_student = (int)$sr->fetch_assoc()['id'];
    else { echo '<div class="empty-state"><div class="icon"><i class="fa-solid fa-trophy"></i></div><h3>No student profile linked</h3></div>'; require_once __DIR__.'/includes/footer.php'; exit; }
}

$exams_q = $conn->query("SELECT * FROM exams WHERE session_year='$session_year' AND is_published=1 ORDER BY created_at DESC");
$all_exams_q = ($role==='admin'||$role==='principal'||$role==='teacher') ? $conn->query("SELECT * FROM exams WHERE session_year='$session_year' ORDER BY created_at DESC") : $exams_q;
$exams_arr = [];
if ($all_exams_q) { $all_exams_q->data_seek(0); while($e=$all_exams_q->fetch_assoc()) $exams_arr[]=$e; }

// Classes (Naturally sorted)
$classes_arr = get_all_classes($conn);


// Fetch students for selected class
$students_q = $sel_class > 0 ? $conn->query("SELECT id,name,admission_no,roll_no,father_name FROM students WHERE class_id=$sel_class AND status='Active' ORDER BY roll_no+0,name") : null;
$students_arr = [];
if ($students_q) while($s=$students_q->fetch_assoc()) $students_arr[]=$s;

// Fetch ALL active students for direct select dropdown
$all_students_q = $conn->query("SELECT id, name, admission_no, roll_no FROM students WHERE status='Active' ORDER BY name");
$all_students_arr = [];
if ($all_students_q) while($s=$all_students_q->fetch_assoc()) $all_students_arr[]=$s;

// Filtered students list (by class and/or search term)
$filtered_students = [];
if (($sel_class > 0 || !empty($search_term)) && !in_array($role, ['student','parent'])) {
    $where = ["s.status='Active'"];
    if ($sel_class > 0) {
        $where[] = "s.class_id = $sel_class";
    }
    if (!empty($search_term)) {
        $st_esc = $conn->real_escape_string($search_term);
        $where[] = "(s.name LIKE '%$st_esc%' OR s.admission_no LIKE '%$st_esc%' OR s.father_name LIKE '%$st_esc%' OR s.roll_no LIKE '%$st_esc%')";
    }
    $w_sql = implode(' AND ', $where);
    $fq = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE $w_sql ORDER BY s.roll_no+0, s.name ASC");
    if ($fq) while($row = $fq->fetch_assoc()) $filtered_students[] = $row;
}

// Build result card
$result_data = null;
if ($sel_exam > 0 && $sel_student > 0) {
    // Get default grading policy's max_fail_subjects
    $def_max_fail = null;
    $pol_q = $conn->query("SELECT max_fail_subjects FROM grading_policies ORDER BY is_default DESC, id ASC LIMIT 1");
    if ($pol_q && $pol_q->num_rows > 0) {
        $def_max_fail = $pol_q->fetch_assoc()['max_fail_subjects'];
    }

    $sq = $conn->query("SELECT s.*, c.name as class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=$sel_student");
    if ($sq && $sq->num_rows) {
        $result_data = $sq->fetch_assoc();
        $eq = $conn->query("SELECT * FROM exams WHERE id=$sel_exam");
        $result_data['exam'] = $eq ? $eq->fetch_assoc() : [];
        $mq = $conn->query("SELECT m.*, sub.name as sub_name, sub.code FROM marks m JOIN subjects sub ON m.subject_id=sub.id WHERE m.exam_id=$sel_exam AND m.student_id=$sel_student ORDER BY sub.name");
        $result_data['marks'] = [];
        $total_obtained = 0; $total_max = 0; $total_pass = 0; $fail_count = 0;
        while($m=$mq->fetch_assoc()) {
            $g = get_grade($m['marks_obtained'], $m['max_marks']);
            $m['grade'] = $g['grade']; $m['remarks'] = $g['remarks'];
            $m['passed'] = !$m['is_absent'] && $m['marks_obtained'] >= $m['pass_marks'];
            if(!$m['passed']) $fail_count++;
            $total_obtained += $m['marks_obtained'];
            $total_max += $m['max_marks'];
            $result_data['marks'][] = $m;
        }
        $result_data['total_obtained'] = $total_obtained;
        $result_data['total_max'] = $total_max;
        $result_data['fail_count'] = $fail_count;
        $overall = get_grade($total_obtained, $total_max);
        $result_data['overall_grade'] = $overall['grade'];
        $result_data['overall_remarks'] = $overall['remarks'];
        $result_data['overall_pct'] = $total_max > 0 ? round(($total_obtained/$total_max)*100,1) : 0;
        
        if ($def_max_fail === null) {
            $result_data['result_status'] = 'PASS';
        } else {
            $result_data['result_status'] = $fail_count >= (int)$def_max_fail ? 'FAIL' : 'PASS';
        }

        // Calculate Class Position if enabled
        $show_position = ($settings['show_position_in_result'] ?? '1') === '1';
        $result_data['class_position'] = null;
        if ($show_position && !empty($result_data['class_id'])) {
            $cid = (int)$result_data['class_id'];
            $cq = $conn->query("
                SELECT m.student_id, SUM(m.marks_obtained) as obt, SUM(m.max_marks) as tot
                FROM marks m
                JOIN students s ON s.id = m.student_id
                WHERE m.exam_id = $sel_exam AND s.class_id = $cid
                GROUP BY m.student_id
            ");
            if ($cq && $cq->num_rows > 0) {
                $pct_map = [];
                while ($crow = $cq->fetch_assoc()) {
                    $pct_map[$crow['student_id']] = $crow['tot'] > 0 ? ($crow['obt'] / $crow['tot']) * 100 : 0;
                }
                arsort($pct_map);
                $cur_rank = 1;
                $p_pct = null;
                foreach ($pct_map as $sid => $pct_val) {
                    if ($p_pct !== null && abs($pct_val - $p_pct) < 0.001) {
                        // tied
                    } else {
                        $cur_rank = $pos_counter ?? 1;
                    }
                    $pos_counter = ($pos_counter ?? 1) + 1;
                    if ($sid == $sel_student) {
                        $mod100 = $cur_rank % 100;
                        $mod10  = $cur_rank % 10;
                        $sfx = ($mod100 >= 11 && $mod100 <= 13) ? 'th' : (($mod10 === 1) ? 'st' : (($mod10 === 2) ? 'nd' : (($mod10 === 3) ? 'rd' : 'th')));
                        $result_data['class_position'] = $cur_rank . $sfx;
                        break;
                    }
                    $p_pct = $pct_val;
                }
            }
        }
    }
}
?>
<style>
.result-card{background:#fff;color:#111;padding:32px;border-radius:8px;max-width:750px;margin:0 auto}
.result-school{text-align:center;border-bottom:2px solid #333;padding-bottom:12px;margin-bottom:16px}
.result-school h2{font-size:20px;font-weight:700;margin:0}
.result-school p{font-size:12px;color:#555;margin:3px 0}
.result-school h3{font-size:14px;font-weight:600;margin-top:8px;background:#111;color:#fff;display:inline-block;padding:3px 16px;border-radius:4px}
.result-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;font-size:13px;margin-bottom:16px}
.result-info-row{display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding:4px 0}
.result-marks-table{width:100%;border-collapse:collapse;font-size:13px;margin:12px 0}
.result-marks-table th,.result-marks-table td{border:1px solid #ccc;padding:6px 8px}
.result-marks-table th{background:#f4f4f4;font-weight:600}
.result-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:16px;text-align:center}
.result-summary-box{padding:12px;border:1px solid #ddd;border-radius:6px}
.result-summary-box .val{font-size:20px;font-weight:700}
.result-summary-box .lbl{font-size:11px;color:#666}
.badge-pass{background:#22c55e;color:#fff;padding:2px 10px;border-radius:4px;font-weight:700}
.badge-fail{background:#ef4444;color:#fff;padding:2px 10px;border-radius:4px;font-weight:700}
.search-filter-card { margin-bottom: 20px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
.search-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end; }
.students-list-card { margin-bottom: 24px; }
@media print{.no-print{display:none!important}.result-card{box-shadow:none;border:none}}
</style>

<div class="page-header">
  <div><h1><i class="fa-solid fa-trophy"></i> Results</h1><p>View and print student result cards &amp; progress reports</p></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
  <a href="<?= BASE_URL ?>modules/exams/results.php?view=all_exams<?= $sel_student ? '&student_id='.$sel_student : '' ?>" class="btn no-print" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-weight:700;"><i class="fa-solid fa-chart-line"></i> All-Exams Progress Report</a>
  <?php if($result_data): ?>
  <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Result Card</button>
  <?php endif; ?>
  <?php if($sel_exam > 0 && $sel_class > 0 && in_array($role, ['admin','principal','teacher'])): ?>
  <a href="<?= BASE_URL ?>prints/print_all_result_cards.php?class_id=<?= $sel_class ?>&exam_id=<?= $sel_exam ?>" target="_blank" class="btn no-print" style="background:#6c5ce7;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-weight:700;"><i class="fa-solid fa-print"></i> Print All Result Cards</a>
  <?php endif; ?>
  </div>
</div>

<!-- SEARCH & FILTER BAR -->
<?php if(!in_array($role,['student','parent'])): ?>
<div class="search-filter-card no-print">
  <form method="GET" action="results.php">
    <div class="search-filter-grid">
      <!-- 1. Select Exam -->
      <div>
        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
          <i class="fa-solid fa-file-pen"></i> Select Exam
        </label>
        <select name="exam_id" class="form-control">
          <option value="">-- Select Exam --</option>
          <?php foreach($exams_arr as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $sel_exam==$e['id']?'selected':'' ?>><?= htmlspecialchars($e['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- 2. Filter by Class -->
      <div>
        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
          <i class="fa-solid fa-school"></i> Filter by Class
        </label>
        <select name="class_id" class="form-control" onchange="this.form.submit()">
          <option value="">-- All Classes --</option>
          <?php foreach($classes_arr as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $sel_class==$c['id']?'selected':'' ?>>Class <?= htmlspecialchars($c['name'].' '.$c['section']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- 3. Search by Name / Reg No. -->
      <div>
        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
          <i class="fa-solid fa-magnifying-glass"></i> Search Name / Reg No.
        </label>
        <input type="text" name="search" class="form-control" placeholder="Enter Student Name or Reg No..." value="<?= htmlspecialchars($search_term) ?>">
      </div>

      <!-- 4. Direct Select Student -->
      <div>
        <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block;">
          <i class="fa-solid fa-user"></i> Direct Select Student
        </label>
        <select name="student_id" class="form-control" onchange="this.form.submit()">
          <option value="">-- Select Student --</option>
          <?php foreach($all_students_arr as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $sel_student == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['admission_no'].' — '.$s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; gap: 8px;">
        <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-search"></i> Search</button>
        <a href="results.php" class="btn btn-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </div>
  </form>
</div>

<!-- Filtered Student List Table -->
<?php if (!empty($filtered_students)): ?>
<div class="card students-list-card no-print">
  <div class="card-header" style="padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid var(--border);">
    <h3 style="font-size: 1rem; font-weight: 700;">
      <i class="fa-solid fa-users"></i> Matching Students (<?= count($filtered_students) ?>)
    </h3>
    <span class="text-muted" style="font-size: 0.78rem;">Click <i class="fa-solid fa-eye" style="color:#6366f1;"></i> for Result Card or <i class="fa-solid fa-chart-line" style="color:#10b981;"></i> for All-Exams Progress Report</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 90px; text-align: center;">Actions</th>
          <th>Reg No.</th>
          <th>Roll No.</th>
          <th>Student Name</th>
          <th>Father Name</th>
          <th>Class</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($filtered_students as $fs): ?>
        <tr>
          <td style="text-align: center; display: flex; justify-content: center; gap: 10px;">
            <a href="results.php?exam_id=<?= $sel_exam ?>&class_id=<?= $sel_class ?>&student_id=<?= $fs['id'] ?>&search=<?= urlencode($search_term) ?>" style="color: #6366f1; font-size: 1.1rem;" title="View Result Card">
              <i class="fa-solid fa-eye"></i>
            </a>
            <a href="results.php?view=all_exams&student_id=<?= $fs['id'] ?>" style="color: #10b981; font-size: 1.1rem;" title="View All Exams Progress Report">
              <i class="fa-solid fa-chart-line"></i>
            </a>
          </td>
          <td><strong><?= htmlspecialchars($fs['admission_no']) ?></strong></td>
          <td><?= htmlspecialchars($fs['roll_no'] ?? '-') ?></td>
          <td><strong><?= htmlspecialchars($fs['name']) ?></strong></td>
          <td><?= htmlspecialchars($fs['father_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars(($fs['class_name'] ?? '').' '.($fs['section'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php else: // student/parent: show exam picker ?>
<div class="filter-bar no-print">
  <form method="GET" class="filter-bar" style="margin-bottom:0">
    <input type="hidden" name="student_id" value="<?= $sel_student ?>">
    <select name="exam_id" class="form-control">
      <option value="">-- Select Exam --</option>
      <?php foreach($exams_arr as $e): ?><option value="<?= $e['id'] ?>" <?= $sel_exam==$e['id']?'selected':'' ?>><?= htmlspecialchars($e['name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">View My Result</button>
  </form>
</div>
<?php endif; ?>

<!-- RESULT CARD -->
<?php if($result_data && !empty($result_data['marks'])): ?>
<div class="result-card">
  <div class="result-school">
    <h2><?= htmlspecialchars($school_name) ?></h2>
    <p><?= htmlspecialchars($settings['school_address'] ?? '') ?> | Ph: <?= htmlspecialchars($settings['school_phone'] ?? '') ?></p>
    <h3>RESULT CARD — <?= htmlspecialchars($result_data['exam']['name'] ?? '') ?></h3>
  </div>
  <div class="result-info-grid">
    <div class="result-info-row"><span>Student Name:</span><strong><?= htmlspecialchars($result_data['name']) ?></strong></div>
    <div class="result-info-row"><span>Admission No:</span><span><?= htmlspecialchars($result_data['admission_no']) ?></span></div>
    <div class="result-info-row"><span>Father's Name:</span><span><?= htmlspecialchars($result_data['father_name'] ?? '-') ?></span></div>
    <div class="result-info-row"><span>Class:</span><span><?= htmlspecialchars(($result_data['class_name']??'-').' '.($result_data['section']??'')) ?></span></div>
    <div class="result-info-row"><span>Roll No:</span><span><?= htmlspecialchars($result_data['roll_no'] ?? '-') ?></span></div>
    <div class="result-info-row"><span>Session:</span><span><?= $session_year ?></span></div>
  </div>
  <table class="result-marks-table">
    <thead><tr><th>#</th><th>Subject</th><th>Max</th><th>Obtained</th><th>Grade</th><th>Remarks</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($result_data['marks'] as $i=>$m): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= htmlspecialchars($m['sub_name']) ?></td>
      <td><?= $m['max_marks'] ?></td>
      <td><?= $m['is_absent'] ? '<span style="color:red">Absent</span>' : $m['marks_obtained'] ?></td>
      <td><strong><?= $m['grade'] ?></strong></td>
      <td><?= $m['remarks'] ?></td>
      <td><?= $m['passed'] ? '<span style="color:green">Pass</span>' : '<span style="color:red">Fail</span>' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:700;background:#f8f8f8">
        <td colspan="2">TOTAL</td>
        <td><?= $result_data['total_max'] ?></td>
        <td><?= $result_data['total_obtained'] ?></td>
        <td><?= $result_data['overall_grade'] ?></td>
        <td><?= $result_data['overall_remarks'] ?></td>
        <td><?= $result_data['result_status'] === 'PASS' ? '<span class="badge-pass">PASS</span>' : '<span class="badge-fail">FAIL</span>' ?></td>
      </tr>
    </tfoot>
  </table>
  <div class="result-summary">
    <div class="result-summary-box"><div class="val"><?= $result_data['overall_pct'] ?>%</div><div class="lbl">Percentage</div></div>
    <div class="result-summary-box"><div class="val"><?= $result_data['overall_grade'] ?></div><div class="lbl">Grade</div></div>
    <?php if (!empty($result_data['class_position'])): ?>
    <div class="result-summary-box" style="background:#fef3c7;border-color:#fde68a"><div class="val" style="color:#d97706"><?= $result_data['class_position'] ?></div><div class="lbl" style="color:#92400e">Class Position</div></div>
    <?php endif; ?>
    <div class="result-summary-box"><div class="val" style="color:<?= $result_data['fail_count']>0?'#ef4444':'#22c55e' ?>"><?= $result_data['fail_count'] ?></div><div class="lbl">Subjects Failed</div></div>
    <div class="result-summary-box"><div class="val" style="color:<?= $result_data['result_status']==='PASS'?'#22c55e':'#ef4444' ?>"><?= $result_data['result_status'] ?></div><div class="lbl">Result</div></div>
  </div>
  <div style="margin-top:24px;display:flex;justify-content:space-between;font-size:12px;color:#555">
    <div>Class Teacher: _________________</div>
    <div>Principal: _________________</div>
    <div>Printed: <?= date('d M Y') ?></div>
  </div>
</div>

<div class="no-print" style="margin-top: 20px; text-align: center;">
  <a href="results.php?view=all_exams&student_id=<?= $result_data['id'] ?>" class="btn" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-weight: 700; padding: 10px 24px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
    <i class="fa-solid fa-chart-line"></i> View All-Exams Progress &amp; Growth Ratios for <?= htmlspecialchars($result_data['name']) ?>
  </a>
</div>
<?php elseif($sel_exam > 0 && $sel_student > 0): ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-trophy"></i></div><h3>No marks found</h3><p>Marks have not been entered for this student and exam.</p></div>
<?php else: ?>
<div class="empty-state"><div class="icon"><i class="fa-solid fa-trophy"></i></div><h3>Select exam and student</h3><p>Choose an exam and student to view the result card.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




