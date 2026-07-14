<?php
/**
 * Helpers for Ta'lim embedded e-Lapkin mode.
 *
 * Ta'lim uses e-Lapkin as the data/PDF engine but owns the app shell,
 * so embedded pages hide e-Lapkin chrome and simplify the guru workflow.
 */

function isTalimEmbed(): bool
{
    return (isset($_SESSION['mobile_simpatisans']) && $_SESSION['mobile_simpatisans'] === true)
        || (isset($_GET['talim']) && $_GET['talim'] === '1')
        || (isset($_GET['embed']) && $_GET['embed'] === 'talim')
        || (isset($_POST['talim']) && $_POST['talim'] === '1');
}

function talimCanDirectGenerate(): bool
{
    return isTalimEmbed();
}

function talimRedirect(string $path, bool $fresh = false): string
{
    if (!isTalimEmbed()) {
        return $path;
    }

    $url = $path;
    if (!str_contains($url, 'talim=1')) {
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . 'talim=1';
    }

    if ($fresh) {
        $url .= (str_contains($url, '?') ? '&' : '?') . '_=' . time();
    }

    return $url;
}

function talimRedirectLocation(string $path): never
{
    header('Location: ' . talimRedirect($path, true));
    exit();
}

function ensureTalimPeriod(mysqli $conn, int $idPegawai): void
{
    if (!isTalimEmbed()) {
        return;
    }

    $stmt = $conn->prepare('SELECT bulan_aktif, tahun_aktif FROM pegawai WHERE id_pegawai = ? LIMIT 1');
    if ($stmt === false) {
        return;
    }

    $stmt->bind_param('i', $idPegawai);
    $stmt->execute();
    $stmt->bind_result($bulanAktif, $tahunAktif);
    $stmt->fetch();
    $stmt->close();

    if ($bulanAktif !== null && $tahunAktif !== null) {
        return;
    }

    $bulanNow = (int) date('m');
    $tahunNow = (int) date('Y');
    $stmt = $conn->prepare(
        'UPDATE pegawai SET bulan_aktif = COALESCE(bulan_aktif, ?), tahun_aktif = COALESCE(tahun_aktif, ?) WHERE id_pegawai = ?'
    );
    if ($stmt === false) {
        return;
    }

    $stmt->bind_param('iii', $bulanNow, $tahunNow, $idPegawai);
    $stmt->execute();
    $stmt->close();
}

function ensureTalimTechnicalRhk(mysqli $conn, int $idPegawai, int $tahun): int
{
    $nama = 'Kinerja Guru Ta\'lim ' . $tahun;

    $stmt = $conn->prepare('SELECT id_rhk FROM rhk WHERE id_pegawai = ? AND nama_rhk = ? ORDER BY id_rhk DESC LIMIT 1');
    if ($stmt === false) {
        error_log('ensureTalimTechnicalRhk prepare(select) failed: ' . $conn->error);

        return 0;
    }

    $stmt->bind_param('is', $idPegawai, $nama);
    if ($stmt->execute() === false) {
        error_log('ensureTalimTechnicalRhk execute(select) failed: ' . $stmt->error);
        $stmt->close();

        return 0;
    }

    $stmt->bind_result($idRhk);
    if ($stmt->fetch()) {
        $stmt->close();

        return (int) $idRhk;
    }
    $stmt->close();

    $aspek = 'Kuantitas';
    $target = 'Kinerja bulanan dan harian guru melalui Ta\'lim';
    $stmt = $conn->prepare('INSERT INTO rhk (id_pegawai, nama_rhk, aspek, target) VALUES (?, ?, ?, ?)');
    if ($stmt === false) {
        error_log('ensureTalimTechnicalRhk prepare(insert) failed: ' . $conn->error);

        return 0;
    }

    $stmt->bind_param('isss', $idPegawai, $nama, $aspek, $target);
    if ($stmt->execute() === false) {
        error_log('ensureTalimTechnicalRhk execute(insert) failed: ' . $stmt->error);
        $stmt->close();

        return 0;
    }

    $newId = (int) $conn->insert_id;
    $stmt->close();

    return $newId;
}

function resolveTalimRhkId(mysqli $conn, int $idPegawai, int $tahun, int $postedRhkId = 0): int
{
    $technicalId = ensureTalimTechnicalRhk($conn, $idPegawai, $tahun);
    if ($technicalId > 0) {
        return $technicalId;
    }

    return $postedRhkId > 0 ? $postedRhkId : 0;
}

