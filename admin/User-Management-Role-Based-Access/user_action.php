<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

function json_out($arr)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// Sanitize input to prevent XSS
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$current_user_id = $_SESSION['userdata']['user_id'] ?? 0;
$current_role = $_SESSION['userdata']['role'] ?? 'Member';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Valid roles - matches your database ENUM (includes all 5 roles)
$valid_roles = ['Super Admin', 'Admin', 'Staff', 'Client', 'Distributor'];

switch ($action) {
    case 'list':
        $search = trim($_POST['search'] ?? $_GET['search'] ?? '');
        
        try {
            if ($search !== '') {
                $stmt = $conn->prepare("SELECT user_id, username, full_name, email, role, status, date_created FROM users WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ? OR user_id LIKE ? ORDER BY user_id DESC");
                $like = "%$search%";
                $stmt->bind_param('ssss', $like, $like, $like, $like);
            } else {
                $stmt = $conn->prepare("SELECT user_id, username, full_name, email, role, status, date_created FROM users ORDER BY user_id DESC");
            }
            
            $stmt->execute();
            $res = $stmt->get_result();
            $users = [];
            $active = 0;
            $inactive = 0;
            $role_counts = [
                'Super Admin' => 0,
                'Admin' => 0,
                'Staff' => 0,
                'Member' => 0,
                'Client' => 0
            ];
            
            while ($row = $res->fetch_assoc()) {
                // Format date
                $row['date_created'] = date('Y-m-d H:i:s', strtotime($row['date_created']));
                $users[] = $row;
                
                // Count by status
                if ($row['status'] === 'Active') $active++;
                else $inactive++;
                
                // Count by role
                if (isset($role_counts[$row['role']])) {
                    $role_counts[$row['role']]++;
                }
            }
            $stmt->close();
            
            json_out([
                'status' => 'success',
                'users' => $users,
                'total' => count($users),
                'active_count' => $active,
                'inactive_count' => $inactive,
                'role_counts' => $role_counts
            ]);
        } catch (Exception $e) {
            error_log("Error in list users: " . $e->getMessage());
            json_out(['status' => 'error', 'msg' => 'Failed to load users: ' . $e->getMessage()]);
        }
        break;

    case 'get':
        $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) json_out(['status' => 'error', 'msg' => 'Invalid user ID']);
        
        try {
            $stmt = $conn->prepare("SELECT user_id, username, full_name, email, role, status, date_created FROM users WHERE user_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$user) json_out(['status' => 'error', 'msg' => 'User not found']);
            
            json_out($user);
        } catch (Exception $e) {
            error_log("Error getting user: " . $e->getMessage());
            json_out(['status' => 'error', 'msg' => 'Failed to retrieve user: ' . $e->getMessage()]);
        }
        break;

    case 'add':
    case 'edit':
        $id = intval($_POST['user_id'] ?? 0);
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // Don't sanitize password
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $role = sanitize_input($_POST['role'] ?? '');
        $status = sanitize_input($_POST['status'] ?? '');
        
        // Normalize status
        if ($status === 'on' || $status === '1' || $status === 'Active') {
            $status = 'Active';
        } else {
            $status = 'Inactive';
        }
        
        // Validation
        if (empty($username)) {
            json_out(['status' => 'error', 'msg' => 'Username is required']);
        }
        if (strlen($username) < 3) {
            json_out(['status' => 'error', 'msg' => 'Username must be at least 3 characters']);
        }
        if (empty($full_name)) {
            json_out(['status' => 'error', 'msg' => 'Full name is required']);
        }
        if (!in_array($role, $valid_roles)) {
            json_out(['status' => 'error', 'msg' => 'Invalid role selected']);
        }
        
        // Email validation (if provided)
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(['status' => 'error', 'msg' => 'Invalid email format']);
        }

        try {
            if ($action === 'add') {
                // Validate password for new users
                if (empty($password)) {
                    json_out(['status' => 'error', 'msg' => 'Password is required for new users']);
                }
                if (strlen($password) < 6) {
                    json_out(['status' => 'error', 'msg' => 'Password must be at least 6 characters']);
                }
                
                // Check if username exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username=?");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) {
                    $stmt->close();
                    json_out(['status' => 'error', 'msg' => 'Username already exists']);
                }
                $stmt->close();
                
                // Check if email exists (if provided)
                if (!empty($email)) {
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? AND email != ''");
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()) {
                        $stmt->close();
                        json_out(['status' => 'error', 'msg' => 'Email already exists']);
                    }
                    $stmt->close();
                }
                
                // Get the next available user_id (WORKAROUND for missing AUTO_INCREMENT)
                $stmt = $conn->prepare("SELECT MAX(user_id) as max_id FROM users");
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $next_id = ($result['max_id'] ?? 0) + 1;
                $stmt->close();
                
                // Hash password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                // role_id set to NULL
                $role_id = null;
                
                // Insert new user WITH user_id specified
                $stmt = $conn->prepare("INSERT INTO users (user_id, role_id, username, password_hash, full_name, email, role, status, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param('iissssss', $next_id, $role_id, $username, $hash, $full_name, $email, $role, $status);
                $ok = $stmt->execute();
                
                if (!$ok) {
                    $error = $stmt->error;
                    $stmt->close();
                    error_log("Database error on user insert: " . $error);
                    json_out(['status' => 'error', 'msg' => 'Database error: ' . $error]);
                }
                
                $new_id = $next_id;
                $stmt->close();
                
                if ($ok) {
                    // Log activity
                    error_log("User created: ID $new_id, Username: $username by User ID: $current_user_id");
                    json_out(['status' => 'success', 'msg' => 'User added successfully', 'user_id' => $new_id]);
                } else {
                    json_out(['status' => 'error', 'msg' => 'Failed to add user']);
                }
                
            } else {
                // Edit existing user
                if ($id <= 0) {
                    json_out(['status' => 'error', 'msg' => 'Invalid user ID']);
                }
                
                // Prevent editing own profile if not Super Admin (force use of approval system)
                if ($id == $current_user_id && $current_role !== 'Super Admin') {
                    json_out(['status' => 'error', 'msg' => 'Please use the "Edit Profile" option in your account menu to update your information. Profile changes require administrator approval.']);
                }
                
                // Extra check for existing role/status protection if it was a Super Admin (redundant but safe)
                if ($id == $current_user_id && $current_role === 'Super Admin') {
                    $stmt = $conn->prepare("SELECT role, status FROM users WHERE user_id=?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $current_data = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if ($current_data['role'] !== $role) {
                        json_out(['status' => 'error', 'msg' => 'You cannot change your own role']);
                    }
                    if ($current_data['status'] !== $status) {
                        json_out(['status' => 'error', 'msg' => 'You cannot change your own status']);
                    }
                }
                
                // Check if username is taken by another user
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username=? AND user_id!=?");
                $stmt->bind_param('si', $username, $id);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) {
                    $stmt->close();
                    json_out(['status' => 'error', 'msg' => 'Username already used by another user']);
                }
                $stmt->close();
                
                // Check if email is taken by another user (if provided)
                if (!empty($email)) {
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? AND user_id!=? AND email != ''");
                    $stmt->bind_param('si', $email, $id);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()) {
                        $stmt->close();
                        json_out(['status' => 'error', 'msg' => 'Email already used by another user']);
                    }
                    $stmt->close();
                }
                
                // Update user
                if (!empty($password)) {
                    // Password change requested
                    if (strlen($password) < 6) {
                        json_out(['status' => 'error', 'msg' => 'Password must be at least 6 characters']);
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username=?, password_hash=?, full_name=?, email=?, role=?, status=? WHERE user_id=?");
                    $stmt->bind_param('ssssssi', $username, $hash, $full_name, $email, $role, $status, $id);
                } else {
                    // No password change
                    $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, status=? WHERE user_id=?");
                    $stmt->bind_param('sssssi', $username, $full_name, $email, $role, $status, $id);
                }
                
                $ok = $stmt->execute();
                
                if (!$ok) {
                    $error = $stmt->error;
                    $stmt->close();
                    error_log("Database error on user update: " . $error);
                    json_out(['status' => 'error', 'msg' => 'Database error: ' . $error]);
                }
                
                $stmt->close();
                
                if ($ok) {
                    // Log activity
                    error_log("User updated: ID $id, Username: $username by User ID: $current_user_id");
                    json_out(['status' => 'success', 'msg' => 'User updated successfully']);
                } else {
                    json_out(['status' => 'error', 'msg' => 'Failed to update user']);
                }
            }
        } catch (Exception $e) {
            error_log("Error in add/edit user: " . $e->getMessage());
            json_out(['status' => 'error', 'msg' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) json_out(['status' => 'error', 'msg' => 'Invalid user ID']);
        
        // Prevent self-deletion / Force deactivation via approval for others
        if ($id == $current_user_id) {
            json_out(['status' => 'error', 'msg' => 'You cannot delete your own account']);
        }
        
        try {
            // Get user info before deletion for logging
            $stmt = $conn->prepare("SELECT username, role FROM users WHERE user_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $user_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$user_info) {
                json_out(['status' => 'error', 'msg' => 'User not found']);
            }
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            
            if ($ok) {
                // Log activity
                error_log("User deleted: ID $id, Username: {$user_info['username']} by User ID: $current_user_id");
                json_out(['status' => 'success', 'msg' => 'User deleted successfully']);
            } else {
                json_out(['status' => 'error', 'msg' => 'Failed to delete user']);
            }
        } catch (Exception $e) {
            error_log("Error deleting user: " . $e->getMessage());
            json_out(['status' => 'error', 'msg' => 'Failed to delete user: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_status':
        $id = intval($_POST['id'] ?? 0);
        $status = sanitize_input($_POST['status'] ?? 'Inactive');
        
        if ($id <= 0) json_out(['status' => 'error', 'msg' => 'Invalid user ID']);
        
        // Normalize status
        if ($status !== 'Active' && $status !== 'Inactive') {
            $status = 'Inactive';
        }
        
        // Prevent changing own status
        if ($id == $current_user_id) {
            json_out(['status' => 'error', 'msg' => 'You cannot change your own status']);
        }
        
        try {
            $stmt = $conn->prepare("UPDATE users SET status=? WHERE user_id=?");
            $stmt->bind_param('si', $status, $id);
            $ok = $stmt->execute();
            $stmt->close();
            
            if ($ok) {
                // Log activity
                error_log("User status changed: ID $id to $status by User ID: $current_user_id");
                $action_word = $status === 'Active' ? 'activated' : 'deactivated';
                json_out(['status' => 'success', 'msg' => "User $action_word successfully"]);
            } else {
                json_out(['status' => 'error', 'msg' => 'Failed to update status']);
            }
        } catch (Exception $e) {
            error_log("Error toggling status: " . $e->getMessage());
            json_out(['status' => 'error', 'msg' => 'Failed to update status: ' . $e->getMessage()]);
        }
        break;

    default:
        json_out(['status' => 'error', 'msg' => 'Invalid action']);
}