<?php
/**
 * ============================================================================
 * Core2 — Savings Sync API (PRODUCTION READY)
 * ============================================================================
 * Path: api/saving_monitoring/savings_sync_api.php
 * 
 * Syncs savings transactions from Core1 to Core2
 * Endpoint: https://core1.microfinancial-1.com/api/savings_transactions
 * 
 * TESTED WITH REAL DATA ✅
 * ============================================================================
 */

require_once(__DIR__ . '/../../initialize_coreT2.php');

// ─────────────────────────────────────────────
// CONFIG
// ─────────────────────────────────────────────
$CORE1_URL     = 'https://core1.microfinancial-1.com/api/savings_transactions';
$API_TOKEN     = 'super-key-123';
$SYNC_INTERVAL = 180; // 3 minutes
$SYSTEM_USER_ID = 0; // System user for synced transactions
// ─────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

$db   = new DBConnection();
$conn = $db->conn;

// ─────────────────────────────────────────────
// FUNCTIONS
// ─────────────────────────────────────────────

/**
 * Check if sync should run
 */
function shouldSync(mysqli $conn, int $interval): bool
{
    // Force sync if requested
    if (isset($_GET['force']) && $_GET['force'] === '1') {
        return true;
    }

    $stmt = $conn->prepare("
        SELECT synced_at FROM savings_sync_logs
        WHERE status = 'success'
        ORDER BY synced_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) return true;

    $lastSync = new DateTime($row['synced_at']);
    $now      = new DateTime();
    $elapsed  = $now->getTimestamp() - $lastSync->getTimestamp();

    return $elapsed >= $interval;
}

/**
 * Fetch data from Core1 API
 */
function fetchFromCore1(string $url, string $token): array
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $headers = ['Accept: application/json'];
    if (!empty($token)) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL Error: " . $error);
    }

    if ($httpCode !== 200) {
        throw new Exception("Core1 returned HTTP {$httpCode}");
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON from Core1: " . json_last_error_msg());
    }

    return $data ?? [];
}

/**
 * Ensure required tables exist
 */
