<?php
declare(strict_types=1);
/**
 * Draws ITAM's default logos with GD: the mark (a box on a blue-to-teal tile), the favicon, and the full logo (the mark
 * and the word ITAM). Run again after changing the colours:  php scripts/make-logo.php [path to a bold TTF font]
 */
if (!function_exists('imagecreatetruecolor')) {
    exit("The gd extension is needed.\n");
}
$out = dirname(__DIR__) . '/public/assets/img';
$font = $argv[1] ?? 'C:/Windows/Fonts/segoeuib.ttf';
const SS = 4;   // drawn this many times larger, then scaled down for smooth edges

function mark(int $size): GdImage
{
    $s = $size * SS;
    $img = imagecreatetruecolor($s, $s);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagealphablending($img, true);
    // Rounded tile with a diagonal gradient #1677e6 → #22c3a6.
    $pad = (int) ($s * 0.06);
    $r = (int) ($s * 0.22);
    for ($y = $pad; $y < $s - $pad; $y++) {
        for ($x = $pad; $x < $s - $pad; $x++) {
            $dx = max($pad + $r - $x, 0, $x - ($s - $pad - $r - 1));
            $dy = max($pad + $r - $y, 0, $y - ($s - $pad - $r - 1));
            if ($dx * $dx + $dy * $dy > $r * $r) {
                continue;
            }
            $t = (($x - $pad) + ($y - $pad)) / (2 * ($s - 2 * $pad));
            imagesetpixel($img, $x, $y, imagecolorallocate($img, (int) (0x16 + (0x22 - 0x16) * $t), (int) (0x77 + (0xc3 - 0x77) * $t), (int) (0xe6 + (0xa6 - 0xe6) * $t)));
        }
    }
    // An open box (isometric cube outline) in white.
    $white = imagecolorallocate($img, 255, 255, 255);
    imagesetthickness($img, (int) max(2, $s * 0.05));
    $c = $s / 2;
    $w = $s * 0.19;
    $h = $s * 0.095;
    $top = $c - $s * 0.2;
    $pts = [
        [$c - $w, $top + $h], [$c, $top], [$c + $w, $top + $h], [$c + $w, $top + $h + $s * 0.24], [$c, $top + 2 * $h + $s * 0.24], [$c - $w, $top + $h + $s * 0.24],
    ];
    $line = fn ($a, $b) => imageline($img, (int) $a[0], (int) $a[1], (int) $b[0], (int) $b[1], $white);
    for ($i = 0; $i < 6; $i++) {
        $line($pts[$i], $pts[($i + 1) % 6]);
    }
    $mid = [$c, $top + 2 * $h];
    $line($pts[0], $mid);
    $line($pts[2], $mid);
    $line($mid, $pts[4]);
    return shrink($img, $size, $size);
}

function shrink(GdImage $big, int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagecopyresampled($img, $big, 0, 0, 0, 0, $w, $h, imagesx($big), imagesy($big));
    return $img;
}

imagepng(mark(512), "$out/logo-mark.png");
imagepng(mark(64), "$out/favicon.png");

// Full logo: the mark, then "ITAM" in the dark ink of the sidebar.
$h = 160;
$markImg = mark($h);
$full = imagecreatetruecolor(560 * SS, $h * SS);
imagesavealpha($full, true);
imagealphablending($full, false);
imagefill($full, 0, 0, imagecolorallocatealpha($full, 0, 0, 0, 127));
imagealphablending($full, true);
imagecopyresampled($full, $markImg, 0, 0, 0, 0, $h * SS, $h * SS, $h, $h);
if (is_file($font)) {
    imagettftext($full, 78 * SS, 0, (int) ($h * 1.18 * SS), (int) ($h * 0.74 * SS), imagecolorallocate($full, 0x0b, 0x1b, 0x3f), $font, 'ITAM');
}
imagepng(shrink($full, 560, $h), "$out/logo-full.png");
echo "logos written to $out\n";
