<?php
/**
 * AJAX AI Statistics Handler
 * Location: /admin/ajax_ai_statistics.php
 * Provides summary statistics for AI credit scoring system
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
    $total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM members WHERE status = 'Active'");
    $total_stmt->execute();
    $total_row = $total_stmt->get_result()->fetch_assoc();
    $total_members = $total_row['total'];
    
    $scored_stmt = $conn->prepare("SELECT COUNT(*) as scored 
                     FROM members 
                     WHERE status = 'Active' 
                     AND member_credit_score IS NOT NULL");
    $scored_stmt->execute();
    $scored_row = $scored_stmt->get_result()->fetch_assoc();
    $scored_members = $scored_row['scored'];
    
    $unscored_members = $total_members - $scored_members;
    
    $old_stmt = $conn->prepare("SELECT COUNT(*) as old_scores 
                  FROM members 
                  WHERE status = 'Active' 
                  AND member_credit_score IS NOT NULL
                  AND (last_score_update IS NULL OR last_score_update < DATE_SUB(NOW(), INTERVAL 30 DAY))");
    $old_stmt->execute();
    $old_row = $old_stmt->get_result()->fetch_assoc();
    $old_scores = $old_row['old_scores'];
    
    $last_stmt = $conn->prepare("SELECT MAX(last_score_update) as last_calc 
                   FROM members 
                   WHERE member_credit_score IS NOT NULL");
    $last_stmt->execute();
    $last_row = $last_stmt->get_result()->fetch_assoc();
    $last_calculation = $last_row['last_calc'] ? date('M j, Y g:i A', strtotime($last_row['last_calc'])) : 'Never';
    
    echo json_encode([
        'success' => true,
        'total_members' => $total_members,
        'scored_members' => $scored_members,
        'unscored_members' => $unscored_members,
        'old_scores' => $old_scores,
        'last_calculation' => $last_calculation
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>