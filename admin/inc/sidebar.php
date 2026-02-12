<!-- Enhanced Sidebar with Bootstrap Design -->
<style>
    :root {
        --brand-primary: #059669;
        --brand-primary-hover: #047857;
        --brand-background-main: #F0FDF4;
        --brand-border: #D1FAE5;
        --brand-text-primary: #1F2937;
        --brand-text-secondary: #4B5563;
        --sidebar-width: 18rem;
    }

    body {
        background: var(--brand-background-main);
        min-height: 100vh;
        color: var(--brand-text-primary);
        margin: 0;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Desktop fixed sidebar */
    @media (min-width: 768px) {
        .sidebar-desktop {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            z-index: 1030;
            border-right: 1px solid #f1f3f5;
            background: #fff;
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-desktop.collapsed {
            left: calc(-1 * var(--sidebar-width));
        }

        .main-wrap {
            padding-left: var(--sidebar-width);
            transition: padding-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-wrap.expanded {
            padding-left: 0;
        }
    }

    /* Brand Section */
    .brand-link {
        transition: all 0.3s ease;
    }

    .brand-link:hover {
        transform: translateY(-2px);
    }

    .brand-link:hover .brand-title,
    .brand-link:hover .brand-subtitle {
        color: var(--brand-primary) !important;
    }

    .brand-link img {
        transition: transform 0.3s ease;
    }

    .brand-link:hover img {
        transform: scale(1.05);
    }

    .brand-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--brand-text-primary);
        transition: color 0.3s ease;
    }

    .brand-subtitle {
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        color: var(--brand-text-secondary);
        transition: color 0.3s ease;
    }

    /* Menu Heading */
    .menu-heading {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        color: #9aa0a6;
        padding: 0 0.5rem;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        text-transform: uppercase;
    }

    .menu-heading:first-of-type {
        margin-top: 0.5rem;
    }

    /* Menu Buttons */
    .menu-btn {
        border-radius: 1rem;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        background: transparent;
        position: relative;
        overflow: hidden;
    }

    .menu-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 0;
        height: 100%;
        background: #ecfdf5;
        transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: -1;
    }

    .menu-btn:hover {
        transform: translateX(4px);
        color: var(--brand-primary);
    }

    .menu-btn:hover::before {
        width: 100%;
    }

    .menu-btn:active {
        transform: translateX(0) scale(0.99);
    }

    .menu-btn .icon-box {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #ecfdf5;
        font-size: 1.1rem;
        transition: all 0.3s ease;
    }

    .menu-btn:hover .icon-box {
        transform: scale(1.1) rotate(5deg);
    }

    /* Active Menu State */
    .menu-active {
        background: var(--brand-primary) !important;
        color: #fff !important;
        box-shadow: 0 0.5rem 1rem rgba(5, 150, 105, 0.25);
        transform: none !important;
    }

    .menu-active::before {
        display: none;
    }

    .menu-active .icon-box {
        background: rgba(255, 255, 255, 0.15);
    }

    .menu-active:hover {
        transform: none !important;
        color: #fff !important;
    }

    /* Submenu Styles */
    .submenu-container {
        margin-top: 0.25rem;
        padding-left: 0;
        overflow: hidden;
    }

    .submenu-items {
        margin-left: 2.5rem;
        padding-left: 1rem;
        border-left: 2px solid #e5e7eb;
    }

    .submenu-link {
        display: block;
        text-decoration: none;
        color: var(--brand-text-secondary);
        font-size: 0.9rem;
        font-weight: 500;
        padding: 0.625rem 1rem;
        border-radius: 0.75rem;
        transition: all 0.3s ease;
    }

    .submenu-link:hover {
        background: #ecfdf5;
        color: var(--brand-primary);
        transform: translateX(4px);
    }

    .submenu-link.active {
        background: #d1fae5;
        color: var(--brand-primary);
        font-weight: 600;
    }

    /* Collapse Arrow */
    .collapse-arrow {
        transition: transform 0.3s ease;
        color: var(--brand-primary);
        opacity: 0.75;
        font-size: 0.9rem;
    }

    .menu-btn[aria-expanded="true"] .collapse-arrow {
        transform: rotate(180deg);
    }

    /* System Status */
    .system-status {
        margin-top: auto;
        padding: 1rem 0.75rem;
    }

    .status-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--brand-primary);
        margin-bottom: 0.75rem;
    }

    .status-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 999px;
        background: var(--brand-primary);
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.7;
            transform: scale(1.1);
        }
    }

    .system-info {
        font-size: 0.7rem;
        line-height: 1.4;
        color: var(--brand-text-secondary);
    }

    /* Scrollbar */
    .sidebar-desktop::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-desktop::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.02);
    }

    .sidebar-desktop::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 3px;
    }

    .sidebar-desktop::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.15);
    }

    /* Mobile Offcanvas adjustments */
    .offcanvas {
        width: var(--sidebar-width) !important;
    }

    /* Responsive */
    @media (max-width: 767px) {
        .main-wrap {
            padding-left: 0;
        }
    }
