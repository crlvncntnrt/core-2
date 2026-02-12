<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

try {
    // ✅ Use the existing MySQLi connection from initialize_coreT2.php
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
    }

    // Get parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $riskFilter = isset($_GET['risk']) ? trim($_GET['risk']) : '';
    $typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';
    $cardFilter = isset($_GET['cardFilter']) ? trim($_GET['cardFilter']) : 'all';

    $offset = ($page - 1) * $limit;

    // --- SUMMARY CARDS ---
    $summary = [
        'total_loans' => 0,
        'active_loans' => 0,
        'overdue_loans' => 0,
        'at_risk_loans' => 0
    ];

    // Total loans
    $result = $conn->query("SELECT COUNT(*) as cnt FROM loan_portfolio");
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['total_loans'] = (int)$row['cnt'];
    }

    // ✨ FIXED: Active loans (loans with paid schedules and NO overdue)
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp 
        WHERE EXISTS (
            SELECT 1 FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id
            AND ls.status = 'Paid'
        )
        AND NOT EXISTS (
            SELECT 1 FROM loan_schedule ls2
            WHERE ls2.loan_id = lp.loan_id 
            AND ls2.status = 'Overdue'
        )
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['active_loans'] = (int)$row['cnt'];
    }

    // Overdue loans
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp 
        INNER JOIN loan_schedule ls ON lp.loan_id = ls.loan_id 
        WHERE ls.status = 'Overdue'
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['overdue_loans'] = (int)$row['cnt'];
    }

    // At-risk loans (3+ overdue or defaulted)
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        WHERE lp.status = 'Defaulted' OR (
            SELECT COUNT(*) 
            FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) >= 3
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['at_risk_loans'] = (int)$row['cnt'];
    }

    // --- LOAN STATUS DISTRIBUTION ---
    $statusData = ['labels' => [], 'values' => []];
    $result = $conn->query("
        SELECT status, COUNT(*) as cnt 
        FROM loan_portfolio 
        WHERE status IS NOT NULL
        GROUP BY status
        ORDER BY 
            CASE 
                WHEN status = 'Active' THEN 1
                WHEN status = 'Pending' THEN 2
                WHEN status = 'Approved' THEN 3
                WHEN status = 'Completed' THEN 4
                WHEN status = 'Defaulted' THEN 5
                ELSE 6
            END
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $statusData['labels'][] = $row['status'];
            $statusData['values'][] = (int)$row['cnt'];
        }
    }

    // --- RISK BREAKDOWN ---
    $riskData = ['labels' => ['Low', 'Medium', 'High'], 'values' => [0, 0, 0]];

    // Low risk (loans with paid schedules and no overdue)
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        WHERE EXISTS (
            SELECT 1 FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id
            AND ls.status = 'Paid'
        )
        AND (
            SELECT COUNT(*) 
            FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) = 0
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $riskData['values'][0] = (int)$row['cnt'];
    }

    // Medium risk (1-2 overdue schedules)
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        WHERE (
            SELECT COUNT(*) 
            FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) BETWEEN 1 AND 2
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $riskData['values'][1] = (int)$row['cnt'];
    }

    // High risk (3+ overdue or defaulted)
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        WHERE lp.status = 'Defaulted' OR (
            SELECT COUNT(*) 
            FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) >= 3
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $riskData['values'][2] = (int)$row['cnt'];
    }

    // --- GET LOAN TYPES ---
    $loanTypes = [];
    $result = $conn->query("
        SELECT DISTINCT loan_type 
        FROM loan_portfolio 
        WHERE loan_type IS NOT NULL 
        ORDER BY loan_type
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $loanTypes[] = $row['loan_type'];
        }
    }

    // --- BUILD WHERE CLAUSES FOR FILTERING ---
    $whereClauses = [];
    $searchParam = '';
    $statusParam = '';
    $typeParam = '';

    // Search filter
    if ($search !== '') {
        $searchParam = $conn->real_escape_string($search);
        $whereClauses[] = "(lp.loan_id LIKE '%$searchParam%' OR m.full_name LIKE '%$searchParam%' OR lp.loan_type LIKE '%$searchParam%')";
    }

    // Status filter
    if ($statusFilter !== '') {
        $statusParam = $conn->real_escape_string($statusFilter);
        $whereClauses[] = "lp.status = '$statusParam'";
    }

    // Type filter
    if ($typeFilter !== '') {
        $typeParam = $conn->real_escape_string($typeFilter);
        $whereClauses[] = "lp.loan_type = '$typeParam'";
    }

    // Risk filter
    if ($riskFilter !== '') {
        if ($riskFilter === 'Low') {
            $whereClauses[] = "overdue_count = 0 AND lp.status IN ('Active', 'Approved')";
        } elseif ($riskFilter === 'Medium') {
            $whereClauses[] = "overdue_count BETWEEN 1 AND 2";
        } elseif ($riskFilter === 'High') {
            $whereClauses[] = "(overdue_count >= 3 OR lp.status = 'Defaulted')";
        }
    }

    // Card filter
    if ($cardFilter !== 'all') {
        if ($cardFilter === 'active') {
            $whereClauses[] = "EXISTS (SELECT 1 FROM loan_schedule WHERE loan_id = lp.loan_id AND status = 'Paid') AND overdue_count = 0";
        } elseif ($cardFilter === 'overdue') {
            $whereClauses[] = "overdue_count > 0";
        } elseif ($cardFilter === 'at_risk') {
            $whereClauses[] = "(overdue_count >= 3 OR lp.status = 'Defaulted')";
        }
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    // --- COUNT TOTAL RECORDS ---
    $countSql = "
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) as overdue_count
            FROM loan_schedule 
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
    ";
    $result = $conn->query($countSql);
    $totalRecords = 0;
    if ($result) {
        $row = $result->fetch_assoc();
        $totalRecords = (int)$row['cnt'];
    }
    $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 1;

    // --- FETCH LOANS ---
    $sql = "
        SELECT 
            lp.loan_id,
            lp.member_id,
            lp.loan_type,
            lp.principal_amount,
            lp.interest_rate,
            lp.loan_term,
            lp.start_date,
            lp.end_date,
            lp.status,
            m.full_name as member_name,
            COALESCE(ls.overdue_count, 0) as overdue_count,
            CASE 
                WHEN lp.status = 'Defaulted' THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                ELSE 'Low'
            END as risk_level,
            (
                SELECT MIN(due_date) 
                FROM loan_schedule 
                WHERE loan_id = lp.loan_id 
                AND status = 'Pending' 
                LIMIT 1
            ) as next_due
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) as overdue_count
            FROM loan_schedule 
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
        ORDER BY lp.loan_id DESC
        LIMIT $offset, $limit
    ";

    $result = $conn->query($sql);
    $loans = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $loans[] = $row;
        }
    }

    // --- GET ALL LOANS FOR PDF EXPORT ---
    $allLoansSql = "
        SELECT 
            lp.loan_id,
            lp.member_id,
            lp.loan_type,
            lp.principal_amount,
            lp.interest_rate,
            lp.loan_term,
            lp.start_date,
            lp.end_date,
            lp.status,
            m.full_name as member_name,
            COALESCE(ls.overdue_count, 0) as overdue_count,
            CASE 
                WHEN lp.status = 'Defaulted' THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                ELSE 'Low'
            END as risk_level
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) as overdue_count
            FROM loan_schedule 
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
        ORDER BY lp.loan_id DESC
    ";
    $result = $conn->query($allLoansSql);
    $allLoans = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $allLoans[] = $row;
        }
    }

    // --- RETURN JSON ---
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'loan_status' => $statusData,
        'risk_breakdown' => $riskData,
        'loan_types' => $loanTypes,
        'loans' => $loans,
        'all_loans' => $allLoans,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}