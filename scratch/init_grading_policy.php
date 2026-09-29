<?php
$conn = new mysqli('localhost', 'root', '', 'siax_smss');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create table if not exists
$sql = "CREATE TABLE IF NOT EXISTS `grading_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "Table grading_policies exists or created successfully.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$sql2 = "CREATE TABLE IF NOT EXISTS `grading_policy_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `policy_id` int(11) NOT NULL,
  `grade_letter` varchar(10) NOT NULL,
  `min_percent` decimal(5,2) NOT NULL,
  `max_percent` decimal(5,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`policy_id`) REFERENCES `grading_policies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql2)) {
    echo "Table grading_policy_details exists or created successfully.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Insert generic if none
$res = $conn->query("SELECT id FROM grading_policies LIMIT 1");
if ($res && $res->num_rows == 0) {
    $conn->query("INSERT INTO grading_policies (title, is_default) VALUES ('Generic Grading Policy', 1)");
    echo "Inserted default policy.\n";
}
?>
