<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

header('Content-Type: application/json');

$current_user_id = $_SESSION['userdata']['user_id'] ?? 0;
$current_role = $_SESSION['userdata']['role'] ?? '';

// Helper function to send email
function sendApprovalEmail($to, $subject, $message) {
    global $conn;
    
    if (empty($to)) return false;
    
    // Insert into email_notifications table
    $stmt = $conn->prepare("INSERT INTO email_notifications (recipient_email, subject, message, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("sss", $to, $subject, $message);
    return $stmt->execute();
}

// Helper function to log activity (simplified - compatible with your audit_trail)
function logApprovalActivity($action, $details) {
    global $conn, $current_user_id;
    
    // Check if audit_trail table exists and has basic columns
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, action, details, timestamp) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("iss", $current_user_id, $action, $details);
        $stmt->execute();
    }
}

// Helper function to notify all admins
function notifyAdmins($request_id, $message) {
    global $conn;
    
    // Get all Super Admin and Admin users
    $stmt = $conn->prepare("SELECT user_id, email FROM users WHERE role IN ('Super Admin', 'Admin') AND status = 'Active'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notified = 0;
    while ($admin = $result->fetch_assoc()) {
        // Insert notification
        $notif_stmt = $conn->prepare("INSERT INTO approval_notifications (request_id, recipient_id, created_at) VALUES (?, ?, NOW())");
        $notif_stmt->bind_param("ii", $request_id, $admin['user_id']);
        if ($notif_stmt->execute()) {
            $notified++;
            
            // Send email notification to admin
            if (!empty($admin['email'])) {
                sendApprovalEmail(
                    $admin['email'],
                    'New Approval Request',
                    $message
                );
            }
        }
    }
    
    return $notified;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ============================================
        // SUBMIT APPROVAL REQUEST
        // ============================================
        case 'submit_request':
            $target_user_id = $_POST['user_id'] ?? null;
            $request_type = $_POST['request_type'] ?? 'profile_update';
            $request_data = $_POST['request_data'] ?? '{}';
            
            if (!$target_user_id) {
                echo json_encode(['status' => 'error', 'msg' => 'User ID required']);
                exit;
            }
            
            // Get current user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $current_user = $stmt->get_result()->fetch_assoc();
            
            if (!$current_user) {
                echo json_encode(['status' => 'error', 'msg' => 'User not found']);
                exit;
            }
            
            $current_data = json_encode([
                'username' => $current_user['username'],
                'full_name' => $current_user['full_name'],
                'email' => $current_user['email'],
                'role' => $current_user['role'],
                'status' => $current_user['status']
            ]);
            
            // Insert approval request
            $stmt = $conn->prepare("INSERT INTO approval_requests (user_id, request_type, request_data, current_data, requested_by, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("isssi", $target_user_id, $request_type, $request_data, $current_data, $current_user_id);
            
            if ($stmt->execute()) {
                $request_id = $conn->insert_id;
                
                // Notify all admins
                $notif_message = "A new profile update approval request has been submitted for user: {$current_user['full_name']} ({$current_user['username']})";
                $notified_count = notifyAdmins($request_id, $notif_message);
                
                // Log activity
                logApprovalActivity(
                    'submit_approval_request',
                    "Submitted approval request for user ID: $target_user_id, Request ID: $request_id, Type: $request_type"
                );
                
                // Send email to user
                if ($current_user['email']) {
                    sendApprovalEmail(
                        $current_user['email'],
                        'Profile Update Request Submitted',
                        "Dear {$current_user['full_name']},\n\nYour profile update request has been submitted for approval. You will be notified once an administrator reviews your request.\n\nThank you!"
                    );
                }
                
                echo json_encode([
                    'status' => 'success',
                    'msg' => "Approval request submitted successfully. $notified_count admin(s) notified.",
                    'request_id' => $request_id
                ]);
            } else {
                throw new Exception("Failed to submit request: " . $conn->error);
            }
            break;
        
        // ============================================
        // GET PENDING APPROVALS (Admin/Super Admin only)
        // ============================================
        case 'get_pending':
            // Only admins can view pending approvals
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
                exit;
            }
            
            $stmt = $conn->prepare("
                SELECT 
                    ar.request_id,
                    ar.request_type,
                    ar.user_id,
                    u.username,
                    u.full_name,
                    u.email,
                    u.role AS current_role,
                    ar.request_data,
                    ar.current_data,
                    ar.status,
                    ar.created_at,
                    rb.full_name as requested_by_name
                FROM approval_requests ar
                LEFT JOIN users u ON ar.user_id = u.user_id
                LEFT JOIN users rb ON ar.requested_by = rb.user_id
                WHERE ar.status = 'pending'
                ORDER BY ar.created_at DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $row['request_data_parsed'] = json_decode($row['request_data'], true);
                $row['current_data_parsed'] = json_decode($row['current_data'], true);
                $requests[] = $row;
            }
            
            echo json_encode(['status' => 'success', 'requests' => $requests]);
            break;
        
        // ============================================
        // APPROVE REQUEST
        // ============================================
        case 'approve':
            $request_id = $_POST['request_id'] ?? null;
            $review_notes = $_POST['review_notes'] ?? '';
            
            if (!$request_id) {
                echo json_encode(['status' => 'error', 'msg' => 'Request ID required']);
                exit;
            }
            
            // Check if user is admin
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                echo json_encode(['status' => 'error', 'msg' => 'Only admins can approve requests']);
                exit;
            }
            
            // Get request details
            $stmt = $conn->prepare("SELECT ar.*, u.email, u.full_name FROM approval_requests ar LEFT JOIN users u ON ar.user_id = u.user_id WHERE ar.request_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            
            if (!$request) {
                echo json_encode(['status' => 'error', 'msg' => 'Request not found']);
                exit;
            }
            
            if ($request['status'] !== 'pending') {
                echo json_encode(['status' => 'error', 'msg' => 'This request has already been processed']);
                exit;
            }
            
            // Parse request data
            $request_data = json_decode($request['request_data'], true);
            
            // Update user record
            $user_id = $request['user_id'];
            $username = $request_data['username'] ?? '';
            $full_name = $request_data['full_name'] ?? '';
            $email = $request_data['email'] ?? '';
            $role = $request_data['role'] ?? '';
            $status = $request_data['status'] ?? '';
            
            // Check if password update was requested
            if (isset($request_data['password']) && !empty($request_data['password'])) {
                $password_hash = password_hash($request_data['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username=?, password_hash=?, full_name=?, email=?, role=?, status=? WHERE user_id=?");
                $stmt->bind_param('ssssssi', $username, $password_hash, $full_name, $email, $role, $status, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, status=? WHERE user_id=?");
                $stmt->bind_param('sssssi', $username, $full_name, $email, $role, $status, $user_id);
            }
            
            if ($stmt->execute()) {
                // Update approval request status
                $stmt = $conn->prepare("UPDATE approval_requests SET status='approved', reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE request_id=?");
                $stmt->bind_param("isi", $current_user_id, $review_notes, $request_id);
                $stmt->execute();
                
                // Mark notifications as read
                $stmt = $conn->prepare("UPDATE approval_notifications SET is_read=1, read_at=NOW() WHERE request_id=?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                
                // Send email confirmation to user
                if ($request['email']) {
                    sendApprovalEmail(
                        $request['email'],
                        'Profile Update Approved',
                        "Dear {$request['full_name']},\n\nYour profile update request has been approved by an administrator.\n\n" . (!empty($review_notes) ? "Review Notes: $review_notes\n\n" : "") . "Thank you!"
                    );
                }
                
                // Log activity
                logApprovalActivity(
                    'approve_request',
                    "Approved request ID: $request_id for user ID: $user_id"
                );
                
                echo json_encode(['status' => 'success', 'msg' => 'Request approved and user updated successfully']);
            } else {
                throw new Exception("Failed to update user: " . $conn->error);
            }
            break;
        
        // ============================================
        // REJECT REQUEST
        // ============================================
        case 'reject':
            $request_id = $_POST['request_id'] ?? null;
            $review_notes = $_POST['review_notes'] ?? '';
            
            if (!$request_id) {
                echo json_encode(['status' => 'error', 'msg' => 'Request ID required']);
                exit;
            }
            
            // Check if user is admin
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                echo json_encode(['status' => 'error', 'msg' => 'Only admins can reject requests']);
                exit;
            }
            
            // Get request details
            $stmt = $conn->prepare("SELECT ar.*, u.email, u.full_name FROM approval_requests ar LEFT JOIN users u ON ar.user_id = u.user_id WHERE ar.request_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            
            if (!$request) {
                echo json_encode(['status' => 'error', 'msg' => 'Request not found']);
                exit;
            }
            
            if ($request['status'] !== 'pending') {
                echo json_encode(['status' => 'error', 'msg' => 'This request has already been processed']);
                exit;
            }
            
            // Update approval request status
            $stmt = $conn->prepare("UPDATE approval_requests SET status='rejected', reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE request_id=?");
            $stmt->bind_param("isi", $current_user_id, $review_notes, $request_id);
            
            if ($stmt->execute()) {
                // Mark notifications as read
                $stmt = $conn->prepare("UPDATE approval_notifications SET is_read=1, read_at=NOW() WHERE request_id=?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                
                // Send email notification to user
                if ($request['email']) {
                    sendApprovalEmail(
                        $request['email'],
                        'Profile Update Rejected',
                        "Dear {$request['full_name']},\n\nYour profile update request has been rejected by an administrator.\n\n" . (!empty($review_notes) ? "Reason: $review_notes\n\n" : "") . "Please contact your administrator for more information.\n\nThank you!"
                    );
                }
                
                // Log activity
                logApprovalActivity(
                    'reject_request',
                    "Rejected request ID: $request_id for user ID: {$request['user_id']}"
                );
                
                echo json_encode(['status' => 'success', 'msg' => 'Request rejected successfully']);
            } else {
                throw new Exception("Failed to reject request: " . $conn->error);
            }
            break;
        
        // ============================================
        // GET NOTIFICATION COUNT (for badge)
        // ============================================
        case 'get_notification_count':
            if (!in_array($current_role, ['Super Admin', 'Admin'])) {
                echo json_encode(['status' => 'success', 'count' => 0]);
                exit;
            }
            
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM approval_notifications 
                WHERE recipient_id = ? AND is_read = 0
            ");
            $stmt->bind_param("i", $current_user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            echo json_encode(['status' => 'success', 'count' => $result['count']]);
            break;
        
        default:
            echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Approval Action Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'An error occurred: ' . $e->getMessage()]);
}