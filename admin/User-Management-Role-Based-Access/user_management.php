<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once __DIR__ . '/../inc/check_auth.php';

checkPermission('user_management');

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

    /* Enhanced Stat Cards */
    .stat-card {
        padding: 1.5rem;
        border-radius: 1rem;
        color: #fff;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        border: 2px solid transparent;
        overflow: hidden;
        background: var(--card-color-1);
        box-shadow: var(--shadow-md);
        min-height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    /* Large decorative circle */
    .stat-card::before {
        content: '';
        position: absolute;
        bottom: -40px;
        right: -40px;
        width: 120px;
        height: 120px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    /* Small decorative circle */
    .stat-card::after {
        content: '';
        position: absolute;
        top: -20px;
        right: 20px;
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-xl);
    }

    .stat-card:hover::before {
        transform: scale(1.1);
        background: rgba(255, 255, 255, 0.15);
    }

    .stat-card.active-filter {
        border: 3px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2), var(--shadow-xl);
        transform: translateY(-5px);
    }

    .stat-card-content {
        position: relative;
        z-index: 2;
    }

    .stat-card-icon {
        width: 3.5rem;
        height: 3.5rem;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }

    .stat-title {
        font-size: 0.75rem;
        opacity: 0.9;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 0.75rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .stat-hint {
        font-size: 0.7rem;
        opacity: 0.85;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* Card color schemes */
    .stat-card[data-filter="all"] {
        --card-color-1: #3b82f6;
    }

    .stat-card[data-filter="Active"] {
        --card-color-1: #059669;
    }

    .stat-card[data-filter="Inactive"] {
        --card-color-1: #ef4444;
    }

    .stat-card[data-filter-role="Super Admin"] {
        --card-color-1: #3b82f6;
    }

    .stat-card[data-filter-role="Admin"] {
        --card-color-1: #6b7280;
    }

    .stat-card[data-filter-role="Staff"] {
        --card-color-1: #f59e0b;
    }

    .stat-card[data-filter-role="Client"] {
        --card-color-1: #1f2937;
    }

    .stat-card[data-filter-role="Distributor"] {
        --card-color-1: #8b5cf6;
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

    /* Active Filters Display */
    .badge-filter {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .badge-filter i {
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.2s;
    }

    .badge-filter i:hover {
        opacity: 1;
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

    #showingInfo {
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
        color: #ffffff  !important;
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
        padding: 0.35rem 0.65rem;
        font-size: 0.8rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--brand-primary), #047857);
        border: none;
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
    }

    .btn-warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border: none;
        color: white;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
    }

    .btn-secondary {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        border: none;
    }

    .btn-info {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
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

    /* Modal Enhancements */
    .modal-content {
        border-radius: 1rem;
        border: none;
        box-shadow: var(--shadow-xl);
    }

    .modal-header {
        border-bottom: 2px solid #f3f4f6;
        border-radius: 1rem 1rem 0 0;
        background: linear-gradient(135deg, var(--brand-primary), #047857);
        color: white;
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
                        <h4><i class="bi bi-people me-2"></i>User Management</h4>
                        <p class="subtitle mb-0">Manage system users, roles, and access permissions</p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (in_array($_SESSION['userdata']['role'], ['Super Admin', 'Admin'])): ?>
                        <button class="btn btn-sm btn-warning" id="viewApprovalsBtn">
                            <i class="fas fa-clipboard-check"></i> Pending Approvals
                            <span class="badge bg-danger ms-1" id="pendingCount" style="display: none;">0</span>
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary" id="addUserBtn">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                        <button class="btn btn-sm btn-outline-light" id="resetFiltersBtn">
                            <i class="fas fa-redo"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter="all">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="stat-title">Total Users</div>
                            <div id="totalUsersCard" class="stat-value">0</div>
                            <div class="stat-hint">
                                <i class="bi bi-hand-index"></i> Click to view all
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter="Active">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stat-title">Active Users</div>
                            <div id="activeUsersCard" class="stat-value">0</div>
                            <div class="stat-hint">
                                <i class="bi bi-hand-index"></i> Click to filter
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter="Inactive">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div class="stat-title">Inactive Users</div>
                            <div id="inactiveUsersCard" class="stat-value">0</div>
                            <div class="stat-hint">
                                <i class="bi bi-hand-index"></i> Click to filter
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter-role="Super Admin">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-shield-fill-check"></i>
                            </div>
                            <div class="stat-title">Super Admin</div>
                            <div id="superAdminCard" class="stat-value">0</div>
                            <div class="stat-hint">
                                <i class="bi bi-hand-index"></i> Click to filter
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter-role="Admin">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div class="stat-title">Admin</div>
                            <div id="adminCard" class="stat-value">0</div>
                            <div class="stat-hint">
                                <i class="bi bi-hand-index"></i> Click to filter
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter-role="Staff">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-person"></i>
                            </div>
                            <div class="stat-title">Staff</div>
                            <div id="staffCard" class="stat-value">0</div>
                            <div class="stat-hint">
                                <i class="bi bi-hand-index"></i> Click to filter
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Filters Display -->
            <div id="activeFilters" class="mb-3"></div>

            <!-- Enhanced Filters Section -->
            <div class="filter-section">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search users...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <select id="roleFilter" class="form-select">
                            <option value="">All Roles</option>
                            <option value="Super Admin">Super Admin</option>
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                            <option value="Client">Client</option>
                            <option value="Distributor">Distributor</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rows per page</label>
                        <select id="rowsPerPage" class="form-select">
                            <option value="10">10 rows</option>
                            <option value="25">25 rows</option>
                            <option value="50">50 rows</option>
                            <option value="100">100 rows</option>
                            <option value="all">All rows</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Enhanced User Table -->
            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span>User Directory</span>
                    </h6>
                    <span id="showingInfo"></span>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Date Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <tr>
                                <td colspan="8" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div>
                        <span id="showingInfoBottom" class="text-muted small"></span>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle"><i class="fas fa-plus-circle me-2"></i>Add User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="user_id">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger" id="passwordRequired">*</span></label>
                        <input type="password" name="password" id="password" class="form-control">
                        <small class="text-muted" id="passwordHelp" style="display: none;">Leave blank to keep current password.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="Super Admin">Super Admin</option>
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="status" id="status" class="form-check-input" checked>
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send for Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="approvalForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Send Profile for Approval</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="approval_user_id" id="approval_user_id">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This profile update will be sent to administrators for approval.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="approval_username" id="approval_username" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="approval_full_name" id="approval_full_name" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="approval_email" id="approval_email" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" name="approval_role" id="approval_role" class="form-control" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send for Approval
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pending Approvals Modal -->
<div class="modal fade" id="pendingApprovalsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Pending Approval Requests</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="pendingApprovalsList"></div>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
  const userModal = new bootstrap.Modal(document.getElementById('userModal'));
  const approvalModal = new bootstrap.Modal(document.getElementById('approvalModal'));
  const pendingApprovalsModal = new bootstrap.Modal(document.getElementById('pendingApprovalsModal'));
  
  let currentUserId = null;
  let allUsers = [];
  let filteredUsers = [];
  let currentPage = 1;
  let rowsPerPage = 10;
  
  // Current user info
  const currentUserRole = '<?php echo $_SESSION['userdata']['role']; ?>';
  const isAdmin = ['Super Admin', 'Admin'].includes(currentUserRole);
  
  // Filter state
  let filters = {
    search: '',
    role: '',
    status: ''
  };

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function loadUsers() {
    document.getElementById('userTableBody').innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';
    
    fetch('user_action.php?action=list')
      .then(res => res.json())
      .then(data => {
        if (data.users) {
          allUsers = data.users;
          applyFilters();
          updateSummaryCards();
        } else {
          allUsers = [];
          filteredUsers = [];
          renderTable();
          updateSummaryCards();
        }
      })
      .catch(err => {
        console.error('Error loading users:', err);
        Swal.fire('Error', 'Failed to load users. Please try again.', 'error');
        document.getElementById('userTableBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger"><i class="bi bi-exclamation-triangle"></i> Error loading users</td></tr>';
      });
  }

  function applyFilters() {
    filteredUsers = allUsers.filter(user => {
      // Search filter
      if (filters.search) {
        const searchLower = filters.search.toLowerCase();
        const matchSearch = 
          user.username.toLowerCase().includes(searchLower) ||
          user.full_name.toLowerCase().includes(searchLower) ||
          (user.email && user.email.toLowerCase().includes(searchLower)) ||
          user.user_id.toString().includes(searchLower);
        if (!matchSearch) return false;
      }
      
      // Role filter
      if (filters.role && user.role !== filters.role) {
        return false;
      }
      
      // Status filter
      if (filters.status && user.status !== filters.status) {
        return false;
      }
      
      return true;
    });
    
    currentPage = 1;
    renderTable();
    updateActiveFiltersDisplay();
  }

  function renderTable() {
    const tbody = document.getElementById('userTableBody');
    tbody.innerHTML = '';
    
    if (filteredUsers.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted"><i class="bi bi-inbox"></i> No users found</td></tr>';
      updatePagination();
      return;
    }
    
    const start = rowsPerPage === 'all' ? 0 : (currentPage - 1) * parseInt(rowsPerPage);
    const end = rowsPerPage === 'all' ? filteredUsers.length : start + parseInt(rowsPerPage);
    const pageUsers = filteredUsers.slice(start, end);
    
    pageUsers.forEach(u => {
      const statusBadge = u.status === 'Active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-danger">Inactive</span>';
      
      // Action buttons - REMOVED EDIT, ADDED SEND FOR APPROVAL
      let actionButtons = '';
      
      // Send for Approval button (for users to request changes)
      actionButtons += `
        <button class="btn btn-sm btn-info sendApprovalBtn" data-id="${u.user_id}" title="Send for Approval">
          <i class="fas fa-paper-plane"></i>
        </button>`;
      
      // Delete button (admin only)
      if (isAdmin) {
        actionButtons += `
          <button class="btn btn-sm btn-danger deleteBtn" data-id="${u.user_id}" title="Delete">
            <i class="fas fa-trash"></i>
          </button>`;
      }
      
      // Toggle status button (admin only)
      if (isAdmin) {
        actionButtons += `
          <button class="btn btn-sm btn-secondary toggleBtn" data-id="${u.user_id}" data-status="${u.status}" title="${u.status === 'Active' ? 'Deactivate' : 'Activate'}">
            <i class="fas fa-${u.status === 'Active' ? 'ban' : 'check'}"></i>
          </button>`;
      }
      
      tbody.innerHTML += `
        <tr>
          <td>${escapeHtml(u.user_id)}</td>
          <td>${escapeHtml(u.username)}</td>
          <td>${escapeHtml(u.full_name)}</td>
          <td>${escapeHtml(u.email)}</td>
          <td><span class="badge bg-info">${escapeHtml(u.role)}</span></td>
          <td>${statusBadge}</td>
          <td>${escapeHtml(u.date_created)}</td>
          <td class="text-center">${actionButtons}</td>
        </tr>`;
    });
    
    updatePagination();
  }

  function updatePagination() {
    const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(filteredUsers.length / parseInt(rowsPerPage));
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    if (totalPages <= 1) {
      document.getElementById('showingInfo').textContent = `Showing ${filteredUsers.length} of ${filteredUsers.length} users`;
      document.getElementById('showingInfoBottom').textContent = `Showing ${filteredUsers.length} of ${filteredUsers.length} users`;
      return;
    }
    
    // Previous button
    pagination.innerHTML += `
      <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
      </li>`;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
        pagination.innerHTML += `
          <li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" data-page="${i}">${i}</a>
          </li>`;
      } else if (i === currentPage - 3 || i === currentPage + 3) {
        pagination.innerHTML += `<li class="page-item disabled"><a class="page-link">...</a></li>`;
      }
    }
    
    // Next button
    pagination.innerHTML += `
      <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
      </li>`;
    
    const start = (currentPage - 1) * parseInt(rowsPerPage) + 1;
    const end = Math.min(currentPage * parseInt(rowsPerPage), filteredUsers.length);
    const infoText = `Showing ${start} to ${end} of ${filteredUsers.length} users`;
    document.getElementById('showingInfo').textContent = infoText;
    document.getElementById('showingInfoBottom').textContent = infoText;
  }

  function updateSummaryCards() {
    let total = 0, active = 0, inactive = 0;
    let superAdmin = 0, admin = 0, staff = 0;
    
    allUsers.forEach(u => {
      total++;
      if (u.status === 'Active') active++;
      else inactive++;
      
      switch(u.role) {
        case 'Super Admin': superAdmin++; break;
        case 'Admin': admin++; break;
        case 'Staff': staff++; break;
      }
    });
    
    document.getElementById('totalUsersCard').innerText = total;
    document.getElementById('activeUsersCard').innerText = active;
    document.getElementById('inactiveUsersCard').innerText = inactive;
    document.getElementById('superAdminCard').innerText = superAdmin;
    document.getElementById('adminCard').innerText = admin;
    document.getElementById('staffCard').innerText = staff;
  }

  function updateActiveFiltersDisplay() {
    const container = document.getElementById('activeFilters');
    let html = '';
    
    if (filters.search) {
      html += `<span class="badge bg-primary badge-filter">Search: "${escapeHtml(filters.search)}" <i class="fas fa-times" style="cursor: pointer;" onclick="clearFilter('search')"></i></span>`;
    }
    if (filters.role) {
      html += `<span class="badge bg-info badge-filter">Role: ${escapeHtml(filters.role)} <i class="fas fa-times" style="cursor: pointer;" onclick="clearFilter('role')"></i></span>`;
    }
    if (filters.status) {
      html += `<span class="badge bg-success badge-filter">Status: ${escapeHtml(filters.status)} <i class="fas fa-times" style="cursor: pointer;" onclick="clearFilter('status')"></i></span>`;
    }
    
    container.innerHTML = html;
    
    // Update card highlights
    document.querySelectorAll('.stat-card').forEach(card => {
      card.classList.remove('active-filter');
    });
    
    if (filters.status) {
      document.querySelector(`[data-filter="${filters.status}"]`)?.classList.add('active-filter');
    }
    if (filters.role) {
      document.querySelector(`[data-filter-role="${filters.role}"]`)?.classList.add('active-filter');
    }
  }

  // Load pending approval count (for admins)
  function loadPendingCount() {
    if (!isAdmin) return;
    
    fetch('approval_action.php?action=get_notification_count')
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success' && data.count > 0) {
          document.getElementById('pendingCount').style.display = 'inline';
          document.getElementById('pendingCount').textContent = data.count;
        }
      })
      .catch(err => console.error('Error loading pending count:', err));
  }

  // Load pending approvals
  function loadPendingApprovals() {
    const container = document.getElementById('pendingApprovalsList');
    container.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p>Loading...</p></div>';
    
    fetch('approval_action.php?action=get_pending')
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          if (data.requests.length === 0) {
            container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No pending approval requests</div>';
            return;
          }
          
          let html = '';
          data.requests.forEach(req => {
            const reqData = req.request_data_parsed;
            html += `
              <div class="card mb-3">
                <div class="card-header bg-light">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <strong>${escapeHtml(req.full_name)}</strong> (${escapeHtml(req.username)})
                      <small class="text-muted">- ${new Date(req.created_at).toLocaleString()}</small>
                    </div>
                    <span class="badge bg-warning">Pending</span>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6">
                      <h6>Requested Changes:</h6>
                      <ul class="list-unstyled">
                        <li><strong>Username:</strong> ${escapeHtml(reqData.username || '')}</li>
                        <li><strong>Full Name:</strong> ${escapeHtml(reqData.full_name || '')}</li>
                        <li><strong>Email:</strong> ${escapeHtml(reqData.email || '')}</li>
                        <li><strong>Role:</strong> ${escapeHtml(reqData.role || '')}</li>
                        <li><strong>Status:</strong> ${escapeHtml(reqData.status || '')}</li>
                      </ul>
                    </div>
                    <div class="col-md-6">
                      <h6>Current Data:</h6>
                      <ul class="list-unstyled text-muted">
                        <li><strong>Username:</strong> ${escapeHtml(req.username)}</li>
                        <li><strong>Full Name:</strong> ${escapeHtml(req.full_name)}</li>
                        <li><strong>Email:</strong> ${escapeHtml(req.email)}</li>
                        <li><strong>Role:</strong> ${escapeHtml(req.current_role)}</li>
                      </ul>
                    </div>
                  </div>
                  <div class="mt-3">
                    <button class="btn btn-success btn-sm approveBtn" data-id="${req.request_id}">
                      <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn btn-danger btn-sm rejectBtn" data-id="${req.request_id}">
                      <i class="fas fa-times"></i> Reject
                    </button>
                  </div>
                </div>
              </div>`;
          });
          container.innerHTML = html;
          
          // Add event listeners
          container.querySelectorAll('.approveBtn').forEach(btn => {
            btn.addEventListener('click', function() {
              approveRequest(this.dataset.id);
            });
          });
          
          container.querySelectorAll('.rejectBtn').forEach(btn => {
            btn.addEventListener('click', function() {
              rejectRequest(this.dataset.id);
            });
          });
        }
      })
      .catch(err => {
        console.error('Error:', err);
        container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error loading approvals</div>';
      });
  }

  // Approve request
  function approveRequest(requestId) {
    Swal.fire({
      title: 'Approve Request?',
      input: 'textarea',
      inputLabel: 'Review Notes (optional)',
      inputPlaceholder: 'Enter any notes about this approval...',
      showCancelButton: true,
      confirmButtonText: 'Approve',
      confirmButtonColor: '#10b981'
    }).then((result) => {
      if (result.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'approve');
        fd.append('request_id', requestId);
        fd.append('review_notes', result.value || '');
        
        fetch('approval_action.php', {
          method: 'POST',
          body: fd
        })
        .then(res => res.json())
        .then(resp => {
          if (resp.status === 'success') {
            Swal.fire('Approved!', resp.msg, 'success');
            loadPendingApprovals();
            loadPendingCount();
            loadUsers();
          } else {
            Swal.fire('Error', resp.msg, 'error');
          }
        })
        .catch(err => {
          console.error('Error:', err);
          Swal.fire('Error', 'Failed to approve request', 'error');
        });
      }
    });
  }

  // Reject request
  function rejectRequest(requestId) {
    Swal.fire({
      title: 'Reject Request?',
      input: 'textarea',
      inputLabel: 'Reason for Rejection',
      inputPlaceholder: 'Enter reason for rejection...',
      showCancelButton: true,
      confirmButtonText: 'Reject',
      confirmButtonColor: '#ef4444',
      inputValidator: (value) => {
        if (!value) {
          return 'Please provide a reason for rejection'
        }
      }
    }).then((result) => {
      if (result.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'reject');
        fd.append('request_id', requestId);
        fd.append('review_notes', result.value);
        
        fetch('approval_action.php', {
          method: 'POST',
          body: fd
        })
        .then(res => res.json())
        .then(resp => {
          if (resp.status === 'success') {
            Swal.fire('Rejected!', resp.msg, 'success');
            loadPendingApprovals();
            loadPendingCount();
          } else {
            Swal.fire('Error', resp.msg, 'error');
          }
        })
        .catch(err => {
          console.error('Error:', err);
          Swal.fire('Error', 'Failed to reject request', 'error');
        });
      }
    });
  }

  window.clearFilter = function(filterType) {
    if (filterType === 'search') {
      filters.search = '';
      document.getElementById('searchInput').value = '';
    } else if (filterType === 'role') {
      filters.role = '';
      document.getElementById('roleFilter').value = '';
    } else if (filterType === 'status') {
      filters.status = '';
      document.getElementById('statusFilter').value = '';
    }
    applyFilters();
  }

  // Event Listeners
  document.getElementById('searchInput').addEventListener('input', function() {
    filters.search = this.value;
    applyFilters();
  });

  document.getElementById('roleFilter').addEventListener('change', function() {
    filters.role = this.value;
    applyFilters();
  });

  document.getElementById('statusFilter').addEventListener('change', function() {
    filters.status = this.value;
    applyFilters();
  });

  document.getElementById('rowsPerPage').addEventListener('change', function() {
    rowsPerPage = this.value;
    currentPage = 1;
    renderTable();
  });

  document.getElementById('pagination').addEventListener('click', function(e) {
    e.preventDefault();
    if (e.target.tagName === 'A' && e.target.dataset.page) {
      currentPage = parseInt(e.target.dataset.page);
      renderTable();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });

  document.getElementById('resetFiltersBtn').addEventListener('click', function() {
    filters = { search: '', role: '', status: '' };
    document.getElementById('searchInput').value = '';
    document.getElementById('roleFilter').value = '';
    document.getElementById('statusFilter').value = '';
    applyFilters();
  });

  // View pending approvals button
  if (isAdmin) {
    document.getElementById('viewApprovalsBtn').addEventListener('click', function() {
      loadPendingApprovals();
      pendingApprovalsModal.show();
    });
  }

  // Clickable summary cards
  document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('click', function() {
      const filterType = this.dataset.filter;
      const filterRole = this.dataset.filterRole;
      
      if (filterType) {
        if (filterType === 'all') {
          filters.status = '';
          document.getElementById('statusFilter').value = '';
        } else {
          filters.status = filterType;
          document.getElementById('statusFilter').value = filterType;
        }
      }
      
      if (filterRole) {
        filters.role = filterRole;
        document.getElementById('roleFilter').value = filterRole;
      }
      
      applyFilters();
    });
  });

  document.getElementById('addUserBtn').addEventListener('click', () => {
    currentUserId = null;
    document.getElementById('userForm').reset();
    document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add User';
    document.getElementById('password').setAttribute('required', 'required');
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('passwordHelp').style.display = 'none';
    document.getElementById('role').value = '';
    userModal.show();
  });

  document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', currentUserId ? 'edit' : 'add');
    fd.append('status', document.getElementById('status').checked ? 'Active' : 'Inactive');
    if (currentUserId) fd.append('user_id', currentUserId);
    
    fetch('user_action.php', {
      method: 'POST',
      body: fd
    })
    .then(res => res.json())
    .then(resp => {
      if (resp.status === 'success') {
        Swal.fire('Success', resp.msg, 'success');
        userModal.hide();
        loadUsers();
      } else {
        Swal.fire('Error', resp.msg, 'error');
      }
    })
    .catch(err => {
      console.error('Error:', err);
      Swal.fire('Error', 'Failed to save user. Please try again.', 'error');
    });
  });

  // Send for Approval Form
  document.getElementById('approvalForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const userId = document.getElementById('approval_user_id').value;
    const username = document.getElementById('approval_username').value;
    const fullName = document.getElementById('approval_full_name').value;
    const email = document.getElementById('approval_email').value;
    const role = document.getElementById('approval_role').value;
    
    const requestData = JSON.stringify({
      username: username,
      full_name: fullName,
      email: email,
      role: role,
      status: 'Active'
    });
    
    const fd = new FormData();
    fd.append('action', 'submit_request');
    fd.append('user_id', userId);
    fd.append('request_type', 'profile_update');
    fd.append('request_data', requestData);
    
    fetch('approval_action.php', {
      method: 'POST',
      body: fd
    })
    .then(res => res.json())
    .then(resp => {
      if (resp.status === 'success') {
        Swal.fire('Success!', resp.msg, 'success');
        approvalModal.hide();
        if (isAdmin) {
          loadPendingCount();
        }
      } else {
        Swal.fire('Error', resp.msg, 'error');
      }
    })
    .catch(err => {
      console.error('Error:', err);
      Swal.fire('Error', 'Failed to submit approval request', 'error');
    });
  });

  document.getElementById('userTableBody').addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    
    const id = btn.dataset.id;
    
    // Send for Approval button
    if (btn.classList.contains('sendApprovalBtn')) {
      fetch('user_action.php?action=get&id=' + id)
        .then(res => res.json())
        .then(u => {
          document.getElementById('approval_user_id').value = u.user_id;
          document.getElementById('approval_username').value = u.username;
          document.getElementById('approval_full_name').value = u.full_name;
          document.getElementById('approval_email').value = u.email || '';
          document.getElementById('approval_role').value = u.role;
          approvalModal.show();
        })
        .catch(err => {
          console.error('Error:', err);
          Swal.fire('Error', 'Failed to load user data.', 'error');
        });
    }
    
    if (btn.classList.contains('deleteBtn')) {
      Swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const fd = new FormData();
          fd.append('action', 'delete');
          fd.append('id', id);
          fetch('user_action.php', {
            method: 'POST',
            body: fd
          })
          .then(res => res.json())
          .then(resp => {
            if (resp.status === 'success') {
              Swal.fire('Deleted!', resp.msg, 'success');
              loadUsers();
            } else {
              Swal.fire('Error', resp.msg, 'error');
            }
          })
          .catch(err => {
            console.error('Error:', err);
            Swal.fire('Error', 'Failed to delete user.', 'error');
          });
        }
      });
    }
    
    if (btn.classList.contains('toggleBtn')) {
      const status = btn.dataset.status === 'Active' ? 'Inactive' : 'Active';
      const fd = new FormData();
      fd.append('action', 'toggle_status');
      fd.append('id', id);
      fd.append('status', status);
      fetch('user_action.php', {
        method: 'POST',
        body: fd
      })
      .then(res => res.json())
      .then(resp => {
        if (resp.status === 'success') {
          Swal.fire('Success', resp.msg, 'success');
          loadUsers();
        } else {
          Swal.fire('Error', resp.msg, 'error');
        }
      })
      .catch(err => {
        console.error('Error:', err);
        Swal.fire('Error', 'Failed to update user status.', 'error');
      });
    }
  });

  // Initialize
  loadUsers();
  if (isAdmin) {
    loadPendingCount();
    // Auto-refresh pending count every 30 seconds
    setInterval(loadPendingCount, 30000);
  }
</script>