<?php
// ===========================
// navbar.php - Simplified Profile Modal (Photo Upload Only)
// ===========================
if (session_status() == PHP_SESSION_NONE) session_start();

// Base URL (should match your project structure)
$base_url = $base_url ?? '/admin';

// Get current user info
$user_id   = $_SESSION['userdata']['user_id'] ?? 0;
$user_name = $_SESSION['userdata']['full_name'] ?? 'User';
$user_role = $_SESSION['userdata']['role'] ?? 'Member';
$user_email = '';
$user_photo = '';

if ($user_id && isset($conn)) {
    $stmt = $conn->prepare("SELECT email, profile_photo FROM users WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $user_email = $res['email'] ?? '';
    $user_photo = $res['profile_photo'] ?? '';
    $stmt->close();
}

// Get initials for avatar with better error handling
$name_parts = array_filter(explode(' ', trim($user_name)));
if (count($name_parts) > 0) {
    $first_initial = substr($name_parts[0], 0, 1);
    $last_initial = isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '';
    $initials = strtoupper($first_initial . $last_initial);
} else {
    $initials = 'U'; // Default for User
}
?>

<!-- NAVBAR STYLES -->
<style>
    :root {
        --brand-primary: #059669;
        --brand-primary-hover: #047857;
        --sidebar-width: 18rem;
    }

    body {
        padding-top: 0;
    }

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

    .main-wrap.expanded .top-header {
        left: 0;
    }

    @media (max-width: 767px) {
        .top-header {
            left: 0 !important;
        }
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
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.2);
            opacity: 0.8;
        }
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

    /* Responsive Vertical Divider */
    .vr {
        opacity: 0.2;
    }

    /* ✅ PROFILE MODAL - Photo Upload Only */
    .profile-modal .modal-dialog {
        max-width: 450px;
    }

    .profile-modal .modal-content {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }

    .profile-modal .modal-header {
        background: linear-gradient(135deg, var(--brand-primary), #047857);
        color: white;
        border: none;
        padding: 1.5rem 2rem;
    }

    .profile-modal .modal-title {
        font-weight: 700;
        font-size: 1.125rem;
    }

    .profile-modal .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }

    .profile-modal .modal-body {
        padding: 2rem;
    }

    /* Profile Avatar Section */
    .profile-avatar-section {
        text-align: center;
        padding: 1.5rem 0;
        margin-bottom: 1.5rem;
    }

    .profile-avatar-large {
        width: 8rem;
        height: 8rem;
        border-radius: 999px;
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        color: var(--brand-primary);
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        border: 4px solid var(--brand-primary);
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    }

    .profile-avatar-large img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .upload-photo-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        opacity: 0;
    }

    .profile-avatar-large:hover .upload-photo-overlay {
        opacity: 1;
    }

    .profile-name {
        font-weight: 700;
        font-size: 1.5rem;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }

    .profile-role {
        text-transform: uppercase;
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        letter-spacing: 0.05em;
        background: #f3f4f6;
        padding: 0.25rem 0.75rem;
        border-radius: 0.5rem;
        display: inline-block;
    }

    /* Account Details */
    .account-detail-item {
        background: #f9fafb;
        padding: 1rem 1.25rem;
        border-radius: 0.75rem;
        margin-bottom: 0.75rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .account-detail-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #6b7280;
    }

    .account-detail-value {
        font-size: 0.875rem;
        font-weight: 600;
        color: #1f2937;
    }

    .profile-modal .modal-footer {
        border-top: 2px solid #f3f4f6;
        padding: 1.5rem 2rem;
        background: #f9fafb;
    }

    /* Info Notice */
    .info-notice {
        background: #dbeafe;
        border-left: 4px solid #3b82f6;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-top: 1.5rem;
    }

    .info-notice i {
        color: #3b82f6;
        font-size: 1.25rem;
    }

    .info-notice-text {
        font-size: 0.875rem;
        color: #1e40af;
        margin: 0;
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
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
    }

    .pulse-icon {
        animation: pulse-logout 1.5s infinite;
    }

    /* Loading Spinner */
    .upload-spinner {
        display: none;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    .uploading .upload-spinner {
        display: block;
    }

    .uploading .profile-avatar-large::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        border-radius: 999px;
    }
</style>

<!-- ✅ MAIN WRAPPER -->
<div class="main-wrap">
    <!-- ✅ HEADER -->
    <header class="top-header d-flex align-items-center justify-content-between px-3 px-sm-4">
        <div class="d-flex align-items-center gap-2">
            <!-- Empty space for alignment -->
        </div>

        <div class="d-flex align-items-center gap-2 gap-sm-3">
            <!-- Real-time Clock -->
            <span id="real-time-clock" class="pill d-none d-sm-inline">--:--:--</span>

            <!-- Notification Bell -->
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

            <!-- User Dropdown -->
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
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="bi bi-person-circle"></i> My Profile
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

    <!-- ✅ CONTENT AREA -->
    <main class="p-4 p-sm-4" style="padding-top: calc(4rem + 1.5rem) !important;">
        <!-- Your page content goes here -->
    </main>
