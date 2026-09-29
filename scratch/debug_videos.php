<?php
require_once __DIR__ . '/../config/db.php';

echo "=== DB VIDEO_RESOURCES TABLE ===\n";
$r = $conn->query("SELECT * FROM video_resources");
if ($r) {
    while($row = $r->fetch_assoc()) {
        echo "ID: {$row['id']} | Title: {$row['title']} | File: {$row['file_name']} | Featured: {$row['is_featured']}\n";
    }
} else {
    echo "Query error or table missing: " . $conn->error . "\n";
}

echo "\n=== FILES IN VIDEOS FOLDER ===\n";
$video_dir = __DIR__ . '/../videos/';
if (is_dir($video_dir)) {
    $files = scandir($video_dir);
    foreach($files as $f) {
        if ($f !== '.' && $f !== '..') {
            echo "File: $f (" . filesize($video_dir . $f) . " bytes)\n";
        }
    }
} else {
    echo "videos/ directory does not exist!\n";
}
