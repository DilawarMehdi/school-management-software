 School Management & Student System

> A comprehensive, web-based School Management System built with PHP & MySQL, covering student administration, fee management, exams, staff, and more.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Project Structure](#project-structure)
- [Modules](#modules)
- [Getting Started](#getting-started)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Technologies Used](#technologies-used)
- [License](#license)

---

## Overview

**SIAX-SMSS** (School Management & Student System) is a full-featured school administration platform. It manages the complete lifecycle of school operations — from student admissions and fee collection to exam scheduling, result generation, staff payroll, and printable reports.

The system supports **multi-school subscriptions**, a **parent/student portal**, and **public result lookup**.

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| 🎓 **Student Management** | Register, enroll, and manage student profiles, classes, and attendance |
| 💰 **Fee Management** | Fee templates, invoice generation, challans, discounts, scholarships, fine policies |
| 📝 **Exam & Results** | Exam scheduling, grading policies, result cards, roll number slips, award lists |
| 👨‍🏫 **Staff Management** | Staff profiles, departments, designations, attendance, salary slips |
| 👪 **Parent Portal** | Dedicated portal for parents/students to view results and fee status |
| 📊 **Reports** | Enrollment register, fee statements, award lists, and more |
| 🖨️ **Print Module** | Printable challans, receipts, result cards, salary slips, ID cards |
| 🔐 **Authentication** | Secure login, session management, subscription validation |
| 📋 **Subscription Management** | Multi-school SaaS-style subscription control |
| 🌐 **Public Result** | Public-facing student result lookup page |

---

## 📁 Project Structure

```
siax-smss/
├── index.php                   # Login page
├── dashboard.php               # Main dashboard
├── portal.php                  # Parent/student portal
├── public_result.php           # Public result lookup
├── register.php                # School registration
├── signup_requests.php         # Manage signup requests
├── subscription_mgmt.php       # Subscription management
├── subscription_status.php     # Subscription status checker
├── schema_migration.php        # DB migration runner
├── config/
│   └── db.php                  # Database connection config
├── includes/
│   └── auth.php                # Authentication helpers
├── modules/
│   ├── students/               # Student management
│   ├── exams/                  # Exams & results
│   ├── fees/                   # Fee management
│   ├── staff/                  # Staff management
│   ├── parents/                # Parent/guardian records
│   ├── expenses/               # School expenses
│   ├── reports/                # Report pages
│   ├── resources/              # Learning resources
│   ├── settings/               # System settings
│   └── fall/                   # Miscellaneous/fall module
├── prints/                     # Printable documents
├── portal/                     # Portal sub-pages
├── setup/
│   └── install.sql             # Full database schema
├── assets/                     # Images, fonts, static assets
├── css/                        # Stylesheets
├── js/                         # JavaScript files
├── api/                        # API endpoints
├── uploads/                    # Uploaded files
└── .htaccess                   # Apache URL rules
```

---

## 🧩 Modules

### 👨‍🎓 Students (`modules/students/`)
| File | Description |
|------|-------------|
| `students.php` | Student listing and management |
| `register_student.php` | New student registration form |
| `student_profile.php` | Individual student profile view |
| `admission_form.php` | Printable admission form |
| `student_id_cards.php` | Generate & print student ID cards |
| `classes.php` | Class/section management |
| `enrollment.php` | Class enrollment |
| `attendance.php` | Student attendance tracking |

### 📝 Exams (`modules/exams/`)
| File | Description |
|------|-------------|
| `exams.php` | Exam management & marks entry |
| `results.php` | Result computation & display |
| `exam_schedule.php` | Exam timetable management |
| `exam_types.php` | Manage exam types (midterm, final, etc.) |
| `subjects.php` | Subject management |
| `grading_policy.php` | Define grading policies |
| `roll_number_slips.php` | Generate & print roll number slips |

### 💰 Fees (`modules/fees/`)
| File | Description |
|------|-------------|
| `fees.php` | Fee overview and collection |
| `fee_templates.php` | Fee structure templates |
| `generate_invoice.php` | Generate fee invoices |
| `invoice_detail.php` | Invoice detail view |
| `monthly_fee_invoices.php` | Monthly invoice management |
| `advance_fee.php` | Advance fee collection |
| `average_fee.php` | Average fee reports |
| `discount_scholarship.php` | Discounts & scholarships |
| `fine_policy.php` | Late fee fine policies |
| `fee_criteria.php` | Fee criteria management |
| `student_fee_history.php` | Per-student fee history |

### 👨‍🏫 Staff (`modules/staff/`)
| File | Description |
|------|-------------|
| `staff_list.php` | Full staff listing & management |
| `staff_attendance.php` | Staff attendance tracking |
| `salary_templates.php` | Salary structure templates |
| `salary_slips.php` | Generate salary slips |
| `departments.php` | Department management |
| `designations.php` | Staff designations |

### 🖨️ Prints (`prints/`)
| File | Description |
|------|-------------|
| `print_challan_single.php` | Single student fee challan |
| `print_challan_all.php` | Batch challan printing |
| `print_receipt.php` | Fee receipt |
| `print_all_result_cards.php` | Bulk result card printing |
| `print_award_list.php` | Award/merit list |
| `print_enrollment_register.php` | Class enrollment register |
| `print_students.php` | Student list print |
| `print_fee_list.php` | Fee collection list |
| `print_fee_statement.php` | Student fee statement |

---

## 🚀 Getting Started

### Prerequisites

- **XAMPP** (Apache + MySQL + PHP 7.4+)
- Web browser (Chrome, Firefox, Edge)

### Installation

1. **Clone / copy** the project into your XAMPP `htdocs` folder:
   ```
   C:\Xampp\htdocs\siax-smss\
   ```

2. **Start XAMPP** — enable Apache and MySQL from the XAMPP Control Panel.

3. **Set up the database** (see [Database Setup](#database-setup) below).

4. **Open in browser:**
   ```
   http://localhost/siax-smss/
   ```

---

## 🗄️ Database Setup

1. Open **phpMyAdmin**: `http://localhost/phpmyadmin/`
2. Create a new database (e.g., `siax_smss`)
3. Import the schema:
   - Go to **Import** tab
   - Select `setup/install.sql`
   - Click **Go**

---

## ⚙️ Configuration

Edit `config/db.php` to set your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'siax_smss');
define('DB_USER', 'root');
define('DB_PASS', '');
```

---

## 🛠️ Technologies Used

| Technology | Role |
|------------|------|
| **PHP** | Server-side logic, session management |
| **MySQL** | Relational database |
| **HTML5 / CSS3** | UI structure and styling |
| **JavaScript** | Dynamic interactions |
| **XAMPP** | Local development server (Apache + MySQL) |
| **Apache `.htaccess`** | URL rewriting & access control |


*Empowering schools with smarter administration. 🏫*
