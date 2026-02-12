<?php
require_once('../initialize_coreT2.php');
require_once(__DIR__ . '/inc/log_audit_trial.php');

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if not coming from login
if (!isset($_SESSION['otp_user_id']) || !isset($_SESSION['otp_sent_time'])) {
    header("Location: login.php");
    exit();
}

// ✅ UPDATED: Check if OTP session has expired (2 minutes = 120 seconds)
if (time() - $_SESSION['otp_sent_time'] > 120) {
    unset($_SESSION['otp_user_id']);
    unset($_SESSION['otp_username']);
    unset($_SESSION['otp_sent_time']);
    
    echo '<script>
        window.location.href = "login.php";
        alert("OTP session expired. Please login again.");
    </script>';
    exit();
}

$error_message = "";
$success_message = "";

// Helper function to log to BOTH tables
function log_to_both_tables($user_id, $action, $module, $remarks, $status = 'Success') {
    global $conn;
    
    // Log to audit_trail
    log_audit_trial($user_id, $action, $module, $remarks);
    
    // Log to permission_logs
    try {
        $stmt = $conn->prepare("
            INSERT INTO permission_logs (user_id, module_name, action_name, action_status, action_time)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('isss', $user_id, $module, $action, $status);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Permission log error: " . $e->getMessage());
    }
}

// Process OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');
    $user_id = $_SESSION['otp_user_id'];

    if (empty($entered_otp)) {
        $error_message = "Please enter the OTP code.";
    } else {
        // Get user's OTP from database
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Check if OTP has expired
            if (strtotime($user['otp_expiry']) < time()) {
                $error_message = "OTP has expired. Please request a new one.";
                
                log_to_both_tables(
                    $user_id,
                    'OTP Verification Failed - Expired',
                    'Authentication',
                    'OTP expired',
                    'Failed'
                );

                // Clear session
                unset($_SESSION['otp_user_id']);
                unset($_SESSION['otp_username']);
                unset($_SESSION['otp_sent_time']);

                echo '<script>
                    setTimeout(function() {
                        window.location.href = "login.php";
                    }, 2000);
                </script>';
                
            } elseif (password_verify($entered_otp, $user['otp_code'])) {
                // ✅ OTP is correct - Complete login
                
                // Mark OTP as verified
                $update_stmt = $conn->prepare("UPDATE users SET otp_verified = 1 WHERE user_id = ?");
                $update_stmt->bind_param("i", $user_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Create full session
                session_regenerate_id(true);

                $_SESSION['userdata'] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'] ?? 'User',
                    'role' => $user['role'] ?? 'Member'
                ];

                $_SESSION['last_activity'] = time();
                $_SESSION['session_start'] = time();
                $_SESSION['login_success'] = "Welcome back, " . ($user['full_name'] ?? 'User') . "!";

                // Clear OTP session data
                unset($_SESSION['otp_user_id']);
                unset($_SESSION['otp_username']);
                unset($_SESSION['otp_sent_time']);

                // Log successful verification
                log_to_both_tables(
                    $user['user_id'],
                    'OTP Verified - Login Complete',
                    'Authentication',
                    'User successfully verified OTP and logged in from IP: ' . $_SERVER['REMOTE_ADDR'],
                    'Success'
                );

                // Redirect to dashboard
                $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                    . "://" . $_SERVER['HTTP_HOST'] . "/admin/dashboard.php";
                
                header("Location: " . $redirect_url);
                exit();
                
            } else {
                $error_message = "Invalid OTP code. Please try again.";
                
                log_to_both_tables(
                    $user_id,
                    'OTP Verification Failed - Wrong Code',
                    'Authentication',
                    'Incorrect OTP entered',
                    'Failed'
                );
            }
        } else {
            $error_message = "User not found.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - <?= $_settings->info('system_name') ?? 'Microfinance HR' ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --brand-primary: #059669;
            --brand-primary-hover: #047857;
            --brand-light: #10b981;
            --brand-dark: #065f46;
        }

        body {
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Segoe UI", sans-serif;
            position: relative;
            overflow: hidden;
        }

        /* Floating shapes background */
        .shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0));
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .shape-1 { width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s; }
        .shape-2 { width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: 3s; }
        .shape-3 { width: 150px; height: 150px; top: 50%; right: 10%; animation-delay: 6s; }

        /* Verify card */
        .verify-card {
            position: relative;
            z-index: 10;
            max-width: 500px;
            width: 90%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 2rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            padding: 3rem 2rem;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Icon container */
        .verify-icon-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(5, 150, 105, 0.3);
            animation: pulse 2s ease infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .verify-icon {
            font-size: 3rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .verify-title {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .verify-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* OTP Inputs */
        .otp-input-group {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin: 2rem 0;
        }

        .otp-input {
            width: 3.5rem;
            height: 4rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
            color: var(--brand-primary);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .otp-input:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1), 0 4px 12px rgba(0, 0, 0, 0.1);
            outline: none;
            transform: scale(1.05);
        }

        .otp-input:not(:placeholder-shown) {
            border-color: var(--brand-light);
            background: #f0fdf4;
        }

        /* Button */
        .btn-verify {
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-light));
            border: none;
            border-radius: 0.75rem;
            padding: 1rem;
            font-weight: 700;
            color: white;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
        }

        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(5, 150, 105, 0.4);
            background: linear-gradient(135deg, var(--brand-primary-hover), var(--brand-primary));
        }

        .btn-verify:active {
            transform: translateY(0);
        }

        /* Info box */
        .info-box {
            background: linear-gradient(135deg, #dbeafe, #e0f2fe);
            border-left: 4px solid #3b82f6;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            margin: 1.5rem 0;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
        }

        .info-box p {
            margin: 0;
            color: #1e40af;
            font-size: 0.9rem;
        }

        /* Timer */
        .timer-box {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            margin: 1.5rem 0;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
        }

        .timer-label {
            color: #7f1d1d;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .countdown {
            color: #dc2626;
            font-size: 1.75rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        /* Back link */
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link a {
            color: var(--brand-primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .back-link a:hover {
            color: var(--brand-primary-hover);
            transform: translateX(-5px);
        }

        /* Resend button */
        .resend-section {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .resend-text {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .btn-resend {
            color: var(--brand-primary);
            font-weight: 600;
            border: 2px solid var(--brand-primary);
            background: transparent;
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }

        .btn-resend:hover:not(:disabled) {
            background: var(--brand-primary);
            color: white;
        }

        .btn-resend:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .verify-card {
                padding: 2rem 1.5rem;
            }

            .otp-input {
                width: 3rem;
                height: 3.5rem;
                font-size: 1.25rem;
            }

            .verify-title {
                font-size: 1.5rem;
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    </style>
</head>

<body>
    <!-- Background shapes -->
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>

    <div class="verify-card">
        <!-- Icon -->
        <div class="verify-icon-container">
            <div class="verify-icon">🔐</div>
        </div>

        <!-- Header -->
        <div class="text-center mb-4">
            <h2 class="verify-title">Verify Your Identity</h2>
            <p class="verify-subtitle">
                We've sent a 6-digit verification code to your email.<br>
                Please enter it below to continue securely.
            </p>
        </div>

        <!-- OTP Form -->
        <form method="POST" action="" id="otpForm">
            <div class="otp-input-group" id="otpInputs">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
            </div>

            <input type="hidden" name="otp" id="otpHidden">

            <button type="submit" class="btn btn-verify w-100" id="verifyBtn">
                <span id="btnText">✓ Verify & Continue</span>
                <span id="btnSpinner" class="spinner d-none"></span>
            </button>
        </form>

        <!-- ✅ UPDATED: Info Box -->
        <div class="info-box">
            <p>
                <strong>🔒 Security Notice:</strong> This code will expire in 2 minutes. 
                If you didn't receive the email, please check your spam folder.
            </p>
        </div>

        <!-- ✅ UPDATED: Timer Display -->
        <div class="timer-box">
            <div class="timer-label">Time Remaining</div>
            <div class="countdown" id="countdown">2:00</div>
        </div>

        <!-- Resend Section -->
        <div class="resend-section">
            <p class="resend-text">Didn't receive the code?</p>
            <button type="button" class="btn btn-resend" id="resendBtn" onclick="resendOTP()">
                📧 Resend Code
            </button>
            <div id="resendCooldown" class="text-muted small mt-2" style="display: none;">
                Wait <span id="cooldownTimer">30</span>s to resend
            </div>
        </div>

        <!-- Back Link -->
        <div class="back-link">
            <a href="login.php">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Login
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // OTP Input handling
        const inputs = document.querySelectorAll('.otp-input');
        const form = document.getElementById('otpForm');
        const hiddenInput = document.getElementById('otpHidden');
        const verifyBtn = document.getElementById('verifyBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');

        inputs.forEach((input, index) => {
            // Auto-focus next input
            input.addEventListener('input', function(e) {
                if (this.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                updateHiddenInput();
            });

            // Handle backspace
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            // Only allow numbers
            input.addEventListener('keypress', function(e) {
                if (!/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });

            // Handle paste
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text');
                const digits = pastedData.replace(/\D/g, '').split('');
                
                digits.forEach((digit, i) => {
                    if (inputs[i]) {
                        inputs[i].value = digit;
                    }
                });
                
                if (digits.length > 0) {
                    const lastIndex = Math.min(digits.length - 1, inputs.length - 1);
                    inputs[lastIndex].focus();
                }
                
                updateHiddenInput();
            });
        });

        // Update hidden input
        function updateHiddenInput() {
            let otp = '';
            inputs.forEach(input => {
                otp += input.value;
            });
            hiddenInput.value = otp;
        }

        // Form submission with loading state
        form.addEventListener('submit', function(e) {
            updateHiddenInput();
            if (hiddenInput.value.length !== 6) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Code',
                    text: 'Please enter all 6 digits of your verification code',
                    confirmButtonColor: '#059669'
                });
            } else {
                // Show loading state
                btnText.classList.add('d-none');
                btnSpinner.classList.remove('d-none');
                verifyBtn.disabled = true;
            }
        });

        // ✅ UPDATED: Countdown timer (2 minutes = 120 seconds)
        let timeRemaining = 120;
        const countdownElement = document.getElementById('countdown');

        function updateTimer() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Turn red when 30 seconds left
            if (timeRemaining <= 30) {
                countdownElement.style.color = '#dc2626';
                countdownElement.style.animation = 'pulse 1s ease infinite';
            }
            
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                Swal.fire({
                    icon: 'error',
                    title: 'Code Expired',
                    text: 'Your verification code has expired (2 minutes). Please login again to receive a new code.',
                    confirmButtonColor: '#059669',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = 'login.php';
                });
            }
            
            timeRemaining--;
        }

        const timerInterval = setInterval(updateTimer, 1000);

        // ✅ ENHANCED: Resend OTP with cooldown
        let canResend = true;
        let resendCooldownTime = 30;

        function resendOTP() {
            if (!canResend) {
                Swal.fire({
                    icon: 'info',
                    title: 'Please Wait',
                    text: `You can resend the code in ${resendCooldownTime} seconds`,
                    confirmButtonColor: '#059669'
                });
                return;
            }

            Swal.fire({
                title: 'Resend Code?',
                text: 'A new verification code will be sent to your email.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Send Code',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Start cooldown
                    canResend = false;
                    const resendBtn = document.getElementById('resendBtn');
                    const cooldownDiv = document.getElementById('resendCooldown');
                    const cooldownTimer = document.getElementById('cooldownTimer');
                    
                    resendBtn.disabled = true;
                    cooldownDiv.style.display = 'block';
                    
                    const cooldownInterval = setInterval(() => {
                        resendCooldownTime--;
                        cooldownTimer.textContent = resendCooldownTime;
                        
                        if (resendCooldownTime <= 0) {
                            clearInterval(cooldownInterval);
                            canResend = true;
                            resendCooldownTime = 30;
                            resendBtn.disabled = false;
                            cooldownDiv.style.display = 'none';
                        }
                    }, 1000);
                    
                    // Redirect to login with resend parameter
                    window.location.href = 'login.php?resend=1';
                }
            });
        }

        // Auto-focus first input
        inputs[0].focus();

        // Add shake animation on error
        <?php if (!empty($error_message)) : ?>
        document.querySelector('.otp-input-group').style.animation = 'shake 0.5s';
        setTimeout(() => {
            inputs.forEach(input => input.value = '');
            inputs[0].focus();
        }, 500);
        <?php endif; ?>
    </script>

    <?php if (!empty($error_message)) : ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Verification Failed',
                text: '<?= addslashes($error_message) ?>',
                confirmButtonColor: '#059669'
            });
        </script>
    <?php endif; ?>

</body>

</html>