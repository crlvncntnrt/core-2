<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once __DIR__ . '/../inc/check_auth.php';

checkPermission('savings_monitoring');

include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<style>
    :root {
        --brand-primary: #059669;
        --brand-primary-hover: #047857;
        --brand-success: #10b981;
        --brand-warning: #f59e0b;
        --brand-danger: #ef4444;
        --brand-info: #3b82f6;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: #f9fafb;
    }

    .page-header {
        background: linear-gradient(135deg, var(--brand-primary) 0%, #047857 100%);
        padding: 2rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        color: white;
    }

    .page-header h4 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
    }

    .page-header .subtitle {
        opacity: 0.9;
        font-size: 0.95rem;
        margin-top: 0.25rem;
    }

    .stat-card {
        padding: 1.75rem;
        border-radius: 1rem;
        color: #fff;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        border: 2px solid transparent;
        overflow: hidden;
        background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
        box-shadow: var(--shadow-md);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        transform: translate(30%, -30%);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--shadow-xl);
    }

    .stat-card:hover::before {
        transform: translate(20%, -20%) scale(1.5);
    }

    .stat-card.active {
        border: 2px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2), var(--shadow-xl);
        transform: translateY(-8px) scale(1.05);
    }

    .stat-card.active::after {
        content: '✓ ACTIVE';
        position: absolute;
        top: 12px;
        right: 12px;
        font-size: 0.65rem;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.25);
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        letter-spacing: 0.05em;
    }

    .stat-card-icon {
        width: 3rem;
        height: 3rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .stat-title {
        font-size: 0.875rem;
        opacity: 0.95;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 2.25rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .stat-hint {
        font-size: 0.75rem;
        opacity: 0.8;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .stat-card[data-filter="all"] {
        --card-color-1: #3b82f6;
        --card-color-2: #2563eb;
    }

    .stat-card[data-filter="deposit"] {
        --card-color-1: #059669;
        --card-color-2: #047857;
    }

    .stat-card[data-filter="withdrawal"] {
        --card-color-1: #ef4444;
        --card-color-2: #dc2626;
    }

    .stat-card[data-filter="balance"] {
        --card-color-1: #8b5cf6;
        --card-color-2: #7c3aed;
    }

    .filter-section {
        background: white;
        padding: 1.5rem;
        border-radius: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
    }

    .filter-section .form-label {
        font-weight: 600;
        color: #374151;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .filter-section .form-control,
    .filter-section .form-select {
        border: 1.5px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    .table-card {
        background: white;
        padding: 1.5rem;
        border-radius: 1rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
    }

    .table-card .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f3f4f6;
    }

    .table-card .table-title {
        font-weight: 700;
        color: #111827;
        font-size: 1.125rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    #filterIndicator {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
    }

    #recordCount {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .table-wrapper {
        overflow-x: auto;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        color: #1f2937 !important;
        font-weight: 700;
        font-size: 0.875rem;
        padding: 1rem 0.75rem;
        border: none;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f3f4f6;
    }

    .table tbody tr:hover {
        background: #f9fafb;
        transform: scale(1.005);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .table tbody td {
        padding: 0.875rem 0.75rem;
        font-size: 0.875rem;
        color: #374151;
        vertical-align: middle;
    }

    .table .badge {
        padding: 0.375rem 0.75rem;
        font-weight: 600;
        font-size: 0.75rem;
        border-radius: 0.5rem;
    }

    .badge-deposit {
        background-color: #10b981;
        color: white;
    }

    .badge-withdrawal {
        background-color: #ef4444;
        color: white;
    }

    .btn {
        border-radius: 0.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn:active {
        transform: translateY(0);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .btn-sync.syncing .bi-arrow-repeat {
        display: inline-block;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid #f3f4f6;
    }

    #paginationInfo {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }

    #paginationControls .btn {
        margin-left: 0.5rem;
    }

    .modal-content {
        border-radius: 1rem;
        border: none;
        box-shadow: var(--shadow-xl);
    }

    .modal-header {
        border-bottom: 2px solid #f3f4f6;
        border-radius: 1rem 1rem 0 0;
    }

    .modal-header.bg-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
    }

    .modal-header.bg-info {
        background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
    }

    .modal-footer {
        border-top: 2px solid #f3f4f6;
    }

    #syncToast {
        animation: slideInRight 0.3s ease;
    }

    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
    }

    #breakdownTable thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .modal-xl {
        max-width: 1200px;
    }

    .btn-group-sm .btn {
        padding: 0.375rem 0.625rem;
        font-size: 0.8125rem;
    }

    @media (max-width: 768px) {
        .page-header { padding: 1.5rem; }
        .stat-card { padding: 1.25rem; }
        .stat-value { font-size: 1.75rem; }
        .filter-section { padding: 1rem; }
    }
</style>

<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">

            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4><i class="bi bi-piggy-bank me-2"></i>Savings Monitoring</h4>
                        <p class="subtitle mb-0">Track and monitor member savings deposits and withdrawals</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="syncCore1Btn" class="btn btn-sm btn-outline-light btn-sync" title="Pull latest savings from Core1">
                            <i class="bi bi-arrow-repeat"></i> Sync Core1
                        </button>
                        <button class="btn btn-sm btn-danger" id="exportPdfBtn">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </button>
                        <a id="exportCsvBtn" class="btn btn-sm btn-success" href="#">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                        </a>
                        <button class="btn btn-sm btn-primary" id="addTxBtn">
                            <i class="bi bi-plus-circle"></i> New Transaction
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="all">
                        <div class="stat-card-icon"><i class="bi bi-list-ul"></i></div>
                        <div class="stat-title">Total Transactions</div>
                        <div id="card_total_tx" class="stat-value">0</div>
                        <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to view all</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="deposit">
                        <div class="stat-card-icon"><i class="bi bi-arrow-down-circle"></i></div>
                        <div class="stat-title">Total Deposits</div>
                        <div id="card_total_deposit" class="stat-value">0</div>
                        <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="withdrawal">
                        <div class="stat-card-icon"><i class="bi bi-arrow-up-circle"></i></div>
                        <div class="stat-title">Total Withdrawals</div>
                        <div id="card_total_withdraw" class="stat-value">0</div>
                        <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="balance">
                        <div class="stat-card-icon"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-title">Current Balance</div>
                        <div id="card_balance" class="stat-value">₱0.00</div>
                        <div class="stat-hint"><i class="bi bi-info-circle"></i> Latest balance</div>
                    </div>
                </div>
            </div>

            <div class="filter-section">
                <div class="row g-3 align-items-end">

                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <select id="searchBy" class="form-select" style="max-width: 150px;">
                                <option value="auto" selected>Auto</option>
                                <option value="member_id">Member ID</option>
                                <option value="transaction_type">Type</option>
                                <option value="transaction_date">Date</option>
                                <option value="recorded_by_name">Recorded By</option>
                            </select>
                            <input id="searchInput" class="form-control" placeholder="Type here...">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Transaction Type</label>
                        <select id="typeFilter" class="form-select">
                            <option value="">All Types</option>
                            <option value="Deposit">Deposit</option>
                            <option value="Withdrawal">Withdrawal</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Member</label>
                        <select id="memberFilter" class="form-select">
                            <option value="">All Members</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Recorded By</label>
                        <select id="recordedByFilter" class="form-select">
                            <option value="">All Users</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" id="dateFrom" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" id="dateTo" class="form-control">
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">Rows</label>
                        <select id="rowsPerPage" class="form-select">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <button id="clearFilters" class="btn btn-outline-secondary w-100" type="button">Clear</button>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span>Savings Transactions</span>
                        <span id="filterIndicator" class="badge bg-info ms-2" style="display:none;"></span>
                    </h6>
                    <span id="recordCount"></span>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover align-middle text-center" id="savingsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Member ID</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Balance</th>
                                <th>Record By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div id="paginationInfo"></div>
                    <div id="paginationControls" class="btn-group"></div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="txModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="txForm" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Transaction</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Member ID</label>
                    <input type="number" name="member_id" id="member_id" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Transaction Date</label>
                    <input type="date" name="transaction_date" id="transaction_date" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Type</label>
                    <select name="transaction_type" id="transaction_type" class="form-select" required>
                        <option value="Deposit">Deposit</option>
                        <option value="Withdrawal">Withdrawal</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
                </div>
                <div class="form-text text-muted">Balance will be recalculated automatically.</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" type="submit">Save</button>
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- View Transaction Modal -->
<div class="modal fade" id="viewTxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>View Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <strong>Transaction ID:</strong>
                        <p class="mb-0" id="v_id"></p>
                    </div>
                    <div class="col-6">
                        <strong>Member ID:</strong>
                        <p class="mb-0" id="v_member"></p>
                    </div>
                    <div class="col-6">
                        <strong>Date:</strong>
                        <p class="mb-0" id="v_date"></p>
                    </div>
                    <div class="col-6">
                        <strong>Type:</strong>
                        <p class="mb-0" id="v_type"></p>
                    </div>
                    <div class="col-6">
                        <strong>Amount:</strong>
                        <p class="mb-0">₱<span id="v_amount"></span></p>
                    </div>
                    <div class="col-6">
                        <strong>Balance After:</strong>
                        <p class="mb-0">₱<span id="v_balance"></span></p>
                    </div>
                    <div class="col-12">
                        <strong>Recorded By:</strong>
                        <p class="mb-0" id="v_by"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Member Breakdown Modal -->
<div class="modal fade" id="breakdownModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-circle me-2"></i>
                    Member Transaction Breakdown
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="card bg-light mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="mb-1 text-primary">
                                    <i class="bi bi-person-badge"></i>
                                    <span id="bd_member_name">Loading...</span>
                                </h6>
                                <p class="mb-0 text-muted small">
                                    Member ID: <strong id="bd_member_id">-</strong>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="badge bg-success fs-6 px-3 py-2">
                                    Current Balance: ₱<span id="bd_current_balance">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-success h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-arrow-down-circle text-success fs-2"></i>
                                <h6 class="mt-2 mb-1 text-muted small">Total Deposits</h6>
                                <h4 class="mb-1 text-success" id="bd_total_deposits">₱0.00</h4>
                                <small class="text-muted"><span id="bd_deposit_count">0</span> transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-arrow-up-circle text-danger fs-2"></i>
                                <h6 class="mt-2 mb-1 text-muted small">Total Withdrawals</h6>
                                <h4 class="mb-1 text-danger" id="bd_total_withdrawals">₱0.00</h4>
                                <small class="text-muted"><span id="bd_withdrawal_count">0</span> transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-calculator text-info fs-2"></i>
                                <h6 class="mt-2 mb-1 text-muted small">Net Change</h6>
                                <h4 class="mb-1" id="bd_net_change">₱0.00</h4>
                                <small class="text-muted">Deposits - Withdrawals</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-primary h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-list-ol text-primary fs-2"></i>
                                <h6 class="mt-2 mb-1 text-muted small">Total Transactions</h6>
                                <h4 class="mb-1 text-primary" id="bd_total_txns">0</h4>
                                <small class="text-muted">All time</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Transaction History
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-hover table-sm mb-0" id="breakdownTable">
                                <thead class="sticky-top bg-dark text-white">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Balance After</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody id="breakdownTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                            <p class="mt-2 mb-0 text-muted">Loading transactions...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
                <button type="button" class="btn btn-success" id="exportMemberBtn">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Member Data
                </button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('#savingsTable tbody');
    const paginationControls = document.getElementById('paginationControls');
    const paginationInfo = document.getElementById('paginationInfo');

    const searchBy = document.getElementById('searchBy');
    const searchInput = document.getElementById('searchInput');

    const typeFilter = document.getElementById('typeFilter');
    const memberFilter = document.getElementById('memberFilter');
    const recordedByFilter = document.getElementById('recordedByFilter');

    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const rowsPerPage = document.getElementById('rowsPerPage');
    const clearFilters = document.getElementById('clearFilters');

    const exportCsvBtn = document.getElementById('exportCsvBtn');
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    const addTxBtn = document.getElementById('addTxBtn');

    const filterIndicator = document.getElementById('filterIndicator');
    const recordCount = document.getElementById('recordCount');

    const txModal = new bootstrap.Modal(document.getElementById('txModal'));
    const viewModal = new bootstrap.Modal(document.getElementById('viewTxModal'));
    const breakdownModal = new bootstrap.Modal(document.getElementById('breakdownModal'));

    const txForm = document.getElementById('txForm');

    let currentMemberData = null;

    let currentPage = 1;
    let limit = 10;
    let currentSearch = '';
    let currentCardFilter = 'all';

    let allTransactionsData = [];
    let summaryData = {};

    // ─── Sync Core1 Handler ───
    document.getElementById('syncCore1Btn').addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.classList.add('syncing');
        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Syncing...';

        fetch('../../api/saving_monitoring/savings_sync_api.php')
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showSyncToast(res.message, 'success');
                    loadFilterMeta();
                    loadData();
                } else {
                    showSyncToast('Sync failed: ' + res.message, 'error');
                }
            })
            .catch(err => {
                showSyncToast('Sync failed: ' + err.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.classList.remove('syncing');
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync Core1';
            });
    });

    function autoSync() {
        fetch('../../api/saving_monitoring/savings_sync_api.php')
            .then(r => r.json())
            .then(res => {
                if (res.success && !res.skipped) {
                    loadFilterMeta();
                    loadData();
                }
            })
            .catch(() => {});
    }

    function showSyncToast(message, type) {
        const existing = document.getElementById('syncToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'syncToast';
        toast.className = 'position-fixed top-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="document.getElementById('syncToast').remove()"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            const t = document.getElementById('syncToast');
            if (t) t.remove();
        }, 4000);
    }

    function updateFilterIndicator() {
        const filterTexts = { all:'', deposit:'Deposits Only', withdrawal:'Withdrawals Only' };
        if (currentCardFilter !== 'all') {
            filterIndicator.textContent = filterTexts[currentCardFilter];
            filterIndicator.style.display = 'inline-block';
            filterIndicator.className = 'badge ms-2 ' + (currentCardFilter === 'deposit' ? 'bg-success' : 'bg-danger');
        } else {
            filterIndicator.style.display = 'none';
        }
    }

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), wait);
        };
    }

    function loadFilterMeta() {
        fetch('savings_action.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'meta' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') return;

            memberFilter.innerHTML = '<option value="">All Members</option>';
            (data.members || []).forEach(m => {
                memberFilter.innerHTML += `<option value="${m}">${m}</option>`;
            });

            recordedByFilter.innerHTML = '<option value="">All Users</option>';
            (data.recorded_by || []).forEach(u => {
                recordedByFilter.innerHTML += `<option value="${u.user_id}">${u.full_name}</option>`;
            });
        })
        .catch(() => {});
    }

    function loadData() {
        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            limit: limit,
            search: currentSearch,
            search_by: searchBy ? searchBy.value : 'auto',
            filter: currentCardFilter,
            type: typeFilter.value,
            member_id: memberFilter.value,
            recorded_by: recordedByFilter.value,
            date_from: dateFrom.value,
            date_to: dateTo.value
        });

        tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

        fetch('savings_action.php', { method:'POST', body: params })
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') {
                    Swal.fire('Error', data.msg || 'Load failed', 'error');
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load data</td></tr>';
                    return;
                }

                allTransactionsData = data.rows || [];
                summaryData = data.summary || {};

                document.getElementById('card_total_tx').textContent = data.summary.total || 0;
                document.getElementById('card_total_deposit').textContent = data.summary.total_deposits || 0;
                document.getElementById('card_total_withdraw').textContent = data.summary.total_withdrawals || 0;
                document.getElementById('card_balance').textContent =
                    '₱' + parseFloat(data.summary.last_balance || 0).toLocaleString(undefined, { minimumFractionDigits:2, maximumFractionDigits:2 });

                const start = (currentPage - 1) * limit + 1;
                const end = Math.min(currentPage * limit, data.pagination?.total_records || 0);
                const total = data.pagination?.total_records || 0;
                recordCount.textContent = total > 0 ? `Showing ${start}-${end} of ${total} records` : 'No records found';

                tbody.innerHTML = '';
                if (data.rows && data.rows.length > 0) {
                    data.rows.forEach(r => {
                        const typeBadge = r.transaction_type === 'Deposit' ? 'badge-deposit' : 'badge-withdrawal';
                        tbody.innerHTML += `
                            <tr>
                                <td>${r.saving_id}</td>
                                <td>${r.member_id}</td>
                                <td>${r.transaction_date}</td>
                                <td><span class="badge ${typeBadge}">${r.transaction_type}</span></td>
                                <td>₱${Number(r.amount).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                                <td>₱${Number(r.balance).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                                <td>${r.recorded_by_name || '-'}</td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-info viewBtn" data-id="${r.saving_id}" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-primary breakdownBtn" data-member-id="${r.member_id}" title="Member Breakdown">
                                            <i class="bi bi-bar-chart-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">
                        <i class="bi bi-inbox"></i> No transactions found
                    </td></tr>`;
                }

                renderPagination(data.pagination?.current_page || 1, data.pagination?.total_pages || 1);

                exportCsvBtn.href =
                    `savings_action.php?export=csv` +
                    `&search=${encodeURIComponent(currentSearch)}` +
                    `&search_by=${encodeURIComponent(searchBy ? searchBy.value : 'auto')}` +
                    `&filter=${encodeURIComponent(currentCardFilter)}` +
                    `&type=${encodeURIComponent(typeFilter.value)}` +
                    `&member_id=${encodeURIComponent(memberFilter.value)}` +
                    `&recorded_by=${encodeURIComponent(recordedByFilter.value)}` +
                    `&date_from=${encodeURIComponent(dateFrom.value)}` +
                    `&date_to=${encodeURIComponent(dateTo.value)}`;

                updateFilterIndicator();
            })
            .catch(err => {
                console.error('Fetch error:', err);
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading data</td></tr>';
            });
    }

    function renderPagination(current, total) {
        paginationControls.innerHTML = '';
        paginationInfo.textContent = total > 0 ? `Page ${current} of ${total}` : '';
        if (total <= 1) return;

        const prev = document.createElement('button');
        prev.className = 'btn btn-sm btn-outline-primary';
        prev.textContent = 'Prev';
        prev.disabled = current === 1;
        prev.onclick = () => { currentPage--; loadData(); };

        const next = document.createElement('button');
        next.className = 'btn btn-sm btn-outline-primary';
        next.textContent = 'Next';
        next.disabled = current === total;
        next.onclick = () => { currentPage++; loadData(); };

        paginationControls.appendChild(prev);
        paginationControls.appendChild(next);
    }

    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('click', function() {
            const filter = this.dataset.filter;
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));

            if (currentCardFilter === filter) {
                currentCardFilter = 'all';
            } else {
                this.classList.add('active');
                currentCardFilter = filter;
            }
            currentPage = 1;
            loadData();
        });
    });

    exportPdfBtn.addEventListener('click', function() {
        if (allTransactionsData.length === 0) {
            Swal.fire('No Data', 'No transactions available to export', 'info');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');

        doc.setFontSize(18);
        doc.setTextColor(40, 40, 40);
        doc.text('Savings Monitoring Report', 14, 15);

        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 22);

        doc.setFontSize(12);
        doc.setTextColor(40, 40, 40);
        doc.text('Summary', 14, 32);

        doc.setFontSize(10);
        doc.text(`Total Transactions: ${summaryData.total || 0}`, 14, 38);
        doc.text(`Total Deposits: ${summaryData.total_deposits || 0}`, 70, 38);
        doc.text(`Total Withdrawals: ${summaryData.total_withdrawals || 0}`, 126, 38);
        doc.text(`Current Balance: ₱${parseFloat(summaryData.last_balance || 0).toLocaleString()}`, 14, 44);

        const tableData = allTransactionsData.map(r => [
            r.saving_id,
            r.member_id,
            r.transaction_date,
            r.transaction_type,
            `₱${Number(r.amount).toLocaleString(undefined, {minimumFractionDigits:2})}`,
            `₱${Number(r.balance).toLocaleString(undefined, {minimumFractionDigits:2})}`,
            r.recorded_by_name || '-'
        ]);

        doc.autoTable({
            startY: 50,
            head: [['ID','Member ID','Date','Type','Amount','Balance','Recorded By']],
            body: tableData,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [5,150,105], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245,245,245] }
        });

        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(150);
            doc.text(`Page ${i} of ${pageCount}`, doc.internal.pageSize.width/2, doc.internal.pageSize.height - 10, { align:'center' });
        }

        doc.save(`Savings_Report_${new Date().toISOString().slice(0,10)}.pdf`);
    });

    addTxBtn.addEventListener('click', () => {
        txForm.reset();
        document.getElementById('transaction_date').valueAsDate = new Date();
        txModal.show();
    });

    txForm.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(txForm);
        fd.append('action', 'add');

        fetch('savings_action.php', { method:'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') {
                    Swal.fire('Saved', resp.msg, 'success');
                    txModal.hide();
                    loadFilterMeta();
                    loadData();
                } else {
                    Swal.fire('Error', resp.msg, 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Failed to save transaction', 'error'));
    });

    function loadMemberBreakdown(memberId) {
        breakdownModal.show();
        document.getElementById('breakdownTableBody').innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <p class="mt-2 mb-0 text-muted">Loading member data...</p>
                </td>
            </tr>
        `;

        fetch('savings_action.php', {
            method: 'POST',
            body: new URLSearchParams({ action:'breakdown', member_id: memberId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                Swal.fire('Error', data.msg || 'Failed to load member data', 'error');
                breakdownModal.hide();
                return;
            }
            currentMemberData = data;
            renderBreakdown(data);
        })
        .catch(() => {
            Swal.fire('Error', 'Failed to load member breakdown', 'error');
            breakdownModal.hide();
        });
    }

    function renderBreakdown(data) {
        const { member_info, summary, transactions } = data;

        document.getElementById('bd_member_id').textContent = member_info.member_id;
        document.getElementById('bd_member_name').textContent = member_info.name;

        document.getElementById('bd_current_balance').textContent =
            summary.current_balance.toLocaleString(undefined, { minimumFractionDigits:2 });

        document.getElementById('bd_total_deposits').textContent =
            '₱' + summary.total_deposits.toLocaleString(undefined, { minimumFractionDigits:2 });
        document.getElementById('bd_deposit_count').textContent = summary.deposit_count;

        document.getElementById('bd_total_withdrawals').textContent =
            '₱' + summary.total_withdrawals.toLocaleString(undefined, { minimumFractionDigits:2 });
        document.getElementById('bd_withdrawal_count').textContent = summary.withdrawal_count;

        const netChange = summary.total_deposits - summary.total_withdrawals;
        const netChangeEl = document.getElementById('bd_net_change');
        netChangeEl.textContent = '₱' + Math.abs(netChange).toLocaleString(undefined, { minimumFractionDigits:2 });
        netChangeEl.className = netChange >= 0 ? 'mb-1 text-success' : 'mb-1 text-danger';

        document.getElementById('bd_total_txns').textContent = summary.total_transactions;

        const bt = document.getElementById('breakdownTableBody');
        bt.innerHTML = '';

        if (!transactions || transactions.length === 0) {
            bt.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-2"></i><p class="mb-0 mt-2">No transactions found for this member</p>
            </td></tr>`;
            return;
        }

        transactions.forEach(txn => {
            const typeClass = txn.transaction_type === 'Deposit' ? 'badge-deposit' : 'badge-withdrawal';
            const amountClass = txn.transaction_type === 'Deposit' ? 'text-success' : 'text-danger';
            const amountIcon = txn.transaction_type === 'Deposit' ? '↓' : '↑';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td><i class="bi bi-calendar3 text-muted me-1"></i>${txn.transaction_date}</td>
                <td><span class="badge ${typeClass}">${txn.transaction_type}</span></td>
                <td class="text-end ${amountClass} fw-bold">${amountIcon} ₱${Number(txn.amount).toLocaleString(undefined, { minimumFractionDigits:2 })}</td>
                <td class="text-end">₱${Number(txn.balance).toLocaleString(undefined, { minimumFractionDigits:2 })}</td>
                <td><small class="text-muted"><i class="bi bi-person"></i> ${txn.recorded_by_name || 'System'}</small></td>
            `;
            bt.appendChild(row);
        });
    }

    document.getElementById('exportMemberBtn').addEventListener('click', () => {
        if (!currentMemberData) {
            Swal.fire('Error', 'No member data to export', 'warning');
            return;
        }

        const { member_info, transactions } = currentMemberData;

        let csv = 'Member Transaction Report\n';
        csv += `Member ID,${member_info.member_id}\n`;
        csv += `Member Name,${member_info.name}\n`;
        csv += `Generated,${new Date().toLocaleString()}\n\n`;
        csv += 'Date,Type,Amount,Balance,Recorded By\n';

        transactions.forEach(txn => {
            csv += `${txn.transaction_date},${txn.transaction_type},${txn.amount},${txn.balance},${txn.recorded_by_name || 'System'}\n`;
        });

        const blob = new Blob([csv], { type:'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `Member_${member_info.member_id}_Transactions_${new Date().toISOString().slice(0,10)}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        Swal.fire({ title:'Export Successful', text:'Member transaction data has been exported', icon:'success', timer:2000, showConfirmButton:false });
    });

    tbody.addEventListener('click', e => {
        const viewBtn = e.target.closest('.viewBtn');
        if (viewBtn) {
            const id = viewBtn.dataset.id;
            fetch('savings_action.php', {
                method:'POST',
                body: new URLSearchParams({ action:'get', id })
            })
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.msg, 'error');
                    return;
                }
                const d = res.row;
                document.getElementById('v_id').textContent = d.saving_id;
                document.getElementById('v_member').textContent = d.member_id;
                document.getElementById('v_date').textContent = d.transaction_date;
                document.getElementById('v_type').innerHTML = `<span class="badge ${d.transaction_type === 'Deposit' ? 'badge-deposit' : 'badge-withdrawal'}">${d.transaction_type}</span>`;
                document.getElementById('v_amount').textContent = Number(d.amount).toLocaleString(undefined, { minimumFractionDigits:2 });
                document.getElementById('v_balance').textContent = Number(d.balance).toLocaleString(undefined, { minimumFractionDigits:2 });
                document.getElementById('v_by').textContent = d.recorded_by_name || 'Unknown';
                viewModal.show();
            })
            .catch(() => Swal.fire('Error', 'Failed to load transaction details', 'error'));
            return;
        }

        const breakdownBtn = e.target.closest('.breakdownBtn');
        if (breakdownBtn) {
            loadMemberBreakdown(breakdownBtn.dataset.memberId);
            return;
        }
    });

    searchInput.addEventListener('input', debounce(() => {
        currentSearch = searchInput.value.trim();
        currentPage = 1;
        loadData();
    }, 500));

    if (searchBy) {
        searchBy.addEventListener('change', () => {
            currentPage = 1;
            loadData();
        });
    }

    typeFilter.addEventListener('change', () => { currentPage = 1; loadData(); });
    memberFilter.addEventListener('change', () => { currentPage = 1; loadData(); });
    recordedByFilter.addEventListener('change', () => { currentPage = 1; loadData(); });
    dateFrom.addEventListener('change', () => { currentPage = 1; loadData(); });
    dateTo.addEventListener('change', () => { currentPage = 1; loadData(); });

    rowsPerPage.addEventListener('change', () => {
        limit = parseInt(rowsPerPage.value);
        currentPage = 1;
        loadData();
    });

    clearFilters.addEventListener('click', () => {
        currentSearch = '';
        currentCardFilter = 'all';
        currentPage = 1;

        searchInput.value = '';
        if (searchBy) searchBy.value = 'auto';
        typeFilter.value = '';
        memberFilter.value = '';
        recordedByFilter.value = '';
        dateFrom.value = '';
        dateTo.value = '';
        rowsPerPage.value = '10';
        limit = 10;

        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
        loadData();
    });

    loadFilterMeta();
    autoSync();
    loadData();
    setInterval(autoSync, 30000);
});
</script>
