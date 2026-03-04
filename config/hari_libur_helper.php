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
 * API Documentation: https://libur.deno.dev
 * @param int $tahun Tahun yang akan di-fetch
 * @return array ['success' => bool, 'data' => array, 'message' => string]
 */
function fetch_hari_libur_dari_api($tahun) {
    // Format URL yang benar: /api?year={tahun}
    $url = "https://libur.deno.dev/api?year={$tahun}";
    
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
            curl_setopt($ch, CURLOPT_USERAGENT, 'E-Lapkin-MTSN11');
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($http_code == 200 && $response) {
                $data = json_decode($response, true);
                
                // API returns array of objects: [{"date": "2026-01-01", "name": "..."}, ...]
                if (is_array($data) && count($data) > 0) {
                    return [
                        'success' => true,
                        'data' => $data,
                        'message' => 'Data berhasil di-fetch dari API'
                    ];
                } else {
                    return [
                        'success' => true,
                        'data' => $data ?? [],
                        'message' => 'Data berhasil di-fetch (tidak ada data untuk tahun ' . $tahun . ')'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Gagal fetch dari API (HTTP: ' . $http_code . ')' . ($curl_error ? ' - ' . $curl_error : '')
                ];
            }
        } else {
            // Fallback to file_get_contents
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'E-Lapkin-MTSN11'
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                // API returns array of objects: [{"date": "2026-01-01", "name": "..."}, ...]
                if (is_array($data) && count($data) > 0) {
                    return [
                        'success' => true,
                        'data' => $data,
                        'message' => 'Data berhasil di-fetch dari API'
                    ];
                } else {
                    return [
                        'success' => true,
                        'data' => $data ?? [],
                        'message' => 'Data berhasil di-fetch (tidak ada data untuk tahun ' . $tahun . ')'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Gagal fetch dari API. Pastikan server memiliki akses internet.'
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
        // Log failed sync
        log_sync_activity($conn, $tahun, 0, 'failed', $api_result['message'], $id_pegawai);
        
        return [
            'success' => false,
            'message' => $api_result['message'],
            'count_added' => 0
        ];
    }
    
    $count = 0;
    $data = $api_result['data'];
    
    // Data dari libur.deno.dev adalah array dengan struktur:
    // [{"date": "2025-01-01", "name": "Tahun Baru"}, ...]
    if (is_array($data) && count($data) > 0) {
        foreach ($data as $item) {
            // Cek struktur data
            if (!isset($item['date']) || !isset($item['name'])) {
                continue;
            }
            
            $tanggal = $item['date'];
            $nama = $item['name'];
            
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
    
    // Log successful sync
    $message = 'Sync berhasil. Ditambahkan ' . $count . ' hari libur baru dari API';
    log_sync_activity($conn, $tahun, $count, 'success', $message, $id_pegawai);
    
    return [
        'success' => true,
        'message' => $message,
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
 * Fungsi untuk auto-sync hari libur jika belum sync hari ini
 * Bisa dipanggil sekali pada setiap page load - dengan guard should_sync_daily
 * @param mysqli $conn Database connection
 * @param int $id_pegawai ID pegawai (optional)
 * @return void
 */
function auto_sync_hari_libur_daily($conn, $id_pegawai = null) {
    $current_year = (int)date('Y');
    
    // Cek apakah sudah sync hari ini untuk tahun ini
    if (should_sync_daily($conn, $current_year)) {
        // Sync tahun ini
        sync_hari_libur_dari_api($conn, $current_year, $id_pegawai);
    }
    
    // Cek dan sync tahun depan kalau Juni keatas (untuk persiapan)
    $current_month = (int)date('m');
    if ($current_month >= 6) {
        $next_year = $current_year + 1;
        if (should_sync_daily($conn, $next_year)) {
            sync_hari_libur_dari_api($conn, $next_year, $id_pegawai);
        }
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
    
    // Ensure sync_log table exists untuk tracking
    $log_table_exists = $conn->query("SHOW TABLES LIKE 'hari_libur_sync_log'")->num_rows > 0;
    if (!$log_table_exists) {
        $sql = "CREATE TABLE IF NOT EXISTS hari_libur_sync_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tahun INT NOT NULL,
            sync_date DATE NOT NULL,
            count_added INT DEFAULT 0,
            status ENUM('success', 'failed') DEFAULT 'success',
            message TEXT NULL,
            synced_by INT NULL,
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tahun_date (tahun, sync_date),
            FOREIGN KEY (synced_by) REFERENCES pegawai(id_pegawai) ON DELETE SET NULL
        )";
        $conn->query($sql);
    }
}

/**
 * Cek apakah sudah melakukan sync hari ini
 * @param mysqli $conn Database connection
 * @param int $tahun Tahun yang akan dicek
 * @return bool true jika belum sync hari ini, false jika sudah sync
 */
function should_sync_daily($conn, $tahun = null) {
    if (!$tahun) {
        $tahun = (int)date('Y');
    }
    
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM hari_libur_sync_log 
        WHERE tahun = ? AND sync_date = ? AND status = 'success'
    ");
    $stmt->bind_param("is", $tahun, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Return true jika belum ada sync sukses hari ini
    return ($row['count'] == 0);
}

/**
 * Log aktivitas sync
 * @param mysqli $conn Database connection
 * @param int $tahun Tahun yang di-sync
 * @param int $count_added Jumlah hari libur yang ditambahkan
 * @param string $status success atau failed
 * @param string $message Pesan log
 * @param int $synced_by ID pegawai yang melakukan sync
 * @return bool
 */
function log_sync_activity($conn, $tahun, $count_added, $status, $message, $synced_by = null) {
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("
        INSERT INTO hari_libur_sync_log (tahun, sync_date, count_added, status, message, synced_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isissi", $tahun, $today, $count_added, $status, $message, $synced_by);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get last sync info untuk tahun tertentu
 * @param mysqli $conn Database connection
 * @param int $tahun Tahun
 * @return array|null Array with sync info atau null jika belum pernah sync
 */
function get_last_sync_info($conn, $tahun = null) {
    if (!$tahun) {
        $tahun = (int)date('Y');
    }
    
    $stmt = $conn->prepare("
        SELECT lsl.tahun, lsl.sync_date, lsl.count_added, lsl.status, lsl.message, lsl.synced_by, lsl.synced_at, p.nama as synced_by_name
        FROM hari_libur_sync_log lsl
        LEFT JOIN pegawai p ON lsl.synced_by = p.id_pegawai
        WHERE lsl.tahun = ?
        ORDER BY lsl.synced_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row;
}

/**
 * Get sync history untuk tahun tertentu
 * @param mysqli $conn Database connection
 * @param int $tahun Tahun
 * @param int $limit Jumlah record maksimal
 * @return array Array of sync history
 */
function get_sync_history($conn, $tahun = null, $limit = 30) {
    if (!$tahun) {
        $tahun = (int)date('Y');
    }
    
    $stmt = $conn->prepare("
        SELECT lsl.tahun, lsl.sync_date, lsl.count_added, lsl.status, lsl.message, lsl.synced_by, lsl.synced_at, p.nama as synced_by_name
        FROM hari_libur_sync_log lsl
        LEFT JOIN pegawai p ON lsl.synced_by = p.id_pegawai
        WHERE lsl.tahun = ?
        ORDER BY lsl.synced_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $tahun, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    return $history;
}

// Ensure table exists saat helper diinclude
ensure_hari_libur_table($conn);

// Auto sync harian untuk tahun ini (max 1x per hari)
auto_sync_hari_libur_daily($conn);

?>
