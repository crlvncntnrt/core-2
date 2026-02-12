<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../initialize_coreT2.php');
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Invalid request'];

try {
    if ($action === 'insert') {
        $audit_id = (int) ($_POST['audit_id'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $status = trim($_POST['compliance_status'] ?? '');
        $review = $_POST['review_date'] ?? date('Y-m-d');

        if (!$status) throw new Exception("Compliance status required");

        $stmt = $conn->prepare("INSERT INTO compliance_logs (audit_id, description, compliance_status, review_date)
                                VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $audit_id, $desc, $status, $review);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response = ['status' => 'success', 'message' => 'Compliance record added successfully'];
        } else {
            throw new Exception("Insert failed");
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
