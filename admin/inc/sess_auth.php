<?php
// ✅ Include system initialization and global functions
require_once(__DIR__ . '/../../initialize_coreT2.php');

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Session timeout configuration (2 minutes = 120 seconds for testing, use 1800 for production)
define('SESSION_TIMEOUT', 120);

// Get current page URL
$link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$link .= "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// ====== SESSION TIMEOUT FUNCTIONS ======

/**
 * Check if session has timed out
 */
function checkSessionTimeout()
{
    // If no userdata, session is invalid
    if (!isset($_SESSION['userdata'])) {
        return false;
    }

    // Initialize last_activity if not set
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        $_SESSION['session_start'] = time();
        return true;
    }

    $current_time = time();
    $last_activity = $_SESSION['last_activity'];

    // Check if session has expired (2 minutes)
    if (($current_time - $last_activity) > SESSION_TIMEOUT) {
        return false;
    }

    // Update last activity time
    $_SESSION['last_activity'] = $current_time;
    return true;
}

/**
 * ✅ NEW: Check if user account is still active
 */
function checkUserStatus()
{
    global $conn;
    
    // If no userdata, can't check status
    if (!isset($_SESSION['userdata']['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['userdata']['user_id'];
    
    try {
        $stmt = $conn->prepare("SELECT status FROM users WHERE user_id=? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // Return true if Active, false if Inactive
            return ($user['status'] === 'Active');
        }
        
        $stmt->close();
        return false; // User not found
        
    } catch (Exception $e) {
        error_log("User status check error: " . $e->getMessage());
        return false;
    }
}

/**
 * ✅ Log to BOTH tables WITH IP ADDRESS
 */
function log_to_both_tables($user_id, $action, $module, $remarks, $status = 'Success') {
    global $conn;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Log to audit_trail
    log_audit($user_id, $action, $module, null, $remarks);
    
    // ✅ Also log to permission_logs WITH IP ADDRESS
    try {
        // Check if ip_address column exists
        $result = $conn->query("SHOW COLUMNS FROM permission_logs LIKE 'ip_address'");
        if ($result->num_rows > 0) {
            // Column exists - use new query with IP
            $stmt = $conn->prepare("
                INSERT INTO permission_logs (user_id, module_name, action_name, action_status, ip_address, action_time)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('issss', $user_id, $module, $action, $status, $ip);
        } else {
            // Column doesn't exist - use old query without IP
            $stmt = $conn->prepare("
                INSERT INTO permission_logs (user_id, module_name, action_name, action_status, action_time)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('isss', $user_id, $module, $action, $status);
        }
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Permission log error: " . $e->getMessage());
    }
}

/**
 * ✅ NEW: Handle inactive user - force logout
 */
function handleInactiveUser()
{
    global $conn;
    
    $user_id = $_SESSION['userdata']['user_id'] ?? 0;
    $username = $_SESSION['userdata']['full_name'] ?? 'Unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // ✅ Log forced logout due to deactivation
    log_to_both_tables(
        $user_id,
        'Forced Logout - Account Deactivated',
        'Authentication',
        "User $username was automatically logged out because account was deactivated. IP: $ip",
        'Warning'
    );

    // ✅ Completely destroy session
    $_SESSION = array();

    // Destroy session cookie
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

    session_destroy();
    session_start();
    session_regenerate_id(true);

    // ✅ Clear output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // ✅ Set headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    
    // Output the SweetAlert page
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deactivated</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
    </style>
</head>
<body>
<script>
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        history.go(1);
    };
    
    Swal.fire({
        icon: "warning",
        title: "Account Deactivated",
        html: "<p style='color: #856404; font-weight: bold; font-size: 1rem; margin: 10px 0;'>Your account has been deactivated by an administrator.</p><p style='color: #6c757d; font-size: 0.95rem; margin: 10px 0;'>Please contact support for assistance.</p>",
        confirmButtonText: "OK",
        confirmButtonColor: "#ffc107",
        allowOutsideClick: false,
        allowEscapeKey: false,
        background: "#ffffff"
    }).then(() => {
        sessionStorage.clear();
        localStorage.removeItem('sessionActive');
        window.location.replace("/admin/login.php?logout=1&inactive=1");
    });
</script>
</body>
</html>
    <?php
    exit();
}

/**
 * ✅ Handle session timeout - DIRECT SWEETALERT OUTPUT
 */
function handleSessionTimeout()
{
    global $conn;
    
    $user_id = $_SESSION['userdata']['user_id'] ?? 0;
    $username = $_SESSION['userdata']['full_name'] ?? 'Unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // ✅ Log to BOTH tables
    log_to_both_tables(
        $user_id,
        'Session Expired',
        'Authentication',
        "User $username session expired due to inactivity from IP: $ip",
        'Failed'
    );

    // ✅ CRITICAL: Completely destroy session FIRST
    $_SESSION = array(); // Clear all session data

    // Destroy session cookie
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

    // Destroy the session
    session_destroy();
    
    // ✅ Start a new clean session to prevent issues
    session_start();
    session_regenerate_id(true);

    // ✅ CRITICAL: Stop all output and show SweetAlert
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // ✅ Set headers to prevent caching
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Output the SweetAlert page
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Expired</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
    </style>
</head>
<body>
<script>
    // Prevent back button
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        history.go(1);
    };
    
    Swal.fire({
        icon: "warning",
        title: "Session Expired",
        html: "<p style='color: #856404; font-weight: bold; font-size: 1rem; margin: 10px 0;'>You have been logged out due to 2 minutes of inactivity.</p><p style='color: #6c757d; font-size: 0.95rem; margin: 10px 0;'>Please log in again to continue.</p>",
        confirmButtonText: "OK",
        confirmButtonColor: "#3085d6",
        allowOutsideClick: false,
        allowEscapeKey: false,
        background: "#ffffff"
    }).then(() => {
        // Clear any remaining session data
        sessionStorage.clear();
        localStorage.removeItem('sessionActive');
        
        // Force redirect to login
        window.location.replace("/admin/login.php");
    });
</script>
</body>
</html>
    <?php
    exit();
}

// ====== AUTHENTICATION CHECKS ======

// Skip session timeout check for login page
$is_login_page = strpos($link, 'login.php') !== false;

// ✅ Check session timeout only if NOT on login page
if (!$is_login_page && isset($_SESSION['userdata'])) {
    // ✅ FIRST: Check if account is still active
    if (!checkUserStatus()) {
        handleInactiveUser(); // Show account deactivated alert and logout
        // Code below will NEVER execute
    }
    
    // ✅ SECOND: Check session timeout
    if (!checkSessionTimeout()) {
        handleSessionTimeout(); // Show session expired alert and logout
        // Code below will NEVER execute
    }
}

// 🔹 1. Ensure session userdata exists
if (!isset($_SESSION['userdata']) && !$is_login_page) {
    log_audit(
        null,                        
        'Unauthorized Access',       
        'Authentication',            
        null,                        
        'Attempted access to: ' . $link
    );

    // ✅ Use absolute URL
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    header("Location: $base_url/admin/login.php");
    exit();
}

// 🔹 2. User already logged in but visiting login.php → redirect to dashboard
// ✅ ONLY redirect if session has valid userdata AND valid last_activity
if (isset($_SESSION['userdata']) && isset($_SESSION['last_activity']) && $is_login_page) {
    $elapsed = time() - $_SESSION['last_activity'];
    
    // Only redirect to dashboard if session is NOT expired
    if ($elapsed < SESSION_TIMEOUT) {
        log_audit(
            $_SESSION['userdata']['user_id'] ?? 0,
            'Re-login Attempt',
            'Authentication',
            null,
            'User attempted to visit login page while logged in.'
        );

        // ✅ Use absolute URL
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        header("Location: $base_url/admin/dashboard.php");
        exit();
    }
}

// 🔹 3. Add session info for JavaScript (optional)
if (isset($_SESSION['userdata']) && isset($_SESSION['last_activity'])) {
    $remaining_time = SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
    $remaining_time = max(0, $remaining_time);

    $_SESSION['session_info'] = [
        'remaining_seconds' => $remaining_time,
        'timeout_warning' => ($remaining_time <= 30)
    ];
}
?>