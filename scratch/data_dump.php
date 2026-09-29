<?php
$conn = new mysqli('localhost', 'root', '', 'siax_smss');
$res = $conn->query("SELECT * FROM student_monthly_fees ORDER BY id DESC LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>



