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
        return 'modal-dialog modal-dialog-scrollable' . ($extra !== '' ? ' ' . $extra : '');
    }

    return 'modal-dialog talim-form-modal' . ($extra !== '' ? ' ' . $extra : '');
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
            z-index: 9999 !important;
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
            bottom: 136px !important;
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
        body.talim-embed .talim-form-modal .modal-content > form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            height: 100%;
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
            flex-shrink: 0;
            display: flex;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
            gap: 8px;
        }
        body.talim-embed .talim-form-modal .modal-footer .btn {
            min-height: 44px;
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
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () {
            document.body.classList.add('talim-modal-open');
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('talim-modal-open');
            }
        });
    });
});
</script>
JS;
}
?>
