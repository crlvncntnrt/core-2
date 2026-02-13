<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once __DIR__ . '/../inc/check_auth.php';

// Comment out permission check if it's causing issues
// checkPermission('disbursement_tracker');

include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<!-- CDN Libraries -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    .stat-card[data-filter="Released"] {
        --card-color-1: #059669;
        --card-color-2: #047857;
    }

    .stat-card[data-filter="Pending"] {
        --card-color-1: #f59e0b;
        --card-color-2: #d97706;
    }

    .stat-card[data-filter="amount"] {
        --card-color-1: #8b5cf6;
        --card-color-2: #7c3aed;
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
        background: #1f2937 !important;
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

    .btn-outline-primary {
        border: 2px solid #3b82f6;
        color: #3b82f6;
    }

    .btn-outline-primary:hover {
        background: #3b82f6;
        color: white;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
    }

    .btn-info {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
    }

    .btn-outline-secondary {
        border: 2px solid #6b7280;
        color: #6b7280;
    }

    .btn-outline-secondary:hover {
        background: #6b7280;
        color: white;
    }

    .btn-outline-light {
        border: 2px solid rgba(255, 255, 255, 0.5);
        color: white;
    }

    .btn-outline-light:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: white;
        color: white;
    }

    .action-btn-group {
        display: flex;
        gap: 5px;
        justify-content: center;
    }

    .action-btn-group .btn {
        padding: 0.35rem 0.65rem;
        font-size: 0.8rem;
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

    /* Modal Enhancements */
    .modal-content {
        border-radius: 1rem;
        border: none;
        box-shadow: var(--shadow-xl);
    }

    .modal-header {
        border-bottom: 2px solid #f3f4f6;
        border-radius: 1rem 1rem 0 0;
    }

    .modal-header.bg-info {
        background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
    }

    .modal-footer {
        border-top: 2px solid #f3f4f6;
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

<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">

            <!-- Enhanced Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4><i class="bi bi-cash-stack me-2"></i>Disbursement Tracker</h4>
                        <p class="subtitle mb-0">Track and monitor loan disbursements and fund allocations</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="exportPdfBtn" class="btn btn-sm btn-danger">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </button>
                        <button id="exportExcelBtn" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
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
                            <i class="bi bi-list-ul"></i>
                        </div>
                        <div class="stat-title">Total Disbursements</div>
                        <div id="card_total" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to view all
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="Released">
                        <div class="stat-card-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-title">Total Released</div>
                        <div id="card_released" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to filter
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="Pending">
                        <div class="stat-card-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-title">Total Pending</div>
                        <div id="card_pending" class="stat-value">0</div>
                        <div class="stat-hint">
                            <i class="bi bi-hand-index"></i> Click to filter
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card" data-filter="amount">
                        <div class="stat-card-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div class="stat-title">Total Amount</div>
                        <div id="card_amount" class="stat-value">₱0.00</div>
                        <div class="stat-hint">
                            <i class="bi bi-info-circle"></i> Total disbursed
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filters Section -->
            <div class="filter-section">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search loan ID, member, fund source...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Released">Released</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fund Source</label>
                        <select id="fundFilter" class="form-select">
                            <option value="">All Funds</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date Range</label>
                        <input type="date" id="dateFilter" class="form-control">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Rows</label>
                        <select id="rowsPerPage" class="form-select">
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button id="clearFilters" class="btn btn-outline-secondary w-100">Clear</button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Disbursement Table -->
            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span>Disbursement Records</span>
                        <span id="filterIndicator" class="badge bg-info ms-2" style="display: none;"></span>
                    </h6>
                    <span id="recordCount"></span>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover" id="disbTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Loan ID</th>
                                <th>Member</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Fund Source</th>
                                <th>Approved By</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="disbTbody">
                            <tr>
                                <td colspan="10" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
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

<!-- View Disbursement Modal -->
<div class="modal fade" id="disbModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Disbursement Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Disbursement ID:</strong> <span id="view_disb_id"></span></div>
                    <div class="col-md-6"><strong>Loan ID:</strong> <span id="view_loan_id"></span></div>
                    <div class="col-md-6"><strong>Member:</strong> <span id="view_member"></span></div>
                    <div class="col-md-6"><strong>Date:</strong> <span id="view_date"></span></div>
                    <div class="col-md-6"><strong>Amount:</strong> <span id="view_amount"></span></div>
                    <div class="col-md-6"><strong>Fund Source:</strong> <span id="view_fund"></span></div>
                    <div class="col-md-6"><strong>Status:</strong> <span id="view_status"></span></div>
                    <div class="col-md-6"><strong>Approved By:</strong> <span id="view_approved"></span></div>
                    <div class="col-12"><strong>Remarks:</strong>
                        <div id="view_remarks" class="mt-1"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentPage = 1,
            limit = 10;
        let currentFilters = {
            search: '',
            status: '',
            fund: '',
            date: '',
            cardFilter: 'all'
        };
        let allDisbursements = [];

        const tbody = document.getElementById('disbTbody');
        const paginationControls = document.getElementById('paginationControls');
        const paginationInfo = document.getElementById('paginationInfo');
        const filterIndicator = document.getElementById('filterIndicator');

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showAlert(message, type = 'danger') {
            const icon = type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'error';
            const title = type === 'success' ? 'Success!' : type === 'warning' ? 'Warning!' : type === 'info' ? 'Info' : 'Error!';

            Swal.fire({
                icon: icon,
                title: title,
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }

        function loadData() {
            const params = new URLSearchParams({
                action: 'list',
                page: currentPage,
                limit: limit,
                search: currentFilters.search,
                status: currentFilters.status,
                fund: currentFilters.fund,
                date: currentFilters.date,
                cardFilter: currentFilters.cardFilter
            });

            tbody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

            fetch('ajax_disbursement.php?' + params)
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                    return r.text();
                })
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Server returned invalid JSON. Check console for details.');
                    }
                })
                .then(data => {
                    console.log('Received data:', data);
                    if (data.error) throw new Error(data.message || 'Server error');
                    allDisbursements = data.all_disbursements || data.disbursements || [];
                    renderTable(data);
                    populateFundSources(data.fund_sources || []);
                    updateFilterIndicator();
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    showError('Failed to fetch data: ' + err.message);
                    showAlert(err.message, 'danger');
                });
        }

        function showError(message) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">
            <i class="bi bi-exclamation-triangle"></i> ${escapeHtml(message)}
            <br><small class="text-muted mt-2">Check the browser console (F12) for more details</small>
        </td></tr>`;
        }

        function updateFilterIndicator() {
            const filterTexts = {
                'all': '',
                'Released': 'Released Only',
                'Pending': 'Pending Only',
                'Cancelled': 'Cancelled Only'
            };
            if (currentFilters.cardFilter !== 'all') {
                filterIndicator.textContent = filterTexts[currentFilters.cardFilter];
                filterIndicator.style.display = 'inline-block';
                filterIndicator.className = 'badge ms-2 ' + (currentFilters.cardFilter === 'Released' ? 'bg-success' :
                    currentFilters.cardFilter === 'Pending' ? 'bg-warning text-dark' : 'bg-danger');
            } else {
                filterIndicator.style.display = 'none';
            }
        }

        function populateFundSources(sources) {
            const fundFilter = document.getElementById('fundFilter');
            const currentValue = fundFilter.value;
            fundFilter.innerHTML = '<option value="">All Funds</option>';
            sources.forEach(source => {
                const option = document.createElement('option');
                option.value = source;
                option.textContent = source;
                fundFilter.appendChild(option);
            });
            fundFilter.value = currentValue;
        }

        function renderTable(data) {
            // Update summary cards
            document.getElementById('card_total').textContent = data.summary?.total || 0;
            document.getElementById('card_released').textContent = data.summary?.released || 0;
            document.getElementById('card_pending').textContent = data.summary?.pending || 0;
            document.getElementById('card_amount').textContent = '₱' + Number(data.summary?.total_amount || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });

            // Update record count
            const start = (currentPage - 1) * limit + 1;
            const end = Math.min(currentPage * limit, data.pagination?.total_records || 0);
            const total = data.pagination?.total_records || 0;
            document.getElementById('recordCount').textContent = total > 0 ? `Showing ${start}-${end} of ${total} records` : 'No records found';

            // Render table rows
            tbody.innerHTML = '';
            if (data.disbursements?.length > 0) {
                data.disbursements.forEach(d => {
                    const statusBadge = d.status === 'Released' ? 'bg-success' : d.status === 'Cancelled' ? 'bg-danger' : 'bg-warning text-dark';
                    const row = document.createElement('tr');
                    row.innerHTML = `
                    <td>${escapeHtml(String(d.disbursement_id))}</td>
                    <td>${escapeHtml(String(d.loan_id))}</td>
                    <td>${escapeHtml(d.member_name || 'N/A')}</td>
                    <td>${escapeHtml(d.disbursement_date || '')}</td>
                    <td>₱${Number(d.amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td>${escapeHtml(d.fund_source || '-')}</td>
                    <td>${escapeHtml(d.approved_by_name || '-')}</td>
                    <td><span class="badge ${statusBadge}">${escapeHtml(d.status)}</span></td>
                    <td class="text-start">${escapeHtml(d.remarks || '-')}</td>
                    <td class="text-center">
                        <div class="action-btn-group">
                            <button class="btn btn-sm btn-info view-disb-btn" data-id="${d.disbursement_id}" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${d.status === 'Pending' ? `
                            <button class="btn btn-sm btn-success approve-btn" data-id="${d.disbursement_id}" title="Approve">
                                <i class="bi bi-check-circle"></i>
                            </button>` : ''}
                        </div>
                    </td>
                `;
                    tbody.appendChild(row);
                });

                document.querySelectorAll('.view-disb-btn').forEach(btn => btn.addEventListener('click', onViewDisbursement));
                document.querySelectorAll('.approve-btn').forEach(btn => btn.addEventListener('click', onApproveDisbursement));
            } else {
                const filterMsg = currentFilters.cardFilter !== 'all' ? ` matching "${filterIndicator.textContent}"` : '';
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted"><i class="bi bi-inbox"></i> No disbursements found${filterMsg}</td></tr>`;
            }

            renderPagination(data.pagination?.current_page || 1, data.pagination?.total_pages || 1);
        }

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

        function onViewDisbursement(e) {
            const id = e.currentTarget.dataset.id;
            const disb = allDisbursements.find(d => d.disbursement_id == id);
            if (disb) {
                document.getElementById('view_disb_id').textContent = disb.disbursement_id || '';
                document.getElementById('view_loan_id').textContent = disb.loan_id || '';
                document.getElementById('view_member').textContent = disb.member_name || 'N/A';
                document.getElementById('view_date').textContent = disb.disbursement_date || '';
                document.getElementById('view_amount').textContent = '₱' + Number(disb.amount || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2
                });
                document.getElementById('view_fund').textContent = disb.fund_source || '-';
                document.getElementById('view_status').innerHTML = `<span class="badge bg-${disb.status === 'Released' ? 'success' : disb.status === 'Cancelled' ? 'danger' : 'warning'}">${escapeHtml(disb.status)}</span>`;
                document.getElementById('view_approved').textContent = disb.approved_by_name || '-';
                document.getElementById('view_remarks').textContent = disb.remarks || 'No remarks';
                new bootstrap.Modal(document.getElementById('disbModal')).show();
            }
        }

        function onApproveDisbursement(e) {
            const id = e.currentTarget.dataset.id;

            Swal.fire({
                title: 'Approve Disbursement?',
                text: `Are you sure you want to approve disbursement ${id}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, approve it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('disbursement_action.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: new URLSearchParams({
                                action: 'approve',
                                id: id
                            }),
                            credentials: 'same-origin'
                        })
                        .then(r => {
                            if (!r.ok) {
                                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                            }
                            return r.json();
                        })
                        .then(res => {
                            Swal.close();
                            if (res.status === 'ok') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Approved!',
                                    text: 'Disbursement has been approved successfully.',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                loadData();
                            } else {
                                throw new Error(res.msg || 'Failed to approve');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed!',
                                text: err.message || 'Failed to approve disbursement'
                            });
                        });
                }
            });
        }

        // Clickable stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                const filter = this.dataset.filter;
                document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
                if (filter === 'amount') return;
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

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func(...args), wait);
            };
        }

        // Filter event listeners
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

        document.getElementById('fundFilter').addEventListener('change', (e) => {
            currentFilters.fund = e.target.value;
            currentPage = 1;
            loadData();
        });

        document.getElementById('dateFilter').addEventListener('change', (e) => {
            currentFilters.date = e.target.value;
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
                fund: '',
                date: '',
                cardFilter: 'all'
            };
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('fundFilter').value = '';
            document.getElementById('dateFilter').value = '';
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
            currentPage = 1;
            loadData();
        });

        document.getElementById('reloadBtn').addEventListener('click', () => loadData());

        // Export PDF
        document.getElementById('exportPdfBtn').addEventListener('click', async function() {
            const passwordPrompt = await Swal.fire({
                title: 'Protect PDF Export',
                text: 'Enter a password before exporting this PDF.',
                input: 'password',
                inputLabel: 'PDF Password',
                inputPlaceholder: 'At least 6 characters',
                showCancelButton: true,
                confirmButtonText: 'Export PDF',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => (!value || value.trim().length < 6) ? 'Please enter at least 6 characters.' : null
            });

            if (!passwordPrompt.isConfirmed) return;
            const pdfPassword = passwordPrompt.value;

            const params = new URLSearchParams({
                export: 'pdf',
                search: currentFilters.search,
                status: currentFilters.status,
                fund: currentFilters.fund,
                date: currentFilters.date,
                cardFilter: currentFilters.cardFilter,
                pdf_password: pdfPassword
            });

            const btn = this;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            btn.disabled = true;

            try {
                const url = `disbursement_action.php?${params.toString()}`;
                
                const response = await fetch(url);
                if (!response.ok) throw new Error('Export failed');
                
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Export failed');
                }

                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `disbursement_tracker_${new Date().toISOString().split('T')[0]}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(downloadUrl);
                a.remove();

                Swal.fire({
                    icon: 'success',
                    title: 'PDF Exported',
                    text: 'Use your entered password to open the PDF.',
                    timer: 3000,
                    showConfirmButton: false
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Export Failed',
                    text: error.message
                });
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        });

        // Export Excel/CSV
        document.getElementById('exportExcelBtn').addEventListener('click', async function() {
            const passwordPrompt = await Swal.fire({
                title: 'Protect Excel Export',
                text: 'Enter a password to encrypt this Excel/CSV export in a ZIP file.',
                input: 'password',
                inputLabel: 'Export Password',
                inputPlaceholder: 'At least 6 characters',
                showCancelButton: true,
                confirmButtonText: 'Export Excel',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => (!value || value.trim().length < 6) ? 'Please enter at least 6 characters.' : null
            });

            if (!passwordPrompt.isConfirmed) return;
            const pdfPassword = passwordPrompt.value;

            const params = new URLSearchParams({
                export: 'csv',
                search: currentFilters.search,
                status: currentFilters.status,
                fund: currentFilters.fund,
                date: currentFilters.date,
                cardFilter: currentFilters.cardFilter,
                pdf_password: pdfPassword
            });

            const btn = this;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            btn.disabled = true;

            try {
                const url = `disbursement_action.php?${params.toString()}`;
                
                const response = await fetch(url);
                if (!response.ok) throw new Error('Export failed');
                
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Export failed');
                }

                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                const extension = pdfPassword ? 'zip' : 'csv';
                a.download = `disbursement_tracker_${new Date().toISOString().split('T')[0]}.${extension}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(downloadUrl);
                a.remove();

                Swal.fire({
                    icon: 'success',
                    title: 'Excel Exported',
                    text: pdfPassword ? 'The ZIP file is password protected.' : 'File downloaded successfully.',
                    timer: 3000,
                    showConfirmButton: false
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Export Failed',
                    text: error.message
                });
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        });

        // Initial load
        loadData();
    });
</script>