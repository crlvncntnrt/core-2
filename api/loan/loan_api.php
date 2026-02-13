<?php
/**
 * Loan Portfolio API with Core1 Laravel Sync
 * Path: api/loan/loan_api.php
 * 
 * Syncs data from Core1 Laravel API: https://core1.microfinancial-1.com/api/loans
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
define('CORE1_LARAVEL_API', 'https://core1.microfinancial-1.com/api/loans');

// If Laravel API needs authentication, uncomment and set:
// define('CORE1_API_TOKEN', 'your_bearer_token_here');

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
        'error' => 'Database connection failed',
        'summary' => ['total_loans' => 0, 'active_loans' => 0, 'overdue_loans' => 0, 'defaulted_loans' => 0],
        'loans' => [],
        'loan_status' => ['labels' => [], 'values' => []],
        'risk_breakdown' => ['labels' => [], 'values' => []],
        'loan_types' => [],
        'pagination' => ['current_page' => 1, 'total_pages' => 1, 'limit' => 10, 'total_records' => 0]
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
// SYNC FROM CORE1 LARAVEL API
// ─────────────────────────────────────────────
function syncFromCore1Laravel($conn) {
    writeLog("🔄 Starting sync from Core1 Laravel API...", 'INFO');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, CORE1_LARAVEL_API);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    // If authentication needed, uncomment:
    // if (defined('CORE1_API_TOKEN')) {
    //     $headers[] = 'Authorization: Bearer ' . CORE1_API_TOKEN;
    // }
    
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
            'message' => "Failed to connect to Core1 (HTTP {$http_code})"
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
        writeLog("Response preview: " . substr($response, 0, 200), 'ERROR');
        return [
            'success' => false,
            'message' => 'Invalid JSON response from Core1'
        ];
    }
    
    // Handle different Laravel response structures
    $loans = [];
    
    if (is_array($data) && isset($data[0])) {
        // Direct array: [{loan1}, {loan2}, ...]
        $loans = $data;
    } elseif (isset($data['data']) && is_array($data['data'])) {
        // Laravel Resource: {"data": [{loan1}, ...]}
        $loans = $data['data'];
    } else {
        writeLog("⚠️ Unexpected response structure", 'WARN');
        writeLog("Response keys: " . implode(', ', array_keys($data)), 'WARN');
        return [
            'success' => false,
            'message' => 'Unexpected response structure from Core1'
        ];
    }
    
    writeLog("📦 Processing " . count($loans) . " loans from Core1", 'INFO');
    
    if (count($loans) === 0) {
        return [
            'success' => true,
            'message' => 'No loans to sync from Core1',
            'synced' => 0,
            'updated' => 0,
            'errors' => 0
        ];
    }
    
    $synced = 0;
    $updated = 0;
    $errors = 0;
    
    foreach ($loans as $loan) {
        try {
            // Extract loan data (based on the document you shared earlier)
            $loan_code = $loan['loan_code'] ?? null;
            $client_id = $loan['client_id'] ?? null;
            $loan_amount = $loan['loan_amount'] ?? 0;
            $loan_type = $loan['loan_type'] ?? 'Unknown';
            $loan_term = $loan['loan_term'] ?? 12;
            $interest_rate = $loan['interest_rate'] ?? 0;
            $disbursement_date = $loan['disbursement_date'] ?? date('Y-m-d');
            $status = $loan['status'] ?? 'pending';
            
            if (!$loan_code) {
                $errors++;
                writeLog("⚠️ Skipping loan without loan_code", 'WARN');
                continue;
            }
            
            // Calculate end date
            $start_date = $disbursement_date ?: date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($start_date . ' +' . $loan_term . ' months'));
            
            // Map status to standardized values
            $status_map = [
                'approved' => 'Approved',
                'active' => 'Active',
                'completed' => 'Completed',
                'defaulted' => 'Defaulted',
                'pending' => 'Pending',
                'rejected' => 'Rejected'
            ];
            $mapped_status = $status_map[strtolower($status)] ?? ucfirst($status);
            
            // Check if loan exists
            $check_stmt = $conn->prepare("SELECT loan_id FROM loan_portfolio WHERE loan_code = ?");
            $check_stmt->bind_param('s', $loan_code);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
            
            if ($exists) {
                // Update
                $stmt = $conn->prepare("
                    UPDATE loan_portfolio SET
                        member_id = ?,
                        loan_type = ?,
                        principal_amount = ?,
                        interest_rate = ?,
                        loan_term = ?,
                        start_date = ?,
                        end_date = ?,
                        status = ?
                    WHERE loan_code = ?
                ");
                
                $stmt->bind_param(
                    'isddissss',
                    $client_id,
                    $loan_type,
                    $loan_amount,
                    $interest_rate,
                    $loan_term,
                    $start_date,
                    $end_date,
                    $mapped_status,
                    $loan_code
                );
                
                if ($stmt->execute()) {
                    $updated++;
                } else {
                    $errors++;
                    writeLog("❌ Update failed for {$loan_code}: " . $stmt->error, 'ERROR');
                }
                $stmt->close();
                
            } else {
                // Insert
                $stmt = $conn->prepare("
                    INSERT INTO loan_portfolio 
                    (loan_code, member_id, loan_type, principal_amount, interest_rate, loan_term, start_date, end_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param(
                    'sisddisss',
                    $loan_code,
                    $client_id,
                    $loan_type,
                    $loan_amount,
                    $interest_rate,
                    $loan_term,
                    $start_date,
                    $end_date,
                    $mapped_status
                );
                
                if ($stmt->execute()) {
                    $synced++;
                } else {
                    $errors++;
                    writeLog("❌ Insert failed for {$loan_code}: " . $stmt->error, 'ERROR');
                }
                $stmt->close();
            }
            
        } catch (Exception $e) {
            $errors++;
            writeLog("❌ Exception processing loan: " . $e->getMessage(), 'ERROR');
        }
    }
    
    $message = "✅ Synced {$synced} new, updated {$updated} loans from Core1";
    if ($errors > 0) $message .= " ({$errors} errors)";
    
    writeLog($message, 'INFO');
    
    // ═══════════════════════════════════════════════════════════════
    // AUTO-GENERATE PAYMENT SCHEDULES FOR NEW LOANS
    // ═══════════════════════════════════════════════════════════════
    $schedules_generated = 0;
    
    if ($synced > 0) {
        writeLog("Auto-generating payment schedules for {$synced} new loans...", 'INFO');
        
        try {
            // Get loans without schedules
            $stmt = $conn->prepare("
                SELECT 
                    lp.loan_id,
                    lp.loan_code,
                    lp.principal_amount,
                    lp.interest_rate,
                    lp.loan_term,
                    lp.start_date
                FROM loan_portfolio lp
                WHERE lp.loan_code NOT IN (
                    SELECT DISTINCT loan_code FROM loan_schedule WHERE loan_code IS NOT NULL
                )
                AND lp.start_date IS NOT NULL
            ");
            
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($loan = $result->fetch_assoc()) {
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
                    
                    // Generate schedule
                    $current_date = new DateTime($start_date);
                    
                    for ($i = 1; $i <= $loan_term; $i++) {
                        $current_date->modify('+1 month');
                        $due_date = $current_date->format('Y-m-d');
                        
                        $today = new DateTime();
                        $status = ($today > $current_date) ? 'Overdue' : 'Pending';
                        
                        $insert_stmt = $conn->prepare("
                            INSERT INTO loan_schedule 
                            (loan_id, loan_code, payment_number, due_date, amount_due, amount_paid, status)
                            VALUES (?, ?, ?, ?, ?, 0.00, ?)
                        ");
                        
                        if ($insert_stmt) {
                            $insert_stmt->bind_param('isiss', $loan_id, $loan_code, $i, $due_date, $monthly_payment, $status);
                            if ($insert_stmt->execute()) {
                                $schedules_generated++;
                            }
                            $insert_stmt->close();
                        }
                    }
                }
                
                $stmt->close();
                
                if ($schedules_generated > 0) {
                    writeLog("Auto-generated {$schedules_generated} payment schedules", 'INFO');
                    $message .= " + {$schedules_generated} schedules generated";
                }
            }
        } catch (Exception $e) {
            writeLog("Schedule generation failed: " . $e->getMessage(), 'ERROR');
        }
    }
    
    return [
        'success' => true,
        'message' => $message,
        'synced' => $synced,
        'updated' => $updated,
        'errors' => $errors,
        'total_processed' => count($loans),
        'schedules_generated' => $schedules_generated
    ];
}

// ─────────────────────────────────────────────
// CALCULATE AMOUNT DUE
// ─────────────────────────────────────────────
function calculateTotalAmountDue($conn, $principal, $interestRate, $loanTerm, $loanCode = null) {
    try {
        $principal = (float) $principal;
        $interestRate = (float) $interestRate;
        $loanTerm = (int) $loanTerm;
        
        $timeInYears = $loanTerm / 12;
        $totalInterest = $principal * ($interestRate / 100) * $timeInYears;
        
        $totalPenalties = 0;
        if ($loanCode && $conn) {
            $tableCheck = @$conn->query("SHOW TABLES LIKE 'loan_penalties'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $stmt = @$conn->prepare("SELECT COALESCE(SUM(penalty_amount), 0) as total_penalties FROM loan_penalties WHERE loan_code = ?");
                if ($stmt) {
                    $stmt->bind_param('s', $loanCode);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $totalPenalties = (float) $row['total_penalties'];
                        }
                    }
                    $stmt->close();
                }
            }
        }
        
        return [
            'principal' => round($principal, 2),
            'total_interest' => round($totalInterest, 2),
            'total_penalties' => round($totalPenalties, 2),
            'total_amount_due' => round($principal + $totalInterest + $totalPenalties, 2)
        ];
        
    } catch (Exception $e) {
        writeLog("Error calculating amount: " . $e->getMessage(), 'ERROR');
        return [
            'principal' => round($principal, 2),
            'total_interest' => 0,
            'total_penalties' => 0,
            'total_amount_due' => round($principal, 2)
        ];
    }
}

// ----------------------------------------------------------------------
// Load TCPDF only when needed to avoid fatal errors if library is missing
// ----------------------------------------------------------------------
function loadTCPDF()
{
    if (class_exists('TCPDF')) return true;

    $paths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../../libs/tcpdf/tcpdf.php',
        __DIR__ . '/../libs/tcpdf/tcpdf.php',
        __DIR__ . '/libs/tcpdf/tcpdf.php'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            if (class_exists('TCPDF')) return true;
        }
    }
    return false;
}

/**
 * Send PDF as clean binary download to avoid corruption.
 */
