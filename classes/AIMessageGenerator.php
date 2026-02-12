<?php
/**
 * AI Message Generator Class
 * Generates context-aware, personalized payment reminder messages
 * Path: /classes/AIMessageGenerator.php
 */

class AIMessageGenerator {
    private $conn;
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }
    
    /**
     * Generate personalized message based on loan data and context
     * 
     * @param array $loanData - Loan and member information
     * @param string $messageType - '7_days_before', '3_days_before', 'due_today', 'overdue'
     * @return array - ['subject' => string, 'message' => string, 'tone' => string]
     */
    public function generateMessage($loanData, $messageType) {
        $memberName = $this->getFirstName($loanData['member_name']);
        $amountDue = number_format($loanData['amount_due'], 2);
        $dueDate = date('F d, Y', strtotime($loanData['due_date']));
        $loanCode = $loanData['loan_code'];
        
        // Get member's payment history to personalize tone
        $paymentHistory = $this->getPaymentHistory($loanData['member_id']);
        $tone = $this->determineTone($paymentHistory, $messageType);
        
        // Generate message based on type and tone
        $message = $this->buildMessage($memberName, $amountDue, $dueDate, $loanCode, $messageType, $tone, $paymentHistory);
        $subject = $this->buildSubject($memberName, $amountDue, $dueDate, $messageType);
        
        return [
            'subject' => $subject,
            'message' => $message,
            'tone' => $tone,
            'message_type' => $messageType
        ];
    }
    
    /**
     * Get member's payment history statistics
     */
    private function getPaymentHistory($memberId) {
        $stmt = $this->conn->prepare("
            SELECT 
                COALESCE(mr.total_on_time_payments, 0) as on_time_count,
                COALESCE(mr.consecutive_on_time, 0) as current_streak,
                COALESCE(mr.best_streak, 0) as best_streak,
                COALESCE(mr.tier, 'Bronze') as tier,
                COUNT(CASE WHEN ls.status = 'Overdue' THEN 1 END) as late_count
            FROM members m
            LEFT JOIN member_rewards mr ON m.member_id = mr.member_id
            LEFT JOIN loan_portfolio lp ON m.member_id = lp.member_id
            LEFT JOIN loan_schedule ls ON lp.loan_id = ls.loan_id
            WHERE m.member_id = ?
            GROUP BY m.member_id, mr.total_on_time_payments, mr.consecutive_on_time, mr.best_streak, mr.tier
        ");
        
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result ?: [
            'on_time_count' => 0,
            'current_streak' => 0,
            'best_streak' => 0,
            'tier' => 'Bronze',
            'late_count' => 0
        ];
    }
    
    /**
     * Determine message tone based on history and urgency
     */
    private function determineTone($history, $messageType) {
        // Excellent payers - always friendly and appreciative
        if ($history['on_time_count'] >= 10 && $history['late_count'] == 0) {
            return 'appreciative';
        }
        
        // Good payers with streak - encouraging
        if ($history['current_streak'] >= 3) {
            return 'encouraging';
        }
        
        // Regular payers - neutral friendly
        if ($history['on_time_count'] >= 5) {
            return 'friendly';
        }
        
        // New borrowers or some late payments - professional
        if ($history['late_count'] > 0 || $history['on_time_count'] < 5) {
            if ($messageType === 'overdue') {
                return 'firm_but_respectful';
            }
            return 'professional';
        }
        
        return 'friendly';
    }
    
    /**
     * Build personalized message
     */
    private function buildMessage($name, $amount, $dueDate, $loanCode, $type, $tone, $history) {
        $greeting = $this->getGreeting($tone);
        $body = $this->getMessageBody($name, $amount, $dueDate, $loanCode, $type, $tone, $history);
        $closing = $this->getClosing($tone);
        
        return $greeting . "\n\n" . $body . "\n\n" . $closing;
    }
    
    /**
     * Generate greeting based on tone
     */
    private function getGreeting($tone) {
        $greetings = [
            'appreciative' => 'Dear Valued Member,',
            'encouraging' => 'Hello!',
            'friendly' => 'Hi there!',
            'professional' => 'Good day,',
            'firm_but_respectful' => 'Dear Member,'
        ];
        
        return $greetings[$tone] ?? 'Hello,';
    }
    
    /**
     * Generate message body
     */
    private function getMessageBody($name, $amount, $dueDate, $loanCode, $type, $tone, $history) {
        switch ($type) {
            case '7_days_before':
                return $this->get7DayMessage($name, $amount, $dueDate, $loanCode, $tone, $history);
            
            case '3_days_before':
                return $this->get3DayMessage($name, $amount, $dueDate, $loanCode, $tone, $history);
            
            case 'due_today':
                return $this->getDueTodayMessage($name, $amount, $dueDate, $loanCode, $tone, $history);
            
            case 'overdue':
                return $this->getOverdueMessage($name, $amount, $dueDate, $loanCode, $tone, $history);
            
            default:
                return $this->getGenericMessage($name, $amount, $dueDate, $loanCode);
        }
    }
    
    /**
     * 7-day advance reminder messages
     */
    private function get7DayMessage($name, $amount, $dueDate, $loanCode, $tone, $history) {
        $messages = [
            'appreciative' => "We wanted to reach out to one of our most reliable members! Your excellent payment record of {$history['on_time_count']} on-time payments is truly appreciated.\n\nThis is just a friendly reminder that your payment of ₱{$amount} for Loan {$loanCode} is due on {$dueDate}. We know you'll handle it as smoothly as always!",
            
            'encouraging' => "Great job on maintaining your {$history['current_streak']}-payment streak! You're doing fantastic.\n\nJust a heads up that your payment of ₱{$amount} for Loan {$loanCode} is coming up on {$dueDate}. Keep up the excellent work!",
            
            'friendly' => "Hope you're doing well! This is a friendly reminder that your loan payment of ₱{$amount} (Loan {$loanCode}) is due on {$dueDate}.\n\nPlanning ahead helps ensure everything stays on track. We're here if you need any assistance!",
            
            'professional' => "This is a courtesy reminder that your loan payment is scheduled for {$dueDate}.\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\n\nPlease ensure sufficient funds are available by the due date. If you have any questions, please don't hesitate to contact us.",
            
            'firm_but_respectful' => "This is an important reminder regarding your upcoming loan payment.\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\nDue Date: {$dueDate}\n\nTimely payment helps maintain your good standing and avoids late fees. Please prioritize this payment."
        ];
        
        return $messages[$tone] ?? $messages['friendly'];
    }
    
    /**
     * 3-day urgent reminder messages
     */
    private function get3DayMessage($name, $amount, $dueDate, $loanCode, $tone, $history) {
        $messages = [
            'appreciative' => "Quick reminder for our valued member! Your payment of ₱{$amount} for Loan {$loanCode} is due in just 3 days on {$dueDate}.\n\nWe know you're on top of things - just wanted to make sure this doesn't slip through the cracks!",
            
            'encouraging' => "You're almost there! Just 3 days until your payment of ₱{$amount} is due on {$dueDate}.\n\nKeep that {$history['current_streak']}-payment streak going strong!",
            
            'friendly' => "Just a quick heads up - your payment of ₱{$amount} for Loan {$loanCode} is due in 3 days on {$dueDate}.\n\nIf you haven't already, please make arrangements to ensure timely payment. We're here to help if needed!",
            
            'professional' => "IMPORTANT: Your loan payment is due in 3 days.\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\nDue Date: {$dueDate}\n\nPlease arrange payment immediately to avoid late fees and maintain your account in good standing.",
            
            'firm_but_respectful' => "URGENT REMINDER: Your payment is due in 3 days.\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\nDue Date: {$dueDate}\n\nImmediate action is required. Late payments incur penalties and may affect your credit standing. Please prioritize this payment."
        ];
        
        return $messages[$tone] ?? $messages['friendly'];
    }
    
    /**
     * Due today messages
     */
    private function getDueTodayMessage($name, $amount, $dueDate, $loanCode, $tone, $history) {
        $messages = [
            'appreciative' => "Just a gentle reminder that today is the due date for your payment of ₱{$amount} (Loan {$loanCode}).\n\nWe appreciate your consistent reliability and wanted to make sure you didn't miss today's date!",
            
            'encouraging' => "Today's the day! Your payment of ₱{$amount} is due today for Loan {$loanCode}.\n\nOne more on-time payment to add to your impressive record!",
            
            'friendly' => "This is a friendly reminder that your payment of ₱{$amount} for Loan {$loanCode} is due TODAY ({$dueDate}).\n\nPlease process your payment as soon as possible to avoid any late fees. Thank you!",
            
            'professional' => "PAYMENT DUE TODAY\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\nDue Date: {$dueDate} (TODAY)\n\nPlease submit your payment immediately to avoid late fees and penalties.",
            
            'firm_but_respectful' => "FINAL NOTICE: Payment due TODAY\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\n\nThis payment is due today. Late payments will incur penalties and may affect your account status. Please pay immediately."
        ];
        
        return $messages[$tone] ?? $messages['friendly'];
    }
    
    /**
     * Overdue messages
     */
    private function getOverdueMessage($name, $amount, $dueDate, $loanCode, $tone, $history) {
        $daysOverdue = abs((int)((strtotime(date('Y-m-d')) - strtotime($dueDate)) / 86400));
        
        $messages = [
            'appreciative' => "We noticed that your payment of ₱{$amount} for Loan {$loanCode}, which was due on {$dueDate}, hasn't been received yet.\n\nThis is unusual for someone with your excellent track record. If there's any issue preventing payment, please reach out - we're here to help!",
            
            'encouraging' => "Your payment of ₱{$amount} for Loan {$loanCode} was due on {$dueDate} and is now {$daysOverdue} day(s) overdue.\n\nDon't let this break your good payment streak! Please submit payment as soon as possible.",
            
            'friendly' => "We wanted to let you know that your payment of ₱{$amount} for Loan {$loanCode} is now {$daysOverdue} day(s) overdue (due date was {$dueDate}).\n\nPlease submit payment immediately to minimize late fees. If you're experiencing difficulties, please contact us to discuss options.",
            
            'professional' => "OVERDUE PAYMENT NOTICE\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\nOriginal Due Date: {$dueDate}\nDays Overdue: {$daysOverdue}\n\nYour payment is overdue and accruing late fees. Please remit payment immediately. Contact our office if you need to arrange a payment plan.",
            
            'firm_but_respectful' => "SERIOUS NOTICE: OVERDUE PAYMENT\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\nDays Overdue: {$daysOverdue}\n\nYour account is seriously delinquent. Late fees are accruing daily. Failure to pay may result in collection actions and credit reporting. IMMEDIATE PAYMENT REQUIRED.\n\nIf you're unable to pay in full, contact us immediately to avoid further action."
        ];
        
        return $messages[$tone] ?? $messages['professional'];
    }
    
    /**
     * Generic fallback message
     */
    private function getGenericMessage($name, $amount, $dueDate, $loanCode) {
        return "This is a reminder regarding your loan payment.\n\nLoan Code: {$loanCode}\nAmount Due: ₱{$amount}\nDue Date: {$dueDate}\n\nPlease ensure timely payment. Thank you for your cooperation.";
    }
    
    /**
     * Generate closing based on tone
     */
    private function getClosing($tone) {
        $closings = [
            'appreciative' => "Thank you for being such a valued member of our community!\n\nWarm regards,\nMicrofinance EIS Team",
            
            'encouraging' => "You've got this!\n\nBest regards,\nMicrofinance EIS Team",
            
            'friendly' => "Thank you for your continued trust!\n\nBest regards,\nMicrofinance EIS Team",
            
            'professional' => "Thank you for your prompt attention to this matter.\n\nSincerely,\nMicrofinance EIS\nCollections Department",
            
            'firm_but_respectful' => "We expect immediate action on this matter.\n\nSincerely,\nMicrofinance EIS\nCollections Department\n\nFor payment arrangements, contact: [Contact Info]"
        ];
        
        return $closings[$tone] ?? $closings['friendly'];
    }
    
    /**
     * Build subject line
     */
    private function buildSubject($name, $amount, $dueDate, $type) {
        switch ($type) {
            case '7_days_before':
                return "Upcoming Payment Reminder - Due {$dueDate}";
            
            case '3_days_before':
                return "URGENT: Payment Due in 3 Days - ₱{$amount}";
            
            case 'due_today':
                return "PAYMENT DUE TODAY - ₱{$amount}";
            
            case 'overdue':
                return "OVERDUE NOTICE - Immediate Action Required";
            
            default:
                return "Loan Payment Reminder";
        }
    }
    
    /**
     * Get first name from full name
     */
    private function getFirstName($fullName) {
        $parts = explode(' ', trim($fullName));
        return $parts[0];
    }
    
    /**
     * Log generated message
     */
    public function logMessage($loanId, $memberId, $messageData, $status = 'generated') {
        $stmt = $this->conn->prepare("
            INSERT INTO ai_message_logs (
                loan_id, member_id, message_type, message_content, 
                ai_generated, sent_via, status
            ) VALUES (?, ?, ?, ?, TRUE, 'email', ?)
        ");
        
        $messageContent = $messageData['subject'] . "\n\n" . $messageData['message'];
        
        $stmt->bind_param(
            'iisss',
            $loanId,
            $memberId,
            $messageData['message_type'],
            $messageContent,
            $status
        );
        
        return $stmt->execute();
    }
}