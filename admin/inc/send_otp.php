<?php
// send_otp.php - OTP Email Sender for CORET2 System with DEBUGGING
// Place this in: /admin/inc/send_otp.php

// ✅ AGGRESSIVE FILE INCLUDE GUARD
if (defined('SEND_OTP_LOADED')) {
    error_log("❌ DUPLICATE FILE LOAD BLOCKED - send_otp.php already loaded!");
    error_log("Called from: " . debug_backtrace()[0]['file'] ?? 'unknown');
    return;
}
define('SEND_OTP_LOADED', true);
error_log("✅ send_otp.php loaded for the first time");

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Generate random OTP
 */
function generateOTP($length = 6) {
    $otp = rand(pow(10, $length - 1), pow(10, $length) - 1);
    error_log("🔢 generateOTP() called - Generated: $otp");
    return $otp;
}

/**
 * Send OTP Email with AGGRESSIVE duplicate prevention
 */
function sendOTPEmail($recipientEmail, $recipientName, $otp) {
    // ✅ LOG EVERY CALL
    error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    error_log("📧 sendOTPEmail() CALLED");
    error_log("   Email: $recipientEmail");
    error_log("   Name: $recipientName");
    error_log("   OTP: $otp");
    error_log("   Time: " . date('Y-m-d H:i:s'));
    
    // Log who called this function
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    error_log("   Called from:");
    foreach ($backtrace as $i => $trace) {
        if ($i === 0) continue; // Skip self
        error_log("     [$i] " . ($trace['file'] ?? 'unknown') . " line " . ($trace['line'] ?? '?'));
    }
    
    // ✅ STATIC GUARD - Prevent same email from being sent twice
    static $email_sent = [];
    $key = $recipientEmail . '_' . $otp;
    
    if (isset($email_sent[$key])) {
        error_log("❌ DUPLICATE EMAIL BLOCKED!");
        error_log("   Already sent this exact OTP to this email in this request");
        error_log("   Returning TRUE without sending");
        error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        return true; // Already sent
    }
    
    // Mark as sent IMMEDIATELY (before sending, to prevent race condition)
    $email_sent[$key] = microtime(true);
    error_log("✅ First call for this email+OTP combo - proceeding to send");
    
    $mail = new PHPMailer(true);

    try {
        error_log("📤 Initializing PHPMailer...");
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'microfinancecore@gmail.com';
        $mail->Password   = 'xmtjeqdoesrujaom';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('microfinancecore@gmail.com', 'CORET2 System');
        $mail->addAddress($recipientEmail, $recipientName);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Your Login OTP - CORET2 System';
        
        // HTML email template
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body {
                    font-family: "Segoe UI", Arial, sans-serif;
                    background-color: #f8f9fa;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px 20px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 600;
                }
                .content {
                    padding: 40px 30px;
                }
                .greeting {
                    font-size: 16px;
                    color: #333;
                    margin-bottom: 20px;
                }
                .otp-box {
                    background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
                    border: 2px dashed #667eea;
                    border-radius: 12px;
                    padding: 30px;
                    text-align: center;
                    margin: 30px 0;
                }
                .otp-label {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 10px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .otp-code {
                    font-size: 42px;
                    font-weight: bold;
                    color: #667eea;
                    letter-spacing: 10px;
                    font-family: "Courier New", monospace;
                }
                .expiry-info {
                    background-color: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 15px;
                    margin: 25px 0;
                    border-radius: 4px;
                }
                .expiry-info strong {
                    color: #856404;
                }
                .security-notice {
                    background-color: #f8d7da;
                    border-left: 4px solid #dc3545;
                    padding: 15px;
                    margin: 25px 0;
                    border-radius: 4px;
                }
                .security-notice strong {
                    color: #721c24;
                    display: block;
                    margin-bottom: 8px;
                }
                .security-notice ul {
                    margin: 8px 0;
                    padding-left: 20px;
                    color: #721c24;
                }
                .security-notice li {
                    margin: 5px 0;
                }
                .footer {
                    background-color: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    color: #6c757d;
                    font-size: 13px;
                    border-top: 1px solid #dee2e6;
                }
                .footer p {
                    margin: 5px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔐 Login Verification</h1>
                </div>
                
                <div class="content">
                    <div class="greeting">
                        <p>Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                        <p>You have requested to login to CORET2 System. Please use the One-Time Password (OTP) below to complete your login:</p>
                    </div>
                    
                    <div class="otp-box">
                        <div class="otp-label">Your OTP Code</div>
                        <div class="otp-code">' . $otp . '</div>
                    </div>
                    
                    <div class="expiry-info">
                        <strong>⏰ Expiry Notice:</strong>
                        <p style="margin: 8px 0 0 0; color: #856404;">This OTP will expire in <strong>2 minutes</strong>. Please enter it as soon as possible.</p>
                    </div>
                    
                    <div class="security-notice">
                        <strong>🛡️ Security Reminders:</strong>
                        <ul>
                            <li>Never share this OTP with anyone, including CORET2 staff</li>
                            <li>CORET2 will never ask for your OTP via phone or email</li>
                            <li>If you did not request this login, please ignore this email and contact support immediately</li>
                            <li>Change your password if you suspect unauthorized access</li>
                        </ul>
                    </div>
                    
                    <p style="color: #666; font-size: 14px; margin-top: 30px;">
                        If you did not attempt to login, please disregard this email or contact our support team.
                    </p>
                    
                    <p style="color: #333; margin-top: 25px;">
                        Best regards,<br>
                        <strong>CORET2 System Team</strong>
                    </p>
                </div>
                
                <div class="footer">
                    <p><strong>This is an automated email. Please do not reply.</strong></p>
                    <p>&copy; ' . date('Y') . ' CORET2 System. All rights reserved.</p>
                    <p style="margin-top: 10px; font-size: 11px;">
                        Login attempt from IP: ' . $_SERVER['REMOTE_ADDR'] . ' at ' . date('Y-m-d H:i:s') . '
                    </p>
                </div>
            </div>
        </body>
        </html>
        ';

        // Plain text version
        $mail->AltBody = "Hello $recipientName,\n\n"
                       . "Your OTP for CORET2 System login is: $otp\n\n"
                       . "This code will expire in 2 minutes.\n\n"
                       . "Security Reminders:\n"
                       . "- Never share this OTP with anyone\n"
                       . "- CORET2 will never ask for your OTP\n"
                       . "- If you did not request this, please ignore this email\n\n"
                       . "Best regards,\n"
                       . "CORET2 System Team\n\n"
                       . "This is an automated email. Please do not reply.";

        error_log("📤 Calling mail->send()...");
        $mail->send();
        error_log("✅ Email sent successfully!");
        error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        return true;
        
    } catch (Exception $e) {
        error_log("❌ PHPMailer Error: {$mail->ErrorInfo}");
        error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        return false;
    }
}

/**
 * Store OTP in database
 */
function storeOTP($user_id, $otp, $conn) {
    error_log("💾 storeOTP() called for user_id: $user_id");
    
    try {
        $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET otp_code = ?, 
                otp_expiry = ?, 
                otp_verified = 0 
            WHERE user_id = ?
        ");
        $stmt->bind_param('ssi', $otp_hash, $expiry, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            error_log("✅ OTP stored in database successfully");
        } else {
            error_log("❌ Failed to store OTP in database");
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("❌ Store OTP Error: " . $e->getMessage());
        return false;
    }
}

error_log("✅ send_otp.php fully loaded - all functions defined");
?>