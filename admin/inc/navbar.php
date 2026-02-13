<?php
// ===========================
// navbar.php - Profile with Edit & Approval System
// ===========================
if (session_status() == PHP_SESSION_NONE) session_start();

$base_url = $base_url ?? '/admin';

// Get current user info
$user_id   = $_SESSION['userdata']['user_id'] ?? 0;
$user_name = $_SESSION['userdata']['full_name'] ?? 'User';
$user_role = $_SESSION['userdata']['role'] ?? 'Member';
$user_email = '';
$user_photo = '';
$user_phone = '';
$user_company = '';

if ($user_id && isset($conn)) {
    $stmt = $conn->prepare("SELECT full_name, role, email, profile_photo, phone, company FROM users WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $user_name = $res['full_name'] ?? $user_name;
        $user_role = $res['role'] ?? $user_role;
        $user_email = $res['email'] ?? '';
        $user_photo = $res['profile_photo'] ?? '';
        $user_phone = $res['phone'] ?? '';
        $user_company = $res['company'] ?? '';
        
        // Update session to keep it in sync (optional but good)
        $_SESSION['userdata']['full_name'] = $user_name;
        $_SESSION['userdata']['role'] = $user_role;
    }
    $stmt->close();
}

// Get initials
$name_parts = array_filter(explode(' ', trim($user_name)));
if (count($name_parts) > 0) {
    $first_initial = substr($name_parts[0], 0, 1);
    $last_initial = isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '';
    $initials = strtoupper($first_initial . $last_initial);
} else {
    $initials = 'U';
}

// Check if Super Admin
$isSuperAdmin = ($user_role === 'Super Admin');
?>

