<?php
// ===========================
// upload_profile_photo.php
// Backend handler for profile photo upload
// ===========================

session_start();
require_once(__DIR__ . '/../../initialize_coreT2.php');

// Set JSON header
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

// Check authentication
if (!isset($_SESSION['userdata']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_photo'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$user_id = $_SESSION['userdata']['user_id'];
$file = $_FILES['profile_photo'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];
    $error_msg = $error_messages[$file['error']] ?? 'Unknown upload error: ' . $file['error'];
    error_log("Upload error for user $user_id: $error_msg");
    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    error_log("Invalid file type for user $user_id: $mime_type");
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP allowed.']);
    exit;
}

// Validate file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    error_log("File too large for user $user_id: " . $file['size'] . " bytes");
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/../../uploads/profiles/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create upload directory: $upload_dir");
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
    error_log("Created upload directory: $upload_dir");
}

// Delete old photo if exists
try {
    $db = new DBConnection();
    $stmt = $db->conn->prepare("SELECT profile_photo FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $old_photo = $result['profile_photo'] ?? null;
    $stmt->close();
    
    // Delete old file
    if ($old_photo) {
        $old_file = __DIR__ . '/../../' . ltrim($old_photo, '/');
        if (file_exists($old_file)) {
            if (@unlink($old_file)) {
                error_log("Deleted old photo for user $user_id: $old_file");
            } else {
                error_log("Failed to delete old photo for user $user_id: $old_file");
            }
        }
    }
} catch (Exception $e) {
    // Continue even if deletion fails
    error_log("Failed to delete old photo: " . $e->getMessage());
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($extension)) {
    // Get extension from mime type
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $extension = $mime_to_ext[$mime_type] ?? 'jpg';
}

$filename = 'user_' . $user_id . '_' . time() . '.' . strtolower($extension);
$filepath = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    error_log("Failed to move uploaded file to: $filepath");
    echo json_encode(['success' => false, 'message' => 'Failed to save file. Please check folder permissions.']);
    exit;
}

error_log("Successfully uploaded file for user $user_id: $filepath");

// Update database
$photo_url = '/uploads/profiles/' . $filename;
$skip_db = isset($_POST['skip_db']) || isset($_GET['skip_db']);

if ($skip_db) {
    echo json_encode([
        'success' => true,
        'photo_url' => $photo_url,
        'message' => 'Profile photo uploaded (pending approval)'
    ]);
    exit;
}

try {
    $db = new DBConnection();
    $stmt = $db->conn->prepare("UPDATE users SET profile_photo = ? WHERE user_id = ?");
    $stmt->bind_param("si", $photo_url, $user_id);
    
    if ($stmt->execute()) {
        // Update session data
        if (isset($_SESSION['userdata'])) {
            $_SESSION['userdata']['profile_photo'] = $photo_url;
        }
        
        error_log("Successfully updated profile photo for user $user_id: $photo_url");
        
        echo json_encode([
            'success' => true,
            'photo_url' => $photo_url,
            'message' => 'Profile photo updated successfully'
        ]);
    } else {
        // Delete uploaded file if database update fails
        @unlink($filepath);
        error_log("Database error for user $user_id: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Delete uploaded file if error occurs
    @unlink($filepath);
    error_log("Exception for user $user_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// End of file - no closing PHP tag needed