<?php
ob_start();
ini_set('date.timezone', 'Asia/Manila');
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === Include Core Files ===
require_once(__DIR__ . '/initialize.php');             // system config
require_once(__DIR__ . '/classes/DBConnection.php');   // DB handler
require_once(__DIR__ . '/classes/systemSettings.php'); // system settings handler

// === Database Connection ===
$db = new DBConnection;
$conn = $db->conn;

// === Load Helpers & Loggers ===
require_once(__DIR__ . '/admin/inc/audit_logger.php');
require_once(__DIR__ . '/admin/inc/compliance_logger.php');

// === Ensure Settings Object Exists ===
if (!isset($_settings)) {
    $_settings = new SystemSettings();
    $_settings->load_system_info();
}

// === Helper Functions ===

// ✅ Image validation (used by login page for logo)
function validate_image($file)
{
    if (!empty($file)) {
        $ex = explode("?", $file);
        $file = $ex[0];
        $ts = isset($ex[1]) ? "?" . $ex[1] : '';
        if (is_file(base_app . $file)) {
            return base_url . $file . $ts;
        } else {
            return base_url . 'dist/img/no-image-available.png';
        }
    } else {
        return base_url . 'dist/img/no-image-available.png';
    }
}

// ✅ Redirect utility
function redirect($url = '')
{
    if (!empty($url)) {
        echo '<script>location.href="' . base_url . ltrim($url, '/') . '"</script>';
        exit;
    }
}

// ✅ Number formatting
function format_num($number = '', $decimal = '')
{
    if (is_numeric($number)) {
        $ex = explode(".", $number);
        $decLen = isset($ex[1]) ? strlen($ex[1]) : 0;
        return number_format($number, is_numeric($decimal) ? $decimal : $decLen);
    } else {
        return "Invalid Input";
    }
}

// ✅ Universal Audit Logger
function log_audit($user_id, $action_type, $module = null, $reference_id = null, $details = null)
{
    global $conn;

    if (!$conn) return; // DB connection failed

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    try {
        $stmt = $conn->prepare("
            INSERT INTO audit_trial (user_id, action_type, module, reference_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ississs", $user_id, $action_type, $module, $reference_id, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Optional: log to PHP error log if needed
        error_log("Audit log failed: " . $e->getMessage());
    }
}

ob_end_flush();
