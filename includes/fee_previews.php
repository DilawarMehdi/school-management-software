<?php
function renderSlipPreview($template) {
    $student = ['name' => 'Ali Khan', 'class' => 'Class 8', 'section' => 'A', 'roll' => '24'];
    $items = [
        ['description' => 'Tuition Fee', 'amount' => '25,000'],
        ['description' => 'Lab Fee', 'amount' => '2,000'],
        ['description' => 'Sports Fee', 'amount' => '1,500'],
    ];
    $subtotal = 28500;
    $discount = 1500;
    $total = 27000;
    ob_start();
    if ($template === 'classic') {
        ?>
        <div class="slip-preview slip-classic">
            <div class="slip-header">
                <div style="text-align:center">
                    <h2 style="margin:0">SIAX-SMSS Fee Slip</h2>
                    <p style="margin:5px 0">Traditional Classic Layout</p>
                </div>
            </div>
            <div class="slip-info" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:20px; border:1px solid #ddd; padding:10px;">
                <div><strong>Student:</strong> <?= $student['name'] ?></div>
                <div><strong>Class:</strong> <?= $student['class'] ?></div>
                <div><strong>Section:</strong> <?= $student['section'] ?></div>
                <div><strong>Roll:</strong> <?= $student['roll'] ?></div>
            </div>
            <div style="margin-top:20px;">
                <?php foreach ($items as $item): ?>
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #ccc;">
                        <span><?= $item['description'] ?></span>
                        <span>Rs <?= $item['amount'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="slip-summary" style="margin-top:20px; margin-left:auto; width:200px;">
                <div style="display:flex; justify-content:space-between; font-weight:bold; border-top:2px solid #000; padding-top:10px;">
                    <span>Total Amount</span>
                    <span>Rs <?= number_format($total) ?></span>
                </div>
            </div>
            <div style="margin-top:40px; text-align:center; font-size:0.8rem; border-top:1px solid #eee; padding-top:10px;">Computer Generated - No Signature Required</div>
        </div>
        <?php
    } elseif ($template === 'modern') {
        ?>
        <div class="slip-preview slip-modern" style="border:none; overflow:hidden; padding:0; border-radius:12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
            <div style="background:#00b894; color:#fff; padding:20px; display:flex; justify-content:space-between; align-items:center;">
                <h2 style="margin:0">SIAX-SMSS</h2>
                <span style="background:rgba(255,255,255,0.2); padding:5px 12px; border-radius:20px; font-size:0.7rem;">MODERN STYLE</span>
            </div>
            <div style="padding:20px;">
                <div style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:15px;">
                    <div style="font-weight:700; color:#00b894; margin-bottom:5px;"><?= $student['name'] ?></div>
                    <div style="font-size:0.8rem; color:#666;"><?= $student['class'] ?> - Section <?= $student['section'] ?></div>
                </div>
                <?php foreach ($items as $item): ?>
                    <div style="display:flex; justify-content:space-between; padding:10px; background:#fff; border:1px solid #eee; border-radius:8px; margin-bottom:8px;">
                        <span style="color:#555"><?= $item['description'] ?></span>
                        <span style="font-weight:700">Rs <?= $item['amount'] ?></span>
                    </div>
                <?php endforeach; ?>
                <div style="background:#00b894; color:#fff; padding:15px; border-radius:8px; margin-top:20px; display:flex; justify-content:space-between; font-size:1.1rem; font-weight:700;">
                    <span>Total Paid</span>
                    <span>Rs <?= number_format($total) ?></span>
                </div>
            </div>
        </div>
        <?php
    } elseif ($template === 'challan') {
        ?>
        <div class="slip-preview slip-challan" style="border: 2px solid #000; padding: 0; color: #000; background: #fff;">
            <div style="border-bottom: 2px solid #000; padding: 15px; display: grid; grid-template-columns: 60px 1fr; gap: 15px; align-items: center;">
                <div style="width:50px; height:50px; border:1px dashed #ccc; display:flex; align-items:center; justify-content:center; font-size:10px; color:#999;">LOGO</div>
                <div style="text-align:center;">
                    <h2 style="font-size: 16px; margin:0; font-weight:900; text-transform:uppercase;">Karakorum Paradise Public School</h2>
                    <p style="font-weight:700; margin:5px 0; font-size:10px;">Phone Number : 03555295651</p>
                </div>
            </div>
            <div style="text-align:center; padding:5px; font-weight:900; background:#fff; font-size:11px; border-bottom:2px solid #000;">School/College Copy</div>
            <table style="width:100%; border-collapse: collapse; font-size: 10px;">
                <tr style="border-bottom: 1px solid #000;"><td style="padding:5px; border-right: 1px solid #000; font-weight:700; width:40%;">Challan Form No</td><td style="padding:5px; font-weight:800; font-size:14px;">1</td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:5px; border-right: 1px solid #000;">Due Date: <strong>10-Apr-2026</strong></td><td style="padding:5px;">Valid Till: <strong>29-Apr-2026</strong></td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:5px; border-right: 1px solid #000; font-weight:700;">Student Reg No</td><td style="padding:5px; font-weight:800;">349-26</td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:5px; border-right: 1px solid #000; font-weight:700;">Student Name</td><td style="padding:5px; font-weight:800;">Khalid Ameen</td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:5px; border-right: 1px solid #000; font-weight:700;">Father Name</td><td style="padding:5px;">Muhammad Amin</td></tr>
                <tr style="border-bottom: 2px solid #000;"><td style="padding:5px; border-right: 1px solid #000; font-weight:700;">Class</td><td style="padding:5px;">Play Group - Jasmine</td></tr>
            </table>
            <table style="width:100%; border-collapse: collapse; font-size: 10px;">
                <tr style="font-weight:800; text-align:center; border-bottom: 1px solid #000;"><td style="padding:5px; border-right: 2px solid #000; width:70%;">Description</td><td style="padding:5px;">Amount</td></tr>
                <tr style="border-bottom: 1px solid #000;"><td style="padding:5px; border-right: 2px solid #000;">Tuition Fee</td><td style="padding:5px; text-align:center; font-weight:700;">1500</td></tr>
                <tr style="border-bottom: 1px solid #000; font-weight:800;"><td style="padding:5px; border-right: 2px solid #000;">Fee Within Due Date</td><td style="padding:5px; text-align:center;">1500</td></tr>
                <tr style="border-bottom: 2px solid #000; font-weight:800;"><td style="padding:5px; border-right: 2px solid #000;">Fee After Due Date</td><td style="padding:5px; text-align:center;">1500</td></tr>
            </table>
            <div style="padding:10px; font-size: 10px; border-bottom: 2px solid #000;"><strong>Amount in words :</strong> Rupees One thousand Five hundred</div>
            <div style="padding:10px; font-size: 9px; line-height:1.4;"><strong>NOTE:</strong> 1.Please pay the fee before the due date mentioned above. 2.Keep receipt safe.</div>
        </div>
        <?php
    } else {
        ?>
        <div class="slip-preview slip-minimal" style="border: 1px solid #e2e8f0; padding: 20px; background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); color: #0f172a;">
            <div style="text-align: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <h3 style="margin: 0; font-size: 16px; color: #0f172a; font-weight: 800; letter-spacing: -0.5px;">SIAX PUBLIC SCHOOL</h3>
                <div style="font-size: 9px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-top: 4px; letter-spacing: 1px;">STUDENT COPY</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; font-size: 11px;">
                <div>
                    <div style="color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 9px;">Student</div>
                    <div style="font-weight: 800;"><?= $student['name'] ?></div>
                    <div style="color: #64748b;"><?= $student['class'] ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 9px;">Due Date</div>
                    <div style="font-weight: 800;">10 May, 2026</div>
                    <div style="color: #64748b;">Challan #1234</div>
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 15px;">
                <thead>
                    <tr style="border-bottom: 2px solid #0f172a;">
                        <th style="text-align: left; padding: 8px 0; color: #0f172a; font-weight: 800; text-transform: uppercase; font-size: 9px;">Description</th>
                        <th style="text-align: right; padding: 8px 0; color: #0f172a; font-weight: 800; text-transform: uppercase; font-size: 9px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="padding: 8px 0; color: #475569; border-bottom: 1px solid #f1f5f9;"><?= $item['description'] ?></td>
                            <td style="padding: 8px 0; text-align: right; color: #0f172a; font-weight: 600; border-bottom: 1px solid #f1f5f9;">Rs <?= $item['amount'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="padding: 15px 0; font-size: 13px; font-weight: 800;">TOTAL PAYABLE</td>
                        <td style="padding: 15px 0; text-align: right; font-size: 16px; font-weight: 900;">Rs <?= number_format($total) ?></td>
                    </tr>
                </tfoot>
            </table>

            <div style="border-top: 1px dashed #e2e8f0; padding-top: 10px; font-size: 9px; color: #64748b; line-height: 1.4; font-style: italic;">
                Note: This is a computer generated document. Please pay by the due date.
            </div>
        </div>
        <?php
    }
    return ob_get_clean();
}



