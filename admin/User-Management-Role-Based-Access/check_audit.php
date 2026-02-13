<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
$result = $conn->query("DESCRIBE audit_trial");
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "audit_trial table NOT found\n";
}
?>
