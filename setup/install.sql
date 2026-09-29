-- ============================================================
-- SIAC SMSS — School Management Software System
-- Database Installation Script
-- ============================================================

CREATE DATABASE IF NOT EXISTS siax_smss CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siax_smss;

-- ─────────────────────────────────────────
-- SCHOOL SETTINGS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
('school_name', 'SIAX Public School'),
('school_address', '123 Education Street, City'),
('school_phone', '0300-0000000'),
('school_email', 'info@siaxschool.com'),
('principal_name', 'Mr. Principal'),
('session_year', '2025-2026'),
('currency', 'PKR'),
('grade_a_plus', '90'),
('grade_a', '80'),
('grade_b', '70'),
('grade_c', '60'),
('grade_d', '50'),
('subscription_start', '2026-01-01'),
('subscription_expiry', '2035-12-31');

-- ─────────────────────────────────────────
-- USERS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('admin','principal','accountant','teacher','student','parent') NOT NULL,
  email VARCHAR(100),
  phone VARCHAR(20),
  is_active TINYINT(1) DEFAULT 1,
  last_login DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin user: admin / admin123
INSERT INTO users (username, password_hash, full_name, role, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'admin@siaxsmss.com'),
('principal', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'School Principal', 'principal', 'principal@siaxsmss.com'),
('accountant', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'School Accountant', 'accountant', 'accountant@siaxsmss.com'),
('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mr. Ahmed Khan', 'teacher', 'ahmed@siaxsmss.com'),
('teacher2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ms. Sara Ali', 'teacher', 'sara@siaxsmss.com');
-- Default password for all: password

-- ─────────────────────────────────────────
-- CLASSES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  section VARCHAR(10) NOT NULL DEFAULT 'A',
  teacher_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_class_section (name, section)
);

INSERT INTO classes (name, section, teacher_id) VALUES
('Nursery', 'A', 4), ('KG', 'A', 4), ('KG', 'B', 5),
('Class 1', 'A', 4), ('Class 1', 'B', 5),
('Class 2', 'A', 4), ('Class 2', 'B', 5),
('Class 3', 'A', 4), ('Class 4', 'A', 5),
('Class 5', 'A', 4), ('Class 6', 'A', 5),
('Class 7', 'A', 4), ('Class 8', 'A', 5),
('Class 9', 'A', 4), ('Class 9', 'B', 5),
('Class 10', 'A', 4), ('Class 10', 'B', 5);

-- ─────────────────────────────────────────
-- SUBJECTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20),
  max_marks INT DEFAULT 100,
  pass_marks INT DEFAULT 40,
  teacher_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- STUDENTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admission_no VARCHAR(20) NOT NULL UNIQUE,
  family_id VARCHAR(50),
  name VARCHAR(100) NOT NULL,
  father_name VARCHAR(100),
  father_occupation VARCHAR(255),
  mother_name VARCHAR(100),
  guardian_name VARCHAR(255),
  guardian_contact VARCHAR(20),
  cnic VARCHAR(20),
  dob DATE,
  gender ENUM('Male','Female','Other') DEFAULT 'Male',
  religion VARCHAR(50) DEFAULT 'Islam',
  nationality VARCHAR(50) DEFAULT 'Pakistani',
  address TEXT,
  permanent_address TEXT,
  phone VARCHAR(20),
  father_phone VARCHAR(20),
  email VARCHAR(100),
  class_id INT,
  admission_class_id INT,
  roll_no VARCHAR(10),
  admission_date DATE,
  status ENUM('Active','Left','Transferred','Passed') DEFAULT 'Active',
  photo VARCHAR(255),
  user_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- FEE HEADS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fee_heads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('Monthly','One-Time','Annual') DEFAULT 'Monthly',
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO fee_heads (name, type) VALUES
('Tuition Fee', 'Monthly'),
('Admission Fee', 'One-Time'),
('Transport Fee', 'Monthly'),
('Examination Fee', 'Annual'),
('Computer Fee', 'Monthly'),
('Library Fee', 'Annual'),
('Sports Fee', 'Annual'),
('Fine', 'One-Time'),
('Other Charges', 'One-Time');

