<?php
$_SERVER['DOCUMENT_ROOT'] = 'E:/Xammp/htdocs';
$_SERVER['SERVER_ADDR'] = '192.168.100.15';
$_SERVER['SERVER_PORT'] = '8080';

require_once __DIR__ . '/../config/db.php';
echo "Campus LAN URL: " . get_campus_base_url() . "\n";
