<?php
/**
 * Log Audit Trail Function
 * Logs user actions to the audit_trail table
 */

if (!function_exists('log_audit_trial')) {
    function log_audit_trial($user_id, $action_type, $module_name = null, $remarks = null, $compliance_status = 'Compliant')
    {
        global $conn;

        // Validate database connection
        if (!$conn || $conn->connect_error) {
            error_log("Audit Trail Error: Database connection not available");
            return false;
        }

        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $action_time = date('Y-m-d H:i:s');

        // Handle user_id = 0 or NULL for system/failed login attempts
        // Set to NULL if user_id is 0 or invalid to avoid foreign key constraint errors
        if ($user_id === 0 || $user_id === '0' || $user_id === null || $user_id === '') {
            $user_id = null;
        } else {
            // Validate that user_id exists in users table
            $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            if ($checkStmt) {
                $checkStmt->bind_param("i", $user_id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                // If user doesn't exist, set to NULL and add note to remarks
                if ($checkResult->num_rows === 0) {
                    $remarks = ($remarks ? $remarks . " | " : "") . "Invalid user_id: " . $user_id;
                    $user_id = null;
                }
                
                $checkStmt->close();
            }
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO audit_trail 
                (user_id, action_type, module_name, ip_address, remarks, compliance_status, review_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                error_log("Audit Trail Error: Failed to prepare statement - " . $conn->error);
                return false;
            }

            $review_date = $action_time; // Use current timestamp as review_date
            
            $stmt->bind_param(
                "issssss",
                $user_id,
                $action_type,
                $module_name,
                $ip_address,
                $remarks,
                $compliance_status,
                $review_date
            );
            
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("Audit Trail Error: Failed to execute - " . $stmt->error);
                $stmt->close();
                return false;
            }
            
            $stmt->close();
            return true;
            
        } catch (Exception $e) {
            error_log("Audit Trail Exception: " . $e->getMessage());
            return false;
        }
    }
}
?>