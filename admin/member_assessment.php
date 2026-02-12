<?php
session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
require_once(__DIR__ . '/inc/sess_auth.php');
require_once(__DIR__ . '/inc/check_auth.php');
require_once(__DIR__ . '/ai_credit_scoring_function.php');

if (!isset($_SESSION['userdata'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['userdata'])) {
    echo json_encode(['error' => 'Session expired', 'redirect' => 'login.php']);
    exit();
}

$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$score_data = null;

if ($member_id > 0) {
    $score_data = calculate_ai_credit_score($member_id);
}

include("inc/header.php");
include("inc/navbar.php");
include("inc/sidebar.php");
?>

<style>
    .assessment-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    .assessment-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .assessment-header h1 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
    }
    
    .score-card {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .score-display {
        font-size: 5rem;
        font-weight: 900;
        line-height: 1;
        margin: 1rem 0;
    }
    
    .score-excellent { color: #8b5cf6; }
    .score-very-good { color: #3b82f6; }
    .score-good { color: #10b981; }
    .score-fair { color: #f59e0b; }
    .score-poor { color: #ef4444; }
    
    .risk-badge {
        display: inline-block;
        padding: 0.5rem 1.5rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 1.1rem;
        margin-top: 1rem;
    }
    
    .breakdown-section {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }
    
    .breakdown-item {
        margin-bottom: 1.5rem;
    }
    
    .breakdown-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    
    .progress-bar-custom {
        height: 25px;
        border-radius: 12px;
        background: #e5e7eb;
        overflow: hidden;
        position: relative;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        transition: width 1s ease;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 10px;
        color: white;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .recommendation-card {
        background: #f9fafb;
        border-left: 4px solid #667eea;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .recommendation-card h5 {
        margin: 0 0 0.5rem 0;
        color: #667eea;
        font-weight: 600;
    }
    
    .recommendation-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
    }
    
    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: white;
        border: 2px solid #667eea;
        color: #667eea;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .back-button:hover {
        background: #667eea;
        color: white;
        text-decoration: none;
    }
    
    .statistics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .stat-box {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        text-align: center;
    }
    
    .stat-label {
        font-size: 0.85rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }
    
    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #111827;
    }
</style>

<div class="main-wrap">
    <main class="main-content">
        <div class="assessment-container">
            
            <a href="dashboard.php" class="back-button">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            
            <div class="assessment-header">
                <h1><i class="bi bi-shield-check"></i> Credit Assessment Report</h1>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Comprehensive Financial Evaluation</p>
            </div>
            
            <?php if ($member_id == 0): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Please select a member from the dashboard to view their assessment.
                </div>
            <?php elseif (!$score_data || !$score_data['success']): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Error loading assessment data.
                </div>
            <?php else: ?>
                
                <!-- Score Display -->
                <div class="score-card">
                    <h3><?php echo htmlspecialchars($score_data['member_name']); ?></h3>
                    <div class="score-display score-<?php 
                        echo $score_data['credit_score'] >= 85 ? 'excellent' :
                             ($score_data['credit_score'] >= 75 ? 'very-good' :
                             ($score_data['credit_score'] >= 65 ? 'good' :
                             ($score_data['credit_score'] >= 55 ? 'fair' : 'poor')));
                    ?>">
                        <?php echo round($score_data['credit_score']); ?>
                    </div>
                    <span class="risk-badge badge-<?php 
                        echo $score_data['credit_score'] >= 85 ? 'success' :
                             ($score_data['credit_score'] >= 75 ? 'info' :
                             ($score_data['credit_score'] >= 65 ? 'primary' :
                             ($score_data['credit_score'] >= 55 ? 'warning' : 'danger')));
                    ?>">
                        <?php echo htmlspecialchars($score_data['risk_category']); ?>
                    </span>
                    <div style="margin-top: 1rem; color: #6b7280; font-size: 0.9rem;">
                        <i class="bi bi-calendar-check"></i> Assessed: <?php echo date('F j, Y g:i A'); ?>
                    </div>
                </div>
                
                <!-- Score Breakdown -->
                <div class="breakdown-section">
                    <h4 style="margin-bottom: 1.5rem;"><i class="bi bi-graph-up"></i> Score Breakdown</h4>
                    
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span>PAYMENT HISTORY</span>
                            <span><?php echo round($score_data['breakdown']['payment_history']); ?> / 100</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?php echo $score_data['breakdown']['payment_history']; ?>%">
                                35% Weight
                            </div>
                        </div>
                    </div>
                    
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span>CREDIT UTILIZATION</span>
                            <span><?php echo round($score_data['breakdown']['credit_utilization']); ?> / 100</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?php echo $score_data['breakdown']['credit_utilization']; ?>%">
                                30% Weight
                            </div>
                        </div>
                    </div>
                    
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span>LOAN DIVERSITY</span>
                            <span><?php echo round($score_data['breakdown']['loan_diversity']); ?> / 100</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?php echo $score_data['breakdown']['loan_diversity']; ?>%">
                                15% Weight
                            </div>
                        </div>
                    </div>
                    
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span>ACCOUNT AGE</span>
                            <span><?php echo round($score_data['breakdown']['account_age']); ?> / 100</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?php echo $score_data['breakdown']['account_age']; ?>%">
                                10% Weight
                            </div>
                        </div>
                    </div>
                    
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span>RECENT ACTIVITY</span>
                            <span><?php echo round($score_data['breakdown']['recent_activity']); ?> / 100</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?php echo $score_data['breakdown']['recent_activity']; ?>%">
                                10% Weight
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- AI Recommendations -->
                <div class="breakdown-section">
                    <h4 style="margin-bottom: 1.5rem;"><i class="bi bi-lightbulb"></i> AI Recommendations</h4>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="recommendation-card">
                                <h5>Interest Rate</h5>
                                <div class="recommendation-value"><?php echo $score_data['recommendations']['interest_rate']; ?>%</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="recommendation-card">
                                <h5>Max Loan Amount</h5>
                                <div class="recommendation-value">₱<?php echo number_format($score_data['recommendations']['max_loan_amount']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="recommendation-card">
                                <h5>Recommendation</h5>
                                <div class="recommendation-value" style="font-size: 1rem;">
                                    <?php echo htmlspecialchars($score_data['recommendations']['approval_recommendation']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Member Statistics -->
                <?php if (isset($score_data['statistics'])): ?>
                <div class="breakdown-section">
                    <h4 style="margin-bottom: 1.5rem;"><i class="bi bi-clipboard-data"></i> Member Statistics</h4>
                    
                    <div class="statistics-grid">
                        <div class="stat-box">
                            <div class="stat-label">Total Loans</div>
                            <div class="stat-value"><?php echo $score_data['statistics']['total_loans']; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Active Loans</div>
                            <div class="stat-value"><?php echo $score_data['statistics']['active_loans']; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Completed Loans</div>
                            <div class="stat-value text-success"><?php echo $score_data['statistics']['completed_loans']; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Defaulted Loans</div>
                            <div class="stat-value text-danger"><?php echo $score_data['statistics']['defaulted_loans']; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Total Payments</div>
                            <div class="stat-value"><?php echo $score_data['statistics']['total_payments']; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">On-Time Payments</div>
                            <div class="stat-value text-success"><?php echo $score_data['statistics']['on_time_payments']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
        </div>
    </main>
</div>

<?php include("inc/footer.php"); ?>