<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once __DIR__ . '/../inc/check_auth.php';

// Enforce RBAC for this page
checkPermission('compliance_logs');

// Include layout
include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
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

    #recordInfo {
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
        color: #ffffff !important;
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

    .btn-primary {
        background: linear-gradient(135deg, var(--brand-primary), #047857);
        border: none;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
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

    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid #f3f4f6;
    }

    .pagination {
        margin-bottom: 0;
    }

    .pagination .page-link {
        border: 1.5px solid #e5e7eb;
        color: var(--brand-primary);
        margin: 0 0.125rem;
        border-radius: 0.375rem;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
        transition: all 0.2s;
    }

    .pagination .page-link:hover {
        background: var(--brand-primary);
        color: white;
        border-color: var(--brand-primary);
    }

    .pagination .page-item.active .page-link {
        background: var(--brand-primary);
        border-color: var(--brand-primary);
        color: white;
    }

    .pagination .page-item.disabled .page-link {
        background: #f3f4f6;
        color: #9ca3af;
        border-color: #e5e7eb;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .page-header {
            padding: 1.5rem;
        }

        .filter-section {
            padding: 1rem;
        }

        .table-card {
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
                        <h4><i class="bi bi-shield-check me-2"></i>Compliance & Audit Trail Logs</h4>
                        <p class="subtitle mb-0">Monitor system compliance and track all audit activities</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="exportCsvBtn" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
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

            <!-- Enhanced Filters Section -->
            <div class="filter-section">
                <form id="filterForm" class="row g-3 align-items-end" onsubmit="return false;">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" name="search" class="form-control" placeholder="User, action, module...">
                    </div>
                    <div class="col-md-2">
                        <label for="start" class="form-label">Start Date</label>
                        <input type="date" id="start" name="start" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="end" class="form-label">End Date</label>
                        <input type="date" id="end" name="end" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Compliant">Compliant</option>
                            <option value="Non-Compliant">Non-Compliant</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="rowsPerPage" class="form-label">Rows per page</label>
                        <select id="rowsPerPage" name="rowsPerPage" class="form-select">
                            <option value="10">10 rows</option>
                            <option value="25">25 rows</option>
                            <option value="50">50 rows</option>
                            <option value="100">100 rows</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button id="filterBtn" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Enhanced Logs Table -->
            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span>Audit Trail Logs</span>
                    </h6>
                    <span id="recordInfo">Showing 0 to 0 of 0 entries</span>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover" id="logsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Date/Time</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="8" class="text-center">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="text-muted small">
                        <span id="recordInfoBottom"></span>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="logsPagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    const tbody = document.querySelector('#logsTable tbody');
    const pagination = document.getElementById('logsPagination');
    const recordInfo = document.getElementById('recordInfo');
    const recordInfoBottom = document.getElementById('recordInfoBottom');
    const searchInput = document.getElementById('search');
    const startInput = document.getElementById('start');
    const endInput = document.getElementById('end');
    const statusInput = document.getElementById('status');
    const rowsInput = document.getElementById('rowsPerPage');
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    const exportCsvBtn = document.getElementById('exportCsvBtn');
    const reloadBtn = document.getElementById('reloadBtn');

    let currentPage = 1;
    let currentLimit = 10;
    let currentFilters = {};

    function toastError(msg) { 
        Toast.fire({ icon: 'error', title: msg }); 
    }

    function toastSuccess(msg) { 
        Toast.fire({ icon: 'success', title: msg }); 
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? String(text).replace(/[&<>"']/g, m => map[m]) : '-';
    }

    function loadLogs(page = 1) {
        currentPage = page;
        currentLimit = parseInt(rowsInput.value);
        
        currentFilters = {
            action: 'list',
            page: page,
            limit: currentLimit,
            search: searchInput.value || '',
            start: startInput.value || '',
            end: endInput.value || '',
            status: statusInput.value || ''
        };

        const params = new URLSearchParams(currentFilters);

        tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

        fetch('compliance_logs_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString()
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'error') {
                toastError(data.msg || 'Failed to load logs');
                tbody.innerHTML = '<tr><td colspan="8" class="text-danger text-center">Error loading data</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            const rows = data.rows || [];
            
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted text-center py-4"><i class="bi bi-inbox"></i> No logs found</td></tr>';
                recordInfo.textContent = 'No records found';
                recordInfoBottom.textContent = '';
            } else {
                const startRecord = ((currentPage - 1) * currentLimit) + 1;
                const endRecord = Math.min(startRecord + rows.length - 1, data.total);
                
                rows.forEach((r, index) => {
                    const badgeClass =
                        r.compliance_status === 'Compliant' ? 'bg-success' :
                        r.compliance_status === 'Non-Compliant' ? 'bg-danger' :
                        r.compliance_status === 'Pending' ? 'bg-warning text-dark' :
                        'bg-info text-dark';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${startRecord + index}</td>
                        <td>${escapeHtml(r.full_name || r.username || 'System')}</td>
                        <td><small>${escapeHtml(r.action_type)}</small></td>
                        <td><small>${escapeHtml(r.module_name)}</small></td>
                        <td class="text-start"><small>${escapeHtml(r.remarks || '-')}</small></td>
                        <td><span class="badge ${badgeClass}">${escapeHtml(r.compliance_status)}</span></td>
                        <td><small>${escapeHtml(r.action_time)}</small></td>
                        <td><small>${escapeHtml(r.ip_address || '-')}</small></td>
                    `;
                    tbody.appendChild(tr);
                });

                const infoText = `Showing ${startRecord} to ${endRecord} of ${data.total} entries`;
                recordInfo.textContent = infoText;
                recordInfoBottom.textContent = infoText;
            }

            // Build pagination
            pagination.innerHTML = '';
            const totalPages = Math.max(1, Math.ceil((data.total || 0) / currentLimit));
            
            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>`;
            pagination.appendChild(prevLi);
            
            // Page numbers (show max 5 pages)
            const maxPages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
            let endPage = Math.min(totalPages, startPage + maxPages - 1);
            
            if (endPage - startPage < maxPages - 1) {
                startPage = Math.max(1, endPage - maxPages + 1);
            }
            
            if (startPage > 1) {
                const li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = `<a class="page-link" href="#" data-page="1">1</a>`;
                pagination.appendChild(li);
                
                if (startPage > 2) {
                    const dots = document.createElement('li');
                    dots.className = 'page-item disabled';
                    dots.innerHTML = `<span class="page-link">...</span>`;
                    pagination.appendChild(dots);
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                pagination.appendChild(li);
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const dots = document.createElement('li');
                    dots.className = 'page-item disabled';
                    dots.innerHTML = `<span class="page-link">...</span>`;
                    pagination.appendChild(dots);
                }
                
                const li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = `<a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a>`;
                pagination.appendChild(li);
            }
            
            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>`;
            pagination.appendChild(nextLi);
        })
        .catch(error => { 
            console.error('Error:', error); 
            toastError('Failed to load data. Please try again.');
            tbody.innerHTML = '<tr><td colspan="8" class="text-danger text-center"><i class="bi bi-exclamation-triangle"></i> Failed to load data</td></tr>';
        });
    }

    // Export PDF function
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', async function(e) {
            e.preventDefault();

            const passwordPrompt = await Swal.fire({
                title: 'Protect PDF Export',
                text: 'Enter a password required to open the exported PDF file.',
                input: 'password',
                inputLabel: 'PDF Password',
                inputPlaceholder: 'Enter at least 6 characters',
                inputAttributes: { maxlength: 64, autocapitalize: 'off', autocorrect: 'off' },
                showCancelButton: true,
                confirmButtonText: 'Export PDF',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value || value.trim().length < 6) {
                        return 'Please enter a password with at least 6 characters.';
                    }
                    return null;
                }
            });

            if (!passwordPrompt.isConfirmed) {
                return;
            }

            const params = new URLSearchParams({
                export: 'pdf',
                search: searchInput.value || '',
                start: startInput.value || '',
                end: endInput.value || '',
                status: statusInput.value || '',
                pdf_password: passwordPrompt.value
            });

            const originalHTML = exportPdfBtn.innerHTML;
            exportPdfBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
            exportPdfBtn.disabled = true;

            const exportUrl = 'compliance_logs_action.php?' + params.toString();

            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = 'compliance_logs_' + new Date().toISOString().split('T')[0] + '.pdf';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Restore button state
            setTimeout(() => {
                exportPdfBtn.innerHTML = originalHTML;
                exportPdfBtn.disabled = false;
            }, 1000);
        });
    }

    // Export CSV function
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const params = new URLSearchParams({
                export: 'csv',
                search: searchInput.value || '',
                start: startInput.value || '',
                end: endInput.value || '',
                status: statusInput.value || ''
            });
            
            const originalHTML = exportCsvBtn.innerHTML;
            exportCsvBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
            exportCsvBtn.disabled = true;
            
            const exportUrl = 'compliance_logs_action.php?' + params.toString();
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = 'compliance_logs_' + new Date().toISOString().split('T')[0] + '.csv';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Restore button state
            setTimeout(() => {
                exportCsvBtn.innerHTML = originalHTML;
                exportCsvBtn.disabled = false;
            }, 1000);
        });
    }

    // Initial load
    loadLogs();

    // Reload button - RESET ALL FILTERS
    if (reloadBtn) {
        reloadBtn.addEventListener('click', () => {
            // Clear all filter inputs
            searchInput.value = '';
            startInput.value = '';
            endInput.value = '';
            statusInput.value = '';
            rowsInput.value = '10';
            
            // Reload data from page 1
            loadLogs(1);
        });
    }

    // Filter button
    document.getElementById('filterBtn').addEventListener('click', e => {
        e.preventDefault();
        
        if (startInput.value && endInput.value && startInput.value > endInput.value) {
            return toastError('Start date must be before end date.');
        }
        
        loadLogs(1);
    });

    // Rows per page change
    rowsInput.addEventListener('change', () => {
        loadLogs(1);
    });

    // Search on Enter key
    searchInput.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadLogs(1);
        }
    });

    // Status filter change
    statusInput.addEventListener('change', () => {
        loadLogs(1);
    });

    // Pagination click handler
    pagination.addEventListener('click', e => {
        e.preventDefault();
        
        if (e.target.tagName === 'A' && !e.target.parentElement.classList.contains('disabled')) {
            const page = parseInt(e.target.dataset.page);
            if (page > 0) {
                loadLogs(page);
            }
        }
    });
});
</script>