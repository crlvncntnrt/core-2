<?php
/**
 * Send AI-Generated Message (Manual)
 * Path: /api/repayments/send_ai_message.php
 * Called when admin clicks "✏️ AI Message" button
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/initialize_coreT2.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/classes/NotificationManager.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/classes/AIMessageGenerator.php');

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
session_start();
if (!isset($_SESSION['userdata']['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please log in.'
    ]);
    exit;
}

$userId = $_SESSION['userdata']['user_id'];

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    $action = $data['action'] ?? 'preview'; // 'preview' or 'send'
    $loanId = $data['loan_id'] ?? null;
    $customMessage = $data['custom_message'] ?? null; // Optional: admin can override AI message
    
    if (!$loanId) {
        throw new Exception('Loan ID is required');
    }
    
    // Get loan and member details with next due payment
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
    
    $stmt->bind_param('i', $loanId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Loan not found or no pending payments');
    }
    
    $loanData = $result->fetch_assoc();
    
    // Check if member has email
    if (empty($loanData['email'])) {
        throw new Exception('Member does not have an email address on file');
    }
    
    // Determine message type based on days until due
    $daysUntilDue = (int)$loanData['days_until_due'];
    $messageType = 'reminder'; // default
    
    if ($daysUntilDue >= 7) {
        $messageType = '7_days_before';
    } elseif ($daysUntilDue >= 1 && $daysUntilDue < 7) {
        $messageType = '3_days_before';
    } elseif ($daysUntilDue == 0) {
        $messageType = 'due_today';
    } elseif ($daysUntilDue < 0) {
        $messageType = 'overdue';
    }
    
    // Initialize AI Message Generator
    $aiGenerator = new AIMessageGenerator($conn);
    
    // Generate AI message
    $aiMessage = $aiGenerator->generateMessage($loanData, $messageType);
    
    // If action is 'preview', just return the generated message
    if ($action === 'preview') {
        echo json_encode([
            'success' => true,
            'action' => 'preview',
            'loan_code' => $loanData['loan_code'],
            'member_name' => $loanData['member_name'],
            'member_email' => $loanData['email'],
            'amount_due' => number_format($loanData['amount_due'], 2),
            'due_date' => date('F d, Y', strtotime($loanData['due_date'])),
            'days_until_due' => $daysUntilDue,
            'message_type' => $messageType,
            'ai_message' => $aiMessage,
            'editable' => true
        ]);
        exit;
    }
    
    // If action is 'send', proceed with sending email
    if ($action === 'send') {
        // Use custom message if provided, otherwise use AI-generated
        if ($customMessage) {
            $subject = $aiMessage['subject'];
            $messageBody = $customMessage;
        } else {
            $subject = $aiMessage['subject'];
            $messageBody = $aiMessage['message'];
        }
        
        // Prepare data for NotificationManager
        $emailData = $loanData;
        $emailData['subject'] = $subject;
        $emailData['body'] = $messageBody;
        
        // Initialize Notification Manager and send email
        $notificationManager = new NotificationManager($conn);
        
        // Create simple HTML body
        $bodyHtml = nl2br(htmlspecialchars($messageBody));
        $bodyPlain = $messageBody;
        
        // Send email using NotificationManager's sendEmail method
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Get SMTP settings
            $settingsQuery = $conn->query("SELECT setting_key, setting_value FROM notification_settings");
            $settings = [];
            while ($row = $settingsQuery->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Configure PHPMailer
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_username'];
            $mail->Password = $settings['smtp_password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $settings['smtp_port'];
            
            $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
            $mail->addAddress($loanData['email'], $loanData['member_name']);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = $bodyPlain;
            
            $mail->send();
            
            // Log the message
            $logStmt = $conn->prepare("
                INSERT INTO ai_message_logs (
                    loan_id, member_id, message_type, message_content,
                    ai_generated, sent_via, sent_at, status
                ) VALUES (?, ?, ?, ?, TRUE, 'email', NOW(), 'sent')
            ");
            
            $fullMessage = $subject . "\n\n" . $messageBody;
            $logStmt->bind_param('iiss', $loanId, $loanData['member_id'], $messageType, $fullMessage);
            $logStmt->execute();
            
            // Also log in email_notification_logs for consistency
            $emailLogStmt = $conn->prepare("
                INSERT INTO email_notification_logs (
                    loan_id, loan_code, member_id, member_email,
                    notification_type, subject, body, status,
                    due_date, amount_due, sent_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', ?, ?, ?)
            ");
            
            $emailLogStmt->bind_param(
                'isissssddi',
                $loanId,
                $loanData['loan_code'],
                $loanData['member_id'],
                $loanData['email'],
                $messageType,
                $subject,
                $messageBody,
                $loanData['due_date'],
                $loanData['amount_due'],
                $userId
            );
            $emailLogStmt->execute();
            
            echo json_encode([
                'success' => true,
                'action' => 'sent',
                'message' => 'AI-generated message sent successfully to ' . $loanData['email'],
                'loan_code' => $loanData['loan_code'],
                'member_name' => $loanData['member_name'],
                'message_type' => $messageType,
                'tone' => $aiMessage['tone']
            ]);
            
        } catch (Exception $e) {
            // Log failed attempt
            $logStmt = $conn->prepare("
                INSERT INTO ai_message_logs (
                    loan_id, member_id, message_type, message_content,
                    ai_generated, sent_via, sent_at, status, error_message
                ) VALUES (?, ?, ?, ?, TRUE, 'email', NOW(), 'failed', ?)
            ");
            
            $fullMessage = $subject . "\n\n" . $messageBody;
            $errorMsg = $mail->ErrorInfo;
            $logStmt->bind_param('iisss', $loanId, $loanData['member_id'], $messageType, $fullMessage, $errorMsg);
            $logStmt->execute();
            
            throw new Exception('Failed to send email: ' . $mail->ErrorInfo);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}