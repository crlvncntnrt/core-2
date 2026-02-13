<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$conn = $db->conn;

// Check email_notifications
$res = $conn->query("SHOW COLUMNS FROM email_notifications LIKE 'message'");
if ($res->num_rows == 0) {
    echo "Adding 'message' column to email_notifications...\n";
    $conn->query("ALTER TABLE email_notifications ADD COLUMN message TEXT AFTER subject");
} else {
    echo "'message' column already exists in email_notifications.\n";
}

// Check audit_trial vs audit_trail
$res = $conn->query("SHOW TABLES LIKE 'audit_trial'");
if ($res->num_rows == 0) {
    echo "audit_trial table missing. Checking audit_trail...\n";
    $res = $conn->query("SHOW TABLES LIKE 'audit_trail'");
    if ($res->num_rows > 0) {
        echo "audit_trail exists. Will use that.\n";
    }
}

echo "Schema check complete.\n";
?>
