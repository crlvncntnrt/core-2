<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

date_default_timezone_set('Asia/Manila');

/* ============================================================
   MONTHLY INTEREST SETTINGS
   ============================================================ */
const SAVINGS_INTEREST_RATE = 0.025;

// Set this to a real existing users.user_id (admin/system)
const SYSTEM_USER_ID_FOR_INTEREST = 1;

/* ============================================================
   TCPDF LOADER + PDF OUTPUT (UNCHANGED STYLE)
   ============================================================ */
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

function outputPdfDownload($pdf, string $filename): void
{
    while (ob_get_level() > 0) ob_end_clean();
    ob_start();
    $binary = $pdf->Output($filename, 'S');
    ob_end_clean();

    if ($binary === '') throw new Exception('Generated PDF content is empty.');

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

/* ============================================================
   MONTHLY INTEREST CORE
   - inserts a real transaction: transaction_type='Interest'
   - prevents duplicates per month
   - safe to run monthly by cron using CLI
   ============================================================ */
function applyMonthlySavingsInterest(mysqli $conn): array
{
    $rate = SAVINGS_INTEREST_RATE;
    $systemUserId = SYSTEM_USER_ID_FOR_INTEREST;

    // Apply for the previous month (recommended if cron runs every 1st day)
    $target = new DateTime('first day of last month');
    $targetYm = $target->format('Y-m');

    // Post date = first day of this month
    $postDate = (new DateTime('first day of this month'))->format('Y-m-d');

    // Get each member latest balance
    $sqlMembers = "
        SELECT s1.member_id, s1.balance
        FROM savings s1
        INNER JOIN (
            SELECT member_id, MAX(CONCAT(transaction_date, LPAD(saving_id, 10, '0'))) AS mx
            FROM savings
            GROUP BY member_id
        ) s2
        ON s1.member_id = s2.member_id
        AND CONCAT(s1.transaction_date, LPAD(s1.saving_id, 10, '0')) = s2.mx
    ";

    $res = $conn->query($sqlMembers);
    if (!$res) {
        return ['ok' => false, 'msg' => 'Failed to read member balances: ' . $conn->error];
    }

    $checkStmt = $conn->prepare("
        SELECT 1
        FROM savings
        WHERE member_id = ?
          AND transaction_type = 'Interest'
          AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
        LIMIT 1
    ");

    $insertStmt = $conn->prepare("
        INSERT INTO savings (member_id, transaction_date, transaction_type, amount, balance, recorded_by)
        VALUES (?, ?, 'Interest', ?, ?, ?)
    ");

    $applied = 0;
    $skipped = 0;

    while ($row = $res->fetch_assoc()) {
        $memberId = intval($row['member_id']);
        $lastBalance = floatval($row['balance']);

        if ($lastBalance <= 0) { $skipped++; continue; }

        // Block duplicate for that month
        $checkStmt->bind_param("is", $memberId, $targetYm);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        if ($exists) { $skipped++; continue; }

        $interest = round($lastBalance * $rate, 2);
        if ($interest <= 0) { $skipped++; continue; }

        $newBalance = round($lastBalance + $interest, 2);

        // NOTE: if your recorded_by column is VARCHAR in DB, this bind might fail.
        // If so, tell me your table structure and I'll adjust bind types.
        $insertStmt->bind_param("issdi", $memberId, $postDate, $interest, $newBalance, $systemUserId);

        if ($insertStmt->execute()) $applied++;
        else {
            error_log("Interest insert failed member {$memberId}: " . $insertStmt->error);
            $skipped++;
        }
    }

    $checkStmt->close();
    $insertStmt->close();

    return [
        'ok' => true,
        'target_month' => $targetYm,
        'post_date' => $postDate,
        'applied' => $applied,
        'skipped' => $skipped
    ];
}

/* ============================================================
   CLI MODE FOR CRON:
   php savings_action.php apply_interest
   ============================================================ */
if (PHP_SAPI === 'cli') {
    $cmd = $argv[1] ?? '';
    if ($cmd === 'apply_interest') {
        $result = applyMonthlySavingsInterest($conn);
        if (!$result['ok']) {
            echo "FAILED: " . $result['msg'] . PHP_EOL;
            exit(1);
        }
        echo "OK: month={$result['target_month']} postDate={$result['post_date']} applied={$result['applied']} skipped={$result['skipped']}" . PHP_EOL;
        exit(0);
    }
}

/* ============================================================
   EXPORTS MUST RUN BEFORE JSON HEADER
   ============================================================ */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search = trim($_GET['search'] ?? '');
    $search_by = $_GET['search_by'] ?? 'auto';
    $filter = $_GET['filter'] ?? '';
    $type = $_GET['type'] ?? '';

    $member_id = intval($_GET['member_id'] ?? 0);
    $recorded_by = intval($_GET['recorded_by'] ?? 0);
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        if (preg_match('/^\d+$/', $search)) {
            $where[] = "s.member_id = ?";
            $params[] = intval($search);
            $types .= 'i';
        } else {
            if ($search_by === 'transaction_type') {
                $where[] = "s.transaction_type LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            } elseif ($search_by === 'transaction_date') {
                $where[] = "s.transaction_date LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            } elseif ($search_by === 'recorded_by_name') {
                $where[] = "s.recorded_by IN (SELECT user_id FROM users WHERE full_name LIKE ?)";
                $params[] = "%$search%";
                $types .= 's';
            } else {
                $where[] = "(CAST(s.member_id AS CHAR) LIKE ? OR s.transaction_type LIKE ? OR s.transaction_date LIKE ?)";
                $s = "%$search%";
                $params[] = $s; $params[] = $s; $params[] = $s;
                $types .= 'sss';
            }
        }
    }

    // Card filters:
    // deposit = Deposit + Interest
    if ($filter === 'deposit') $where[] = "s.transaction_type IN ('Deposit','Interest')";
    elseif ($filter === 'withdrawal') $where[] = "s.transaction_type='Withdrawal'";

    if ($type !== '') {
        $where[] = "s.transaction_type=?";
        $params[] = $type;
        $types .= 's';
    }

    if ($member_id > 0) {
        $where[] = "s.member_id=?";
        $params[] = $member_id;
        $types .= 'i';
    }

    if ($recorded_by > 0) {
        $where[] = "s.recorded_by=?";
        $params[] = $recorded_by;
        $types .= 'i';
    }

    if ($date_from !== '') {
        $where[] = "s.transaction_date >= ?";
        $params[] = $date_from;
        $types .= 's';
    }

    if ($date_to !== '') {
        $where[] = "s.transaction_date <= ?";
        $params[] = $date_to;
        $types .= 's';
    }

    $whereSql = count($where) ? "WHERE " . implode(' AND ', $where) : '';
    $pdfPassword = trim($_GET['pdf_password'] ?? '');

    $sql = "SELECT s.*, u.full_name AS recorded_by_name
            FROM savings s
            LEFT JOIN users u ON s.recorded_by = u.user_id
            $whereSql
            ORDER BY s.transaction_date DESC, s.saving_id DESC";

    $csv_data = [];
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $csv_data[] = $r;
        $stmt->close();
    } else {
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc()) $csv_data[] = $r;
    }

    $filename_base = 'savings_export_' . date('Y-m-d_His');
    $csv_filename = $filename_base . '.csv';

    $out = fopen('php://temp', 'r+');
    fputcsv($out, ['ID', 'Member ID', 'Date', 'Type', 'Amount', 'Balance', 'Recorded By']);
    foreach ($csv_data as $r) {
        fputcsv($out, [
            $r['saving_id'], $r['member_id'], $r['transaction_date'], $r['transaction_type'],
            $r['amount'], $r['balance'], $r['recorded_by_name'] ?? '-'
        ]);
    }
    rewind($out);
    $csv_content = stream_get_contents($out);
    fclose($out);

    if ($pdfPassword !== '') {
        if (!class_exists('ZipArchive')) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
            echo $csv_content;
            exit;
        }
        $zip = new ZipArchive();
        $zip_filename = $filename_base . '.zip';
        $temp_file = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($temp_file, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString($csv_filename, $csv_content);
            if (method_exists($zip, 'setEncryptionName')) {
                $zip->setEncryptionName($csv_filename, ZipArchive::EM_AES_256, $pdfPassword);
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

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
    echo $csv_content;
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $search = trim($_GET['search'] ?? '');
    $search_by = $_GET['search_by'] ?? 'auto';
    $filter = $_GET['filter'] ?? '';
    $type = $_GET['type'] ?? '';
    $member_id = intval($_GET['member_id'] ?? 0);
    $recorded_by = intval($_GET['recorded_by'] ?? 0);
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $pdfPassword = trim($_GET['pdf_password'] ?? '');

    if (strlen($pdfPassword) < 6) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => 'PDF password must be at least 6 characters.']);
        exit;
    }

    if (!loadTCPDF()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => 'TCPDF library not found.']);
        exit;
    }

    if (!class_exists('SavingsExportPDF')) {
        class SavingsExportPDF extends TCPDF
        {
            public function Header(): void
            {
                $leftMargin = 10;
                $top = 8;
                $width = 277;
                $this->SetFillColor(5, 150, 105);
                $this->SetDrawColor(5, 150, 105);
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
                $this->Cell(0, 5, 'Savings Monitoring & Transactions Report', 0, 0, 'L');
            }

            public function Footer(): void
            {
                $this->SetY(-12);
                $this->SetFont('helvetica', 'I', 8);
                $this->SetTextColor(5, 150, 105);
                $this->Cell(0, 8, 'Confidential • Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
            }
        }
    }

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        if (preg_match('/^\d+$/', $search)) {
            $where[] = "s.member_id = ?";
            $params[] = intval($search);
            $types .= 'i';
        } else {
            if ($search_by === 'transaction_type') {
                $where[] = "s.transaction_type LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            } elseif ($search_by === 'transaction_date') {
                $where[] = "s.transaction_date LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            } elseif ($search_by === 'recorded_by_name') {
                $where[] = "s.recorded_by IN (SELECT user_id FROM users WHERE full_name LIKE ?)";
                $params[] = "%$search%";
                $types .= 's';
            } else {
                $where[] = "(CAST(s.member_id AS CHAR) LIKE ? OR s.transaction_type LIKE ? OR s.transaction_date LIKE ?)";
                $s = "%$search%";
                $params[] = $s; $params[] = $s; $params[] = $s;
                $types .= 'sss';
            }
        }
    }

    if ($filter === 'deposit') $where[] = "s.transaction_type IN ('Deposit','Interest')";
    elseif ($filter === 'withdrawal') $where[] = "s.transaction_type='Withdrawal'";

    if ($type !== '') { $where[] = "s.transaction_type=?"; $params[] = $type; $types .= 's'; }
    if ($member_id > 0) { $where[] = "s.member_id=?"; $params[] = $member_id; $types .= 'i'; }
    if ($recorded_by > 0) { $where[] = "s.recorded_by=?"; $params[] = $recorded_by; $types .= 'i'; }
    if ($date_from !== '') { $where[] = "s.transaction_date >= ?"; $params[] = $date_from; $types .= 's'; }
    if ($date_to !== '') { $where[] = "s.transaction_date <= ?"; $params[] = $date_to; $types .= 's'; }

    $whereSql = count($where) ? "WHERE " . implode(' AND ', $where) : '';
    $sql = "SELECT s.*, u.full_name AS recorded_by_name
            FROM savings s
            LEFT JOIN users u ON s.recorded_by = u.user_id
            $whereSql
            ORDER BY s.transaction_date DESC, s.saving_id DESC";

    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $pdf = new SavingsExportPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Savings System');
    $pdf->SetTitle('Savings Transactions Report');
    $ownerPassword = md5(uniqid(mt_rand(), true));
    $pdf->SetProtection(['print', 'copy'], $pdfPassword, $ownerPassword, 0, null);
    $pdf->SetMargins(10, 32, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    $html = '<style>
        table { border-collapse: collapse; }
        th { background-color: #059669; color: #ffffff; font-size: 10px; font-weight: bold; padding: 6px; border: 1px solid #065f46; text-align: center; }
        td { font-size: 9px; color: #1f2937; padding: 5px; border: 1px solid #d1fae5; }
        .row-alt { background-color: #f0fdf4; }
        .center { text-align: center; }
        .text-end { text-align: right; }
    </style>
    <table width="100%" cellpadding="5">
        <thead>
            <tr>
                <th width="10%">ID</th>
                <th width="15%">Member ID</th>
                <th width="15%">Date</th>
                <th width="15%">Type</th>
                <th width="15%" class="text-end">Amount</th>
                <th width="15%" class="text-end">Balance</th>
                <th width="15%">Recorded By</th>
            </tr>
        </thead>
        <tbody>';

    $n = 0;
    while ($r = $res->fetch_assoc()) {
        $rowClass = ($n % 2 === 0) ? '' : 'row-alt';
        $html .= '<tr class="' . $rowClass . '">'
            . '<td class="center">' . $r['saving_id'] . '</td>'
            . '<td class="center">' . $r['member_id'] . '</td>'
            . '<td class="center">' . $r['transaction_date'] . '</td>'
            . '<td class="center">' . $r['transaction_type'] . '</td>'
            . '<td class="text-end">₱' . number_format($r['amount'], 2) . '</td>'
            . '<td class="text-end">₱' . number_format($r['balance'], 2) . '</td>'
            . '<td>' . htmlspecialchars($r['recorded_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
        $n++;
    }
    if ($n === 0) $html .= '<tr><td colspan="7" class="center">No records found.</td></tr>';

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    outputPdfDownload($pdf, 'savings_report_' . date('Y-m-d_His') . '.pdf');
}

/* ============================================================
   JSON AFTER EXPORTS
   ============================================================ */
header('Content-Type: application/json');

$role = $_SESSION['userdata']['role'] ?? 'Guest';
$user_id = intval($_SESSION['userdata']['user_id'] ?? 0);

if (!hasPermission($conn, $role, 'Savings Monitoring', 'view') && $role !== 'Admin') {
    echo json_encode(['status' => 'error', 'msg' => 'Access denied']);
    exit();
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

/* ============================================================
   SUMMARY (Interest counts as Deposit)
   ============================================================ */
function getSummary($conn, $where = '', $params = [], $types = '')
{
    $summary = [
        'total' => 0,
        'total_deposits' => 0,      // includes Interest
        'total_withdrawals' => 0,
        'last_balance' => 0
    ];

    $whereClean = str_replace(['WHERE ', 'where '], '', trim($where));
    $hasWhere = !empty($whereClean);

    $sql = "SELECT COUNT(*) AS total FROM savings" . ($hasWhere ? " WHERE $whereClean" : "");
    if ($params && count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $summary['total'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    } else {
        $summary['total'] = $conn->query($sql)->fetch_assoc()['total'] ?? 0;
    }

    $whereDeposit = $hasWhere
        ? "WHERE $whereClean AND transaction_type IN ('Deposit','Interest')"
        : "WHERE transaction_type IN ('Deposit','Interest')";
    $sql = "SELECT COUNT(*) AS total_deposits FROM savings $whereDeposit";
    if ($params && count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $summary['total_deposits'] = $stmt->get_result()->fetch_assoc()['total_deposits'] ?? 0;
        $stmt->close();
    } else {
        $summary['total_deposits'] = $conn->query($sql)->fetch_assoc()['total_deposits'] ?? 0;
    }

    $whereWithdraw = $hasWhere ? "WHERE $whereClean AND transaction_type='Withdrawal'" : "WHERE transaction_type='Withdrawal'";
    $sql = "SELECT COUNT(*) AS total_withdrawals FROM savings $whereWithdraw";
    if ($params && count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $summary['total_withdrawals'] = $stmt->get_result()->fetch_assoc()['total_withdrawals'] ?? 0;
        $stmt->close();
    } else {
        $summary['total_withdrawals'] = $conn->query($sql)->fetch_assoc()['total_withdrawals'] ?? 0;
    }

    $q = $conn->query("SELECT balance FROM savings ORDER BY saving_id DESC LIMIT 1");
    $summary['last_balance'] = $q ? ($q->fetch_assoc()['balance'] ?? 0) : 0;

    return $summary;
}

/* ============================================================
   MAIN
   ============================================================ */
try {
    switch ($action) {

        case 'meta':
            $members = [];
            $q1 = $conn->query("SELECT DISTINCT member_id FROM savings ORDER BY member_id ASC");
            while ($q1 && $r = $q1->fetch_assoc()) $members[] = intval($r['member_id']);

            $users = [];
            $q2 = $conn->query("
                SELECT DISTINCT u.user_id, u.full_name
                FROM savings s
                JOIN users u ON u.user_id = s.recorded_by
                ORDER BY u.full_name ASC
            ");
            while ($q2 && $r = $q2->fetch_assoc()) $users[] = $r;

            echo json_encode(['status' => 'success', 'members' => $members, 'recorded_by' => $users]);
            break;

        case 'list':
            $page = max(1, intval($_POST['page'] ?? 1));
            $limit = max(1, intval($_POST['limit'] ?? 10));
            $offset = ($page - 1) * $limit;

            $search = trim($_POST['search'] ?? '');
            $search_by = $_POST['search_by'] ?? 'auto';

            $filter = $_POST['filter'] ?? '';
            $type = $_POST['type'] ?? '';

            $member_id = intval($_POST['member_id'] ?? 0);
            $recorded_by = intval($_POST['recorded_by'] ?? 0);

            $date_from = $_POST['date_from'] ?? '';
            $date_to = $_POST['date_to'] ?? '';

            $where = [];
            $params = [];
            $types = '';

            if ($search !== '') {
                if ($search_by === 'auto') {
                    if (preg_match('/^\d+$/', $search)) {
                        $where[] = "s.member_id = ?";
                        $params[] = intval($search);
                        $types .= 'i';
                    } else {
                        $where[] = "(CAST(s.member_id AS CHAR) LIKE ? OR s.transaction_type LIKE ? OR s.transaction_date LIKE ?)";
                        $s = "%$search%";
                        $params[] = $s; $params[] = $s; $params[] = $s;
                        $types .= 'sss';
                    }
                } elseif ($search_by === 'member_id') {
                    if (preg_match('/^\d+$/', $search)) {
                        $where[] = "s.member_id = ?";
                        $params[] = intval($search);
                        $types .= 'i';
                    } else {
                        $where[] = "1=0";
                    }
                } elseif ($search_by === 'transaction_type') {
                    $where[] = "s.transaction_type LIKE ?";
                    $params[] = "%$search%";
                    $types .= 's';
                } elseif ($search_by === 'transaction_date') {
                    $where[] = "s.transaction_date LIKE ?";
                    $params[] = "%$search%";
                    $types .= 's';
                } elseif ($search_by === 'recorded_by_name') {
                    $where[] = "s.recorded_by IN (SELECT user_id FROM users WHERE full_name LIKE ?)";
                    $params[] = "%$search%";
                    $types .= 's';
                }
            }

            // Card filters
            if ($filter === 'deposit') $where[] = "s.transaction_type IN ('Deposit','Interest')";
            elseif ($filter === 'withdrawal') $where[] = "s.transaction_type='Withdrawal'";

            if ($type !== '') {
                $where[] = "s.transaction_type=?";
                $params[] = $type;
                $types .= 's';
            }

            if ($member_id > 0) {
                $where[] = "s.member_id=?";
                $params[] = $member_id;
                $types .= 'i';
            }

            if ($recorded_by > 0) {
                $where[] = "s.recorded_by=?";
                $params[] = $recorded_by;
                $types .= 'i';
            }

            if ($date_from !== '') {
                $where[] = "s.transaction_date >= ?";
                $params[] = $date_from;
                $types .= 's';
            }

            if ($date_to !== '') {
                $where[] = "s.transaction_date <= ?";
                $params[] = $date_to;
                $types .= 's';
            }

            $whereSql = count($where) ? "WHERE " . implode(' AND ', $where) : '';

            $sql = "
                SELECT s.*, u.full_name AS recorded_by_name
                FROM savings s
                LEFT JOIN users u ON s.recorded_by = u.user_id
                $whereSql
                ORDER BY s.transaction_date DESC, s.saving_id DESC
                LIMIT ?, ?
            ";

            $stmt = $conn->prepare($sql);
            $bindTypes = $types . 'ii';
            $bindParams = $params;
            $bindParams[] = $offset;
            $bindParams[] = $limit;

            $stmt->bind_param($bindTypes, ...$bindParams);
            $stmt->execute();
            $res = $stmt->get_result();

            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();

            $countSql = "SELECT COUNT(*) AS cnt FROM savings s $whereSql";
            $total = 0;

            if (!empty($params)) {
                $countStmt = $conn->prepare($countSql);
                $countStmt->bind_param($types, ...$params);
                $countStmt->execute();
                $total = intval($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
                $countStmt->close();
            } else {
                $result = $conn->query($countSql);
                $total = intval($result->fetch_assoc()['cnt'] ?? 0);
            }

            $total_pages = $limit > 0 ? ceil($total / $limit) : 1;

            $summaryWhere = str_replace('s.', '', $whereSql);
            $summaryWhere = str_replace('WHERE ', '', $summaryWhere);

            echo json_encode([
                'status' => 'success',
                'rows' => $rows,
                'summary' => getSummary($conn, $summaryWhere, $params, $types),
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => max(1, $total_pages),
                    'total_records' => $total
                ]
            ]);
            break;

        case 'breakdown':
            $member_id = intval($_POST['member_id'] ?? 0);
            if (!$member_id) {
                echo json_encode(['status' => 'error', 'msg' => 'Member ID required']);
                exit;
            }

            $memberStmt = $conn->prepare("
                SELECT member_id,
                       CONCAT('Member #', member_id) as name
                FROM savings
                WHERE member_id = ?
                LIMIT 1
            ");
            $memberStmt->bind_param("i", $member_id);
            $memberStmt->execute();
            $memberInfo = $memberStmt->get_result()->fetch_assoc();
            $memberStmt->close();

            $stmt = $conn->prepare("
                SELECT s.*, u.full_name AS recorded_by_name
                FROM savings s
                LEFT JOIN users u ON s.recorded_by = u.user_id
                WHERE s.member_id = ?
                ORDER BY s.transaction_date DESC, s.saving_id DESC
            ");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $res = $stmt->get_result();

            $transactions = [];
            while ($r = $res->fetch_assoc()) $transactions[] = $r;
            $stmt->close();

            $memberSummary = [
                'total_deposits' => 0, // includes Interest
                'total_withdrawals' => 0,
                'deposit_count' => 0,
                'withdrawal_count' => 0,
                'current_balance' => 0,
                'total_transactions' => count($transactions)
            ];

            foreach ($transactions as $txn) {
                if ($txn['transaction_type'] === 'Deposit' || $txn['transaction_type'] === 'Interest') {
                    $memberSummary['total_deposits'] += floatval($txn['amount']);
                    $memberSummary['deposit_count']++;
                } elseif ($txn['transaction_type'] === 'Withdrawal') {
                    $memberSummary['total_withdrawals'] += floatval($txn['amount']);
                    $memberSummary['withdrawal_count']++;
                }
            }

            if (!empty($transactions)) {
                $memberSummary['current_balance'] = floatval($transactions[0]['balance']);
            }

            echo json_encode([
                'status' => 'success',
                'member_info' => $memberInfo ?: ['member_id' => $member_id, 'name' => "Member #$member_id"],
                'summary' => $memberSummary,
                'transactions' => $transactions
            ]);
            break;

        case 'get':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['status' => 'error', 'msg' => 'ID required']);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT s.*, u.full_name AS recorded_by_name
                FROM savings s
                LEFT JOIN users u ON s.recorded_by = u.user_id
                WHERE s.saving_id=?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res) echo json_encode(['status' => 'success', 'row' => $res]);
            else echo json_encode(['status' => 'error', 'msg' => 'Record not found']);
            break;

        case 'add':
            if (!hasPermission($conn, $role, 'Savings Monitoring', 'add') && $role !== 'Admin') {
                echo json_encode(['status' => 'error', 'msg' => 'Permission denied']);
                exit;
            }

            $member_id = intval($_POST['member_id'] ?? 0);
            $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
            $type = $_POST['transaction_type'] ?? 'Deposit';
            $amount = floatval($_POST['amount'] ?? 0.0);

            // Lock manual types to Deposit/Withdrawal (Interest is cron-only)
            if ($type !== 'Deposit' && $type !== 'Withdrawal') $type = 'Deposit';

            if ($member_id <= 0 || $amount <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Member and positive amount required']);
                exit;
            }

            $balRes = $conn->prepare("SELECT balance FROM savings WHERE member_id = ? ORDER BY saving_id DESC LIMIT 1");
            $balRes->bind_param("i", $member_id);
            $balRes->execute();
            $bR = $balRes->get_result()->fetch_assoc();
            $last_balance = $bR['balance'] ?? 0;
            $balRes->close();

            $new_balance = ($type === 'Deposit') ? ($last_balance + $amount) : ($last_balance - $amount);

            if ($new_balance < 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Insufficient balance for withdrawal']);
                exit;
            }

            $stmt = $conn->prepare("
                INSERT INTO savings (member_id, transaction_date, transaction_type, amount, balance, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issddi", $member_id, $transaction_date, $type, $amount, $new_balance, $user_id);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                if (function_exists('logPermission')) {
                    logPermission($conn, $user_id, 'Savings Monitoring', 'Add', 'Success');
                }
                echo json_encode(['status' => 'success', 'msg' => 'Transaction added successfully']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Failed to save transaction']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Savings action error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'Server error']);
    exit;
}
