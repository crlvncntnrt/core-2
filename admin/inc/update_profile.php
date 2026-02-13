<?php
// ===========================
// update_profile_direct.php
// Direct profile update for Super Admin (bypasses approval)
// ===========================

session_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check authentication
if (!isset($_SESSION['userdata']['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not authenticated']);
    exit;
}

// Check if Super Admin
if ($_SESSION['userdata']['role'] !== 'Super Admin') {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized. Only Super Admin can directly update profiles.']);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

$user_id = intval($data['user_id'] ?? 0);
$full_name = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');

// Validation
if (!$user_id || $full_name === '' || $email === '') {
    echo json_encode(['status' => 'error', 'msg' => 'User ID, Full Name and Email are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid email format']);
    exit;
}

try {
    $db = new DBConnection();
    
    // Check if email is already taken by another user
    $stmt = $db->conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param('si', $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'msg' => 'Email address is already in use by another user']);
        exit;
    }
    $stmt->close();
    
    // Update user profile
    $stmt = $db->conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
    $stmt->bind_param('sssi', $full_name, $email, $phone, $user_id);
    
    if ($stmt->execute()) {
        // Update session if updating own profile
        if ($user_id == $_SESSION['userdata']['user_id']) {
            $_SESSION['userdata']['full_name'] = $full_name;
            $_SESSION['userdata']['email'] = $email;
        }
        
        error_log("Super Admin direct profile update for user $user_id");
        
        echo json_encode([
            'status' => 'success',
            'msg' => 'Profile updated successfully'
        ]);
    } else {
        error_log("Database error updating user $user_id: " . $stmt->error);
        echo json_encode(['status' => 'error', 'msg' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Exception updating profile for user $user_id: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'Error: ' . $e->getMessage()]);
}
?>