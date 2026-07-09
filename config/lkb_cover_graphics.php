<?php
/**
 * Lightweight vector cover graphics for LKB PDF (FPDF).
 * Wave-style corner accents inspired by modern formal report covers.
 */

require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

class LkbFpdf extends FPDF
{
    private const COLOR_TEAL = [0, 169, 157];
    private const COLOR_NAVY = [0, 74, 77];
    private const COLOR_GREY = [166, 166, 166];

    public function drawLkbCoverBackground(): void
    {
        $w = $this->GetPageWidth();
        $h = $this->GetPageHeight();

        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, $w, $h, 'F');

        // Top-right layered waves (back to front)
        $this->fillWaveRibbon([
            [210, 0], [210, 88], [176, 24], [118, 48], [76, 72], [92, 118], [210, 172],
        ], self::COLOR_GREY);

        $this->fillWaveRibbon([
            [210, 0], [210, 66], [186, 16], [142, 36], [104, 56], [120, 92], [210, 126],
        ], self::COLOR_NAVY);

        $this->fillWaveRibbon([
            [210, 0], [210, 44], [198, 10], [168, 24], [148, 38], [158, 62], [210, 86],
        ], self::COLOR_TEAL);

        // Bottom-left mirrored waves
        $this->fillWaveRibbon([
            [0, 297], [0, 209], [34, 273], [92, 249], [134, 225], [118, 179], [0, 125],
        ], self::COLOR_GREY);

        $this->fillWaveRibbon([
            [0, 297], [0, 231], [24, 281], [68, 261], [106, 241], [90, 205], [0, 171],
        ], self::COLOR_NAVY);

        $this->fillWaveRibbon([
            [0, 297], [0, 253], [12, 287], [42, 273], [62, 259], [52, 235], [0, 211],
        ], self::COLOR_TEAL);

        $this->resetDrawingDefaults();
    }

    private function resetDrawingDefaults(): void
    {
        $this->SetDrawColor(0);
        $this->SetFillColor(255);
        $this->SetTextColor(0);
        $this->SetLineWidth(0.2);
    }

    /**
     * @param array<int, array{0: float, 1: float}> $points
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function fillWaveRibbon(array $points, array $rgb): void
    {
        if (count($points) < 4) {
            return;
        }

        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $path = $this->pathMove($points[0][0], $points[0][1]);

        for ($i = 1; $i + 2 < count($points); $i += 3) {
            $path .= $this->pathCurve(
                $points[$i][0],
                $points[$i][1],
                $points[$i + 1][0],
                $points[$i + 1][1],
                $points[$i + 2][0],
                $points[$i + 2][1]
            );
        }

        $first = $points[0];
        $path .= $this->pathLine($first[0], $first[1]);
        $path .= ' h f';

        $this->_out($path);
    }

    private function pathMove(float $x, float $y): string
    {
        return sprintf('%F %F m ', $x * $this->k, ($this->h - $y) * $this->k);
    }

    private function pathLine(float $x, float $y): string
    {
        return sprintf('%F %F l ', $x * $this->k, ($this->h - $y) * $this->k);
    }

    private function pathCurve(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): string
    {
        return sprintf(
            '%F %F %F %F %F %F c ',
            $x1 * $this->k,
            ($this->h - $y1) * $this->k,
            $x2 * $this->k,
            ($this->h - $y2) * $this->k,
            $x3 * $this->k,
            ($this->h - $y3) * $this->k
        );
    }
}
