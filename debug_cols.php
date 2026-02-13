<?php
require_once(__DIR__ . '/initialize_coreT2.php');
$conn = $db->conn;

function getColumns($table) {
    global $conn;
    $res = $conn->query("SHOW COLUMNS FROM $table");
    $cols = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
    }
    return $cols;
}

echo "email_notifications: " . implode(', ', getColumns('email_notifications')) . "\n";
echo "audit_trial: " . implode(', ', getColumns('audit_trial')) . "\n";
echo "audit_trail: " . implode(', ', getColumns('audit_trail')) . "\n";
?>
