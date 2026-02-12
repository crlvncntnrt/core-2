<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// RBAC: only allow users with compliance_view permission
require_once(__DIR__ . '/../inc/access_control.php');
check_permission('compliance_view');

// Layout
include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<main class="main-content container-fluid py-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-shield-check"></i> Compliance & Audit Trail Viewer</h5>
            <button class="btn btn-light btn-sm" id="btnReload"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="complianceTable" class="table table-striped table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Audit ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>IP Address</th>
                            <th>Remarks</th>
                            <th>Compliance Status</th>
                            <th>Review Date</th>
                        </tr>
                    </thead>
                    <tbody id="complianceBody">
                        <!-- Filled by AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadComplianceData();

        document.getElementById('btnReload').addEventListener('click', loadComplianceData);

        async function loadComplianceData() {
            try {
                const res = await fetch('ajax_compliance_viewer.php');
                const data = await res.json();
                const tbody = document.getElementById('complianceBody');
                tbody.innerHTML = '';

                if (!data.length) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No records found.</td></tr>';
                    return;
                }

                for (const row of data) {
                    tbody.innerHTML += `
          <tr>
            <td>${row.audit_id}</td>
            <td>${row.full_name || 'System'}</td>
            <td>${row.action_type}</td>
            <td>${row.module_name}</td>
            <td>${row.ip_address}</td>
            <td>${row.remarks || ''}</td>
            <td>${row.compliance_status || 'Pending'}</td>
            <td>${row.review_date || '-'}</td>
          </tr>`;
                }
            } catch (err) {
                console.error(err);
                alert('Error loading compliance data.');
            }
        }
    });
</script>