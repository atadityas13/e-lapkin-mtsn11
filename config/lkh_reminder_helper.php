<?php
/**
 * Pengingat pengisian LKH berbasis data nyata:
 * - Tidak ada pengingat jika LKH tanggal hari ini sudah ada di database
 *   (LKH untuk tanggal ke depan tidak dianggap mengisi hari ini).
 * - Hari terlewat = hari kerja (bukan Sabtu/Minggu/hari libur) tanpa LKH,
 *   dihitung dari LKH terakhir sebelum hari ini sampai hari ini.
 */

if (!function_exists('hitung_pengingat_lkh')) {
    /**
     * @return array{hari_terlewat: int, pesan: string}|null  null jika tidak perlu pengingat
     */
    function hitung_pengingat_lkh(mysqli $conn, int $id_pegawai, ?string $hari_ini = null): ?array
    {
        $tz = new DateTimeZone('Asia/Jakarta');
        $today = new DateTimeImmutable($hari_ini ?? 'today', $tz);
        $today_str = $today->format('Y-m-d');

        $stmt = $conn->prepare('SELECT COUNT(*) FROM lkh WHERE id_pegawai = ? AND DATE(tanggal_lkh) = ?');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $id_pegawai, $today_str);
        $stmt->execute();
        $stmt->bind_result($jumlah_hari_ini);
        $stmt->fetch();
        $stmt->close();

        if ((int) $jumlah_hari_ini > 0) {
            return null;
        }

        $stmt = $conn->prepare('SELECT MAX(DATE(tanggal_lkh)) FROM lkh WHERE id_pegawai = ? AND DATE(tanggal_lkh) < ?');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $id_pegawai, $today_str);
        $stmt->execute();
        $stmt->bind_result($lkh_terakhir);
        $stmt->fetch();
        $stmt->close();

        $mulai = $lkh_terakhir
            ? (new DateTimeImmutable($lkh_terakhir, $tz))->modify('+1 day')
            : $today->modify('first day of this month');

        // Batasi rentang agar tetap ringan dipanggil di setiap halaman.
        $batas_awal = $today->modify('-60 days');
        if ($mulai < $batas_awal) {
            $mulai = $batas_awal;
        }

        $libur = [];
        $mulai_str = $mulai->format('Y-m-d');
        $stmt = $conn->prepare('SELECT tanggal_libur FROM hari_libur WHERE tanggal_libur BETWEEN ? AND ?');
        if ($stmt) {
            $stmt->bind_param('ss', $mulai_str, $today_str);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $libur[(string) $row['tanggal_libur']] = true;
            }
            $stmt->close();
        }

        $hari_terlewat = 0;
        $hari_ini_hari_kerja = false;
        for ($tanggal = $mulai; $tanggal <= $today; $tanggal = $tanggal->modify('+1 day')) {
            $hari = (int) $tanggal->format('w');
            $hari_kerja = $hari !== 0 && $hari !== 6 && !isset($libur[$tanggal->format('Y-m-d')]);
            if (!$hari_kerja) {
                continue;
            }
            $hari_terlewat++;
            if ($tanggal->format('Y-m-d') === $today_str) {
                $hari_ini_hari_kerja = true;
            }
        }

        if ($hari_terlewat === 0) {
            return null;
        }

        $pesan = ($hari_terlewat === 1 && $hari_ini_hari_kerja)
            ? 'Hari ini anda belum mengisi laporan kinerja harian, silahkan mengisi laporan'
            : "Anda sudah $hari_terlewat hari kerja belum mengisi laporan kinerja harian, silahkan mengisi laporan";

        return ['hari_terlewat' => $hari_terlewat, 'pesan' => $pesan];
    }
}
