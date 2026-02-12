<?php
// ═══════════════════════════════════════════════════════════════
// ULTIMATE FIX: Complete error suppression and buffer cleanup
// ═══════════════════════════════════════════════════════════════

// STEP 1: Disable ALL error output IMMEDIATELY
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@error_reporting(0);

// STEP 2: Clean ALL output buffers
while (@ob_get_level()) {
    @ob_end_clean();
}

// STEP 3: Start fresh output buffer
ob_start();

// STEP 4: Load initialize
require_once(__DIR__ . '/../../initialize_coreT2.php');

// STEP 5: Clean buffer started by initialize
while (@ob_get_level() > 1) {
    @ob_end_clean();
}

// STEP 6: Set headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// STEP 7: Additional error suppression
@ini_set('log_errors', '1');
@ini_set('error_log', '/tmp/php_errors.log');

// ─────────────────────────────────────────────
// HELPER FUNCTION: Calculate Total Amount Due
// ─────────────────────────────────────────────
function calculateTotalAmountDue($conn, $principal, $interestRate, $loanTerm, $loanCode = null) {
    $timeInYears = $loanTerm / 12;
    $totalInterest = $principal * ($interestRate / 100) * $timeInYears;
    
    $totalPenalties = 0;
    if ($loanCode) {
        try {
            @$tableCheck = $conn->query("SHOW TABLES LIKE 'loan_penalties'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                @$stmt = $conn->prepare("
                    SELECT COALESCE(SUM(penalty_amount), 0) as total_penalties
                    FROM loan_penalties
                    WHERE loan_code = ?
                ");
                if ($stmt) {
                    @$stmt->bind_param('s', $loanCode);
                    @$stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $totalPenalties = (float)$row['total_penalties'];
                    }
                    @$stmt->close();
                }
            }
        } catch (Exception $e) {
            $totalPenalties = 0;
        }
    }
    
    return [
        'principal' => $principal,
        'total_interest' => $totalInterest,
        'total_penalties' => $totalPenalties,
        'total_amount_due' => $principal + $totalInterest + $totalPenalties
    ];
}

// --- Get Parameters ---
$page       = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit      = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$status     = isset($_GET['status']) ? trim($_GET['status']) : '';
$risk       = isset($_GET['risk']) ? trim($_GET['risk']) : '';
$type       = isset($_GET['type']) ? trim($_GET['type']) : '';
$cardFilter = isset($_GET['cardFilter']) ? trim($_GET['cardFilter']) : 'all';
$offset     = ($page - 1) * $limit;

$response = [
    'success' => true,  // ✅ ADDED
    'message' => '',    // ✅ ADDED
    'summary' => ['total_loans' => 0, 'active_loans' => 0, 'overdue_loans' => 0, 'defaulted_loans' => 0],
    'loan_status' => ['labels' => [], 'values' => []],
    'risk_breakdown' => ['labels' => [], 'values' => []],
    'loans' => [],
    'loan_types' => [],
    'pagination' => ['current_page' => $page, 'total_pages' => 1, 'limit' => $limit, 'total_records' => 0]
];

