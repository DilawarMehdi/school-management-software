<?php
/**
 * SIAX SMSS - Admission Form Settings
 * Customize visible fields in the registration form.
 */
$page_title = 'Admission Form Settings';
$active_page = 'admission_settings';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin');

$msg = ''; $err = '';

// Default fields and their display names
$reg_fields = [
    'field_admission_no'      => 'Admission Number',
    'field_family_id'         => 'Family ID / Number',
    'field_admission_class'   => 'Admission Class (History)',
    'field_photo'             => 'Student Photo',
    'field_gender'            => 'Gender Selection',
    'field_dob'               => 'Date of Birth',
    'field_religion'          => 'Religion',
    'field_cnic'              => 'CNIC / B-Form',
    'field_nationality'       => 'Nationality',
    'field_address'           => 'Present Address',
    'field_permanent_address' => 'Permanent Address',
    'field_phone'             => 'Student Phone',
    'field_email'             => 'Email Address',
    'field_father_name'       => 'Father\'s Name',
    'field_father_occupation' => 'Father\'s Occupation',
    'field_mother_name'       => 'Mother\'s Name',
    'field_father_phone'      => 'Father\'s Phone',
    'field_guardian_name'     => 'Guardian Name',
    'field_guardian_contact'  => 'Guardian Contact',
    'field_emergency_contact' => 'Emergency Contact'
];

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_admission_settings') {
    foreach ($reg_fields as $key => $label) {
        $val = isset($_POST[$key]) ? '1' : '0';
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$val') ON DUPLICATE KEY UPDATE setting_value='$val'");
    }
    $msg = 'Admission form settings updated successfully.';
    $settings = all_settings($conn); // refresh global settings
}

// Load current settings (default to 1 if not set)
$current_settings = [];
foreach ($reg_fields as $key => $label) {
    $current_settings[$key] = isset($settings[$key]) ? (int)$settings[$key] : 1;
}
?>

<div class="page-header">
    <div>
        <h1><i class="fa-solid fa-clipboard-list"></i> Admission Form Settings</h1>
        <p>Enable or disable fields in the student registration form</p>
    </div>
</div>

<?php if ($msg): ?><div class="login-error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:#34d399;"><?= $msg ?></div><?php endif; ?>

<div class="registration-container" style="max-width: 800px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-eye"></i> Form Field Visibility</h3>
        </div>
        <form method="POST" style="padding: 30px;">
            <input type="hidden" name="action" value="save_admission_settings">
            
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
                Check the fields you want to appear in the <strong>Register Student</strong> form. Unchecked fields will be hidden.
            </p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px 40px;">
                <?php foreach ($reg_fields as $key => $label): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'">
                    <div class="toggle-switch">
                        <input type="checkbox" name="<?= $key ?>" id="<?= $key ?>" <?= $current_settings[$key] ? 'checked' : '' ?> style="cursor: pointer; width: 18px; height: 18px;">
                    </div>
                    <label for="<?= $key ?>" style="cursor: pointer; font-size: 0.95rem; font-weight: 500; color: var(--text-primary);"><?= $label ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 40px; border-top: 1px solid var(--border); padding-top: 25px;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-weight: 700;">
                    <i class="fa-solid fa-floppy-disk"></i> Apply Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