-- ─────────────────────────────────────────
-- FEE STRUCTURES (per class)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fee_structures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  fee_head_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  session_year VARCHAR(20) DEFAULT '2025-2026',
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (fee_head_id) REFERENCES fee_heads(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- FEE PAYMENTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fee_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(30) NOT NULL UNIQUE,
  student_id INT NOT NULL,
  payment_date DATE NOT NULL,
  month VARCHAR(20),
  session_year VARCHAR(20) DEFAULT '2025-2026',
  subtotal DECIMAL(10,2) DEFAULT 0,
  discount DECIMAL(10,2) DEFAULT 0,
  fine DECIMAL(10,2) DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL,
  paid_amount DECIMAL(10,2) NOT NULL,
  balance DECIMAL(10,2) DEFAULT 0,
  payment_mode ENUM('Cash','Online','Cheque') DEFAULT 'Cash',
  collected_by INT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- FEE PAYMENT ITEMS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fee_payment_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_id INT NOT NULL,
  fee_head_id INT,
  fee_head_name VARCHAR(100),
  amount DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (payment_id) REFERENCES fee_payments(id) ON DELETE CASCADE,
  FOREIGN KEY (fee_head_id) REFERENCES fee_heads(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- ATTENDANCE
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  date DATE NOT NULL,
  status ENUM('Present','Absent','Late','Leave') DEFAULT 'Present',
  marked_by INT,
  notes VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_attendance (student_id, date),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS exam_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    session_year VARCHAR(20) DEFAULT '2025-2026',
    is_announced TINYINT(1) DEFAULT 0,
    result_token VARCHAR(64) DEFAULT NULL,
    announced_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO exams (name, session_year, is_published) VALUES
('First Term Exam 2025', '2025-2026', 0),
('Mid Term Exam 2025', '2025-2026', 0),
('Final Term Exam 2026', '2025-2026', 0);

-- ─────────────────────────────────────────
-- MARKS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS marks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  marks_obtained DECIMAL(6,2) DEFAULT 0,
  max_marks INT DEFAULT 100,
  pass_marks INT DEFAULT 40,
  is_absent TINYINT(1) DEFAULT 0,
  entered_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_mark (exam_id, student_id, subject_id),
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- CERTIFICATES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cert_no VARCHAR(30) NOT NULL UNIQUE,
  student_id INT NOT NULL,
  type ENUM('Leaving','Transfer','Character','Bonafide','Migration') NOT NULL,
  issue_date DATE NOT NULL,
  reason TEXT,
  destination_school VARCHAR(200),
  fee_clearance TINYINT(1) DEFAULT 0,
  conduct VARCHAR(100) DEFAULT 'Good',
  remarks TEXT,
  issued_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- NOTIFICATIONS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  role VARCHAR(20),
  title VARCHAR(200) NOT NULL,
  message TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- SIGNUP REQUESTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS signup_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  school_name VARCHAR(150) NOT NULL,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME
);

-- ─────────────────────────────────────────
-- VIDEO RESOURCES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS video_resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'General Tutorial',
  description TEXT,
  file_name VARCHAR(255) NOT NULL,
  file_size BIGINT DEFAULT 0,
  badge VARCHAR(50) DEFAULT 'Tutorial',
  badge_color VARCHAR(20) DEFAULT '#00b894',
  icon VARCHAR(50) DEFAULT 'fa-video',
  is_featured TINYINT(1) DEFAULT 0,
  sort_order INT DEFAULT 0,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────
-- STUDENT ENROLLMENTS (session-based class tracking)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS student_enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  session_year VARCHAR(50) NOT NULL,
  roll_no VARCHAR(20),
  shift VARCHAR(50) DEFAULT 'Morning',
  enrollment_date DATE,
  discharge_date DATE DEFAULT NULL,
  status ENUM('Active', 'Discharged', 'Promoted') DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- FEE INVOICES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fee_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  month VARCHAR(20) NOT NULL,
  session_year VARCHAR(20) NOT NULL,
  created_at DATE NOT NULL,
  due_date DATE NOT NULL,
  valid_till DATE NOT NULL,
  student_count INT DEFAULT 0,
  prev_pending DECIMAL(10,2) DEFAULT 0,
  current_fee DECIMAL(10,2) DEFAULT 0,
  received_fee DECIMAL(10,2) DEFAULT 0,
  waived_fee DECIMAL(10,2) DEFAULT 0,
  fine_amount DECIMAL(10,2) DEFAULT 0,
  fine_off DECIMAL(10,2) DEFAULT 0
);

-- ─────────────────────────────────────────
-- STUDENT MONTHLY FEES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS student_monthly_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  month VARCHAR(20) NOT NULL,
  fee_details TEXT DEFAULT NULL,
  tuition_fee DECIMAL(10,2) DEFAULT 0,
  admission_fee DECIMAL(10,2) DEFAULT 0,
  prev_pending DECIMAL(10,2) DEFAULT 0,
  discount_amount DECIMAL(10,2) DEFAULT 0,
  fine_amount DECIMAL(10,2) DEFAULT 0,
  paid_amount DECIMAL(10,2) DEFAULT 0,
  payment_date DATE DEFAULT NULL,
  status ENUM('Due', 'Partial', 'Paid', 'Unpaid', 'Partially Paid') DEFAULT 'Due',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────
-- FEE PAYMENT HISTORY
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fee_payment_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  monthly_fee_id INT NOT NULL,
  amount_paid DECIMAL(10,2) DEFAULT 0,
  fine_paid DECIMAL(10,2) DEFAULT 0,
  waived_fee DECIMAL(10,2) DEFAULT 0,
  waived_fine DECIMAL(10,2) DEFAULT 0,
  payment_date DATE NOT NULL,
  received_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (monthly_fee_id) REFERENCES student_monthly_fees(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- ADVANCE FEES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS advance_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  invoice_id INT DEFAULT NULL,
  advance_month VARCHAR(30) NOT NULL,
  advance_year INT NOT NULL,
  amount DECIMAL(10,2) DEFAULT 0,
  status ENUM('Pending','Applied') DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  applied_at DATETIME DEFAULT NULL,
  notes TEXT DEFAULT NULL
);

-- ─────────────────────────────────────────
-- EXPENSE CATEGORIES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expense_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────
-- EXPENSES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT,
  amount DECIMAL(10,2) NOT NULL,
  description TEXT,
  expense_date DATE NOT NULL,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- GRADING POLICIES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS grading_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  is_default TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO grading_policies (title, is_default) VALUES ('Generic Grading Policy', 1);

CREATE TABLE IF NOT EXISTS grading_policy_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  policy_id INT NOT NULL,
  grade_letter VARCHAR(10) NOT NULL,
  min_percent DECIMAL(5,2) NOT NULL,
  max_percent DECIMAL(5,2) NOT NULL,
  remarks VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (policy_id) REFERENCES grading_policies(id) ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- EXAM SCHEDULES & CLASS SUBJECTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_type_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS class_subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  exam_type_id INT NOT NULL,
  subject VARCHAR(200) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS schedule_dates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT NOT NULL,
  class_subject_id INT NOT NULL,
  exam_date DATE,
  start_time TIME,
  UNIQUE KEY(schedule_id, class_subject_id)
);


