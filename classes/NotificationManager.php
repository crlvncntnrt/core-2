<?php
/**
 * Notification Manager Class
 * Handles email notifications for loan due dates
 * Path: classes/NotificationManager.php
 */

// Load PHPMailer from /inc/PHPMailer/ instead of vendor
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/inc/PHPMailer/PHPMailer.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/inc/PHPMailer/SMTP.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/inc/PHPMailer/Exception.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationManager {
    private $conn;
    private $settings = [];
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
        $this->loadSettings();
    }
    
    /**
     * Load settings from database
     */
    private function loadSettings() {
        $result = $this->conn->query("SELECT setting_key, setting_value FROM notification_settings");
        while ($row = $result->fetch_assoc()) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    /**
     * Check if auto notifications are enabled
     */
    public function isAutoEnabled() {
        return ($this->settings['auto_notifications_enabled'] ?? 'false') === 'true';
    }
    
    /**
     * Get loans due in X days
     */
    public function getLoansDueIn($days) {
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));
        
        $stmt = $this->conn->prepare("
            SELECT DISTINCT
                ls.loan_id,
                ls.loan_code,
                ls.due_date,
                ls.amount_due,
                ls.balance,
                lp.member_id,
                m.full_name as member_name,
                m.email,
                m.contact_no
            FROM loan_schedule ls
            JOIN loan_portfolio lp ON ls.loan_id = lp.loan_id
            JOIN members m ON lp.member_id = m.member_id
            WHERE ls.due_date = ?
            AND ls.status = 'Pending'
            AND m.email IS NOT NULL
            AND m.email != ''
            AND NOT EXISTS (
                SELECT 1 FROM email_notification_logs enl
                WHERE enl.loan_id = ls.loan_id
                AND enl.due_date = ls.due_date
                AND enl.notification_type = ?
                AND DATE(enl.sent_at) = CURDATE()
            )
        ");
        
        $notificationType = $days === 7 ? '7_days_before' : '3_days_before';
        $stmt->bind_param('ss', $targetDate, $notificationType);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get email template
     */
    private function getTemplate($type) {
        $stmt = $this->conn->prepare("
            SELECT * FROM email_templates 
            WHERE template_type = ? AND is_active = TRUE 
            LIMIT 1
        ");
        $stmt->bind_param('s', $type);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Replace template variables
     */
    private function replaceVariables($text, $data) {
        foreach ($data as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendEmail($to, $subject, $bodyHtml, $bodyPlain) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['smtp_username'];
            $mail->Password = $this->settings['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->settings['smtp_port'];
            
            // Recipients
            $mail->setFrom(
                $this->settings['smtp_from_email'], 
                $this->settings['smtp_from_name']
            );
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = $bodyPlain;
            
            $mail->send();
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $mail->ErrorInfo
            ];
        }
    }
    
    /**
     * Log notification
     */
    private function logNotification($loanData, $type, $status, $error = null, $sentBy = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO email_notification_logs (
                loan_id, loan_code, member_id, member_email,
                notification_type, subject, body, status,
                error_message, due_date, amount_due, sent_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            'isissssssddi',
            $loanData['loan_id'],
            $loanData['loan_code'],
            $loanData['member_id'],
            $loanData['email'],
            $type,
            $loanData['subject'] ?? '',
            $loanData['body'] ?? '',
            $status,
            $error,
            $loanData['due_date'],
            $loanData['amount_due'],
            $sentBy
        );
        
        return $stmt->execute();
    }
    
    /**
     * Send notification for a loan
     */
    public function sendNotification($loanData, $type, $sentBy = null) {
        // Get template
        $template = $this->getTemplate($type);
        if (!$template) {
            return [
                'success' => false,
                'error' => 'Template not found'
            ];
        }
        
        // Prepare variables
        $variables = [
            'member_name' => $loanData['member_name'],
            'loan_code' => $loanData['loan_code'],
            'amount_due' => number_format($loanData['amount_due'], 2),
            'due_date' => date('F d, Y', strtotime($loanData['due_date'])),
            'balance' => number_format($loanData['balance'] ?? $loanData['amount_due'], 2),
            'custom_message' => $loanData['custom_message'] ?? ''
        ];
        
        // Replace variables in template
        $subject = $this->replaceVariables($template['subject'], $variables);
        $bodyHtml = $this->replaceVariables($template['body_html'], $variables);
        $bodyPlain = $this->replaceVariables($template['body_plain'], $variables);
        
        // Send email
        $result = $this->sendEmail(
            $loanData['email'],
            $subject,
            $bodyHtml,
            $bodyPlain
        );
        
        // Log result
        $loanData['subject'] = $subject;
        $loanData['body'] = $bodyPlain;
        
        $this->logNotification(
            $loanData,
            $type,
            $result['success'] ? 'sent' : 'failed',
            $result['error'] ?? null,
            $sentBy
        );
        
        return $result;
    }
    
    /**
     * Process automatic notifications
     */
    public function processAutomaticNotifications() {
        if (!$this->isAutoEnabled()) {
            return [
                'success' => false,
                'message' => 'Auto notifications are disabled'
            ];
        }
        
        $results = [
            '7_days' => ['sent' => 0, 'failed' => 0],
            '3_days' => ['sent' => 0, 'failed' => 0]
        ];
        
        // Process 7-day reminders
        if (($this->settings['7_days_before_enabled'] ?? 'true') === 'true') {
            $loans = $this->getLoansDueIn(7);
            foreach ($loans as $loan) {
                $result = $this->sendNotification($loan, '7_days_before');
                if ($result['success']) {
                    $results['7_days']['sent']++;
                } else {
                    $results['7_days']['failed']++;
                }
            }
        }
        
        // Process 3-day reminders
        if (($this->settings['3_days_before_enabled'] ?? 'true') === 'true') {
            $loans = $this->getLoansDueIn(3);
            foreach ($loans as $loan) {
                $result = $this->sendNotification($loan, '3_days_before');
                if ($result['success']) {
                    $results['3_days']['sent']++;
                } else {
                    $results['3_days']['failed']++;
                }
            }
        }
        
        return [
            'success' => true,
            'results' => $results,
            'total_sent' => $results['7_days']['sent'] + $results['3_days']['sent'],
            'total_failed' => $results['7_days']['failed'] + $results['3_days']['failed']
        ];
    }
    
    /**
     * Get notification statistics
     */
    public function getStatistics($days = 7) {
        $since = date('Y-m-d', strtotime("-{$days} days"));
        
        $stmt = $this->conn->prepare("
            SELECT 
                notification_type,
                status,
                COUNT(*) as count
            FROM email_notification_logs
            WHERE DATE(sent_at) >= ?
            GROUP BY notification_type, status
        ");
        
        $stmt->bind_param('s', $since);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}