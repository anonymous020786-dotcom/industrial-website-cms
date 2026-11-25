<?php
/**
 * Minimal PDF writer (no library): A4 pages, Helvetica / Helvetica-Bold, text with real width
 * measurement (for wrapping and right alignment), lines and filled rectangles. Coordinates are
 * in points from the TOP-left corner. Text is encoded as Windows-1252 (the standard PDF fonts'
 * WinAnsi encoding), so characters outside it are replaced.
 */
declare(strict_types=1);

final class Pdf
{
    public const W = 595.28;
    public const H = 841.89;
    private array $pages = [];
    private int $page = -1;

    // Advance widths (1/1000 em) for ASCII 32–126, from the standard Helvetica AFM files
    private const HELV = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const HELV_B = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->page = count($this->pages) - 1;
    }

    public function pageCount(): int { return count($this->pages); }

    public function setPage(int $i): void { $this->page = $i; }

    private static function enc(string $s): string
    {
        $s = strtr($s, ['₹' => 'Rs.', '–' => '-', '—' => '-', '•' => '-', '…' => '...', "\u{2009}" => ' ', "\u{00A0}" => ' ']);
        $c = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return is_string($c) ? $c : preg_replace('~[^\x20-\x7E]~', '?', $s);
    }

    public function width(string $s, float $size, bool $bold = false): float
    {
        $t = self::enc($s);
        $m = $bold ? self::HELV_B : self::HELV;
        $w = 0;
        for ($i = 0, $n = strlen($t); $i < $n; $i++) {
            $o = ord($t[$i]);
            $w += ($o >= 32 && $o <= 126) ? $m[$o - 32] : 556;
        }
        return $w * $size / 1000;
    }

    /** Split text into lines no wider than $max (respects existing line breaks). */
    public function wrap(string $s, float $max, float $size, bool $bold = false): array
    {
        $out = [];
        foreach (preg_split('~\R~', $s) ?: [''] as $para) {
            $line = '';
            foreach (preg_split('~\s+~', trim($para)) ?: [] as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($this->width($try, $size, $bold) <= $max || $line === '') { $line = $try; continue; }
                $out[] = $line;
                $line = $word;
            }
            $out[] = $line;
        }
        return $out;
    }

    /** $align: left | right | center (x is the left edge, right edge or centre). */
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [0.15, 0.17, 0.21], string $align = 'left'): void
    {
        if ($align === 'right') $x -= $this->width($s, $size, $bold);
        elseif ($align === 'center') $x -= $this->width($s, $size, $bold) / 2;
        $t = str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], self::enc($s));
        $this->pages[$this->page] .= sprintf("BT %.3F %.3F %.3F rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $bold ? 'F2' : 'F1', $size, $x, self::H - $y, $t);
    }

    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->pages[$this->page] .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $rgb[0], $rgb[1], $rgb[2], $x, self::H - $y - $h, $w, $h);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.85, 0.87, 0.9], float $width = 0.75): void
    {
        $this->pages[$this->page] .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $rgb[0], $rgb[1], $rgb[2], $width, $x1, self::H - $y1, $x2, self::H - $y2);
    }

    public function output(string $title = ''): string
    {
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $n = 5;
        foreach ($this->pages as $content) {
            $kids[] = "{$n} 0 R";
            $objs[$n] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($n + 1) . ' 0 R >>';
            $data = gzcompress($content);
            $objs[$n + 1] = '<< /Length ' . strlen($data) . " /Filter /FlateDecode >>\nstream\n" . $data . "\nendstream";
            $n += 2;
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $info = $n;
        $objs[$info] = '<< /Title (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], self::enc($title)) . ') /Producer (Industrial CMS) /CreationDate (D:' . date('YmdHis') . ') >>';
        ksort($objs);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $body) { $offsets[$id] = strlen($pdf); $pdf .= "{$id} 0 obj\n{$body}\nendobj\n"; }
        $xref = strlen($pdf);
        $count = max(array_keys($objs)) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        return $pdf . "trailer\n<< /Size {$count} /Root 1 0 R /Info {$info} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
