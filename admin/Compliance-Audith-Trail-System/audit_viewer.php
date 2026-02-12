<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/log_audit.php');
require_once __DIR__ . '/../inc/check_auth.php';

// ✅ RBAC protection - CHECK BEFORE ANY OUTPUT
if (!isset($_SESSION['userdata'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['userdata'];
if (!in_array($user['role'], ['Super Admin', 'Admin'])) {
    // Show minimal output for unauthorized access
    include(__DIR__ . '/../inc/header.php');
    include(__DIR__ . '/../inc/navbar.php');
    include(__DIR__ . '/../inc/sidebar.php');
    echo "<div class='alert alert-danger m-4'>Access denied: You don't have permission to view audit logs.</div>";
    include(__DIR__ . '/../inc/footer.php');
    exit;
}

// === Handle CSV Export BEFORE any HTML output ===
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Filters for export
    $search = $_GET['search'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';

    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = "(a.action_type LIKE ? OR a.module_name LIKE ? OR u.full_name LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        $types .= 'sss';
    }

    if ($start_date !== '' && $end_date !== '') {
        $where[] = "DATE(a.action_time) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= 'ss';
    }

    $whereSQL = count($where) ? "WHERE " . implode(' AND ', $where) : '';

    // Export CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    
    // BOM for Excel UTF-8 support
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($out, ['#', 'User', 'Action', 'Module', 'Remarks', 'Compliance', 'Date/Time', 'IP']);

    $csv_stmt = $conn->prepare("
        SELECT a.*, u.full_name
        FROM audit_trail a
        LEFT JOIN users u ON a.user_id = u.user_id
        $whereSQL
        ORDER BY a.action_time DESC
    ");
    
    if ($types !== '') {
        $csv_stmt->bind_param($types, ...$params);
    }
    
    $csv_stmt->execute();
    $csv_res = $csv_stmt->get_result();
    
    $n = 1;
    while ($row = $csv_res->fetch_assoc()) {
        fputcsv($out, [
            $n++,
            $row['full_name'] ?? 'System',
            $row['action_type'],
            $row['module_name'],
            $row['remarks'],
            $row['compliance_status'],
            $row['action_time'],
            $row['ip_address']
        ]);
    }
    
    fclose($out);
    $csv_stmt->close();
    exit;
}

// === Filters ===
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// === Build Query ===
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(a.action_type LIKE ? OR a.module_name LIKE ? OR u.full_name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= 'sss';
}

if ($start_date !== '' && $end_date !== '') {
    $where[] = "DATE(a.action_time) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
}

$whereSQL = count($where) ? "WHERE " . implode(' AND ', $where) : '';

// === Count total records ===
$countSQL = "SELECT COUNT(*) AS total FROM audit_trail a LEFT JOIN users u ON a.user_id = u.user_id $whereSQL";
$stmt = $conn->prepare($countSQL);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$total_pages = ceil($total / $per_page);

// === Fetch records ===
$sql = "
    SELECT a.*, u.full_name, u.username
    FROM audit_trail a
    LEFT JOIN users u ON a.user_id = u.user_id
    $whereSQL
    ORDER BY a.action_time DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

// Bind parameters including LIMIT and OFFSET
if ($types !== '') {
    $allParams = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($types . 'ii', ...$allParams);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// NOW include headers after all processing
include(__DIR__ . '/../inc/header.php');
include(__DIR__ . '/../inc/navbar.php');
include(__DIR__ . '/../inc/sidebar.php');
?>

<main class="main-content" id="main-content">
    <div class="container-fluid mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-journal-text"></i> Audit Trail Viewer</h5>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search user/module/action" 
                           value="<?= htmlspecialchars($search) ?>">
                    <input type="date" name="start_date" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($start_date) ?>">
                    <input type="date" name="end_date" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($end_date) ?>">
                    <button type="submit" class="btn btn-light btn-sm">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="audit_viewer.php" class="btn btn-secondary btn-sm">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                    <a href="?export=csv<?= $search ? '&search=' . urlencode($search) : '' ?><?= $start_date ? '&start_date=' . $start_date : '' ?><?= $end_date ? '&end_date=' . $end_date : '' ?>" 
                       class="btn btn-success btn-sm">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Remarks</th>
                                <th>Compliance</th>
                                <th>Date/Time</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php 
                                $i = $offset + 1;
                                while ($row = $result->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($row['full_name'] ?? 'System') ?></td>
                                        <td>
                                            <?php
                                            $action = htmlspecialchars($row['action_type']);
                                            if (stripos($action, 'login') !== false && stripos($action, 'failed') === false) {
                                                echo "<span class='badge bg-success'>$action</span>";
                                            } elseif (stripos($action, 'logout') !== false || stripos($action, 'failed') !== false) {
                                                echo "<span class='badge bg-danger'>$action</span>";
                                            } elseif (stripos($action, 'delete') !== false) {
                                                echo "<span class='badge bg-danger'>$action</span>";
                                            } elseif (stripos($action, 'update') !== false || stripos($action, 'edit') !== false) {
                                                echo "<span class='badge bg-warning text-dark'>$action</span>";
                                            } elseif (stripos($action, 'create') !== false || stripos($action, 'add') !== false) {
                                                echo "<span class='badge bg-info'>$action</span>";
                                            } else {
                                                echo "<span class='badge bg-primary'>$action</span>";
                                            }
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['module_name']) ?></td>
                                        <td class="text-start small"><?= htmlspecialchars($row['remarks']) ?></td>
                                        <td>
                                            <?php
                                            $status = htmlspecialchars($row['compliance_status']);
                                            $badgeClass = 
                                                $status === 'Compliant' ? 'bg-success' :
                                                ($status === 'Non-Compliant' ? 'bg-danger' : 'bg-warning text-dark');
                                            echo "<span class='badge $badgeClass'>$status</span>";
                                            ?>
                                        </td>
                                        <td><small><?= htmlspecialchars($row['action_time']) ?></small></td>
                                        <td><small><?= htmlspecialchars($row['ip_address']) ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                        <p class="mb-0 mt-2">No audit logs found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    Showing <?= $total > 0 ? $offset + 1 : 0 ?> to <?= min($offset + $per_page, $total) ?> of <?= $total ?> entries
                </span>

                <nav aria-label="Audit logs pagination">
                    <ul class="pagination mb-0">
                        <!-- Previous -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                                Previous
                            </a>
                        </li>

                        <!-- Page numbers -->
                        <?php 
                        $range = 2;
                        $start_page = max(1, $page - $range);
                        $end_page = min($total_pages, $page + $range);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Next -->
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</main>

<?php 
$stmt->close();
include(__DIR__ . '/../inc/footer.php'); 
?>