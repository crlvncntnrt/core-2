<?php
/**
 * AJAX Get Members List Handler
 * Location: /admin/ajax_get_members_list.php
 * Returns list of active members for dropdown/selection
 */

session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
require_once(__DIR__ . '/inc/sess_auth.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userdata'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Session expired',
        'redirect' => '../login.php',
        'session_expired' => true
    ]);
    exit();
}

try {
    $query = "SELECT member_id, full_name, member_code 
              FROM members 
              WHERE status = 'Active' 
              ORDER BY full_name ASC";
    
    $result = $conn->query($query);
    $members = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>