try {
    // Summary queries
    @$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio");
    @$stmt->execute();
    $response['summary']['total_loans'] = (int)$stmt->get_result()->fetch_assoc()['c'];
    @$stmt->close();

    @$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio WHERE status='Active'");
    @$stmt->execute();
    $response['summary']['active_loans'] = (int)$stmt->get_result()->fetch_assoc()['c'];
    @$stmt->close();

    @$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio WHERE status='Defaulted'");
    @$stmt->execute();
    $response['summary']['defaulted_loans'] = (int)$stmt->get_result()->fetch_assoc()['c'];
    @$stmt->close();

    // Check if loan_schedule exists
    @$table_exists = $conn->query("SHOW TABLES LIKE 'loan_schedule'")->num_rows > 0;
    
    if ($table_exists) {
        @$stmt = $conn->prepare("
            SELECT COUNT(DISTINCT l.loan_id) AS c
            FROM loan_portfolio l
            JOIN loan_schedule s ON (s.loan_code = l.loan_code OR (s.loan_code IS NULL AND s.loan_id = l.loan_id))
            WHERE s.status='Overdue' OR (s.due_date<CURDATE() AND s.amount_paid < s.amount_due)
        ");
        @$stmt->execute();
        $response['summary']['overdue_loans'] = (int)$stmt->get_result()->fetch_assoc()['c'];
        @$stmt->close();
    }

    // Status distribution
    @$stmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM loan_portfolio GROUP BY status");
    @$stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['loan_status']['labels'][] = $row['status'];
        $response['loan_status']['values'][] = (int)$row['total'];
    }
    @$stmt->close();

    // Loan types
    @$stmt = $conn->prepare("SELECT DISTINCT loan_type FROM loan_portfolio WHERE loan_type IS NOT NULL ORDER BY loan_type");
    @$stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['loan_types'][] = $row['loan_type'];
    }
    @$stmt->close();

    // Build filters
    $where_conditions = [];
    $params = [];
    $types = '';

    if ($cardFilter === 'active') {
        $where_conditions[] = "l.status = 'Active'";
    } elseif ($cardFilter === 'overdue' && $table_exists) {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM loan_schedule ls 
            WHERE (ls.loan_code = l.loan_code OR (ls.loan_code IS NULL AND ls.loan_id = l.loan_id))
            AND (ls.status = 'Overdue' OR (ls.due_date < CURDATE() AND ls.amount_paid < ls.amount_due))
        )";
    } elseif ($cardFilter === 'defaulted') {
        $where_conditions[] = "l.status = 'Defaulted'";
    }

    if ($search !== '') {
        $where_conditions[] = "(l.loan_id LIKE ? OR l.loan_code LIKE ? OR m.full_name LIKE ? OR l.loan_type LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ssss';
    }

    if ($status !== '') {
        $where_conditions[] = "l.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($type !== '') {
        $where_conditions[] = "l.loan_type = ?";
        $params[] = $type;
        $types .= 's';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Count total
    $count_sql = "SELECT COUNT(*) AS total FROM loan_portfolio l LEFT JOIN members m ON m.member_id = l.member_id $where_clause";
    
    if ($types) {
        @$stmt = $conn->prepare($count_sql);
        @$stmt->bind_param($types, ...$params);
    } else {
        @$stmt = $conn->prepare($count_sql);
    }

    @$stmt->execute();
    $total_filtered = (int)$stmt->get_result()->fetch_assoc()['total'];
    @$stmt->close();

    $total_pages = max(1, ceil($total_filtered / $limit));
    $response['pagination']['total_pages'] = $total_pages;
    $response['pagination']['total_records'] = $total_filtered;

    $risk_counts = ['Low' => 0, 'Medium' => 0, 'High' => 0];

    // Fetch loans
    $fetch_sql = "
        SELECT l.loan_id, l.loan_code, l.member_id, l.loan_type, l.principal_amount, 
               l.interest_rate, l.loan_term, l.start_date, l.end_date, l.status,
               COALESCE(m.full_name, 'Unknown') AS member_name
        FROM loan_portfolio l
        LEFT JOIN members m ON m.member_id = l.member_id
        $where_clause
        ORDER BY l.loan_id DESC
        LIMIT ? OFFSET ?
    ";

    if ($types) {
        @$stmt = $conn->prepare($fetch_sql);
        @$stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
    } else {
        @$stmt = $conn->prepare($fetch_sql);
        @$stmt->bind_param('ii', $limit, $offset);
    }

    @$stmt->execute();
    $result = $stmt->get_result();

    while ($loan = $result->fetch_assoc()) {
        $loan_id = (int)$loan['loan_id'];
        $loan_code = $loan['loan_code'];
        
        $amounts = calculateTotalAmountDue($conn, (float)$loan['principal_amount'], (float)$loan['interest_rate'], (int)$loan['loan_term'], $loan_code);

        // Overdue count
        $overdue_count = 0;
        if ($table_exists) {
            if (!empty($loan_code)) {
                @$stmt2 = $conn->prepare("SELECT COUNT(*) AS overdue_count FROM loan_schedule WHERE loan_code = ? AND (status='Overdue' OR (due_date<CURDATE() AND amount_paid < amount_due))");
                @$stmt2->bind_param("s", $loan_code);
            } else {
                @$stmt2 = $conn->prepare("SELECT COUNT(*) AS overdue_count FROM loan_schedule WHERE loan_id = ? AND (status='Overdue' OR (due_date<CURDATE() AND amount_paid < amount_due))");
                @$stmt2->bind_param("i", $loan_id);
            }
            @$stmt2->execute();
            $overdue_count = (int)$stmt2->get_result()->fetch_assoc()['overdue_count'];
            @$stmt2->close();
        }

        // Next due
        $next_due = '-';
        if ($table_exists) {
            if (!empty($loan_code)) {
                @$stmt2 = $conn->prepare("SELECT due_date FROM loan_schedule WHERE loan_code = ? AND status <> 'Paid' ORDER BY due_date ASC LIMIT 1");
                @$stmt2->bind_param("s", $loan_code);
            } else {
                @$stmt2 = $conn->prepare("SELECT due_date FROM loan_schedule WHERE loan_id = ? AND status <> 'Paid' ORDER BY due_date ASC LIMIT 1");
                @$stmt2->bind_param("i", $loan_id);
            }
            @$stmt2->execute();
            $result2 = $stmt2->get_result();
            $next_due_row = $result2->fetch_assoc();
            $next_due = $next_due_row ? date('d M Y', strtotime($next_due_row['due_date'])) : '-';
            @$stmt2->close();
        }

        // Risk level
        $risk_level = 'Low';
        if ($loan['status'] === 'Defaulted' || $overdue_count >= 2) $risk_level = 'High';
        else if ($overdue_count === 1) $risk_level = 'Medium';
        $risk_counts[$risk_level]++;

        if ($risk !== '' && $risk_level !== $risk) continue;

        $response['loans'][] = [
            'loan_id'          => $loan_id,
            'loan_code'        => $loan_code,
            'member_id'        => (int)$loan['member_id'],
            'member_name'      => htmlspecialchars($loan['member_name'], ENT_QUOTES, 'UTF-8'),
            'loan_type'        => htmlspecialchars($loan['loan_type'], ENT_QUOTES, 'UTF-8'),
            'principal_amount' => (float)$loan['principal_amount'],
            'interest_rate'    => (float)$loan['interest_rate'],
            'loan_term'        => (int)$loan['loan_term'],
            'start_date'       => $loan['start_date'] ? date('d M Y', strtotime($loan['start_date'])) : '-',
            'end_date'         => $loan['end_date'] ? date('d M Y', strtotime($loan['end_date'])) : '-',
            'status'           => $loan['status'],
            'overdue_count'    => $overdue_count,
            'risk_level'       => $risk_level,
            'next_due'         => $next_due,
            'total_interest'   => round($amounts['total_interest'], 2),
            'total_penalties'  => round($amounts['total_penalties'], 2),
            'total_amount_due' => round($amounts['total_amount_due'], 2)
        ];
    }
    @$stmt->close();

    $response['risk_breakdown']['labels'] = array_keys($risk_counts);
    $response['risk_breakdown']['values'] = array_values($risk_counts);

} catch (Exception $e) {
    // Silent fail - just return empty data
    $response['success'] = false;  // ✅ ADDED
    $response['message'] = 'Error: ' . $e->getMessage();  // ✅ ADDED
}

// ✅ ADDED: Set success message if no error
if ($response['success'] && empty($response['message'])) {
    $response['message'] = 'Successfully loaded ' . count($response['loans']) . ' loans';
}

// FINAL STEP: Clean buffer and output pure JSON
@ob_end_clean();
echo json_encode($response);
exit;