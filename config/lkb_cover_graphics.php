<?php
/**
 * Lightweight vector cover graphics for LKB PDF (FPDF).
 * Dual-curve wave ribbons — matches modern report cover style.
 */

require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

class LkbFpdf extends FPDF
{
    private const PAGE_W = 210.0;
    private const PAGE_H = 297.0;

    private const COLOR_TEAL = [0, 169, 157];
    private const COLOR_NAVY = [0, 74, 77];
    private const COLOR_GREY = [166, 166, 166];

    public function drawLkbCoverBackground(): void
    {
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, self::PAGE_W, self::PAGE_H, 'F');

        // Top-right corner — back to front
        $this->drawCornerRibbon(
            [210, 0],
            [[210, 58], [152, 4], [82, 32], [52, 52], [68, 108], [210, 168]],
            [[192, 136], [142, 86], [210, 72], [210, 46], [210, 20], [210, 0]],
            self::COLOR_GREY
        );
        $this->drawCornerRibbon(
            [210, 0],
            [[208, 38], [170, 6], [118, 24], [82, 42], [98, 82], [210, 112]],
            [[194, 88], [158, 58], [210, 52], [210, 32], [210, 14], [210, 0]],
            self::COLOR_NAVY
        );
        $this->drawCornerRibbon(
            [210, 0],
            [[210, 26], [190, 2], [158, 14], [138, 26], [146, 48], [210, 62]],
            [[202, 50], [184, 32], [210, 24], [210, 12], [210, 4], [210, 0]],
            self::COLOR_TEAL
        );

        // Bottom-left corner — mirrored
        $this->drawCornerRibbon(
            [0, 297],
            [[0, 239], [58, 293], [128, 265], [158, 245], [142, 189], [0, 129]],
            [[18, 161], [68, 211], [0, 225], [0, 251], [0, 277], [0, 297]],
            self::COLOR_GREY
        );
        $this->drawCornerRibbon(
            [0, 297],
            [[2, 259], [40, 291], [92, 273], [128, 255], [112, 215], [0, 185]],
            [[16, 209], [52, 239], [0, 245], [0, 265], [0, 283], [0, 297]],
            self::COLOR_NAVY
        );
        $this->drawCornerRibbon(
            [0, 297],
            [[0, 271], [20, 295], [52, 283], [72, 271], [64, 249], [0, 235]],
            [[8, 247], [26, 265], [0, 273], [0, 285], [0, 293], [0, 297]],
            self::COLOR_TEAL
        );

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
     * @param array{0: float, 1: float} $start
     * @param array<int, array{0: float, 1: float}> $outer
     * @param array<int, array{0: float, 1: float}> $inner
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function drawCornerRibbon(array $start, array $outer, array $inner, array $rgb): void
    {
        if (count($outer) % 3 !== 0 || count($inner) % 3 !== 0) {
            return;
        }

        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);

        $path = $this->pathMove($start[0], $start[1]);

        for ($i = 0; $i < count($outer); $i += 3) {
            $path .= $this->pathCurve(
                $outer[$i][0],
                $outer[$i][1],
                $outer[$i + 1][0],
                $outer[$i + 1][1],
                $outer[$i + 2][0],
                $outer[$i + 2][1]
            );
        }

        for ($i = 0; $i < count($inner); $i += 3) {
            $path .= $this->pathCurve(
                $inner[$i][0],
                $inner[$i][1],
                $inner[$i + 1][0],
                $inner[$i + 1][1],
                $inner[$i + 2][0],
                $inner[$i + 2][1]
            );
        }

        $path .= ' h f';
        $this->_out($path);
    }

    private function pathMove(float $x, float $y): string
    {
        return sprintf('%F %F m ', $x * $this->k, ($this->h - $y) * $this->k);
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
