<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../initialize_coreT2.php');

// Header will be set per action

// CHECK AUTHENTICATION - Your system uses $_SESSION['userdata']
if (!isset($_SESSION['userdata']) || empty($_SESSION['userdata'])) {
    error_log("disbursement_action.php - Authentication failed - no userdata in session");
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error', 
        'msg' => 'Unauthorized - Please login again'
    ]);
    exit;
}

// Update last activity time (for your session timeout system)
if (isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

// Check database connection
if (!isset($conn)) {
    error_log("disbursement_action.php - Database connection not found");
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'msg' => 'Database connection failed'
    ]);
    exit;
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

try {
    // Check if it's a PDF export request (GET)
    if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
        $fundFilter = isset($_GET['fund']) ? trim($_GET['fund']) : '';
        $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : '';
        $cardFilter = isset($_GET['cardFilter']) ? trim($_GET['cardFilter']) : 'all';
        $pdfPassword = isset($_GET['pdf_password']) ? trim($_GET['pdf_password']) : '';

        if (strlen($pdfPassword) < 6) {
            throw new Exception("PDF password must be at least 6 characters.");
        }

        if (!loadTCPDF()) {
            throw new Exception("TCPDF library not found.");
        }

        if (!class_exists('DisbursementExportPDF')) {
            class DisbursementExportPDF extends TCPDF
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
                    $this->Cell(0, 5, 'Disbursement Tracker Report', 0, 0, 'L');
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

        // Build WHERE
        $whereSql = "WHERE 1=1";
        if ($search) {
            $s = $conn->real_escape_string($search);
            $whereSql .= " AND (d.disbursement_id LIKE '%$s%' OR d.loan_id LIKE '%$s%' OR m.full_name LIKE '%$s%' OR d.fund_source LIKE '%$s%')";
        }
        if ($statusFilter) {
            $st = $conn->real_escape_string($statusFilter);
            $whereSql .= " AND d.status = '$st'";
        }
        if ($fundFilter) {
            $f = $conn->real_escape_string($fundFilter);
            $whereSql .= " AND d.fund_source = '$f'";
        }
        if ($dateFilter) {
            $dt = $conn->real_escape_string($dateFilter);
            $whereSql .= " AND d.disbursement_date = '$dt'";
        }
        if ($cardFilter !== 'all') {
            $c = $conn->real_escape_string($cardFilter);
            $whereSql .= " AND d.status = '$c'";
        }

        $sql = "SELECT d.*, m.full_name as member_name, u.full_name as approved_by_name 
                FROM disbursements d 
                LEFT JOIN members m ON d.member_id = m.member_id 
                LEFT JOIN users u ON d.approved_by = u.user_id 
                $whereSql ORDER BY d.disbursement_date DESC";
        $result = $conn->query($sql);

        $pdf = new DisbursementExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Microfinance EIS');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Disbursement Tracker Report');

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
                th { background-color: #14532d; color: #ffffff; font-size: 9px; font-weight: bold; padding: 6px; border: 1px solid #166534; text-align: center; }
                td { font-size: 8px; color: #1f2937; padding: 5px; border: 1px solid #bbf7d0; }
                .row-light { background-color: #f0fdf4; }
                .row-alt { background-color: #dcfce7; }
                .center { text-align: center; }
            </style>
            <table width="100%" cellpadding="4">
                <thead>
                    <tr>
                        <th width="8%">ID</th>
                        <th width="10%">Loan ID</th>
                        <th width="20%">Member</th>
                        <th width="12%">Date</th>
                        <th width="12%">Amount</th>
                        <th width="14%">Fund Source</th>
                        <th width="14%">Approved By</th>
                        <th width="10%">Status</th>
                    </tr>
                </thead>
                <tbody>';

        $n = 0;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rowClass = ($n % 2 === 0) ? 'row-alt' : 'row-light';
                $html .= '<tr class="' . $rowClass . '">'
                    . '<td class="center">' . $row['disbursement_id'] . '</td>'
                    . '<td class="center">' . $row['loan_id'] . '</td>'
                    . '<td>' . htmlspecialchars($row['member_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="center">' . $row['disbursement_date'] . '</td>'
                    . '<td class="center">₱' . number_format($row['amount'], 2) . '</td>'
                    . '<td>' . htmlspecialchars($row['fund_source'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($row['approved_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="center">' . $row['status'] . '</td>'
                    . '</tr>';
                $n++;
            }
        } else {
            $html .= '<tr class="row-light"><td colspan="8" class="center">No records found.</td></tr>';
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        
        outputPdfDownload($pdf, 'disbursement_tracker_' . date('Y-m-d_His') . '.pdf');
    }

    // Check if it's a CSV export request (GET)
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        while (ob_get_level() > 0) ob_end_clean();
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $fund = $_GET['fund'] ?? '';
        $date = $_GET['date'] ?? '';
        $cardFilter = $_GET['cardFilter'] ?? 'all';
        $exportPassword = trim($_GET['pdf_password'] ?? '');

        // Use the same filtering logic as PDF
        $where = ["1=1"];
        $params = [];
        $types = "";

        if ($search !== "") {
            $where[] = "(d.disbursement_id LIKE ? OR d.loan_id LIKE ? OR m.full_name LIKE ? OR d.fund_source LIKE ?)";
            $s = "%$search%";
            $params = array_merge($params, [$s, $s, $s, $s]);
            $types .= "ssss";
        }
        if ($status !== "") {
            $where[] = "d.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        if ($fund !== "") {
            $where[] = "d.fund_source = ?";
            $params[] = $fund;
            $types .= "s";
        }
        if ($date !== "") {
            $where[] = "d.disbursement_date = ?";
            $params[] = $date;
            $types .= "s";
        }
        if ($cardFilter !== "all" && $cardFilter !== "") {
            if ($cardFilter === "released") $where[] = "d.status = 'Released'";
            elseif ($cardFilter === "pending") $where[] = "d.status = 'Pending'";
        }

        $sql = "SELECT d.*, m.full_name as member_name, u.full_name as approved_by_name 
                FROM disbursements d 
                LEFT JOIN members m ON d.member_id = m.member_id 
                LEFT JOIN users u ON d.approved_by = u.user_id 
                WHERE " . implode(" AND ", $where) . " 
                ORDER BY d.disbursement_id DESC";

        $stmt = $conn->prepare($sql);
        if ($types !== "") $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $filename_base = 'disbursements_export_' . date('Y-m-d_His');
        $csv_filename = $filename_base . '.csv';

        // Create CSV in memory
        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
        fputcsv($output, ['ID', 'Loan ID', 'Member', 'Date', 'Amount', 'Fund Source', 'Approved By', 'Status', 'Remarks']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['disbursement_id'],
                $row['loan_id'],
                $row['member_name'] ?? 'N/A',
                $row['disbursement_date'],
                $row['amount'],
                $row['fund_source'] ?? '-',
                $row['approved_by_name'] ?? '-',
                $row['status'],
                $row['remarks'] ?? ''
            ]);
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        $stmt->close();

        if ($exportPassword !== '') {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                $zip_filename = $filename_base . '.zip';
                $temp_file = tempnam(sys_get_temp_dir(), 'zip');
                if ($zip->open($temp_file, ZipArchive::CREATE) === TRUE) {
                    $zip->addFromString($csv_filename, $csv_content);
                    if (method_exists($zip, 'setEncryptionName')) {
                        $zip->setEncryptionName($csv_filename, ZipArchive::EM_AES_256, $exportPassword);
                    }
                    $zip->close();
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
                    header('Content-Length: ' . filesize($temp_file));
                    readfile($temp_file);
                    unlink($temp_file);
                    exit;
                }
            }
        }

        // Standard CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
        echo $csv_content;
        exit;
    }

    $action = $_POST['action'] ?? '';
    $disbursementId = $_POST['id'] ?? '';
    
    // Get user ID from your session structure
    $userId = $_SESSION['userdata']['user_id'] ?? 0;
    $userName = $_SESSION['userdata']['full_name'] ?? 'Unknown User';
    
    error_log("disbursement_action.php - Action: {$action}, ID: {$disbursementId}, User: {$userId} ({$userName})");
    
    if (empty($disbursementId)) {
        throw new Exception('Disbursement ID is required');
    }
    
    if ($action === 'approve') {
        // Start transaction for data integrity
        $conn->begin_transaction();
        
        try {
            // Check if disbursement exists and is pending
            $checkStmt = $conn->prepare("SELECT status, loan_id FROM disbursements WHERE disbursement_id = ?");
            if (!$checkStmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $checkStmt->bind_param('s', $disbursementId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                $checkStmt->close();
                throw new Exception('Disbursement not found');
            }
            
            $disbursement = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            if ($disbursement['status'] !== 'Pending') {
                throw new Exception('Only pending disbursements can be approved');
            }
            
            // Check if approved_by column exists
            $columnsResult = $conn->query("SHOW COLUMNS FROM disbursements LIKE 'approved_by'");
            
            if ($columnsResult && $columnsResult->num_rows > 0) {
                // Column exists, update with approved_by
                $updateStmt = $conn->prepare("UPDATE disbursements 
                                             SET status = 'Released', 
                                                 approved_by = ?
                                             WHERE disbursement_id = ?");
                if (!$updateStmt) {
                    throw new Exception("Prepare update failed: " . $conn->error);
                }
                $updateStmt->bind_param('is', $userId, $disbursementId);
            } else {
                // Column doesn't exist, update without approved_by
                $updateStmt = $conn->prepare("UPDATE disbursements 
                                             SET status = 'Released'
                                             WHERE disbursement_id = ?");
                if (!$updateStmt) {
                    throw new Exception("Prepare update failed: " . $conn->error);
                }
                $updateStmt->bind_param('s', $disbursementId);
            }
            
            $result = $updateStmt->execute();
            
            if (!$result) {
                error_log("disbursement_action.php - Update failed: " . $conn->error);
                throw new Exception('Database update failed: ' . $conn->error);
            }
            
            $affectedRows = $updateStmt->affected_rows;
            $updateStmt->close();
            
            if ($affectedRows === 0) {
                throw new Exception('No rows were updated');
            }
            
            // Log the action using your audit system
            if (function_exists('log_audit')) {
                log_audit(
                    $userId,
                    'Approve Disbursement',
                    'Disbursement Tracker',
                    $disbursementId,
                    "User {$userName} approved disbursement #{$disbursementId} for loan #{$disbursement['loan_id']}"
                );
            }
            
            // Commit transaction
            $conn->commit();
            
            error_log("disbursement_action.php - Disbursement {$disbursementId} approved successfully by user {$userId}");
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'ok', 
                'msg' => 'Disbursement approved successfully'
            ]);
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            throw $e;
        }
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("disbursement_action.php - Error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error', 
        'msg' => $e->getMessage()
    ]);
}
?>