<?php
/**
 * Tanda tangan (TTD) pegawai & pejabat penilai untuk LKH/LKB.
 * TTD disimpan di database sebagai data URI (image/png atau image/jpeg).
 */

if (!function_exists('ensure_ttd_schema')) {
    define('TTD_TIPE_PENILAI', [
        'penilai_mtsn' => 'Kepala Madrasah',
        'penilai_tata_usaha' => 'Kepala Tata Usaha',
    ]);

    define('TTD_MAKS_BYTE', 1048576);

    function ensure_ttd_schema(mysqli $conn): void
    {
        static $sudah = false;
        if ($sudah) {
            return;
        }
        $sudah = true;

        $kolom = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'ttd'");
        if ($kolom && $kolom->num_rows === 0) {
            $conn->query('ALTER TABLE pegawai ADD COLUMN ttd MEDIUMTEXT NULL');
        }

        $conn->query(
            'CREATE TABLE IF NOT EXISTS ttd_penilai (
                tipe VARCHAR(50) NOT NULL PRIMARY KEY,
                ttd MEDIUMTEXT NOT NULL,
                updated_at DATETIME NOT NULL
            )'
        );
    }

    /**
     * Validasi & normalisasi data URI gambar TTD.
     */
    function normalisasi_ttd_data_uri(string $data_uri): ?string
    {
        if (!preg_match('#^data:(image/(?:png|jpeg));base64,([A-Za-z0-9+/=\s]+)$#', trim($data_uri), $m)) {
            return null;
        }

        $biner = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($biner === false || $biner === '' || strlen($biner) > TTD_MAKS_BYTE) {
            return null;
        }

        $info = @getimagesizefromstring($biner);
        if ($info === false || !in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:' . $info['mime'] . ';base64,' . base64_encode($biner);
    }

    function get_ttd_pegawai(mysqli $conn, int $id_pegawai): ?string
    {
        ensure_ttd_schema($conn);
        $stmt = $conn->prepare('SELECT ttd FROM pegawai WHERE id_pegawai = ? LIMIT 1');
        $stmt->bind_param('i', $id_pegawai);
        $stmt->execute();
        $stmt->bind_result($ttd);
        $stmt->fetch();
        $stmt->close();

        return $ttd ?: null;
    }

    function simpan_ttd_pegawai(mysqli $conn, int $id_pegawai, string $data_uri): bool
    {
        ensure_ttd_schema($conn);
        $ttd = normalisasi_ttd_data_uri($data_uri);
        if ($ttd === null) {
            return false;
        }
        $stmt = $conn->prepare('UPDATE pegawai SET ttd = ? WHERE id_pegawai = ?');
        $stmt->bind_param('si', $ttd, $id_pegawai);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    function get_ttd_penilai(mysqli $conn, string $tipe): ?string
    {
        ensure_ttd_schema($conn);
        $stmt = $conn->prepare('SELECT ttd FROM ttd_penilai WHERE tipe = ? LIMIT 1');
        $stmt->bind_param('s', $tipe);
        $stmt->execute();
        $stmt->bind_result($ttd);
        $stmt->fetch();
        $stmt->close();

        return $ttd ?: null;
    }

    function simpan_ttd_penilai(mysqli $conn, string $tipe, string $data_uri): bool
    {
        if (!isset(TTD_TIPE_PENILAI[$tipe])) {
            return false;
        }
        ensure_ttd_schema($conn);
        $ttd = normalisasi_ttd_data_uri($data_uri);
        if ($ttd === null) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            'INSERT INTO ttd_penilai (tipe, ttd, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE ttd = VALUES(ttd), updated_at = VALUES(updated_at)'
        );
        $stmt->bind_param('sss', $tipe, $ttd, $now);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    function hapus_ttd_penilai(mysqli $conn, string $tipe): bool
    {
        ensure_ttd_schema($conn);
        $stmt = $conn->prepare('DELETE FROM ttd_penilai WHERE tipe = ?');
        $stmt->bind_param('s', $tipe);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    /**
     * Tipe penilai pegawai: cocokkan NIP penilai dengan pengaturan, fallback ke unit kerja.
     */
    function tipe_penilai_pegawai(?string $unit_kerja, ?string $nip_penilai): string
    {
        $settings_file = __DIR__ . '/penilai_settings.json';
        $settings = is_file($settings_file) ? json_decode((string) file_get_contents($settings_file), true) : [];
        $nip_penilai = trim((string) $nip_penilai);

        if ($nip_penilai !== '' && is_array($settings)) {
            foreach (array_keys(TTD_TIPE_PENILAI) as $tipe) {
                if (trim((string) ($settings[$tipe]['nip'] ?? '')) === $nip_penilai) {
                    return $tipe;
                }
            }
        }

        return $unit_kerja === 'Tata Usaha MTsN 11 Majalengka' ? 'penilai_tata_usaha' : 'penilai_mtsn';
    }

    /**
     * Tempel gambar TTD ke PDF (tinggi tetap, lebar proporsional dengan batas maksimum).
     */
    function pdf_tempel_ttd(FPDF $pdf, ?string $data_uri, float $x, float $y, float $tinggi = 18, float $lebar_maks = 50): void
    {
        if (!$data_uri || !preg_match('#^data:image/(png|jpeg);base64,(.+)$#s', $data_uri, $m)) {
            return;
        }
        $biner = base64_decode($m[2], true);
        $info = $biner ? @getimagesizefromstring($biner) : false;
        if ($info === false || empty($info[0]) || empty($info[1])) {
            return;
        }

        $lebar = $tinggi * $info[0] / $info[1];
        if ($lebar > $lebar_maks) {
            $lebar = $lebar_maks;
            $tinggi = $lebar * $info[1] / $info[0];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ttd');
        file_put_contents($tmp, $biner);
        try {
            $pdf->Image($tmp, $x, $y, $lebar, $tinggi, $m[1] === 'png' ? 'PNG' : 'JPG');
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Tempel TTD penilai (kolom kiri) & pegawai (kolom kanan) di ruang tanda tangan laporan.
     */
    function pdf_bubuhkan_ttd_laporan(mysqli $conn, FPDF $pdf, int $id_pegawai, ?string $unit_kerja, ?string $nip_penilai, float $x_penilai, float $x_pegawai, float $y): void
    {
        pdf_tempel_ttd($pdf, get_ttd_penilai($conn, tipe_penilai_pegawai($unit_kerja, $nip_penilai)), $x_penilai, $y + 1);
        pdf_tempel_ttd($pdf, get_ttd_pegawai($conn, $id_pegawai), $x_pegawai, $y + 1);
    }

    /**
     * Proses opsi "Sign" dari form generate mobile. Simpan TTD pertama kali jika dikirim.
     *
     * @return array{ok: bool, dengan_ttd: bool, message?: string}
     */
    function siapkan_ttd_generate(mysqli $conn, int $id_pegawai, array $post): array
    {
        $dengan_ttd = !empty($post['dengan_ttd']);
        if (!$dengan_ttd) {
            return ['ok' => true, 'dengan_ttd' => false];
        }

        $data = (string) ($post['ttd_data'] ?? '');
        if ($data === '' && get_ttd_pegawai($conn, $id_pegawai) !== null) {
            return ['ok' => true, 'dengan_ttd' => true];
        }

        if ($data === '' || !simpan_ttd_pegawai($conn, $id_pegawai, $data)) {
            return ['ok' => false, 'dengan_ttd' => true, 'message' => 'Tanda tangan belum dibuat atau tidak valid. Silakan buat tanda tangan terlebih dahulu.'];
        }

        return ['ok' => true, 'dengan_ttd' => true];
    }
}
