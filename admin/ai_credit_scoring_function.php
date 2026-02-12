<?php
/**
 * AI Credit Scoring Function - MySQLi Compatible
 * Calculates credit score for a member based on loan history and payment behavior
 */

if (!isset($_SESSION['userdata'])) {
    echo json_encode(['error' => 'Session expired', 'redirect' => 'login.php']);
    exit();
}

function calculate_ai_credit_score($member_id) {
    global $conn;
    
    try {
        // Validate member exists
        $member_check = $conn->query("SELECT member_id, full_name FROM members WHERE member_id = $member_id");
        
        if ($member_check->num_rows == 0) {
            return ['success' => false, 'error' => 'Member not found'];
        }
        
        $member = $member_check->fetch_assoc();
        
        // Initialize scores (0-100 scale)
        $payment_history_score = 0;
        $credit_utilization_score = 0;
        $loan_diversity_score = 0;
        $account_age_score = 0;
        $recent_activity_score = 0;
        
        // ================================================
        // 1. PAYMENT HISTORY SCORE (35% weight) - 0-100
        // ================================================
        $payment_query = "SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN late_days = 0 THEN 1 ELSE 0 END) as on_time,
            SUM(CASE WHEN late_days > 0 AND late_days <= 30 THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN late_days > 30 THEN 1 ELSE 0 END) as very_late,
            AVG(late_days) as avg_late_days
            FROM repayments r
            INNER JOIN loan_portfolio lp ON r.loan_id = lp.loan_id
            WHERE lp.member_id = $member_id";
        
        $payment_result = $conn->query($payment_query);
        $payments = $payment_result->fetch_assoc();
        
        // Count defaults from loan_portfolio
        $default_query = "SELECT COUNT(*) as defaulted 
                         FROM loan_portfolio 
                         WHERE member_id = $member_id AND status = 'Defaulted'";
        $default_result = $conn->query($default_query);
        $defaults = $default_result->fetch_assoc();
        
        if ($payments['total_payments'] > 0) {
            $on_time_ratio = $payments['on_time'] / $payments['total_payments'];
            $late_penalty = ($payments['late'] * 5);
            $very_late_penalty = ($payments['very_late'] * 10);
            $default_penalty = ($defaults['defaulted'] * 25);
            
            $payment_history_score = ($on_time_ratio * 100) - $late_penalty - $very_late_penalty - $default_penalty;
            $payment_history_score = max(0, min(100, $payment_history_score));
        } else {
            $payment_history_score = 50;
        }
        
        // ================================================
        // 2. CREDIT UTILIZATION SCORE (30% weight) - 0-100
        // ================================================
        $utilization_query = "SELECT 
            COUNT(*) as total_loans,
            SUM(CASE WHEN status = 'Active' OR status = 'Approved' THEN 1 ELSE 0 END) as active_loans,
            SUM(CASE WHEN status = 'Active' OR status = 'Approved' THEN principal_amount ELSE 0 END) as total_active_amount,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_loans,
            SUM(CASE WHEN status = 'Defaulted' THEN 1 ELSE 0 END) as defaulted_loans
            FROM loan_portfolio 
            WHERE member_id = $member_id";
        
        $util_result = $conn->query($utilization_query);
        $loans = $util_result->fetch_assoc();
        
        $max_recommended_active = 2;
        if ($loans['active_loans'] == 0) {
            $credit_utilization_score = 100;
        } else if ($loans['active_loans'] <= $max_recommended_active) {
            $credit_utilization_score = 100 - ($loans['active_loans'] * 10);
        } else {
            $credit_utilization_score = max(0, 70 - (($loans['active_loans'] - $max_recommended_active) * 15));
        }
        
        if ($loans['completed_loans'] > 0) {
            $credit_utilization_score = min(100, $credit_utilization_score + ($loans['completed_loans'] * 2));
        }
        
        // ================================================
        // 3. LOAN DIVERSITY SCORE (15% weight) - 0-100
        // ================================================
        $diversity_query = "SELECT 
            COUNT(DISTINCT loan_type) as loan_types,
            COUNT(*) as total_loans
            FROM loan_portfolio 
            WHERE member_id = $member_id";
        
        $div_result = $conn->query($diversity_query);
        $diversity = $div_result->fetch_assoc();
        
        if ($diversity['total_loans'] > 0) {
            $loan_diversity_score = min(100, 40 + ($diversity['loan_types'] * 20));
        } else {
            $loan_diversity_score = 50;
        }
        
        // ================================================
        // 4. ACCOUNT AGE SCORE (10% weight) - 0-100
        // ================================================
        $age_query = "SELECT 
            DATEDIFF(NOW(), membership_date) as days_active,
            membership_date
            FROM members 
            WHERE member_id = $member_id";
        
        $age_result = $conn->query($age_query);
        $account = $age_result->fetch_assoc();
        
        $days_active = $account['days_active'] ?? 0;
        if ($days_active >= 365 * 2) {
            $account_age_score = 100;
        } else if ($days_active >= 365) {
            $account_age_score = 80;
        } else if ($days_active >= 180) {
            $account_age_score = 60;
        } else if ($days_active >= 90) {
            $account_age_score = 40;
        } else {
            $account_age_score = 20;
        }
        
        // ================================================
        // 5. RECENT ACTIVITY SCORE (10% weight) - 0-100
        // ================================================
        $recent_query = "SELECT 
            COUNT(*) as recent_payments
            FROM repayments r
            INNER JOIN loan_portfolio lp ON r.loan_id = lp.loan_id
            WHERE lp.member_id = $member_id 
            AND r.repayment_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        
        $recent_result = $conn->query($recent_query);
        $recent = $recent_result->fetch_assoc();
        
        $recent_activity_score = min(100, ($recent['recent_payments'] * 20));
        
        // ================================================
        // CALCULATE FINAL WEIGHTED SCORE
        // ================================================
        $final_score = (
            ($payment_history_score * 0.35) +
            ($credit_utilization_score * 0.30) +
            ($loan_diversity_score * 0.15) +
            ($account_age_score * 0.10) +
            ($recent_activity_score * 0.10)
        );
        
        // ================================================
        // DETERMINE RISK CATEGORY & TIER
        // ================================================
        if ($final_score >= 85) {
            $risk_category = 'Excellent';
            $risk_tier = 'A';
            $interest_rate = 5.0;
            $max_loan = 100000;
            $recommendation = 'Highly Recommended for Approval';
        } else if ($final_score >= 75) {
            $risk_category = 'Very Good';
            $risk_tier = 'A';
            $interest_rate = 6.5;
            $max_loan = 75000;
            $recommendation = 'Recommended for Approval';
        } else if ($final_score >= 65) {
            $risk_category = 'Good';
            $risk_tier = 'B';
            $interest_rate = 8.0;
            $max_loan = 50000;
            $recommendation = 'Approved with Standard Terms';
        } else if ($final_score >= 55) {
            $risk_category = 'Fair';
            $risk_tier = 'C';
            $interest_rate = 10.0;
            $max_loan = 30000;
            $recommendation = 'Approved with Caution';
        } else if ($final_score >= 45) {
            $risk_category = 'Poor';
            $risk_tier = 'D';
            $interest_rate = 12.0;
            $max_loan = 20000;
            $recommendation = 'High Risk - Requires Collateral';
        } else {
            $risk_category = 'Very Poor';
            $risk_tier = 'D';
            $interest_rate = 15.0;
            $max_loan = 10000;
            $recommendation = 'Recommend Rejection or Co-Signer';
        }
        
        // ================================================
        // UPDATE MEMBER TABLE with credit score
        // ================================================
        $update_member = "UPDATE members SET 
            member_credit_score = $final_score,
            risk_tier = '$risk_tier',
            last_score_update = NOW()
            WHERE member_id = $member_id";
        
        $conn->query($update_member);
        
        // ================================================
        // UPDATE ALL ACTIVE/APPROVED LOANS for this member
        // ================================================
        $update_loans = "UPDATE loan_portfolio SET 
            ai_credit_score = $final_score,
            ai_risk_category = '$risk_category',
            ai_assessment_date = NOW(),
            recommended_interest_rate = $interest_rate
            WHERE member_id = $member_id 
            AND status IN ('Active', 'Approved', 'Pending')";
        
        $conn->query($update_loans);
        
        return [
            'success' => true,
            'member_id' => $member_id,
            'member_name' => $member['full_name'],
            'credit_score' => round($final_score, 2),
            'risk_category' => $risk_category,
            'risk_tier' => $risk_tier,
            'breakdown' => [
                'payment_history' => round($payment_history_score, 2),
                'credit_utilization' => round($credit_utilization_score, 2),
                'loan_diversity' => round($loan_diversity_score, 2),
                'account_age' => round($account_age_score, 2),
                'recent_activity' => round($recent_activity_score, 2)
            ],
            'recommendations' => [
                'interest_rate' => $interest_rate,
                'max_loan_amount' => $max_loan,
                'approval_recommendation' => $recommendation
            ],
            'statistics' => [
                'total_loans' => $loans['total_loans'],
                'active_loans' => $loans['active_loans'],
                'completed_loans' => $loans['completed_loans'],
                'defaulted_loans' => $loans['defaulted_loans'],
                'total_payments' => $payments['total_payments'],
                'on_time_payments' => $payments['on_time'],
                'late_payments' => $payments['late']
            ]
        ];
        
    } catch (Exception $e) {
        error_log("AI Scoring Error for Member $member_id: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to calculate credit score: ' . $e->getMessage()
        ];
    }
}
?>
