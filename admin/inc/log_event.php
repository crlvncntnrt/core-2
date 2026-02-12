<?php
// ===========================
// log_event.php
// ===========================
// Shared function to record Compliance + Audit Trail logs
// Include this file wherever you want automatic tracking.

if (!function_exists('record_log')) {
    function record_log($conn, $user_id, $action_type, $module_name, $description, $status = 'Compliant')
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // === Insert to audit_trail (general)
            $stmt1 = $conn->prepare("
                INSERT INTO audit_trail (user_id, action_type, module_name, ip_address, remarks)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt1->bind_param("issss", $user_id, $action_type, $module_name, $ip, $description);
            $stmt1->execute();
            $stmt1->close();

            // === Insert to compliance_logs (specific)
            $stmt2 = $conn->prepare("
                INSERT INTO compliance_logs (user_id, action_type, module_name, description, status, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt2->bind_param("isssss", $user_id, $action_type, $module_name, $description, $status, $ip);
            $stmt2->execute();
            $stmt2->close();

            return true;
        } catch (Throwable $e) {
            error_log("Logging failed: " . $e->getMessage());
            return false;
        }
    }
}
