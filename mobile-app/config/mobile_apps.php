<?php
/**
 * Registered mobile apps allowed to access E-LAPKIN mobile interface.
 */

define('MOBILE_SECRET_KEY', 'MTSN11-MOBILE-KEY-2025');
define('TALIM_SSO_SECRET', 'MTSN11-TALIM-SSO-2026');

$MOBILE_APPS = [
    'elapkin' => [
        'user_agent' => 'E-LAPKIN-MTSN11-Mobile-App/1.0',
        'package' => 'id.sch.mtsn11majalengka.elapkin',
        'name' => 'E-LAPKIN',
    ],
    'talim' => [
        'user_agent' => 'ATADevLabs_TalimSuperApp_MTsN11',
        'package' => 'com.atadevlabs.talim',
        'name' => "Ta'lim SuperApp",
    ],
];

function generateMobileTokenForDate(string $date): string
{
    return md5(MOBILE_SECRET_KEY . $date);
}

function generateMobileToken(): string
{
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Jakarta');
    $token = generateMobileTokenForDate(date('Y-m-d'));
    date_default_timezone_set($originalTimezone);
    return $token;
}

function getRequestHeader(string $name): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, $name) === 0) {
            return $value;
        }
    }

    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return $_SERVER[$serverKey] ?? '';
}

function resolveMobileApp(): ?array
{
    global $MOBILE_APPS;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    foreach ($MOBILE_APPS as $app) {
        // Beberapa HTTP client bisa menambahkan suffix (mis. versi/library).
        // Jadi selain exact match, kita izinkan match sebagai substring.
        if ($userAgent === $app['user_agent']) {
            return $app;
        }

        if ($userAgent !== '' && stripos($userAgent, $app['user_agent']) !== false) {
            return $app;
        }
    }

    return null;
}

function validateMobileUserAgentForApp(): array
{
    $app = resolveMobileApp();
    if (!$app) {
        $receivedUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        http_response_code(403);
        die(json_encode([
            'error' => 'Access denied. Mobile app access only.',
            'code' => 'INVALID_USER_AGENT',
            'received_user_agent' => $receivedUserAgent,
        ]));
    }

    return $app;
}

function validateOptionalMobileHeaders(array $app): void
{
    $receivedToken = getRequestHeader('X-Mobile-Token');
    $receivedPackage = getRequestHeader('X-App-Package');

    if ($receivedToken === '' && $receivedPackage === '') {
        return;
    }

    if ($receivedToken === '' || $receivedPackage === '') {
        http_response_code(403);
        die(json_encode([
            'error' => 'Mobile token and package headers are required together.',
            'code' => 'INVALID_HEADERS',
        ]));
    }

    $expectedToken = generateMobileToken();
    if ($receivedToken !== $expectedToken) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Invalid mobile token.',
            'code' => 'INVALID_TOKEN',
        ]));
    }

    if ($receivedPackage !== $app['package']) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Invalid app package.',
            'code' => 'INVALID_PACKAGE',
        ]));
    }
}

function performMobileLogin(mysqli $conn, string $nip, string $password): array
{
    $stmt = $conn->prepare("SELECT id_pegawai, nip, password, nama, jabatan, unit_kerja, role, status FROM pegawai WHERE nip = ? AND role != 'admin'");
    $stmt->bind_param('s', $nip);
    $stmt->execute();
    $result = $stmt->get_result();
    $pegawai = $result->fetch_assoc();
    $stmt->close();

    if (!$pegawai) {
        return ['success' => false, 'message' => 'NIP atau password salah.'];
    }

    if (!password_verify($password, $pegawai['password'])) {
        return ['success' => false, 'message' => 'NIP atau password salah.'];
    }

    $status = $pegawai['status'] ?? 'approved';
    if ($status !== 'approved' && $status !== '') {
        return ['success' => false, 'message' => 'Akun Anda belum disetujui atau ditolak.'];
    }

    $_SESSION['mobile_loggedin'] = true;
    $_SESSION['mobile_id_pegawai'] = $pegawai['id_pegawai'];
    $_SESSION['mobile_nip'] = $pegawai['nip'];
    $_SESSION['mobile_nama'] = $pegawai['nama'];
    $_SESSION['mobile_jabatan'] = $pegawai['jabatan'];
    $_SESSION['mobile_unit_kerja'] = $pegawai['unit_kerja'];
    $_SESSION['mobile_role'] = $pegawai['role'];

    return [
        'success' => true,
        'user' => [
            'id_pegawai' => (int) $pegawai['id_pegawai'],
            'nip' => $pegawai['nip'],
            'nama' => $pegawai['nama'],
            'jabatan' => $pegawai['jabatan'],
            'unit_kerja' => $pegawai['unit_kerja'],
            'role' => $pegawai['role'],
        ],
    ];
}

