<?php
// ==================================================
// Compliance Logger Helper
// ==================================================

if (!function_exists('log_compliance')) {
    function log_compliance($user_id, $action_type, $module_name, $description, $status = 'Pending')
    {
        global $conn;

        if (!$conn) {
            error_log("Compliance Log Error: No DB connection");
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        $stmt = $conn->prepare("
            INSERT INTO compliance_logs (user_id, action_type, module_name, description, status, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("Compliance Log Prepare Failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param("isssss", $user_id, $action_type, $module_name, $description, $status, $ip);
        $stmt->execute();

        $stmt->close();
        return true;
    }
}

// Optional wrapper to match old calls
if (!function_exists('log_compliance_event')) {
    function log_compliance_event($user_id, $action_type, $module_name, $description, $status = 'Pending')
    {
        return log_compliance($user_id, $action_type, $module_name, $description, $status);
    }
}
