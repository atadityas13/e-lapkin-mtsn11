<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mobile_apps.php';

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

$nip = trim($input['nip'] ?? '');
$timestamp = (int) ($input['timestamp'] ?? 0);
$signature = $input['signature'] ?? '';
$profileHash = trim($input['profile_hash'] ?? '');
$profile = $input['profile'] ?? [];

if ($nip === '' || $timestamp <= 0 || $signature === '' || !is_array($profile) || empty($profile)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Data SSO tidak lengkap.']);
    exit;
}

$result = performMobileSsoLogin($conn, $nip, $timestamp, $signature, $profile, $profileHash);

if (!$result['success']) {
    http_response_code(401);
}

echo json_encode($result);
