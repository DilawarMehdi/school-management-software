<?php
require 'e:\Xammp\htdocs\siax-smss\config\db.php';
$res = $conn->query("DESCRIBE marks");
while($row = $res->fetch_assoc()) print_r($row);
