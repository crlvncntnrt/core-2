<?php
/**
 * Members/Clients Sync API - ERROR-FREE VERSION
 * Path: /api/members/sync_members.php
 * 
 * FIXED: Status field truncation error
 */

// ═══════════════════════════════════════════════════════════════
// CRITICAL: STOP ALL OUTPUT
// ═══════════════════════════════════════════════════════════════
while (@ob_end_clean());
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');

// ═══════════════════════════════════════════════════════════════
// CORE1 LARAVEL API CONFIGURATION
// ═══════════════════════════════════════════════════════════════
define('CORE1_CLIENTS_API', 'https://core1.microfinancial-1.com/api/clients');

// ═══════════════════════════════════════════════════════════════
// DATABASE CONNECTION
// ═══════════════════════════════════════════════════════════════
require_once(__DIR__ . '/../../initialize_coreT2.php');

if (!isset($conn) || $conn->connect_error) {
    while (@ob_end_clean());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ], JSON_PRETTY_PRINT));
}

$conn->set_charset('utf8mb4');

// ─────────────────────────────────────────────
// LOGGING
// ─────────────────────────────────────────────
function writeLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$level}][{$timestamp}] {$message}");
}

// ─────────────────────────────────────────────
// EXTRACT CLIENT NAME - FLEXIBLE
// ─────────────────────────────────────────────
function extractClientName($client) {
    // Try different name field combinations
    
    // Option 1: full_name field exists
    if (!empty($client['full_name'])) {
        return trim($client['full_name']);
    }
    
    // Option 2: name field exists
    if (!empty($client['name'])) {
        return trim($client['name']);
    }
    
    // Option 3: first_name + last_name
    $first_name = trim($client['first_name'] ?? '');
    $last_name = trim($client['last_name'] ?? '');
    
    if (!empty($first_name) || !empty($last_name)) {
        return trim($first_name . ' ' . $last_name);
    }
    
    // Option 4: firstName + lastName (camelCase)
    $first_name = trim($client['firstName'] ?? '');
    $last_name = trim($client['lastName'] ?? '');
    
    if (!empty($first_name) || !empty($last_name)) {
        return trim($first_name . ' ' . $last_name);
    }
    
    // Option 5: client_name
    if (!empty($client['client_name'])) {
        return trim($client['client_name']);
    }
    
    // Fallback: Use client ID
    $client_id = $client['id'] ?? $client['client_id'] ?? 'Unknown';
    return 'Client #' . $client_id;
}

// ─────────────────────────────────────────────
// NORMALIZE STATUS VALUE
// ─────────────────────────────────────────────
function normalizeStatus($status) {
    if (empty($status)) {
        return 'Active';
    }
    
    $status = trim(strtolower($status));
    
    // Map various status values to standard ones
    $statusMap = [
        'active' => 'Active',
        '1' => 'Active',
        'approved' => 'Active',
        'inactive' => 'Inactive',
        '0' => 'Inactive',
        'pending' => 'Pending',
        'suspended' => 'Suspended',
        'closed' => 'Closed',
        'deceased' => 'Deceased'
    ];
    
    return $statusMap[$status] ?? 'Active';
}

