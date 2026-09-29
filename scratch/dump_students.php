<?php
require_once __DIR__ . '/../config/db.php';
$res = $conn->query("DESCRIBE students");
while($row = $res->fetch_assoc()) print_r($row);
