<?php
require_once __DIR__ . '/../config/db.php';
$res = $conn->query("DESCRIBE notifications");
while($row = $res->fetch_assoc()) print_r($row);
