<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');

$type = $_GET['type'] ?? '';
$filter = $_GET['filter'] ?? '';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="dashboard_export_' . $type . '_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');

try {
    switch ($type) {

        // ==== MEMBERS ====
        case 'members':
            $sql = "SELECT member_id AS ID, full_name AS 'Full Name', contact_no AS 'Contact No',
                           address AS Address, date_registered AS 'Date Joined', status AS Status
                    FROM members
                    WHERE status='Active'
                    ORDER BY full_name";
            break;

        // ==== LOANS ====
        case 'loans':
            $sql = "SELECT l.loan_id AS ID, m.full_name AS 'Member Name', l.loan_type AS 'Loan Type',
                           l.principal_amount AS 'Principal Amount', l.status AS Status, 
                           l.date_applied AS 'Date Applied'
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    ORDER BY l.loan_id DESC";
            break;

        // ==== SAVINGS ====
        case 'savings':
            $sql = "SELECT s.saving_id AS ID, m.full_name AS 'Member Name', s.amount AS Amount,
                           s.transaction_type AS 'Transaction Type', s.date AS 'Date'
                    FROM savings s
                    LEFT JOIN members m ON m.member_id = s.member_id
                    ORDER BY s.date DESC";
            break;

        // ==== DISBURSEMENTS ====
        case 'disbursements':
            $sql = "SELECT d.disbursement_id AS ID, m.full_name AS 'Member Name', 
                           d.amount AS 'Amount', d.status AS 'Status', 
                           d.disbursement_date AS 'Date Released'
                    FROM disbursements d
                    LEFT JOIN members m ON m.member_id = d.member_id
                    ORDER BY d.disbursement_date DESC";
            break;

        // ==== COMPLIANCE ====
        case 'compliance':
            $sql = "SELECT c.compliance_id AS ID, a.module_name AS 'Module', 
                           c.description AS 'Description', 
                           c.compliance_status AS 'Status', 
                           c.review_date AS 'Reviewed On'
                    FROM compliance_logs c
                    LEFT JOIN audit_trail a ON a.audit_id = c.audit_id
                    ORDER BY c.review_date DESC";
            break;

        default:
            fwrite($output, "Invalid export type");
            exit;
    }

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $headers = array_keys($res->fetch_assoc());
        fputcsv($output, $headers);
        $res->data_seek(0);
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, $row);
        }
    } else {
        fputcsv($output, ['No records found']);
    }

} catch (Throwable $e) {
    fputcsv($output, ['Error', $e->getMessage()]);
}

fclose($output);
exit;
