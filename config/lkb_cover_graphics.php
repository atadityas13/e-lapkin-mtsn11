<?php
/**
 * Formal LKB cover page for FPDF — lightweight vector, institutional style.
 */

require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

class LkbFpdf extends FPDF
{
    private const PAGE_W = 210.0;
    private const PAGE_H = 297.0;

    private const GREEN = [14, 92, 72];
    private const GREEN_DARK = [9, 62, 49];
    private const GOLD = [184, 150, 75];
    private const PANEL = [248, 251, 249];

    public function renderLkbCoverPage(
        string $bulanLabel,
        int $tahun,
        string $namaPegawai,
        string $nip,
        string $logoPath
    ): void {
        $this->drawLkbCoverBackground();

        $this->SetTextColor(...self::GREEN_DARK);
        $this->SetFont('Times', 'B', 20);
        $this->SetXY(0, 52);
        $this->Cell(self::PAGE_W, 9, 'LAPORAN KINERJA BULANAN', 0, 1, 'C');

        $this->drawOrnament(66);

        $this->SetTextColor(30, 30, 30);
        $this->SetFont('Times', 'B', 15);
        $this->Cell(self::PAGE_W, 8, 'BULAN ' . strtoupper($bulanLabel), 0, 1, 'C');

        $this->SetTextColor(...self::GREEN_DARK);
        $this->SetFont('Times', 'B', 26);
        $this->Cell(self::PAGE_W, 12, (string) $tahun, 0, 1, 'C');

        $logoY = 108;
        if ($logoPath !== '' && file_exists($logoPath)) {
            $this->Image($logoPath, (self::PAGE_W / 2) - 22, $logoY, 44, 44);
        }

        $this->drawOrnament(168);

        $this->SetTextColor(20, 20, 20);
        $this->SetFont('Times', 'B', 14);
        $this->SetXY(0, 178);
        $this->Cell(self::PAGE_W, 8, $namaPegawai, 0, 1, 'C');

        $this->SetFont('Times', '', 12);
        $this->Cell(self::PAGE_W, 7, 'NIP. ' . $nip, 0, 1, 'C');

        $this->SetTextColor(...self::GREEN_DARK);
        $this->SetFont('Times', 'B', 13);
        $this->SetXY(0, 228);
        $this->Cell(self::PAGE_W, 7, 'MTsN 11 MAJALENGKA', 0, 1, 'C');

        $this->SetFont('Times', 'B', 11);
        $this->Cell(self::PAGE_W, 6, 'KEMENTERIAN AGAMA KABUPATEN MAJALENGKA', 0, 1, 'C');

        $this->resetDrawingDefaults();
    }

    public function drawLkbCoverBackground(): void
    {
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, self::PAGE_W, self::PAGE_H, 'F');

        // Top institutional band
        $this->SetFillColor(...self::GREEN);
        $this->Rect(0, 0, self::PAGE_W, 22, 'F');
        $this->SetFillColor(...self::GOLD);
        $this->Rect(0, 22, self::PAGE_W, 0.8, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Times', 'B', 10);
        $this->SetXY(0, 7);
        $this->Cell(self::PAGE_W, 5, 'REPUBLIK INDONESIA', 0, 1, 'C');
        $this->SetFont('Times', '', 9);
        $this->Cell(self::PAGE_W, 5, 'KEMENTERIAN AGAMA REPUBLIK INDONESIA', 0, 1, 'C');

        // Bottom band
        $this->SetFillColor(...self::GOLD);
        $this->Rect(0, self::PAGE_H - 17.8, self::PAGE_W, 0.8, 'F');
        $this->SetFillColor(...self::GREEN);
        $this->Rect(0, self::PAGE_H - 17, self::PAGE_W, 17, 'F');

        // Inner content panel
        $this->SetFillColor(...self::PANEL);
        $this->Rect(16, 38, self::PAGE_W - 32, 205, 'F');

        // Double frame
        $this->SetDrawColor(...self::GREEN);
        $this->SetLineWidth(0.55);
        $this->Rect(12, 34, self::PAGE_W - 24, 213);

        $this->SetDrawColor(...self::GOLD);
        $this->SetLineWidth(0.25);
        $this->Rect(14.5, 36.5, self::PAGE_W - 29, 208);

        $this->drawCornerBrackets(18, 40, 10);
        $this->drawCornerBrackets(self::PAGE_W - 18, 40, 10, true, false);
        $this->drawCornerBrackets(18, self::PAGE_H - 40, 10, false, false);
        $this->drawCornerBrackets(self::PAGE_W - 18, self::PAGE_H - 40, 10, true, true);

        $this->resetDrawingDefaults();
    }

    private function drawOrnament(float $y): void
    {
        $center = self::PAGE_W / 2;
        $half = 36.0;

        $this->SetDrawColor(...self::GOLD);
        $this->SetFillColor(...self::GOLD);
        $this->SetLineWidth(0.35);

        $this->Line($center - $half, $y, $center - 4, $y);
        $this->Line($center + 4, $y, $center + $half, $y);

        $size = 2.2;
        $this->Rect($center - $size, $y - $size, $size * 2, $size * 2, 'DF');
    }

    private function drawCornerBrackets(
        float $x,
        float $y,
        float $len,
        bool $right = false,
        bool $bottom = false
    ): void {
        $this->SetDrawColor(...self::GREEN);
        $this->SetLineWidth(0.45);

        $dx = $right ? -$len : $len;
        $dy = $bottom ? -$len : $len;

        $this->Line($x, $y, $x + $dx, $y);
        $this->Line($x, $y, $x, $y + $dy);
    }

    private function resetDrawingDefaults(): void
    {
        $this->SetDrawColor(0);
        $this->SetFillColor(255);
        $this->SetTextColor(0);
        $this->SetLineWidth(0.2);
    }
}
