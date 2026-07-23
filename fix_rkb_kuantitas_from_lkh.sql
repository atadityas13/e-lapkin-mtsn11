-- One-time fix: RKB.kuantitas harus total JP/realisasi LKH, bukan jumlah baris LKH.
-- Contoh: 1 baris LKH dengan jumlah_realisasi=3 → RKB/LKB = 3 JP (bukan 1).
--
-- Jalankan sekali di database e-lapkin setelah deploy perbaikan lkh.php.

UPDATE rkb
SET kuantitas = (
    SELECT COALESCE(SUM(l.jumlah_realisasi), 0)
    FROM lkh l
    WHERE l.id_rkb = rkb.id_rkb
      AND MONTH(l.tanggal_lkh) = rkb.bulan
      AND YEAR(l.tanggal_lkh) = rkb.tahun
);
