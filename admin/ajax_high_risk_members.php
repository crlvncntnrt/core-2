<?php
/**
 * AJAX High Risk Members Handler
 * Location: /admin/ajax_high_risk_members.php
 * Returns list of members with credit scores below 55 (high risk)
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
    $query = "
        SELECT 
            m.member_id,
            m.full_name,
            m.contact_no,
            lp.ai_credit_score,
            lp.ai_risk_category,
            lp.ai_assessment_date,
            COUNT(DISTINCT lp.loan_id) as total_loans,
            SUM(CASE WHEN lp.status = 'Defaulted' THEN 1 ELSE 0 END) as defaulted_count
        FROM members m
        LEFT JOIN loan_portfolio lp ON m.member_id = lp.member_id
        WHERE lp.ai_credit_score < 55 
        AND lp.ai_credit_score IS NOT NULL
        GROUP BY m.member_id
        ORDER BY lp.ai_credit_score ASC, defaulted_count DESC
        LIMIT 20
    ";
    
    $result = $conn->query($query);
    $members = [];
    
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
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