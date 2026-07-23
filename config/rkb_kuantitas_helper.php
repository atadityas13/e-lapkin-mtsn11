<?php
/**
 * Sinkronkan rkb.kuantitas dari total LKH.jumlah_realisasi (bukan COUNT baris).
 */

if (!function_exists('sync_rkb_kuantitas_from_lkh')) {
    /**
     * Update kuantitas satu RKB.
     */
    function sync_rkb_kuantitas_by_id(mysqli $conn, int $id_rkb): bool
    {
        $stmt = $conn->prepare("
            UPDATE rkb
            SET kuantitas = (
                SELECT COALESCE(SUM(lkh.jumlah_realisasi), 0)
                FROM lkh
                WHERE lkh.id_rkb = rkb.id_rkb
                  AND MONTH(lkh.tanggal_lkh) = rkb.bulan
                  AND YEAR(lkh.tanggal_lkh) = rkb.tahun
            )
            WHERE id_rkb = ?
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id_rkb);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    /**
     * Update semua RKB milik pegawai untuk bulan/tahun tertentu.
     */
    function sync_rkb_kuantitas_for_pegawai(mysqli $conn, int $id_pegawai, int $bulan, int $tahun): int
    {
        $stmt = $conn->prepare('SELECT id_rkb FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ?');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('iii', $id_pegawai, $bulan, $tahun);
        $stmt->execute();
        $result = $stmt->get_result();
        $updated = 0;
        while ($row = $result->fetch_assoc()) {
            if (sync_rkb_kuantitas_by_id($conn, (int) $row['id_rkb'])) {
                $updated++;
            }
        }
        $stmt->close();

        return $updated;
    }

    /**
     * Update seluruh RKB di database (sekali untuk data lama).
     */
    function sync_all_rkb_kuantitas_from_lkh(mysqli $conn): int
    {
        $sql = "
            UPDATE rkb
            SET kuantitas = (
                SELECT COALESCE(SUM(l.jumlah_realisasi), 0)
                FROM lkh l
                WHERE l.id_rkb = rkb.id_rkb
                  AND MONTH(l.tanggal_lkh) = rkb.bulan
                  AND YEAR(l.tanggal_lkh) = rkb.tahun
            )
        ";
        if (!$conn->query($sql)) {
            return 0;
        }

        return (int) $conn->affected_rows;
    }
}
