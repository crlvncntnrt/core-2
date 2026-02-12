<?php
/**
 * AJAX Calculate All Scores Handler
 * Location: /admin/ajax_calculate_all_scores.php
 * Calculates AI credit scores for ALL active members (bulk operation)
 */

session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
require_once(__DIR__ . '/inc/sess_auth.php');
require_once(__DIR__ . '/ai_credit_scoring_function.php');

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
    $query = "SELECT member_id FROM members WHERE status = 'Active'";
    $result = $conn->query($query);
    
    $members = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }
    
    $total = count($members);
    $successful = 0;
    $failed = 0;
    
    foreach ($members as $member) {
        try {
            $score_result = calculate_ai_credit_score($member['member_id']);
            if ($score_result['success']) {
                $successful++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
            error_log("Failed to calculate score for member {$member['member_id']}: " . $e->getMessage());
        }
    }
    
    // Log the bulk calculation
    $log_stmt = $conn->prepare("INSERT INTO audit_trail (user_id, action_type, module_name, remarks, ip_address, action_time)
                 VALUES (?, 'AI Bulk Calculation', 'Credit Scoring', ?, ?, NOW())");
    
    if ($log_stmt) {
        $user_id = $_SESSION['userdata']['user_id'];
        $remarks = "Bulk calculated {$successful} out of {$total} member AI scores";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param('iss', $user_id, $remarks, $ip);
        $log_stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'successful' => $successful,
        'failed' => $failed,
        'message' => "Successfully calculated $successful out of $total member scores"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>