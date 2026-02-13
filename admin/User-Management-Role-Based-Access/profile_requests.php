<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

// Only Super Admin and Admin can access this page
if (!in_array($_SESSION['userdata']['role'], ['Super Admin', 'Admin'])) {
    redirect('admin/dashboard.php');
}

include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<style>
    :root {
        --brand-primary: #059669;
        --brand-warning: #f59e0b;
        --brand-danger: #ef4444;
        --brand-info: #3b82f6;
    }

    .page-header {
        background: linear-gradient(135deg, var(--brand-primary) 0%, #047857 100%);
        padding: 2rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
    }

    .request-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
        margin-bottom: 1.5rem;
        overflow: hidden;
        transition: transform 0.2s;
    }

    .request-card:hover {
        transform: translateY(-2px);
    }

    .request-header {
        padding: 1rem 1.5rem;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .request-body {
        padding: 1.5rem;
    }

    .comparison-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    .data-box {
        background: #f3f4f6;
        padding: 1rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
    }

    .data-box.new-data {
        background: #ecfdf5;
        border-color: #10b981;
    }

    .data-box-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        font-weight: 700;
        color: #6b7280;
        margin-bottom: 0.75rem;
    }

    .data-item {
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }

    .data-label {
        color: #6b7280;
        font-weight: 500;
        width: 100px;
        display: inline-block;
    }

    .data-value {
        color: #111827;
        font-weight: 600;
    }

    .diff-highlight {
        background: #fef08a;
        padding: 0 2px;
        border-radius: 2px;
    }

    .request-footer {
        padding: 1rem 1.5rem;
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e5e7eb;
    }
</style>

<div class="main-wrap">
    <main class="main-content" id="main-content">
        <div class="container-fluid py-4">
            <div class="page-header">
                <h4><i class="bi bi-shield-check me-2"></i>Approval Requests</h4>
                <p class="subtitle mb-0">Review and action profile update and account requests</p>
            </div>

            <div id="requestsContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading requests...</p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectRequestId">
                <div class="mb-3">
                    <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                    <textarea id="rejectReason" class="form-control" rows="4" placeholder="Please explain why this request is being rejected..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRejectBtn">Confirm Reject</button>
            </div>
        </div>
    </div>
</div>

