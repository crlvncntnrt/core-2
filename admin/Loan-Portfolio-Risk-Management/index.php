<?php
// === Include layout files ===
include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
include(__DIR__ . '/../inc/footer.php');

require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once __DIR__ . '/../inc/check_auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
?>

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

    /* Enhanced Header */
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

    /* Enhanced Stat Cards */
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

    /* Card color schemes */
    .stat-card[data-filter="all"] {
        --card-color-1: #3b82f6;
        --card-color-2: #2563eb;
    }

    .stat-card[data-filter="active"] {
        --card-color-1: #059669;
        --card-color-2: #047857;
    }

    .stat-card[data-filter="overdue"] {
        --card-color-1: #f59e0b;
        --card-color-2: #d97706;
    }

    .stat-card[data-filter="defaulted"] {
        --card-color-1: #ef4444;
        --card-color-2: #dc2626;
    }

    /* Enhanced Filter Section */
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

    /* Enhanced Chart Cards */
    .chart-card {
        padding: 1.5rem;
        border-radius: 1rem;
        background: #fff;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .chart-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    .chart-card h6 {
        font-weight: 700;
        color: #111827;
        font-size: 1rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-card h6::before {
        content: '';
        width: 4px;
        height: 1.25rem;
        background: var(--brand-primary);
        border-radius: 2px;
    }

    .chart-container {
        position: relative;
        height: 280px;
    }

    /* Enhanced Table */
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

    .table thead {
        background: linear-gradient(135deg, var(--brand-primary), #047857);
    }

    .table thead th {
        color: #111827 !important;
        font-weight: 600;
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

    /* Enhanced Buttons */
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

    .btn-outline-success {
        border: 2px solid var(--brand-primary);
        color: var(--brand-primary);
    }

    .btn-outline-success:hover {
        background: var(--brand-primary);
        border-color: var(--brand-primary);
        color: white;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
    }

    .btn-outline-primary {
        border: 2px solid #3b82f6;
        color: #3b82f6;
    }

    .btn-outline-primary:hover {
        background: #3b82f6;
        color: white;
    }

    .btn-info {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
    }

    /* Sync button animation */
    .btn-sync.syncing .bi-arrow-repeat {
        display: inline-block;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* Pagination */
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

    /* Toast notification */
    #syncToast {
        animation: slideInRight 0.3s ease;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Loading state */
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .page-header {
            padding: 1.5rem;
        }

        .stat-card {
            padding: 1.25rem;
        }

        .stat-value {
            font-size: 1.75rem;
        }

        .filter-section {
            padding: 1rem;
        }
    }
</style>

<!-- ✅ FIXED: Added main-wrap wrapper to prevent sidebar overlap -->
<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">
            <!-- Enhanced Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4>Loan Portfolio & Risk Management</h4>
                        <p class="subtitle mb-0">Monitor and analyze your loan portfolio performance</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="syncCore1Btn" class="btn btn-sm btn-outline-light btn-sync" title="Pull latest loans from Core1">
                            <i class="bi bi-arrow-repeat"></i> Sync Core1
                        </button>
                        <button id="exportPdfBtn" class="btn btn-sm btn-danger">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </button>
                        <button id="reloadBtn" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-arrow-clockwise"></i> Reload
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="all">
                        <div class="stat-card-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div class="stat-title">Total Loans</div>
                        <div id="card_total_loans" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to view all
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="active">
                        <div class="stat-card-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-title">Active Loans</div>
                        <div id="card_active_loans" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to filter
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="overdue">
                        <div class="stat-card-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-title">Overdue Loans</div>
                        <div id="card_overdue_loans" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to filter
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="defaulted">
                        <div class="stat-card-icon">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="stat-title">Defaulted Loans</div>
                        <div id="card_defaulted_loans" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to filter
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filters Section -->
            <div class="filter-section">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search loans...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Defaulted">Defaulted</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Risk Level</label>
                        <select id="riskFilter" class="form-select">
                            <option value="">All Risks</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Loan Type</label>
                        <select id="typeFilter" class="form-select">
                            <option value="">All Types</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rows per page</label>
                        <select id="rowsPerPage" class="form-select">
                            <option value="10" selected>10 rows</option>
                            <option value="20">20 rows</option>
                            <option value="50">50 rows</option>
                            <option value="100">100 rows</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button id="clearFilters" class="btn btn-outline-secondary w-100">Clear</button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Charts -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="chart-card">
                        <h6>Loan Status Distribution</h6>
                        <div class="chart-container">
                            <canvas id="loanStatusPie"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-card">
                        <h6>Risk Level Breakdown</h6>
                        <div class="chart-container">
                            <canvas id="riskDonut"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Loan Risk Table -->
            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span id="tableTitle">Loan Risk Table</span>
                        <span id="filterIndicator" class="badge bg-info ms-2" style="display: none;"></span>
                    </h6>
                    <span id="recordCount"></span>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover" id="loanRiskTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Member</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Interest Rate</th>
                                <th>Term</th>
                                <th>Total Amount Due</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Overdue</th>
                                <th>Risk</th>
                                <th>Next Due</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="loanRiskTbody"></tbody>
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

<!-- View Loan Modal -->
<div class="modal fade" id="viewLoanModal" tabindex="-1" aria-labelledby="viewLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 1rem; border: none; box-shadow: var(--shadow-xl);">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--brand-info), #2563eb); color: #111827; border-radius: 1rem 1rem 0 0;">
                <h5 class="modal-title" id="viewLoanModalLabel">
                    <i class="bi bi-eye me-2"></i> Loan Details & Payment History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="loanDetailsContent">
                    <p class="text-center text-muted">Loading loan details...</p>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 2px solid #f3f4f6;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let loanStatusChart, riskDonutChart;
        let currentPage = 1,
            limit = 10;
        let currentFilters = {
            search: '',
            status: '',
            risk: '',
            type: '',
            cardFilter: 'all'
        };
        let allLoansData = [];

        const tbody = document.getElementById('loanRiskTbody');
        const paginationControls = document.getElementById('paginationControls');
        const paginationInfo = document.getElementById('paginationInfo');
        const filterIndicator = document.getElementById('filterIndicator');

        // ✅ FIXED: Correct API path with /coref2/
        // ✅ BEST: Works everywhere
        const API_BASE_URL = (() => {
            const path = window.location.pathname;
            const parts = path.split('/');

            // Find "admin" folder index
            const adminIndex = parts.indexOf('admin');

            if (adminIndex > 0) {
                // Build path from root to admin, then go to api
                const rootPath = parts.slice(0, adminIndex).join('/');
                return rootPath + '/api/loan/loan_api.php';
            }

            // Fallback
            return '/api/loan/loan_api.php';
        })();

        console.log('🔧 API URL:', API_BASE_URL);

        console.log('🔧 API URL:', API_BASE_URL);

        // ─── Sync Core1 handler ───
        document.getElementById('syncCore1Btn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.classList.add('syncing');
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Syncing...';

            console.log('🔄 Starting sync to:', API_BASE_URL + '?force=1');

            fetch(`${API_BASE_URL}?force=1`)
                .then(r => {
                    console.log('📡 Response status:', r.status, r.statusText);
                    if (!r.ok) {
                        throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                    }
                    return r.text();
                })
                .then(text => {
                    console.log('📥 Response received:', text.substring(0, 200));
                    try {
                        const res = JSON.parse(text);
                        console.log('✅ Parsed JSON:', res);
                        if (res.success) {
                            showSyncToast(res.message || 'Sync successful!', 'success');
                            loadData();
                        } else {
                            showSyncToast('Sync failed: ' + (res.message || res.error || 'Unknown error'), 'error');
                        }
                    } catch (e) {
                        console.error('❌ JSON Parse Error:', e);
                        console.error('Response text:', text);
                        showSyncToast('Invalid response from server. Check console.', 'error');
                    }
                })
                .catch(err => {
                    console.error('❌ Sync error:', err);
                    showSyncToast('Sync failed: ' + err.message, 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.classList.remove('syncing');
                    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync Core1';
                });
        });

        // ─── Auto Sync ───
        function autoSync() {
            console.log('🔄 [AutoSync] Checking for updates...');
            fetch(API_BASE_URL)
                .then(r => {
                    if (!r.ok) {
                        console.warn('[AutoSync] HTTP error:', r.status);
                        throw new Error(`HTTP ${r.status}`);
                    }
                    return r.text();
                })
                .then(text => {
                    try {
                        const res = JSON.parse(text);
                        if (res.success) {
                            console.log('✅ [AutoSync] Success:', res.message);
                            loadData();
                        } else {
                            console.log('⏭️ [AutoSync] Skipped or failed:', res.message || res.error);
                        }
                    } catch (e) {
                        console.warn('⚠️ [AutoSync] Invalid JSON response');
                    }
                })
                .catch(err => {
                    console.warn('⚠️ [AutoSync] Failed silently:', err.message);
                });
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
                            ${escapeHtml(message)}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="document.getElementById('syncToast').remove()"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                const t = document.getElementById('syncToast');
                if (t) t.remove();
            }, 5000);
        }

        // ✅ FIXED: Load data function
        function loadData() {
            const params = new URLSearchParams({
                page: currentPage,
                limit: limit,
                search: currentFilters.search,
                status: currentFilters.status,
                risk: currentFilters.risk,
                type: currentFilters.type,
                cardFilter: currentFilters.cardFilter
            });

            tbody.innerHTML = '<tr><td colspan="14" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

            console.log('📊 Loading data with params:', params.toString());

            // ✅ FIXED: Use correct API endpoint
            fetch(`${API_BASE_URL}?${params}`)
                .then(r => {
                    console.log('📡 Response status:', r.status);
                    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                    return r.json();
                })
                .then(data => {
                    console.log('📊 Received data:', data);

                    // ✅ FIXED: Check success property
                    if (data.success) {
                        allLoansData = data.loans || [];
                        renderChartsAndTable(data);
                        populateLoanTypes(data.loan_types || []);
                        updateFilterIndicator();
                    } else {
                        console.error('❌ Error from server:', data.error || data.message);
                        showError('Failed to load data: ' + (data.error || data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('❌ Fetch error:', err);
                    showError('Failed to fetch data: ' + err.message);
                });
        }

        function showError(message) {
            tbody.innerHTML = `<tr><td colspan="14" class="text-center text-danger"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(message)}</td></tr>`;
        }

        function updateFilterIndicator() {
            const filterTexts = {
                'all': '',
                'active': 'Active Loans Only',
                'overdue': 'Overdue Loans Only',
                'defaulted': 'Defaulted Loans Only'
            };

            if (currentFilters.cardFilter !== 'all') {
                filterIndicator.textContent = filterTexts[currentFilters.cardFilter];
                filterIndicator.style.display = 'inline-block';
                filterIndicator.className = 'badge ms-2 ' +
                    (currentFilters.cardFilter === 'active' ? 'bg-success' :
                        currentFilters.cardFilter === 'overdue' ? 'bg-warning text-dark' : 'bg-danger');
            } else {
                filterIndicator.style.display = 'none';
            }
        }

        function populateLoanTypes(types) {
            const typeFilter = document.getElementById('typeFilter');
            const currentValue = typeFilter.value;
            typeFilter.innerHTML = '<option value="">All Types</option>';
            types.forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = type;
                typeFilter.appendChild(option);
            });
            typeFilter.value = currentValue;
        }

        // --- Render charts & table ---
        function renderChartsAndTable(data) {
            // Summary Cards
            document.getElementById('card_total_loans').textContent = data.summary?.total_loans || 0;
            document.getElementById('card_active_loans').textContent = data.summary?.active_loans || 0;
            document.getElementById('card_overdue_loans').textContent = data.summary?.overdue_loans || 0;
            document.getElementById('card_defaulted_loans').textContent = data.summary?.defaulted_loans || 0;

            // Loan Status Pie
            if (loanStatusChart) loanStatusChart.destroy();
            if (data.loan_status && data.loan_status.labels && data.loan_status.labels.length > 0) {
                const ctx = document.getElementById('loanStatusPie');
                if (ctx) {
                    loanStatusChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: data.loan_status.labels,
                            datasets: [{
                                data: data.loan_status.values,
                                backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            }

            // Risk Donut
            if (riskDonutChart) riskDonutChart.destroy();
            if (data.risk_breakdown && data.risk_breakdown.labels && data.risk_breakdown.labels.length > 0) {
                const ctx = document.getElementById('riskDonut');
                if (ctx) {
                    riskDonutChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.risk_breakdown.labels,
                            datasets: [{
                                data: data.risk_breakdown.values,
                                backgroundColor: ['#198754', '#ffc107', '#dc3545']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
            }

            // Record Count
            const start = (currentPage - 1) * limit + 1;
            const end = Math.min(currentPage * limit, data.pagination?.total_records || 0);
            const total = data.pagination?.total_records || 0;
            document.getElementById('recordCount').textContent =
                total > 0 ? `Showing ${start}-${end} of ${total} records` : 'No records found';

            // Table
            tbody.innerHTML = '';
            if (data.loans && data.loans.length > 0) {
                data.loans.forEach(l => {
                    const riskBadge = l.risk_level === 'High' ? 'bg-danger' :
                        l.risk_level === 'Medium' ? 'bg-warning text-dark' : 'bg-success';
                    const statusBadge = l.status === 'Active' ? 'bg-success' :
                        l.status === 'Defaulted' ? 'bg-danger' :
                        l.status === 'Completed' ? 'bg-info' : 'bg-warning';

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${escapeHtml(l.loan_code || 'OLD-' + l.loan_id)}</td>
                        <td>${escapeHtml(l.member_name || 'N/A')}</td>
                        <td>${escapeHtml(l.loan_type)}</td>
                        <td>₱${Number(l.principal_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                        <td>${l.interest_rate ?? '-'}%</td>
                        <td>${l.loan_term ?? '-'} mo</td>
                        <td><strong>₱${Number(l.total_amount_due || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</strong>
                            ${l.total_penalties > 0 ? '<br><small class="text-danger">+₱' + Number(l.total_penalties).toLocaleString('en-PH', {minimumFractionDigits: 2}) + ' penalty</small>' : ''}
                        </td>
                        <td>${l.start_date ?? '-'}</td>
                        <td>${l.end_date ?? '-'}</td>
                        <td><span class="badge ${statusBadge}">${escapeHtml(l.status)}</span></td>
                        <td><span class="badge ${l.overdue_count > 0 ? 'bg-danger' : 'bg-secondary'}">${l.overdue_count || 0}</span></td>
                        <td><span class="badge ${riskBadge}">${escapeHtml(l.risk_level)}</span></td>
                        <td>${l.next_due || '-'}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info view-loan-btn" 
                                    data-code="${escapeHtml(l.loan_code || '')}" 
                                    data-id="${l.loan_id}" 
                                    title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                document.querySelectorAll('.view-loan-btn').forEach(b => {
                    b.addEventListener('click', onViewLoan);
                });
            } else {
                const filterMsg = currentFilters.cardFilter !== 'all' ?
                    ` matching "${filterIndicator.textContent}"` : '';
                tbody.innerHTML = `<tr><td colspan="14" class="text-center text-muted"><i class="bi bi-inbox"></i> No loans found${filterMsg}</td></tr>`;
            }

            renderPagination(data.pagination?.current_page || 1, data.pagination?.total_pages || 1);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // --- PDF Export ---
        document.getElementById('exportPdfBtn').addEventListener('click', async function() {
            if (allLoansData.length === 0) {
                alert('No data available to export');
                return;
            }

            const passwordPrompt = await Swal.fire({
                title: 'Protect PDF Export',
                text: 'Enter a password before exporting this PDF.',
                input: 'password',
                inputLabel: 'PDF Password',
                inputPlaceholder: 'At least 6 characters',
                showCancelButton: true,
                confirmButtonText: 'Export PDF',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value || value.trim().length < 6) return 'Please enter at least 6 characters.';
                    return null;
                }
            });

            if (!passwordPrompt.isConfirmed) return;
            const pdfPassword = passwordPrompt.value;

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4');

            if (typeof doc.setEncryption === 'function') {
                doc.setEncryption({ userPassword: pdfPassword, ownerPassword: pdfPassword });
            }

            const totalLoans = document.getElementById('card_total_loans').textContent;
            const activeLoans = document.getElementById('card_active_loans').textContent;
            const overdueLoans = document.getElementById('card_overdue_loans').textContent;
            const defaultedLoans = document.getElementById('card_defaulted_loans').textContent;

            doc.setFillColor(15, 23, 42);
            doc.roundedRect(10, 8, 277, 18, 2, 2, 'F');
            doc.setFontSize(16);
            doc.setTextColor(255, 255, 255);
            doc.text('Loan Portfolio & Risk Management Report', 14, 19);

            doc.setFontSize(9);
            doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 24);

            doc.setTextColor(40, 40, 40);
            doc.setFontSize(11);
            doc.text('Summary', 14, 34);

            doc.setFontSize(10);
            doc.text(`Total Loans: ${totalLoans}`, 14, 40);
            doc.text(`Active Loans: ${activeLoans}`, 70, 40);
            doc.text(`Overdue Loans: ${overdueLoans}`, 126, 40);
            doc.text(`Defaulted Loans: ${defaultedLoans}`, 182, 40);

            if (currentFilters.cardFilter !== 'all') {
                doc.setFontSize(9);
                doc.setTextColor(200, 0, 0);
                doc.text(`Filter Applied: ${filterIndicator.textContent}`, 14, 46);
            }

            const tableData = allLoansData.map(l => [
                l.loan_code || 'OLD-' + l.loan_id,
                l.member_name || 'N/A',
                l.loan_type,
                `₱${Number(l.principal_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}`,
                `${l.interest_rate ?? '-'}%`,
                `${l.loan_term ?? '-'} mo`,
                `₱${Number(l.total_amount_due || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}`,
                l.start_date ?? '-',
                l.end_date ?? '-',
                l.status,
                l.overdue_count || 0,
                l.risk_level,
                l.next_due || '-'
            ]);

            doc.autoTable({
                startY: currentFilters.cardFilter !== 'all' ? 50 : 46,
                head: [['Code', 'Member', 'Type', 'Amount', 'Rate', 'Term', 'Total Due', 'Start', 'End', 'Status', 'Overdue', 'Risk', 'Next Due']],
                body: tableData,
                styles: { fontSize: 7, cellPadding: 1.5 },
                headStyles: { fillColor: [30, 64, 175], textColor: 255, fontStyle: 'bold' },
                alternateRowStyles: { fillColor: [241, 245, 249] }
            });

            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(120);
                doc.text(`Confidential • Page ${i} of ${pageCount}`, doc.internal.pageSize.width / 2, doc.internal.pageSize.height - 8, { align: 'center' });
            }

            const filename = `Loan_Portfolio_Report_${new Date().toISOString().slice(0, 10)}.pdf`;
            doc.save(filename);
        });

        // --- Pagination ---
        function renderPagination(current, total) {
            paginationControls.innerHTML = '';
            paginationInfo.textContent = total > 0 ? `Page ${current} of ${total}` : '';

            if (total <= 1) return;

            const prev = document.createElement('button');
            prev.textContent = 'Prev';
            prev.className = 'btn btn-sm btn-outline-primary';
            prev.disabled = current === 1;
            prev.onclick = () => {
                currentPage--;
                loadData();
            };
            paginationControls.appendChild(prev);

            const next = document.createElement('button');
            next.textContent = 'Next';
            next.className = 'btn btn-sm btn-outline-primary';
            next.disabled = current === total;
            next.onclick = () => {
                currentPage++;
                loadData();
            };
            paginationControls.appendChild(next);
        }

        // Card click handler
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                const filter = this.dataset.filter;

                document.querySelectorAll('.stat-card')
                    .forEach(c => c.classList.remove('active'));

                if (currentFilters.cardFilter === filter) {
                    currentFilters.cardFilter = 'all';
                } else {
                    this.classList.add('active');
                    currentFilters.cardFilter = filter;
                }

                currentPage = 1;
                loadData();
            });
        });

        // --- Filter listeners ---
        document.getElementById('searchInput').addEventListener('input', debounce((e) => {
            currentFilters.search = e.target.value.trim();
            currentPage = 1;
            loadData();
        }, 500));

        document.getElementById('statusFilter').addEventListener('change', (e) => {
            currentFilters.status = e.target.value;
            currentPage = 1;
            loadData();
        });

        document.getElementById('riskFilter').addEventListener('change', (e) => {
            currentFilters.risk = e.target.value;
            currentPage = 1;
            loadData();
        });

        document.getElementById('typeFilter').addEventListener('change', (e) => {
            currentFilters.type = e.target.value;
            currentPage = 1;
            loadData();
        });

        document.getElementById('rowsPerPage').addEventListener('change', (e) => {
            limit = parseInt(e.target.value);
            currentPage = 1;
            loadData();
        });

        document.getElementById('clearFilters').addEventListener('click', () => {
            currentFilters = {
                search: '',
                status: '',
                risk: '',
                type: '',
                cardFilter: 'all'
            };
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('riskFilter').value = '';
            document.getElementById('typeFilter').value = '';
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
            currentPage = 1;
            loadData();
        });

        document.getElementById('reloadBtn').addEventListener('click', () => loadData());

        // --- View Loan Modal ---
        function onViewLoan(e) {
            const loan_code = e.currentTarget.dataset.code;
            const loan_id = e.currentTarget.dataset.id;
            const content = document.getElementById('loanDetailsContent');
            content.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p class="mt-2">Loading...</p></div>';

            const queryParam = (loan_code && loan_code.trim() !== '') ?
                `loan_code=${encodeURIComponent(loan_code)}` :
                `loan_id=${loan_id}`;

            fetch(`loan_crud.php?${queryParam}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.loan) {
                        const l = res.loan;

                        const totalInterest = l.principal_amount * (l.interest_rate / 100) * (l.loan_term / 12);
                        const totalAmountDue = parseFloat(l.principal_amount) + totalInterest;

                        let html = `
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><strong>Loan Code:</strong> ${escapeHtml(l.loan_code || 'N/A (Old Record #' + l.loan_id + ')')}</div>
                            <div class="col-md-6"><strong>Member:</strong> ${escapeHtml(l.member_name)} (ID: ${l.member_id})</div>
                            <div class="col-md-6"><strong>Type:</strong> ${escapeHtml(l.loan_type)}</div>
                            <div class="col-md-6"><strong>Status:</strong> <span class="badge bg-${l.status === 'Active' ? 'success' : l.status === 'Completed' ? 'info' : l.status === 'Defaulted' ? 'danger' : 'warning'}">${escapeHtml(l.status)}</span></div>
                            <div class="col-md-6"><strong>Loan Term:</strong> ${l.loan_term} months</div>
                            <div class="col-md-6"><strong>Start Date:</strong> ${l.start_date}</div>
                            <div class="col-md-6"><strong>End Date:</strong> ${l.end_date}</div>
                            <div class="col-md-12 border-top pt-3 mt-2">
                                <h6 class="mb-3">💰 Amount Breakdown:</h6>
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <td><strong>Principal Amount:</strong></td>
                                        <td class="text-end">₱${Number(l.principal_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Interest (${l.interest_rate}% for ${l.loan_term} months):</strong></td>
                                        <td class="text-end">₱${totalInterest.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Penalties:</strong></td>
                                        <td class="text-end text-danger">₱0.00</td>
                                    </tr>
                                    <tr class="table-success">
                                        <td><strong>Total Amount Due:</strong></td>
                                        <td class="text-end fw-bold fs-5">₱${totalAmountDue.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>`;

                        if (res.schedules && res.schedules.length > 0) {
                            html += `<h6 class="mb-2 mt-4"><i class="bi bi-calendar-check me-1"></i> Payment Schedule & History</h6>
                            <div class="table-responsive"><table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Due Date</th>
                                    <th>Amount Due</th>
                                    <th>Amount Paid</th>
                                    <th>Balance</th>
                                    <th>Payment Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead><tbody>`;

                            res.schedules.forEach(s => {
                                const badge = s.status === 'Paid' ? 'bg-success' : s.status === 'Overdue' ? 'bg-danger' : 'bg-warning';
                                const balance = Number(s.amount_due) - Number(s.amount_paid);
                                html += `<tr>
                                    <td>${s.due_date}</td>
                                    <td>₱${Number(s.amount_due).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    <td>₱${Number(s.amount_paid).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    <td>₱${balance.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    <td>${s.payment_date || '-'}</td>
                                    <td><span class="badge ${badge}">${escapeHtml(s.status)}</span></td>
                                </tr>`;
                            });
                            html += '</tbody></table></div>';
                        } else {
                            html += '<p class="text-muted">No payment schedules available</p>';
                        }

                        content.innerHTML = html;
                        new bootstrap.Modal(document.getElementById('viewLoanModal')).show();
                    } else {
                        content.innerHTML = '<p class="text-center text-danger">Failed to load loan details.</p>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    content.innerHTML = '<p class="text-center text-danger">Error loading loan.</p>';
                });
        }

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func(...args), wait);
            };
        }

        // Initial load
        console.log('🚀 Initializing app...');
        autoSync();
        loadData();

        // Auto-sync every 3 minutes (180 seconds)
        setInterval(autoSync, 180000);
        console.log('✅ Auto-sync enabled (every 3 minutes)');
    });
</script>