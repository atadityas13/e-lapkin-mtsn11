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
        || (isset($_GET['embed']) && $_GET['embed'] === 'talim');
}

function talimCanDirectGenerate(): bool
{
    return isTalimEmbed();
}

function talimRedirect(string $path): string
{
    return isTalimEmbed() && !str_contains($path, '?')
        ? $path . '?talim=1'
        : $path;
}

function ensureTalimTechnicalRhk(mysqli $conn, int $idPegawai, int $tahun): int
{
    $nama = 'Kinerja Guru Ta\'lim ' . $tahun;

    $stmt = $conn->prepare('SELECT id_rhk FROM rhk WHERE id_pegawai = ? AND nama_rhk = ? ORDER BY id_rhk DESC LIMIT 1');
    $stmt->bind_param('is', $idPegawai, $nama);
    $stmt->execute();
    $stmt->bind_result($idRhk);
    if ($stmt->fetch()) {
        $stmt->close();
        return (int) $idRhk;
    }
    $stmt->close();

    $aspek = 'Kuantitas';
    $target = 'Kinerja bulanan dan harian guru melalui Ta\'lim';
    $stmt = $conn->prepare('INSERT INTO rhk (id_pegawai, nama_rhk, aspek, target) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $idPegawai, $nama, $aspek, $target);
    $stmt->execute();
    $newId = (int) $conn->insert_id;
    $stmt->close();

    return $newId;
}

function talimEmbedCss(): string
{
    return '<style>
        body.talim-embed {
            background: #ecfdf5 !important;
            padding-bottom: 132px !important;
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
            z-index: 9999 !important;
        }
        body.talim-embed .modal-backdrop {
            z-index: 9998 !important;
        }
        body.talim-embed .btn-outline-info {
            border-color: #0891b2 !important;
            color: #0e7490 !important;
        }
    </style>';
}
?>
