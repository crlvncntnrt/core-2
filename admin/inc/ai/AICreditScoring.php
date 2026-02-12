<?php
/**
 * AI Credit Scoring System
 * Customized para sa core2_db database structure
 * 
 * Tables used:
 * - loan_portfolio (loan_id, member_id, principal_amount, interest_rate, status)
 * - repayments (repayment_id, loan_id, amount, repayment_date, overdue_count, risk_level)
 * - members (member_id, full_name, contact_no, status)
 * - savings (para sa additional scoring)
 */

// FIXED: Correct path to config.php
if (!defined('DB_HOST')) {
    require_once(__DIR__ . '/../../../config.php');
}

class AICreditScoring {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Main function: Calculate AI Credit Score para sa member
     * 
     * @param int $member_id - ID ng member from members table
     * @return array - Complete credit assessment
     */
    public function calculateCreditScore($member_id) {
        // Kunin ang member information
        $member = $this->getMemberData($member_id);
        
        if (!$member) {
            return [
                'success' => false,
                'error' => 'Member not found',
                'member_id' => $member_id
            ];
        }
        
        // Calculate different scoring factors
        $scores = [
            'payment_history' => $this->getPaymentHistoryScore($member_id),
            'loan_performance' => $this->getLoanPerformanceScore($member_id),
            'repayment_behavior' => $this->getRepaymentBehaviorScore($member_id),
            'risk_indicators' => $this->getRiskIndicatorScore($member_id),
            'savings_score' => $this->getSavingsScore($member_id)
        ];
        
        // Weighted calculation
        $weights = [
            'payment_history' => 0.35,    // 35% - Pinaka importante
            'loan_performance' => 0.25,   // 25% - Loan completion rate
            'repayment_behavior' => 0.20, // 20% - On-time payments
            'risk_indicators' => 0.15,    // 15% - Red flags
            'savings_score' => 0.05       // 5% - Savings behavior
        ];
        
        $final_score = 0;
        foreach ($scores as $key => $score) {
            $final_score += ($score * $weights[$key]);
        }
        
        $final_score = round($final_score);
        
        // Determine risk category and recommendations
        $risk_category = $this->getRiskCategory($final_score);
        $recommended_rate = $this->getRecommendedRate($final_score);
        $max_loan = $this->getMaxLoanAmount($final_score, $member_id);
        
        // Save AI score sa database
        $this->saveAIScore($member_id, $final_score, $risk_category);
        
        return [
            'success' => true,
            'member_id' => $member_id,
            'member_name' => $member['full_name'],
            'credit_score' => $final_score,
            'risk_category' => $risk_category,
            'score_breakdown' => $scores,
            'recommendations' => [
                'interest_rate' => $recommended_rate,
                'max_loan_amount' => $max_loan,
                'approval_recommendation' => $this->getApprovalRecommendation($final_score)
            ],
            'assessment_date' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get member basic data
     */
    private function getMemberData($member_id) {
        $query = "SELECT * FROM members WHERE member_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Score 1: Payment History (0-100)
     * Based on repayment records
     */
    private function getPaymentHistoryScore($member_id) {
        $query = "SELECT 
            COUNT(DISTINCT r.repayment_id) as total_payments,
            AVG(r.overdue_count) as avg_overdue,
            SUM(CASE WHEN r.repayment_date <= r.next_due THEN 1 ELSE 0 END) as on_time_count,
            MAX(r.overdue_count) as max_overdue
        FROM repayments r
        JOIN loan_portfolio lp ON r.loan_id = lp.loan_id
        WHERE lp.member_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        // Walang payment history = neutral score
        if ($result['total_payments'] == 0) {
            return 60; // Default score para sa new members
        }
        
        $score = 100;
        
        // Deduct points based on overdue count
        if ($result['avg_overdue'] > 0) {
            $score -= ($result['avg_overdue'] * 10); // -10 points per average overdue
        }
        
        if ($result['max_overdue'] > 5) {
            $score -= 20; // Additional penalty for very late payments
        }
        
        // On-time payment bonus
        $on_time_rate = ($result['on_time_count'] / $result['total_payments']) * 100;
        if ($on_time_rate > 90) {
            $score += 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Score 2: Loan Performance (0-100)
     * Based on loan completion and status
     */
    private function getLoanPerformanceScore($member_id) {
        $query = "SELECT 
            COUNT(*) as total_loans,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_loans,
            SUM(CASE WHEN status = 'Defaulted' THEN 1 ELSE 0 END) as defaulted_loans,
            SUM(CASE WHEN status = 'Approved' OR status = 'Active' THEN 1 ELSE 0 END) as active_loans,
            SUM(principal_amount) as total_borrowed,
            AVG(interest_rate) as avg_interest_rate
        FROM loan_portfolio
        WHERE member_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        // Walang loan history = neutral
        if ($result['total_loans'] == 0) {
            return 65;
        }
        
        $score = 70; // Base score
        
        // Completed loans bonus
        $completion_rate = ($result['completed_loans'] / $result['total_loans']) * 100;
        $score += ($completion_rate * 0.3); // Up to +30 points
        
        // Defaulted loans penalty
        if ($result['defaulted_loans'] > 0) {
            $score -= ($result['defaulted_loans'] * 25); // -25 per default
        }
        
        // Active loans (good sign of engagement)
        if ($result['active_loans'] > 0 && $result['active_loans'] <= 3) {
            $score += 10;
        } elseif ($result['active_loans'] > 3) {
            $score -= 15; // Too many active loans = risky
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Score 3: Repayment Behavior (0-100)
     * Detailed repayment patterns
     */
    private function getRepaymentBehaviorScore($member_id) {
        $query = "SELECT 
            COUNT(r.repayment_id) as payment_count,
            SUM(r.amount) as total_paid,
            AVG(r.amount) as avg_payment,
            MIN(r.repayment_date) as first_payment,
            MAX(r.repayment_date) as last_payment,
            SUM(CASE WHEN r.overdue_count = 0 THEN 1 ELSE 0 END) as no_overdue_count
        FROM repayments r
        JOIN loan_portfolio lp ON r.loan_id = lp.loan_id
        WHERE lp.member_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['payment_count'] == 0) {
            return 60;
        }
        
        $score = 60;
        
        // Consistent payments bonus
        if ($result['payment_count'] > 10) {
            $score += 20;
        } elseif ($result['payment_count'] > 5) {
            $score += 10;
        }
        
        // No overdue bonus
        $no_overdue_rate = ($result['no_overdue_count'] / $result['payment_count']) * 100;
        $score += ($no_overdue_rate * 0.2); // Up to +20 points
        
        return max(0, min(100, $score));
    }
    
    /**
     * Score 4: Risk Indicators (0-100)
     * Red flags and warning signs
     */
    private function getRiskIndicatorScore($member_id) {
        $score = 100; // Start with perfect score
        
        // Check 1: Multiple defaulted loans
        $query1 = "SELECT COUNT(*) as count FROM loan_portfolio 
                   WHERE member_id = ? AND status = 'Defaulted'";
        $stmt1 = $this->conn->prepare($query1);
        $stmt1->bind_param('i', $member_id);
        $stmt1->execute();
        $defaults = $stmt1->get_result()->fetch_assoc()['count'];
        
        if ($defaults > 0) {
            $score -= ($defaults * 30); // -30 per default
        }
        
        // Check 2: High overdue count on recent payments
        $query2 = "SELECT AVG(overdue_count) as avg_overdue 
                   FROM repayments r
                   JOIN loan_portfolio lp ON r.loan_id = lp.loan_id
                   WHERE lp.member_id = ? 
                   AND r.repayment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->bind_param('i', $member_id);
        $stmt2->execute();
        $recent_overdue = $stmt2->get_result()->fetch_assoc()['avg_overdue'];
        
        if ($recent_overdue > 3) {
            $score -= 25; // Recent pattern of late payments
        }
        
        // Check 3: Too many active loans
        $query3 = "SELECT COUNT(*) as count FROM loan_portfolio 
                   WHERE member_id = ? AND status IN ('Approved', 'Active')";
        $stmt3 = $this->conn->prepare($query3);
        $stmt3->bind_param('i', $member_id);
        $stmt3->execute();
        $active_count = $stmt3->get_result()->fetch_assoc()['count'];
        
        if ($active_count > 5) {
            $score -= 20; // Overextended
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Score 5: Savings Score (0-100)
     * Based on savings account if available
     */
    private function getSavingsScore($member_id) {
        // Note: Adjust based on your savings table structure
        $query = "SELECT 
            COUNT(*) as transaction_count,
            SUM(amount) as total_savings
        FROM savings
        WHERE member_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result || $result['transaction_count'] == 0) {
            return 50; // Neutral kung walang savings
        }
        
        $score = 50;
        
        // Bonus for having savings
        if ($result['total_savings'] > 10000) {
            $score += 30;
        } elseif ($result['total_savings'] > 5000) {
            $score += 20;
        } elseif ($result['total_savings'] > 1000) {
            $score += 10;
        }
        
        // Bonus for regular savings
        if ($result['transaction_count'] > 20) {
            $score += 20;
        } elseif ($result['transaction_count'] > 10) {
            $score += 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Determine risk category based on score
     */
    private function getRiskCategory($score) {
        if ($score >= 85) return 'Excellent';
        if ($score >= 75) return 'Very Good';
        if ($score >= 65) return 'Good';
        if ($score >= 55) return 'Fair';
        if ($score >= 45) return 'Poor';
        return 'Very Poor';
    }
    
    /**
     * Recommend interest rate based on score
     */
    private function getRecommendedRate($score) {
        if ($score >= 85) return 1.00;  // 1% para sa excellent
        if ($score >= 75) return 1.25;
        if ($score >= 65) return 1.50;
        if ($score >= 55) return 2.00;
        if ($score >= 45) return 2.50;
        return 3.00; // Maximum rate para sa very poor
    }
    
    /**
     * Get maximum recommended loan amount
     */
    private function getMaxLoanAmount($score, $member_id) {
        // Base sa average ng previous successful loans
        $query = "SELECT AVG(principal_amount) as avg_loan
                  FROM loan_portfolio
                  WHERE member_id = ? AND status = 'Completed'";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $base_amount = $result['avg_loan'] ?? 10000;
        
        // Multiply based on credit score
        if ($score >= 85) {
            $multiplier = 3.0;  // 3x ng average
        } elseif ($score >= 75) {
            $multiplier = 2.5;
        } elseif ($score >= 65) {
            $multiplier = 2.0;
        } elseif ($score >= 55) {
            $multiplier = 1.5;
        } elseif ($score >= 45) {
            $multiplier = 1.0;
        } else {
            $multiplier = 0.5;  // Limited amount para sa poor rating
        }
        
        return round($base_amount * $multiplier, -3); // Round to nearest thousand
    }
    
    /**
     * Get approval recommendation
     */
    private function getApprovalRecommendation($score) {
        if ($score >= 75) {
            return 'APPROVE - Excellent credit profile';
        } elseif ($score >= 65) {
            return 'APPROVE - Good credit standing';
        } elseif ($score >= 55) {
            return 'CONDITIONAL - Require additional collateral';
        } elseif ($score >= 45) {
            return 'REVIEW - High risk, needs manual review';
        } else {
            return 'REJECT - Credit score too low';
        }
    }
    
    /**
     * Save AI score to loan_portfolio table
     * (Add new columns: ai_credit_score, ai_risk_category, ai_assessment_date)
     */
    private function saveAIScore($member_id, $score, $category) {
        // Update active loans with AI score
        $query = "UPDATE loan_portfolio 
                  SET ai_credit_score = ?,
                      ai_risk_category = ?,
                      ai_assessment_date = NOW()
                  WHERE member_id = ? 
                  AND status IN ('Pending', 'Approved', 'Active')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isi', $score, $category, $member_id);
        
        try {
            $stmt->execute();
        } catch (Exception $e) {
            // Table might not have these columns yet
            // Run the migration SQL first
        }
    }
    
    /**
     * Bulk calculate scores for all active members
     * Useful for monthly recalculation
     */
    public function calculateAllScores() {
        $query = "SELECT DISTINCT member_id FROM members WHERE status = 'Active'";
        $result = $this->conn->query($query);
        
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = $this->calculateCreditScore($row['member_id']);
        }
        
        return $results;
    }
}
?>