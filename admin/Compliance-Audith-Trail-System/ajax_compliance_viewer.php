<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');
header('Content-Type: application/json');

$query = "
  SELECT 
    a.audit_id,
    u.full_name,
    a.action_type,
    a.module_name,
    a.ip_address,
    a.remarks,
    c.compliance_status,
    DATE_FORMAT(c.review_date, '%Y-%m-%d %H:%i') AS review_date
  FROM audit_trail a
  LEFT JOIN users u ON a.user_id = u.user_id
  LEFT JOIN compliance_logs c ON a.audit_id = c.audit_id
  ORDER BY a.audit_id DESC
";

$result = $conn->query($query);
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}
echo json_encode($rows);
