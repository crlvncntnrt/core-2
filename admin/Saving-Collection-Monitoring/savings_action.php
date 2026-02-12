<?php
// ============================================================================
// savings_action.php - ENHANCED with Member Breakdown - SECURITY FIXED
// ============================================================================

require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

header('Content-Type: application/json');

// ---------------------------------------------------------------------------
// ROLE & PERMISSION SECURITY
// ---------------------------------------------------------------------------
$role = $_SESSION['userdata']['role'] ?? 'Guest';
$user_id = intval($_SESSION['userdata']['user_id'] ?? 0);
$user_name = $_SESSION['userdata']['full_name'] ?? 'Unknown';

if (!hasPermission($conn, $role, 'Savings Monitoring', 'view') && $role !== 'Admin') {
    echo json_encode(['status' => 'error', 'msg' => 'Access denied']);
    exit();
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// ---------------------------------------------------------------------------
// HELPER: SUMMARY CALCULATOR (with filters)
// ---------------------------------------------------------------------------
function getSummary($conn, $where = '', $params = [], $types = '')
{
    $summary = [
        'total' => 0,
        'total_deposits' => 0,
        'total_withdrawals' => 0,
        'last_balance' => 0
    ];

    // Clean where clause
    $whereClean = str_replace(['WHERE ', 'where '], '', trim($where));
    $hasWhere = !empty($whereClean);

    // Total count
    $sql = "SELECT COUNT(*) AS total FROM savings" . ($hasWhere ? " WHERE $whereClean" : "");
    if ($params && count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $summary['total'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    } else {
        $summary['total'] = $conn->query($sql)->fetch_assoc()['total'] ?? 0;
    }

    // Total deposits
    if ($hasWhere) {
        $whereDeposit = "WHERE $whereClean AND transaction_type='Deposit'";
    } else {
        $whereDeposit = "WHERE transaction_type='Deposit'";
    }
    $sql = "SELECT COUNT(*) AS total_deposits FROM savings $whereDeposit";
    if ($params && count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $summary['total_deposits'] = $stmt->get_result()->fetch_assoc()['total_deposits'] ?? 0;
        $stmt->close();
    } else {
        $summary['total_deposits'] = $conn->query($sql)->fetch_assoc()['total_deposits'] ?? 0;
    }

    // Total withdrawals
    if ($hasWhere) {
        $whereWithdraw = "WHERE $whereClean AND transaction_type='Withdrawal'";
    } else {
        $whereWithdraw = "WHERE transaction_type='Withdrawal'";
    }
    $sql = "SELECT COUNT(*) AS total_withdrawals FROM savings $whereWithdraw";
    if ($params && count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $summary['total_withdrawals'] = $stmt->get_result()->fetch_assoc()['total_withdrawals'] ?? 0;
        $stmt->close();
    } else {
        $summary['total_withdrawals'] = $conn->query($sql)->fetch_assoc()['total_withdrawals'] ?? 0;
    }

    // Last balance
    $q = $conn->query("SELECT balance FROM savings ORDER BY saving_id DESC LIMIT 1");
    $summary['last_balance'] = $q->fetch_assoc()['balance'] ?? 0;

    return $summary;
}

// ---------------------------------------------------------------------------
// MAIN LOGIC
// ---------------------------------------------------------------------------
try {
    switch ($action) {

        // =====================================================
        // LIST TRANSACTIONS
        // =====================================================
        case 'list':
            $page = max(1, intval($_POST['page'] ?? 1));
            $limit = max(1, intval($_POST['limit'] ?? 10));
            $offset = ($page - 1) * $limit;
            $search = trim($_POST['search'] ?? '');
            $filter = $_POST['filter'] ?? '';
            $type = $_POST['type'] ?? '';
            $date_from = $_POST['date_from'] ?? '';
            $date_to = $_POST['date_to'] ?? '';

            $where = [];
            $params = [];
            $types = '';

            // Search filter
            if ($search !== '') {
                $where[] = "(CAST(s.member_id AS CHAR) LIKE ? OR s.transaction_type LIKE ? OR s.transaction_date LIKE ?)";
                $s = "%$search%";
                $params[] = $s;
                $params[] = $s;
                $params[] = $s;
                $types .= 'sss';
            }

            // Card filter
            if ($filter === 'deposit') {
                $where[] = "s.transaction_type='Deposit'";
            } elseif ($filter === 'withdrawal') {
                $where[] = "s.transaction_type='Withdrawal'";
            }

            // Type filter
            if ($type !== '') {
                $where[] = "s.transaction_type=?";
                $params[] = $type;
                $types .= 's';
            }

            // Date range
            if ($date_from !== '') {
                $where[] = "s.transaction_date >= ?";
                $params[] = $date_from;
                $types .= 's';
            }
            if ($date_to !== '') {
                $where[] = "s.transaction_date <= ?";
                $params[] = $date_to;
                $types .= 's';
            }

            $whereSql = count($where) ? "WHERE " . implode(' AND ', $where) : '';

            // Get rows
            $sql = "
                SELECT s.*, u.full_name AS recorded_by_name
                FROM savings s
                LEFT JOIN users u ON s.recorded_by = u.user_id
                $whereSql
                ORDER BY s.transaction_date DESC, s.saving_id DESC
                LIMIT ?, ?
            ";

            $stmt = $conn->prepare($sql);
            $types .= 'ii';
            $params[] = $offset;
            $params[] = $limit;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();

            // Total count
            $countSql = "SELECT COUNT(*) AS cnt FROM savings s " . $whereSql;
            $total = 0;
            
            if (count($params) > 2) {
                $countParams = array_slice($params, 0, -2);
                $countTypes = substr($types, 0, -2);
                $countStmt = $conn->prepare($countSql);
                $countStmt->bind_param($countTypes, ...$countParams);
                $countStmt->execute();
                $total = intval($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
                $countStmt->close();
            } else {
                $result = $conn->query($countSql);
                $total = intval($result->fetch_assoc()['cnt'] ?? 0);
            }

            $total_pages = $limit > 0 ? ceil($total / $limit) : 1;

            $summaryParams = count($params) > 2 ? array_slice($params, 0, -2) : [];
            $summaryTypes = count($params) > 2 ? substr($types, 0, -2) : '';
            $summaryWhere = str_replace('s.', '', $whereSql);

            echo json_encode([
                'status' => 'success',
                'rows' => $rows,
                'summary' => getSummary($conn, $summaryWhere, $summaryParams, $summaryTypes),
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => max(1, $total_pages),
                    'total_records' => $total
                ]
            ]);
            break;

        // =====================================================
        // GET MEMBER BREAKDOWN - SECURITY FIXED!
        // =====================================================
        case 'breakdown':
            $member_id = intval($_POST['member_id'] ?? 0);
            
            if (!$member_id) {
                echo json_encode(['status' => 'error', 'msg' => 'Member ID required']);
                exit;
            }

            // FIXED: Use prepared statement instead of direct query
            $memberStmt = $conn->prepare("
                SELECT member_id, 
                       CONCAT('Member #', member_id) as name
                FROM savings 
                WHERE member_id = ?
                LIMIT 1
            ");
            $memberStmt->bind_param("i", $member_id);
            $memberStmt->execute();
            $memberInfo = $memberStmt->get_result()->fetch_assoc();
            $memberStmt->close();

            // Get all transactions for this member
            $stmt = $conn->prepare("
                SELECT s.*, u.full_name AS recorded_by_name
                FROM savings s
                LEFT JOIN users u ON s.recorded_by = u.user_id
                WHERE s.member_id = ?
                ORDER BY s.transaction_date DESC, s.saving_id DESC
            ");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $transactions = [];
            while ($r = $res->fetch_assoc()) {
                $transactions[] = $r;
            }
            $stmt->close();

            // Calculate member summary
            $memberSummary = [
                'total_deposits' => 0,
                'total_withdrawals' => 0,
                'deposit_count' => 0,
                'withdrawal_count' => 0,
                'current_balance' => 0,
                'total_transactions' => count($transactions)
            ];

            foreach ($transactions as $txn) {
                if ($txn['transaction_type'] === 'Deposit') {
                    $memberSummary['total_deposits'] += floatval($txn['amount']);
                    $memberSummary['deposit_count']++;
                } else {
                    $memberSummary['total_withdrawals'] += floatval($txn['amount']);
                    $memberSummary['withdrawal_count']++;
                }
            }

            // Get current balance
            if (!empty($transactions)) {
                $memberSummary['current_balance'] = floatval($transactions[0]['balance']);
            }

            echo json_encode([
                'status' => 'success',
                'member_info' => $memberInfo ?: ['member_id' => $member_id, 'name' => "Member #$member_id"],
                'summary' => $memberSummary,
                'transactions' => $transactions
            ]);
            break;

        // =====================================================
        // GET SINGLE TRANSACTION
        // =====================================================
        case 'get':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['status' => 'error', 'msg' => 'ID required']);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT s.*, u.full_name AS recorded_by_name
                FROM savings s
                LEFT JOIN users u ON s.recorded_by = u.user_id
                WHERE s.saving_id=?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res) echo json_encode(['status' => 'success', 'row' => $res]);
            else echo json_encode(['status' => 'error', 'msg' => 'Record not found']);
            break;

        // =====================================================
        // ADD TRANSACTION
        // =====================================================
        case 'add':
            if (!hasPermission($conn, $role, 'Savings Monitoring', 'add') && $role !== 'Admin') {
                echo json_encode(['status' => 'error', 'msg' => 'Permission denied']);
                exit;
            }
            
            $member_id = intval($_POST['member_id'] ?? 0);
            $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
            $type = $_POST['transaction_type'] ?? 'Deposit';
            $amount = floatval($_POST['amount'] ?? 0.0);

            if ($member_id <= 0 || $amount <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Member and positive amount required']);
                exit;
            }

            $balRes = $conn->prepare("SELECT balance FROM savings WHERE member_id = ? ORDER BY saving_id DESC LIMIT 1");
            $balRes->bind_param("i", $member_id);
            $balRes->execute();
            $bR = $balRes->get_result()->fetch_assoc();
            $last_balance = $bR['balance'] ?? 0;
            $balRes->close();

            $new_balance = ($type === 'Deposit') ? ($last_balance + $amount) : ($last_balance - $amount);

            if ($new_balance < 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Insufficient balance for withdrawal']);
                exit;
            }

            $stmt = $conn->prepare("
                INSERT INTO savings (member_id, transaction_date, transaction_type, amount, balance, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issddi", $member_id, $transaction_date, $type, $amount, $new_balance, $user_id);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                logPermission($conn, $user_id, 'Savings Monitoring', 'Add', 'Success');
                echo json_encode(['status' => 'success', 'msg' => 'Transaction added successfully']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Failed to save transaction']);
            }
            break;

        // =====================================================
        // CSV EXPORT
        // =====================================================
        default:
            if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                $search = trim($_GET['search'] ?? '');
                $filter = $_GET['filter'] ?? '';
                $type = $_GET['type'] ?? '';
                $date_from = $_GET['date_from'] ?? '';
                $date_to = $_GET['date_to'] ?? '';

                $where = [];
                $params = [];
                $types = '';

                if ($search !== '') {
                    $where[] = "(CAST(s.member_id AS CHAR) LIKE ? OR s.transaction_type LIKE ? OR s.transaction_date LIKE ?)";
                    $s = "%$search%";
                    $params[] = $s;
                    $params[] = $s;
                    $params[] = $s;
                    $types .= 'sss';
                }

                if ($filter === 'deposit') {
                    $where[] = "s.transaction_type='Deposit'";
                } elseif ($filter === 'withdrawal') {
                    $where[] = "s.transaction_type='Withdrawal'";
                }

                if ($type !== '') {
                    $where[] = "s.transaction_type=?";
                    $params[] = $type;
                    $types .= 's';
                }

                if ($date_from !== '') {
                    $where[] = "s.transaction_date >= ?";
                    $params[] = $date_from;
                    $types .= 's';
                }
                if ($date_to !== '') {
                    $where[] = "s.transaction_date <= ?";
                    $params[] = $date_to;
                    $types .= 's';
                }

                $whereSql = count($where) ? "WHERE " . implode(' AND ', $where) : '';

                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="savings_export_' . date('Y-m-d') . '.csv"');

                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Member ID', 'Date', 'Type', 'Amount', 'Balance', 'Recorded By']);

                $sql = "
                    SELECT s.*, u.full_name AS recorded_by_name
                    FROM savings s
                    LEFT JOIN users u ON s.recorded_by = u.user_id
                    $whereSql
                    ORDER BY s.transaction_date DESC, s.saving_id DESC
                ";

                if ($params) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $res = $stmt->get_result();
                } else {
                    $res = $conn->query($sql);
                }

                while ($r = $res->fetch_assoc()) {
                    fputcsv($out, [
                        $r['saving_id'],
                        $r['member_id'],
                        $r['transaction_date'],
                        $r['transaction_type'],
                        $r['amount'],
                        $r['balance'],
                        $r['recorded_by_name'] ?? '-'
                    ]);
                }

                if (isset($stmt)) $stmt->close();
                fclose($out);
                exit;
            }

            echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Savings action error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'Server error: ' . $e->getMessage()]);
    exit;
}