<style>
    :root {
        --brand-primary: #059669;
        --brand-primary-hover: #047857;
        --sidebar-width: 18rem;
    }

    body { padding-top: 0; }

    /* Header */
    .top-header {
        background: #fff;
        height: 4rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        z-index: 1020;
        transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .main-wrap.expanded .top-header { left: 0; }

    @media (max-width: 767px) {
        .top-header { left: 0 !important; }
    }

    /* Clock Pill */
    .pill {
        font-size: 0.75rem;
        font-weight: 700;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 0.75rem;
        padding: 0.5rem 0.75rem;
        color: #495057;
        white-space: nowrap;
        font-family: 'Monaco', 'Courier New', monospace;
    }

    /* Icon Buttons */
    .btn-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e9ecef;
        background: #fff;
        color: #6c757d;
        transition: all 0.3s ease;
        position: relative;
    }

    .btn-icon:hover {
        background: #f8f9fa;
        border-color: var(--brand-primary);
        color: var(--brand-primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Notification Dot */
    .notif-dot {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 999px;
        background: #ef4444;
        border: 2px solid #fff;
        animation: pulse-dot 2s ease-in-out infinite;
    }

    @keyframes pulse-dot {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.2); opacity: 0.8; }
    }

    /* Avatar */
    .avatar {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 999px;
        overflow: hidden;
        border: 2px solid #e9ecef;
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        color: var(--brand-primary);
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* User Dropdown Button */
    .user-dropdown-btn {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 1rem;
        padding: 0.5rem 0.75rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .user-dropdown-btn:hover {
        background: #f8f9fa;
        border-color: var(--brand-primary);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    }

    .user-dropdown-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
    }

    .user-info {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        line-height: 1.2;
    }

    .user-name {
        font-weight: 700;
        color: #1f2937;
        font-size: 0.9rem;
    }

    .user-role-badge {
        text-transform: uppercase;
        font-size: 0.625rem;
        font-weight: 600;
        color: #6b7280;
        letter-spacing: 0.05em;
    }

    /* Dropdown Menu */
    .dropdown-menu {
        border: none;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        border-radius: 1rem;
        padding: 0.5rem;
        min-width: 14rem;
        margin-top: 0.5rem !important;
    }

    .dropdown-item {
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        font-weight: 500;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        cursor: pointer;
    }

    .dropdown-item:hover {
        background: #f3f4f6;
        transform: translateX(4px);
    }

    .dropdown-item i {
        font-size: 1.1rem;
        width: 1.25rem;
    }

    .dropdown-divider {
        margin: 0.5rem 0;
        opacity: 0.1;
    }

    .dropdown-item.text-danger:hover {
        background: #fee2e2;
        color: #dc2626 !important;
    }

    .vr { opacity: 0.2; }

    /* ===========================
       PROFILE MODAL - EDIT MODE
       =========================== */
    
    .profile-modal .modal-dialog {
        max-width: 580px;
    }

    .profile-modal .modal-content {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }

    .profile-modal .modal-header {
        background: #fff;
        border: none;
        padding: 1.25rem 1.5rem 0.75rem;
        position: relative;
    }

    .profile-modal .modal-title {
        font-weight: 700;
        font-size: 1rem;
        color: #1f2937;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .profile-modal .btn-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        opacity: 0.4;
    }

    .profile-modal .btn-close:hover {
        opacity: 1;
    }

    .profile-modal .modal-body {
        padding: 1.25rem 1.5rem;
        background: #fff;
        max-height: 70vh;
        overflow-y: auto;
    }

    .profile-modal .modal-footer {
        border: none;
        padding: 0.875rem 1.5rem 1.25rem;
        background: #fff;
        display: flex;
        gap: 0.75rem;
    }

    /* Photo Upload Section */
    .photo-upload-section {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f3f4f6;
        margin-bottom: 1rem;
    }

    .photo-upload-avatar {
        width: 4rem;
        height: 4rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        color: #6b7280;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        border: 2px solid #e5e7eb;
        overflow: hidden;
        flex-shrink: 0;
        position: relative;
    }

    .photo-upload-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-upload-controls {
        flex: 1;
    }

    .photo-upload-btns {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .btn-photo-action {
        padding: 0.45rem 0.875rem;
        border-radius: 0.375rem;
        font-size: 0.8125rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-upload-photo {
        background: #3b82f6;
        color: #fff;
    }

    .btn-upload-photo:hover {
        background: #2563eb;
    }

    .btn-remove-photo {
        background: transparent;
        color: #6b7280;
        border: 1px solid #e5e7eb;
    }

    .btn-remove-photo:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .photo-upload-hint {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.375rem;
    }

    /* Form Sections */
    .form-section {
        margin-bottom: 1rem;
    }

    .form-section-title {
        font-size: 0.6875rem;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
        padding-bottom: 0.375rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .form-row {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .form-col {
        flex: 1;
    }

    .form-label-modern {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.375rem;
        display: block;
    }

    .form-control-modern {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        color: #1f2937;
        transition: all 0.2s;
        background: #fff;
    }

    .form-control-modern:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-control-modern:disabled {
        background: #f9fafb;
        color: #9ca3af;
        cursor: not-allowed;
    }

    /* Account Details Box */
    .account-details-section {
        background: #f9fafb;
        padding: 0.875rem;
        border-radius: 0.5rem;
        margin-bottom: 0.875rem;
        border: 1px solid #e5e7eb;
    }

    .account-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
    }

    .account-detail-row:not(:last-child) {
        border-bottom: 1px solid #e5e7eb;
    }

    .account-detail-label {
        font-size: 0.8125rem;
        color: #6b7280;
        font-weight: 500;
    }

    .account-detail-value {
        font-size: 0.8125rem;
        color: #1f2937;
        font-weight: 600;
    }

    /* Footer Buttons */
    .btn-modal-action {
        flex: 1;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.9375rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-close-modal {
        background: #fff;
        color: #6b7280;
        border: 1px solid #d1d5db;
    }

    .btn-close-modal:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    .btn-save-changes {
        background: #3b82f6;
        color: #fff;
    }

    .btn-save-changes:hover {
        background: #2563eb;
    }

    .btn-save-changes:disabled {
        background: #93c5fd;
        cursor: not-allowed;
    }

    /* Loading Spinner */
    .upload-spinner {
        display: none;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 10;
    }

    .uploading .upload-spinner {
        display: block;
    }

    .uploading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 999px;
    }

    /* Logout Modal */
    .logout-modal .modal-content {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    }

    .logout-modal .modal-header {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        border: none;
        padding: 1.5rem;
    }

    .logout-modal .modal-title {
        color: #dc2626;
        font-weight: 700;
    }

    @keyframes pulse-logout {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.8; }
    }

    .pulse-icon {
        animation: pulse-logout 1.5s infinite;
    }

    /* Info Alert */
    .info-alert {
        background: #dbeafe;
        border-left: 4px solid #3b82f6;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: start;
        gap: 0.75rem;
    }

    .info-alert i {
        color: #3b82f6;
        font-size: 1rem;
        margin-top: 0.125rem;
    }

    .info-alert-text {
        font-size: 0.8125rem;
        color: #1e40af;
        line-height: 1.5;
        margin: 0;
    }
</style>

<div class="main-wrap">
    <header class="top-header d-flex align-items-center justify-content-between px-3 px-sm-4">
        <div class="d-flex align-items-center gap-2"></div>

        <div class="d-flex align-items-center gap-2 gap-sm-3">
            <span id="real-time-clock" class="pill d-none d-sm-inline">--:--:--</span>

            <div class="dropdown">
                <button class="btn btn-icon position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                    <i class="bi bi-bell"></i>
                    <span class="notif-dot"></span>
                </button>
                
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle text-primary"></i> New loan application</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-check-circle text-success"></i> Payment received</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-exclamation-triangle text-warning"></i> Compliance alert</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center small" href="#">View all notifications</a></li>
                </ul>
            </div>

            <div class="vr d-none d-sm-block"></div>

            <div class="dropdown">
                <button class="user-dropdown-btn" type="button" id="userDropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                    <span class="avatar" id="navbarAvatar">
                        <?php if ($user_photo): ?>
                            <img src="<?= htmlspecialchars($user_photo) ?>" alt="Profile">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </span>
                    <span class="user-info d-none d-md-flex">
                        <span class="user-name" id="navbarUsername"><?= htmlspecialchars($user_name) ?></span>
                        <span class="user-role-badge"><?= htmlspecialchars($user_role) ?></span>
                    </span>
                    <i class="bi bi-chevron-down text-secondary" style="font-size: 0.75rem;"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal" id="btnOpenProfile">
                            <i class="bi bi-person-circle"></i> View Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main class="p-4 p-sm-4" style="padding-top: calc(4rem + 1.5rem) !important;">
        <!-- Your page content -->
    </main>
</div>

<!-- PROFILE EDIT MODAL -->
<div class="modal fade profile-modal" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">My Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- VIEW MODE -->
                <div id="profileViewMode">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <div class="photo-upload-avatar mx-auto mb-3" style="width: 6rem; height: 6rem; font-size: 2.5rem;">
                                <?php if ($user_photo): ?>
                                    <img src="<?= htmlspecialchars($user_photo) ?>" alt="Profile" id="viewProfilePhoto">
                                <?php else: ?>
                                    <span id="viewProfileInitials"><?= $initials ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <h4 class="mb-1" id="viewProfileName"><?= htmlspecialchars($user_name) ?></h4>
                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?= htmlspecialchars($user_role) ?></span>
                    </div>

                    <div class="account-details-section">
                        <div class="account-detail-row">
                            <span class="account-detail-label"><i class="bi bi-envelope me-2"></i>Email Address</span>
                            <span class="account-detail-value" id="viewProfileEmail"><?= htmlspecialchars($user_email) ?></span>
                        </div>
                        <div class="account-detail-row">
                            <span class="account-detail-label"><i class="bi bi-telephone me-2"></i>Phone Number</span>
                            <span class="account-detail-value" id="viewProfilePhone"><?= htmlspecialchars($user_phone ?: 'Not provided') ?></span>
                        </div>
                        <div class="account-detail-row">
                            <span class="account-detail-label"><i class="bi bi-building me-2"></i>Company</span>
                            <span class="account-detail-value"><?= htmlspecialchars($user_company ?: 'System') ?></span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="button" class="btn btn-primary w-100 py-2 fw-bold" id="btnEnterEditMode">
                            <i class="bi bi-pencil-square me-2"></i>Edit Profile
                        </button>
                    </div>
                </div>

                <!-- EDIT MODE (Initially Hidden) -->
                <div id="profileEditMode" style="display: none;">
                    <?php if (!$isSuperAdmin): ?>
                    <div class="info-alert">
                        <i class="bi bi-info-circle"></i>
                        <p class="info-alert-text">
                            <strong>Note:</strong> Changes will be sent to Super Admin for approval before taking effect.
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Photo Upload -->
                    <div class="photo-upload-section">
                        <div class="photo-upload-avatar" id="photoUploadAvatar">
                            <?php if ($user_photo): ?>
                                <img src="<?= htmlspecialchars($user_photo) ?>" alt="Profile" id="photoUploadAvatarImg">
                            <?php else: ?>
                                <span id="photoUploadAvatarInitials"><?= $initials ?></span>
                            <?php endif; ?>
                            <div class="upload-spinner">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Uploading...</span>
                                </div>
                            </div>
                        </div>
                        <div class="photo-upload-controls">
                            <div class="photo-upload-btns">
                                <label for="profilePhotoInput" class="btn-photo-action btn-upload-photo">
                                    Upload photo
                                </label>
                                <button type="button" class="btn-photo-action btn-remove-photo" id="btnRemovePhoto" <?= !$user_photo ? 'style="display:none;"' : '' ?>>
                                    Remove photo
                                </button>
                            </div>
                            <div class="photo-upload-hint">JPG, PNG or WEBP (max. 5MB)</div>
                        </div>
                    </div>
                    <input type="file" class="d-none" id="profilePhotoInput" accept="image/*">

                    <!-- Full Name -->
                    <div class="form-section">
                        <div class="form-section-title">Full Name</div>
                        <div class="form-row">
                            <div class="form-col">
                                <label class="form-label-modern">First</label>
                                <input type="text" class="form-control-modern" id="editFirstName" placeholder="Fernando">
                            </div>
                            <div class="form-col">
                                <label class="form-label-modern">Last</label>
                                <input type="text" class="form-control-modern" id="editLastName" placeholder="Jr.">
                            </div>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-section">
                        <div class="form-section-title">Email</div>
                        <div class="form-row">
                            <div class="form-col">
                                <input type="email" class="form-control-modern" id="editEmail" placeholder="user@example.com" value="<?= htmlspecialchars($user_email) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="form-section">
                        <div class="form-section-title">Phone Number</div>
                        <div class="form-row">
                            <div class="form-col">
                                <input type="tel" class="form-control-modern" id="editPhone" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($user_phone) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Termination Link -->
                    <?php if (!$isSuperAdmin): ?>
                    <div class="mt-4 text-center">
                        <a href="javascript:void(0)" class="text-danger small fw-bold" id="btnRequestTermination">
                            Request Account Deactivation
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <!-- Footer buttons switch based on mode via JS -->
                <div id="footerViewMode" class="w-100">
                    <button type="button" class="btn btn-light border w-100 py-2 fw-semibold" data-bs-dismiss="modal">Close</button>
                </div>
                <div id="footerEditMode" class="w-100" style="display: none; display: flex; gap: 0.75rem;">
                    <button type="button" class="btn btn-light border flex-grow-1" id="btnCancelEdit">Cancel</button>
                    <button type="button" class="btn btn-primary flex-grow-1" id="btnSaveProfileChanges">
                        <?= $isSuperAdmin ? 'Save Changes' : 'Send for Approval' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LOGOUT MODAL -->
<div class="modal fade logout-modal" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirm Logout
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-box-arrow-right display-3 text-danger mb-3 pulse-icon"></i>
                <h5>Are you sure you want to log out?</h5>
                <p class="text-muted mb-0">You will need to sign in again to access your account.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?= $base_url ?>/logout.php" class="btn btn-danger">
                    <i class="bi bi-box-arrow-right me-1"></i>Yes, Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
        const userData = {
            userId: <?= $user_id ?>,
            fullName: '<?= addslashes($user_name) ?>',
            email: '<?= addslashes($user_email) ?>',
            phone: '<?= addslashes($user_phone) ?>',
            company: '<?= addslashes($user_company) ?>',
            photo: '<?= addslashes($user_photo) ?>',
            pendingPhoto: null
        };

        // Parse name
        const nameParts = userData.fullName.split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';

        // Real-time clock
        const clockEl = document.getElementById('real-time-clock');
        function pad(n) { return String(n).padStart(2, '0'); }
        function updateClock() {
            const d = new Date();
            if (clockEl) {
                clockEl.textContent = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
            }
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Mode Toggling Logic
        const profileViewMode = document.getElementById('profileViewMode');
        const profileEditMode = document.getElementById('profileEditMode');
        const footerViewMode = document.getElementById('footerViewMode');
        const footerEditMode = document.getElementById('footerEditMode');
        const profileModalTitle = document.getElementById('profileModalLabel');

        function switchToViewMode() {
            profileViewMode.style.display = 'block';
            profileEditMode.style.display = 'none';
            footerViewMode.style.display = 'block';
            footerEditMode.style.display = 'none';
            profileModalTitle.textContent = 'My Profile';
        }

        function switchToEditMode() {
            profileViewMode.style.display = 'none';
            profileEditMode.style.display = 'block';
            footerViewMode.style.display = 'none';
            footerEditMode.style.display = 'flex';
            profileModalTitle.textContent = 'Edit Profile';
        }

        document.getElementById('btnEnterEditMode').addEventListener('click', switchToEditMode);
        document.getElementById('btnCancelEdit').addEventListener('click', switchToViewMode);

        // Reset to view mode when modal opens or closes
        document.getElementById('profileModal').addEventListener('show.bs.modal', function() {
            switchToViewMode();
            
            // Re-parse current data in case it changed (for edit inputs)
            const currentNameParts = userData.fullName.split(' ');
            document.getElementById('editFirstName').value = currentNameParts[0] || '';
            document.getElementById('editLastName').value = currentNameParts.slice(1).join(' ') || '';
            document.getElementById('editEmail').value = userData.email;
            document.getElementById('editPhone').value = userData.phone;
        });

        // Photo Upload
        const photoInput = document.getElementById('profilePhotoInput');
        const photoUploadAvatar = document.getElementById('photoUploadAvatar');
        const btnRemovePhoto = document.getElementById('btnRemovePhoto');
        
        if (photoInput) {
            photoInput.addEventListener('change', async function(e) {
                const file = e.target.files[0];
                if (!file) return;

                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file');
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size must be less than 5MB');
                    return;
                }

                photoUploadAvatar.classList.add('uploading');

                try {
                    const formData = new FormData();
                    formData.append('profile_photo', file);
                    if (!isSuperAdmin) {
                        formData.append('skip_db', '1');
                    }

                    const response = await fetch('<?= base_url ?>admin/inc/upload_profile_photo.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        const newPhotoUrl = result.photo_url + '?t=' + Date.now();
                        
                        if (!isSuperAdmin) {
                            userData.pendingPhoto = result.photo_url;
                        }

                        const editAvatarImg = document.getElementById('photoUploadAvatarImg');
                        const editAvatarInitials = document.getElementById('photoUploadAvatarInitials');
                        
                        if (editAvatarImg) {
                            editAvatarImg.src = newPhotoUrl;
                        } else if (editAvatarInitials) {
                            editAvatarInitials.outerHTML = `<img src="${newPhotoUrl}" alt="Profile" id="photoUploadAvatarImg">`;
                        }

                        if (isSuperAdmin) {
                            const navbarAvatar = document.getElementById('navbarAvatar');
                            if (navbarAvatar) {
                                navbarAvatar.innerHTML = `<img src="${newPhotoUrl}" alt="Profile">`;
                            }
                            Swal.fire('Success', 'Profile photo updated!', 'success');
                        } else {
                            Swal.fire('Uploaded', 'Photo uploaded. Send profile changes to apply.', 'info');
                        }

                        btnRemovePhoto.style.display = 'inline-block';
                    } else {
                        Swal.fire('Error', result.message || 'Failed to upload photo', 'error');
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    Swal.fire('Error', 'Error uploading photo. Please try again.', 'error');
                } finally {
                    photoUploadAvatar.classList.remove('uploading');
                    photoInput.value = '';
                }
            });
        }

        // Save Profile Changes
        document.getElementById('btnSaveProfileChanges').addEventListener('click', async function() {
            const newFirstName = document.getElementById('editFirstName').value.trim();
            const newLastName = document.getElementById('editLastName').value.trim();
            const newEmail = document.getElementById('editEmail').value.trim();
            const newPhone = document.getElementById('editPhone').value.trim();

            if (!newFirstName || !newLastName || !newEmail) {
                alert('Please fill in all required fields (First Name, Last Name, Email)');
                return;
            }

            const fullName = `${newFirstName} ${newLastName}`;
            this.disabled = true;
            this.textContent = 'Saving...';

            try {
                if (isSuperAdmin) {
                    // Super Admin: Direct update
                    const response = await fetch('<?= base_url ?>admin/inc/update_profile.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            user_id: userData.userId,
                            full_name: fullName,
                            email: newEmail,
                            phone: newPhone
                        })
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        alert('Profile updated successfully!');
                        userData.fullName = fullName;
                        userData.email = newEmail;
                        userData.phone = newPhone;
                        document.getElementById('navbarUsername').textContent = fullName;
                        bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
                        location.reload();
                    } else {
                        alert('Error: ' + (result.msg || 'Failed to update profile'));
                    }
                } else {
                    // Staff/Admin: Send for approval
                    const payload = {
                        full_name: fullName,
                        email: newEmail,
                        phone: newPhone
                    };
                    
                    if (userData.pendingPhoto) {
                        payload.profile_photo = userData.pendingPhoto;
                    }

                    const requestData = JSON.stringify(payload);

                    const fd = new FormData();
                    fd.append('action', 'submit_request');
                    fd.append('user_id', userData.userId);
                    fd.append('request_type', 'profile_update');
                    fd.append('request_data', requestData);

                    const response = await fetch('<?= base_url ?>admin/User-Management-Role-Based-Access/approval_action.php', {
                        method: 'POST',
                        body: fd
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        Swal.fire('Success', 'Profile changes sent to Super Admin for approval!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
                    } else {
                        Swal.fire('Error', result.msg || 'Failed to send approval request', 'error');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving profile. Please try again.');
            } finally {
                this.disabled = false;
                this.textContent = isSuperAdmin ? 'Save Changes' : 'Send for Approval';
            }
        });

        // Request Termination
        if (document.getElementById('btnRequestTermination')) {
            document.getElementById('btnRequestTermination').addEventListener('click', async function() {
                const confirmResult = await Swal.fire({
                    title: 'Deactivate Account?',
                    text: "This will send a request to the administrator to deactivate your account. You will be logged out once approved.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Send Request',
                    confirmButtonColor: '#dc2626'
                });

                if (!confirmResult.isConfirmed) return;

                try {
                    const fd = new FormData();
                    fd.append('action', 'submit_request');
                    fd.append('user_id', userData.userId);
                    fd.append('request_type', 'termination');
                    fd.append('request_data', JSON.stringify({ reason: 'User initiated deactivation' }));

                    const response = await fetch('<?= base_url ?>admin/User-Management-Role-Based-Access/approval_action.php', {
                        method: 'POST',
                        body: fd
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        Swal.fire('Request Sent', result.msg, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
                    } else {
                        Swal.fire('Error', result.msg, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'An unexpected error occurred.', 'error');
                }
            });
        }
    });
</script>