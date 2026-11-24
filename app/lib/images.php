<?php
/**
 * Upload optimisation (GD): auto-rotate, cap the original at 2560 px, strip metadata (EXIF/GPS),
 * and generate responsive WebP versions that img_tag() serves via srcset.
 * Without GD the upload is stored unchanged and the site still works.
 */
declare(strict_types=1);

const IMAGE_MAX_SIDE = 2560;
const IMAGE_WIDTHS = [480, 800, 1200, 1600];
const IMAGE_JPEG_Q = 82;
const IMAGE_WEBP_Q = 78;

function images_supported(): bool
{
    return extension_loaded('gd') && function_exists('imagewebp');
}

/** Load an image file into GD, applying EXIF orientation for JPEGs. */
function image_load(string $file, string $mime): ?GdImage
{
    $im = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file),
        'image/png' => @imagecreatefrompng($file),
        'image/webp' => @imagecreatefromwebp($file),
        default => false,
    };
    if (!$im) return null;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($file)['Orientation'] ?? 1);
        $im = match ($o) {
            3 => imagerotate($im, 180, 0),
            6 => imagerotate($im, -90, 0),
            8 => imagerotate($im, 90, 0),
            default => $im,
        } ?: $im;
    }
    if ($mime !== 'image/jpeg') { imagealphablending($im, false); imagesavealpha($im, true); }
    return $im;
}

function image_resize(GdImage $src, int $w): GdImage
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($w >= $sw) return $src;
    $h = max(1, (int) round($sh * $w / $sw));
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
    return $dst;
}

/**
 * Optimise a stored upload in place and create WebP variants.
 * @param string $relPath e.g. uploads/2026/11/photo-abc123.jpg
 * @return array{width:int,height:int,size:int,variants:int[]}|null
 */
function image_optimize(string $relPath, string $mime): ?array
{
    if (!images_supported() || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return null;
    $file = PUBLIC_DIR . '/' . $relPath;
    $im = image_load($file, $mime);
    if (!$im) return null;
    $w = imagesx($im);
    $h = imagesy($im);
    // 1. Cap the original and re-encode (drops EXIF/GPS metadata)
    $scale = min(1, IMAGE_MAX_SIDE / max($w, $h));
    if ($scale < 1) { $im = image_resize($im, (int) round($w * $scale)); $w = imagesx($im); $h = imagesy($im); }
    $tmp = $file . '.tmp';
    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($im, $tmp, IMAGE_JPEG_Q),
        'image/png' => imagepng($im, $tmp, 8),
        'image/webp' => imagewebp($im, $tmp, IMAGE_WEBP_Q),
    };
    if ($ok && filesize($tmp) > 0 && ($scale < 1 || filesize($tmp) < filesize($file))) rename($tmp, $file); else @unlink($tmp);
    // 2. Responsive WebP variants (never upscaled)
    $base = preg_replace('~\.[a-z0-9]+$~i', '', $file);
    $made = [];
    foreach (IMAGE_WIDTHS as $vw) {
        $real = min($vw, $w); // the file name and srcset descriptor always state the true width
        if (imagewebp(image_resize($im, $real), "{$base}-{$real}.webp", IMAGE_WEBP_Q)) $made[] = $real;
        if ($vw >= $w) break;
    }
    clearstatcache();
    return ['width' => $w, 'height' => $h, 'size' => (int) filesize($file), 'variants' => $made];
}

function image_variant_files(string $relPath, array $variants): array
{
    $base = preg_replace('~\.[a-z0-9]+$~i', '', PUBLIC_DIR . '/' . $relPath);
    return array_map(fn($w) => "{$base}-{$w}.webp", $variants);
}

/** Variant widths for an uploaded image path (one query per request, cached). */
function media_variants(string $relPath): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            foreach (db_all("SELECT path, variants FROM media WHERE variants IS NOT NULL AND variants <> '[]'") as $r) {
                $map[$r['path']] = array_map('intval', json_decode((string) $r['variants'], true) ?: []);
            }
        } catch (Throwable) { /* before install */ }
    }
    return $map[ltrim($relPath, '/')] ?? [];
}
