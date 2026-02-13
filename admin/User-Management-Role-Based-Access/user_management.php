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

    .stat-card-content { position: relative; z-index: 2; }

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

    .stat-card[data-filter="all"]            { --card-color-1: #3b82f6; }
    .stat-card[data-filter="Active"]         { --card-color-1: #059669; }
    .stat-card[data-filter="Inactive"]       { --card-color-1: #ef4444; }
    .stat-card[data-filter-role="Super Admin"]{ --card-color-1: #3b82f6; }
    .stat-card[data-filter-role="Admin"]     { --card-color-1: #6b7280; }
    .stat-card[data-filter-role="Staff"]     { --card-color-1: #f59e0b; }
    .stat-card[data-filter-role="Client"]    { --card-color-1: #1f2937; }
    .stat-card[data-filter-role="Distributor"]{ --card-color-1: #8b5cf6; }

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

    .badge-filter {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .badge-filter i { cursor: pointer; opacity: 0.8; transition: opacity 0.2s; }
    .badge-filter i:hover { opacity: 1; }

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

    #showingInfo { color: #6b7280; font-size: 0.875rem; font-weight: 500; }

    .table-wrapper {
        overflow-x: auto;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
    }

    .table { margin-bottom: 0; }
    .table thead { background: #1f2937 !important; }
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

    .btn {
        border-radius: 0.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .btn:active { transform: translateY(0); }
    .btn-sm { padding: 0.35rem 0.65rem; font-size: 0.8rem; }
    .btn-primary   { background: linear-gradient(135deg, var(--brand-primary), #047857); border: none; }
    .btn-success   { background: linear-gradient(135deg, #10b981, #059669); border: none; }
    .btn-warning   { background: linear-gradient(135deg, #f59e0b, #d97706); border: none; color: white; }
    .btn-danger    { background: linear-gradient(135deg, #ef4444, #dc2626); border: none; }
    .btn-secondary { background: linear-gradient(135deg, #6b7280, #4b5563); border: none; }
    .btn-info      { background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid #f3f4f6;
    }

    .pagination { margin-bottom: 0; }

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

    .pagination .page-link:hover { background: var(--brand-primary); color: white; border-color: var(--brand-primary); }
    .pagination .page-item.active .page-link { background: var(--brand-primary); border-color: var(--brand-primary); color: white; }
    .pagination .page-item.disabled .page-link { background: #f3f4f6; color: #9ca3af; border-color: #e5e7eb; }

    .modal-content { border-radius: 1rem; border: none; box-shadow: var(--shadow-xl); }
    .modal-header {
        border-bottom: 2px solid #f3f4f6;
        border-radius: 1rem 1rem 0 0;
        background: linear-gradient(135deg, var(--brand-primary), #047857);
        color: white;
    }
    .modal-footer { border-top: 2px solid #f3f4f6; }

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

            <!-- Header — NO Pending Approvals button -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4><i class="bi bi-people me-2"></i>User Management</h4>
                        <p class="subtitle mb-0">Manage system users, roles, and access permissions</p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (in_array($_SESSION['userdata']['role'], ['Super Admin', 'Admin'])): ?>
                        <button class="btn btn-sm btn-primary" id="addUserBtn">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-light" id="resetFiltersBtn">
                            <i class="fas fa-redo"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter="all">
                        <div class="stat-card-content">
                            <div class="stat-card-icon"><i class="bi bi-people-fill"></i></div>
                            <div class="stat-title">Total Users</div>
                            <div id="totalUsersCard" class="stat-value">0</div>
                            <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to view all</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter="Active">
                        <div class="stat-card-content">
                            <div class="stat-card-icon"><i class="bi bi-check-circle"></i></div>
                            <div class="stat-title">Active Users</div>
                            <div id="activeUsersCard" class="stat-value">0</div>
                            <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter="Inactive">
                        <div class="stat-card-content">
                            <div class="stat-card-icon"><i class="bi bi-x-circle"></i></div>
                            <div class="stat-title">Inactive Users</div>
                            <div id="inactiveUsersCard" class="stat-value">0</div>
                            <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter-role="Super Admin">
                        <div class="stat-card-content">
                            <div class="stat-card-icon"><i class="bi bi-shield-fill-check"></i></div>
                            <div class="stat-title">Super Admin</div>
                            <div id="superAdminCard" class="stat-value">0</div>
                            <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter-role="Admin">
                        <div class="stat-card-content">
                            <div class="stat-card-icon"><i class="bi bi-person-badge"></i></div>
                            <div class="stat-title">Admin</div>
                            <div id="adminCard" class="stat-value">0</div>
                            <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card" data-filter-role="Staff">
                        <div class="stat-card-content">
                            <div class="stat-card-icon"><i class="bi bi-person"></i></div>
                            <div class="stat-title">Staff</div>
                            <div id="staffCard" class="stat-value">0</div>
                            <div class="stat-hint"><i class="bi bi-hand-index"></i> Click to filter</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Filters Display -->
            <div id="activeFilters" class="mb-3"></div>

            <!-- Filters -->
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

            <!-- User Table -->
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
                            <tr><td colspan="8" class="text-center">Loading...</td></tr>
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

<!-- Add / Edit User Modal -->
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
                        <small class="text-muted" id="passwordHelp" style="display:none;">Leave blank to keep current password.</small>
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

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
  const userModal = new bootstrap.Modal(document.getElementById('userModal'));

  let currentUserId = null;
  let allUsers      = [];
  let filteredUsers = [];
  let currentPage   = 1;
  let rowsPerPage   = 10;

  const currentUserRole = '<?php echo addslashes($_SESSION['userdata']['role']); ?>';
  const isAdmin = ['Super Admin', 'Admin'].includes(currentUserRole);

  let filters = { search: '', role: '', status: '' };

  /* ── helpers ── */
  function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  /* ── load users ── */
  function loadUsers() {
    document.getElementById('userTableBody').innerHTML =
      '<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

    fetch('user_action.php?action=list')
      .then(r => r.json())
      .then(data => {
        allUsers = data.users || [];
        applyFilters();
        updateSummaryCards();
      })
      .catch(() => {
        Swal.fire('Error', 'Failed to load users. Please try again.', 'error');
        document.getElementById('userTableBody').innerHTML =
          '<tr><td colspan="8" class="text-center text-danger"><i class="bi bi-exclamation-triangle"></i> Error loading users</td></tr>';
      });
  }

  /* ── filters ── */
  function applyFilters() {
    filteredUsers = allUsers.filter(u => {
      if (filters.search) {
        const s = filters.search.toLowerCase();
        if (![u.username, u.full_name, u.email, String(u.user_id)]
              .some(v => v && v.toLowerCase().includes(s))) return false;
      }
      if (filters.role   && u.role   !== filters.role)   return false;
      if (filters.status && u.status !== filters.status) return false;
      return true;
    });
    currentPage = 1;
    renderTable();
    updateActiveFiltersDisplay();
  }

  /* ── render table ── */
  function renderTable() {
    const tbody = document.getElementById('userTableBody');
    tbody.innerHTML = '';

    if (!filteredUsers.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted"><i class="bi bi-inbox"></i> No users found</td></tr>';
      updatePagination();
      return;
    }

    const start = rowsPerPage === 'all' ? 0 : (currentPage - 1) * parseInt(rowsPerPage);
    const end   = rowsPerPage === 'all' ? filteredUsers.length : start + parseInt(rowsPerPage);

    filteredUsers.slice(start, end).forEach(u => {
      const statusBadge = u.status === 'Active'
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-danger">Inactive</span>';

      let actions = '';

      // Edit button — Super Admin & Admin only
      if (isAdmin) {
        actions += `
          <button class="btn btn-sm btn-warning editBtn" data-id="${u.user_id}" title="Edit User">
            <i class="fas fa-edit"></i>
          </button>`;
      }

      // Delete — Super Admin & Admin only
      if (isAdmin) {
        actions += `
          <button class="btn btn-sm btn-danger deleteBtn" data-id="${u.user_id}" title="Delete">
            <i class="fas fa-trash"></i>
          </button>`;
      }

      // Toggle status — Super Admin & Admin only
      if (isAdmin) {
        actions += `
          <button class="btn btn-sm btn-secondary toggleBtn"
            data-id="${u.user_id}" data-status="${u.status}"
            title="${u.status === 'Active' ? 'Deactivate' : 'Activate'}">
            <i class="fas fa-${u.status === 'Active' ? 'ban' : 'check'}"></i>
          </button>`;
      }

      if (!actions) actions = '<span class="text-muted small">—</span>';

      tbody.innerHTML += `
        <tr>
          <td>${escapeHtml(u.user_id)}</td>
          <td>${escapeHtml(u.username)}</td>
          <td>${escapeHtml(u.full_name)}</td>
          <td>${escapeHtml(u.email)}</td>
          <td><span class="badge bg-info">${escapeHtml(u.role)}</span></td>
          <td>${statusBadge}</td>
          <td>${escapeHtml(u.date_created)}</td>
          <td class="text-center">${actions}</td>
        </tr>`;
    });

    updatePagination();
  }

  /* ── pagination ── */
  function updatePagination() {
    const total      = filteredUsers.length;
    const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(total / parseInt(rowsPerPage));
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';

    const start = rowsPerPage === 'all' ? 1 : (currentPage - 1) * parseInt(rowsPerPage) + 1;
    const end   = rowsPerPage === 'all' ? total : Math.min(currentPage * parseInt(rowsPerPage), total);
    const info  = total ? `Showing ${start} to ${end} of ${total} users` : 'No users found';

    document.getElementById('showingInfo').textContent       = info;
    document.getElementById('showingInfoBottom').textContent = info;

    if (totalPages <= 1) return;

    pagination.innerHTML += `<li class="page-item ${currentPage===1?'disabled':''}">
      <a class="page-link" href="#" data-page="${currentPage-1}">Previous</a></li>`;

    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentPage-2 && i <= currentPage+2)) {
        pagination.innerHTML += `<li class="page-item ${i===currentPage?'active':''}">
          <a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
      } else if (i === currentPage-3 || i === currentPage+3) {
        pagination.innerHTML += `<li class="page-item disabled"><a class="page-link">…</a></li>`;
      }
    }

    pagination.innerHTML += `<li class="page-item ${currentPage===totalPages?'disabled':''}">
      <a class="page-link" href="#" data-page="${currentPage+1}">Next</a></li>`;
  }

  /* ── summary cards ── */
  function updateSummaryCards() {
    let total=0, active=0, inactive=0, superAdmin=0, admin=0, staff=0;
    allUsers.forEach(u => {
      total++;
      u.status === 'Active' ? active++ : inactive++;
      if (u.role === 'Super Admin') superAdmin++;
      else if (u.role === 'Admin')  admin++;
      else if (u.role === 'Staff')  staff++;
    });
    document.getElementById('totalUsersCard').innerText   = total;
    document.getElementById('activeUsersCard').innerText  = active;
    document.getElementById('inactiveUsersCard').innerText= inactive;
    document.getElementById('superAdminCard').innerText   = superAdmin;
    document.getElementById('adminCard').innerText        = admin;
    document.getElementById('staffCard').innerText        = staff;
  }

  /* ── active filter badges ── */
  function updateActiveFiltersDisplay() {
    let html = '';
    if (filters.search)
      html += `<span class="badge bg-primary badge-filter">Search: "${escapeHtml(filters.search)}" <i class="fas fa-times" onclick="clearFilter('search')"></i></span>`;
    if (filters.role)
      html += `<span class="badge bg-info badge-filter">Role: ${escapeHtml(filters.role)} <i class="fas fa-times" onclick="clearFilter('role')"></i></span>`;
    if (filters.status)
      html += `<span class="badge bg-success badge-filter">Status: ${escapeHtml(filters.status)} <i class="fas fa-times" onclick="clearFilter('status')"></i></span>`;

    document.getElementById('activeFilters').innerHTML = html;

    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
    if (filters.status) document.querySelector(`[data-filter="${filters.status}"]`)?.classList.add('active-filter');
    if (filters.role)   document.querySelector(`[data-filter-role="${filters.role}"]`)?.classList.add('active-filter');
  }

  window.clearFilter = function(type) {
    if (type === 'search') { filters.search=''; document.getElementById('searchInput').value=''; }
    if (type === 'role')   { filters.role='';   document.getElementById('roleFilter').value=''; }
    if (type === 'status') { filters.status=''; document.getElementById('statusFilter').value=''; }
    applyFilters();
  };

  /* ── event listeners ── */
  document.getElementById('searchInput').addEventListener('input', function(){ filters.search=this.value; applyFilters(); });
  document.getElementById('roleFilter').addEventListener('change', function(){ filters.role=this.value; applyFilters(); });
  document.getElementById('statusFilter').addEventListener('change', function(){ filters.status=this.value; applyFilters(); });
  document.getElementById('rowsPerPage').addEventListener('change', function(){ rowsPerPage=this.value; currentPage=1; renderTable(); });

  document.getElementById('pagination').addEventListener('click', function(e){
    e.preventDefault();
    if (e.target.tagName==='A' && e.target.dataset.page) {
      currentPage = parseInt(e.target.dataset.page);
      renderTable();
      window.scrollTo({top:0, behavior:'smooth'});
    }
  });

  document.getElementById('resetFiltersBtn').addEventListener('click', function(){
    filters = {search:'', role:'', status:''};
    document.getElementById('searchInput').value='';
    document.getElementById('roleFilter').value='';
    document.getElementById('statusFilter').value='';
    applyFilters();
  });

  /* stat cards clickable */
  document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('click', function(){
      const ft = this.dataset.filter;
      const fr = this.dataset.filterRole;
      if (ft) {
        filters.status = ft==='all' ? '' : ft;
        document.getElementById('statusFilter').value = filters.status;
      }
      if (fr) {
        filters.role = fr;
        document.getElementById('roleFilter').value = fr;
      }
      applyFilters();
    });
  });

  /* ── Add User ── */
  if (isAdmin) {
    document.getElementById('addUserBtn').addEventListener('click', () => {
      currentUserId = null;
      document.getElementById('userForm').reset();
      document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add User';
      document.getElementById('password').setAttribute('required','required');
      document.getElementById('passwordRequired').style.display = 'inline';
      document.getElementById('passwordHelp').style.display = 'none';
      document.getElementById('role').value = '';
      document.getElementById('status').checked = true;
      userModal.show();
    });
  }

  /* ── Submit Add/Edit form ── */
  document.getElementById('userForm').addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', currentUserId ? 'edit' : 'add');
    fd.append('status', document.getElementById('status').checked ? 'Active' : 'Inactive');
    if (currentUserId) fd.append('user_id', currentUserId);

    fetch('user_action.php', { method:'POST', body:fd })
      .then(r => r.json())
      .then(resp => {
        if (resp.status === 'success') {
          Swal.fire('Success', resp.msg, 'success');
          userModal.hide();
          loadUsers();
        } else {
          Swal.fire('Error', resp.msg, 'error');
        }
      })
      .catch(() => Swal.fire('Error', 'Failed to save user. Please try again.', 'error'));
  });

  /* ── Table button clicks ── */
  document.getElementById('userTableBody').addEventListener('click', function(e){
    const btn = e.target.closest('button');
    if (!btn) return;
    const id = btn.dataset.id;

    /* Edit */
    if (btn.classList.contains('editBtn')) {
      fetch('user_action.php?action=get&id=' + id)
        .then(r => r.json())
        .then(u => {
          if (u.status === 'error') { Swal.fire('Error', u.msg, 'error'); return; }
          currentUserId = u.user_id;
          document.getElementById('user_id').value     = u.user_id;
          document.getElementById('username').value    = u.username;
          document.getElementById('full_name').value   = u.full_name;
          document.getElementById('email').value       = u.email || '';
          document.getElementById('role').value        = u.role;
          document.getElementById('status').checked    = u.status === 'Active';
          document.getElementById('password').value    = '';
          document.getElementById('password').removeAttribute('required');
          document.getElementById('passwordRequired').style.display = 'none';
          document.getElementById('passwordHelp').style.display     = 'inline';
          document.getElementById('userModalTitle').innerHTML =
            '<i class="fas fa-edit me-2"></i>Edit User';
          userModal.show();
        })
        .catch(() => Swal.fire('Error','Failed to load user data.','error'));
    }

    /* Delete */
    if (btn.classList.contains('deleteBtn')) {
      Swal.fire({
        title:'Are you sure?', text:'This action cannot be undone!',
        icon:'warning', showCancelButton:true,
        confirmButtonColor:'#d33', confirmButtonText:'Yes, delete!', cancelButtonText:'Cancel'
      }).then(res => {
        if (!res.isConfirmed) return;
        const fd = new FormData();
        fd.append('action','delete'); fd.append('id',id);
        fetch('user_action.php',{method:'POST',body:fd})
          .then(r=>r.json())
          .then(resp => {
            if (resp.status==='success') { Swal.fire('Deleted!',resp.msg,'success'); loadUsers(); }
            else Swal.fire('Error',resp.msg,'error');
          })
          .catch(() => Swal.fire('Error','Failed to delete user.','error'));
      });
    }

    /* Toggle status */
    if (btn.classList.contains('toggleBtn')) {
      const newStatus = btn.dataset.status === 'Active' ? 'Inactive' : 'Active';
      const fd = new FormData();
      fd.append('action','toggle_status'); fd.append('id',id); fd.append('status',newStatus);
      fetch('user_action.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(resp => {
          if (resp.status==='success') { Swal.fire('Success',resp.msg,'success'); loadUsers(); }
          else Swal.fire('Error',resp.msg,'error');
        })
        .catch(() => Swal.fire('Error','Failed to update user status.','error'));
    }
  });

  /* ── Init ── */
  loadUsers();
</script>