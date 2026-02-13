<?php
/**
 * Send AI-Generated Message (Manual)
 * Path: /admin/Repayment-Tracker/send_ai_message.php   (adjust if needed)
 */

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Start buffering to prevent accidental output breaking JSON
ob_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/initialize_coreT2.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/classes/NotificationManager.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/classes/AIMessageGenerator.php');

// ✅ PHPMailer load (IMPORTANT: prevents fatal error -> HTML output)
$autoloadPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
foreach ($autoloadPaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        break;
    }
}

date_default_timezone_set('Asia/Manila');

function jsonResponse(int $code, array $payload): void
{
    // clean all buffers so response is PURE JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ Session start guard (fixes your "session already active" notice)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['userdata']['user_id'])) {
    jsonResponse(401, [
        'success' => false,
        'message' => 'Unauthorized. Please log in.',
    ]);
}

$userId = (int)$_SESSION['userdata']['user_id'];

try {
    // Ensure DB connection exists
    if (!isset($conn) || !$conn || $conn->connect_error) {
        throw new Exception('Database connection failed.');
    }

    // Read JSON body safely
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        // allow empty body for quick testing but still require loan_id
        $data = [];
    }

    $action = isset($data['action']) ? (string)$data['action'] : 'preview'; // preview | send
    $loanId = isset($data['loan_id']) ? (int)$data['loan_id'] : 0;
    $customMessage = isset($data['custom_message']) ? (string)$data['custom_message'] : '';

    if ($loanId <= 0) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Loan ID is required',
        ]);
    }

    // Fetch loan + member + next pending/overdue schedule
    $stmt = $conn->prepare("
        SELECT 
            lp.loan_id,
            lp.loan_code,
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
            m.contact_no,
            ls.schedule_id,
            ls.due_date,
            ls.amount_due,
            ls.balance,
            ls.status as payment_status,
            DATEDIFF(ls.due_date, CURDATE()) as days_until_due
        FROM loan_portfolio lp
        JOIN members m ON lp.member_id = m.member_id
        LEFT JOIN loan_schedule ls ON lp.loan_id = ls.loan_id
        WHERE lp.loan_id = ?
          AND ls.status IN ('Pending', 'Overdue')
        ORDER BY ls.due_date ASC
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare query.');
    }

    $stmt->bind_param('i', $loanId);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Loan not found or no pending payments',
        ]);
    }

    $loanData = $result->fetch_assoc();

    if (empty($loanData['email'])) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Member does not have an email address on file',
        ]);
    }

    // Determine message type
    $daysUntilDue = (int)$loanData['days_until_due'];
    $messageType = 'reminder';

    if ($daysUntilDue >= 7) {
        $messageType = '7_days_before';
    } elseif ($daysUntilDue >= 1 && $daysUntilDue < 7) {
        $messageType = '3_days_before';
    } elseif ($daysUntilDue === 0) {
        $messageType = 'due_today';
    } elseif ($daysUntilDue < 0) {
        $messageType = 'overdue';
    }

    // Generate AI message
    $aiGenerator = new AIMessageGenerator($conn);
    $aiMessage = $aiGenerator->generateMessage($loanData, $messageType);

    // Expect aiMessage array with subject/message
    $subject = is_array($aiMessage) && isset($aiMessage['subject']) ? (string)$aiMessage['subject'] : 'Payment Reminder';
    $generatedBody = is_array($aiMessage) && isset($aiMessage['message']) ? (string)$aiMessage['message'] : '';

    if ($action === 'preview') {
        jsonResponse(200, [
            'success' => true,
            'action' => 'preview',
            'loan_code' => $loanData['loan_code'] ?? '',
            'member_name' => $loanData['member_name'] ?? '',
            'member_email' => $loanData['email'] ?? '',
            'amount_due' => number_format((float)$loanData['amount_due'], 2),
            'due_date' => !empty($loanData['due_date']) ? date('F d, Y', strtotime($loanData['due_date'])) : '',
            'days_until_due' => $daysUntilDue,
            'message_type' => $messageType,
            'ai_message' => $aiMessage,
            'editable' => true,
        ]);
    }

    if ($action !== 'send') {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Invalid action. Use "preview" or "send".',
        ]);
    }

    // Send mode
    $messageBody = trim($customMessage) !== '' ? $customMessage : $generatedBody;

    if (trim($messageBody) === '') {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Message body is empty.',
        ]);
    }

    // Get SMTP settings
    $settingsQuery = $conn->query("SELECT setting_key, setting_value FROM notification_settings");
    $settings = [];
    if ($settingsQuery) {
        while ($row = $settingsQuery->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    $requiredKeys = ['smtp_host','smtp_username','smtp_password','smtp_port','smtp_from_email','smtp_from_name'];
    foreach ($requiredKeys as $k) {
        if (empty($settings[$k])) {
            throw new Exception("Missing SMTP setting: {$k}");
        }
    }

    // Send email via PHPMailer
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        throw new Exception('PHPMailer is not available. Check vendor/autoload.php.');
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    $bodyHtml = nl2br(htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8'));
    $bodyPlain = $messageBody;

    try {
        $mail->isSMTP();
        $mail->Host = (string)$settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string)$settings['smtp_username'];
        $mail->Password = (string)$settings['smtp_password'];
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$settings['smtp_port'];

        $mail->setFrom((string)$settings['smtp_from_email'], (string)$settings['smtp_from_name']);
        $mail->addAddress((string)$loanData['email'], (string)$loanData['member_name']);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = $bodyPlain;

        $mail->send();

        // Log to ai_message_logs
        $logStmt = $conn->prepare("
            INSERT INTO ai_message_logs (
                loan_id, member_id, message_type, message_content,
                ai_generated, sent_via, sent_at, status
            ) VALUES (?, ?, ?, ?, TRUE, 'email', NOW(), 'sent')
        ");
        if ($logStmt) {
            $fullMessage = $subject . "\n\n" . $messageBody;
            $mid = (int)$loanData['member_id'];
            $logStmt->bind_param('iiss', $loanId, $mid, $messageType, $fullMessage);
            $logStmt->execute();
        }

        // Log to email_notification_logs
        $emailLogStmt = $conn->prepare("
            INSERT INTO email_notification_logs (
                loan_id, loan_code, member_id, member_email,
                notification_type, subject, body, status,
                due_date, amount_due, sent_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', ?, ?, ?)
        ");
        if ($emailLogStmt) {
            $loanCode = (string)($loanData['loan_code'] ?? '');
            $mid = (int)$loanData['member_id'];
            $memberEmail = (string)$loanData['email'];
            $dueDate = (string)$loanData['due_date'];
            $amountDue = (float)$loanData['amount_due'];

            // types: i s i s s s s s d i  -> (we match your schema style)
            $emailLogStmt->bind_param(
                'isisssssddi',
                $loanId,
                $loanCode,
                $mid,
                $memberEmail,
                $messageType,
                $subject,
                $messageBody,
                $dueDate,
                $amountDue,
                $userId
            );
            $emailLogStmt->execute();
        }

        jsonResponse(200, [
            'success' => true,
            'action' => 'sent',
            'message' => 'AI-generated message sent successfully to ' . $loanData['email'],
            'loan_code' => $loanData['loan_code'] ?? '',
            'member_name' => $loanData['member_name'] ?? '',
            'message_type' => $messageType,
            'tone' => is_array($aiMessage) && isset($aiMessage['tone']) ? $aiMessage['tone'] : null
        ]);

    } catch (\Throwable $e) {
        // log failed attempt
        $logStmt = $conn->prepare("
            INSERT INTO ai_message_logs (
                loan_id, member_id, message_type, message_content,
                ai_generated, sent_via, sent_at, status, error_message
            ) VALUES (?, ?, ?, ?, TRUE, 'email', NOW(), 'failed', ?)
        ");
        if ($logStmt) {
            $fullMessage = $subject . "\n\n" . $messageBody;
            $mid = (int)$loanData['member_id'];
            $err = $e->getMessage();
            $logStmt->bind_param('iisss', $loanId, $mid, $messageType, $fullMessage, $err);
            $logStmt->execute();
        }

        jsonResponse(500, [
            'success' => false,
            'message' => 'Failed to send email: ' . $e->getMessage(),
        ]);
    }

} catch (\Throwable $e) {
    jsonResponse(500, [
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
