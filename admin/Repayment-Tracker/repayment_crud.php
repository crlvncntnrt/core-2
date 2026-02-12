<?php
require_once('../../initialize_coreT2.php');
require_once('../inc/sess_auth.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $loan_id = intval($_POST['loan_id'] ?? 0);
        $member_id = intval($_POST['member_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $repayment_date = $_POST['repayment_date'] ?? '';
        $method = trim($_POST['method'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $created_by = $_SESSION['userdata']['id'] ?? 0;

        // Validation
        if ($loan_id <= 0 || $member_id <= 0 || $amount <= 0 || empty($repayment_date)) {
            $response['error'] = 'Invalid input data';
            echo json_encode($response);
            exit;
        }

        // Verify loan exists and belongs to member
        $stmt = $conn->prepare("SELECT loan_id, status FROM loan_portfolio WHERE loan_id=? AND member_id=?");
        $stmt->bind_param("ii", $loan_id, $member_id);
        $stmt->execute();
        $loan_result = $stmt->get_result();

        if ($loan_result->num_rows === 0) {
            $response['error'] = 'Loan not found or does not belong to this member';
            echo json_encode($response);
            exit;
        }
        $loan = $loan_result->fetch_assoc();
        $stmt->close();

        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert repayment record
            $stmt = $conn->prepare("INSERT INTO repayments (loan_id, member_id, amount, repayment_date, method, remarks, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iidsssi", $loan_id, $member_id, $amount, $repayment_date, $method, $remarks, $created_by);

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $stmt->close();

            // Update loan schedule - apply payment to oldest unpaid/partially paid schedules
            $remaining_amount = $amount;

            $stmt = $conn->prepare("
                SELECT schedule_id, amount_due, amount_paid 
                FROM loan_schedule 
                WHERE loan_id=? AND amount_paid < amount_due 
                ORDER BY due_date ASC
            ");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $schedules = $stmt->get_result();
            $stmt->close();

            while ($schedule = $schedules->fetch_assoc()) {
                if ($remaining_amount <= 0) break;

                $schedule_id = $schedule['schedule_id'];
                $amount_due = floatval($schedule['amount_due']);
                $amount_paid = floatval($schedule['amount_paid']);
                $balance = $amount_due - $amount_paid;

                // Calculate how much to apply to this schedule
                $payment_to_apply = min($remaining_amount, $balance);
                $new_amount_paid = $amount_paid + $payment_to_apply;
                $remaining_amount -= $payment_to_apply;

                // Determine new status
                $new_status = 'Pending';
                if ($new_amount_paid >= $amount_due) {
                    $new_status = 'Paid';
                } elseif ($new_amount_paid > 0) {
                    $new_status = 'Partial';
                }

                // Update schedule
                $stmt = $conn->prepare("
                    UPDATE loan_schedule 
                    SET amount_paid=?, payment_date=?, status=? 
                    WHERE schedule_id=?
                ");
                $stmt->bind_param("dssi", $new_amount_paid, $repayment_date, $new_status, $schedule_id);
                $stmt->execute();
                $stmt->close();
            }

            // Check if loan is fully paid
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unpaid_count 
                FROM loan_schedule 
                WHERE loan_id=? AND status != 'Paid'
            ");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $unpaid_count = intval($result['unpaid_count']);
            $stmt->close();

            // Update loan status if fully paid
            if ($unpaid_count === 0) {
                $stmt = $conn->prepare("UPDATE loan_portfolio SET status='Completed' WHERE loan_id=?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                $stmt->close();
            } elseif ($loan['status'] === 'Approved') {
                // Activate loan on first payment
                $stmt = $conn->prepare("UPDATE loan_portfolio SET status='Active' WHERE loan_id=?");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Repayment recorded successfully';
        } catch (Exception $e) {
            $conn->rollback();
            $response['error'] = 'Failed to record repayment: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $repayment_id = intval($_POST['repayment_id'] ?? 0);

        if ($repayment_id <= 0) {
            $response['error'] = 'Invalid repayment ID';
            echo json_encode($response);
            exit;
        }

        // Get repayment details before deleting
        $stmt = $conn->prepare("SELECT loan_id, amount FROM repayments WHERE repayment_id=?");
        $stmt->bind_param("i", $repayment_id);
        $stmt->execute();
        $repayment_result = $stmt->get_result();

        if ($repayment_result->num_rows === 0) {
            $response['error'] = 'Repayment not found';
            echo json_encode($response);
            exit;
        }

        $repayment = $repayment_result->fetch_assoc();
        $stmt->close();

        // Begin transaction
        $conn->begin_transaction();

        try {
            // Delete repayment
            $stmt = $conn->prepare("DELETE FROM repayments WHERE repayment_id=?");
            $stmt->bind_param("i", $repayment_id);

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $stmt->close();

            // Recalculate loan schedule payments
            // This is a simple approach - you might want to implement a more sophisticated reversal
            $loan_id = $repayment['loan_id'];

            // Reset all schedules and recalculate from remaining repayments
            $stmt = $conn->prepare("UPDATE loan_schedule SET amount_paid=0, payment_date=NULL, status='Pending' WHERE loan_id=?");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $stmt->close();

            // Get all remaining repayments for this loan
            $stmt = $conn->prepare("SELECT amount, repayment_date FROM repayments WHERE loan_id=? ORDER BY repayment_date ASC");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $repayments = $stmt->get_result();
            $stmt->close();

            // Reapply all repayments
            while ($rep = $repayments->fetch_assoc()) {
                $remaining_amount = floatval($rep['amount']);
                $rep_date = $rep['repayment_date'];

                $stmt = $conn->prepare("
                    SELECT schedule_id, amount_due, amount_paid 
                    FROM loan_schedule 
                    WHERE loan_id=? AND amount_paid < amount_due 
                    ORDER BY due_date ASC
                ");
                $stmt->bind_param("i", $loan_id);
                $stmt->execute();
                $schedules = $stmt->get_result();
                $stmt->close();

                while ($schedule = $schedules->fetch_assoc()) {
                    if ($remaining_amount <= 0) break;

                    $schedule_id = $schedule['schedule_id'];
                    $amount_due = floatval($schedule['amount_due']);
                    $amount_paid = floatval($schedule['amount_paid']);
                    $balance = $amount_due - $amount_paid;

                    $payment_to_apply = min($remaining_amount, $balance);
                    $new_amount_paid = $amount_paid + $payment_to_apply;
                    $remaining_amount -= $payment_to_apply;

                    $new_status = ($new_amount_paid >= $amount_due) ? 'Paid' : (($new_amount_paid > 0) ? 'Partial' : 'Pending');

                    $stmt = $conn->prepare("UPDATE loan_schedule SET amount_paid=?, payment_date=?, status=? WHERE schedule_id=?");
                    $stmt->bind_param("dssi", $new_amount_paid, $rep_date, $new_status, $schedule_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Update loan status
            $stmt = $conn->prepare("SELECT COUNT(*) as unpaid FROM loan_schedule WHERE loan_id=? AND status != 'Paid'");
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $unpaid = $stmt->get_result()->fetch_assoc()['unpaid'];
            $stmt->close();

            $new_loan_status = ($unpaid == 0) ? 'Completed' : 'Active';
            $stmt = $conn->prepare("UPDATE loan_portfolio SET status=? WHERE loan_id=?");
            $stmt->bind_param("si", $new_loan_status, $loan_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Repayment deleted successfully';
        } catch (Exception $e) {
            $conn->rollback();
            $response['error'] = 'Failed to delete repayment: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);
