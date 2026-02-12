<?php
require_once('../initialize_coreT2.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Prevent logged-in users from logging in again
if (isset($_SESSION['userdata'])) {
    echo json_encode(['status'=>'success','msg'=>'Already logged in']);
    exit();
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','msg'=>'Invalid request']);
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$generic_error = "Invalid username or password.";

// Fetch user safely
$stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Inactive user
    if ($user['status'] !== 'Active') {
        log_audit($user['user_id'], 'Login Failed - Inactive Account', 'Authentication', null, 'Inactive user attempted login: '.$username);
        echo json_encode(['status'=>'error','msg'=>'Your account is inactive. Please contact admin.']);
        exit();
    }

    // Wrong password
    if (!password_verify($password, $user['password_hash'])) {
        log_audit($user['user_id'], 'Login Failed - Wrong Password', 'Authentication', null, 'Incorrect password entered for username: '.$username);
        echo json_encode(['status'=>'error','msg'=>$generic_error]);
        exit();
    }

    // ✅ Successful login
    session_regenerate_id(true);
    $_SESSION['userdata'] = [
        'user_id' => $user['user_id'],
        'full_name' => $user['full_name'] ?? 'User',
        'role' => $user['role'] ?? 'Member'
    ];

    log_audit($user['user_id'], 'Login', 'Authentication', null, 'User logged in successfully.');

    echo json_encode(['status'=>'success','msg'=>'Welcome back, '.($user['full_name'] ?? 'User').'!']);
    exit();
} else {
    // Unknown user
    log_audit(null, 'Login Failed - Unknown User', 'Authentication', null, 'Login attempt with unknown username: '.$username);
    echo json_encode(['status'=>'error','msg'=>$generic_error]);
    exit();
}


?>