function outputPdfDownload($pdf, string $filename): void
{
    // Clear any previous output buffers to avoid corrupting PDF binary
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Ensure no output happens before or after this
    ob_start();
    $binary = $pdf->Output($filename, 'S');
    ob_end_clean();

    if ($binary === '') {
        throw new Exception('Generated PDF content is empty.');
    }

    if (!headers_sent()) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    }

    echo $binary;
    exit;
}

// ─────────────────────────────────────────────
// GET PARAMETERS
// ─────────────────────────────────────────────
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$risk = isset($_GET['risk']) ? trim($_GET['risk']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$cardFilter = isset($_GET['cardFilter']) ? trim($_GET['cardFilter']) : 'all';
$force = isset($_GET['force']) ? intval($_GET['force']) : 0;
$offset = ($page - 1) * $limit;

// ─────────────────────────────────────────────
// INITIALIZE RESPONSE
// ─────────────────────────────────────────────
$response = [
    'success' => true,
    'message' => '',
    'summary' => ['total_loans' => 0, 'active_loans' => 0, 'overdue_loans' => 0, 'defaulted_loans' => 0],
    'loan_status' => ['labels' => [], 'values' => []],
    'risk_breakdown' => ['labels' => [], 'values' => []],
    'loans' => [],
    'loan_types' => [],
    'pagination' => ['current_page' => $page, 'total_pages' => 1, 'limit' => $limit, 'total_records' => 0]
];

try {
    // ═══════════════════════════════════════════════════════════════
    // HANDLE PDF EXPORT
    // ═══════════════════════════════════════════════════════════════
    $export = isset($_GET['export']) ? trim($_GET['export']) : '';
    $pdfPassword = isset($_GET['pdf_password']) ? trim($_GET['pdf_password']) : '';

    if ($export === 'pdf') {
        if (strlen($pdfPassword) < 6) {
            throw new Exception("PDF password must be at least 6 characters.");
        }

        if (!loadTCPDF()) {
            throw new Exception("TCPDF library not found.");
        }

        if (!class_exists('LoanExportPDF')) {
            class LoanExportPDF extends TCPDF
            {
                public function Header(): void
                {
                    $leftMargin = 10;
                    $top = 8;
                    $width = 277;
                    $this->SetFillColor(20, 83, 45);
                    $this->SetDrawColor(20, 83, 45);
                    $this->RoundedRect($leftMargin, $top, $width, 20, 2, '1111', 'FD');
                    $logoPath = __DIR__ . '/../../dist/img/logo.jpg';
                    if (is_file($logoPath)) {
                        $this->Image($logoPath, $leftMargin + 3, $top + 2, 16, 16, 'JPG');
                    }
                    $this->SetTextColor(255, 255, 255);
                    $this->SetXY($leftMargin + 22, $top + 4);
                    $this->SetFont('helvetica', 'B', 13);
                    $this->Cell(0, 6, 'Golden Horizons Cooperative', 0, 1, 'L');
                    $this->SetX($leftMargin + 22);
                    $this->SetFont('helvetica', '', 9);
                    $this->Cell(0, 5, 'Loan Portfolio & Risk Management Report', 0, 0, 'L');
                }

                public function Footer(): void
                {
                    $this->SetY(-12);
                    $this->SetFont('helvetica', 'I', 8);
                    $this->SetTextColor(20, 83, 45);
                    $this->Cell(0, 8, 'Confidential • Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
                }
            }
        }

        // Build Query for Export (No Limit)
        $where_conditions_pdf = [];
        if ($cardFilter === 'active') {
            $where_conditions_pdf[] = "l.status = 'Active'";
        } elseif ($cardFilter === 'overdue' && $table_exists) {
            $where_conditions_pdf[] = "EXISTS (SELECT 1 FROM loan_schedule ls WHERE (ls.loan_code = l.loan_code OR (ls.loan_code IS NULL AND ls.loan_id = l.loan_id)) AND (ls.status = 'Overdue' OR (ls.due_date < CURDATE() AND ls.amount_paid < ls.amount_due)))";
        } elseif ($cardFilter === 'defaulted') {
            $where_conditions_pdf[] = "l.status = 'Defaulted'";
        }
        if ($search !== '') {
            $s = $conn->real_escape_string($search);
            $where_conditions_pdf[] = "(l.loan_id LIKE '%$s%' OR l.loan_code LIKE '%$s%' OR m.full_name LIKE '%$s%' OR l.loan_type LIKE '%$s%')";
        }
        if ($status !== '') {
            $st = $conn->real_escape_string($status);
            $where_conditions_pdf[] = "l.status = '$st'";
        }
        if ($type !== '') {
            $ty = $conn->real_escape_string($type);
            $where_conditions_pdf[] = "l.loan_type = '$ty'";
        }
        $where_clause_pdf = !empty($where_conditions_pdf) ? 'WHERE ' . implode(' AND ', $where_conditions_pdf) : '';

        $sql_pdf = "SELECT l.*, COALESCE(m.full_name, 'Unknown') AS member_name FROM loan_portfolio l LEFT JOIN members m ON m.member_id = l.member_id $where_clause_pdf ORDER BY l.loan_id DESC";
        $result_pdf = $conn->query($sql_pdf);

        $pdf = new LoanExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Loan System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Loan Portfolio Report');

        $ownerPassword = md5(uniqid(mt_rand(), true));
        $pdf->SetProtection(['print', 'copy'], $pdfPassword, $ownerPassword, 0, null);

        $pdf->SetMargins(10, 32, 10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();

        $pdf->SetTextColor(34, 34, 34);
        $pdf->SetFillColor(220, 252, 231);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 7, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
        $pdf->Ln(3);

        $html = '
            <style>
                table { border-collapse: collapse; }
                th { background-color: #14532d; color: #ffffff; font-size: 8px; font-weight: bold; padding: 4px; border: 1px solid #166534; text-align: center; }
                td { font-size: 7px; color: #1f2937; padding: 4px; border: 1px solid #bbf7d0; }
                .row-light { background-color: #f0fdf4; }
                .row-alt { background-color: #dcfce7; }
                .center { text-align: center; }
            </style>
            <table width="100%" cellpadding="3">
                <thead>
                    <tr>
                        <th width="7%">Code</th>
                        <th width="15%">Member</th>
                        <th width="10%">Type</th>
                        <th width="10%">Amount</th>
                        <th width="5%">Rate</th>
                        <th width="5%">Term</th>
                        <th width="10%">Total Due</th>
                        <th width="10%">Start</th>
                        <th width="10%">End</th>
                        <th width="7%">Status</th>
                        <th width="11%">Risk</th>
                    </tr>
                </thead>
                <tbody>';

        $n = 0;
        if ($result_pdf && $result_pdf->num_rows > 0) {
            while ($row = $result_pdf->fetch_assoc()) {
                $rowClass = ($n % 2 === 0) ? 'row-alt' : 'row-light';
                
                // Risk Calc
                $overdue_count = 0;
                if ($table_exists) {
                   $lc = $row['loan_code'];
                   $li = $row['loan_id'];
                   if (!empty($lc)) {
                       $qc = "SELECT COUNT(*) AS overdue_count FROM loan_schedule WHERE loan_code = '$lc' AND (status = 'Overdue' OR (due_date < CURDATE() AND amount_paid < amount_due))";
                   } else {
                       $qc = "SELECT COUNT(*) AS overdue_count FROM loan_schedule WHERE loan_id = $li AND (status = 'Overdue' OR (due_date < CURDATE() AND amount_paid < amount_due))";
                   }
                   $rc = $conn->query($qc);
                   if ($rowc = $rc->fetch_assoc()) $overdue_count = (int)$rowc['overdue_count'];
                }
                $risk_level = 'Low';
                if ($row['status'] === 'Defaulted' || $overdue_count >= 2) $risk_level = 'High';
                elseif ($overdue_count === 1) $risk_level = 'Medium';
                
                $am = calculateTotalAmountDue($conn, $row['principal_amount'], $row['interest_rate'], $row['loan_term'], $row['loan_code']);

                $html .= '<tr class="' . $rowClass . '">'
                    . '<td class="center">' . ($row['loan_code'] ?: 'OLD-' . $row['loan_id']) . '</td>'
                    . '<td>' . htmlspecialchars($row['member_name'], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($row['loan_type'], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="center">₱' . number_format($row['principal_amount'], 2) . '</td>'
                    . '<td class="center">' . $row['interest_rate'] . '%</td>'
                    . '<td class="center">' . $row['loan_term'] . 'm</td>'
                    . '<td class="center">₱' . number_format($am['total_amount_due'], 2) . '</td>'
                    . '<td class="center">' . $row['start_date'] . '</td>'
                    . '<td class="center">' . $row['end_date'] . '</td>'
                    . '<td class="center">' . $row['status'] . '</td>'
                    . '<td class="center">' . $risk_level . '</td>'
                    . '</tr>';
                $n++;
            }
        } else {
            $html .= '<tr class="row-light"><td colspan="11" class="center">No records found.</td></tr>';
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        
        outputPdfDownload($pdf, 'loan_portfolio_report_' . date('Y-m-d_His') . '.pdf');
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLE FORCE SYNC
    // ═══════════════════════════════════════════════════════════════
    if ($force === 1) {
        $sync_result = syncFromCore1Laravel($conn);
        $response['sync'] = $sync_result;
        $response['message'] = $sync_result['message'];
        
        if (!$sync_result['success']) {
            $response['success'] = false;
        }
    }
    
    // ═══════════════════════════════════════════════════════════════
    // SUMMARY
    // ═══════════════════════════════════════════════════════════════
    $stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $response['summary']['total_loans'] = (int) $row['c'];
        }
        $stmt->close();
    }

    $stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio WHERE status = 'Active'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $response['summary']['active_loans'] = (int) $row['c'];
        }
        $stmt->close();
    }

    $stmt = @$conn->prepare("SELECT COUNT(*) AS c FROM loan_portfolio WHERE status = 'Defaulted'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $response['summary']['defaulted_loans'] = (int) $row['c'];
        }
        $stmt->close();
    }

    $table_exists = false;
    $check = @$conn->query("SHOW TABLES LIKE 'loan_schedule'");
    if ($check && $check->num_rows > 0) {
        $table_exists = true;
    }

    if ($table_exists) {
        $stmt = @$conn->prepare("
            SELECT COUNT(DISTINCT l.loan_id) AS c
            FROM loan_portfolio l
            JOIN loan_schedule s ON (s.loan_code = l.loan_code OR (s.loan_code IS NULL AND s.loan_id = l.loan_id))
            WHERE s.status = 'Overdue' OR (s.due_date < CURDATE() AND s.amount_paid < s.amount_due)
        ");
        
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $response['summary']['overdue_loans'] = (int) $row['c'];
            }
            $stmt->close();
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STATUS DISTRIBUTION
    // ═══════════════════════════════════════════════════════════════
    $stmt = @$conn->prepare("SELECT status, COUNT(*) AS total FROM loan_portfolio GROUP BY status ORDER BY total DESC");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response['loan_status']['labels'][] = $row['status'];
            $response['loan_status']['values'][] = (int) $row['total'];
        }
        
        $stmt->close();
    }

    // ═══════════════════════════════════════════════════════════════
    // LOAN TYPES
    // ═══════════════════════════════════════════════════════════════
    $stmt = @$conn->prepare("SELECT DISTINCT loan_type FROM loan_portfolio WHERE loan_type IS NOT NULL ORDER BY loan_type");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response['loan_types'][] = $row['loan_type'];
        }
        
        $stmt->close();
    }

    // ═══════════════════════════════════════════════════════════════
    // BUILD FILTERS
    // ═══════════════════════════════════════════════════════════════
    $where_conditions = [];
    $params = [];
    $types = '';

    if ($cardFilter === 'active') {
        $where_conditions[] = "l.status = 'Active'";
    } elseif ($cardFilter === 'overdue' && $table_exists) {
        $where_conditions[] = "EXISTS (
            SELECT 1 FROM loan_schedule ls 
            WHERE (ls.loan_code = l.loan_code OR (ls.loan_code IS NULL AND ls.loan_id = l.loan_id))
            AND (ls.status = 'Overdue' OR (ls.due_date < CURDATE() AND ls.amount_paid < ls.amount_due))
        )";
    } elseif ($cardFilter === 'defaulted') {
        $where_conditions[] = "l.status = 'Defaulted'";
    }

    if ($search !== '') {
        $where_conditions[] = "(l.loan_id LIKE ? OR l.loan_code LIKE ? OR m.full_name LIKE ? OR l.loan_type LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ssss';
    }

    if ($status !== '') {
        $where_conditions[] = "l.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if ($type !== '') {
        $where_conditions[] = "l.loan_type = ?";
        $params[] = $type;
        $types .= 's';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // ═══════════════════════════════════════════════════════════════
    // COUNT TOTAL
    // ═══════════════════════════════════════════════════════════════
    $count_sql = "SELECT COUNT(*) AS total FROM loan_portfolio l LEFT JOIN members m ON m.member_id = l.member_id $where_clause";
    
    $total_filtered = 0;
    
    if ($types) {
        $stmt = @$conn->prepare($count_sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $total_filtered = (int) $row['total'];
            }
            $stmt->close();
        }
    } else {
        $stmt = @$conn->prepare($count_sql);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $total_filtered = (int) $row['total'];
            }
            $stmt->close();
        }
    }

    $total_pages = max(1, ceil($total_filtered / $limit));
    $response['pagination']['total_pages'] = $total_pages;
    $response['pagination']['total_records'] = $total_filtered;

    // ═══════════════════════════════════════════════════════════════
    // FETCH LOANS
    // ═══════════════════════════════════════════════════════════════
    $fetch_sql = "
        SELECT 
            l.loan_id, l.loan_code, l.member_id, l.loan_type, l.principal_amount, 
            l.interest_rate, l.loan_term, l.start_date, l.end_date, l.status,
            COALESCE(m.full_name, 'Unknown') AS member_name
        FROM loan_portfolio l
        LEFT JOIN members m ON m.member_id = l.member_id
        $where_clause
        ORDER BY l.loan_id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = null;
    
    if ($types) {
        $stmt = @$conn->prepare($fetch_sql);
        if ($stmt) {
            $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
        }
    } else {
        $stmt = @$conn->prepare($fetch_sql);
        if ($stmt) {
            $stmt->bind_param('ii', $limit, $offset);
        }
    }

    $risk_counts = ['Low' => 0, 'Medium' => 0, 'High' => 0];
    $loans_processed = 0;

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($loan = $result->fetch_assoc()) {
            $loan_id = (int) $loan['loan_id'];
            $loan_code = $loan['loan_code'];
            
            $amounts = calculateTotalAmountDue($conn, (float) $loan['principal_amount'], (float) $loan['interest_rate'], (int) $loan['loan_term'], $loan_code);

            $overdue_count = 0;
            if ($table_exists) {
                if (!empty($loan_code)) {
                    $stmt2 = @$conn->prepare("SELECT COUNT(*) AS overdue_count FROM loan_schedule WHERE loan_code = ? AND (status = 'Overdue' OR (due_date < CURDATE() AND amount_paid < amount_due))");
                    if ($stmt2) {
                        $stmt2->bind_param("s", $loan_code);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();
                        if ($row2 = $res2->fetch_assoc()) {
                            $overdue_count = (int) $row2['overdue_count'];
                        }
                        $stmt2->close();
                    }
                } else {
                    $stmt2 = @$conn->prepare("SELECT COUNT(*) AS overdue_count FROM loan_schedule WHERE loan_id = ? AND (status = 'Overdue' OR (due_date < CURDATE() AND amount_paid < amount_due))");
                    if ($stmt2) {
                        $stmt2->bind_param("i", $loan_id);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();
                        if ($row2 = $res2->fetch_assoc()) {
                            $overdue_count = (int) $row2['overdue_count'];
                        }
                        $stmt2->close();
                    }
                }
            }

            $next_due = '-';
            if ($table_exists) {
                if (!empty($loan_code)) {
                    $stmt2 = @$conn->prepare("SELECT due_date FROM loan_schedule WHERE loan_code = ? AND status <> 'Paid' ORDER BY due_date ASC LIMIT 1");
                    if ($stmt2) {
                        $stmt2->bind_param("s", $loan_code);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();
                        if ($row2 = $res2->fetch_assoc()) {
                            $next_due = date('d M Y', strtotime($row2['due_date']));
                        }
                        $stmt2->close();
                    }
                } else {
                    $stmt2 = @$conn->prepare("SELECT due_date FROM loan_schedule WHERE loan_id = ? AND status <> 'Paid' ORDER BY due_date ASC LIMIT 1");
                    if ($stmt2) {
                        $stmt2->bind_param("i", $loan_id);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();
                        if ($row2 = $res2->fetch_assoc()) {
                            $next_due = date('d M Y', strtotime($row2['due_date']));
                        }
                        $stmt2->close();
                    }
                }
            }

            $risk_level = 'Low';
            if ($loan['status'] === 'Defaulted' || $overdue_count >= 2) {
                $risk_level = 'High';
            } elseif ($overdue_count === 1) {
                $risk_level = 'Medium';
            }
            
            $risk_counts[$risk_level]++;

            if ($risk !== '' && $risk_level !== $risk) {
                continue;
            }

            $response['loans'][] = [
                'loan_id' => $loan_id,
                'loan_code' => $loan_code,
                'member_id' => (int) $loan['member_id'],
                'member_name' => htmlspecialchars($loan['member_name'], ENT_QUOTES, 'UTF-8'),
                'loan_type' => htmlspecialchars($loan['loan_type'], ENT_QUOTES, 'UTF-8'),
                'principal_amount' => (float) $loan['principal_amount'],
                'interest_rate' => (float) $loan['interest_rate'],
                'loan_term' => (int) $loan['loan_term'],
                'start_date' => $loan['start_date'] ? date('d M Y', strtotime($loan['start_date'])) : '-',
                'end_date' => $loan['end_date'] ? date('d M Y', strtotime($loan['end_date'])) : '-',
                'status' => $loan['status'],
                'overdue_count' => $overdue_count,
                'risk_level' => $risk_level,
                'next_due' => $next_due,
                'total_interest' => $amounts['total_interest'],
                'total_penalties' => $amounts['total_penalties'],
                'total_amount_due' => $amounts['total_amount_due']
            ];
            
            $loans_processed++;
        }

        $stmt->close();
    }

    $response['risk_breakdown']['labels'] = array_keys($risk_counts);
    $response['risk_breakdown']['values'] = array_values($risk_counts);

    if (empty($response['message'])) {
        $response['message'] = "Successfully loaded {$loans_processed} loans";
    }

} catch (Exception $e) {
    writeLog("API Error: " . $e->getMessage(), 'ERROR');
    $response['success'] = false;
    $response['error'] = 'An error occurred';
    $response['message'] = 'Error: ' . $e->getMessage();
}

// ═══════════════════════════════════════════════════════════════
// SEND RESPONSE
// ═══════════════════════════════════════════════════════════════
while (@ob_end_clean());
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode($response, JSON_PRETTY_PRINT);
exit;