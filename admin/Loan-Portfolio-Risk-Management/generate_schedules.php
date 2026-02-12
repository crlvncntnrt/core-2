<?php

/**
 * Generate Payment Schedules for Core2 Loans
 * Path: /admin/Loan-Portfolio-Risk-Management/generate_schedules.php
 */

require_once(__DIR__ . '/../../initialize_coreT2.php');
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'schedules_created' => 0,
    'loans_processed' => 0,
    'errors' => []
];

try {
    $sql = "
    SELECT 
        lp.loan_id,
        lp.loan_code,
        lp.principal_amount,
        lp.interest_rate,
        lp.loan_term,
        lp.start_date
    FROM loan_portfolio lp
    WHERE lp.loan_code NOT IN (SELECT DISTINCT loan_code FROM loan_schedule WHERE loan_code IS NOT NULL)
    AND lp.start_date IS NOT NULL
    ORDER BY lp.loan_id
";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $loans_to_process = [];
    while ($row = $result->fetch_assoc()) {
        $loans_to_process[] = $row;
    }

    if (count($loans_to_process) === 0) {
        $response['success'] = true;
        $response['message'] = 'All loans already have schedules';
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    $total_schedules = 0;
    $loans_processed = 0;

    foreach ($loans_to_process as $loan) {
        $loan_id = $loan['loan_id'];
        $loan_code = $loan['loan_code'];
        $principal = (float) $loan['principal_amount'];
        $interest_rate = (float) $loan['interest_rate'];
        $loan_term = (int) $loan['loan_term'];
        $start_date = $loan['start_date'];

        // Calculate monthly payment
        $time_in_years = $loan_term / 12;
        $total_interest = $principal * ($interest_rate / 100) * $time_in_years;
        $total_amount = $principal + $total_interest;
        $monthly_payment = $total_amount / $loan_term;

        // Generate schedule for each month
        $current_date = new DateTime($start_date);

        for ($i = 1; $i <= $loan_term; $i++) {
            $current_date->modify('+1 month');
            $due_date = $current_date->format('Y-m-d');

            $today = new DateTime();
            if ($today > $current_date) {
                $status = 'Overdue';
            } else {
                $status = 'Pending';
            }

            // WITH payment_number column
            $stmt = $conn->prepare("
                INSERT INTO loan_schedule 
                (loan_id, loan_code, payment_number, due_date, amount_due, amount_paid, status)
                VALUES (?, ?, ?, ?, ?, 0.00, ?)
            ");

            if (!$stmt) {
                $response['errors'][] = "Failed to prepare statement for {$loan_code}: " . $conn->error;
                continue;
            }

            $stmt->bind_param('isisds', $loan_id, $loan_code, $i, $due_date, $monthly_payment, $status);

            if ($stmt->execute()) {
                $total_schedules++;
            } else {
                $response['errors'][] = "Failed to insert schedule for {$loan_code} payment {$i}: " . $stmt->error;
            }

            $stmt->close();
        }

        $loans_processed++;
    }

    $response['success'] = true;
    $response['message'] = "Successfully generated {$total_schedules} payment schedules for {$loans_processed} loans";
    $response['schedules_created'] = $total_schedules;
    $response['loans_processed'] = $loans_processed;
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
