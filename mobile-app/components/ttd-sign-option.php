<?php
/**
 * Opsi "Sign" pada modal generate LKB/LKH mobile.
 * Membutuhkan: $ttd_suffix ('lkb' | 'lkh'), $punya_ttd (bool), $ttd_tersimpan (?string data URI).
 */
?>
<div class="form-check mb-2">
    <input class="form-check-input js-ttd-toggle" type="checkbox" name="dengan_ttd" value="1"
           id="dengan_ttd_<?= $ttd_suffix ?>" data-pad="ttdPad_<?= $ttd_suffix ?>">
    <label class="form-check-label" for="dengan_ttd_<?= $ttd_suffix ?>">
        <i class="fas fa-signature me-1"></i>Sign (tanda tangan saya &amp; pejabat penilai)
    </label>
</div>
<div id="ttdPad_<?= $ttd_suffix ?>" class="ttd-pad d-none" data-punya="<?= $punya_ttd ? '1' : '0' ?>">
    <?php if ($punya_ttd): ?>
    <div class="ttd-preview">
        <small class="text-muted d-block mb-1">Tanda tangan tersimpan:</small>
        <div class="border rounded bg-white text-center p-2">
            <img src="<?= htmlspecialchars($ttd_tersimpan) ?>" alt="Tanda tangan" style="max-height: 80px; max-width: 100%;">
        </div>
        <div class="d-flex justify-content-end mt-1">
            <button type="button" class="btn btn-sm btn-outline-primary js-ttd-ganti">
                <i class="fas fa-pen me-1"></i>Ganti tanda tangan
            </button>
        </div>
    </div>
    <?php endif; ?>
    <div class="ttd-editor<?= $punya_ttd ? ' d-none' : '' ?>">
        <small class="text-muted d-block mb-1">
            <?= $punya_ttd
                ? 'Buat tanda tangan baru di kotak berikut. Tanda tangan lama akan diganti saat generate.'
                : 'Anda belum memiliki tanda tangan. Buat sekali di kotak berikut, akan tersimpan untuk generate berikutnya.' ?>
        </small>
        <canvas class="ttd-canvas border rounded bg-white w-100" height="160" style="touch-action: none;"></canvas>
        <div class="d-flex justify-content-end gap-2 mt-1">
            <?php if ($punya_ttd): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary js-ttd-batal">
                <i class="fas fa-times me-1"></i>Batal
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary js-ttd-clear">
                <i class="fas fa-eraser me-1"></i>Hapus
            </button>
        </div>
    </div>
    <input type="hidden" name="ttd_data" value="">
</div>
