<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

$response = [
    'status' => 'success',
    'total_members' => 0,
    'active_loans' => 0,
    'total_savings' => 0,
    'total_disbursed' => 0,
    'loan_chart' => ['labels' => [], 'values' => []],
    'collection_chart' => ['labels' => [], 'values' => []],
    'loan_disbursement_chart' => ['labels' => [], 'values' => []],
    'compliance_chart' => ['labels' => [], 'values' => []],
    'compliance_table_html' => '',
    'recent_audit_html' => ''
];

function scalar($conn, $sql) {
    $res = $conn->query($sql);
    return ($res && $r = $res->fetch_assoc()) ? (float)$r['val'] : 0;
}
function rows($conn, $sql) {
    $out = [];
    $res = $conn->query($sql);
    if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

try {
    // KPIs
    $response['total_members'] = (int) scalar($conn, "SELECT COUNT(*) AS val FROM members WHERE status='Active'");
    $response['active_loans'] = (int) scalar($conn, "SELECT COUNT(*) AS val FROM loan_portfolio WHERE status IN ('Active','Approved')");

    // Savings (view fallback)
    $v = $conn->query("SELECT 1 FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='v_member_savings'");
    if ($v && $v->num_rows > 0) {
        $response['total_savings'] = scalar($conn, "SELECT IFNULL(SUM(total_savings),0) AS val FROM v_member_savings");
    } else {
        $response['total_savings'] = scalar($conn, "SELECT IFNULL(SUM(amount),0) AS val FROM savings");
    }

    $response['total_disbursed'] = scalar($conn, "SELECT IFNULL(SUM(amount),0) AS val FROM disbursements WHERE status='Released'");

    // Loan Portfolio
    $loan_rows = rows($conn, "SELECT status AS label, COUNT(*) AS value FROM loan_portfolio GROUP BY status");
    $response['loan_chart']['labels'] = array_column($loan_rows, 'label');
    $response['loan_chart']['values'] = array_column($loan_rows, 'value');

    // Monthly collections and disbursements
    $year = date('Y');
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $coll = rows($conn, "SELECT MONTH(collection_date) AS m, SUM(amount_collected) AS total FROM collections WHERE YEAR(collection_date)={$year} GROUP BY MONTH(collection_date)");
    $collData = array_fill(1, 12, 0.0);
    foreach ($coll as $r) $collData[(int)$r['m']] = (float)$r['total'];
    $response['collection_chart'] = ['labels' => $months, 'values' => array_values($collData)];

    $disb = rows($conn, "SELECT MONTH(disbursement_date) AS m, SUM(amount) AS total FROM disbursements WHERE YEAR(disbursement_date)={$year} GROUP BY MONTH(disbursement_date)");
    $disbData = array_fill(1, 12, 0.0);
    foreach ($disb as $r) $disbData[(int)$r['m']] = (float)$r['total'];
    $response['loan_disbursement_chart'] = ['labels' => $months, 'values' => array_values($disbData)];

    // Compliance Chart
    $compRows = rows($conn, "SELECT compliance_status AS label, COUNT(*) AS value FROM compliance_logs GROUP BY compliance_status");
    $response['compliance_chart'] = [
        'labels' => array_column($compRows, 'label'),
        'values' => array_column($compRows, 'value')
    ];

    // Compliance Records Table (latest 20)
    $compTable = rows($conn, "
        SELECT cl.compliance_id, cl.compliance_status, cl.description, cl.review_date,
               a.module_name, a.remarks AS audit_remarks,
               m.full_name AS member_name
        FROM compliance_logs cl
        LEFT JOIN audit_trail a ON cl.audit_id = a.audit_id
        LEFT JOIN members m ON a.record_id = m.member_id
        ORDER BY cl.review_date DESC
        LIMIT 20
    ");
    if ($compTable) {
        $html = '<table class="table table-sm table-striped mb-0"><thead><tr>
                 <th>ID</th><th>Member / Module</th><th>Status</th><th>Review Date</th><th>Remarks</th></tr></thead><tbody>';
        foreach ($compTable as $r) {
            $who = $r['member_name'] ?: '[' . ($r['module_name'] ?? '-') . ']';
            $remarks = trim(($r['audit_remarks'] ?? '') . ' ' . ($r['description'] ?? ''));
            $html .= '<tr><td>' . htmlspecialchars($r['compliance_id']) . '</td>
                      <td>' . htmlspecialchars($who) . '</td>
                      <td>' . htmlspecialchars($r['compliance_status']) . '</td>
                      <td>' . htmlspecialchars($r['review_date']) . '</td>
                      <td>' . htmlspecialchars($remarks) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        $response['compliance_table_html'] = $html;
    } else {
        $response['compliance_table_html'] = '<div class="p-2 text-muted">No compliance records found.</div>';
    }

    // Recent Audit Trail
    $audits = rows($conn, "SELECT a.module_name,a.action_type,a.remarks,a.action_time,u.full_name
                           FROM audit_trail a
                           LEFT JOIN users u ON a.user_id=u.user_id
                           ORDER BY a.action_time DESC LIMIT 8");
    $html = "<ul class='list-group'>";
    foreach ($audits as $a) {
        $time = date('M d, Y H:i', strtotime($a['action_time']));
        $user = htmlspecialchars($a['full_name'] ?? 'Unknown');
        $act = htmlspecialchars($a['action_type'] ?? '-');
        $mod = htmlspecialchars($a['module_name'] ?? '-');
        $rem = htmlspecialchars($a['remarks'] ?? '');
        $html .= "<li class='list-group-item'><strong>{$user}</strong> - {$act} on {$mod}<br><small class='text-muted'>{$rem} | {$time}</small></li>";
    }
    $html .= "</ul>";
    $response['recent_audit_html'] = $html;

} catch (Throwable $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
