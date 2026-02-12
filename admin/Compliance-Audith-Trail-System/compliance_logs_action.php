<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');
require_once __DIR__ . '/../inc/check_auth.php';

// Enforce RBAC
checkPermission('compliance_logs');

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => 'Database connection failed']);
    exit;
}

/**
 * Build WHERE clause for filtering
 */
function buildWhereClause($search, $start, $end, $status) {
    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = "(a.action_type LIKE ? OR a.module_name LIKE ? OR u.full_name LIKE ? OR a.remarks LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        $types .= 'ssss';
    }

    if ($start !== '' && $end !== '') {
        // Validate date format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $where[] = "DATE(a.action_time) BETWEEN ? AND ?";
            $params[] = $start;
            $params[] = $end;
            $types .= 'ss';
        }
    }

    if ($status !== '') {
        // Valid statuses that match your frontend dropdown
        $validStatuses = ['Compliant', 'Non-Compliant', 'Under Review', 'Pending'];
        if (in_array($status, $validStatuses)) {
            $where[] = "a.compliance_status = ?";
            $params[] = $status;
            $types .= 's';
        }
    }

    $whereSQL = count($where) ? "WHERE " . implode(' AND ', $where) : '';

    return ['sql' => $whereSQL, 'params' => $params, 'types' => $types];
}

/**
 * Load TCPDF library from various possible locations
 */
function loadTCPDF() {
    if (class_exists('TCPDF')) {
        return true;
    }
    
    // Possible autoload paths (relative to coreT2 root)
    $autoloadPaths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
        __DIR__ . '/../../../../vendor/autoload.php',
    ];
    
    // Possible direct TCPDF paths
    $tcpdfPaths = [
        __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../../libs/tcpdf/tcpdf.php',
        __DIR__ . '/../libs/tcpdf/tcpdf.php',
    ];
    
    // Try autoload first (preferred method)
    foreach ($autoloadPaths as $path) {
        if (file_exists($path)) {
            require_once($path);
            if (class_exists('TCPDF')) {
                return true;
            }
        }
    }
    
    // Try direct TCPDF include as fallback
    foreach ($tcpdfPaths as $path) {
        if (file_exists($path)) {
            require_once($path);
            if (class_exists('TCPDF')) {
                return true;
            }
        }
    }
    
    return false;
}


class ComplianceExportPDF extends TCPDF {
    public function Header(): void {
        $leftMargin = 10;
        $top = 8;
        $width = 277;

        // Dark green company header band
        $this->SetFillColor(20, 83, 45);
        $this->SetDrawColor(20, 83, 45);
        $this->RoundedRect($leftMargin, $top, $width, 20, 2, '1111', 'FD');

        // Company logo (if available)
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
        $this->Cell(0, 5, 'Compliance & Audit Trail Report', 0, 0, 'L');
    }

