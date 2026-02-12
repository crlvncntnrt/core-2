<?php
require_once(__DIR__ . '/../../initialize_coreT2.php');

function qval($conn, $sql)
{
    $res = $conn->query($sql);
    return ($res && $r = $res->fetch_assoc()) ? $r['val'] : 0;
}

echo "<h4>Database Snapshot</h4><ul>";
echo "<li>Total Active Members: " . qval($conn, "SELECT COUNT(*) AS val FROM members WHERE status='Active'") . "</li>";
echo "<li>Total Loans: " . qval($conn, "SELECT COUNT(*) AS val FROM loan_portfolio") . "</li>";
echo "<li>Active/Approved Loans: " . qval($conn, "SELECT COUNT(*) AS val FROM loan_portfolio WHERE status IN ('Active','Approved')") . "</li>";
echo "<li>Total Savings: ₱" . number_format(qval($conn, "SELECT IFNULL(SUM(amount),0) AS val FROM savings"), 2) . "</li>";
echo "<li>Total Disbursed (Released): ₱" . number_format(qval($conn, "SELECT IFNULL(SUM(amount),0) AS val FROM disbursements WHERE status='Released'"), 2) . "</li>";
echo "<li>Total Collections: ₱" . number_format(qval($conn, "SELECT IFNULL(SUM(amount_collected),0) AS val FROM collections"), 2) . "</li>";
echo "</ul>";

echo "<h5>Recent compliance logs</h5>";
$res = $conn->query("SELECT cl.compliance_id, cl.description, cl.compliance_status, cl.review_date, a.module_name, a.remarks, m.full_name FROM compliance_logs cl LEFT JOIN audit_trail a ON cl.audit_id = a.audit_id LEFT JOIN members m ON a.record_id = m.member_id ORDER BY cl.review_date DESC LIMIT 20");
if ($res && $res->num_rows) {
    echo "<table class='table table-sm table-striped'><thead><tr><th>ID</th><th>Member/module</th><th>Status</th><th>Date</th><th>Remarks</th></tr></thead><tbody>";
    while ($r = $res->fetch_assoc()) {
        $who = $r['full_name'] ?: '[' . $r['module_name'] . ']';
        echo "<tr><td>{$r['compliance_id']}</td><td>{$who}</td><td>{$r['compliance_status']}</td><td>{$r['review_date']}</td><td>{$r['remarks']}</td></tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<div class='text-muted'>No compliance logs found.</div>";
}
