<?php
// update_session_activity.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json');

$response = [
    'success' => false,
    'session_valid' => false
];

// ✅ Check if session is valid
if (isset($_SESSION['userdata']) && isset($_SESSION['last_activity'])) {
    // Check if session has timed out (2 minutes = 120 seconds)
    $elapsed = time() - $_SESSION['last_activity'];
    
    if ($elapsed > 120) {
        // Session expired
        $response['success'] = false;
        $response['session_valid'] = false;
        $response['message'] = 'Session expired';
        $response['elapsed'] = $elapsed;
    } else {
        // Session still valid - update activity
        $_SESSION['last_activity'] = time();
        $response['success'] = true;
        $response['session_valid'] = true;
        $response['message'] = 'Session activity updated';
        $response['remaining'] = 120 - $elapsed;
    }
} else {
    // No session data
    $response['success'] = false;
    $response['session_valid'] = false;
    $response['message'] = 'No session data';
}

echo json_encode($response);
exit();
?>