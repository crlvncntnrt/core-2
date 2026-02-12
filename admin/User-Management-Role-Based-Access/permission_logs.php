<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once(__DIR__ . '/../inc/check_auth.php');

// ✅ Use the new RBAC check function
checkPermission('permission_logs');

require_once(__DIR__ . '/../inc/header.php');
require_once(__DIR__ . '/../inc/navbar.php');
require_once(__DIR__ . '/../inc/sidebar.php');
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

    #resultsInfo {
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

    .btn-primary {
        background: linear-gradient(135deg, var(--brand-primary), #047857);
        border: none;
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
    }

    .btn-secondary {
        background: linear-gradient(135deg, #6b7280, #4b5563);
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

    /* Loading Spinner */
    #loadingSpinner {
        padding: 2rem;
    }

    #loadingSpinner .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.3rem;
        color: var(--brand-primary);
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
                        <h4><i class="bi bi-activity me-2"></i>Permission & User Audit Logs</h4>
                        <p class="subtitle mb-0">Monitor and track all user activities and permission changes</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a id="exportCsvBtn" href="permission_logs_action.php?export=csv" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                        </a>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filters Section -->
            <div class="filter-section">
                <form id="filterForm" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="start" class="form-label">Start Date</label>
                        <input type="date" id="start" name="start" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="end" class="form-label">End Date</label>
                        <input type="date" id="end" name="end" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="rowsPerPage" class="form-label">Rows per page</label>
                        <select id="rowsPerPage" name="rowsPerPage" class="form-select">
                            <option value="5">5 rows</option>
                            <option value="10" selected>10 rows</option>
                            <option value="25">25 rows</option>
                            <option value="50">50 rows</option>
                            <option value="100">100 rows</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" id="filterBtn" class="btn btn-primary me-2">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <button type="button" id="resetBtn" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- Loading Spinner -->
            <div id="loadingSpinner" class="text-center my-5" style="display: none;">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted fw-semibold">Loading logs...</p>
            </div>

            <!-- Enhanced Audit Logs Table -->
            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span>Audit Trail Records</span>
                    </h6>
                    <span id="resultsInfo"></span>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover text-center" id="logsTable">
                        <thead>
                            <tr>
                                <th>Audit ID</th>
                                <th>Username</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Remarks</th>
                                <th>IP Address</th>
                                <th>Date / Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div>
                        <span id="resultsInfoBottom" class="text-muted small"></span>
                    </div>
                    <nav aria-label="Logs pagination">
                        <ul class="pagination pagination-sm mb-0" id="logsPagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Toast configuration
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
  });

  // DOM Elements
  const tbody = document.querySelector('#logsTable tbody');
  const pagination = document.getElementById('logsPagination');
  const resultsInfo = document.getElementById('resultsInfo');
  const resultsInfoBottom = document.getElementById('resultsInfoBottom');
  const startInput = document.getElementById('start');
  const endInput = document.getElementById('end');
  const rowsPerPageSelect = document.getElementById('rowsPerPage');
  const exportBtn = document.getElementById('exportCsvBtn');
  const filterBtn = document.getElementById('filterBtn');
  const resetBtn = document.getElementById('resetBtn');
  const loadingSpinner = document.getElementById('loadingSpinner');

  // State
  let currentPage = 1;
  let limit = parseInt(rowsPerPageSelect.value);

  // Helper functions
  const toastError = (msg) => Toast.fire({ icon: 'error', title: msg });
  const toastSuccess = (msg) => Toast.fire({ icon: 'success', title: msg });
  const toastInfo = (msg) => Toast.fire({ icon: 'info', title: msg });

  // Validate dates
  function validateDates() {
    const start = startInput.value;
    const end = endInput.value;

    if (!start || !end) return true;

    if (start > end) {
      toastError('Start date must be before or equal to End date');
      return false;
    }

    const today = new Date().toISOString().split('T')[0];
    if (start > today || end > today) {
      toastError('Cannot select future dates');
      return false;
    }

    return true;
  }

  // Build export link
  function buildExportLink(start, end) {
    let url = 'permission_logs_action.php?export=csv';
    if (start && end) {
      url += `&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
    }
    return url;
  }

  // Show/hide loading
  function setLoading(isLoading) {
    if (isLoading) {
      loadingSpinner.style.display = 'block';
      tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Loading...</td></tr>';
      filterBtn.disabled = true;
      resetBtn.disabled = true;
      rowsPerPageSelect.disabled = true;
    } else {
      loadingSpinner.style.display = 'none';
      filterBtn.disabled = false;
      resetBtn.disabled = false;
      rowsPerPageSelect.disabled = false;
    }
  }

  // Update results info
  function updateResultsInfo(page, total, limit) {
    const start = (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);
    
    if (total === 0) {
      resultsInfo.innerHTML = 'No records found';
      resultsInfoBottom.innerHTML = '';
    } else {
      const infoText = `Showing ${start} to ${end} of ${total} records`;
      resultsInfo.innerHTML = infoText;
      resultsInfoBottom.innerHTML = infoText;
    }
  }

  // Escape HTML to prevent XSS
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Load logs function
  async function loadLogs(page = 1) {
    if (!validateDates()) return;

    currentPage = page;
    limit = parseInt(rowsPerPageSelect.value);
    setLoading(true);

    const params = new URLSearchParams({
      action: 'list',
      page: page.toString(),
      limit: limit.toString(),
      start: startInput.value || '',
      end: endInput.value || ''
    });

    try {
      const response = await fetch('permission_logs_action.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params.toString()
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.status !== 'ok') {
        toastError(data.msg || 'Failed to load logs');
        tbody.innerHTML = '<tr><td colspan="7" class="text-danger"><i class="bi bi-exclamation-triangle"></i> Error loading data</td></tr>';
        console.error('API Error:', data);
        return;
      }

      const rows = data.rows || [];
      tbody.innerHTML = '';

      if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-muted"><i class="bi bi-inbox"></i> No logs found for the selected filters</td></tr>';
        pagination.innerHTML = '';
        updateResultsInfo(0, 0, limit);
      } else {
        // Populate table rows
        rows.forEach(r => {
          const actionType = r.action_type || 'N/A';
          const badgeClass = 
            actionType.toLowerCase().includes('failed') || 
            actionType.toLowerCase().includes('denied') ||
            actionType.toLowerCase().includes('error')
              ? 'bg-danger'
              : actionType.toLowerCase().includes('success') ||
                actionType.toLowerCase().includes('login')
                ? 'bg-success'
                : 'bg-info';

          const row = document.createElement('tr');
          row.innerHTML = `
            <td>${escapeHtml(r.audit_id || 'N/A')}</td>
            <td>${escapeHtml(r.username || 'System')}</td>
            <td><span class="badge ${badgeClass}">${escapeHtml(actionType)}</span></td>
            <td>${escapeHtml(r.module_name || 'N/A')}</td>
            <td class="text-start" style="max-width:400px; white-space:normal;">
              ${escapeHtml(r.remarks || 'No remarks')}
            </td>
            <td>${escapeHtml(r.ip_address || 'N/A')}</td>
            <td>${escapeHtml(r.action_time || 'N/A')}</td>
          `;
          tbody.appendChild(row);
        });

        // Build pagination
        pagination.innerHTML = '';
        const totalPages = data.totalPages || Math.ceil((data.total || 0) / limit);
        
        if (totalPages > 1) {
          // Previous button
          const prevLi = document.createElement('li');
          prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
          prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>`;
          pagination.appendChild(prevLi);

          // Page numbers
          const maxButtons = 7;
          let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
          let endPage = Math.min(totalPages, startPage + maxButtons - 1);

          if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
          }

          for (let i = startPage; i <= endPage; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
            pagination.appendChild(li);
          }

          // Next button
          const nextLi = document.createElement('li');
          nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
          nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>`;
          pagination.appendChild(nextLi);
        }

        // Update results info
        updateResultsInfo(currentPage, data.total || 0, limit);
      }

      // Update export link
      exportBtn.href = buildExportLink(startInput.value, endInput.value);

    } catch (error) {
      console.error('Fetch error:', error);
      toastError('Failed to load logs. Please try again.');
      tbody.innerHTML = '<tr><td colspan="7" class="text-danger"><i class="bi bi-exclamation-triangle"></i> Network error. Please check your connection.</td></tr>';
    } finally {
      setLoading(false);
    }
  }

  // Event: Rows per page change
  rowsPerPageSelect.addEventListener('change', () => {
    toastInfo(`Changed to ${rowsPerPageSelect.value} rows per page`);
    loadLogs(1); // Reset to page 1 when changing rows per page
  });

  // Event: Filter button
  filterBtn.addEventListener('click', (e) => {
    e.preventDefault();
    if (validateDates()) {
      toastInfo('Applying filters...');
      loadLogs(1);
    }
  });

  // Event: Reset button
  resetBtn.addEventListener('click', () => {
    startInput.value = '';
    endInput.value = '';
    rowsPerPageSelect.value = '10';
    toastInfo('Filters cleared');
    loadLogs(1);
  });

  // Event: Pagination clicks
  pagination.addEventListener('click', (e) => {
    e.preventDefault();
    const target = e.target;
    
    if (target.tagName === 'A' && target.dataset.page) {
      const page = parseInt(target.dataset.page);
      if (page >= 1) {
        loadLogs(page);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }
  });

  // Event: Enter key in date inputs
  startInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      filterBtn.click();
    }
  });

  endInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      filterBtn.click();
    }
  });

  // Initial load
  loadLogs(1);
});
</script>