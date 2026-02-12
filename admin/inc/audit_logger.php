<?php
// ======================================
// audit_logger.php
// Global Audit / Compliance Logger
// ======================================

if (!function_exists('log_compliance_action')) {
    /**
     * Insert a compliance log entry.
     *
     * @param mysqli $conn       Database connection
     * @param int|null $user_id  User performing the action
     * @param string $action_type  Action performed (e.g. "Login", "Add Record")
     * @param string $module_name  Module name (e.g. "User Management")
     * @param string $description  Details about the action
     * @param string $status       'Compliant', 'Non-Compliant', or 'Pending'
     * @return bool
     */
    function log_compliance_action($conn, $user_id, $action_type, $module_name, $description = '', $status = 'Pending')
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $stmt = $conn->prepare("
                INSERT INTO compliance_logs (user_id, action_type, module_name, description, status, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssss", $user_id, $action_type, $module_name, $description, $status, $ip);
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Throwable $e) {
            error_log("Audit Logger Error: " . $e->getMessage());
            return false;
        }
    }
}
