<?php
// ============================================================================
// access_control.php — Central RBAC (Role-Based Access Control)
// ============================================================================

require_once(__DIR__ . '/../../initialize_coreT2.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ============================================================================
// ✅ Session Validation
// ============================================================================
if (!isset($_SESSION['userdata'])) {
    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Session Expired</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body style='background-color: #f5f5f5;'>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Session Expired',
            text: 'Please log in again to continue.',
            confirmButtonColor: '#3085d6',
            background: '#ffffff',
            allowOutsideClick: false
        }).then(() => {
            window.location.href = '/admin/login.php';
        });
    </script>
    </body>
    </html>";
    exit();
}

// ============================================================================
//  Get Current User Info
// ============================================================================
$user = $_SESSION['userdata'];
$user_role = $user['role'] ?? 'Guest';
$user_id   = $user['user_id'] ?? 0;

// ============================================================================
//  Role Permission Map (adjust per your modules)
// ============================================================================
$allowed_roles = [
    'dashboard'            => ['Super Admin', 'Admin', 'Staff'],
    'loan_portfolio'       => ['Super Admin', 'Admin', 'Staff'],
    'Repayment_Tracker'       => ['Super Admin', 'Admin', 'Staff'],
    'savings_monitoring'   => ['Super Admin', 'Admin', 'Staff'],
    'disbursement_tracker' => ['Super Admin', 'Admin', 'Staff'],

    // ✅ Super Admin AND Admin can access Compliance Logs (NOT Staff)
    'compliance_logs'      => ['Super Admin', 'Admin'],  // ✅ Admin can access, Staff CANNOT

    // ❌ ONLY SUPER ADMIN CAN ACCESS THESE:
    'user_management'      => ['Super Admin'],  // ❌ Admin and Staff CANNOT access
    'role_permissions'     => ['Super Admin'],  // ❌ Admin and Staff CANNOT access
    'permission_logs'      => ['Super Admin']   // ❌ Admin and Staff CANNOT access
];

function showAccessDenied($module)
{
    $pretty = ucfirst(str_replace('_', ' ', $module));
    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Access Denied</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <style>
            body {
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            }
            .swal2-icon.swal2-error {
                border-color: #dc3545 !important;
                color: #dc3545 !important;
            }
            .swal2-icon.swal2-error [class^='swal2-x-mark-line'] {
                background-color: #dc3545 !important;
            }
        </style>
    </head>
    <body>
    <script>
        Swal.fire({
            icon: 'error',
            title: '<span style=\"color: #333;\">🚫 Access Denied!</span>',
            html: `
                <div style=\"text-align: center; padding: 10px 0;\">
                    <p style=\"color: #dc3545; font-weight: bold; font-size: 1.1rem; margin: 15px 0 10px 0;\">
                        You don't have permission to access <strong>{$pretty}</strong>.
                    </p>
                    <p style=\"color: #6c757d; font-size: 1rem; margin: 10px 0;\">
                        This module is restricted to Super Admin only.
                    </p>
                    <p style=\"color: #6c757d; font-size: 0.95rem; margin: 10px 0;\">
                        Please contact your system administrator if you need access.
                    </p>
                </div>
            `,
            confirmButtonText: '← Return to Dashboard',
            confirmButtonColor: '#dc3545',
            allowOutsideClick: false,
            allowEscapeKey: false,
            background: '#ffffff',
            customClass: {
                popup: 'animated-popup',
                confirmButton: 'custom-confirm-btn'
            },
            width: '500px',
            padding: '2rem'
        }).then(() => {
            window.location.href = '/admin/dashboard.php';
        });
    </script>
    </body>
    </html>";
    exit();
}

