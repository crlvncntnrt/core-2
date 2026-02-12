<?php
/**
 * Repayment Sync API - Core1 to Core2
 * Path: /api/repayments/sync_repayments.php
 */

require_once(__DIR__ . '/../../initialize_coreT2.php');

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');

$CORE1_REPAYMENTS_URL = 'https://core1.microfinancial-1.com/api/repayments';
$CORE1_LOANS_URL = 'https://core1.microfinancial-1.com/api/loans';

function writeLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$level}][{$timestamp}] {$message}");
}

function fetchFromCore1($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    return json_decode($response, true);
}

function buildLoanMapping($conn, $core1LoansUrl) {
    $core1Loans = fetchFromCore1($core1LoansUrl);
    if (!$core1Loans) return [];
    
    $mapping = [];
    
    foreach ($core1Loans as $loan) {
        $core1LoanId = $loan['id'] ?? null;
        $loanCode = $loan['loan_code'] ?? null;
        
        if (!$core1LoanId) continue;
        
        // Try by loan_code first
        if ($loanCode) {
            $stmt = $conn->prepare("SELECT loan_id FROM loan_portfolio WHERE loan_code = ? LIMIT 1");
            $stmt->bind_param('s', $loanCode);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $mapping[$core1LoanId] = $row['loan_id'];
                $stmt->close();
                continue;
            }
            $stmt->close();
        }
        
        // Try by loan_id
        $stmt = $conn->prepare("SELECT loan_id FROM loan_portfolio WHERE loan_id = ? LIMIT 1");
        $stmt->bind_param('i', $core1LoanId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $mapping[$core1LoanId] = $row['loan_id'];
        }
        $stmt->close();
    }
    
    writeLog("Mapped " . count($mapping) . " loans", 'INFO');
    return $mapping;
}

function updateLoanSchedule($conn, $loanId, $dueDate, $paidAmount, $paymentDate) {
    $stmt = $conn->prepare("SELECT loan_code FROM loan_portfolio WHERE loan_id = ? LIMIT 1");
    $stmt->bind_param('i', $loanId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) return false;
    
    $loanCode = $row['loan_code'];
    
    // Update schedule
    $stmt = $conn->prepare("
        UPDATE loan_schedule 
        SET 
            amount_paid = amount_paid + ?,
            balance = GREATEST(0, amount_due - (amount_paid + ?)),
            payment_date = ?,
            status = IF(amount_due - (amount_paid + ?) <= 0.01, 'Paid', status)
        WHERE loan_code = ? AND due_date = ?
        LIMIT 1
    ");
    
    $stmt->bind_param('ddsdss', $paidAmount, $paidAmount, $paymentDate, $paidAmount, $loanCode, $dueDate);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    return $affected > 0;
}

try {
    writeLog("Starting repayment sync...", 'INFO');
    
    // Build loan mapping
    $loanMapping = buildLoanMapping($conn, $CORE1_LOANS_URL);
    
    if (empty($loanMapping)) {
        throw new Exception('No loan mapping found');
    }
    
    // Fetch repayments
    $repayments = fetchFromCore1($CORE1_REPAYMENTS_URL);
    
    if (!$repayments) {
        throw new Exception('Failed to fetch repayments from Core1');
    }
    
    writeLog("Processing " . count($repayments) . " repayments", 'INFO');
    
    $synced = 0;
    $schedulesUpdated = 0;
    $skipped = 0;
    $errors = [];
    
    // Check if core1_repayment_id exists
    $hasCore1Id = false;
    $result = $conn->query("SHOW COLUMNS FROM repayments LIKE 'core1_repayment_id'");
    if ($result && $result->num_rows > 0) {
        $hasCore1Id = true;
    }
    
    foreach ($repayments as $repayment) {
        $repaymentId = $repayment['id'] ?? null;
        $core1LoanId = $repayment['loan_id'] ?? null;
        $status = strtolower($repayment['status'] ?? 'pending');
        
        // Only sync paid repayments
        if ($status !== 'paid') {
            $skipped++;
            continue;
        }
        
        if (!isset($loanMapping[$core1LoanId])) {
            $skipped++;
            continue;
        }
        
        $core2LoanId = $loanMapping[$core1LoanId];
        $paidAmount = floatval($repayment['paid_amount'] ?? 0);
        $dueDate = $repayment['due_date'] ?? null;
        $repaymentDate = $repayment['repayment_date'] ?? null;
        
        if ($paidAmount <= 0 || !$repaymentDate) {
            $skipped++;
            continue;
        }
        
        try {
            $dt = new DateTime($repaymentDate);
            $mysqlDate = $dt->format('Y-m-d');
        } catch (Exception $e) {
            $skipped++;
            continue;
        }
        
        // Insert repayment record
        if ($hasCore1Id) {
            $stmt = $conn->prepare("
                INSERT INTO repayments (
                    core1_repayment_id, loan_id, amount, repayment_date, method, remarks, created_by, created_at
                ) VALUES (?, ?, ?, ?, 'Cash', ?, 1, CURDATE())
                ON DUPLICATE KEY UPDATE
                    amount = VALUES(amount),
                    repayment_date = VALUES(repayment_date)
            ");
            $remarks = "Synced from Core1 (ID: {$repaymentId})";
            $stmt->bind_param('iidss', $repaymentId, $core2LoanId, $paidAmount, $mysqlDate, $remarks);
        } else {
            // Check if already exists
            $checkStmt = $conn->prepare("SELECT repayment_id FROM repayments WHERE loan_id = ? AND amount = ? AND repayment_date = ? LIMIT 1");
            $checkStmt->bind_param('ids', $core2LoanId, $paidAmount, $mysqlDate);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();
            
            if ($exists) {
                $skipped++;
                continue;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO repayments (
                    loan_id, amount, repayment_date, method, remarks, created_by, created_at
                ) VALUES (?, ?, ?, 'Cash', ?, 1, CURDATE())
            ");
            $remarks = "Synced from Core1 (ID: {$repaymentId})";
            $stmt->bind_param('idss', $core2LoanId, $paidAmount, $mysqlDate, $remarks);
        }
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $synced++;
        }
        $stmt->close();
        
        // Update schedule
        if ($dueDate && updateLoanSchedule($conn, $core2LoanId, $dueDate, $paidAmount, $mysqlDate)) {
            $schedulesUpdated++;
        }
    }
    
    $message = "✅ Synced {$synced} repayments, updated {$schedulesUpdated} schedules, skipped {$skipped}";
    writeLog($message, 'INFO');
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'total' => count($repayments),
        'synced' => $synced,
        'schedules_updated' => $schedulesUpdated,
        'skipped' => $skipped,
        'loan_mapping_count' => count($loanMapping)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    writeLog("Error: " . $e->getMessage(), 'ERROR');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}