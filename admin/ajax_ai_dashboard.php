<?php
/**
 * AJAX AI Dashboard Data Handler
 * Location: /admin/ajax_ai_dashboard.php
 * Provides real-time AI credit scoring data for dashboard
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
    $response = [];
    
    // 1. Average Credit Score
    $avg_query = "SELECT AVG(ai_credit_score) as avg_score 
                  FROM loan_portfolio 
                  WHERE ai_credit_score IS NOT NULL";
    $avg_result = $conn->query($avg_query);
    $response['avg_score'] = round($avg_result->fetch_assoc()['avg_score'] ?? 0, 1);
    
    // 2. High Risk Count (Score < 55)
    $high_risk_query = "SELECT COUNT(DISTINCT member_id) as count 
                        FROM loan_portfolio 
                        WHERE ai_credit_score < 55 
                        AND ai_credit_score IS NOT NULL";
    $high_risk_result = $conn->query($high_risk_query);
    $response['high_risk_count'] = $high_risk_result->fetch_assoc()['count'] ?? 0;
    
    // 3. Excellent Members Count (Score >= 85)
    $excellent_query = "SELECT COUNT(DISTINCT member_id) as count 
                        FROM loan_portfolio 
                        WHERE ai_credit_score >= 85";
    $excellent_result = $conn->query($excellent_query);
    $response['excellent_count'] = $excellent_result->fetch_assoc()['count'] ?? 0;
    
    // 4. Assessed Today
    $today_query = "SELECT COUNT(DISTINCT member_id) as count 
                    FROM loan_portfolio 
                    WHERE DATE(ai_assessment_date) = CURDATE()";
    $today_result = $conn->query($today_query);
    $response['assessed_today'] = $today_result->fetch_assoc()['count'] ?? 0;
    
    // 5. Score Distribution
    $distribution_query = "
        SELECT 
            SUM(CASE WHEN ai_credit_score BETWEEN 0 AND 20 THEN 1 ELSE 0 END) as range_0_20,
            SUM(CASE WHEN ai_credit_score BETWEEN 21 AND 40 THEN 1 ELSE 0 END) as range_21_40,
            SUM(CASE WHEN ai_credit_score BETWEEN 41 AND 55 THEN 1 ELSE 0 END) as range_41_55,
            SUM(CASE WHEN ai_credit_score BETWEEN 56 AND 64 THEN 1 ELSE 0 END) as range_56_64,
            SUM(CASE WHEN ai_credit_score BETWEEN 65 AND 74 THEN 1 ELSE 0 END) as range_65_74,
            SUM(CASE WHEN ai_credit_score BETWEEN 75 AND 84 THEN 1 ELSE 0 END) as range_75_84,
            SUM(CASE WHEN ai_credit_score BETWEEN 85 AND 100 THEN 1 ELSE 0 END) as range_85_100
        FROM loan_portfolio 
        WHERE ai_credit_score IS NOT NULL
    ";
    $dist_result = $conn->query($distribution_query);
    $response['distribution'] = $dist_result->fetch_assoc();
    
    // 6. Risk Categories Count
    $categories_query = "
        SELECT 
            SUM(CASE WHEN ai_risk_category = 'Excellent' THEN 1 ELSE 0 END) as excellent,
            SUM(CASE WHEN ai_risk_category = 'Very Good' THEN 1 ELSE 0 END) as very_good,
            SUM(CASE WHEN ai_risk_category = 'Good' THEN 1 ELSE 0 END) as good,
            SUM(CASE WHEN ai_risk_category = 'Fair' THEN 1 ELSE 0 END) as fair,
            SUM(CASE WHEN ai_risk_category = 'Poor' THEN 1 ELSE 0 END) as poor,
            SUM(CASE WHEN ai_risk_category = 'Very Poor' THEN 1 ELSE 0 END) as very_poor
        FROM loan_portfolio 
        WHERE ai_risk_category IS NOT NULL
    ";
    $cat_result = $conn->query($categories_query);
    $response['risk_categories'] = $cat_result->fetch_assoc();
    
    // 7. Top 5 High Risk Members
    $high_risk_members_query = "
        SELECT 
            m.member_id,
            m.full_name,
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
        LIMIT 5
    ";
    $members_result = $conn->query($high_risk_members_query);
    $response['high_risk_members'] = [];
    while ($row = $members_result->fetch_assoc()) {
        $response['high_risk_members'][] = $row;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Failed to fetch AI dashboard data',
        'message' => $e->getMessage()
    ]);
}
?>