function talimGeneratedPdfUrl(string $filename, bool $inline = false): string
{
    $file = rawurlencode(basename($filename));
    $query = 'download_pdf.php?file=' . $file;
    if ($inline) {
        $query .= '&inline=1';
    }

    return talimRedirect($query);
}

function talimRenderPdfActions(string $filename): string
{
    $previewUrl = htmlspecialchars(talimGeneratedPdfUrl($filename, true), ENT_QUOTES);
    $downloadUrl = htmlspecialchars(talimGeneratedPdfUrl($filename, false), ENT_QUOTES);
    $name = htmlspecialchars($filename, ENT_QUOTES);

    return <<<HTML
<button type="button" class="btn btn-outline-primary btn-sm" onclick="talimPreviewPdf('{$previewUrl}')">
    <i class="fas fa-eye me-1"></i>Lihat
</button>
<button type="button" class="btn btn-download btn-sm" onclick="talimDownloadPdf('{$downloadUrl}', '{$name}')">
    <i class="fas fa-download me-1"></i>Download
</button>
HTML;
}

function talimLaporanReady(bool $isTalimEmbed, int $dataCount, bool $pdfExists, string $statusVerval): bool
{
    if ($isTalimEmbed) {
        return $dataCount > 0 || $pdfExists;
    }

    return $dataCount > 0 && $statusVerval === 'disetujui';
}

function talimModalDialogClass(string $extra = ''): string
{
    $extra = trim($extra);
    if (!isTalimEmbed()) {
        return 'modal-dialog modal-dialog-scrollable elapkin-form-modal' . ($extra !== '' ? ' ' . $extra : '');
    }

    return 'modal-dialog talim-form-modal elapkin-form-modal' . ($extra !== '' ? ' ' . $extra : '');
}

/**
 * Shared form-modal CSS so sticky Simpan/Batal stays visible
 * when a <form> wraps modal-header/body/footer (breaks Bootstrap scrollable otherwise).
 */
function elapkinFormModalCss(): string
{
    return '<style>
        .modal-dialog-scrollable.elapkin-form-modal {
            max-height: calc(100dvh - 1.5rem);
            margin: 0.75rem auto;
        }
        .modal-dialog-scrollable.elapkin-form-modal .modal-content {
            max-height: calc(100dvh - 1.5rem);
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }
        .elapkin-form-modal .modal-content > form {
            display: flex !important;
            flex-direction: column !important;
            flex: 1 1 auto;
            min-height: 0;
            max-height: 100%;
            overflow: hidden;
            height: 100%;
        }
        .elapkin-form-modal .modal-content > form > .modal-header,
        .elapkin-form-modal .modal-content > form > .modal-footer {
            flex-shrink: 0 !important;
        }
        .elapkin-form-modal .modal-content > form > .modal-body {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
            min-height: 0 !important;
            max-height: none !important;
        }
        .elapkin-form-modal .modal-footer {
            display: flex !important;
            background: #fff !important;
            border-top: 1px solid #e5e7eb;
            padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
            gap: 8px;
            z-index: 5;
            position: relative;
        }
        .elapkin-form-modal .modal-footer .btn {
            min-height: 44px;
        }
        /* Standalone mobile: keep modal above bottom-nav (~72px) */
        body:not(.talim-embed) .modal-dialog-scrollable.elapkin-form-modal {
            max-height: calc(100dvh - 5.5rem);
        }
        body:not(.talim-embed) .modal-dialog-scrollable.elapkin-form-modal .modal-content {
            max-height: calc(100dvh - 5.5rem);
        }
        @media (max-width: 576px) {
            body:not(.talim-embed) .modal-dialog-scrollable.elapkin-form-modal {
                margin: 0.5rem;
                width: calc(100% - 1rem);
                max-width: none;
                max-height: calc(100dvh - 5rem);
            }
            body:not(.talim-embed) .modal-dialog-scrollable.elapkin-form-modal .modal-content {
                max-height: calc(100dvh - 5rem);
            }
            .elapkin-form-modal .modal-footer .btn {
                flex: 1 1 0;
            }
        }
    </style>';
}

