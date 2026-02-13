<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$conn = $db->conn;

$tables = ['email_notifications', 'approval_requests', 'audit_trial', 'audit_trail'];

foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
?>
