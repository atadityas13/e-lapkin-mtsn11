<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mobile_apps.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$app = validateMobileUserAgentForApp();
validateOptionalMobileHeaders($app);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$nip = trim($input['nip'] ?? $input['username'] ?? '');
$password = $input['password'] ?? '';

if ($nip === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'NIP dan password harus diisi.']);
    exit;
}

$result = performMobileLogin($conn, $nip, $password);

if (!$result['success']) {
    http_response_code(401);
}

echo json_encode($result);
