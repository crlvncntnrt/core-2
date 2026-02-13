<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
$result = $conn->query("SHOW TABLES LIKE 'approval_requests'");
if ($result->num_rows > 0) {
    echo "approval_requests exists\n";
} else {
    echo "approval_requests DOES NOT exist\n";
}
$result = $conn->query("SHOW TABLES LIKE 'approval_notifications'");
if ($result->num_rows > 0) {
    echo "approval_notifications exists\n";
} else {
    echo "approval_notifications DOES NOT exist\n";
}
?>