// ─────────────────────────────────────────────
// SYNC MEMBERS FROM CORE1 LARAVEL API
// ─────────────────────────────────────────────
function syncMembersFromCore1($conn) {
    writeLog("🔄 Starting members sync from Core1 Laravel API...", 'INFO');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, CORE1_CLIENTS_API);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: Core2-MemberSync/1.0'
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    writeLog("📡 Laravel API Response: HTTP {$http_code}", 'INFO');
    
    if ($http_code !== 200) {
        writeLog("❌ Laravel API Error: HTTP {$http_code} - {$curl_error}", 'ERROR');
        return [
            'success' => false,
            'message' => "Failed to connect to Core1 (HTTP {$http_code})",
            'error_details' => $curl_error
        ];
    }
    
    if (!$response) {
        writeLog("❌ Empty response from Laravel API", 'ERROR');
        return [
            'success' => false,
            'message' => 'Core1 returned empty response'
        ];
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        writeLog("❌ Invalid JSON from Laravel API", 'ERROR');
        writeLog("Raw response: " . substr($response, 0, 500), 'ERROR');
        return [
            'success' => false,
            'message' => 'Invalid JSON response from Core1'
        ];
    }
    
    // Handle different Laravel response structures
    $clients = [];
    
    // Structure 1: Direct array of clients
    if (is_array($data) && isset($data[0]) && is_array($data[0])) {
        $clients = $data;
        writeLog("📋 Response structure: Direct array", 'INFO');
    }
    // Structure 2: Wrapped in 'data' key
    elseif (isset($data['data']) && is_array($data['data'])) {
        $clients = $data['data'];
        writeLog("📋 Response structure: Wrapped in 'data'", 'INFO');
    }
    // Structure 3: Wrapped in 'clients' key
    elseif (isset($data['clients']) && is_array($data['clients'])) {
        $clients = $data['clients'];
        writeLog("📋 Response structure: Wrapped in 'clients'", 'INFO');
    }
    // Structure 4: Laravel pagination
    elseif (isset($data['data']) && isset($data['current_page'])) {
        $clients = $data['data'];
        writeLog("📋 Response structure: Laravel pagination", 'INFO');
    }
    else {
        writeLog("⚠️ Unexpected response structure", 'WARN');
        writeLog("Available keys: " . implode(', ', array_keys($data)), 'WARN');
        return [
            'success' => false,
            'message' => 'Unexpected response structure from Core1',
            'available_keys' => array_keys($data)
        ];
    }
    
    writeLog("📦 Processing " . count($clients) . " clients from Core1", 'INFO');
    
    if (count($clients) === 0) {
        return [
            'success' => true,
            'message' => 'No clients to sync from Core1',
            'synced' => 0,
            'updated' => 0,
            'errors' => 0
        ];
    }
    
    // Log first client structure for debugging
    if (isset($clients[0])) {
        writeLog("📝 Sample client fields: " . implode(', ', array_keys($clients[0])), 'INFO');
        $sample_name = extractClientName($clients[0]);
        writeLog("📝 Sample client name: " . $sample_name, 'INFO');
    }
    
    $synced = 0;
    $updated = 0;
    $errors = 0;
    $error_details = [];
    
    foreach ($clients as $index => $client) {
        try {
            // Extract client ID (handle different field names)
            $client_id = $client['id'] ?? $client['client_id'] ?? null;
            
            if (!$client_id) {
                $errors++;
                $error_msg = "Client at index {$index}: Missing ID";
                writeLog("⚠️ " . $error_msg, 'WARN');
                $error_details[] = $error_msg;
                continue;
            }
            
            // Extract name using flexible method
            $full_name = extractClientName($client);
            
            // Extract other fields (with fallbacks)
            $gender = $client['gender'] ?? $client['sex'] ?? null;
            $birth_date = $client['birthdate'] ?? $client['birth_date'] ?? $client['date_of_birth'] ?? null;
            $contact_no = $client['contact'] ?? $client['contact_no'] ?? $client['phone'] ?? $client['mobile'] ?? null;
            $address = $client['address'] ?? $client['full_address'] ?? null;
            $email = $client['email'] ?? null;
            
            // Normalize status - FIXED!
            $status = normalizeStatus($client['status'] ?? 'Active');
            
            // Format birthdate if needed
            if ($birth_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
                try {
                    $birth_date = date('Y-m-d', strtotime($birth_date));
                } catch (Exception $e) {
                    $birth_date = null;
                }
            }
            
            // Check if member exists
            $check_stmt = $conn->prepare("SELECT member_id FROM members WHERE member_id = ?");
            if (!$check_stmt) {
                $errors++;
                $error_msg = "Client {$client_id}: Prepare check failed - " . $conn->error;
                writeLog("❌ " . $error_msg, 'ERROR');
                $error_details[] = $error_msg;
                continue;
            }
            
            $check_stmt->bind_param('i', $client_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
            
            if ($exists) {
                // Update existing member
                $stmt = $conn->prepare("
                    UPDATE members SET
                        full_name = ?,
                        gender = ?,
                        birth_date = ?,
                        contact_no = ?,
                        address = ?,
                        email = ?,
                        status = ?,
                        last_score_update = NOW()
                    WHERE member_id = ?
                ");
                
                if (!$stmt) {
                    $errors++;
                    $error_msg = "Client {$client_id}: Prepare update failed - " . $conn->error;
                    writeLog("❌ " . $error_msg, 'ERROR');
                    $error_details[] = $error_msg;
                    continue;
                }
                
                $stmt->bind_param(
                    'sssssssi',
                    $full_name,
                    $gender,
                    $birth_date,
                    $contact_no,
                    $address,
                    $email,
                    $status,
                    $client_id
                );
                
                if ($stmt->execute()) {
                    $updated++;
                    writeLog("✅ Updated member {$client_id}: {$full_name} (Status: {$status})", 'INFO');
                } else {
                    $errors++;
                    $error_msg = "Client {$client_id}: Update execute failed - " . $stmt->error;
                    writeLog("❌ " . $error_msg, 'ERROR');
                    $error_details[] = $error_msg;
                }
                $stmt->close();
                
            } else {
                // Insert new member
                $member_code = $client['client_code'] ?? $client['code'] ?? 'MEM' . str_pad($client_id, 5, '0', STR_PAD_LEFT);
                
                // Get membership date
                $membership_date = null;
                if (isset($client['created_at'])) {
                    try {
                        $membership_date = date('Y-m-d', strtotime($client['created_at']));
                    } catch (Exception $e) {
                        $membership_date = date('Y-m-d');
                    }
                } elseif (isset($client['membership_date'])) {
                    try {
                        $membership_date = date('Y-m-d', strtotime($client['membership_date']));
                    } catch (Exception $e) {
                        $membership_date = date('Y-m-d');
                    }
                } else {
                    $membership_date = date('Y-m-d');
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO members 
                    (member_id, member_code, full_name, gender, birth_date, contact_no, address, email, membership_date, status, last_score_update)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                if (!$stmt) {
                    $errors++;
                    $error_msg = "Client {$client_id}: Prepare insert failed - " . $conn->error;
                    writeLog("❌ " . $error_msg, 'ERROR');
                    $error_details[] = $error_msg;
                    continue;
                }
                
                $stmt->bind_param(
                    'isssssssss',
                    $client_id,
                    $member_code,
                    $full_name,
                    $gender,
                    $birth_date,
                    $contact_no,
                    $address,
                    $email,
                    $membership_date,
                    $status
                );
                
                if ($stmt->execute()) {
                    $synced++;
                    writeLog("✅ Inserted new member {$client_id}: {$full_name} (Status: {$status})", 'INFO');
                } else {
                    $errors++;
                    $error_msg = "Client {$client_id}: Insert execute failed - " . $stmt->error;
                    writeLog("❌ " . $error_msg, 'ERROR');
                    $error_details[] = $error_msg;
                }
                $stmt->close();
            }
            
        } catch (Exception $e) {
            $errors++;
            $error_msg = "Exception at index {$index}: " . $e->getMessage();
            writeLog("❌ " . $error_msg, 'ERROR');
            $error_details[] = $error_msg;
        }
    }
    
    $message = "✅ Synced {$synced} new, updated {$updated} members from Core1";
    if ($errors > 0) {
        $message .= " ({$errors} errors)";
    }
    
    writeLog($message, 'INFO');
    
    $result = [
        'success' => true,
        'message' => $message,
        'synced' => $synced,
        'updated' => $updated,
        'errors' => $errors,
        'total_processed' => count($clients)
    ];
    
    // Include error details if there are errors (but limit to first 10)
    if ($errors > 0 && count($error_details) > 0) {
        $result['error_details'] = array_slice($error_details, 0, 10);
        if (count($error_details) > 10) {
            $result['error_details'][] = "... and " . (count($error_details) - 10) . " more errors";
        }
    }
    
    return $result;
}

// ═══════════════════════════════════════════════════════════════
// MAIN EXECUTION
// ═══════════════════════════════════════════════════════════════

$response = [
    'success' => true,
    'message' => '',
    'data' => []
];

try {
    $sync_result = syncMembersFromCore1($conn);
    $response = array_merge($response, $sync_result);
    
    // Also return current member count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM members");
    if ($count_stmt) {
        $count_stmt->execute();
        $result = $count_stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $response['total_members_now'] = (int) $row['total'];
        }
        $count_stmt->close();
    }
    
    // Return sample of recent members
    $sample_stmt = $conn->prepare("SELECT member_id, member_code, full_name, status FROM members ORDER BY member_id DESC LIMIT 5");
    if ($sample_stmt) {
        $sample_stmt->execute();
        $result = $sample_stmt->get_result();
        $samples = [];
        while ($row = $result->fetch_assoc()) {
            $samples[] = $row;
        }
        if (count($samples) > 0) {
            $response['sample_members'] = $samples;
        }
        $sample_stmt->close();
    }
    
} catch (Exception $e) {
    writeLog("API Error: " . $e->getMessage(), 'ERROR');
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

$conn->close();

// ═══════════════════════════════════════════════════════════════
// SEND RESPONSE
// ═══════════════════════════════════════════════════════════════
while (@ob_end_clean());
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;