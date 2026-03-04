<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * File: Hari Libur Helper Functions
 * Deskripsi: Helper functions untuk memanajemen hari libur nasional dan cuti bersama
 * 
 * @package    E-Lapkin-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2025 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * 
 * ========================================================
 */

// Ensure database connection exists
if (!isset($conn)) {
    require_once __DIR__ . '/database.php';
}

/**
 * Fungsi untuk check apakah tanggal adalah hari libur atau weekend
 * @param string $tanggal Format: Y-m-d
 * @param mysqli $conn Database connection
 * @return array ['is_holiday' => bool, 'type' => string, 'name' => string]
 */
function is_hari_libur_atau_weekend($tanggal, $conn) {
    $timestamp = strtotime($tanggal);
    $hari = date('w', $timestamp); // 0 = Sunday, 6 = Saturday
    
    // Check Sabtu (6) atau Minggu (0)
    if ($hari == 0 || $hari == 6) {
        $nama_hari = $hari == 0 ? 'Minggu' : 'Sabtu';
        return [
            'is_holiday' => true,
            'type' => 'weekend',
            'name' => $nama_hari
        ];
    }
    
    // Check dari database hari_libur
    $stmt = $conn->prepare("SELECT * FROM hari_libur WHERE tanggal_libur = ?");
    $stmt->bind_param("s", $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return [
            'is_holiday' => true,
            'type' => $row['tipe_libur'],
            'name' => $row['nama_hari_libur']
        ];
    }
    
    $stmt->close();
    return [
        'is_holiday' => false,
        'type' => null,
        'name' => null
    ];
}

/**
 * Fungsi untuk get semua hari libur
 * @param mysqli $conn Database connection
 * @param string $tahun Optional: filter by year (format: YYYY)
 * @return array Array of hari_libur records
 */
function get_all_hari_libur($conn, $tahun = null) {
    if ($tahun) {
        $stmt = $conn->prepare("SELECT * FROM hari_libur WHERE YEAR(tanggal_libur) = ? ORDER BY tanggal_libur ASC");
        $stmt->bind_param("s", $tahun);
    } else {
        $stmt = $conn->prepare("SELECT * FROM hari_libur ORDER BY tanggal_libur ASC");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $haris = [];
    
    while ($row = $result->fetch_assoc()) {
        $haris[] = $row;
    }
    
    $stmt->close();
    return $haris;
}

/**
 * Fungsi untuk add hari libur baru
 * @param mysqli $conn Database connection
 * @param string $tanggal_libur Format: Y-m-d
 * @param string $nama_hari_libur Nama hari libur
 * @param string $tipe_libur nasional|cuti_bersama|custom
 * @param int $id_pegawai ID pegawai yang membuat entry (untuk audit)
 * @param string $keterangan Optional: keterangan/deskripsi
 * @param string $sumber api|admin
 * @return array ['success' => bool, 'message' => string]
 */
function add_hari_libur($conn, $tanggal_libur, $nama_hari_libur, $tipe_libur = 'nasional', $id_pegawai = null, $keterangan = null, $sumber = 'admin') {
    // Validate tanggal format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_libur)) {
        return [
            'success' => false,
            'message' => 'Format tanggal tidak valid. Gunakan format Y-m-d'
        ];
    }
    
    // Check apakah sudah ada
    $stmt_check = $conn->prepare("SELECT id_hari_libur FROM hari_libur WHERE tanggal_libur = ?");
    $stmt_check->bind_param("s", $tanggal_libur);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $stmt_check->close();
    
    if ($result_check->num_rows > 0) {
        return [
            'success' => false,
            'message' => 'Tanggal hari libur sudah ada di database'
        ];
    }
    
    // Insert
    $stmt = $conn->prepare("INSERT INTO hari_libur (tanggal_libur, nama_hari_libur, tipe_libur, sumber, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $tanggal_libur, $nama_hari_libur, $tipe_libur, $sumber, $keterangan, $id_pegawai);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Hari libur berhasil ditambahkan'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($error)
        ];
    }
}

/**
 * Fungsi untuk delete hari libur
 * @param mysqli $conn Database connection
 * @param int $id_hari_libur ID dari hari libur
 * @return array ['success' => bool, 'message' => string]
 */
function delete_hari_libur($conn, $id_hari_libur) {
    $stmt = $conn->prepare("DELETE FROM hari_libur WHERE id_hari_libur = ?");
    $stmt->bind_param("i", $id_hari_libur);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Hari libur berhasil dihapus'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($error)
        ];
    }
}

/**
 * Fungsi untuk update hari libur
 * @param mysqli $conn Database connection
 * @param int $id_hari_libur ID dari hari libur
 * @param string $nama_hari_libur Nama hari libur
 * @param string $tipe_libur nasional|cuti_bersama|custom
 * @param string $keterangan Optional: keterangan
 * @return array ['success' => bool, 'message' => string]
 */
function update_hari_libur($conn, $id_hari_libur, $nama_hari_libur, $tipe_libur, $keterangan = null) {
    $stmt = $conn->prepare("UPDATE hari_libur SET nama_hari_libur = ?, tipe_libur = ?, keterangan = ?, updated_at = NOW() WHERE id_hari_libur = ?");
    $stmt->bind_param("sssi", $nama_hari_libur, $tipe_libur, $keterangan, $id_hari_libur);
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'Hari libur berhasil diupdate'
        ];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($error)
        ];
    }
}

/**
 * Fungsi untuk fetch hari libur dari libur.deno.dev API
 * @param int $tahun Tahun yang akan di-fetch
 * @return array ['success' => bool, 'data' => array, 'message' => string]
 */
