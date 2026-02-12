<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');

header('Content-Type: application/json');

// ============================================
// Security: Check if user is Super Admin
// ============================================
$user_id = $_SESSION['userdata']['user_id'] ?? 0;
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$user_role = $user['role'] ?? '';
if ($user_role !== 'Super Admin') {
    echo json_encode([
        'success' => false,
        'title' => '🚫 Access Denied!',
        'message' => 'You don\'t have permission to manage Role Permissions.',
        'details' => 'This module is restricted to Super Admin only.',
        'instruction' => 'Please contact your system administrator if you need access.'
    ]);
    exit();
}

// ============================================
// Get action from POST request
// ============================================
$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ---------------- LIST PERMISSIONS ----------------
        case 'list':
            $query = "
                SELECT 
                    rp.perm_id,
                    rp.role_id,
                    ur.role_name,
                    rp.module_name,
                    rp.can_view,
                    rp.can_add,
                    rp.can_edit,
                    rp.can_delete
                FROM role_permissions rp
                INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                ORDER BY ur.role_name ASC, rp.module_name ASC
            ";
            $res = $conn->query($query);
            
            if (!$res) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode($data);
            break;

        // ---------------- GET SINGLE PERMISSION ----------------
        case 'get':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Permission ID is required'
                ]);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT 
                    rp.perm_id,
                    rp.role_id,
                    ur.role_name,
                    rp.module_name,
                    rp.can_view,
                    rp.can_add,
                    rp.can_edit,
                    rp.can_delete
                FROM role_permissions rp
                INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE rp.perm_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            
            if (!$data) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Permission not found'
                ]);
                exit;
            }
            
            echo json_encode($data);
            break;

        // ---------------- ADD PERMISSION ----------------
        case 'add':
            $role_id = intval($_POST['role_id'] ?? 0);
            $module = trim($_POST['module'] ?? '');
            $can_view = intval($_POST['can_view'] ?? 0);
            $can_add = intval($_POST['can_add'] ?? 0);
            $can_edit = intval($_POST['can_edit'] ?? 0);
            $can_delete = intval($_POST['can_delete'] ?? 0);

            // Validation
            if (!$role_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please select a role'
                ]);
                exit;
            }

            if (empty($module)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Module name is required'
                ]);
                exit;
            }

            // Check if role exists
            $stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE role_id = ?");
            $stmt->bind_param("i", $role_id);
            $stmt->execute();
            $role = $stmt->get_result()->fetch_assoc();
            
            if (!$role) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Selected role does not exist'
                ]);
                exit;
            }

            // Check for duplicate (same role + module)
            $stmt = $conn->prepare("
                SELECT perm_id 
                FROM role_permissions 
                WHERE role_id = ? AND module_name = ?
            ");
            $stmt->bind_param("is", $role_id, $module);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            
            if ($existing) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This permission already exists for this role and module'
                ]);
                exit;
            }

            // Insert permission
            $stmt = $conn->prepare("
                INSERT INTO role_permissions 
                (role_id, module_name, action_name, can_view, can_add, can_edit, can_delete)
                VALUES (?, ?, '', ?, ?, ?, ?)
            ");
            $stmt->bind_param("isiiii", $role_id, $module, $can_view, $can_add, $can_edit, $can_delete);
            
            if ($stmt->execute()) {
                // Log the action
                logPermissionAction($conn, $user_id, 'Add', "Added permission for module: $module");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Permission added successfully',
                    'perm_id' => $conn->insert_id
                ]);
            } else {
                throw new Exception("Failed to add permission: " . $stmt->error);
            }
            break;

        // ---------------- EDIT PERMISSION ----------------
        case 'edit':
            $perm_id = intval($_POST['permission_id'] ?? 0);
            $role_id = intval($_POST['role_id'] ?? 0);
            $module = trim($_POST['module'] ?? '');
            $can_view = intval($_POST['can_view'] ?? 0);
            $can_add = intval($_POST['can_add'] ?? 0);
            $can_edit = intval($_POST['can_edit'] ?? 0);
            $can_delete = intval($_POST['can_delete'] ?? 0);

            // Validation
            if (!$perm_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Permission ID is required'
                ]);
                exit;
            }

            if (!$role_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please select a role'
                ]);
                exit;
            }

            if (empty($module)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Module name is required'
                ]);
                exit;
            }

            // Check if permission exists
            $stmt = $conn->prepare("SELECT perm_id FROM role_permissions WHERE perm_id = ?");
            $stmt->bind_param("i", $perm_id);
            $stmt->execute();
            
            if (!$stmt->get_result()->fetch_assoc()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Permission not found'
                ]);
                exit;
            }

            // Check for duplicate (excluding current record)
            $stmt = $conn->prepare("
                SELECT perm_id 
                FROM role_permissions 
                WHERE role_id = ? AND module_name = ? AND perm_id != ?
            ");
            $stmt->bind_param("isi", $role_id, $module, $perm_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            
            if ($existing) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Another permission already exists for this role and module'
                ]);
                exit;
            }

            // Update permission
            $stmt = $conn->prepare("
                UPDATE role_permissions
                SET role_id = ?, 
                    module_name = ?, 
                    can_view = ?, 
                    can_add = ?, 
                    can_edit = ?, 
                    can_delete = ?
                WHERE perm_id = ?
            ");
            $stmt->bind_param("isiiiii", $role_id, $module, $can_view, $can_add, $can_edit, $can_delete, $perm_id);
            
            if ($stmt->execute()) {
                // Log the action
                logPermissionAction($conn, $user_id, 'Edit', "Updated permission for module: $module");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Permission updated successfully'
                ]);
            } else {
                throw new Exception("Failed to update permission: " . $stmt->error);
            }
            break;

        // ---------------- DELETE PERMISSION ----------------
        case 'delete':
            $perm_id = intval($_POST['id'] ?? 0);
            
            if (!$perm_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Permission ID is required'
                ]);
                exit;
            }

            // Get permission details before deleting (for logging)
            $stmt = $conn->prepare("
                SELECT rp.module_name, ur.role_name
                FROM role_permissions rp
                INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE rp.perm_id = ?
            ");
            $stmt->bind_param("i", $perm_id);
            $stmt->execute();
            $permission = $stmt->get_result()->fetch_assoc();
            
            if (!$permission) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Permission not found'
                ]);
                exit;
            }

            // Delete permission
            $stmt = $conn->prepare("DELETE FROM role_permissions WHERE perm_id = ?");
            $stmt->bind_param("i", $perm_id);
            
            if ($stmt->execute()) {
                // Log the action
                $log_message = "Deleted permission: {$permission['role_name']} - {$permission['module_name']}";
                logPermissionAction($conn, $user_id, 'Delete', $log_message);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Permission deleted successfully'
                ]);
            } else {
                throw new Exception("Failed to delete permission: " . $stmt->error);
            }
            break;

        // ---------------- BULK OPERATIONS ----------------
        case 'bulk_delete':
            $ids = $_POST['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No permissions selected'
                ]);
                exit;
            }

            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $types = str_repeat('i', count($ids));
            
            $stmt = $conn->prepare("DELETE FROM role_permissions WHERE perm_id IN ($placeholders)");
            $stmt->bind_param($types, ...$ids);
            
            if ($stmt->execute()) {
                logPermissionAction($conn, $user_id, 'Bulk Delete', "Deleted " . count($ids) . " permissions");
                
                echo json_encode([
                    'success' => true,
                    'message' => count($ids) . ' permissions deleted successfully'
                ]);
            } else {
                throw new Exception("Failed to delete permissions: " . $stmt->error);
            }
            break;

        // ---------------- GET ROLE MODULES ----------------
        case 'get_role_modules':
            $role_id = intval($_POST['role_id'] ?? 0);
            
            if (!$role_id) {
                echo json_encode([]);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT module_name, can_view, can_add, can_edit, can_delete
                FROM role_permissions
                WHERE role_id = ?
                ORDER BY module_name
            ");
            $stmt->bind_param("i", $role_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $modules = [];
            while ($row = $result->fetch_assoc()) {
                $modules[] = $row;
            }
            
            echo json_encode($modules);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("Role Permissions Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

// ============================================
// Helper Functions
// ============================================

/**
 * Log permission actions to audit trail
 */
function logPermissionAction($conn, $user_id, $action, $remarks) {
    $stmt = $conn->prepare("
        INSERT INTO permission_logs (user_id, module_name, action_name, action_status, action_time)
        VALUES (?, 'role_permissions', ?, 'Success', NOW())
    ");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    
    // Also log to audit_trail if you want
    $stmt = $conn->prepare("
        INSERT INTO audit_trail (user_id, action_type, module_name, action_time, ip_address, remarks, compliance_status)
        VALUES (?, ?, 'Role Permissions', NOW(), ?, ?, 'Compliant')
    ");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $stmt->bind_param("isss", $user_id, $action, $ip, $remarks);
    $stmt->execute();
}