// ============================================================================
// ✅ Log Permission Activity (Success or Denied)
// ============================================================================
function logPermission($conn, $user_id, $module_name, $action_name, $status = 'Success')
{
    if (!$conn) return;
    try {
        $stmt = $conn->prepare("
            INSERT INTO permission_logs (user_id, module_name, action_name, action_status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('isss', $user_id, $module_name, $action_name, $status);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Silent fail — do not break app
        error_log("Permission log error: " . $e->getMessage());
    }
}

// ============================================================================
// ✅ Check Permission (Main Function)
// ============================================================================
function checkPermission($module)
{
    global $allowed_roles, $user_role, $user_id, $conn;

    // If module not defined, Super Admin only
    if (!isset($allowed_roles[$module])) {
        if ($user_role !== 'Super Admin') {
            logPermission($conn, $user_id, $module, 'Access', 'Denied');
            showAccessDenied($module);
        }
        return;
    }

    // Check if allowed
    if (!in_array($user_role, $allowed_roles[$module])) {
        logPermission($conn, $user_id, $module, 'Access', 'Denied');
        showAccessDenied($module);
        exit();
    }

    // Log success
    logPermission($conn, $user_id, $module, 'Access', 'Success');
}

// ============================================================================
// ✅ Helper to Get Role
// ============================================================================
function getCurrentUserRole()
{
    global $user_role;
    return $user_role;
}

// ============================================================================
// ✅ hasPermission() helper (for button-level checks)
// ============================================================================
if (!function_exists('hasPermission')) {
    function hasPermission($conn, $role, $module, $action)
    {
        // Super Admin has access to everything
        if ($role === 'Super Admin') return true;

        $rolePermissions = [
            'Super Admin' => [
                'Dashboard' => ['view', 'add', 'edit', 'delete'],
                'User Management' => ['view', 'add', 'edit', 'delete'],
                'Savings Monitoring' => ['view', 'add', 'edit', 'delete'],
                'Loan Portfolio' => ['view', 'add', 'edit', 'delete'],
                'Repayment Tracker' => ['view', 'add', 'edit', 'delete'],
                'Disbursement Tracker' => ['view', 'add', 'edit', 'delete'],
                'Compliance & Audit Trail' => ['view', 'add', 'edit', 'delete'],
                'Compliance Logs' => ['view', 'add', 'edit', 'delete'],
                'Permission Logs' => ['view', 'add', 'edit', 'delete'],
                'Audit Trail' => ['view', 'add', 'edit', 'delete'],
                'Role Permissions' => ['view', 'add', 'edit', 'delete'],
            ],
            'Admin' => [
                'Dashboard' => ['view'],  // VIEW ONLY
                'Loan Portfolio' => ['view'],  // VIEW ONLY
                'Repayment Tracker' => ['view'],  // VIEW ONLY
                'Savings Monitoring' => ['view'],  // VIEW ONLY
                'Disbursement Tracker' => ['view'],  // VIEW ONLY
                'Compliance Logs' => ['view'],  // ✅ VIEW ONLY - Admin can access
                'Compliance & Audit Trail' => ['view'],  // ✅ VIEW ONLY - Admin can access
                'Audit Trail' => ['view'],  // ✅ VIEW ONLY - Admin can access
                // ❌ NO ACCESS to: User Management, Role Permissions, Permission Logs
            ],
            'Staff' => [
                'Dashboard' => ['view'],  // VIEW ONLY
                'Loan Portfolio' => ['view'],  // VIEW ONLY
                'Repayment Tracker' => ['view'],  // VIEW ONLY
                'Savings Monitoring' => ['view'],  // VIEW ONLY
                'Disbursement Tracker' => ['view'],  // VIEW ONLY
                // ❌ NO ACCESS to: User Management, Role Permissions, Permission Logs, Compliance Logs
            ],
        ];

        // Check if role and module exist in permissions
        $hasAccess = isset($rolePermissions[$role][$module]) &&
            in_array($action, $rolePermissions[$role][$module]);

        // Log access denial to audit trail
        if (!$hasAccess) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $userId = $_SESSION['userdata']['user_id'] ?? null;
            $username = $_SESSION['userdata']['username'] ?? 'Unknown';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            try {
                $stmt = $conn->prepare("
                    INSERT INTO audit_trail (user_id, action_type, module_name, remarks, ip_address, action_time)
                    VALUES (?, 'Access Denied', ?, ?, ?, NOW())
                ");
                $remarks = "User '$username' (role: $role) tried to $action $module without permission.";
                $stmt->bind_param('isss', $userId, $module, $remarks, $ip);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                // Silent fail - don't break the app
                error_log("Failed to log access denial: " . $e->getMessage());
            }
        }

        return $hasAccess;
    }
}