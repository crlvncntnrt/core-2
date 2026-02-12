<?php
// ============================================================================
// savings_action.php - FULL REPLACE (SearchBy dropdown + exact numeric search)
// ============================================================================

require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

// ---------------------------------------------------------------------------
// CSV EXPORT MUST RUN BEFORE JSON HEADER
// ---------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search = trim($_GET['search'] ?? '');
    $search_by = $_GET['search_by'] ?? 'auto';

    $filter = $_GET['filter'] ?? '';
    $type = $_GET['type'] ?? '';

    $member_id = intval($_GET['member_id'] ?? 0);
    $recorded_by = intval($_GET['recorded_by'] ?? 0);

    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $where = [];
    $params = [];
    $types = '';

// Search logic (bulletproof)
if ($search !== '') {

    // If input is pure number: ALWAYS exact member_id match
    if (preg_match('/^\d+$/', $search)) {
        $where[] = "s.member_id = ?";
        $params[] = intval($search);
        $types .= 'i';
    } else {

        // If not number: use dropdown logic
        if ($search_by === 'transaction_type') {
            $where[] = "s.transaction_type LIKE ?";
            $params[] = "%$search%";
            $types .= 's';

        } elseif ($search_by === 'transaction_date') {
            $where[] = "s.transaction_date LIKE ?";
            $params[] = "%$search%";
            $types .= 's';

        } elseif ($search_by === 'recorded_by_name') {
            $where[] = "s.recorded_by IN (SELECT user_id FROM users WHERE full_name LIKE ?)";
            $params[] = "%$search%";
            $types .= 's';

        } else {
            // auto / member_id / unknown = fallback to your old partial search
            $where[] = "(CAST(s.member_id AS CHAR) LIKE ? OR s.transaction_type LIKE ? OR s.transaction_date LIKE ?)";
            $s = "%$search%";
            $params[] = $s; $params[] = $s; $params[] = $s;
            $types .= 'sss';
        }
    }
}

    if ($filter === 'deposit') $where[] = "s.transaction_type='Deposit'";
    elseif ($filter === 'withdrawal') $where[] = "s.transaction_type='Withdrawal'";

    if ($type !== '') {
        $where[] = "s.transaction_type=?";
        $params[] = $type;
        $types .= 's';
    }

    if ($member_id > 0) {
        $where[] = "s.member_id=?";
        $params[] = $member_id;
        $types .= 'i';
    }

    if ($recorded_by > 0) {
        $where[] = "s.recorded_by=?";
        $params[] = $recorded_by;
        $types .= 'i';
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
    fputcsv($out, ['ID','Member ID','Date','Type','Amount','Balance','Recorded By']);

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
        $stmt->close();
    } else {
        $res = $conn->query($sql);
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
    }

    fclose($out);
    exit;
}

// JSON responses
header('Content-Type: application/json');

// ---------------------------------------------------------------------------
// ROLE & PERMISSION SECURITY
// ---------------------------------------------------------------------------
$role = $_SESSION['userdata']['role'] ?? 'Guest';
$user_id = intval($_SESSION['userdata']['user_id'] ?? 0);

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

    $whereClean = str_replace(['WHERE ', 'where '], '', trim($where));
    $hasWhere = !empty($whereClean);

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

    $whereDeposit = $hasWhere ? "WHERE $whereClean AND transaction_type='Deposit'" : "WHERE transaction_type='Deposit'";
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

    $whereWithdraw = $hasWhere ? "WHERE $whereClean AND transaction_type='Withdrawal'" : "WHERE transaction_type='Withdrawal'";
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

    $q = $conn->query("SELECT balance FROM savings ORDER BY saving_id DESC LIMIT 1");
    $summary['last_balance'] = $q ? ($q->fetch_assoc()['balance'] ?? 0) : 0;

    return $summary;
}

