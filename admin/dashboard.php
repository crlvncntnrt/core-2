<?php
// Start session FIRST to avoid headers already sent error
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../initialize_coreT2.php');
require_once(__DIR__ . '/inc/sess_auth.php');
require_once __DIR__ . '/inc/check_auth.php';

if (!isset($_SESSION['userdata'])) {
    header("Location: login.php");
    exit();
}

$user_fullname = $_SESSION['userdata']['full_name'] ?? 'User';
$user_role = $_SESSION['userdata']['role'] ?? 'User';

// Include layout files
include("inc/header.php");
include("inc/navbar.php");
include("inc/sidebar.php");
?>

<style>
    :root {
        --primary: #10b981;
        --primary-dark: #059669;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --info: #3b82f6;
        --purple: #8b5cf6;
        --orange: #f97316;
        --dark: #1f2937;
    }

    body {
        background: #f3f4f6;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .main-content {
        padding: 1.5rem;
    }

    /* Header */
    .page-header {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .page-header h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 0.25rem 0;
    }

    .page-header .subtitle {
        color: #6b7280;
        font-size: 0.875rem;
        margin: 0;
    }

    .update-time {
        text-align: right;
        font-size: 0.75rem;
        color: #6b7280;
    }

    .update-time .time {
        color: var(--primary);
        font-weight: 500;
    }

    /* KPI Cards */
    .kpi-card {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        border-left: 4px solid;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        height: 100%;
    }

    .kpi-card::before {
        content: '';
        position: absolute;
        top: -15px;
        right: -15px;
        width: 80px;
        height: 80px;
        opacity: 0.05;
        border-radius: 50%;
    }

    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .kpi-card.active {
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        transform: translateY(-2px);
    }

    /* Card Colors */
    .kpi-card.primary {
        border-color: var(--primary);
    }

    .kpi-card.primary::before {
        background: var(--primary);
    }

    .kpi-card.primary .kpi-icon {
        background: var(--primary);
    }

    .kpi-card.success {
        border-color: var(--success);
    }

    .kpi-card.success::before {
        background: var(--success);
    }

    .kpi-card.success .kpi-icon {
        background: var(--success);
    }

    .kpi-card.info {
        border-color: var(--info);
    }

    .kpi-card.info::before {
        background: var(--info);
    }

    .kpi-card.info .kpi-icon {
        background: var(--info);
    }

    .kpi-card.orange {
        border-color: var(--orange);
    }

    .kpi-card.orange::before {
        background: var(--orange);
    }

    .kpi-card.orange .kpi-icon {
        background: var(--orange);
    }

    .kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 0.75rem;
        color: white;
    }

    .kpi-title {
        font-size: 0.7rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .kpi-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #111827;
        margin: 0.5rem 0;
        line-height: 1;
    }

    .kpi-subtitle {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }

    /* Chart Cards */
    .chart-card {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        height: 100%;
        transition: all 0.3s ease;
    }

    .chart-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f3f4f6;
    }

    .chart-title {
        font-weight: 600;
        color: #111827;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-title i {
        color: var(--primary);
        font-size: 1rem;
    }

    .chart-period {
        font-size: 0.7rem;
        color: #6b7280;
        background: #f9fafb;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }

    .chart-container {
        height: 280px;
        position: relative;
    }

    /* ✅ FIXED: AI Section Styles */
    .ai-widget {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 1.5rem;
        color: white;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        max-height: 680px;
        overflow: auto;
        
    }

    .ai-widget h5 {
        margin: 0 0 1rem 0;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .ai-kpi-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .ai-kpi-mini {
        flex: 1;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border-radius: 8px;
        padding: 0.75rem;
        text-align: center;
    }

    .ai-kpi-mini .label {
        font-size: 0.7rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .ai-kpi-mini .value {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .ai-kpi-mini .sub {
        font-size: 0.7rem;
        opacity: 0.8;
        margin-top: 0.25rem;
    }

   
    .ai-chart-wrapper {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
        height: 250px;
        overflow: hidden;
    }


    .ai-chart-wrapper canvas {
        max-height: 180px !important;
    }

  
    .ai-high-risk-table {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 1rem;
        max-height: 300px;
        overflow-y: auto;
    }

    .ai-high-risk-table table {
        width: 100%;
        margin: 0;
    }

    .ai-high-risk-table thead {
        background: #f9fafb;
    }

    .ai-high-risk-table th {
        padding: 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        border-bottom: 2px solid #e5e7eb;
    }

    .ai-high-risk-table td {
        padding: 0.75rem;
        font-size: 0.85rem;
        color: #111827;
        border-bottom: 1px solid #f3f4f6;
    }

    .opacity-25 {
        opacity: 0.25;
    }

    .btn-purple {
        background-color: #8b5cf6;
        color: white;
        border: none;
    }

    .btn-purple:hover {
        background-color: #7c3aed;
        color: white;
    }

    /* Detail Panel */
    .detail-panel {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        margin-top: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f3f4f6;
    }

    .detail-header h4 {
        font-weight: 600;
        color: #111827;
        font-size: 1rem;
        margin: 0;
    }

    .detail-header .subtitle {
        color: #6b7280;
        font-size: 0.8rem;
        margin: 0;
    }

    .table-container {
        max-height: 450px;
        overflow: auto;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .table {
        margin: 0;
        font-size: 0.85rem;
    }

    .table thead {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f9fafb;
    }

    .table thead th {
        border-bottom: 2px solid #e5e7eb;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #6b7280;
        padding: 0.75rem 1rem;
        white-space: nowrap;
    }

    .table tbody td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: #f9fafb;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Compliance Section */
    .compliance-card {
        margin-top: 1.5rem;
    }

    .audit-list {
        max-height: 380px;
        overflow-y: auto;
    }

    .list-group-item {
        border: 1px solid #e5e7eb;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
        font-size: 0.85rem;
    }

    .list-group-item:hover {
        background: #f9fafb;
    }

    .list-group-item strong {
        color: #111827;
    }

    .list-group-item small {
        color: #6b7280;
        font-size: 0.75rem;
    }

    /* Loading & Empty States */
    .loading,
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6b7280;
    }

    .loading::after {
        content: '';
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #e5e7eb;
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-left: 0.5rem;
        vertical-align: middle;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .empty-state i {
        font-size: 2.5rem;
        color: #d1d5db;
        margin-bottom: 1rem;
        display: block;
    }

    .empty-state h5 {
        color: #6b7280;
        margin-bottom: 0.5rem;
        font-size: 1rem;
    }

    .empty-state p {
        color: #9ca3af;
        font-size: 0.85rem;
        margin: 0;
    }

    /* Buttons */
    .btn-sm {
        padding: 0.4rem 1rem;
        font-size: 0.8rem;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn-outline-primary {
        color: var(--primary);
        border-color: var(--primary);
    }

    .btn-outline-primary:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .btn-outline-success {
        color: var(--success);
        border-color: var(--success);
    }

    .btn-outline-success:hover {
        background: var(--success);
        border-color: var(--success);
        color: white;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
        }

        .page-header {
            padding: 1rem;
        }

        .page-header h1 {
            font-size: 1.5rem;
        }

        .kpi-value {
            font-size: 1.5rem;
        }

        .chart-container {
            height: 220px;
        }

        .detail-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .btn-group {
            width: 100%;
            display: flex;
        }

        .btn-group .btn {
            flex: 1;
        }

        .ai-kpi-row {
            flex-direction: column;
        }
    }

    /* Scrollbar Styling */
    .table-container::-webkit-scrollbar,
    .audit-list::-webkit-scrollbar,
    .ai-widget::-webkit-scrollbar,
    .ai-high-risk-table::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .table-container::-webkit-scrollbar-track,
    .audit-list::-webkit-scrollbar-track,
    .ai-widget::-webkit-scrollbar-track,
    .ai-high-risk-table::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 4px;
    }

    .table-container::-webkit-scrollbar-thumb,
    .audit-list::-webkit-scrollbar-thumb,
    .ai-widget::-webkit-scrollbar-thumb,
    .ai-high-risk-table::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 4px;
    }

    .table-container::-webkit-scrollbar-thumb:hover,
    .audit-list::-webkit-scrollbar-thumb:hover,
    .ai-widget::-webkit-scrollbar-thumb:hover,
    .ai-high-risk-table::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
</style>

<!-- ✅ FIXED: Added main-wrap wrapper to prevent sidebar overlap -->
<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid">

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1>Dashboard Overview</h1>
                        <p class="subtitle">Welcome back, <?php echo htmlspecialchars($user_fullname); ?>! Here's your microfinance system overview.</p>
                    </div>
                    <div class="col-md-4">
                        <div class="update-time">
                            <div><?php echo date('l, F j, Y'); ?></div>
                            <div class="time">Last updated: <span id="lastUpdateTime">Just now</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ✅ AI CREDIT SCORING WIDGET -->
            <div class="ai-widget">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="bi bi-robot"></i> AI Credit Scoring Overview</h5>
                </div>

                <div class="ai-kpi-row">
                    <div class="ai-kpi-mini">
                        <div class="label">Avg Score</div>
                        <div class="value" id="ai_avg_score">--</div>
                        <div class="sub">out of 100</div>
                    </div>
                    <div class="ai-kpi-mini">
                        <div class="label">High Risk</div>
                        <div class="value" id="ai_high_risk">--</div>
                        <div class="sub">members</div>
                    </div>
                    <div class="ai-kpi-mini">
                        <div class="label">Excellent</div>
                        <div class="value" id="ai_excellent">--</div>
                        <div class="sub">score ≥ 85</div>
                    </div>
                    <div class="ai-kpi-mini">
                        <div class="label">Assessed Today</div>
                        <div class="value" id="ai_assessed">--</div>
                        <div class="sub">members</div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-8">
                        <div class="ai-chart-wrapper">
                            <h6 style="margin-bottom: 1rem; font-size: 0.9rem;">Score Distribution</h6>
                            <canvas id="aiScoreDistChart" height="120"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ai-chart-wrapper">
                            <h6 style="margin-bottom: 1rem; font-size: 0.9rem;">Risk Categories</h6>
                            <canvas id="aiRiskPieChart" height="120"></canvas>
                        </div>
                    </div>
                </div>

                <div class="ai-high-risk-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Score</th>
                                <th>Risk</th>
                                <th>Defaults</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="ai_high_risk_tbody">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 1rem;">
                                    <div class="spinner-border spinner-border-sm" role="status"></div> Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- AI CONTROL PANEL -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-robot"></i> AI Credit Scoring Controls
                                </h5>
                                <button class="btn btn-sm btn-light" onclick="toggleAIControlPanel()">
                                    <i class="bi bi-gear"></i> Manage AI Scores
                                </button>
                            </div>
                        </div>
                        <div id="aiControlPanel" class="collapse">
                            <div class="card-body">
                                <div class="row">

                                    <div class="col-md-6">
                                        <h6 class="font-weight-bold mb-3">
                                            <i class="bi bi-lightning-fill text-warning"></i> Quick Actions
                                        </h6>

                                        <button class="btn btn-primary btn-block mb-2" onclick="calculateAllScores()">
                                            <i class="bi bi-calculator"></i> Calculate All Member Scores
                                        </button>

                                        <button class="btn btn-info btn-block mb-2" onclick="recalculateOldScores()">
                                            <i class="bi bi-arrow-repeat"></i> Recalculate Scores Older Than 30 Days
                                        </button>

                                        <button class="btn btn-success btn-block mb-2" onclick="showScoringModal()">
                                            <i class="bi bi-person-check"></i> Calculate Single Member Score
                                        </button>

                                        <div id="bulk-action-status" class="mt-3"></div>
                                    </div>

                                    <div class="col-md-6">
                                        <h6 class="font-weight-bold mb-3">
                                            <i class="bi bi-graph-up text-info"></i> AI Statistics
                                        </h6>

                                        <div class="bg-light p-3 rounded">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Total Members:</span>
                                                <strong id="stat-total-members">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Scored Members:</span>
                                                <strong id="stat-scored-members" class="text-success">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Unscored Members:</span>
                                                <strong id="stat-unscored-members" class="text-warning">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Scores > 30 Days Old:</span>
                                                <strong id="stat-old-scores" class="text-danger">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Last Calculation:</span>
                                                <strong id="stat-last-calc" class="text-muted">-</strong>
                                            </div>
                        </div>

                                        <button class="btn btn-sm btn-outline-secondary btn-block mt-2" onclick="refreshEnhancedAIStats()">
                                            <i class="bi bi-arrow-clockwise"></i> Refresh Statistics
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ✅ FIXED: SINGLE MEMBER SCORING MODAL WITH WORKING CLOSE BUTTON -->
            <div class="modal fade" id="singleScoringModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header" style="background: #667eea; color: white;">
                            <h5 class="modal-title">
                                <i class="bi bi-person-check"></i> Calculate Member Credit Score
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1; color: white !important; font-size: 2rem; font-weight: 300;">
                                <span aria-hidden="true" style="color: white;">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Select Member:</label>
                                <select id="single-member-select" class="form-control">
                                    <option value="">-- Choose a member --</option>
                                </select>
                            </div>

                            <div id="single-member-result" style="display:none;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="calculateSingleScore()">
                                <i class="bi bi-calculator"></i> Calculate Score
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- KPI Cards -->
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="kpi-card primary" data-type="members">
                        <div class="kpi-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="kpi-title">Total Members</div>
                        <div class="kpi-value" id="card_members">0</div>
                        <div class="kpi-subtitle">Active members</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="kpi-card success" data-type="loans" data-filter="Active">
                        <div class="kpi-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="kpi-title">Active Loans</div>
                        <div class="kpi-value" id="card_loans">0</div>
                        <div class="kpi-subtitle">Currently active</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="kpi-card info" data-type="savings">
                        <div class="kpi-icon">
                            <i class="bi bi-piggy-bank-fill"></i>
                        </div>
                        <div class="kpi-title">Total Savings</div>
                        <div class="kpi-value" id="card_savings">₱0</div>
                        <div class="kpi-subtitle">Member savings</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="kpi-card orange" data-type="disbursements" data-filter="Released">
                        <div class="kpi-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div class="kpi-title">Total Disbursed</div>
                        <div class="kpi-value" id="card_disbursed">₱0</div>
                        <div class="kpi-subtitle">Released funds</div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">
                                <i class="bi bi-pie-chart-fill"></i>
                                Loan Portfolio
                            </div>
                            <div class="chart-period">Live</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="chartLoanStatus"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">
                                <i class="bi bi-graph-up"></i>
                                Monthly Collections
                            </div>
                            <div class="chart-period"><?php echo date('Y'); ?></div>
                        </div>
                        <div class="chart-container">
                            <canvas id="chartCollections"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">
                                <i class="bi bi-cash-coin"></i>
                                Loan Disbursements
                            </div>
                            <div class="chart-period"><?php echo date('Y'); ?></div>
                        </div>
                        <div class="chart-container">
                            <canvas id="chartDisbursements"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">
                                <i class="bi bi-shield-check"></i>
                                Compliance Status
                            </div>
                            <div class="chart-period">Overview</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="chartCompliance"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Panel -->
            <div class="detail-panel">
                <div class="detail-header">
                    <div>
                        <h4 id="detail_title">Detailed Records</h4>
                        <p class="subtitle" id="detail_subtitle">Click any KPI card to view detailed records</p>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn_refresh">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btn_export" disabled>
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>

                <div class="table-container" id="detail_container">
                    <div class="empty-state">
                        <i class="bi bi-cursor"></i>
                        <h5>No Selection</h5>
                        <p>Click any KPI card above to view detailed records</p>
                    </div>
                </div>
            </div>

            <!-- Compliance Section -->
            <div class="compliance-card">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-title">
                                    <i class="bi bi-table"></i>
                                    Compliance Records
                                </div>
                                <div class="chart-period">Latest 20</div>
                            </div>
                            <div style="max-height: 380px; overflow: auto;" id="compliance_table_container">
                                <div class="loading">Loading compliance records...</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-title">
                                    <i class="bi bi-clock-history"></i>
                                    Recent Activity
                                </div>
                                <div class="chart-period">Last 8</div>
                            </div>
                            <div class="audit-list" id="recent_audit_container">
                                <div class="loading">Loading audit trail...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    function toggleAIControlPanel() {
        const panel = document.getElementById('aiControlPanel');
        if (panel.classList.contains('show')) {
            panel.classList.remove('show');
        } else {
            panel.classList.add('show');
        }
    }

    (async function() {
        let dashboardData = null;
        let currentDetailType = null;
        let currentDetailFilter = null;
        let chartInstances = {};

        // ✅ SESSION TIMEOUT FIX: Track user activity
        let lastUserActivity = Date.now();
        let autoRefreshInterval = null;

        const fmt = n => new Intl.NumberFormat('en-PH').format(n);
        const fmtCurrency = n => '₱' + fmt(parseFloat(n).toFixed(2));

        // ✅ Reset activity timer on ANY user action
        function resetActivityTimer() {
            lastUserActivity = Date.now();
        }

        // ✅ Listen for REAL user activity (not auto-refresh)
        ['mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(event => {
            document.addEventListener(event, resetActivityTimer, { passive: true });
        });

        function updateLastUpdateTime() {
            document.getElementById('lastUpdateTime').textContent =
                new Date().toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
        }

        async function loadDashboardData() {
            try {
                const res = await fetch('ajax_dashboard_data.php', {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });

                // ✅ Check if session expired
                if (res.status === 401) {
                    clearInterval(autoRefreshInterval);
                    alert('Session expired. Redirecting to login...');
                    window.location.href = '/admin/login.php?timeout=1';
                    return;
                }

                dashboardData = await res.json();

                // ✅ Check for session_expired flag
                if (dashboardData && dashboardData.session_expired === true) {
                    clearInterval(autoRefreshInterval);
                    alert('Session expired. Redirecting to login...');
                    window.location.href = '/admin/login.php';
                    return;
                }

                if (!dashboardData || dashboardData.status !== 'success') {
                    console.error('Dashboard error:', dashboardData);
                    return;
                }

                updateDashboardUI();
                updateLastUpdateTime();
            } catch (error) {
                console.error('Failed to load dashboard:', error);
            }
        }

        async function loadAIDashboardData() {
            try {
                const res = await fetch('ajax_ai_dashboard.php', {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });

                // ✅ Check if session expired
                if (res.status === 401) {
                    clearInterval(autoRefreshInterval);
                    return;
                }

                const data = await res.json();

                if (data.error) {
                    console.error('AI Dashboard error:', data.error);
                    return;
                }

                document.getElementById('ai_avg_score').textContent = data.avg_score || '0';
                document.getElementById('ai_high_risk').textContent = data.high_risk_count || '0';
                document.getElementById('ai_excellent').textContent = data.excellent_count || '0';
                document.getElementById('ai_assessed').textContent = data.assessed_today || '0';

                updateAIScoreDistChart(data.distribution);
                updateAIRiskPieChart(data.risk_categories);
                updateAIHighRiskTable(data.high_risk_members);

            } catch (error) {
                console.error('Failed to load AI dashboard:', error);
            }
        }

        function updateAIScoreDistChart(distribution) {
            const ctx = document.getElementById('aiScoreDistChart');
            if (!ctx) return;

            if (chartInstances.aiScoreDist) chartInstances.aiScoreDist.destroy();

            chartInstances.aiScoreDist = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['0-20', '21-40', '41-55', '56-64', '65-74', '75-84', '85-100'],
                    datasets: [{
                        label: 'Members',
                        data: [
                            distribution.range_0_20 || 0,
                            distribution.range_21_40 || 0,
                            distribution.range_41_55 || 0,
                            distribution.range_56_64 || 0,
                            distribution.range_65_74 || 0,
                            distribution.range_75_84 || 0,
                            distribution.range_85_100 || 0
                        ],
                        backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#eab308', '#10b981', '#3b82f6', '#8b5cf6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.parsed.y + ' members'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function updateAIRiskPieChart(categories) {
            const ctx = document.getElementById('aiRiskPieChart');
            if (!ctx) return;

            if (chartInstances.aiRiskPie) chartInstances.aiRiskPie.destroy();

            chartInstances.aiRiskPie = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Excellent', 'Very Good', 'Good', 'Fair', 'Poor'],
                    datasets: [{
                        data: [
                            categories.excellent || 0,
                            categories.very_good || 0,
                            categories.good || 0,
                            categories.fair || 0,
                            (categories.poor || 0) + (categories.very_poor || 0)
                        ],
                        backgroundColor: ['#8b5cf6', '#3b82f6', '#10b981', '#eab308', '#ef4444'],
                        borderWidth: 2,
                        borderColor: 'rgba(255,255,255,0.3)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: 'rgba(255,255,255,0.9)',
                                font: {
                                    size: 10
                                },
                                padding: 8
                            }
                        }
                    }
                }
            });
        }

        function updateAIHighRiskTable(members) {
            const tbody = document.getElementById('ai_high_risk_tbody');
            if (!tbody) return;

            if (!members || members.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #6b7280;">No high risk members</td></tr>';
                return;
            }

            let html = '';
            members.slice(0, 5).forEach(member => {
                const badgeClass = member.ai_credit_score < 45 ? 'danger' : 'warning';
                html += `
                <tr>
                    <td><strong>${member.full_name}</strong></td>
                    <td><span class="badge badge-${badgeClass}">${member.ai_credit_score}</span></td>
                    <td>${member.ai_risk_category || 'N/A'}</td>
                    <td class="text-danger"><strong>${member.defaulted_count}</strong></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="window.location.href='member_assessment.php?member_id=${member.member_id}'">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
            });
            tbody.innerHTML = html;
        }

        function updateDashboardUI() {
            const d = dashboardData;

            document.getElementById('card_members').textContent = fmt(d.total_members || 0);
            document.getElementById('card_loans').textContent = fmt(d.active_loans || 0);
            document.getElementById('card_savings').textContent = fmtCurrency(d.total_savings || 0);
            document.getElementById('card_disbursed').textContent = fmtCurrency(d.total_disbursed || 0);

            const compTable = document.getElementById('compliance_table_container');
            if (compTable) {
                compTable.innerHTML = d.compliance_table_html ||
                    '<div class="empty-state"><i class="bi bi-inbox"></i><h5>No Data</h5><p>No compliance records</p></div>';
            }

            const audit = document.getElementById('recent_audit_container');
            if (audit) {
                audit.innerHTML = d.recent_audit_html ||
                    '<div class="empty-state"><i class="bi bi-inbox"></i><h5>No Activity</h5><p>No recent activity</p></div>';
            }

            updateCharts();
        }

        function updateCharts() {
            updateLoanChart(dashboardData);
            updateCollectionsChart(dashboardData);
            updateDisbursementsChart(dashboardData);
            updateComplianceChart(dashboardData);
        }

        function updateLoanChart(d) {
            const ctx = document.getElementById('chartLoanStatus');
            if (!ctx) return;

            if (chartInstances.loan) chartInstances.loan.destroy();

            const data = d.loan_chart || {
                labels: [],
                values: []
            };

            chartInstances.loan = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 12,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.label + ': ' + ctx.parsed + ' loans'
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        function updateCollectionsChart(d) {
            const ctx = document.getElementById('chartCollections');
            if (!ctx) return;

            if (chartInstances.collections) chartInstances.collections.destroy();

            const data = d.collection_chart || {
                labels: [],
                values: []
            };

            chartInstances.collections = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Collections',
                        data: data.values,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Collections: ₱' + fmt(ctx.parsed.y.toFixed(2))
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => '₱' + (v >= 1000 ? (v / 1000) + 'K' : v)
                            }
                        }
                    }
                }
            });
        }

        function updateDisbursementsChart(d) {
            const ctx = document.getElementById('chartDisbursements');
            if (!ctx) return;

            if (chartInstances.disbursements) chartInstances.disbursements.destroy();

            const data = d.loan_disbursement_chart || {
                labels: [],
                values: []
            };

            chartInstances.disbursements = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Disbursements',
                        data: data.values,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: '#3b82f6',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Disbursed: ₱' + fmt(ctx.parsed.y.toFixed(2))
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => '₱' + (v >= 1000 ? (v / 1000) + 'K' : v)
                            }
                        }
                    }
                }
            });
        }

        function updateComplianceChart(d) {
            const ctx = document.getElementById('chartCompliance');
            if (!ctx) return;

            if (chartInstances.compliance) chartInstances.compliance.destroy();

            const data = d.compliance_chart || {
                labels: [],
                values: []
            };

            chartInstances.compliance = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 12,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }

        async function loadDetailData(type, filter = '') {
            currentDetailType = type;
            currentDetailFilter = filter;

            const container = document.getElementById('detail_container');
            const title = document.getElementById('detail_title');
            const subtitle = document.getElementById('detail_subtitle');

            const typeNames = {
                'members': 'Active Members',
                'loans': 'Loan Portfolio',
                'savings': 'Savings Transactions',
                'disbursements': 'Disbursements',
                'overdue': 'Overdue Loans',
                'defaulted': 'Defaulted Loans',
                'pending': 'Pending Loans',
                'repayments': "Today's Repayments"
            };

            if (title) title.textContent = typeNames[type] || 'Details';
            if (subtitle) subtitle.textContent = filter ? `Filter: ${filter}` : 'All records';
            if (container) container.innerHTML = '<div class="loading">Loading records...</div>';

            const exportBtn = document.getElementById('btn_export');
            if (exportBtn) exportBtn.disabled = true;

            try {
                const params = new URLSearchParams({
                    type,
                    filter
                });
                const res = await fetch(`ajax_dashboard_details.php?${params}`, {
                    cache: 'no-store'
                });
                const data = await res.json();

                if (!data || data.status !== 'success') {
                    throw new Error('Failed to load');
                }

                if (!data.rows || data.rows.length === 0) {
                    if (container) {
                        container.innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><h5>No Records</h5><p>No records found</p></div>';
                    }
                    return;
                }

                const columns = data.columns || Object.keys(data.rows[0] || {});
                let html = '<table class="table table-hover table-sm"><thead><tr>';

                columns.forEach(col => {
                    html += `<th>${col}</th>`;
                });
                html += '</tr></thead><tbody>';

                data.rows.forEach(row => {
                    html += '<tr>';
                    columns.forEach(col => {
                        let value = row[col];
                        if (value === null || value === undefined) value = '-';
                        html += `<td>${value}</td>`;
                    });
                    html += '</tr>';
                });
                html += '</tbody></table>';

                if (container) container.innerHTML = html;
                if (exportBtn) exportBtn.disabled = false;

            } catch (error) {
                console.error('Error loading details:', error);
                if (container) {
                    container.innerHTML = '<div class="alert alert-danger m-3">Error loading details</div>';
                }
            }
        }

        document.querySelectorAll('.kpi-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.kpi-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                loadDetailData(card.dataset.type, card.dataset.filter || '');
            });
        });

        const refreshBtn = document.getElementById('btn_refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                loadDashboardData();
                loadAIDashboardData();
                if (currentDetailType) {
                    loadDetailData(currentDetailType, currentDetailFilter);
                }
            });
        }

        // ✅ INITIAL LOAD
        await loadDashboardData();
        await loadAIDashboardData();

        // ✅ SMART AUTO-REFRESH: Only if user is active!
        autoRefreshInterval = setInterval(() => {
            const timeSinceActivity = Date.now() - lastUserActivity;
            const inactivitySeconds = Math.floor(timeSinceActivity / 1000);

            // Only refresh if user was active in last 90 seconds (less than 120-second timeout)
            if (inactivitySeconds < 90) {
                console.log('User active - refreshing dashboard');
                loadDashboardData();
                loadAIDashboardData();
            } else {
                // User is idle - STOP auto-refresh
                console.log('User idle (' + inactivitySeconds + 's) - stopping auto-refresh');
                clearInterval(autoRefreshInterval);
            }
        }, 60000); // Check every 60 seconds

    })();

    function refreshEnhancedAIStats() {
        $.ajax({
            url: 'ajax_ai_statistics.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#stat-total-members').text(data.total_members);
                    $('#stat-scored-members').text(data.scored_members);
                    $('#stat-unscored-members').text(data.unscored_members);
                    $('#stat-old-scores').text(data.old_scores);
                    $('#stat-last-calc').text(data.last_calculation || 'Never');
                }
            },
            error: function() {
                console.error('Failed to load AI statistics');
            }
        });
    }

    function calculateAllScores() {
        if (!confirm('This will calculate AI scores for ALL active members. This may take a few moments. Continue?')) {
            return;
        }

        $('#bulk-action-status').html('<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Calculating scores... Please wait.</div>');

        $.ajax({
            url: 'calculate_all_ai_scores_ajax.php',
            method: 'POST',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#bulk-action-status').html('<div class="alert alert-success"><strong>✅ Success!</strong><br>Calculated scores for ' + data.successful + ' out of ' + data.total + ' members.</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#bulk-action-status').html('<div class="alert alert-danger"><strong>❌ Error:</strong> ' + data.error + '</div>');
                }
            }
        });
    }

    function recalculateOldScores() {
        if (!confirm('This will recalculate scores that are older than 30 days. Continue?')) {
            return;
        }

        $('#bulk-action-status').html('<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Recalculating old scores...</div>');

        $.ajax({
            url: 'recalculate_old_scores_ajax.php',
            method: 'POST',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#bulk-action-status').html('<div class="alert alert-success"><strong>✅ Success!</strong><br>Recalculated ' + data.updated + ' scores.</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#bulk-action-status').html('<div class="alert alert-danger"><strong>❌ Error:</strong> ' + data.error + '</div>');
                }
            }
        });
    }

    function showScoringModal() {
        $('#singleScoringModal').modal('show');
        $('#single-member-result').hide();
        loadMembersList();
    }

    function loadMembersList() {
        $.ajax({
            url: 'ajax_get_members_list.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success && data.members) {
                    var options = '<option value="">-- Choose a member --</option>';
                    data.members.forEach(function(member) {
                        var displayName = member.full_name;
                        if (member.member_code) {
                            displayName += ' (' + member.member_code + ')';
                        }
                        options += '<option value="' + member.member_id + '">' + displayName + '</option>';
                    });
                    $('#single-member-select').html(options);
                } else {
                    console.error('Failed to load members:', data);
                    $('#single-member-select').html('<option value="">Error loading members</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $('#single-member-select').html('<option value="">Connection error</option>');
            }
        });
    }

    function calculateSingleScore() {
        var memberId = $('#single-member-select').val();

        if (!memberId) {
            alert('Please select a member first');
            return;
        }

        $('#single-member-result').html('<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Calculating credit score...</div>').show();

        $.ajax({
            url: 'calculate_single_score_ajax.php',
            method: 'POST',
            data: {
                member_id: memberId
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#singleScoringModal').modal('hide');
                    window.location.href = 'member_assessment.php?member_id=' + memberId;
                } else {
                    $('#single-member-result').html('<div class="alert alert-danger"><strong>❌ Error:</strong> ' + (data.error || 'Calculation failed') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#single-member-result').html('<div class="alert alert-danger"><strong>Connection Error:</strong> ' + error + '</div>');
            }
        });
    }

    $(document).ready(function() {
        refreshEnhancedAIStats();
        setInterval(function() {
            refreshEnhancedAIStats();
        }, 60000);
    });
</script>

<?php include("inc/footer.php"); ?>