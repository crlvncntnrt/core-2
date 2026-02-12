<?php
require_once('../initialize_coreT2.php');
require_once(__DIR__ . '/inc/log_audit_trial.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Helper function to log to BOTH tables
function log_to_both_tables($user_id, $action, $module, $remarks, $status = 'Success') {
    global $conn;
    
    // Log to audit_trail (existing)
    log_audit_trial($user_id, $action, $module, $remarks);
    
    // ✅ Also log to permission_logs
    try {
        $stmt = $conn->prepare("
            INSERT INTO permission_logs (user_id, module_name, action_name, action_status, action_time)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('isss', $user_id, $module, $action, $status);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Permission log error: " . $e->getMessage());
    }
}

// ✅ CHECK IF USER IS INACTIVE BEFORE LOGOUT
$is_inactive = false;

// Log the logout if user is logged in
if (!empty($_SESSION['userdata']['user_id'])) {
    $user_id = $_SESSION['userdata']['user_id'];
    $username = $_SESSION['userdata']['full_name'] ?? $_SESSION['userdata']['username'] ?? 'User';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // ✅ CHECK USER STATUS IN DATABASE
    try {
        $stmt = $conn->prepare("SELECT status FROM users WHERE user_id=? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($user['status'] !== 'Active') {
                $is_inactive = true;
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Logout status check error: " . $e->getMessage());
    }

    // Determine logout type
    if ($is_inactive) {
        $logout_type = 'Logout - Account Inactive';
        $remarks = "Inactive user $username logged out from IP: $ip";
        $log_status = 'Warning';
    } elseif (isset($_GET['auto'])) {
        $logout_type = 'Auto Logout';
        $remarks = "User $username auto-logged out due to inactivity from IP: $ip";
        $log_status = 'Success';
    } else {
        $logout_type = 'Logout';
        $remarks = "User $username logged out from IP: $ip";
        $log_status = 'Success';
    }

    // ✅ Log to both tables
    log_to_both_tables(
        $user_id,
        $logout_type,
        'Authentication',
        $remarks,
        $log_status
    );
}

// Destroy session completely
$_SESSION = [];
session_unset();
session_destroy();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// ✅ Redirect with appropriate message
if (isset($_GET['auto'])) {
    header("Location: login.php?timeout=1&auto=1");
} elseif ($is_inactive) {
    // ✅ SHOW INACTIVE WARNING INSTEAD OF SUCCESS
    header("Location: login.php?logout=1&inactive=1");
} else {
    header("Location: login.php?logout=1");
}
exit();