</style>

<?php
// Base URL to your project root
$base_url = '/admin';
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['REQUEST_URI'];

// Function to check if a path is active
function is_active($path, $current_path)
{
    return strpos($current_path, $path) !== false;
}

// Function to get active class
function get_active_class($path, $current_path)
{
    return is_active($path, $current_path) ? 'menu-active' : '';
}

// ✅ DUAL LOGO SUPPORT: Different logos for different purposes
// Sidebar/Dashboard logo (Golden.png)
$sidebar_logo = 'dist/img/Golden.png';
// Login page logo (logo.jpg) - stored separately
$login_logo = 'dist/img/logo.jpg';

$system_name = 'Microfinance EIS';
$system_tagline = 'Integrated Loan, Savings, and Collection Monitoring';

try {
    // Include database connection
    if (file_exists(__DIR__ . '/../classes/DBConnection.php')) {
        require_once __DIR__ . '/../classes/DBConnection.php';
        $db = new DBConnection();
        $conn = $db->conn;

        // Get system info from database including BOTH logos
        $stmt = $conn->prepare("SELECT meta_field, meta_value FROM system_info WHERE meta_field IN ('logo', 'login_logo', 'system_name', 'system_tagline')");
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            switch ($row['meta_field']) {
                case 'logo':
                    // This is the sidebar/dashboard logo
                    $sidebar_logo = $row['meta_value'];
                    break;
                case 'login_logo':
                    // This is the login page logo
                    $login_logo = $row['meta_value'];
                    break;
                case 'system_name':
                    $system_name = $row['meta_value'];
                    break;
                case 'system_tagline':
                    $system_tagline = $row['meta_value'];
                    break;
            }
        }

        $stmt->close();
    }
} catch (Exception $e) {
    // Use defaults if database connection fails
    error_log("Sidebar: Could not load system info - " . $e->getMessage());
}

// ✅ FIXED: Build logo paths using absolute paths from web root
// Remove any leading slashes from database paths
$sidebar_logo = ltrim($sidebar_logo, '/');
$login_logo = ltrim($login_logo, '/');

// Build the full paths - use root-relative paths (works from any subdirectory)
if (!str_starts_with($sidebar_logo, 'http')) {
    $sidebar_logo_path = '/' . $sidebar_logo;
} else {
    $sidebar_logo_path = $sidebar_logo;
}

if (!str_starts_with($login_logo, 'http')) {
    $login_logo_path = '/' . $login_logo;
} else {
    $login_logo_path = $login_logo;
}

// Create fallback default logo path (also using absolute path)
$default_logo = '/dist/img/default-logo.png';
?>