function talimEmbedCss(): string
{
    return '<style>
        body.talim-embed {
            background: #ecfdf5 !important;
            padding-bottom: 132px !important;
        }
        body.talim-embed.talim-modal-open {
            overflow: hidden !important;
        }
        body.talim-embed .nav-header,
        body.talim-embed .bottom-nav,
        body.talim-embed .talim-hidden,
        body.talim-embed .rhk-badge {
            display: none !important;
        }
        body.talim-embed .container-fluid {
            padding-top: 10px !important;
        }
        body.talim-embed .card,
        body.talim-embed .report-item,
        body.talim-embed .period-card {
            border-radius: 18px !important;
            border: 1px solid rgba(6, 95, 70, 0.08) !important;
            box-shadow: 0 10px 28px rgba(6, 95, 70, 0.08) !important;
        }
        body.talim-embed .btn-primary,
        body.talim-embed .btn-generate {
            background: linear-gradient(135deg, #047857, #059669) !important;
            border-color: #047857 !important;
        }
        body.talim-embed .floating-action {
            bottom: 150px !important;
        }
        body.talim-embed .modal {
            z-index: 10000 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        body.talim-embed .modal-backdrop {
            z-index: 9990 !important;
        }
        /* Nested picker (RKB/LKH terdahulu) must sit above form modal */
        body.talim-embed .modal.talim-picker-modal {
            z-index: 10120 !important;
        }
        body.talim-embed .modal-backdrop.talim-picker-backdrop {
            z-index: 10110 !important;
        }
        body.talim-embed.talim-picker-open .modal.show:not(.talim-picker-modal) .talim-form-modal {
            pointer-events: none !important;
        }
        body.talim-embed .btn-outline-info {
            border-color: #0891b2 !important;
            color: #0e7490 !important;
        }
        body.talim-embed .talim-card-actions .btn {
            width: 38px;
            height: 38px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        body.talim-embed .talim-form-modal {
            position: fixed !important;
            top: 72px !important;
            bottom: max(96px, calc(72px + env(safe-area-inset-bottom, 0px))) !important;
            left: 12px !important;
            right: 12px !important;
            margin: 0 !important;
            width: auto !important;
            max-width: none !important;
            height: auto !important;
            transform: none !important;
            display: flex !important;
            flex-direction: column !important;
            pointer-events: none;
        }
        body.talim-embed .modal.show .talim-form-modal {
            pointer-events: auto;
        }
        body.talim-embed .talim-form-modal .modal-content {
            flex: 1 1 auto;
            display: flex !important;
            flex-direction: column !important;
            max-height: 100% !important;
            height: 100% !important;
            border-radius: 20px !important;
            overflow: hidden !important;
            box-shadow: 0 16px 48px rgba(6, 95, 70, 0.22) !important;
            border: 1px solid rgba(6, 95, 70, 0.1) !important;
        }
        body.talim-embed .talim-form-modal .modal-header {
            flex-shrink: 0;
            border-bottom: 1px solid #e5e7eb;
            padding: 14px 16px;
        }
        body.talim-embed .talim-form-modal .modal-body {
            flex: 1 1 auto;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
            padding: 16px;
            max-height: none !important;
        }
        body.talim-embed .talim-form-modal .modal-footer {
            flex-shrink: 0 !important;
            display: flex !important;
        }
        body.talim-embed .talim-form-modal .modal-footer .btn {
            flex: 1 1 0;
        }
        body.talim-embed .dropdown-menu {
            z-index: 10050 !important;
            margin-bottom: 8px;
        }
    </style>';
}

function talimEmbedModalJs(): string
{
    if (!isTalimEmbed()) {
        return '';
    }

    return <<<'JS'
<script>
window.talimShowPickerModal = function (modalId) {
    var el = document.getElementById(modalId);
    if (!el || typeof bootstrap === 'undefined') return;

    el.classList.add('talim-picker-modal');

    var modal = bootstrap.Modal.getOrCreateInstance(el, {
        backdrop: true,
        keyboard: true,
        focus: true
    });

    function markBackdrop() {
        var backs = document.querySelectorAll('.modal-backdrop');
        if (backs.length) {
            var top = backs[backs.length - 1];
            top.classList.add('talim-picker-backdrop');
            top.style.zIndex = '10110';
        }
        el.style.zIndex = '10120';
        document.body.classList.add('talim-picker-open');
    }

    function clearPickerState() {
        document.body.classList.remove('talim-picker-open');
        document.querySelectorAll('.modal-backdrop.talim-picker-backdrop').forEach(function (b) {
            b.classList.remove('talim-picker-backdrop');
        });
    }

    el.addEventListener('shown.bs.modal', markBackdrop, { once: true });
    el.addEventListener('hidden.bs.modal', clearPickerState, { once: true });
    modal.show();
};

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () {
            document.body.classList.add('talim-modal-open');
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('talim-modal-open');
                document.body.classList.remove('talim-picker-open');
            }
        });
    });
});
</script>
JS;
}
?>
