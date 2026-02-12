<?php
// ======================
// permission_logs_action.php
// JSON-only endpoint for Permission & User Audit Logs
// ======================

require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/access_control.php');

// Hide PHP warnings from breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['userdata']['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized access']);
    exit();
}

// Permission check
$role = $_SESSION['userdata']['role'] ?? 'Member';
if (!hasPermission($conn, $role, 'Permission Logs', 'view') && $role !== 'Super Admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => 'Access denied']);
    exit();
}

// Determine action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    // -------------------------
    // HELPER FUNCTIONS
    // -------------------------
    function buildWhereClause($start, $end, &$params, &$types) {
        $where = "WHERE 1=1";
        
        if (!empty($start) && !empty($end)) {
            // Validate date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                $where .= " AND DATE(action_time) BETWEEN ? AND ?";
                $params = [$start, $end];
                $types = 'ss';
            }
        }
        
        return $where;
    }

    function executePreparedStatement($conn, $sql, $types, $params) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL Prepare Error: " . $conn->error);
        }
        
        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("SQL Execute Error: " . $stmt->error);
        }
        
        return $stmt;
    }

    // -------------------------
    // LIST LOGS (AJAX)
    // -------------------------
    if ($action === 'list') {
        header('Content-Type: application/json');
        
        $page = max(1, intval($_POST['page'] ?? 1));
        $limit = max(1, min(100, intval($_POST['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        $start = trim($_POST['start'] ?? '');
        $end = trim($_POST['end'] ?? '');

        $params = [];
        $types = '';
        $where = buildWhereClause($start, $end, $params, $types);

        // ✅ UNION query to combine permission_logs AND audit_trail (Authentication only)
        $countSql = "
            SELECT COUNT(*) AS cnt FROM (
                SELECT log_id as id, action_time 
                FROM permission_logs 
                $where
                
                UNION ALL
                
                SELECT audit_id as id, action_time 
                FROM audit_trail 
                WHERE module_name = 'Authentication'
                " . (strpos($where, 'DATE(action_time)') !== false ? 
                    "AND DATE(action_time) BETWEEN ? AND ?" : "") . "
            ) combined
        ";
        
        // Adjust params for UNION (need to duplicate date params if exists)
        $countParams = $params;
        $countTypes = $types;
        if (!empty($params)) {
            $countParams = array_merge($params, $params);
            $countTypes = $types . $types;
        }

        $cntStmt = executePreparedStatement($conn, $countSql, $countTypes, $countParams);
        $total = intval($cntStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $cntStmt->close();

        // ✅ Fetch combined data from both tables
        $dataSql = "
            SELECT 
                log_id as audit_id,
                COALESCE(u.username, 'System') as username,
                action_name as action_type,
                module_name,
                CONCAT('Status: ', action_status) as remarks,
                '' as ip_address,
                DATE_FORMAT(pl.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                'permission_logs' as source
            FROM permission_logs pl
            LEFT JOIN users u ON pl.user_id = u.user_id
            $where
            
            UNION ALL
            
            SELECT 
                audit_id,
                COALESCE(u.username, 'System') as username,
                action_type,
                module_name,
                remarks,
                ip_address,
                DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time,
                'audit_trail' as source
            FROM audit_trail a
            LEFT JOIN users u ON a.user_id = u.user_id
            WHERE a.module_name = 'Authentication'
            " . (strpos($where, 'DATE(action_time)') !== false ? 
                "AND DATE(a.action_time) BETWEEN ? AND ?" : "") . "
            
            ORDER BY action_time DESC
            LIMIT ? OFFSET ?
        ";

        // Combine parameters for data query
        $dataParams = $params;
        $dataTypes = $types;
        if (!empty($params)) {
            $dataParams = array_merge($params, $params);
            $dataTypes = $types . $types;
        }
        $dataParams = array_merge($dataParams, [$limit, $offset]);
        $dataTypes .= 'ii';

        $stmt = executePreparedStatement($conn, $dataSql, $dataTypes, $dataParams);
        $result = $stmt->get_result();
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            // Remove the 'source' column before sending to frontend
            unset($row['source']);
            $rows[] = $row;
        }
        $stmt->close();

        // Log this access
        $userId = $_SESSION['userdata']['user_id'];
        $logSql = "INSERT INTO permission_logs (user_id, module_name, action_name, action_status, action_time) 
                   VALUES (?, 'permission_logs', 'View Logs', 'Success', NOW())";
        $logStmt = $conn->prepare($logSql);
        if ($logStmt) {
            $logStmt->bind_param('i', $userId);
            $logStmt->execute();
            $logStmt->close();
        }

        echo json_encode([
            'status' => 'ok',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ]);
        exit();
    }

    // -------------------------
    // CSV Export
    // -------------------------
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $start = trim($_GET['start'] ?? '');
        $end = trim($_GET['end'] ?? '');

        $params = [];
        $types = '';
        $where = buildWhereClause($start, $end, $params, $types);

        // ✅ Export combined data
        $sql = "
            SELECT 
                log_id as audit_id,
                COALESCE(u.username, 'System') as username,
                action_name as action_type,
                module_name,
                CONCAT('Status: ', action_status) as remarks,
                '' as ip_address,
                DATE_FORMAT(pl.action_time, '%Y-%m-%d %h:%i %p') as action_time
            FROM permission_logs pl
            LEFT JOIN users u ON pl.user_id = u.user_id
            $where
            
            UNION ALL
            
            SELECT 
                audit_id,
                COALESCE(u.username, 'System') as username,
                action_type,
                module_name,
                remarks,
                ip_address,
                DATE_FORMAT(a.action_time, '%Y-%m-%d %h:%i %p') as action_time
            FROM audit_trail a
            LEFT JOIN users u ON a.user_id = u.user_id
            WHERE a.module_name = 'Authentication'
            " . (strpos($where, 'DATE(action_time)') !== false ? 
                "AND DATE(a.action_time) BETWEEN ? AND ?" : "") . "
            
            ORDER BY action_time DESC
        ";

        $exportParams = $params;
        $exportTypes = $types;
        if (!empty($params)) {
            $exportParams = array_merge($params, $params);
            $exportTypes = $types . $types;
        }

        $stmt = executePreparedStatement($conn, $sql, $exportTypes, $exportParams);
        $result = $stmt->get_result();

        // Set CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        $filename = 'permission_logs_' . date('Y-m-d_His') . '.csv';
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, ['Audit ID', 'Username', 'Action', 'Module', 'Remarks', 'IP Address', 'Date/Time']);

        // CSV Data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['audit_id'],
                $row['username'],
                $row['action_type'],
                $row['module_name'],
                $row['remarks'],
                $row['ip_address'],
                $row['action_time']
            ]);
        }

        fclose($output);
        $stmt->close();

        // Log export action
        $userId = $_SESSION['userdata']['user_id'];
        $logSql = "INSERT INTO permission_logs (user_id, module_name, action_name, action_status, action_time) 
                   VALUES (?, 'permission_logs', 'Export CSV', 'Success', NOW())";
        $logStmt = $conn->prepare($logSql);
        if ($logStmt) {
            $logStmt->bind_param('i', $userId);
            $logStmt->execute();
            $logStmt->close();
        }

        exit();
    }

    // -------------------------
    // Invalid request
    // -------------------------
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
    exit();

} catch (Exception $e) {
    error_log("Permission Logs Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    header('Content-Type: application/json');
    
    $response = [
        'status' => 'error',
        'msg' => 'Server error occurred'
    ];
    
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        $response['details'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}
?>