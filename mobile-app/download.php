<?php
/**
 * Download handler for Android WebView compatibility
 */

session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';

// Check mobile login
try {
    checkMobileLogin();
} catch (Exception $e) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get parameters
$file = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'pdf';

if (empty($file)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File parameter required']);
    exit();
}

// Validate and sanitize file path
$file = basename($file); // Remove any directory traversal attempts
$allowedPath = __DIR__ . '/../generated/';
$filePath = $allowedPath . $file;

// Security check: ensure file exists and is in allowed directory
if (!file_exists($filePath) || strpos(realpath($filePath), realpath($allowedPath)) !== 0) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found']);
    exit();
}

// Additional security: check file extension
$allowedExtensions = ['pdf'];
$fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File type not allowed']);
    exit();
}

// Set appropriate headers for download
$fileSize = filesize($filePath);
$fileName = basename($file);

// Clear any output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, must-revalidate');
header('Pragma: private');
header('Expires: 0');

// Additional headers for Android WebView
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// For Android WebView, add specific headers
if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'wv') !== false) {
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
}

// Output file
readfile($filePath);
exit();
?>
