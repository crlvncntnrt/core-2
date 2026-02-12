<?php
/**
 * AJAX Calculate Single Score Handler
 * Location: /admin/ajax_calculate_single_score.php
 * Calculates AI credit score for a single member
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

if (!isset($_POST['member_id']) || empty($_POST['member_id'])) {
    echo json_encode(['success' => false, 'error' => 'Member ID is required']);
    exit();
}

$member_id = intval($_POST['member_id']);

if ($member_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid Member ID']);
    exit();
}

try {
    // Calculate the AI score using the function
    $result = calculate_ai_credit_score($member_id);
    
    if (!$result['success']) {
        echo json_encode($result);
        exit();
    }
    
    // Format the response for the modal display
    $response = [
        'success' => true,
        'member_id' => $member_id,
        'member_name' => $result['member_name'],
        'credit_score' => round($result['credit_score']),
        'risk_category' => $result['risk_category'],
        'assessment_date' => date('F j, Y g:i A'),
        'score_breakdown' => [
            'payment_history' => round($result['breakdown']['payment_history']),
            'credit_utilization' => round($result['breakdown']['credit_utilization']),
            'loan_diversity' => round($result['breakdown']['loan_diversity']),
            'account_age' => round($result['breakdown']['account_age']),
            'recent_activity' => round($result['breakdown']['recent_activity'])
        ],
        'recommendations' => [
            'interest_rate' => $result['recommendations']['interest_rate'],
            'max_loan_amount' => $result['recommendations']['max_loan_amount'],
            'approval_recommendation' => $result['recommendations']['approval_recommendation']
        ]
    ];
    
    // Log the action
    $log_stmt = $conn->prepare("INSERT INTO audit_trail (user_id, action_type, module_name, remarks, ip_address, action_time)
                 VALUES (?, 'AI Score Calculation', 'Credit Scoring', ?, ?, NOW())");
    
    if ($log_stmt) {
        $user_id = $_SESSION['userdata']['user_id'];
        $remarks = "Calculated AI score for Member ID: {$member_id} (Score: {$result['credit_score']})";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param('iss', $user_id, $remarks, $ip);
        $log_stmt->execute();
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>