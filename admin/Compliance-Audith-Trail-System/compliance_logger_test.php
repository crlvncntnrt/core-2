<?php
require_once(__DIR__ . '/../../initialize_coreT2.php'); // load core + audit logger

if (session_status() === PHP_SESSION_NONE) session_start();

// For testing only — assume user_id = current logged in user
$user_id = $_SESSION['userdata']['user_id'] ?? 1;

// Example: log compliance action
try {
    $action_type = "Test Log Entry";
    $module_name = "Compliance Test Module";
    $description = "This is a test to verify compliance log functionality.";

    // ✅ call the compliance logger function
    log_compliance_action($user_id, $action_type, $module_name, $description, "Super Admin");

    echo "<h3 style='color:green;'>✅ Compliance log successfully recorded!</h3>";
    echo "<p>Check your <b>compliance_logs</b> table in phpMyAdmin.</p>";
} catch (Throwable $e) {
    echo "<h3 style='color:red;'>❌ Error:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
