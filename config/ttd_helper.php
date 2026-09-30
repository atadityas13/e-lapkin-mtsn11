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

    define('TTD_JENIS_PENILAI', [
        'ttd' => 'Tanda Tangan',
        'cap' => 'Cap / Stempel',
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

        $kolom_cap = $conn->query("SHOW COLUMNS FROM ttd_penilai LIKE 'cap'");
        if ($kolom_cap && $kolom_cap->num_rows === 0) {
            $conn->query('ALTER TABLE ttd_penilai MODIFY ttd MEDIUMTEXT NULL');
            $conn->query('ALTER TABLE ttd_penilai ADD COLUMN cap MEDIUMTEXT NULL AFTER ttd');
        }
    }

    /**
     * Ubah latar putih/terang jadi transparan, potong area kosong, dan batasi ukuran (maks 800px).
     * Mengembalikan biner PNG, atau null jika GD tidak tersedia / gambar tidak terbaca.
     */
    function olah_gambar_ttd(string $biner): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring($biner);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $skala = min(1, 800 / max($w, $h));
        $nw = max(1, (int) round($w * $skala));
        $nh = max(1, (int) round($h * $skala));

        $img = imagecreatetruecolor($nw, $nh);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $minX = $nw;
        $minY = $nh;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $nh; $y++) {
            for ($x = 0; $x < $nw; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                $terang = min($r, $g, $b);
                if ($terang >= 235) {
                    $a = 127;
                } elseif ($terang > 190) {
                    $a = max($a, (int) round(($terang - 190) / 45 * 127));
                }
                imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $r, $g, $b, $a));

                if ($a < 110) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX >= 0) {
            $pad = 4;
            $minX = max(0, $minX - $pad);
            $minY = max(0, $minY - $pad);
            $maxX = min($nw - 1, $maxX + $pad);
            $maxY = min($nh - 1, $maxY + $pad);
            $crop = imagecrop($img, ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1]);
            if ($crop !== false) {
                imagedestroy($img);
                $img = $crop;
                imagealphablending($img, false);
                imagesavealpha($img, true);
            }
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
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

        $png = olah_gambar_ttd($biner);
        if ($png !== null) {
            return 'data:image/png;base64,' . base64_encode($png);
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

    function simpan_ttd_pegawai_file(mysqli $conn, int $id_pegawai, string $path): bool
    {
        if (!is_file($path) || filesize($path) > TTD_MAKS_BYTE) {
            return false;
        }
        $biner = file_get_contents($path);
        if ($biner === false || $biner === '') {
            return false;
        }

        return simpan_ttd_pegawai($conn, $id_pegawai, 'data:image/png;base64,' . base64_encode($biner));
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

    /**
     * @param 'ttd'|'cap' $jenis
     */
    function get_ttd_penilai(mysqli $conn, string $tipe, string $jenis = 'ttd'): ?string
    {
        if (!isset(TTD_JENIS_PENILAI[$jenis])) {
            return null;
        }
        ensure_ttd_schema($conn);
        $stmt = $conn->prepare("SELECT {$jenis} FROM ttd_penilai WHERE tipe = ? LIMIT 1");
        $stmt->bind_param('s', $tipe);
        $stmt->execute();
        $stmt->bind_result($gambar);
        $stmt->fetch();
        $stmt->close();

        return $gambar ?: null;
    }

    /**
     * @param 'ttd'|'cap' $jenis
     */
    function simpan_ttd_penilai(mysqli $conn, string $tipe, string $data_uri, string $jenis = 'ttd'): bool
    {
        if (!isset(TTD_TIPE_PENILAI[$tipe]) || !isset(TTD_JENIS_PENILAI[$jenis])) {
            return false;
        }
        ensure_ttd_schema($conn);
        $gambar = normalisasi_ttd_data_uri($data_uri);
        if ($gambar === null) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "INSERT INTO ttd_penilai (tipe, {$jenis}, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE {$jenis} = VALUES({$jenis}), updated_at = VALUES(updated_at)"
        );
        $stmt->bind_param('sss', $tipe, $gambar, $now);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    /**
     * @param 'ttd'|'cap' $jenis
     */
    function hapus_ttd_penilai(mysqli $conn, string $tipe, string $jenis = 'ttd'): bool
    {
        if (!isset(TTD_JENIS_PENILAI[$jenis])) {
            return false;
        }
        ensure_ttd_schema($conn);
        $stmt = $conn->prepare("UPDATE ttd_penilai SET {$jenis} = NULL WHERE tipe = ?");
        $stmt->bind_param('s', $tipe);
        $ok = $stmt->execute();
        $stmt->close();

        $conn->query('DELETE FROM ttd_penilai WHERE ttd IS NULL AND cap IS NULL');

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
     * Jika $tengah = true, ($x, $y) adalah titik tengah gambar.
     */
    function pdf_tempel_ttd(FPDF $pdf, ?string $data_uri, float $x, float $y, float $tinggi = 18, float $lebar_maks = 50, bool $tengah = false): void
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
        if ($tengah) {
            $x -= $lebar / 2;
            $y -= $tinggi / 2;
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
     * $y = baris setelah "Pejabat Penilai,"; nama dicetak 20 mm di bawahnya.
     * Area TTD penilai = lebar blok teks (label/nama/NIP) × 20 mm; TTD di tengah area,
     * titik tengah cap tepat di tepi kiri teks.
     * Font dikembalikan ke Arial 10 reguler (sama dengan baris label).
     */
    function pdf_bubuhkan_ttd_laporan(mysqli $conn, FPDF $pdf, int $id_pegawai, ?string $unit_kerja, ?string $nip_penilai, float $x_penilai, float $x_pegawai, float $y, ?string $nama_penilai = null): void
    {
        $tinggi_area = 20;
        $tengah_y = $y + $tinggi_area / 2;

        $pdf->SetFont('Arial', 'B', 10);
        $lebar_area = $pdf->GetStringWidth((string) $nama_penilai);
        $pdf->SetFont('Arial', '', 10);
        $lebar_area = max($lebar_area, $pdf->GetStringWidth('NIP. ' . $nip_penilai), $pdf->GetStringWidth('Pejabat Penilai,'));

        $tipe = tipe_penilai_pegawai($unit_kerja, $nip_penilai);
        pdf_tempel_ttd($pdf, get_ttd_penilai($conn, $tipe, 'ttd'), $x_penilai + $lebar_area / 2, $tengah_y, 16, 45, true);
        pdf_tempel_ttd($pdf, get_ttd_penilai($conn, $tipe, 'cap'), $x_penilai, $tengah_y, 40, 42, true);

        pdf_tempel_ttd($pdf, get_ttd_pegawai($conn, $id_pegawai), $x_pegawai, $y, 20, 45);
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
