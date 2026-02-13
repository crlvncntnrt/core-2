<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

header('Content-Type: application/json');

$current_user_id = $_SESSION['userdata']['user_id'] ?? 0;
$current_role = $_SESSION['userdata']['role'] ?? '';

// Helper function to send email - made robust to avoid crashing
function sendApprovalEmail($to, $subject, $message) {
    global $conn;
    
    if (empty($to)) return false;
    
    try {
        // First, check if message column exists (safety fallback)
        $checkCol = $conn->query("SHOW COLUMNS FROM email_notifications LIKE 'message'");
        if ($checkCol && $checkCol->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO email_notifications (recipient_email, subject, message, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            if ($stmt) {
                $stmt->bind_param("sss", $to, $subject, $message);
                return $stmt->execute();
            }
        } else {
            // Fallback if column is missing or different
            error_log("Email Notifications Error: Missing 'message' column");
            return false;
        }
    } catch (Exception $e) {
        error_log("sendApprovalEmail Error: " . $e->getMessage());
        return false;
    }
    return false;
}

// Helper function to log activity - aligned with core audit_trail
function logApprovalActivity($action, $module, $ref_id, $details) {
    global $current_user_id;
    if (function_exists('log_audit')) {
        log_audit($current_user_id, $action, $module, $ref_id, $details);
    } else {
        global $conn;
        // Try audit_trail first, then fallback
        $table = 'audit_trail';
        $stmt = $conn->prepare("INSERT INTO $table (user_id, action_type, module_name, remarks, action_time) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            $table = 'audit_trial';
            $stmt = $conn->prepare("INSERT INTO $table (user_id, action_type, module, details, timestamp) VALUES (?, ?, ?, ?, NOW())");
        }
        
        if ($stmt) {
            $stmt->bind_param("isss", $current_user_id, $action, $module, $details);
            $stmt->execute();
            $stmt->close();
        }
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
                'status' => $current_user['status'],
                'phone' => $current_user['phone'] ?? ''
            ]);

            // Rule: Only 1 pending request at a time per user
            $stmt = $conn->prepare("SELECT request_id FROM approval_requests WHERE user_id = ? AND status = 'pending'");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(['status' => 'error', 'msg' => 'You already have a pending request. Please wait for it to be processed.']);
                exit;
            }
            
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
                    'submit_request',
                    'Approval System',
                    $request_id,
                    "Submitted $request_type request for User ID: $target_user_id"
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
            
            $where = "WHERE ar.status = 'pending'";
            if ($current_role === 'Admin') {
                $where .= " AND u.role = 'Staff'";
            } elseif ($current_role === 'Super Admin') {
                $where .= " AND u.role IN ('Admin', 'Staff')";
            } else {
                // Other roles shouldn't see anything, but just in case
                $where .= " AND 1=0";
            }

            $stmt = $conn->prepare("
                SELECT 
                    ar.request_id,
                    ar.request_type,
                    ar.user_id,
                    u.username,
                    u.full_name,
                    u.email,
                    u.role AS u_role,
                    ar.request_data,
                    ar.current_data,
                    ar.status,
                    ar.created_at,
                    rb.full_name as requested_by_name
                FROM approval_requests ar
                LEFT JOIN users u ON ar.user_id = u.user_id
                LEFT JOIN users rb ON ar.requested_by = rb.user_id
                $where
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
            $stmt = $conn->prepare("
                SELECT ar.*, u.email, u.full_name, u.role as target_role 
                FROM approval_requests ar 
                LEFT JOIN users u ON ar.user_id = u.user_id 
                WHERE ar.request_id = ?
            ");
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

            // Rule: Cannot approve your own request
            if ($request['user_id'] == $current_user_id) {
                echo json_encode(['status' => 'error', 'msg' => 'You cannot approve your own request. Please wait for another administrator to review it.']);
                exit;
            }

            // Hierarchical Rule: 
            // - Admin can only approve Staff
            // - Super Admin can approve Admin and Staff
            $target_role = $request['target_role'];
            if ($current_role === 'Admin' && $target_role !== 'Staff') {
                echo json_encode(['status' => 'error', 'msg' => 'Admins can only approve Staff profile changes. Admin changes require Super Admin approval.']);
                exit;
            }
            if ($current_role === 'Super Admin' && !in_array($target_role, ['Admin', 'Staff'])) {
                // Potentially allow other roles too, but for now stick to request
            }
            
            // Parse request data
            $request_data = json_decode($request['request_data'], true);
            
            // Handle different request types
            $user_id = $request['user_id'];
            if ($request['request_type'] === 'termination') {
                // Termination: Deactivate user
                $stmt = $conn->prepare("UPDATE users SET status='Inactive' WHERE user_id=?");
                $stmt->bind_param('i', $user_id);
            } else {
                // Profile Update: Update user record
                $full_name = $request_data['full_name'] ?? $request['full_name'];
                $email = $request_data['email'] ?? $request['email'];
                $phone = $request_data['phone'] ?? '';
                $profile_photo = $request_data['profile_photo'] ?? null;
                
                if ($profile_photo) {
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, profile_photo=? WHERE user_id=?");
                    $stmt->bind_param('ssssi', $full_name, $email, $phone, $profile_photo, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE user_id=?");
                    $stmt->bind_param('sssi', $full_name, $email, $phone, $user_id);
                }
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
                    $subject = $request['request_type'] === 'termination' ? 'Account Termination Approved' : 'Profile Update Approved';
                    $msg_body = $request['request_type'] === 'termination' 
                        ? "Your account termination request has been approved. Your account has been deactivated."
                        : "Your profile update request has been approved by an administrator.";
                    
                    sendApprovalEmail(
                        $request['email'],
                        $subject,
                        "Dear {$request['full_name']},\n\n$msg_body\n\n" . (!empty($review_notes) ? "Review Notes: $review_notes\n\n" : "") . "Thank you!"
                    );
                }
                
                // Log activity
                logApprovalActivity(
                    'approve_request',
                    'Approval System',
                    $request_id,
                    "Approved {$request['request_type']} request for User ID: $user_id"
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
            
            // Rule: Rejection requires a reason
            if (empty($review_notes)) {
                echo json_encode(['status' => 'error', 'msg' => 'A reason for rejection is required.']);
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
                    'Approval System',
                    $request_id,
                    "Rejected {$request['request_type']} request for User ID: {$request['user_id']}. Reason: $review_notes"
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