<?php
$page_title = 'Challan Form Settings';
$active_page = 'chalan_form_settings';
require_once __DIR__ . '/../../includes/header.php';
require_role('admin');

$msg = ''; $err = '';

// Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    foreach ($_POST as $key => $value) {
        if ($key === 'save_settings') continue;
        $val = $conn->real_escape_string($value);
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$val') 
                      ON DUPLICATE KEY UPDATE setting_value = '$val'");
    }

    // Handle Logo Upload
    if (!empty($_FILES['school_logo']['name'])) {
        if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION));
        $file_name = "school_logo_" . time() . "." . $file_ext;

        if (move_uploaded_file($_FILES['school_logo']['tmp_name'], UPLOAD_PATH . $file_name)) {
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('school_logo', '$file_name') 
                          ON DUPLICATE KEY UPDATE setting_value = '$file_name'");
            $msg = 'Settings and Logo updated successfully.';
        } else {
            $err = 'Error uploading logo.';
        }
    } else {
        $msg = 'Settings updated successfully.';
    }

    // Handle Checkbox for Show Logo (checkboxes are not sent if unchecked)
    $show_logo = isset($_POST['challan_show_logo']) ? '1' : '0';
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('challan_show_logo', '$show_logo') 
                  ON DUPLICATE KEY UPDATE setting_value = '$show_logo'");

    // Refresh settings
    $settings = all_settings($conn);
}

// Default values if not set
$challan_settings = [
    'challan_main_heading' => $settings['school_name'] ?? '',
    'challan_sub_heading' => '',
    'challan_phone' => $settings['phone'] ?? '',
    'challan_bank_name' => $settings['bank_name'] ?? '',
    'challan_account_title' => $settings['bank_title'] ?? '',
    'challan_account_no' => $settings['bank_account'] ?? '',
    'challan_copy1_title' => 'School/College Copy',
    'challan_copy2_title' => 'Bank Copy',
    'challan_copy3_title' => 'Student Copy',
    'challan_instructions' => '1.Please pay the fee before the due date mentioned above. 2.Keep the receipt safe for future reference.',
    'challan_show_logo' => $settings['challan_show_logo'] ?? '1'
];

// Merge with database values
foreach($challan_settings as $k => $v) {
    if(isset($settings[$k])) $challan_settings[$k] = $settings[$k];
}
?>

<style>
    .settings-card { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); padding: 30px; max-width: 900px; margin: 20px auto; }
    .form-row { display: grid; grid-template-columns: 200px 1fr; gap: 20px; align-items: center; margin-bottom: 15px; }
    .form-row label { font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); text-align: right; }
    .form-control { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-primary); font-size: 0.9rem; }
    .btn-save { width: 100%; padding: 14px; background: var(--accent); color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 20px; transition: 0.3s; }
    .btn-save:hover { opacity: 0.9; transform: translateY(-2px); }
    textarea.form-control { height: 100px; resize: vertical; }
</style>

<div class="page-header">
    <div>
        <h1><i class="fa-solid fa-receipt"></i> Challan Form Settings</h1>
        <p>Customize the content and titles on generated fee slips</p>
    </div>
</div>

<?php if($msg): ?>
    <div class="alert alert-success" style="max-width: 900px; margin: 0 auto 20px;"><?= $msg ?></div>
<?php endif; ?>

<?php if($err): ?>
    <div class="alert alert-danger" style="max-width: 900px; margin: 0 auto 20px;"><?= $err ?></div>
<?php endif; ?>

<div class="settings-card">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="save_settings" value="1">
        
        <div class="form-row">
            <label>School Logo</label>
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php if(!empty($settings['school_logo'])): ?>
                    <?php 
                    $logo_preview = '';
                    if (file_exists(UPLOAD_PATH . $settings['school_logo'])) {
                        $logo_preview = BASE_URL . 'uploads/' . htmlspecialchars($settings['school_logo']);
                    } elseif (file_exists($settings['school_logo'])) {
                        $logo_preview = BASE_URL . htmlspecialchars($settings['school_logo']);
                    }
                    ?>
                    <?php if($logo_preview): ?>
                    <img src="<?= $logo_preview ?>" style="width: 50px; height: 50px; border-radius: 8px; border: 1px solid var(--border); object-fit: contain;">
                    <?php endif; ?>
                <?php endif; ?>
                <input type="file" name="school_logo" class="form-control" accept="image/*">
            </div>
        </div>

        <div class="form-row">
            <label>Display Logo</label>
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="challan_show_logo" value="1" <?= $challan_settings['challan_show_logo'] == '1' ? 'checked' : '' ?> style="width: 20px; height: 20px;">
                <span style="font-size: 0.85rem; color: var(--text-secondary);">Show logo in Challan header</span>
            </div>
        </div>

        <div class="form-row">
            <label>Main Heading</label>
            <input type="text" name="challan_main_heading" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_main_heading']) ?>">
        </div>

        <div class="form-row">
            <label>Sub Heading</label>
            <input type="text" name="challan_sub_heading" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_sub_heading']) ?>">
        </div>

        <div class="form-row">
            <label>Phone Number</label>
            <input type="text" name="challan_phone" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_phone']) ?>">
        </div>

        <div class="form-row">
            <label>Bank Name</label>
            <input type="text" name="challan_bank_name" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_bank_name']) ?>">
        </div>

        <div class="form-row">
            <label>Account Title</label>
            <input type="text" name="challan_account_title" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_account_title']) ?>">
        </div>

        <div class="form-row">
            <label>Account No</label>
            <input type="text" name="challan_account_no" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_account_no']) ?>">
        </div>

        <hr style="margin: 25px 0; border: none; border-top: 1px solid var(--border);">

        <div class="form-row">
            <label>Title of Copy 1</label>
            <input type="text" name="challan_copy1_title" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_copy1_title']) ?>">
        </div>

        <div class="form-row">
            <label>Title of Copy 2</label>
            <input type="text" name="challan_copy2_title" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_copy2_title']) ?>">
        </div>

        <div class="form-row">
            <label>Title of Copy 3</label>
            <input type="text" name="challan_copy3_title" class="form-control" value="<?= htmlspecialchars($challan_settings['challan_copy3_title']) ?>">
        </div>

        <div class="form-row">
            <label>Instructions</label>
            <textarea name="challan_instructions" class="form-control"><?= htmlspecialchars($challan_settings['challan_instructions']) ?></textarea>
        </div>

        <button type="submit" class="btn-save">Save Settings</button>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>