<!-- ✅ DESKTOP SIDEBAR -->
<aside class="sidebar-desktop d-none d-md-flex flex-column" id="desktopSidebar">
    <!-- Brand -->
    <div class="border-bottom px-3" style="height:4rem; display:flex; align-items:center;">
        <a href="<?= $base_url ?>/dashboard.php" class="brand-link d-flex align-items-center gap-3 text-decoration-none w-100 rounded-4 px-2 py-2">
            <!-- ✅ SIDEBAR LOGO (Golden.png) with ABSOLUTE PATH -->
            <img src="<?= htmlspecialchars($sidebar_logo_path) ?>"
                alt="<?= htmlspecialchars($system_name) ?> Logo"
                width="40"
                height="40"
                class="flex-shrink-0"
                style="object-fit: contain;"
                onerror="this.onerror=null; this.src='<?= $default_logo ?>';">
            <div class="lh-sm">
                <div class="brand-title"><?= htmlspecialchars($system_name) ?></div>
                <div class="brand-subtitle"><?= htmlspecialchars($system_tagline) ?></div>
            </div>
        </a>
    </div>

    <!-- Menu -->
    <div class="px-3 py-3 overflow-auto flex-grow-1">
        <div class="menu-heading">Main Menu</div>

        <a href="<?= $base_url ?>/dashboard.php"
            class="btn menu-btn <?= $current_page == 'dashboard.php' ? 'menu-active' : '' ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-speedometer2"></i></span>
            <span>Dashboard</span>
        </a>

        <a href="<?= $base_url ?>/Loan-Portfolio-Risk-Management/index.php"
            class="btn menu-btn <?= get_active_class('Loan-Portfolio', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-wallet2"></i></span>
            <span>Loan Portfolio</span>
        </a>

        <div class="menu-heading">Transactions</div>

        <a href="<?= $base_url ?>/Repayment-Tracker/repayments.php"
            class="btn menu-btn <?= get_active_class('Repayment-Tracker', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-cash-stack"></i></span>
            <span>Collection Monitoring</span> 
        </a>

        <a href="<?= $base_url ?>/Saving-Collection-Monitoring/savings_monitoring.php"
            class="btn menu-btn <?= get_active_class('Saving-Collection', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-piggy-bank"></i></span>
            <span>Savings Monitoring</span>
        </a>

        <a href="<?= $base_url ?>/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php"
            class="btn menu-btn <?= get_active_class('Disbursement', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-send"></i></span>
            <span>Disbursement Tracker</span>
        </a>

        <a href="<?= $base_url ?>/Compliance-Audith-Trail-System/compliance_logs.php"
            class="btn menu-btn <?= get_active_class('Compliance', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-shield-check"></i></span>
            <span>Compliance & Audit</span>
        </a>

        <div class="menu-heading">System Admin</div>

        <!-- User Management with Submenu -->
        <button class="btn menu-btn w-100 text-start d-flex align-items-center justify-content-between mt-2 px-3 py-3"
            data-bs-toggle="collapse"
            data-bs-target="#userManagementSubmenu"
            aria-expanded="false">
            <span class="d-flex align-items-center gap-3">
                <span class="icon-box"><i class="bi bi-people-fill"></i></span>
                <span>User Management</span>
            </span>
            <span class="collapse-arrow">▾</span>
        </button>

        <div class="collapse submenu-container" id="userManagementSubmenu">
            <div class="submenu-items">
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/user_management.php"
                    class="submenu-link <?= is_active('user_management.php', $current_path) ? 'active' : '' ?>">
                    Users
                </a>
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/role_permissions.php"
                    class="submenu-link <?= is_active('role_permissions.php', $current_path) ? 'active' : '' ?>">
                    Role Permissions
                </a>
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/permission_logs.php"
                    class="submenu-link <?= is_active('permission_logs.php', $current_path) ? 'active' : '' ?>">
                    Permission Logs
                </a>
            </div>
        </div>

        <!-- System Status -->
        <div class="system-status">
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>SYSTEM ONLINE</span>
            </div>
            <div class="system-info">
                Core Transaction © <?= date('Y') ?><br>
                Microfinance System v2.0
            </div>
        </div>
    </div>
</aside>