function getMobileDashboardStats(mysqli $conn, int $idPegawai): array
{
    $month = (int) date('m');
    $year = (int) date('Y');
    $today = date('Y-m-d');

    $lkhBulan = 0;
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?');
    $stmt->bind_param('iii', $idPegawai, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $lkhBulan = (int) ($result['total'] ?? 0);
    $stmt->close();

    $lkhHariIni = 0;
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM lkh WHERE id_pegawai = ? AND tanggal_lkh = ?');
    $stmt->bind_param('is', $idPegawai, $today);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $lkhHariIni = (int) ($result['total'] ?? 0);
    $stmt->close();

    return [
        'lkh_bulan_ini' => $lkhBulan,
        'lkh_hari_ini' => $lkhHariIni,
    ];
}

/**
 * SSO Ta'lim/SimpatiSans — identitas guru 100% dari SimpatiSans.
 * Tabel pegawai e-Lapkin hanya shadow teknis agar fitur LKH (id_pegawai FK) bisa jalan.
 */
function verifyTalimSsoSignature(string $nip, int $timestamp, string $signature, string $profileHash): bool
{
    if (abs(time() - $timestamp) > 300) {
        return false;
    }

    $payload = $nip . '|' . $timestamp . '|' . $profileHash;
    $expected = hash_hmac('sha256', $payload, TALIM_SSO_SECRET);

    return hash_equals($expected, $signature);
}

function syncFeatureShadowPegawai(mysqli $conn, array $profile): ?int
{
    $nip = trim($profile['nip'] ?? '');
    if ($nip === '') {
        return null;
    }

    $nama = trim($profile['nama'] ?? 'Guru');
    $jabatan = trim($profile['jabatan'] ?? 'Guru');
    $unitKerja = trim($profile['unit_kerja'] ?? 'MTsN 11 Majalengka');
    $nipPenilai = trim($profile['nip_penilai'] ?? '');
    $namaPenilai = trim($profile['nama_penilai'] ?? '');

    $stmt = $conn->prepare('SELECT id_pegawai FROM pegawai WHERE nip = ? LIMIT 1');
    if ($stmt === false) {
        error_log('syncFeatureShadowPegawai prepare(select) failed: '.$conn->error);
        return null;
    }

    $stmt->bind_param('s', $nip);
    if ($stmt->execute() === false) {
        error_log('syncFeatureShadowPegawai execute(select) failed: '.$conn->error);
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        $id = (int) $row['id_pegawai'];
        $stmt = $conn->prepare(
            'UPDATE pegawai SET nama = ?, jabatan = ?, unit_kerja = ?, nip_penilai = ?, nama_penilai = ?, status = ? WHERE id_pegawai = ?'
        );
        if ($stmt === false) {
            error_log('syncFeatureShadowPegawai prepare(update) failed: '.$conn->error);
            return null;
        }
        $status = 'approved';
        $stmt->bind_param('ssssssi', $nama, $jabatan, $unitKerja, $nipPenilai, $namaPenilai, $status, $id);
        if ($stmt->execute() === false) {
            error_log('syncFeatureShadowPegawai execute(update) failed: '.$conn->error);
            $stmt->close();
            return null;
        }
        $stmt->close();
        return $id;
    }

    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $role = 'user';
    $status = 'approved';
    $stmt = $conn->prepare(
        'INSERT INTO pegawai (nip, password, nama, jabatan, unit_kerja, nip_penilai, nama_penilai, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt === false) {
        error_log('syncFeatureShadowPegawai prepare(insert) failed: '.$conn->error);
        return null;
    }

    $stmt->bind_param('sssssssss', $nip, $passwordHash, $nama, $jabatan, $unitKerja, $nipPenilai, $namaPenilai, $role, $status);
    if ($stmt->execute() === false) {
        error_log('syncFeatureShadowPegawai execute(insert) failed: '.$conn->error);
        $stmt->close();
        return null;
    }
    $id = (int) $conn->insert_id;
    $stmt->close();

    return $id > 0 ? $id : null;
}

function performMobileSsoLogin(mysqli $conn, string $nip, int $timestamp, string $signature, array $profile, string $profileHash = ''): array
{
    if (trim($profile['nip'] ?? '') !== $nip) {
        return ['success' => false, 'message' => 'Profil SSO tidak cocok dengan NIP.'];
    }

    if ($profileHash === '') {
        $profileHash = hash('sha256', json_encode($profile, JSON_UNESCAPED_UNICODE));
    }

    if (!verifyTalimSsoSignature($nip, $timestamp, $signature, $profileHash)) {
        return ['success' => false, 'message' => 'Token SSO tidak valid.'];
    }

    $idPegawai = syncFeatureShadowPegawai($conn, $profile);
    if (!$idPegawai) {
        return ['success' => false, 'message' => 'Gagal menyiapkan modul kinerja.'];
    }

    $_SESSION['mobile_loggedin'] = true;
    $_SESSION['mobile_id_pegawai'] = $idPegawai;
    $_SESSION['mobile_nip'] = $nip;
    $_SESSION['mobile_nama'] = $profile['nama'] ?? 'Guru';
    $_SESSION['mobile_jabatan'] = $profile['jabatan'] ?? 'Guru';
    $_SESSION['mobile_unit_kerja'] = $profile['unit_kerja'] ?? 'MTsN 11 Majalengka';
    $_SESSION['mobile_role'] = 'user';
    $_SESSION['mobile_sso'] = true;
    $_SESSION['mobile_simpatisans'] = true;
    $_SESSION['mobile_nip_penilai'] = $profile['nip_penilai'] ?? '';
    $_SESSION['mobile_nama_penilai'] = $profile['nama_penilai'] ?? '';
    $_SESSION['mobile_kode_guru'] = $profile['kode_guru'] ?? '';

    return [
        'success' => true,
        'sso' => true,
        'feature_only' => true,
        'source' => 'simpatisans',
    ];
}