<?php include(__DIR__ . '/../inc/footer.php'); ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadRequests();

        async function loadRequests() {
            const container = document.getElementById('requestsContainer');
            try {
                const response = await fetch('approval_action.php?action=get_pending');
                const result = await response.json();

                if (result.status === 'success') {
                    renderRequests(result.requests);
                } else {
                    container.innerHTML = `<div class="alert alert-danger">${result.msg}</div>`;
                }
            } catch (error) {
                container.innerHTML = `<div class="alert alert-danger">Error loading requests.</div>`;
            }
        }

        function renderRequests(requests) {
            const container = document.getElementById('requestsContainer');
            if (requests.length === 0) {
                container.innerHTML = `
                    <div class="card p-5 text-center shadow-sm">
                        <i class="bi bi-check-circle display-1 text-success mb-3"></i>
                        <h5>No Pending Requests</h5>
                        <p class="text-muted">All approval requests have been processed.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = requests.map(req => {
                const isTermination = req.request_type === 'termination';
                const typeBadge = isTermination ? 'bg-danger' : 'bg-info';
                const typeText = isTermination ? 'Account Termination' : 'Profile Update';

                let bodyContent = '';
                if (isTermination) {
                    bodyContent = `
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            User is requesting to <strong>Deactivate</strong> their account.
                        </div>
                    `;
                } else {
                    const current = req.current_data_parsed || {};
                    const requested = req.request_data_parsed || {};
                    
                    const fields = [
                        { label: 'Full Name', key: 'full_name' },
                        { label: 'Email', key: 'email' },
                        { label: 'Phone', key: 'phone' }
                    ];

                    bodyContent = `
                        <div class="comparison-grid">
                            <div class="data-box">
                                <div class="data-box-title">Current Info</div>
                                ${fields.map(f => `
                                    <div class="data-item">
                                        <span class="data-label">${f.label}:</span>
                                        <span class="data-value">${current[f.key] || '—'}</span>
                                    </div>
                                `).join('')}
                            </div>
                            <div class="data-box new-data">
                                <div class="data-box-title">Requested Changes</div>
                                ${fields.map(f => {
                                    const isDiff = requested[f.key] && requested[f.key] !== current[f.key];
                                    return `
                                        <div class="data-item">
                                            <span class="data-label">${f.label}:</span>
                                            <span class="data-value ${isDiff ? 'diff-highlight' : ''}">${requested[f.key] || '—'}</span>
                                        </div>
                                    `;
                                }).join('')}
                                
                                ${requested.profile_photo ? `
                                    <div class="data-item mt-3">
                                        <span class="data-label">New Photo:</span>
                                        <img src="${requested.profile_photo}" class="avatar-circle ms-2" alt="New Profile">
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }

                return `
                    <div class="request-card" id="request_${req.request_id}">
                        <div class="request-header">
                            <div>
                                <span class="badge ${typeBadge} me-2">${typeText}</span>
                                <span class="text-muted small">ID: #${req.request_id} • Requested by <strong>${req.requested_by_name}</strong> on ${req.created_at}</span>
                            </div>
                            <div class="text-primary fw-bold">${req.full_name} (${req.username})</div>
                        </div>
                        <div class="request-body">
                            ${bodyContent}
                        </div>
                        <div class="request-footer">
                            <button class="btn btn-outline-danger btn-sm reject-btn" data-id="${req.request_id}">
                                <i class="bi bi-x-circle me-1"></i>Reject
                            </button>
                            <button class="btn btn-success btn-sm approve-btn" data-id="${req.request_id}">
                                <i class="bi bi-check-circle me-1"></i>Approve Request
                            </button>
                        </div>
                    </div>
                `;
            }).join('');

            // Attach listeners
            document.querySelectorAll('.approve-btn').forEach(btn => {
                btn.addEventListener('click', () => handleApprove(btn.dataset.id));
            });

            document.querySelectorAll('.reject-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('rejectRequestId').value = btn.dataset.id;
                    document.getElementById('rejectReason').value = '';
                    new bootstrap.Modal(document.getElementById('rejectModal')).show();
                });
            });
        }

        async function handleApprove(requestId) {
            const btn = document.querySelector(`.approve-btn[data-id="${requestId}"]`);
            const originalText = btn.innerHTML;
            
            const confirmResult = await Swal.fire({
                title: 'Approve Request?',
                text: "The changes will be applied immediately.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Approve',
                confirmButtonColor: '#059669'
            });

            if (!confirmResult.isConfirmed) return;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

            try {
                const fd = new FormData();
                fd.append('action', 'approve');
                fd.append('request_id', requestId);
                fd.append('review_notes', 'Approved by Admin');

                const response = await fetch('approval_action.php', { method: 'POST', body: fd });
                const result = await response.json();

                if (result.status === 'success') {
                    Swal.fire('Approved!', result.msg, 'success');
                    document.getElementById(`request_${requestId}`).remove();
                    if (document.getElementById('requestsContainer').children.length === 0) {
                        loadRequests();
                    }
                } else {
                    Swal.fire('Error', result.msg, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'An unexpected error occurred.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        document.getElementById('confirmRejectBtn').addEventListener('click', async function() {
            const requestId = document.getElementById('rejectRequestId').value;
            const reason = document.getElementById('rejectReason').value.trim();

            if (!reason) {
                alert('Successive rejection requires a reason.');
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Rejecting...';

            try {
                const fd = new FormData();
                fd.append('action', 'reject');
                fd.append('request_id', requestId);
                fd.append('review_notes', reason);

                const response = await fetch('approval_action.php', { method: 'POST', body: fd });
                const result = await response.json();

                if (result.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
                    Swal.fire('Rejected', 'The request has been rejected and the user notified.', 'success');
                    document.getElementById(`request_${requestId}`).remove();
                    if (document.getElementById('requestsContainer').children.length === 0) {
                        loadRequests();
                    }
                } else {
                    Swal.fire('Error', result.msg, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'An unexpected error occurred.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Confirm Reject';
            }
        });
    });
</script>