    public function Footer(): void {
        $this->SetY(-12);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(20, 83, 45);
        $this->Cell(0, 8, 'Confidential • Compliance Monitoring • Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $search = trim($_GET['search'] ?? '');
        $start = trim($_GET['start'] ?? '');
        $end = trim($_GET['end'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $pdfPassword = trim($_GET['pdf_password'] ?? '');

        // Build WHERE clause
        $filterData = buildWhereClause($search, $start, $end, $status);
        $whereSQL = $filterData['sql'];
        $params = $filterData['params'];
        $types = $filterData['types'];

        // Fetch data
        $sql = "
            SELECT 
                a.audit_id,
                u.full_name,
                u.username,
                a.action_type,
                a.module_name,
                a.remarks,
                a.compliance_status,
                DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                a.ip_address
            FROM audit_trail a
            LEFT JOIN users u ON a.user_id = u.user_id
            $whereSQL
            ORDER BY a.action_time DESC
        ";

        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();

        // Set headers for CSV download
        $filename = 'compliance_logs_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV Headers
        fputcsv($output, [
            'ID',
            'User',
            'Username',
            'Action Type',
            'Module',
            'Description',
            'Compliance Status',
            'Date/Time',
            'IP Address'
        ]);

        // CSV Data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['audit_id'] ?? '',
                $row['full_name'] ?? 'System',
                $row['username'] ?? '',
                $row['action_type'] ?? '',
                $row['module_name'] ?? '',
                $row['remarks'] ?? '',
                $row['compliance_status'] ?? '',
                $row['action_time'] ?? '',
                $row['ip_address'] ?? ''
            ]);
        }

        fclose($output);
        $stmt->close();
        exit;

    } catch (Exception $e) {
        error_log("CSV Export Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => 'CSV export failed: ' . $e->getMessage()]);
        exit;
    }
}

// Handle PDF Export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Try to load TCPDF
    if (!loadTCPDF()) {
        error_log("TCPDF library not found in any expected location");
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Export failed. PDF library not found. No composer needed if you place TCPDF at: admin/libs/tcpdf/tcpdf.php',
            'msg' => 'Export failed. PDF library not found. No composer needed if you place TCPDF at: admin/libs/tcpdf/tcpdf.php'
        ]);
        exit;
    }
    
    try {
        $search = trim($_GET['search'] ?? '');
        $start = trim($_GET['start'] ?? '');
        $end = trim($_GET['end'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $pdfPassword = trim($_GET['pdf_password'] ?? '');

        if (strlen($pdfPassword) < 6) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Export failed. PDF password must be at least 6 characters.',
                'msg' => 'Export failed. PDF password must be at least 6 characters.'
            ]);
            exit;
        }

        // Build WHERE clause
        $filterData = buildWhereClause($search, $start, $end, $status);
        $whereSQL = $filterData['sql'];
        $params = $filterData['params'];
        $types = $filterData['types'];

        // Fetch data
        $sql = "
            SELECT 
                a.audit_id,
                a.user_id,
                a.action_type,
                a.module_name,
                a.remarks,
                a.compliance_status,
                DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                a.ip_address,
                u.full_name, 
                u.username
            FROM audit_trail a
            LEFT JOIN users u ON a.user_id = u.user_id
            $whereSQL
            ORDER BY a.action_time DESC
        ";

        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();

        // Create PDF using TCPDF
        $pdf = new ComplianceExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('Compliance System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Compliance & Audit Trail Logs - Golden Horizons Cooperative');
        $pdf->SetSubject('Audit Trail Report');

        // Security: require password before opening exported PDF
        $pdf->SetProtection(['print', 'copy'], $pdfPassword, null, 0, null);

        $pdf->SetMargins(10, 32, 10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();

        $pdf->SetTextColor(34, 34, 34);
        $pdf->SetFillColor(220, 252, 231);

        $periodText = ($start && $end)
            ? ('Period: ' . $start . ' to ' . $end)
            : 'Period: All Dates';
        $statusText = $status
            ? ('Status: ' . $status)
            : 'Status: All';

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(138.5, 7, $periodText, 0, 0, 'L', true);
        $pdf->Cell(138.5, 7, $statusText, 0, 1, 'R', true);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
        $pdf->Ln(3);

        // Table data (green theme)
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
                        <th width="4%">#</th>
                        <th width="14%">User</th>
                        <th width="11%">Action</th>
                        <th width="11%">Module</th>
                        <th width="28%">Description</th>
                        <th width="10%">Status</th>
                        <th width="13%">Date/Time</th>
                        <th width="9%">IP</th>
                    </tr>
                </thead>
                <tbody>';

        $n = 1;
        $hasRows = false;
        while ($row = $result->fetch_assoc()) {
            $hasRows = true;
            $rowClass = ($n % 2 === 0) ? 'row-alt' : 'row-light';
            $user = $row['full_name'] ?? $row['username'] ?? 'System';
            $action = $row['action_type'] ?? '';
            $module = $row['module_name'] ?? '';
            $remarks = $row['remarks'] ?? '';
            $statusVal = $row['compliance_status'] ?? '';
            $datetime = $row['action_time'] ?? '';
            $ip = $row['ip_address'] ?? '-';

            $html .= '<tr class="' . $rowClass . '">'
                . '<td class="center">' . $n . '</td>'
                . '<td>' . htmlspecialchars((string)$user, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string)$action, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string)$module, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string)$remarks, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="center">' . htmlspecialchars((string)$statusVal, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="center">' . htmlspecialchars((string)$datetime, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="center">' . htmlspecialchars((string)$ip, ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
            $n++;
        }

        if (!$hasRows) {
            $html .= '<tr class="row-light"><td colspan="8" class="center">No compliance logs found for the selected filters.</td></tr>';
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        
        $stmt->close();

        if (ob_get_length()) {
            ob_end_clean();
        }

        // Output PDF
        $filename = 'compliance_logs_' . date('Y-m-d_His') . '.pdf';
        $pdf->Output($filename, 'D');
        exit;

    } catch (Exception $e) {
        error_log("PDF Export Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => 'PDF export failed: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX List Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'list') {
            $page = max(1, intval($_POST['page'] ?? 1));
            $limit = max(1, min(100, intval($_POST['limit'] ?? 10)));
            $offset = ($page - 1) * $limit;

            $search = trim($_POST['search'] ?? '');
            $start = trim($_POST['start'] ?? '');
            $end = trim($_POST['end'] ?? '');
            $status = trim($_POST['status'] ?? '');

            // Build WHERE clause
            $filterData = buildWhereClause($search, $start, $end, $status);
            $whereSQL = $filterData['sql'];
            $params = $filterData['params'];
            $types = $filterData['types'];

            // Count total records
            $countSQL = "SELECT COUNT(*) AS total FROM audit_trail a LEFT JOIN users u ON a.user_id = u.user_id $whereSQL";
            $stmt = $conn->prepare($countSQL);
            
            if (!$stmt) {
                throw new Exception("Failed to prepare count statement: " . $conn->error);
            }
            
            if ($types !== '' && count($params) > 0) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute count query: " . $stmt->error);
            }
            
            $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt->close();

            // Fetch records with pagination
            $sql = "
                SELECT 
                    a.audit_id,
                    a.user_id,
                    a.action_type,
                    a.module_name,
                    a.record_id,
                    DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                    a.ip_address,
                    a.remarks,
                    a.compliance_status,
                    u.full_name,
                    u.username
                FROM audit_trail a
                LEFT JOIN users u ON a.user_id = u.user_id
                $whereSQL
                ORDER BY a.action_time DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Failed to prepare select statement: " . $conn->error);
            }
            
            // Bind all parameters including LIMIT and OFFSET
            if ($types !== '' && count($params) > 0) {
                $allParams = array_merge($params, [$limit, $offset]);
                $allTypes = $types . 'ii';
                $stmt->bind_param($allTypes, ...$allParams);
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute select query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();

            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'audit_id' => $row['audit_id'],
                    'user_id' => $row['user_id'],
                    'username' => $row['username'] ?? '',
                    'full_name' => $row['full_name'] ?? '',
                    'action_type' => $row['action_type'] ?? '',
                    'module_name' => $row['module_name'] ?? '',
                    'record_id' => $row['record_id'] ?? '',
                    'remarks' => $row['remarks'] ?? '',
                    'compliance_status' => $row['compliance_status'] ?? '',
                    'action_time' => $row['action_time'] ?? '',
                    'ip_address' => $row['ip_address'] ?? ''
                ];
            }

            $stmt->close();

            echo json_encode([
                'status' => 'success',
                'rows' => $rows,
                'total' => intval($total),
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);
            exit;
        }

        echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
        exit;

    } catch (Exception $e) {
        error_log("Compliance Logs Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'status' => 'error',
            'msg' => 'Database error occurred'
        ]);
        exit;
    }
}

// Invalid request
http_response_code(400);
echo json_encode(['status' => 'error', 'msg' => 'Invalid request method']);
exit;
?>
