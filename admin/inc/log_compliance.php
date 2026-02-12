<?php
// ====================================================
// log_compliance.php
// Shared function to record compliance & audit events
// ====================================================

if (!function_exists('log_compliance')) {
    function log_compliance($conn, $module, $action, $remarks = '', $user_id = null)
    {
        try {
            // Ensure DB connection exists
            if (!$conn) return false;

            // Start session if not already active
            if (session_status() === PHP_SESSION_NONE) session_start();

            // Get current user if not provided
            if ($user_id === null && isset($_SESSION['userdata']['user_id'])) {
                $user_id = $_SESSION['userdata']['user_id'];
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $stmt = $conn->prepare("
                INSERT INTO audit_trail 
                (user_id, action_type, module_name, remarks, ip_address, action_time)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issss", $user_id, $action, $module, $remarks, $ip);
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log('Compliance Log Error: ' . $e->getMessage());
            return false;
        }
    }
}
