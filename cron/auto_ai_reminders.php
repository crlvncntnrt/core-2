<?php
/**
 * Automatic AI Payment Reminders Cron Job
 * Path: /cron/auto_ai_reminders.php
 *
 * Schedule: Run daily at 8:00 AM
 * cPanel Cron Command:
 * 0 8 * * * /usr/bin/php /path/to/htdocs/core2/cron/auto_ai_reminders.php
 *
 * OR via Windows Task Scheduler:
 * C:\xampp\php\php.exe C:\xampp\htdocs\core2\cron\auto_ai_reminders.php
 */

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/ai_reminders_error.log');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Initialize
require_once(__DIR__ . '/../initialize_coreT2.php');
require_once(__DIR__ . '/../classes/AIMessageGenerator.php');
require_once(__DIR__ . '/../admin/inc/PHPMailer/PHPMailer.php');
require_once(__DIR__ . '/../admin/inc/PHPMailer/SMTP.php');
require_once(__DIR__ . '/../admin/inc/PHPMailer/Exception.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Log file for this run
$logFile = $logsDir . '/auto_reminders_' . date('Y-m-d') . '.log';

function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

logMessage("=== Automatic AI Reminders Cron Job Started ===", $logFile);

try {
    // Load settings
    $settingsQuery = $conn->query("SELECT setting_key, setting_value FROM notification_settings");
    $settings = [];
    while ($row = $settingsQuery->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Make settings available inside sendAIMessage()
    $GLOBALS['settings'] = $settings;

    if (($settings['auto_notifications_enabled'] ?? 'false') !== 'true') {
        logMessage("Auto notifications are disabled in settings. Exiting.", $logFile);
        exit(0);
    }

    logMessage("Auto notifications are enabled. Proceeding...", $logFile);

    // Initialize AI Generator
    $aiGenerator = new AIMessageGenerator($conn);

    // Statistics
    $stats = [
        '7_days_before'     => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
        '3_days_before'     => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
        'due_today'         => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
        'overdue_followup'  => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
    ];

    // Configure PHPMailer
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $settings['smtp_host'] ?? '';
    $mail->SMTPAuth = true;
    $mail->Username = $settings['smtp_username'] ?? '';
    $mail->Password = $settings['smtp_password'] ?? '';

    // If you add smtp_encryption setting later, this supports it.
    // Default to TLS like your original code.
    $enc = strtolower(trim($settings['smtp_encryption'] ?? 'tls'));
    if ($enc === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->Port = (int)($settings['smtp_port'] ?? 587);
    $mail->setFrom($settings['smtp_from_email'] ?? '', $settings['smtp_from_name'] ?? 'System');

    logMessage("SMTP configured: {$mail->Host}:{$mail->Port}", $logFile);

    // ========================================================================
    // 1. PROCESS 7-DAY ADVANCE REMINDERS
    // ========================================================================
    if (($settings['7_days_before_enabled'] ?? 'true') === 'true') {
        logMessage("\n--- Processing 7-Day Advance Reminders ---", $logFile);

        $date7Days = date('Y-m-d', strtotime('+7 days'));

        $stmt = $conn->prepare("
            SELECT DISTINCT
                lp.loan_id,
                lp.loan_code,
                lp.member_id,
                m.full_name as member_name,
                m.email,
                ls.due_date,
                ls.amount_due,
                ls.balance
            FROM loan_schedule ls
            JOIN loan_portfolio lp ON ls.loan_id = lp.loan_id
            JOIN members m ON lp.member_id = m.member_id
            WHERE ls.due_date = ?
            AND ls.status = 'Pending'
            AND m.email IS NOT NULL
            AND m.email != ''
            AND NOT EXISTS (
                SELECT 1 FROM ai_message_logs aml
                WHERE aml.loan_id = lp.loan_id
                AND aml.message_type = '7_days_before'
                AND DATE(aml.sent_at) = CURDATE()
            )
        ");

        $stmt->bind_param('s', $date7Days);
        $stmt->execute();
        $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        logMessage("Found " . count($loans) . " loans due in 7 days", $logFile);

        foreach ($loans as $loan) {
            $result = sendAIMessage($loan, '7_days_before', $aiGenerator, $mail, $conn, $logFile);
            $stats['7_days_before'][$result]++;
            usleep(500000);
        }
    }

    // ========================================================================
    // 2. PROCESS 3-DAY URGENT REMINDERS
    // ========================================================================
    if (($settings['3_days_before_enabled'] ?? 'true') === 'true') {
        logMessage("\n--- Processing 3-Day Urgent Reminders ---", $logFile);

        $date3Days = date('Y-m-d', strtotime('+3 days'));

        $stmt = $conn->prepare("
            SELECT DISTINCT
                lp.loan_id,
                lp.loan_code,
                lp.member_id,
                m.full_name as member_name,
                m.email,
                ls.due_date,
                ls.amount_due,
                ls.balance
            FROM loan_schedule ls
            JOIN loan_portfolio lp ON ls.loan_id = lp.loan_id
            JOIN members m ON lp.member_id = m.member_id
            WHERE ls.due_date = ?
            AND ls.status = 'Pending'
            AND m.email IS NOT NULL
            AND m.email != ''
            AND NOT EXISTS (
                SELECT 1 FROM ai_message_logs aml
                WHERE aml.loan_id = lp.loan_id
                AND aml.message_type = '3_days_before'
                AND DATE(aml.sent_at) = CURDATE()
            )
        ");

        $stmt->bind_param('s', $date3Days);
        $stmt->execute();
        $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        logMessage("Found " . count($loans) . " loans due in 3 days", $logFile);

        foreach ($loans as $loan) {
            $result = sendAIMessage($loan, '3_days_before', $aiGenerator, $mail, $conn, $logFile);
            $stats['3_days_before'][$result]++;
            usleep(500000);
        }
    }

    // ========================================================================
    // 3. PROCESS DUE TODAY REMINDERS
    // ========================================================================
    logMessage("\n--- Processing Due Today Reminders ---", $logFile);

    $dateToday = date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT DISTINCT
            lp.loan_id,
            lp.loan_code,
            lp.member_id,
            m.full_name as member_name,
            m.email,
            ls.due_date,
            ls.amount_due,
            ls.balance
        FROM loan_schedule ls
        JOIN loan_portfolio lp ON ls.loan_id = lp.loan_id
        JOIN members m ON lp.member_id = m.member_id
        WHERE ls.due_date = ?
        AND ls.status = 'Pending'
        AND m.email IS NOT NULL
        AND m.email != ''
        AND NOT EXISTS (
            SELECT 1 FROM ai_message_logs aml
            WHERE aml.loan_id = lp.loan_id
            AND aml.message_type = 'due_today'
            AND DATE(aml.sent_at) = CURDATE()
        )
    ");

    $stmt->bind_param('s', $dateToday);
    $stmt->execute();
    $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    logMessage("Found " . count($loans) . " loans due today", $logFile);

    foreach ($loans as $loan) {
        $result = sendAIMessage($loan, 'due_today', $aiGenerator, $mail, $conn, $logFile);
        $stats['due_today'][$result]++;
        usleep(500000);
    }

    // ========================================================================
    // 4. PROCESS OVERDUE FOLLOW-UP REMINDERS (7+ days overdue only)
    // ========================================================================
    logMessage("\n--- Processing Overdue Follow-up Reminders (7+ days) ---", $logFile);

    $stmt = $conn->prepare("
        SELECT DISTINCT
            lp.loan_id,
            lp.loan_code,
            lp.member_id,
            m.full_name as member_name,
            m.email,
            ls.due_date,
            ls.amount_due,
            ls.balance
        FROM loan_schedule ls
        JOIN loan_portfolio lp ON ls.loan_id = lp.loan_id
        JOIN members m ON lp.member_id = m.member_id
        WHERE ls.status = 'Overdue'
        AND DATEDIFF(CURDATE(), ls.due_date) >= 7
        AND m.email IS NOT NULL
        AND m.email != ''
        AND NOT EXISTS (
            SELECT 1 FROM ai_message_logs aml
            WHERE aml.loan_id = lp.loan_id
            AND aml.message_type = 'overdue_followup'
        )
    ");

    $stmt->execute();
    $loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    logMessage("Found " . count($loans) . " loans overdue 7+ days (first follow-up)", $logFile);

    foreach ($loans as $loan) {
        $result = sendAIMessage($loan, 'overdue_followup', $aiGenerator, $mail, $conn, $logFile);
        $stats['overdue_followup'][$result]++;
        usleep(500000);
    }

    // ========================================================================
    // SUMMARY
    // ========================================================================
    logMessage("\n=== SUMMARY ===", $logFile);

    $totalSent = 0;
    $totalFailed = 0;
    $totalSkipped = 0;

    foreach ($stats as $type => $counts) {
        $totalSent += $counts['sent'];
        $totalFailed += $counts['failed'];
        $totalSkipped += $counts['skipped'];

        if ($counts['sent'] > 0 || $counts['failed'] > 0 || $counts['skipped'] > 0) {
            logMessage("{$type}: {$counts['sent']} sent, {$counts['failed']} failed, {$counts['skipped']} skipped", $logFile);
        }
    }

    logMessage("\nTOTAL: {$totalSent} sent, {$totalFailed} failed, {$totalSkipped} skipped", $logFile);
    logMessage("=== Automatic AI Reminders Completed Successfully ===\n", $logFile);

    exit(0);

} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage(), $logFile);
    logMessage("Stack trace: " . $e->getTraceAsString(), $logFile);
    exit(1);
}

/**
 * Send AI-generated message for a single loan
 */
function sendAIMessage($loanData, $messageType, $aiGenerator, $mail, $conn, $logFile) {
    try {
        // Generate AI message
        $aiMessage = $aiGenerator->generateMessage($loanData, $messageType);

        // Clear previous recipients
        $mail->clearAddresses();
        $mail->addAddress($loanData['email'], $loanData['member_name']);

        // Set subject and body
        $mail->Subject = $aiMessage['subject'];
        $mail->Body = nl2br(htmlspecialchars($aiMessage['message']));
        $mail->AltBody = $aiMessage['message'];
        $mail->isHTML(true);

        // ----------------------------
        // Rate limits (safe add-on)
        // ----------------------------
        $dailyLimit = (int)($GLOBALS['settings']['daily_email_limit'] ?? 100);
        $perLoanLimit = (int)($GLOBALS['settings']['rate_limit_per_loan'] ?? 3);

        // Count emails sent today
        $dailyCountStmt = $conn->query("SELECT COUNT(*) as cnt FROM ai_message_logs WHERE DATE(sent_at)=CURDATE() AND status='sent'");
        $dailyCount = (int)($dailyCountStmt->fetch_assoc()['cnt'] ?? 0);

        if ($dailyCount >= $dailyLimit) {
            logMessage("↷ Skipped (daily limit reached: {$dailyCount}/{$dailyLimit})", $logFile);
            return 'skipped';
        }

        // Count emails sent today for this loan
        $loanCountStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM ai_message_logs WHERE loan_id=? AND DATE(sent_at)=CURDATE() AND status='sent'");
        $loanCountStmt->bind_param('i', $loanData['loan_id']);
        $loanCountStmt->execute();
        $loanCount = (int)($loanCountStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

        if ($loanCount >= $perLoanLimit) {
            logMessage("↷ Skipped (loan daily limit reached: Loan {$loanData['loan_code']} {$loanCount}/{$perLoanLimit})", $logFile);
            return 'skipped';
        }

        // Send email
        $mail->send();

        // Log success
        $logStmt = $conn->prepare("
            INSERT INTO ai_message_logs (
                loan_id, member_id, message_type, message_content,
                ai_generated, sent_via, sent_at, status
            ) VALUES (?, ?, ?, ?, TRUE, 'email', NOW(), 'sent')
        ");

        $fullMessage = $aiMessage['subject'] . "\n\n" . $aiMessage['message'];
        $logStmt->bind_param('iiss', $loanData['loan_id'], $loanData['member_id'], $messageType, $fullMessage);
        $logStmt->execute();

        logMessage("✓ Sent to {$loanData['member_name']} ({$loanData['email']}) - Loan {$loanData['loan_code']} - Tone: {$aiMessage['tone']}", $logFile);

        return 'sent';

    } catch (Exception $e) {
        // Log failure
        $logStmt = $conn->prepare("
            INSERT INTO ai_message_logs (
                loan_id, member_id, message_type, message_content,
                ai_generated, sent_via, sent_at, status, error_message
            ) VALUES (?, ?, ?, ?, TRUE, 'email', NOW(), 'failed', ?)
        ");

        $fullMessage = ($aiMessage['subject'] ?? 'Error') . "\n\n" . ($aiMessage['message'] ?? 'Failed to generate');
        $errorMsg = $e->getMessage();
        $logStmt->bind_param('iisss', $loanData['loan_id'], $loanData['member_id'], $messageType, $fullMessage, $errorMsg);
        $logStmt->execute();

        logMessage("✗ Failed to send to {$loanData['member_name']} - Error: {$errorMsg}", $logFile);

        return 'failed';
    }
}
