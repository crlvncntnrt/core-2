<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');

// CHECK AUTHENTICATION - Your system uses $_SESSION['userdata']
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
    error_log("disbursement_action.php - Authentication failed - no userdata in session");
    http_response_code(401);
    echo json_encode([
        'status' => 'error', 
        'msg' => 'Unauthorized - Please login again'
    ]);
    exit;
}

// Update last activity time (for your session timeout system)
if (isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

// Check database connection
if (!isset($conn)) {
    error_log("disbursement_action.php - Database connection not found");
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'msg' => 'Database connection failed'
    ]);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    $disbursementId = $_POST['id'] ?? '';
    
    // Get user ID from your session structure
    $userId = $_SESSION['userdata']['user_id'] ?? 0;
    $userName = $_SESSION['userdata']['full_name'] ?? 'Unknown User';
    
    error_log("disbursement_action.php - Action: {$action}, ID: {$disbursementId}, User: {$userId} ({$userName})");
    
    if (empty($disbursementId)) {
        throw new Exception('Disbursement ID is required');
    }
    
    if ($action === 'approve') {
        // Start transaction for data integrity
        $conn->begin_transaction();
        
        try {
            // Check if disbursement exists and is pending
            $checkStmt = $conn->prepare("SELECT status, loan_id FROM disbursements WHERE disbursement_id = ?");
            if (!$checkStmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $checkStmt->bind_param('s', $disbursementId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                $checkStmt->close();
                throw new Exception('Disbursement not found');
            }
            
            $disbursement = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            if ($disbursement['status'] !== 'Pending') {
                throw new Exception('Only pending disbursements can be approved');
            }
            
            // Check if approved_by column exists
            $columnsResult = $conn->query("SHOW COLUMNS FROM disbursements LIKE 'approved_by'");
            
            if ($columnsResult && $columnsResult->num_rows > 0) {
                // Column exists, update with approved_by
                $updateStmt = $conn->prepare("UPDATE disbursements 
                                             SET status = 'Released', 
                                                 approved_by = ?
                                             WHERE disbursement_id = ?");
                if (!$updateStmt) {
                    throw new Exception("Prepare update failed: " . $conn->error);
                }
                $updateStmt->bind_param('is', $userId, $disbursementId);
            } else {
                // Column doesn't exist, update without approved_by
                $updateStmt = $conn->prepare("UPDATE disbursements 
                                             SET status = 'Released'
                                             WHERE disbursement_id = ?");
                if (!$updateStmt) {
                    throw new Exception("Prepare update failed: " . $conn->error);
                }
                $updateStmt->bind_param('s', $disbursementId);
            }
            
            $result = $updateStmt->execute();
            
            if (!$result) {
                error_log("disbursement_action.php - Update failed: " . $conn->error);
                throw new Exception('Database update failed: ' . $conn->error);
            }
            
            $affectedRows = $updateStmt->affected_rows;
            $updateStmt->close();
            
            if ($affectedRows === 0) {
                throw new Exception('No rows were updated');
            }
            
            // Log the action using your audit system
            if (function_exists('log_audit')) {
                log_audit(
                    $userId,
                    'Approve Disbursement',
                    'Disbursement Tracker',
                    $disbursementId,
                    "User {$userName} approved disbursement #{$disbursementId} for loan #{$disbursement['loan_id']}"
                );
            }
            
            // Commit transaction
            $conn->commit();
            
            error_log("disbursement_action.php - Disbursement {$disbursementId} approved successfully by user {$userId}");
            
            echo json_encode([
                'status' => 'ok', 
                'msg' => 'Disbursement approved successfully'
            ]);
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            throw $e;
        }
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("disbursement_action.php - Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'msg' => $e->getMessage()
    ]);
}
?>