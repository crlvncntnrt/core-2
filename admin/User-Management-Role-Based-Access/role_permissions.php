<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once(__DIR__ . '/../inc/check_auth.php');

// ✅ Use centralized access control (shows RED modal with detailed message)
checkPermission('role_permissions');

// ==========================
// Fetch user's role from DB
// ==========================
$user_id = $_SESSION['userdata']['user_id'] ?? 0;
$stmt = $conn->prepare("
    SELECT u.role, ur.role_name, ur.role_id
    FROM users u
    LEFT JOIN user_roles ur ON u.role_id = ur.role_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch all roles for dropdown
$roles_query = "SELECT role_id, role_name FROM user_roles ORDER BY role_name";
$roles_result = $conn->query($roles_query);
$roles = [];
while ($role = $roles_result->fetch_assoc()) {
    $roles[] = $role;
}

// Fetch all distinct modules for suggestions
$modules_query = "SELECT DISTINCT module_name FROM role_permissions ORDER BY module_name";
$modules_result = $conn->query($modules_query);
$modules = [];
while ($module = $modules_result->fetch_assoc()) {
    $modules[] = $module['module_name'];
}

// Include your layout files
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
        cursor: default;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        background: var(--card-color-1);
        box-shadow: var(--shadow-md);
        min-height: 150px;
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
        font-size: 0.875rem;
        opacity: 0.95;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Card color schemes */
    .stat-card.blue {
        --card-color-1: #1976d2;
    }

    .stat-card.green {
        --card-color-1: #4caf50;
    }

    .stat-card.orange {
        --card-color-1: #f57c00;
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

    .btn-outline-light {
        border: 2px solid rgba(255, 255, 255, 0.5);
        color: white;
    }

    .btn-outline-light:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: white;
        color: white;
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

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    .modal-footer {
        border-top: 2px solid #f3f4f6;
    }

    .form-check-input:checked {
        background-color: var(--brand-primary);
        border-color: var(--brand-primary);
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
            font-size: 2rem;
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
                        <h4><i class="bi bi-shield-lock me-2"></i>Role Permissions Management</h4>
                        <p class="subtitle mb-0">Configure and manage role-based access control permissions</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-light" id="addPermissionBtn">
                            <i class="bi bi-plus-circle"></i> Add Permission
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card blue">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <div class="stat-title">Total Permissions</div>
                            <div id="totalPermCount" class="stat-value">0</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card green">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-grid"></i>
                            </div>
                            <div class="stat-title">Modules</div>
                            <div id="moduleCount" class="stat-value">0</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card orange">
                        <div class="stat-card-content">
                            <div class="stat-card-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="stat-title">Roles</div>
                            <div id="roleCount" class="stat-value">0</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filter Section -->
            <div class="filter-section">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Filter by Role</label>
                        <select class="form-select" id="filterRole">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Filter by Module</label>
                        <select class="form-select" id="filterModule">
                            <option value="">All Modules</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?= htmlspecialchars($module) ?>"><?= htmlspecialchars($module) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-secondary w-100" id="clearFilter">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Permissions Table -->
            <div class="table-card">
                <div class="table-header">
                    <h6 class="table-title">
                        <i class="bi bi-table"></i>
                        <span>Permissions Configuration</span>
                    </h6>
                </div>

                <div class="table-wrapper">
                    <table class="table table-hover text-center" id="permTable">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Module</th>
                                <th>View</th>
                                <th>Add</th>
                                <th>Edit</th>
                                <th>Delete</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal -->
<div class="modal fade" id="permModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="permModalLabel"><i class="bi bi-plus-circle me-2"></i>Add Permission</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="permForm">
                <input type="hidden" id="permission_id" name="permission_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="module" class="form-label">Module <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="module" name="module" 
                                   placeholder="e.g., User Management" list="moduleList" required>
                            <datalist id="moduleList">
                                <option value="User Management">
                                <option value="Loan Portfolio">
                                <option value="Savings Monitoring">
                                <option value="Disbursement Tracker">
                                <option value="Repayment Tracker">
                                <option value="Compliance & Audit Trail">
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?= htmlspecialchars($module) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="fw-bold mb-3 d-block">Permissions</label>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="can_view" id="can_view">
                                    <label class="form-check-label" for="can_view">
                                        <i class="bi bi-eye"></i> View
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="can_add" id="can_add">
                                    <label class="form-check-label" for="can_add">
                                        <i class="bi bi-plus-circle"></i> Add
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="can_edit" id="can_edit">
                                    <label class="form-check-label" for="can_edit">
                                        <i class="bi bi-pencil"></i> Edit
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="can_delete" id="can_delete">
                                    <label class="form-check-label" for="can_delete">
                                        <i class="bi bi-trash"></i> Delete
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Save
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('#permTable tbody');
    const totalPermCount = document.getElementById('totalPermCount');
    const roleCount = document.getElementById('roleCount');
    const moduleCount = document.getElementById('moduleCount');
    const filterRole = document.getElementById('filterRole');
    const filterModule = document.getElementById('filterModule');
    const clearFilterBtn = document.getElementById('clearFilter');

    let allPermissions = [];

    function loadPermissions() {
      fetch('role_permissions_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'list'
          })
        })
        .then(r => {
          if (!r.ok) throw new Error('Network response was not ok');
          return r.json();
        })
        .then(data => {
          allPermissions = data;
          displayPermissions(data);
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'Failed to load permissions', 'error');
          tbody.innerHTML = '<tr><td colspan="7" class="text-danger"><i class="bi bi-exclamation-triangle"></i> Error loading permissions</td></tr>';
        });
    }

    function displayPermissions(data) {
      tbody.innerHTML = '';
      
      if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-muted"><i class="bi bi-inbox"></i> No permissions defined yet</td></tr>';
        totalPermCount.textContent = '0';
        roleCount.textContent = '0';
        moduleCount.textContent = '0';
      } else {
        let roles = new Set(),
          modules = new Set();
        
        data.forEach(p => {
          roles.add(p.role_name || p.role);
          modules.add(p.module_name);
          
          tbody.innerHTML += `
            <tr>
              <td><span class="badge bg-secondary">${escapeHtml(p.role_name || p.role)}</span></td>
              <td><strong>${escapeHtml(p.module_name)}</strong></td>
              <td>${p.can_view == 1 ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
              <td>${p.can_add == 1 ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
              <td>${p.can_edit == 1 ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
              <td>${p.can_delete == 1 ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
              <td>
                <button class="btn btn-sm btn-info editBtn" data-id="${p.perm_id}" title="Edit">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <button class="btn btn-sm btn-danger delBtn" data-id="${p.perm_id}" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>`;
        });
        
        totalPermCount.textContent = data.length;
        roleCount.textContent = roles.size;
        moduleCount.textContent = modules.size;
      }
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function filterPermissions() {
      const roleFilter = filterRole.value;
      const moduleFilter = filterModule.value.toLowerCase();

      const filtered = allPermissions.filter(p => {
        const matchRole = !roleFilter || p.role_id == roleFilter;
        const matchModule = !moduleFilter || p.module_name.toLowerCase().includes(moduleFilter);
        return matchRole && matchModule;
      });

      displayPermissions(filtered);
    }

    // Event Listeners
    filterRole.addEventListener('change', filterPermissions);
    filterModule.addEventListener('change', filterPermissions);
    
    clearFilterBtn.addEventListener('click', () => {
      filterRole.value = '';
      filterModule.value = '';
      displayPermissions(allPermissions);
    });

    loadPermissions();

    document.getElementById('addPermissionBtn').addEventListener('click', () => {
      document.getElementById('permForm').reset();
      document.getElementById('permission_id').value = '';
      document.getElementById('permModalLabel').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add Permission';
      new bootstrap.Modal(document.getElementById('permModal')).show();
    });

    tbody.addEventListener('click', e => {
      if (e.target.closest('.editBtn')) {
        const id = e.target.closest('.editBtn').dataset.id;
        fetch('role_permissions_action.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
              action: 'get',
              id
            })
          })
          .then(r => r.json())
          .then(p => {
            document.getElementById('permission_id').value = p.perm_id;
            document.getElementById('role_id').value = p.role_id;
            document.getElementById('module').value = p.module_name;
            document.getElementById('can_view').checked = p.can_view == 1;
            document.getElementById('can_add').checked = p.can_add == 1;
            document.getElementById('can_edit').checked = p.can_edit == 1;
            document.getElementById('can_delete').checked = p.can_delete == 1;
            document.getElementById('permModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Permission';
            new bootstrap.Modal(document.getElementById('permModal')).show();
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Failed to load permission details', 'error');
          });
      }

      if (e.target.closest('.delBtn')) {
        const id = e.target.closest('.delBtn').dataset.id;
        
        Swal.fire({
          title: 'Delete Permission?',
          text: "This action cannot be undone!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
          if (result.isConfirmed) {
            fetch('role_permissions_action.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                  action: 'delete',
                  id
                })
              })
              .then(r => r.json())
              .then(response => {
                if (response.success) {
                  Swal.fire('Deleted!', 'Permission has been deleted.', 'success');
                  loadPermissions();
                } else {
                  Swal.fire('Error', response.message || 'Failed to delete permission', 'error');
                }
              })
              .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to delete permission', 'error');
              });
          }
        });
      }
    });

    document.getElementById('permForm').addEventListener('submit', e => {
      e.preventDefault();
      const formData = new FormData(e.target);
      const permId = document.getElementById('permission_id').value;
      formData.append('action', permId ? 'edit' : 'add');
      
      // Convert checkboxes to 1/0
      formData.set('can_view', document.getElementById('can_view').checked ? 1 : 0);
      formData.set('can_add', document.getElementById('can_add').checked ? 1 : 0);
      formData.set('can_edit', document.getElementById('can_edit').checked ? 1 : 0);
      formData.set('can_delete', document.getElementById('can_delete').checked ? 1 : 0);

      fetch('role_permissions_action.php', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(response => {
          if (response.success) {
            Swal.fire('Success', response.message || 'Permission saved successfully', 'success');
            loadPermissions();
            bootstrap.Modal.getInstance(document.getElementById('permModal')).hide();
          } else {
            Swal.fire('Error', response.message || 'Failed to save permission', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'Failed to save permission', 'error');
        });
    });
  });
</script>