</div>

<!-- ✅ PROFILE MODAL - Photo Upload Only -->
<div class="modal fade profile-modal" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="bi bi-person-circle me-2"></i>My Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Profile Avatar Section -->
                <div class="profile-avatar-section">
                    <div class="profile-avatar-large" id="profileAvatarContainer">
                        <?php if ($user_photo): ?>
                            <img src="<?= htmlspecialchars($user_photo) ?>" alt="Profile" id="profileAvatarImg">
                        <?php else: ?>
                            <span id="profileAvatarInitials"><?= $initials ?></span>
                        <?php endif; ?>
                        <label class="upload-photo-overlay" for="profilePhotoInput">
                            <i class="bi bi-camera-fill me-1"></i>
                            <small>Change Photo</small>
                        </label>
                        <div class="upload-spinner">
                            <div class="spinner-border text-white" role="status">
                                <span class="visually-hidden">Uploading...</span>
                            </div>
                        </div>
                    </div>
                    <input type="file" class="d-none" id="profilePhotoInput" accept="image/*">
                    
                    <div class="profile-name"><?= htmlspecialchars($user_name) ?></div>
                    <span class="profile-role"><?= htmlspecialchars($user_role) ?></span>
                </div>

                <!-- Account Details -->
                <div class="account-detail-item">
                    <span class="account-detail-label">Email</span>
                    <span class="account-detail-value"><?= htmlspecialchars($user_email) ?></span>
                </div>
                
                <div class="account-detail-item">
                    <span class="account-detail-label">User ID</span>
                    <span class="account-detail-value">#<?= $user_id ?></span>
                </div>

                <div class="account-detail-item">
                    <span class="account-detail-label">Time Zone</span>
                    <span class="account-detail-value">UTC+08:00 (PH)</span>
                </div>

                <!-- Info Notice -->
                <div class="info-notice d-flex gap-3">
                    <i class="bi bi-info-circle"></i>
                    <p class="info-notice-text">
                        <strong>Note:</strong> To update your name, email, or password, please contact the Super Administrator through the User Management section.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ✅ LOGOUT MODAL -->
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

<!-- ✅ SCRIPTS -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Real-time clock
        const clockEl = document.getElementById('real-time-clock');
        
        function pad(n) { 
            return String(n).padStart(2, '0'); 
        }
        
        function updateClock() {
            const d = new Date();
            const hours = pad(d.getHours());
            const minutes = pad(d.getMinutes());
            const seconds = pad(d.getSeconds());
            
            if (clockEl) {
                clockEl.textContent = `${hours}:${minutes}:${seconds}`;
            }
        }
        
        updateClock();
        setInterval(updateClock, 1000);

        // Close dropdowns when modals open
        const profileModal = document.getElementById('profileModal');
        const logoutModal = document.getElementById('logoutModal');
        
        [profileModal, logoutModal].forEach(modalEl => {
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function() {
                    const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
                    openDropdowns.forEach(dropdown => {
                        const bsDropdown = bootstrap.Dropdown.getInstance(dropdown.previousElementSibling);
                        if (bsDropdown) {
                            bsDropdown.hide();
                        }
                    });
                });
            }
        });

        // ✅ Profile Photo Upload Handler
        const photoInput = document.getElementById('profilePhotoInput');
        const avatarContainer = document.getElementById('profileAvatarContainer');
        
        if (photoInput) {
            photoInput.addEventListener('change', async function(e) {
                const file = e.target.files[0];
                if (!file) return;

                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file');
                    return;
                }

                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size must be less than 5MB');
                    return;
                }

                // Show loading
                avatarContainer.classList.add('uploading');

                try {
                    const formData = new FormData();
                    formData.append('profile_photo', file);

                    const response = await fetch('<?= $base_url ?>/inc/upload_profile_photo.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Update avatar display
                        const avatarImg = document.getElementById('profileAvatarImg');
                        const avatarInitials = document.getElementById('profileAvatarInitials');
                        
                        if (avatarImg) {
                            avatarImg.src = result.photo_url + '?t=' + Date.now();
                        } else if (avatarInitials) {
                            // Replace initials with image
                            avatarInitials.outerHTML = `<img src="${result.photo_url}?t=${Date.now()}" alt="Profile" id="profileAvatarImg">`;
                        }

                        // Update navbar avatar
                        const navbarAvatar = document.getElementById('navbarAvatar');
                        if (navbarAvatar) {
                            navbarAvatar.innerHTML = `<img src="${result.photo_url}?t=${Date.now()}" alt="Profile">`;
                        }

                        alert('Profile photo updated successfully!');
                    } else {
                        alert('Error: ' + (result.message || 'Failed to upload photo'));
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    alert('Error uploading photo. Please try again.');
                } finally {
                    // Hide loading
                    avatarContainer.classList.remove('uploading');
                    photoInput.value = ''; // Clear input
                }
            });
        }
    });
</script>