// ---------------------------------------------------------------------------
// MAIN LOGIC
// ---------------------------------------------------------------------------
try {
    switch ($action) {

        case 'meta':
            $members = [];
            $q1 = $conn->query("SELECT DISTINCT member_id FROM savings ORDER BY member_id ASC");
            while ($q1 && $r = $q1->fetch_assoc()) $members[] = intval($r['member_id']);

            $users = [];
            $q2 = $conn->query("
                SELECT DISTINCT u.user_id, u.full_name
                FROM savings s
                JOIN users u ON u.user_id = s.recorded_by
                ORDER BY u.full_name ASC
            ");
            while ($q2 && $r = $q2->fetch_assoc()) $users[] = $r;

            echo json_encode(['status' => 'success', 'members' => $members, 'recorded_by' => $users]);
            break;

        case 'list':
            $page = max(1, intval($_POST['page'] ?? 1));
            $limit = max(1, intval($_POST['limit'] ?? 10));
            $offset = ($page - 1) * $limit;

            $search = trim($_POST['search'] ?? '');
            $search_by = $_POST['search_by'] ?? 'auto';

            $filter = $_POST['filter'] ?? '';
            $type = $_POST['type'] ?? '';

            $member_id = intval($_POST['member_id'] ?? 0);
            $recorded_by = intval($_POST['recorded_by'] ?? 0);

            $date_from = $_POST['date_from'] ?? '';
            $date_to = $_POST['date_to'] ?? '';

            $where = [];
            $params = [];
            $types = '';

            // Search logic (safe + exact numeric)
            if ($search !== '') {
                if ($search_by === 'auto') {
                    if (preg_match('/^\d+$/', $search)) {
                        $where[] = "s.member_id = ?";
                        $params[] = intval($search);
                        $types .= 'i';
                    } else {
                        $where[] = "(CAST(s.member_id AS CHAR) LIKE ? OR s.transaction_type LIKE ? OR s.transaction_date LIKE ?)";
                        $s = "%$search%";
                        $params[] = $s; $params[] = $s; $params[] = $s;
                        $types .= 'sss';
                    }
                } elseif ($search_by === 'member_id') {
                    if (preg_match('/^\d+$/', $search)) {
                        $where[] = "s.member_id = ?";
                        $params[] = intval($search);
                        $types .= 'i';
                    } else {
                        $where[] = "1=0";
                    }
                } elseif ($search_by === 'transaction_type') {
                    $where[] = "s.transaction_type LIKE ?";
                    $params[] = "%$search%";
                    $types .= 's';
                } elseif ($search_by === 'transaction_date') {
                    $where[] = "s.transaction_date LIKE ?";
                    $params[] = "%$search%";
                    $types .= 's';
                } elseif ($search_by === 'recorded_by_name') {
                    $where[] = "s.recorded_by IN (SELECT user_id FROM users WHERE full_name LIKE ?)";
                    $params[] = "%$search%";
                    $types .= 's';
                }
            }

            if ($filter === 'deposit') $where[] = "s.transaction_type='Deposit'";
            elseif ($filter === 'withdrawal') $where[] = "s.transaction_type='Withdrawal'";

            if ($type !== '') {
                $where[] = "s.transaction_type=?";
                $params[] = $type;
                $types .= 's';
            }

            if ($member_id > 0) {
                $where[] = "s.member_id=?";
                $params[] = $member_id;
                $types .= 'i';
            }

            if ($recorded_by > 0) {
                $where[] = "s.recorded_by=?";
                $params[] = $recorded_by;
                $types .= 'i';
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

            // Rows
            $sql = "
                SELECT s.*, u.full_name AS recorded_by_name
                FROM savings s
                LEFT JOIN users u ON s.recorded_by = u.user_id
                $whereSql
                ORDER BY s.transaction_date DESC, s.saving_id DESC
                LIMIT ?, ?
            ";

            $stmt = $conn->prepare($sql);
            $bindTypes = $types . 'ii';
            $bindParams = $params;
            $bindParams[] = $offset;
            $bindParams[] = $limit;

            $stmt->bind_param($bindTypes, ...$bindParams);
            $stmt->execute();
            $res = $stmt->get_result();

            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();

            // Count
            $countSql = "SELECT COUNT(*) AS cnt FROM savings s $whereSql";
            $total = 0;

            if (!empty($params)) {
                $countStmt = $conn->prepare($countSql);
                $countStmt->bind_param($types, ...$params);
                $countStmt->execute();
                $total = intval($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
                $countStmt->close();
            } else {
                $result = $conn->query($countSql);
                $total = intval($result->fetch_assoc()['cnt'] ?? 0);
            }

            $total_pages = $limit > 0 ? ceil($total / $limit) : 1;

            // Summary where must not contain "s."
            $summaryWhere = str_replace('s.', '', $whereSql);
            $summaryWhere = str_replace('WHERE ', '', $summaryWhere);

            echo json_encode([
                'status' => 'success',
                'rows' => $rows,
                'summary' => getSummary($conn, $summaryWhere, $params, $types),
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => max(1, $total_pages),
                    'total_records' => $total
                ]
            ]);
            break;

        case 'breakdown':
            $member_id = intval($_POST['member_id'] ?? 0);
            if (!$member_id) {
                echo json_encode(['status' => 'error', 'msg' => 'Member ID required']);
                exit;
            }

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
            while ($r = $res->fetch_assoc()) $transactions[] = $r;
            $stmt->close();

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
                if (function_exists('logPermission')) {
                    logPermission($conn, $user_id, 'Savings Monitoring', 'Add', 'Success');
                }
                echo json_encode(['status' => 'success', 'msg' => 'Transaction added successfully']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Failed to save transaction']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Savings action error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'Server error']);
    exit;
}
