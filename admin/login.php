<?php
require_once('../initialize_coreT2.php');
require_once(__DIR__ . '/inc/log_audit_trial.php');
require_once(__DIR__ . '/inc/send_otp.php');

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if already logged in
if (isset($_SESSION['userdata'])) {
    header("Location: /admin/dashboard.php");
    exit();
}

$error_message = "";
$show_session_expired = isset($_GET['auto']) && isset($_GET['timeout']);
$show_logout_success = isset($_GET['logout']);

// Helper function to log to BOTH tables
function log_to_both_tables($user_id, $action, $module, $remarks, $status = 'Success') {
    global $conn;
    
    log_audit_trial($user_id, $action, $module, $remarks);
    
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

// Login processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_or_email = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username_or_email === '' || $password === '') {
        $error_message = "Please enter both username/email and password.";
    } else {
        // Check if input is email or username
        $stmt = $conn->prepare("SELECT * FROM users WHERE username=? OR email=? LIMIT 1");
        $stmt->bind_param("ss", $username_or_email, $username_or_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'Active') {
                $error_message = "Your account is inactive. Please contact admin.";
                log_to_both_tables(
                    $user['user_id'], 
                    'Login Failed - Inactive', 
                    'Authentication', 
                    'Inactive user tried login',
                    'Failed'
                );
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error_message = "Invalid username/email or password.";
                log_to_both_tables(
                    $user['user_id'], 
                    'Login Failed - Wrong Password', 
                    'Authentication', 
                    'Incorrect password from IP: ' . $_SERVER['REMOTE_ADDR'],
                    'Failed'
                );
            } else {
                if (empty($user['email'])) {
                    $error_message = "No email address found for this account. Please contact admin.";
                    log_to_both_tables(
                        $user['user_id'], 
                        'Login Failed - No Email', 
                        'Authentication', 
                        'User has no email for OTP',
                        'Failed'
                    );
                } else {
                    $otp = generateOTP(6);
                    
                    if (storeOTP($user['user_id'], $otp, $conn)) {
                        if (sendOTPEmail($user['email'], $user['full_name'], $otp)) {
                            $_SESSION['otp_user_id'] = $user['user_id'];
                            $_SESSION['otp_username'] = $user['username'];
                            $_SESSION['otp_sent_time'] = time();
                            
                            log_to_both_tables(
                                $user['user_id'],
                                'OTP Sent',
                                'Authentication',
                                'OTP sent to email: ' . $user['email'],
                                'Success'
                            );
                            
                            header("Location: verify_otp.php");
                            exit();
                        } else {
                            $error_message = "Failed to send OTP email. Please try again.";
                            log_to_both_tables(
                                $user['user_id'],
                                'OTP Send Failed',
                                'Authentication',
                                'Email sending failed',
                                'Failed'
                            );
                        }
                    } else {
                        $error_message = "Failed to generate OTP. Please try again.";
                        log_to_both_tables(
                            $user['user_id'],
                            'OTP Generation Failed',
                            'Authentication',
                            'Database error storing OTP',
                            'Failed'
                        );
                    }
                }
            }
        } else {
            $error_message = "Invalid username/email or password.";
            log_to_both_tables(
                0, 
                'Login Failed - Unknown User', 
                'Authentication', 
                'Unknown username/email: ' . $username_or_email . ' from IP: ' . $_SERVER['REMOTE_ADDR'],
                'Failed'
            );
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $_settings->info('system_name') ?? 'Microfinance HR3' ?> - Login</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    :root {
      --brand-primary: #059669;
      --brand-primary-hover: #047857;
      --brand-background-main: #F0FDF4;
      --brand-border: #D1FAE5;
      --brand-text-primary: #1F2937;
      --brand-text-secondary: #4B5563;
    }

    body {
      background-color: var(--brand-primary);
      min-height: 100vh;
      overflow: hidden;
    }

    /* Floating shapes */
    .shape {
      position: absolute;
      border-radius: 50%;
      background: linear-gradient(45deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0));
      animation: float 20s ease-in-out infinite;
    }

    @keyframes float {
      0% {
        transform: translateY(0px) translateX(0px);
      }

      50% {
        transform: translateY(-30px) translateX(20px);
      }

      100% {
        transform: translateY(0px) translateX(0px);
      }
    }

    @keyframes float-alt {
      0% {
        transform: translateY(0px) translateX(0px);
      }

      50% {
        transform: translateY(20px) translateX(-30px);
      }

      100% {
        transform: translateY(0px) translateX(0px);
      }
    }

    .shape-2 {
      animation-delay: 3s;
    }

    .shape-3 {
      animation: float-alt 25s ease-in-out infinite;
      animation-delay: 5s;
    }

    .shape-4 {
      animation: float-alt 15s ease-in-out infinite;
      animation-delay: 8s;
    }

    .shape-5 {
      animation-delay: 11s;
    }

    /* Login card glass effect */
    .login-card {
      background: rgba(255, 255, 255, 0.90);
      backdrop-filter: blur(12px);
      border-radius: 1.5rem;
      box-shadow: 0 1.5rem 3rem rgba(0, 0, 0, 0.2);
    }

    /* Illustration carousel */
    .illustration-container {
      position: relative;
      width: 100%;
      max-width: 42rem;
      height: 24rem;
    }

    .login-svg {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: contain;
      opacity: 0;
      transition: opacity 0.6s ease;
    }

    .login-svg.active {
      opacity: 1;
    }

    /* Button styles */
    .btn-brand {
      background-color: var(--brand-primary);
      border-color: var(--brand-primary);
      color: #fff;
      font-weight: 700;
      padding: 0.85rem 1rem;
      border-radius: 0.75rem;
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
      transition: all 0.2s ease;
    }

    .btn-brand:hover {
      background-color: var(--brand-primary-hover);
      border-color: var(--brand-primary-hover);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.2);
    }

    .btn-brand:active {
      transform: translateY(0) scale(0.99);
    }

    .btn-brand:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* Input styles */
    .form-control:focus {
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 0.25rem rgba(5, 150, 105, 0.25);
    }

    .input-group-text {
      background-color: #f8f9fa;
      border-color: #dee2e6;
    }

    /* Password toggle button */
    .btn-password-toggle {
      border-left: none;
      background-color: transparent;
      border-color: #dee2e6;
    }

    .btn-password-toggle:hover {
      background-color: #f8f9fa;
    }

    .btn-password-toggle:focus {
      box-shadow: none;
      border-color: var(--brand-primary);
    }

    /* Modal styles */
    .modal-content {
      border-radius: 1.5rem;
      border: none;
      box-shadow: 0 1.5rem 3rem rgba(0, 0, 0, 0.3);
    }

    .modal-header {
      border-bottom: 1px solid #e9ecef;
      border-radius: 1.5rem 1.5rem 0 0;
      background: linear-gradient(135deg, var(--brand-primary) 0%, #047857 100%);
      color: white;
    }

    .modal-header .btn-close {
      filter: invert(1);
    }

    /* Link styles */
    .link-brand {
      color: var(--brand-primary);
      text-decoration: none;
      font-weight: 600;
    }

    .link-brand:hover {
      color: var(--brand-primary-hover);
      text-decoration: underline;
    }

    /* Form check */
    .form-check-input:checked {
      background-color: var(--brand-primary);
      border-color: var(--brand-primary);
    }

    .form-check-input:focus {
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 0.25rem rgba(5, 150, 105, 0.25);
    }

    /* Remove browser password reveal */
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear {
      display: none;
    }

    input::-webkit-credentials-auto-fill-button {
      display: none !important;
    }

    /* Terms modal content styling */
    .terms-section {
      margin-bottom: 1.5rem;
    }

    .terms-section h6 {
      color: var(--brand-primary);
      font-weight: 700;
      margin-bottom: 0.75rem;
    }

    .terms-section p, .terms-section ul {
      color: #6c757d;
      font-size: 0.9rem;
      line-height: 1.6;
    }

    .terms-section ul {
      padding-left: 1.5rem;
    }

    .terms-section li {
      margin-bottom: 0.5rem;
    }
  </style>
</head>

<body>

  <!-- Floating Shapes Background -->
  <div class="position-absolute top-0 start-0 w-100 h-100" style="z-index: 0;">
    <div class="shape" style="width:18rem;height:18rem;top:5%;left:-5%;"></div>
    <div class="shape shape-2" style="width:24rem;height:24rem;bottom:-20%;left:15%;"></div>
    <div class="shape shape-3" style="width:20rem;height:20rem;top:-15%;right:-10%;"></div>
    <div class="shape shape-4" style="width:14rem;height:14rem;bottom:5%;right:10%;"></div>
    <div class="shape shape-5" style="width:12rem;height:12rem;top:50%;left:50%;transform:translate(-50%,-50%);"></div>
  </div>

  <div class="container-fluid min-vh-100 position-relative" style="z-index: 1;">
    <div class="row min-vh-100">

      <!-- Left Panel -->
      <section class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center p-5 text-white">
        <div class="d-flex flex-column align-items-center w-100 py-5">
          
          <!-- Logo and Title -->
          <div class="text-center mb-4">
            <img src="<?= validate_image($_settings->info('logo') ?? '../dist/img/logo.png') ?>" 
                 alt="System Logo" class="mb-3" style="width:112px;height:112px;">
            <h1 class="display-5 fw-bold mb-2"><?= $_settings->info('system_name') ?? 'Microfinance HR' ?></h1>
            <p class="text-white-50 mb-0"><?= $_settings->info('system_tagline') ?? 'Human Resource III' ?></p>
          </div>

          <!-- Illustration Carousel -->
          <div class="illustration-container my-4">
            <img src="../dist/img/illustration-1.svg" alt="Illustration 1" class="login-svg active">
            <img src="../dist/img/illustration-2.svg" alt="Illustration 2" class="login-svg">
            <img src="../dist/img/illustration-3.svg" alt="Illustration 3" class="login-svg">
            <img src="../dist/img/illustration-4.svg" alt="Illustration 4" class="login-svg">
            <img src="../dist/img/illustration-5.svg" alt="Illustration 5" class="login-svg">
          </div>

          <!-- Quote -->
          <div class="text-center mt-4" style="max-width: 36rem;">
            <p class="fst-italic text-white fs-5 mb-2 lh-base">
              "The strength of the team is each individual member. The strength of each member is the team."
            </p>
            <cite class="d-block text-end text-white-50">- Phil Jackson</cite>
          </div>

        </div>
      </section>

      <!-- Right Panel: Login Card -->
      <section class="col-12 col-lg-6 d-flex align-items-center justify-content-center p-4">
        <div class="login-card w-100 p-4 p-md-5" style="max-width: 28rem;">

          <!-- Header -->
          <div class="text-center mb-4">
            <h2 class="fw-bold mb-2" style="font-size: 2rem; color: var(--brand-text-primary);">Welcome Back!</h2>
            <p class="text-secondary mb-0">Please enter your details to sign in.</p>
          </div>

          <!-- Login Form -->
          <form id="login-form" method="POST" action="">
            
            <!-- Username or Email -->
            <div class="mb-3">
              <label class="form-label fw-medium text-secondary" for="username">Username or Email</label>
              <div class="input-group">
                <span class="input-group-text">@</span>
                <input type="text" class="form-control py-3" id="username" name="username" 
                       placeholder="Enter your username or email" required autofocus>
              </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
              <label class="form-label fw-medium text-secondary" for="password">Password</label>
              <div class="input-group">
                <span class="input-group-text">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 11c1.657 0 3 1.343 3 3v2a2 2 0 01-2 2H9a2 2 0 01-2-2v-2c0-1.657 1.343-3 3-3h2zm4-1V7a4 4 0 00-8 0v3h8z">
                    </path>
                  </svg>
                </span>
                <input type="password" class="form-control py-3" id="password" name="password" 
                       placeholder="Enter your password" required>
                <button class="btn btn-outline-secondary btn-password-toggle" type="button" id="password-toggle">
                  <!-- Eye Open -->
                  <svg id="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                    </path>
                  </svg>
                  <!-- Eye Closed -->
                  <svg id="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="d-none">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.269-2.943-9.543-7a9.966 9.966 0 012.257-3.592m3.086-2.16A9.956 9.956 0 0112 5c4.478 0 8.269 2.943 9.543 7a9.97 9.97 0 01-4.043 5.197M15 12a3 3 0 00-4.5-2.598M9 12a3 3 0 004.5 2.598M3 3l18 18">
                    </path>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Sign In Button -->
            <button type="submit" class="btn btn-brand w-100 mb-3" id="sign-in-btn" disabled>
              Sign In
            </button>

            <!-- Terms Checkbox -->
            <div class="d-flex align-items-start gap-2">
              <input type="checkbox" class="form-check-input mt-1" id="terms-check">
              <label class="form-check-label text-secondary small" for="terms-check">
                I agree to the
                <button type="button" class="btn btn-link p-0 link-brand" id="terms-link" 
                        style="vertical-align: baseline; text-decoration: none;">
                  Terms and Conditions
                </button>
              </label>
            </div>

          </form>

          <!-- Footer -->
          <div class="text-center mt-4">
            <p class="text-muted small mb-0">
              &copy; <?= date('Y') ?> <?= $_settings->info('system_name') ?? 'Microfinance HR' ?>. All Rights Reserved.
            </p>
          </div>

        </div>
      </section>

    </div>
  </div>

  <!-- Terms Modal -->
  <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Terms and Conditions</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="max-height: 70vh;">
          
          <div class="terms-section">
            <h6>1. Acceptance of Terms</h6>
            <p>By accessing and using the <?= $_settings->info('system_name') ?? 'Microfinance HR' ?> system, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions. If you do not agree with any part of these terms, you must not use this system.</p>
          </div>

          <div class="terms-section">
            <h6>2. User Account Security</h6>
            <p>You are responsible for maintaining the confidentiality of your account credentials. You agree to:</p>
            <ul>
              <li>Keep your password secure and not share it with others</li>
              <li>Notify the system administrator immediately of any unauthorized use</li>
              <li>Accept responsibility for all activities under your account</li>
              <li>Use a strong password and change it periodically</li>
            </ul>
          </div>

          <div class="terms-section">
            <h6>3. Acceptable Use Policy</h6>
            <p>You agree to use this system only for legitimate business purposes. Prohibited activities include:</p>
            <ul>
              <li>Attempting to gain unauthorized access to any part of the system</li>
              <li>Interfering with or disrupting the system's operation</li>
              <li>Uploading malicious software or harmful code</li>
              <li>Accessing, copying, or distributing confidential information without authorization</li>
              <li>Using the system for any illegal or fraudulent purposes</li>
            </ul>
          </div>

          <div class="terms-section">
            <h6>4. Data Privacy and Confidentiality</h6>
            <p>All information accessed through this system is confidential and proprietary. You agree to:</p>
            <ul>
              <li>Protect sensitive employee and company information</li>
              <li>Only access data necessary for your role and responsibilities</li>
              <li>Not disclose confidential information to unauthorized parties</li>
              <li>Comply with all applicable data protection regulations</li>
            </ul>
          </div>

          <div class="terms-section">
            <h6>5. System Monitoring and Audit</h6>
            <p>Your use of this system may be monitored and audited for security, compliance, and operational purposes. All activities are logged and may be reviewed by authorized personnel.</p>
          </div>

          <div class="terms-section">
            <h6>6. Intellectual Property</h6>
            <p>All content, features, and functionality of this system are the exclusive property of <?= $_settings->info('system_name') ?? 'Microfinance HR' ?> and are protected by copyright, trademark, and other intellectual property laws.</p>
          </div>

          <div class="terms-section">
            <h6>7. Termination</h6>
            <p>Your access to this system may be suspended or terminated at any time, with or without notice, for violation of these terms or for any other reason deemed appropriate by the organization.</p>
          </div>

          <div class="terms-section">
            <h6>8. Limitation of Liability</h6>
            <p>The organization shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of this system.</p>
          </div>

          <div class="terms-section">
            <h6>9. Changes to Terms</h6>
            <p>These Terms and Conditions may be updated from time to time. Continued use of the system after changes constitutes acceptance of the modified terms.</p>
          </div>

          <div class="terms-section">
            <h6>10. Contact Information</h6>
            <p>For questions about these Terms and Conditions, please contact your system administrator or HR department.</p>
          </div>

          <p class="text-muted small mt-4">
            <strong>Last Updated:</strong> <?= date('F d, Y') ?>
          </p>

        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-brand" id="accept-terms-btn">Accept Terms</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Illustration carousel
    (function() {
      const slides = document.querySelectorAll('.login-svg');
      if (!slides.length) return;
      
      let currentIndex = 0;
      
      setInterval(() => {
        slides[currentIndex].classList.remove('active');
        currentIndex = (currentIndex + 1) % slides.length;
        slides[currentIndex].classList.add('active');
      }, 2500);
    })();

    // Password toggle
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('password-toggle');
    const eyeOpen = document.getElementById('eye-open');
    const eyeClosed = document.getElementById('eye-closed');

    passwordToggle?.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      eyeOpen.classList.toggle('d-none', isPassword);
      eyeClosed.classList.toggle('d-none', !isPassword);
    });

    // Terms checkbox enable/disable button
    const termsCheck = document.getElementById('terms-check');
    const signInBtn = document.getElementById('sign-in-btn');

    function updateButton() {
      signInBtn.disabled = !termsCheck.checked;
    }

    termsCheck?.addEventListener('change', updateButton);
    updateButton();

    // Terms modal
    const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
    const termsLink = document.getElementById('terms-link');
    const acceptTermsBtn = document.getElementById('accept-terms-btn');

    termsLink?.addEventListener('click', (e) => {
      e.preventDefault();
      termsModal.show();
    });

    // Accept terms button in modal
    acceptTermsBtn?.addEventListener('click', () => {
      termsCheck.checked = true;
      updateButton();
      termsModal.hide();
    });
  </script>

  <?php if ($show_session_expired) : ?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
          icon: "warning",
          title: "Session Expired",
          html: "<p style='color: #856404; font-weight: bold; font-size: 1rem; margin: 10px 0;'>You have been logged out due to 2 minutes of inactivity.</p><p style='color: #6c757d; font-size: 0.95rem; margin: 10px 0;'>Please log in again to continue.</p>",
          confirmButtonText: "OK",
          confirmButtonColor: "#059669",
          allowOutsideClick: false,
          background: "#ffffff"
        });
      });
    </script>
  <?php endif; ?>

  <?php if ($show_logout_success) : ?>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
          icon: "success",
          title: "Logged Out",
          text: "You have been logged out successfully.",
          timer: 2000,
          showConfirmButton: false,
          background: "#ffffff"
        });
      });
    </script>
  <?php endif; ?>

  <?php if (!empty($error_message)) : ?>
    <script>
      Swal.fire({
        icon: 'error',
        title: 'Login Failed',
        text: '<?= addslashes($error_message) ?>',
        confirmButtonText: 'OK',
        confirmButtonColor: '#059669',
        background: '#ffffff'
      });
    </script>
  <?php endif; ?>

</body>

</html>