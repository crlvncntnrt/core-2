<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'success', 'columns' => [], 'rows' => []];

$type = $_GET['type'] ?? '';
$filter = $_GET['filter'] ?? '';

try {
    switch ($type) {

        // ---- MEMBERS ----
        case 'members':
            $sql = "SELECT member_id AS ID, full_name AS 'Full Name', contact_no AS 'Contact No', 
                           address AS Address, date_registered AS 'Date Joined', status AS Status
                    FROM members
                    WHERE status = 'Active'
                    ORDER BY full_name";
            break;

        // ---- LOANS ----
        case 'loans':
            $sql = "SELECT loan_id AS ID, l.member_id AS 'Member ID', m.full_name AS 'Member Name',
                           loan_type AS 'Loan Type', principal_amount AS 'Principal', 
                           status AS Status, date_applied AS 'Date Applied'
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    " . ($filter ? "WHERE l.status = '$filter'" : "") . "
                    ORDER BY l.loan_id DESC";
            break;

        // ---- SAVINGS ----
        case 'savings':
            $sql = "SELECT s.saving_id AS ID, m.full_name AS 'Member Name', 
                           s.amount AS Amount, s.transaction_type AS 'Type', 
                           s.date AS 'Date Recorded'
                    FROM savings s
                    LEFT JOIN members m ON m.member_id = s.member_id
                    ORDER BY s.date DESC";
            break;

        // ---- DISBURSEMENTS ----
        case 'disbursements':
            $sql = "SELECT d.disbursement_id AS ID, m.full_name AS 'Member Name', 
                           d.amount AS 'Amount', d.status AS 'Status', 
                           d.disbursement_date AS 'Date Released'
                    FROM disbursements d
                    LEFT JOIN members m ON m.member_id = d.member_id
                    " . ($filter ? "WHERE d.status = '$filter'" : "") . "
                    ORDER BY d.disbursement_date DESC";
            break;

        // ---- OVERDUE LOANS ----
        case 'overdue':
            $sql = "SELECT l.loan_id AS ID, m.full_name AS 'Member Name',
                           l.principal_amount AS 'Principal', 
                           l.date_applied AS 'Date Applied',
                           DATEDIFF(CURDATE(), ls.due_date) AS 'Days Overdue',
                           ls.due_date AS 'Due Date'
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    LEFT JOIN loan_schedules ls ON l.loan_id = ls.loan_id
                    WHERE ls.payment_status = 'Overdue' AND ls.due_date < CURDATE()
                    ORDER BY ls.due_date ASC";
            break;

        // ---- DEFAULTED LOANS ----
        case 'defaulted':
            $sql = "SELECT l.loan_id AS ID, m.full_name AS 'Member Name',
                           l.principal_amount AS 'Principal', 
                           l.date_applied AS 'Date Applied',
                           l.date_defaulted AS 'Date Defaulted'
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    WHERE l.status = 'Defaulted'
                    ORDER BY l.date_defaulted DESC";
            break;

        // ---- PENDING LOANS ----
        case 'pending':
            $sql = "SELECT l.loan_id AS ID, m.full_name AS 'Member Name',
                           l.principal_amount AS 'Principal', 
                           l.loan_type AS 'Loan Type',
                           l.date_applied AS 'Date Applied'
                    FROM loan_portfolio l
                    LEFT JOIN members m ON m.member_id = l.member_id
                    WHERE l.status = 'Pending'
                    ORDER BY l.date_applied DESC";
            break;

        // ---- TODAY'S REPAYMENTS ----
        case 'repayments':
            $today = date('Y-m-d');
            $sql = "SELECT r.repayment_id AS ID, m.full_name AS 'Member Name',
                           l.loan_id AS 'Loan ID',
                           r.amount AS 'Amount',
                           r.method AS 'Payment Method',
                           r.repayment_date AS 'Payment Date'
                    FROM loan_repayments r
                    LEFT JOIN loan_portfolio l ON r.loan_id = l.loan_id
                    LEFT JOIN members m ON l.member_id = m.member_id
                    WHERE DATE(r.repayment_date) = '$today'
                    ORDER BY r.repayment_date DESC";
            break;

        // ---- COMPLIANCE ----
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
            $sql = '';
            $response = ['status' => 'error', 'message' => 'Invalid type'];
    }

    if ($sql) {
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            // Get column names
            $response['columns'] = array_keys($res->fetch_assoc());
            $res->data_seek(0); // reset pointer
            
            // Get all rows
            while ($row = $res->fetch_assoc()) {
                $response['rows'][] = $row;
            }
        } else {
            $response['rows'] = [];
        }
    }

} catch (Throwable $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);