<?php
ob_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');
while (ob_get_level() > 0) ob_end_clean();
ob_start();

$result = $conn->query("DESCRIBE audit_trial");
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "audit_trial table NOT found\n";
}
ob_end_flush();
?>
