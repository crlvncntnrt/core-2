<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');

try {
    // Create database connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=coret2_db;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=repayments_report_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');

    // Write CSV header row
    fputcsv($output, [
        'Repayment ID',
        'Loan ID',
        'Member Name',
        'Amount',
        'Repayment Date',
        'Payment Method',
        'Remarks',
        'Created By',
        'Overdue Count',
        'Risk Level',
        'Next Due Date',
        'Created At'
    ]);

    // Query all repayment records (with joined member & loan info)
    $sql = "
        SELECT 
            r.repayment_id,
            r.loan_id,
            COALESCE(m.full_name, 'N/A') AS member_name,
            r.amount,
            r.repayment_date,
            r.method,
            r.remarks,
            r.created_by_name,
            r.overdue_count,
            r.risk_level,
            r.next_due,
            r.created_at
        FROM repayments r
        LEFT JOIN loan_portfolio lp ON lp.loan_id = r.loan_id
        LEFT JOIN members m ON m.member_id = lp.member_id
        ORDER BY r.repayment_date DESC
    ";

    $stmt = $pdo->query($sql);

    // Write each row to CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo "Error exporting CSV: " . $e->getMessage();
}
