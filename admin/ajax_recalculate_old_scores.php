<?php
/**
 * AJAX Recalculate Old Scores Handler
 * Location: /admin/ajax_recalculate_old_scores.php
 * Recalculates AI scores for members with scores older than 30 days
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
    $query = "SELECT DISTINCT member_id 
              FROM members 
              WHERE status = 'Active'
              AND (last_score_update IS NULL OR last_score_update < DATE_SUB(NOW(), INTERVAL 30 DAY))";
    
    $result = $conn->query($query);
    $members = [];
    
    while ($row = $result->fetch_assoc()) {
        $members[] = $row['member_id'];
    }
    
    $total_found = count($members);
    $updated = 0;
    $failed = 0;
    
    foreach ($members as $member_id) {
        try {
            $score_result = calculate_ai_credit_score($member_id);
            if ($score_result['success']) {
                $updated++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
            error_log("Failed to recalculate score for member {$member_id}: " . $e->getMessage());
        }
    }
    
    // Log the recalculation
    $log_stmt = $conn->prepare("INSERT INTO audit_trail (user_id, action_type, module_name, remarks, ip_address, action_time)
                 VALUES (?, 'AI Recalculation', 'Credit Scoring', ?, ?, NOW())");
    
    if ($log_stmt) {
        $user_id = $_SESSION['userdata']['user_id'];
        $remarks = "Recalculated {$updated} old AI scores (>30 days)";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param('iss', $user_id, $remarks, $ip);
        $log_stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'failed' => $failed,
        'total_found' => $total_found,
        'message' => "Recalculated $updated out of $total_found old scores"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
