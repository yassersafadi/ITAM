<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * A scanned or photographed signature made ready for documents: the paper is removed (transparent background, even when
 * the scan is shaded or unevenly lit), the ink keeps its colour, and the picture is cropped to the signature.
 */
final class SignatureImage
{
    private const MAX_WORK = 1400;   // longest side while processing
    private const MAX_WIDTH = 640;   // width of the finished picture
    private const CELL = 24;         // size of the squares the paper's brightness is measured on
    private const FADE_FROM = 16;    // this much darker than the paper starts to show...
    private const FADE_TO = 60;      // ...and this much darker is solid ink

    /** PNG bytes of the signature with a transparent background, or null when the file holds no picture or no ink. */
    public static function clean(string $file): ?string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (!$src) {
            return null;
        }
        $src = self::upright($src, $file);
        $img = self::flatten($src);
        $w = imagesx($img);
        $h = imagesy($img);
        $paper = self::paper($img, $w, $h);
        $n = (int) ceil($w / self::CELL);

        $out = self::canvas($w, $h);
        [$left, $top, $right, $bottom] = [$w, $h, -1, -1];
        for ($y = 0; $y < $h; $y++) {
            $row = intdiv($y, self::CELL) * $n;
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 255;
                $g = ($rgb >> 8) & 255;
                $b = $rgb & 255;
                $bg = $paper[$row + intdiv($x, self::CELL)];
                $a = (($bg - self::luma($r, $g, $b)) - self::FADE_FROM) / (self::FADE_TO - self::FADE_FROM);
                if ($a <= 0) {
                    continue;
                }
                $a = min(1.0, $a);
                // Take the paper out of the pixel so the edges of the strokes do not keep a pale halo.
                $ink = fn (int $c) => (int) max(0, min(255, ($c - (1 - $a) * $bg) / $a));
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $ink($r), $ink($g), $ink($b), (int) round(127 * (1 - $a))));
                if ($a >= 0.5) {
                    [$left, $top, $right, $bottom] = [min($left, $x), min($top, $y), max($right, $x), max($bottom, $y)];
                }
            }
        }
        // A real signature leaves more than a speck.
        if ($right - $left < 20 || $bottom - $top < 6) {
            return null;
        }
        $pad = 6;
        $left = max(0, $left - $pad);
        $top = max(0, $top - $pad);
        $cw = min($w - 1, $right + $pad) - $left + 1;
        $ch = min($h - 1, $bottom + $pad) - $top + 1;
        $scale = min(1, self::MAX_WIDTH / $cw);
        $fw = max(1, (int) round($cw * $scale));
        $fh = max(1, (int) round($ch * $scale));
        $final = self::canvas($fw, $fh);
        imagecopyresampled($final, $out, 0, 0, $left, $top, $fw, $fh, $cw, $ch);
        ob_start();
        imagepng($final, null, 9);
        return (string) ob_get_clean();
    }

    /** Photos from a phone can be stored on their side. */
    private static function upright(\GdImage $img, string $file): \GdImage
    {
        $exif = function_exists('exif_read_data') ? (@exif_read_data($file) ?: []) : [];
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        return $angle ? (imagerotate($img, $angle, 0) ?: $img) : $img;
    }

    /** The picture on white (a PNG may already be transparent), at a size that is quick to work on. */
    private static function flatten(\GdImage $src): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_WORK / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $img = imagecreatetruecolor($tw, $th);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        return $img;
    }

    /**
     * The brightness of the paper around each square: the brightest pixels of the square and its neighbours, so a shadow
     * across the page is followed and a thick stroke does not pass for paper.
     * @return float[] by square, row after row
     */
    private static function paper(\GdImage $img, int $w, int $h): array
    {
        $n = (int) ceil($w / self::CELL);
        $m = (int) ceil($h / self::CELL);
        $peak = array_fill(0, $n * $m, 0.0);
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $rgb = imagecolorat($img, $x, $y);
                $i = intdiv($y, self::CELL) * $n + intdiv($x, self::CELL);
                $peak[$i] = max($peak[$i], self::luma(($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255));
            }
        }
        $out = $peak;
        for ($j = 0; $j < $m; $j++) {
            for ($i = 0; $i < $n; $i++) {
                for ($dj = max(0, $j - 1); $dj <= min($m - 1, $j + 1); $dj++) {
                    for ($di = max(0, $i - 1); $di <= min($n - 1, $i + 1); $di++) {
                        $out[$j * $n + $i] = max($out[$j * $n + $i], $peak[$dj * $n + $di]);
                    }
                }
            }
        }
        return $out;
    }

    private static function luma(int $r, int $g, int $b): float
    {
        return 0.299 * $r + 0.587 * $g + 0.114 * $b;
    }

    private static function canvas(int $w, int $h): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        return $img;
    }
}
