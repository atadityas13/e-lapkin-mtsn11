<?php
/**
 * Tawaran pindah periode aktif (RKB/LKH) ke bulan berikutnya
 * setelah LKH / LKB periode aktif sudah digenerate.
 */

if (!function_exists('laporan_periode_sudah_digenerate')) {
    function nama_bulan_periode(int $bulan): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$bulan] ?? (string) $bulan;
    }

    /**
     * Cek file PDF generated/{LKH|LKB}_{Bulan}_{Tahun}_{NIP}.pdf.
     */
    function laporan_periode_sudah_digenerate(mysqli $conn, int $id_pegawai, string $jenis, int $bulan, int $tahun): bool
    {
        $stmt = $conn->prepare('SELECT nip FROM pegawai WHERE id_pegawai = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id_pegawai);
        $stmt->execute();
        $stmt->bind_result($nip);
        $stmt->fetch();
        $stmt->close();

        if (!$nip) {
            return false;
        }

        $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $nip);

        return is_file(__DIR__ . "/../generated/{$jenis}_" . nama_bulan_periode($bulan) . "_{$tahun}_{$nama_file_nip}.pdf");
    }

    /**
     * @return array{bulan: int, tahun: int}
     */
    function periode_bulan_berikutnya(int $bulan, int $tahun): array
    {
        return $bulan >= 12
            ? ['bulan' => 1, 'tahun' => $tahun + 1]
            : ['bulan' => $bulan + 1, 'tahun' => $tahun];
    }

    function pindah_ke_periode_berikutnya(mysqli $conn, int $id_pegawai, int $bulan, int $tahun): bool
    {
        $berikutnya = periode_bulan_berikutnya($bulan, $tahun);
        $stmt = $conn->prepare('UPDATE pegawai SET bulan_aktif = ?, tahun_aktif = ? WHERE id_pegawai = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iii', $berikutnya['bulan'], $berikutnya['tahun'], $id_pegawai);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    function kunci_tunda_periode_berikutnya(string $jenis, int $bulan, int $tahun): string
    {
        return "{$jenis}_{$tahun}_{$bulan}";
    }

    function periode_berikutnya_ditunda(string $jenis, int $bulan, int $tahun): bool
    {
        return !empty($_SESSION['tunda_periode_berikutnya'][kunci_tunda_periode_berikutnya($jenis, $bulan, $tahun)]);
    }

    function tunda_periode_berikutnya(string $jenis, int $bulan, int $tahun): void
    {
        $_SESSION['tunda_periode_berikutnya'][kunci_tunda_periode_berikutnya($jenis, $bulan, $tahun)] = true;
    }

    /**
     * Tangani POST "Ubah" / "Nanti" dari modal tawaran periode berikutnya.
     * Dipanggil sebelum handler POST lain di halaman LKH/RKB mobile.
     */
    function handle_tawaran_periode_berikutnya(mysqli $conn, int $id_pegawai, string $jenis, int $bulan, int $tahun, string $redirect): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        if (isset($_POST['tunda_periode_berikutnya'])) {
            tunda_periode_berikutnya($jenis, $bulan, $tahun);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        if (isset($_POST['ganti_periode_berikutnya'])) {
            if (laporan_periode_sudah_digenerate($conn, $id_pegawai, $jenis, $bulan, $tahun)
                && pindah_ke_periode_berikutnya($conn, $id_pegawai, $bulan, $tahun)) {
                $berikutnya = periode_bulan_berikutnya($bulan, $tahun);
                set_mobile_notification(
                    'success',
                    'Periode Diubah',
                    'Periode ' . $jenis . ' diubah ke ' . nama_bulan_periode($berikutnya['bulan']) . ' ' . $berikutnya['tahun'] . '.'
                );
            } else {
                set_mobile_notification('error', 'Gagal', 'Periode tidak dapat diubah.');
            }
            talimRedirectLocation($redirect);
        }
    }

    function render_tawaran_periode_berikutnya_script(string $jenis, int $bulan, int $tahun): string
    {
        $berikutnya = periode_bulan_berikutnya($bulan, $tahun);
        $config = [
            'title' => $jenis . ' Bulan Ini Sudah Dibuat',
            'text' => 'Periode ' . $jenis . ' akan diubah ke bulan berikutnya ('
                . nama_bulan_periode($berikutnya['bulan']) . ' ' . $berikutnya['tahun'] . ').',
        ];

        return '<form id="formGantiPeriodeBerikutnya" method="POST" style="display:none;">'
            . '<input type="hidden" name="ganti_periode_berikutnya" value="1"></form>'
            . '<script>(function () {'
            . 'var cfg = ' . json_encode($config, JSON_UNESCAPED_UNICODE) . ';'
            . 'function tawarkan() {'
            . 'Swal.fire({icon: "question", title: cfg.title, text: cfg.text, showCancelButton: true,'
            . 'confirmButtonText: "Ubah", cancelButtonText: "Nanti", reverseButtons: true, allowOutsideClick: false})'
            . '.then(function (r) {'
            . 'if (r.isConfirmed) { document.getElementById("formGantiPeriodeBerikutnya").submit(); return; }'
            . 'var body = new FormData(); body.append("tunda_periode_berikutnya", "1");'
            . 'fetch(window.location.href, {method: "POST", body: body, credentials: "same-origin"}).catch(function () {});'
            . '});'
            . '}'
            . 'function tunggu() { if (window.Swal && Swal.isVisible()) { setTimeout(tunggu, 400); } else { tawarkan(); } }'
            . 'window.addEventListener("load", function () { setTimeout(tunggu, 300); });'
            . '})();</script>';
    }
}
