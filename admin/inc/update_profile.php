<?php
require_once('../initialize_coreT2.php');
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['userdata'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$full_name = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if($full_name === '' || $email === '') {
    echo json_encode(['status'=>'error','msg'=>'Full Name and Email cannot be empty']);
    exit();
}

$user_id = $_SESSION['userdata']['user_id'];

try {
    if($password !== '') {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, password_hash=? WHERE user_id=?");
        $stmt->bind_param('sssi', $full_name, $email, $password_hash, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=? WHERE user_id=?");
        $stmt->bind_param('ssi', $full_name, $email, $user_id);
    }
    $stmt->execute();
    $stmt->close();

    // Update session data
    $_SESSION['userdata']['full_name'] = $full_name;

    echo json_encode(['status'=>'success']);
} catch(Exception $e) {
    error_log("Profile Update Error: ".$e->getMessage());
    echo json_encode(['status'=>'error','msg'=>'Database error']);
}
?>

