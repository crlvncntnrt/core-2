<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once __DIR__ . '/../inc/check_auth.php';

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
    // ✅ Use the existing MySQLi connection from initialize_coreT2.php
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
    }

    // Get parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $riskFilter = isset($_GET['risk']) ? trim($_GET['risk']) : '';
    $typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';
    $cardFilter = isset($_GET['cardFilter']) ? trim($_GET['cardFilter']) : 'all';
    $export = isset($_GET['export']) ? trim($_GET['export']) : '';
    $pdfPassword = isset($_GET['pdf_password']) ? trim($_GET['pdf_password']) : '';

    $offset = ($page - 1) * $limit;

    // --- BUILD WHERE CLAUSES FOR FILTERING ---
    $whereClauses = [];
    $searchParam = '';
    $statusParam = '';
    $typeParam = '';

    // Search filter
    if ($search !== '') {
        $searchParam = $conn->real_escape_string($search);
        $whereClauses[] = "(lp.loan_id LIKE '%$searchParam%' OR m.full_name LIKE '%$searchParam%' OR lp.loan_type LIKE '%$searchParam%')";
    }

    // Status filter
    if ($statusFilter !== '') {
        $statusParam = $conn->real_escape_string($statusFilter);
        $whereClauses[] = "lp.status = '$statusParam'";
    }

    // Type filter
    if ($typeFilter !== '') {
        $typeParam = $conn->real_escape_string($typeFilter);
        $whereClauses[] = "lp.loan_type = '$typeParam'";
    }

    // Risk filter
    if ($riskFilter !== '') {
        if ($riskFilter === 'Low') {
            $whereClauses[] = "overdue_count = 0 AND lp.status IN ('Active', 'Approved')";
        } elseif ($riskFilter === 'Medium') {
            $whereClauses[] = "overdue_count BETWEEN 1 AND 2";
        } elseif ($riskFilter === 'High') {
            $whereClauses[] = "(overdue_count >= 3 OR lp.status = 'Defaulted')";
        }
    }

    // Card filter
    if ($cardFilter !== 'all') {
        if ($cardFilter === 'active') {
            $whereClauses[] = "lp.status = 'Active'";
        } elseif ($cardFilter === 'overdue') {
            $whereClauses[] = "overdue_count > 0";
        } elseif ($cardFilter === 'at_risk') {
            $whereClauses[] = "(overdue_count >= 3 OR lp.status = 'Defaulted')";
        }
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    // --- HANDLE PDF EXPORT ---
    if ($export === 'pdf') {
        if (strlen($pdfPassword) < 6) {
            throw new Exception("PDF password must be at least 6 characters.");
        }

        if (!loadTCPDF()) {
            throw new Exception("TCPDF library not found.");
        }

        if (!class_exists('RepaymentExportPDF')) {
            class RepaymentExportPDF extends TCPDF
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
                    $this->Cell(0, 5, 'Collection Monitoring & Recovery Report', 0, 0, 'L');
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

        // Fetch data
        $allLoansSql = "
            SELECT 
                lp.loan_id,
                lp.member_id,
                lp.loan_type,
                lp.principal_amount,
                lp.interest_rate,
                lp.loan_term,
                lp.start_date,
                lp.end_date,
                lp.status,
                m.full_name as member_name,
                m.email,
                COALESCE(ls.overdue_count, 0) as overdue_count,
                CASE 
                    WHEN lp.status = 'Defaulted' THEN 'High'
                    WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                    WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                    ELSE 'Low'
                END as risk_level
            FROM loan_portfolio lp
            LEFT JOIN members m ON lp.member_id = m.member_id
            LEFT JOIN (
                SELECT loan_id, COUNT(*) as overdue_count
                FROM loan_schedule 
                WHERE status = 'Overdue'
                GROUP BY loan_id
            ) ls ON lp.loan_id = ls.loan_id
            $whereSql
            ORDER BY lp.loan_id DESC
        ";
        $result = $conn->query($allLoansSql);
        
        $pdf = new RepaymentExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Repayment System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Repayment Tracker Report');

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
                        <th width="20%">Member</th>
                        <th width="12%">Type</th>
                        <th width="12%">Principal</th>
                        <th width="8%">Rate</th>
                        <th width="8%">Term</th>
                        <th width="12%">Start</th>
                        <th width="10%">Status</th>
                        <th width="10%">Risk</th>
                    </tr>
                </thead>
                <tbody>';

        $n = 0;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rowClass = ($n % 2 === 0) ? 'row-alt' : 'row-light';
                $html .= '<tr class="' . $rowClass . '">'
                    . '<td class="center">' . $row['loan_id'] . '</td>'
                    . '<td>' . htmlspecialchars($row['member_name'], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($row['loan_type'], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="center">₱' . number_format($row['principal_amount'], 2) . '</td>'
                    . '<td class="center">' . $row['interest_rate'] . '%</td>'
                    . '<td class="center">' . $row['loan_term'] . ' mo</td>'
                    . '<td class="center">' . $row['start_date'] . '</td>'
                    . '<td class="center">' . $row['status'] . '</td>'
                    . '<td class="center">' . $row['risk_level'] . '</td>'
                    . '</tr>';
                $n++;
            }
        } else {
            $html .= '<tr class="row-light"><td colspan="9" class="center">No records found.</td></tr>';
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        
        outputPdfDownload($pdf, 'repayment_tracker_' . date('Y-m-d_His') . '.pdf');
    }

    // --- CSV EXPORT (EXCEL) ---
    if ($export === 'csv') {
        // Build WHERE clause (reuse logic from start of file if possible, or just build it)
        $whereClauses = [];
        if ($search !== '') {
            $s = $conn->real_escape_string($search);
            $whereClauses[] = "(lp.loan_id LIKE '%$s%' OR m.full_name LIKE '%$s%' OR lp.loan_type LIKE '%$s%')";
        }
        if ($statusFilter !== '') {
            $st = $conn->real_escape_string($statusFilter);
            $whereClauses[] = "lp.status = '$st'";
        }
        if ($typeFilter !== '') {
            $tp = $conn->real_escape_string($typeFilter);
            $whereClauses[] = "lp.loan_type = '$tp'";
        }
        if ($cardFilter === 'active') $whereClauses[] = "lp.status = 'Active'";
        if ($cardFilter === 'overdue') {
            $whereClauses[] = "lp.loan_id IN (SELECT loan_id FROM loan_schedule WHERE status = 'Overdue')";
        }
        if ($cardFilter === 'at_risk') {
            $whereClauses[] = "(lp.status = 'Defaulted' OR lp.loan_id IN (SELECT loan_id FROM loan_schedule WHERE status = 'Overdue' GROUP BY loan_id HAVING COUNT(*) >= 3))";
        }

        $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = "
            SELECT 
                lp.loan_id,
                m.full_name as member_name,
                lp.loan_type,
                lp.principal_amount,
                lp.interest_rate,
                lp.loan_term,
                lp.start_date,
                lp.status,
                CASE 
                    WHEN lp.status = 'Defaulted' THEN 'High'
                    WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                    WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                    ELSE 'Low'
                END as risk_level
            FROM loan_portfolio lp
            LEFT JOIN members m ON lp.member_id = m.member_id
            LEFT JOIN (
                SELECT loan_id, COUNT(*) as overdue_count
                FROM loan_schedule 
                WHERE status = 'Overdue'
                GROUP BY loan_id
            ) ls ON lp.loan_id = ls.loan_id
            $whereSql
            ORDER BY lp.loan_id DESC
        ";
        
        $result = $conn->query($sql);
        $filename_base = 'repayment_export_' . date('Y-m-d_His');
        $csv_filename = $filename_base . '.csv';

        // Create CSV in memory
        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
        fputcsv($output, ['Loan ID', 'Member', 'Loan Type', 'Principal', 'Rate (%)', 'Term (mo)', 'Start Date', 'Status', 'Risk Level']);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['loan_id'],
                    $row['member_name'] ?? 'N/A',
                    $row['loan_type'],
                    $row['principal_amount'],
                    $row['interest_rate'],
                    $row['loan_term'],
                    $row['start_date'],
                    $row['status'],
                    $row['risk_level']
                ]);
            }
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        if ($pdfPassword !== '') {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                $zip_filename = $filename_base . '.zip';
                $temp_file = tempnam(sys_get_temp_dir(), 'zip');
                if ($zip->open($temp_file, ZipArchive::CREATE) === TRUE) {
                    $zip->addFromString($csv_filename, $csv_content);
                    if (method_exists($zip, 'setEncryptionName')) {
                        $zip->setEncryptionName($csv_filename, ZipArchive::EM_AES_256, $pdfPassword);
                    }
                    $zip->close();
                    
                    while (ob_get_level() > 0) ob_end_clean();
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
                    header('Content-Length: ' . filesize($temp_file));
                    readfile($temp_file);
                    unlink($temp_file);
                    exit;
                }
            }
        }

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
        echo $csv_content;
        exit;
    }

    // --- SUMMARY CARDS ---
    $summary = [
        'total_loans' => 0,
        'active_loans' => 0,
        'overdue_loans' => 0,
        'at_risk_loans' => 0
    ];

    // Total loans
    $result = $conn->query("SELECT COUNT(*) as cnt FROM loan_portfolio");
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['total_loans'] = (int)$row['cnt'];
    }

    // Active loans
    $result = $conn->query("SELECT COUNT(*) as cnt FROM loan_portfolio WHERE status='Active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['active_loans'] = (int)$row['cnt'];
    }

    // Overdue loans
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp 
        INNER JOIN loan_schedule ls ON lp.loan_id = ls.loan_id 
        WHERE ls.status = 'Overdue'
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['overdue_loans'] = (int)$row['cnt'];
    }

    // At-risk loans (3+ overdue or defaulted)
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        WHERE lp.status = 'Defaulted' OR (
            SELECT COUNT(*) 
            FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) >= 3
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['at_risk_loans'] = (int)$row['cnt'];
    }

    // --- LOAN STATUS DISTRIBUTION ---
    $statusData = ['labels' => [], 'values' => []];
    $result = $conn->query("
        SELECT status, COUNT(*) as cnt 
        FROM loan_portfolio 
        WHERE status IS NOT NULL
        GROUP BY status
        ORDER BY 
            CASE 
                WHEN status = 'Active' THEN 1
                WHEN status = 'Pending' THEN 2
                WHEN status = 'Approved' THEN 3
                WHEN status = 'Completed' THEN 4
                WHEN status = 'Defaulted' THEN 5
                ELSE 6
            END
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $statusData['labels'][] = $row['status'];
            $statusData['values'][] = (int)$row['cnt'];
        }
    }

    // --- RISK BREAKDOWN ---
    $riskData = ['labels' => ['Low', 'Medium', 'High'], 'values' => [0, 0, 0]];

    // Low risk
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        WHERE lp.status IN ('Active', 'Approved')
        AND (
            SELECT COUNT(*) 
            FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) = 0
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $riskData['values'][0] = (int)$row['cnt'];
    }

    // Medium risk
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        WHERE lp.status IN ('Active', 'Approved')
        AND (
            SELECT COUNT(*) 
            FROM loan_schedule ls 
            WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
        ) BETWEEN 1 AND 2
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $riskData['values'][1] = (int)$row['cnt'];
    }

    // High risk
    $result = $conn->query("
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        WHERE lp.status = 'Defaulted' OR (
            lp.status IN ('Active', 'Approved')
            AND (
                SELECT COUNT(*) 
                FROM loan_schedule ls 
                WHERE ls.loan_id = lp.loan_id AND ls.status = 'Overdue'
            ) >= 3
        )
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $riskData['values'][2] = (int)$row['cnt'];
    }

    // --- GET LOAN TYPES ---
    $loanTypes = [];
    $result = $conn->query("
        SELECT DISTINCT loan_type 
        FROM loan_portfolio 
        WHERE loan_type IS NOT NULL 
        ORDER BY loan_type
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $loanTypes[] = $row['loan_type'];
        }
    }

    // --- COUNT TOTAL RECORDS ---
    $countSql = "
        SELECT COUNT(DISTINCT lp.loan_id) as cnt
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) as overdue_count
            FROM loan_schedule 
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
    ";
    $result = $conn->query($countSql);
    $totalRecords = 0;
    if ($result) {
        $row = $result->fetch_assoc();
        $totalRecords = (int)$row['cnt'];
    }
    $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 1;

    // --- FETCH LOANS (WITH EMAIL FIELD!) ---
    $sql = "
        SELECT 
            lp.loan_id,
            lp.member_id,
            lp.loan_type,
            lp.principal_amount,
            lp.interest_rate,
            lp.loan_term,
            lp.start_date,
            lp.end_date,
            lp.status,
            m.full_name as member_name,
            m.email,
            COALESCE(ls.overdue_count, 0) as overdue_count,
            CASE 
                WHEN lp.status = 'Defaulted' THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                ELSE 'Low'
            END as risk_level,
            (
                SELECT MIN(due_date) 
                FROM loan_schedule 
                WHERE loan_id = lp.loan_id 
                AND status = 'Pending' 
                LIMIT 1
            ) as next_due
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) as overdue_count
            FROM loan_schedule 
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
        ORDER BY lp.loan_id DESC
        LIMIT $offset, $limit
    ";

    $result = $conn->query($sql);
    $loans = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $loans[] = $row;
        }
    }

    // --- GET ALL LOANS FOR PDF EXPORT (WITH EMAIL FIELD!) ---
    $allLoansSql = "
        SELECT 
            lp.loan_id,
            lp.member_id,
            lp.loan_type,
            lp.principal_amount,
            lp.interest_rate,
            lp.loan_term,
            lp.start_date,
            lp.end_date,
            lp.status,
            m.full_name as member_name,
            m.email,
            COALESCE(ls.overdue_count, 0) as overdue_count,
            CASE 
                WHEN lp.status = 'Defaulted' THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) >= 3 THEN 'High'
                WHEN COALESCE(ls.overdue_count, 0) BETWEEN 1 AND 2 THEN 'Medium'
                ELSE 'Low'
            END as risk_level
        FROM loan_portfolio lp
        LEFT JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) as overdue_count
            FROM loan_schedule 
            WHERE status = 'Overdue'
            GROUP BY loan_id
        ) ls ON lp.loan_id = ls.loan_id
        $whereSql
        ORDER BY lp.loan_id DESC
    ";
    $result = $conn->query($allLoansSql);
    $allLoans = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $allLoans[] = $row;
        }
    }

    // --- RETURN JSON ---
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'loan_status' => $statusData,
        'risk_breakdown' => $riskData,
        'loan_types' => $loanTypes,
        'loans' => $loans,
        'all_loans' => $allLoans,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($export === 'pdf') {
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
