<?php
// ✅ Include system initialization and global functions
require_once(__DIR__ . '/../../initialize_coreT2.php');

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Session timeout configuration (2 minutes = 120 seconds)
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
 * Handle session timeout logout
 */
function handleSessionTimeout()
{
    $user_id = $_SESSION['userdata']['user_id'] ?? 0;
    $username = $_SESSION['userdata']['full_name'] ?? 'Unknown';

    // Log the timeout event (SAFE - uses your existing function)
    if (function_exists('log_audit')) {
        log_audit(
            $user_id,
            'Session Timeout',
            'Authentication',
            null,
            "User $username session expired due to inactivity"
        );
    }

    // Clear all session data
    $_SESSION = [];

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

    // Redirect to login
    header("Location: /admin/login.php?timeout=1");
    exit();
}

// ====== AUTHENTICATION CHECKS ======

// Skip session timeout check for login page
$is_login_page = strpos($link, 'login.php') !== false;

// Check session timeout only if NOT on login page
if (!$is_login_page && isset($_SESSION['userdata'])) {
    if (!checkSessionTimeout()) {
        handleSessionTimeout();
    }
}

// 🔹 1. Ensure session userdata exists
if (!isset($_SESSION['userdata'])) {
    // Log unauthorized access (SAFE)
    if (function_exists('log_audit')) {
        log_audit(
            null,
            'Unauthorized Access',
            'Authentication',
            null,
            'Attempted access to: ' . $link
        );
    }

    // Redirect to login page
    header("Location: /admin/login.php");
    exit();
}

// 🔹 2. User already logged in but visiting login.php → redirect to dashboard
if (isset($_SESSION['userdata']) && $is_login_page) {
    // Log re-login attempt (SAFE)
    if (function_exists('log_audit')) {
        log_audit(
            $_SESSION['userdata']['user_id'] ?? 0,
            'Re-login Attempt',
            'Authentication',
            null,
            'User attempted to visit login page while logged in.'
        );
    }

    header("Location: /admin/dashboard.php");
    exit;
}


if (isset($_SESSION['userdata']) && isset($_SESSION['last_activity'])) {
    $remaining_time = SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
    $remaining_time = max(0, $remaining_time);

    $_SESSION['session_info'] = [
        'remaining_seconds' => $remaining_time,
        'timeout_warning' => ($remaining_time <= 30)
    ];
}

// ====== AUTO-LOGOUT JAVASCRIPT ======
function autoLogoutJS()
{
?>
    <script>
        // Simple Auto Logout after 2 minutes of inactivity
        let inactivityTimer;
        const logoutTime = 120000; // 2 minutes in milliseconds

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(logoutDueToInactivity, logoutTime);
        }

        function logoutDueToInactivity() {
            // Redirect to logout page
            window.location.href = '/admin/logout.php?auto=1';
        }

        // Start timer when page loads
        resetInactivityTimer();

        // Reset timer on user activity
        window.addEventListener('mousemove', resetInactivityTimer);
        window.addEventListener('keypress', resetInactivityTimer);
        window.addEventListener('click', resetInactivityTimer);
        window.addEventListener('scroll', resetInactivityTimer);
        window.addEventListener('touchstart', resetInactivityTimer);
        window.addEventListener('mousedown', resetInactivityTimer);

        // Also reset when window gets focus
        window.addEventListener('focus', resetInactivityTimer);
    </script>
<?php
}

// Call the auto-logout JavaScript if user is logged in and not on login page
if (isset($_SESSION['userdata']) && !$is_login_page) {
    autoLogoutJS();
}
