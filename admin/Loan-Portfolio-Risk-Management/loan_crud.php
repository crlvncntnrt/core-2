<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

try {
    // Only GET is allowed — this is view-only
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error'   => 'Only GET requests are allowed. This endpoint is view-only.'
        ]);
        exit;
    }

    // Accept BOTH loan_code (new) and loan_id (old) as identifiers
    $loan_code = isset($_GET['loan_code']) ? trim($_GET['loan_code']) : '';
    $loan_id   = isset($_GET['loan_id']) ? intval($_GET['loan_id']) : 0;

    if (empty($loan_code) && !$loan_id) {
        throw new Exception('Loan Code or Loan ID is required.');
    }

    // ─── Fetch loan details ───
    // Prioritize loan_code, but fall back to loan_id for old records without loan_code
    if (!empty($loan_code)) {
        $stmt = $conn->prepare("
            SELECT 
                l.loan_code,
                l.loan_id,
                l.member_id,
                COALESCE(m.full_name, 'Unknown') AS member_name,
                l.loan_type,
                l.principal_amount,
                l.interest_rate,
                l.loan_term,
                DATE_FORMAT(l.start_date, '%Y-%m-%d') AS start_date,
                DATE_FORMAT(l.end_date, '%Y-%m-%d')   AS end_date,
                l.status
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.loan_code = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $loan_code);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                l.loan_code,
                l.loan_id,
                l.member_id,
                COALESCE(m.full_name, 'Unknown') AS member_name,
                l.loan_type,
                l.principal_amount,
                l.interest_rate,
                l.loan_term,
                DATE_FORMAT(l.start_date, '%Y-%m-%d') AS start_date,
                DATE_FORMAT(l.end_date, '%Y-%m-%d')   AS end_date,
                l.status
            FROM loan_portfolio l
            LEFT JOIN members m ON m.member_id = l.member_id
            WHERE l.loan_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $loan_id);
    }
    
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan) {
        throw new Exception('Loan not found.');
    }

    // ─── Fetch payment schedules ───
    // Use loan_code if available, otherwise loan_id
    if (!empty($loan['loan_code'])) {
        $stmt = $conn->prepare("
            SELECT 
                schedule_id,
                DATE_FORMAT(due_date, '%Y-%m-%d')     AS due_date,
                amount_due,
                amount_paid,
                DATE_FORMAT(payment_date, '%Y-%m-%d') AS payment_date,
                status
            FROM loan_schedule
            WHERE loan_code = ?
            ORDER BY due_date ASC
        ");
        $stmt->bind_param('s', $loan['loan_code']);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                schedule_id,
                DATE_FORMAT(due_date, '%Y-%m-%d')     AS due_date,
                amount_due,
                amount_paid,
                DATE_FORMAT(payment_date, '%Y-%m-%d') AS payment_date,
                status
            FROM loan_schedule
            WHERE loan_id = ?
            ORDER BY due_date ASC
        ");
        $stmt->bind_param('i', $loan['loan_id']);
    }
    $stmt->execute();

    $schedules = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    // ─── Return response ───
    echo json_encode([
        'success'   => true,
        'loan'      => $loan,
        'schedules' => $schedules
    ], JSON_UNESCAPED_UNICODE);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
    error_log('loan_crud.php MySQLi Error: ' . $e->getMessage());

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    error_log('loan_crud.php Error: ' . $e->getMessage());
}