function ensureTables(mysqli $conn): void
{
    // Sync logs table
    $conn->query("
        CREATE TABLE IF NOT EXISTS savings_sync_logs (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            total      INT NOT NULL,
            synced     INT NOT NULL,
            status     ENUM('success','error') NOT NULL,
            message    TEXT NULL,
            synced_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status_date (status, synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

/**
 * Calculate running balances for all transactions
 * Groups by savings_id (account) and calculates cumulative balance
 */
function calculateBalances(array $transactions): array
{
    if (empty($transactions)) {
        return [];
    }
    
    // Group transactions by savings_id (account ID)
    $byAccount = [];
    foreach ($transactions as $txn) {
        $accountId = (int) $txn['savings_id'];
        if (!isset($byAccount[$accountId])) {
            $byAccount[$accountId] = [];
        }
        $byAccount[$accountId][] = $txn;
    }
    
    // Calculate running balance per account
    $processed = [];
    foreach ($byAccount as $accountId => $txns) {
        // Sort by created_at timestamp, then by id
        usort($txns, function($a, $b) {
            $dateCompare = strtotime($a['created_at']) - strtotime($b['created_at']);
            if ($dateCompare === 0) {
                return (int)$a['id'] - (int)$b['id'];
            }
            return $dateCompare;
        });
        
        // Calculate cumulative balance
        $balance = 0.00;
        foreach ($txns as $txn) {
            $amount = (float) $txn['amount'];
            $type = strtolower(trim($txn['type']));
            
            if ($type === 'deposit') {
                $balance += $amount;
            } elseif ($type === 'withdrawal') {
                $balance -= $amount;
            }
            
            $txn['balance'] = round($balance, 2);
            $processed[] = $txn;
        }
    }
    
    return $processed;
}

/**
 * Save transactions to Core2 database
 * Uses INSERT ... ON DUPLICATE KEY UPDATE for idempotency
 */
function saveTransactions(mysqli $conn, array $transactions, int $systemUserId): array
{
    if (empty($transactions)) {
        return ['count' => 0, 'errors' => []];
    }
    
    // Calculate balances first
    $transactions = calculateBalances($transactions);
    
    $stmt = $conn->prepare("
        INSERT INTO savings (
            saving_id,
            member_id,
            transaction_date,
            transaction_type,
            amount,
            balance,
            recorded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            member_id = VALUES(member_id),
            transaction_date = VALUES(transaction_date),
            transaction_type = VALUES(transaction_type),
            amount = VALUES(amount),
            balance = VALUES(balance),
            recorded_by = VALUES(recorded_by)
    ");

    $count = 0;
    $errors = [];

    foreach ($transactions as $txn) {
        // Core1 structure:
        // - id = transaction ID (will be saving_id in Core2)
        // - savings_id = account/member ID (will be member_id in Core2)
        // - type = transaction type (lowercase)
        // - amount = amount
        // - created_at = timestamp
        
        $saving_id = (int) $txn['id'];           // Transaction ID
        $member_id = (int) $txn['savings_id'];   // Account/Member ID
        
        // Convert timestamp to date
        try {
            $dt = new DateTime($txn['created_at']);
            $transaction_date = $dt->format('Y-m-d');
        } catch (Exception $e) {
            $errors[] = "Invalid date for transaction #{$saving_id}: " . $e->getMessage();
            continue;
        }
        
        // Standardize transaction type (capitalize first letter)
        $type = ucfirst(strtolower(trim($txn['type'])));
        
        // Validate type
        if (!in_array($type, ['Deposit', 'Withdrawal'])) {
            $errors[] = "Invalid transaction type '{$txn['type']}' for #{$saving_id}";
            continue;
        }
        
        $amount = round((float) $txn['amount'], 2);
        $balance = round((float) $txn['balance'], 2);

        // Validate amount
        if ($amount <= 0) {
            $errors[] = "Invalid amount {$amount} for transaction #{$saving_id}";
            continue;
        }

        // Execute insert/update
        $stmt->bind_param('iissddi', 
            $saving_id,         // saving_id (PK)
            $member_id,         // member_id
            $transaction_date,  // transaction_date
            $type,              // transaction_type
            $amount,            // amount
            $balance,           // balance
            $systemUserId       // recorded_by (NEW!)
        );
        
        if ($stmt->execute()) {
            $count++;
        } else {
            $errors[] = "Failed to save transaction #{$saving_id}: " . $stmt->error;
        }
    }

    $stmt->close();
    
    return ['count' => $count, 'errors' => $errors];
}

/**
 * Log sync result to database
 */
function logSync(mysqli $conn, int $total, int $synced, string $status, string $msg): void
{
    $stmt = $conn->prepare("
        INSERT INTO savings_sync_logs (total, synced, status, message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('iiss', $total, $synced, $status, $msg);
    $stmt->execute();
    $stmt->close();
}

// ─────────────────────────────────────────────
// MAIN EXECUTION
// ─────────────────────────────────────────────
try {
    // Ensure required tables exist
    ensureTables($conn);

    // Check if we should sync
    if (!shouldSync($conn, $SYNC_INTERVAL)) {
        echo json_encode([
            'success' => true,
            'message' => 'Sync skipped. Last sync was within ' . $SYNC_INTERVAL . ' seconds. Use ?force=1 to override.',
            'skipped' => true,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Fetch transactions from Core1
    $transactions = fetchFromCore1($CORE1_URL, $API_TOKEN);

    if (empty($transactions)) {
        $msg = 'No transactions returned from Core1';
        logSync($conn, 0, 0, 'success', $msg);
        
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'fetched' => 0,
            'synced' => 0,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Save to Core2 database (NOW WITH SYSTEM USER ID!)
    $result = saveTransactions($conn, $transactions, $SYSTEM_USER_ID);

    // Build result message
    $msg = "Synced {$result['count']} of " . count($transactions) . " transactions";
    
    if (!empty($result['errors'])) {
        $msg .= " (" . count($result['errors']) . " errors)";
    }

    // Log the sync
    logSync($conn, count($transactions), $result['count'], 'success', $msg);

    // Return response
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'fetched' => count($transactions),
        'synced' => $result['count'],
        'errors' => $result['errors'],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log error
    $errorMsg = $e->getMessage();
    logSync($conn, 0, 0, 'error', $errorMsg);

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $errorMsg,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}