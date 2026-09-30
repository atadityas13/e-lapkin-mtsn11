<?php
/**
 * Opsi "Sign" pada modal generate LKB/LKH mobile.
 * Membutuhkan: $ttd_suffix ('lkb' | 'lkh'), $punya_ttd (bool).
 */
?>
<div class="form-check mb-2">
    <input class="form-check-input js-ttd-toggle" type="checkbox" name="dengan_ttd" value="1"
           id="dengan_ttd_<?= $ttd_suffix ?>" data-pad="ttdPad_<?= $ttd_suffix ?>">
    <label class="form-check-label" for="dengan_ttd_<?= $ttd_suffix ?>">
        <i class="fas fa-signature me-1"></i>Sign (tanda tangan saya &amp; pejabat penilai)
    </label>
</div>
<?php if (!$punya_ttd): ?>
<div id="ttdPad_<?= $ttd_suffix ?>" class="ttd-pad d-none">
    <small class="text-muted d-block mb-1">
        Anda belum memiliki tanda tangan. Buat sekali di kotak berikut, akan tersimpan untuk generate berikutnya.
    </small>
    <canvas class="ttd-canvas border rounded bg-white w-100" height="160" style="touch-action: none;"></canvas>
    <div class="d-flex justify-content-end mt-1">
        <button type="button" class="btn btn-sm btn-outline-secondary js-ttd-clear">
            <i class="fas fa-eraser me-1"></i>Hapus
        </button>
    </div>
    <input type="hidden" name="ttd_data" value="">
</div>
<?php endif; ?>
