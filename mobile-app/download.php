<?php
/**
 * Download handler for Android WebView compatibility
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get parameters
$file = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'pdf';

if (empty($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'File parameter required']);
    exit();
}

// Validate file path (security check)
$allowedPaths = [
    '../generated/',
    __DIR__ . '/../generated/'
];

$filePath = '';
foreach ($allowedPaths as $path) {
    $fullPath = $path . $file;
    if (file_exists($fullPath) && strpos(realpath($fullPath), realpath($path)) === 0) {
        $filePath = $fullPath;
        break;
    }
}

if (empty($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit();
}

// Set appropriate headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($filePath);
exit();
?>
