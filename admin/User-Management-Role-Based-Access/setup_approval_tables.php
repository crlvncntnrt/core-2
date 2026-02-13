<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');

$queries = [
    "CREATE TABLE IF NOT EXISTS approval_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        request_type VARCHAR(50) NOT NULL,
        request_data TEXT,
        current_data TEXT,
        requested_by INT,
        status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
        review_notes TEXT,
        reviewed_by INT,
        reviewed_at DATETIME,
        created_at DATETIME
    )",
    "CREATE TABLE IF NOT EXISTS approval_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        recipient_id INT NOT NULL,
        is_read TINYINT DEFAULT 0,
        read_at DATETIME,
        created_at DATETIME
    )",
    "CREATE TABLE IF NOT EXISTS email_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT,
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        created_at DATETIME
    )"
];

foreach ($queries as $q) {
    if (!$conn->query($q)) {
        die("Error creating table: " . $conn->error);
    }
}

echo "Tables checked/created successfully.";
?>