<!-- ✅ MOBILE SIDEBAR (Offcanvas) -->
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <a href="<?= $base_url ?>/dashboard.php" class="brand-link d-flex align-items-center gap-3 text-decoration-none w-100 rounded-4 px-2 py-2">
            <!-- ✅ SIDEBAR LOGO (Golden.png) with ABSOLUTE PATH -->
            <img src="<?= htmlspecialchars($sidebar_logo_path) ?>"
                alt="<?= htmlspecialchars($system_name) ?> Logo"
                width="40"
                height="40"
                style="object-fit: contain;"
                onerror="this.onerror=null; this.src='<?= $default_logo ?>';">
            <div class="lh-sm">
                <div class="brand-title"><?= htmlspecialchars($system_name) ?></div>
                <div class="brand-subtitle"><?= htmlspecialchars($system_tagline) ?></div>
            </div>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">
        <!-- Same menu structure as desktop -->
        <div class="menu-heading mt-0">Main Menu</div>

        <a href="<?= $base_url ?>/dashboard.php"
            class="btn menu-btn <?= $current_page == 'dashboard.php' ? 'menu-active' : '' ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-speedometer2"></i></span>
            <span>Dashboard</span>
        </a>

        <a href="<?= $base_url ?>/Loan-Portfolio-Risk-Management/index.php"
            class="btn menu-btn <?= get_active_class('Loan-Portfolio', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-wallet2"></i></span>
            <span>Loan Portfolio</span>
        </a>

        <div class="menu-heading">Transactions</div>

        <a href="<?= $base_url ?>/Repayment-Tracker/repayments.php"
            class="btn menu-btn <?= get_active_class('Repayment-Tracker', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-cash-stack"></i></span>
            <span>Collection Monitoring</span>
        </a>
        <a href="<?= $base_url ?>/Saving-Collection-Monitoring/savings_monitoring.php"
            class="btn menu-btn <?= get_active_class('Saving-Collection', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-piggy-bank"></i></span>
            <span>Savings Monitoring</span>
        </a>

        <a href="<?= $base_url ?>/Disbursement-Fund-Allocation-Tracker/disbursement_tracker.php"
            class="btn menu-btn <?= get_active_class('Disbursement', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-send"></i></span>
            <span>Disbursement Tracker</span>
        </a>

        <a href="<?= $base_url ?>/Compliance-Audith-Trail-System/compliance_logs.php"
            class="btn menu-btn <?= get_active_class('Compliance', $current_path) ?> w-100 text-start d-flex align-items-center gap-3 mt-2 px-3 py-3">
            <span class="icon-box"><i class="bi bi-shield-check"></i></span>
            <span>Compliance & Audit</span>
        </a>

        <div class="menu-heading">System Admin</div>

        <button class="btn menu-btn w-100 text-start d-flex align-items-center justify-content-between mt-2 px-3 py-3"
            data-bs-toggle="collapse"
            data-bs-target="#userManagementSubmenuMobile"
            aria-expanded="false">
            <span class="d-flex align-items-center gap-3">
                <span class="icon-box"><i class="bi bi-people-fill"></i></span>
                <span>User Management</span>
            </span>
            <span class="collapse-arrow">▾</span>
        </button>

        <div class="collapse submenu-container" id="userManagementSubmenuMobile">
            <div class="submenu-items">
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/user_management.php"
                    class="submenu-link <?= is_active('user_management.php', $current_path) ? 'active' : '' ?>">
                    Users
                </a>
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/role_permissions.php"
                    class="submenu-link <?= is_active('role_permissions.php', $current_path) ? 'active' : '' ?>">
                    Role Permissions
                </a>
                <a href="<?= $base_url ?>/User-Management-Role-Based-Access/permission_logs.php"
                    class="submenu-link <?= is_active('permission_logs.php', $current_path) ? 'active' : '' ?>">
                    Permission Logs
                </a>
            </div>
        </div>

        <div class="system-status">
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>SYSTEM ONLINE</span>
            </div>
            <div class="system-info">
                Core Transaction © <?= date('Y') ?><br>
                Microfinance System v2.0
            </div>
        </div>
    </div>
</div>

<!-- Toggle Sidebar Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const desktopSidebar = document.getElementById('desktopSidebar');
        const mainWrap = document.querySelector('.main-wrap');

        if (sidebarToggle && desktopSidebar && mainWrap) {
            sidebarToggle.addEventListener('click', function() {
                desktopSidebar.classList.toggle('collapsed');
                mainWrap.classList.toggle('expanded');

                // Add smooth bounce effect
                this.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        }
    });
</script>