function fetch_hari_libur_dari_api($tahun) {
    $url = "https://libur.deno.dev/?tahun={$tahun}";
    
    // Use cURL atau file_get_contents untuk fetch API
    try {
        // Try using cURL first
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200 && $response) {
                $data = json_decode($response, true);
                return [
                    'success' => true,
                    'data' => $data ?? [],
                    'message' => 'Data berhasil di-fetch dari API'
                ];
            } else {
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Gagal fetch dari API (HTTP: ' . $http_code . ')'
                ];
            }
        } else {
            // Fallback to file_get_contents
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'verify_peer' => false
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                return [
                    'success' => true,
                    'data' => $data ?? [],
                    'message' => 'Data berhasil di-fetch dari API'
                ];
            } else {
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Gagal fetch dari API'
                ];
            }
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'data' => [],
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ];
    }
}

/**
 * Fungsi untuk sync hari libur dari API ke database
 * @param mysqli $conn Database connection
 * @param int $tahun Tahun yang akan di-sync
 * @param int $id_pegawai ID pegawai yang melakukan sync (untuk audit)
 * @return array ['success' => bool, 'message' => string, 'count_added' => int]
 */
function sync_hari_libur_dari_api($conn, $tahun, $id_pegawai = null) {
    $api_result = fetch_hari_libur_dari_api($tahun);
    
    if (!$api_result['success']) {
        return [
            'success' => false,
            'message' => $api_result['message'],
            'count_added' => 0
        ];
    }
    
    $count = 0;
    $data = $api_result['data'];
    
    // Data dari libur.deno.dev adalah array dengan key tanggal dan value nama libur
    // Contoh: {"2025-01-01": "Tahun Baru", ...}
    if (is_array($data)) {
        foreach ($data as $tanggal => $nama) {
            // Check format tanggal
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                continue;
            }
            
            // Check apakah sudah ada
            $stmt_check = $conn->prepare("SELECT id_hari_libur FROM hari_libur WHERE tanggal_libur = ? AND sumber = 'api'");
            $stmt_check->bind_param("s", $tanggal);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $stmt_check->close();
            
            if ($result_check->num_rows == 0) {
                // Insert baru dari API
                $tipe = 'nasional';
                $sumber = 'api';
                
                $stmt = $conn->prepare("INSERT INTO hari_libur (tanggal_libur, nama_hari_libur, tipe_libur, sumber, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $tanggal, $nama, $tipe, $sumber, $id_pegawai);
                
                if ($stmt->execute()) {
                    $count++;
                }
                $stmt->close();
            }
        }
    }
    
    return [
        'success' => true,
        'message' => 'Sync berhasil. Ditambahkan ' . $count . ' hari libur baru dari API',
        'count_added' => $count
    ];
}

/**
 * Fungsi untuk get nama hari dalam bahasa Indonesia
 * @param string $tanggal Format: Y-m-d
 * @return string Nama hari
 */
function get_nama_hari_indo($tanggal) {
    $hariList = [
        'Sun' => 'Minggu',
        'Mon' => 'Senin',
        'Tue' => 'Selasa',
        'Wed' => 'Rabu',
        'Thu' => 'Kamis',
        'Fri' => 'Jumat',
        'Sat' => 'Sabtu'
    ];
    
    $hari = $hariList[date('D', strtotime($tanggal))];
    return $hari . ', ' . date('d-m-Y', strtotime($tanggal));
}

/**
 * Fungsi untuk auto-sync hari libur jika belum ada untuk tahun ini
 * Bisa dipanggil sekali pada awal tahun atau saat initialization
 * @param mysqli $conn Database connection
 * @param int $id_pegawai ID pegawai (optional)
 * @return void
 */
function auto_sync_hari_libur_jika_diperlukan($conn, $id_pegawai = null) {
    $tahun = (int)date('Y');
    
    // Check apakah sudah ada data untuk tahun ini
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM hari_libur WHERE YEAR(tanggal_libur) = ? AND sumber = 'api'");
    $stmt_check->bind_param("i", $tahun);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $row = $result->fetch_assoc();
    $stmt_check->close();
    
    // Jika belum ada data dari API untuk tahun ini, lakukan sync
    if ($row['count'] == 0) {
        sync_hari_libur_dari_api($conn, $tahun, $id_pegawai);
    }
}

// Ensure tabel hari_libur exists
function ensure_hari_libur_table($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'hari_libur'");
    if ($result && $result->num_rows == 0) {
        // Create table jika belum ada
        $sql = "CREATE TABLE IF NOT EXISTS hari_libur (
            id_hari_libur INT PRIMARY KEY AUTO_INCREMENT,
            tanggal_libur DATE NOT NULL UNIQUE,
            nama_hari_libur VARCHAR(255) NOT NULL,
            tipe_libur ENUM('nasional', 'cuti_bersama', 'custom') DEFAULT 'nasional',
            sumber ENUM('api', 'admin') DEFAULT 'admin',
            keterangan TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NULL,
            FOREIGN KEY (created_by) REFERENCES pegawai(id_pegawai) ON DELETE SET NULL,
            INDEX idx_tanggal_libur (tanggal_libur),
            INDEX idx_tipe_libur (tipe_libur),
            INDEX idx_sumber (sumber)
        )";
        $conn->query($sql);
    }
}

// Ensure table exists saat helper diinclude
ensure_hari_libur_table($conn);

// Auto sync untuk tahun ini saat aplikasi diload
auto_sync_hari_libur_jika_diperlukan($conn);

?>
