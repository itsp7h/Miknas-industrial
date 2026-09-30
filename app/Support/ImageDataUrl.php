<?php

namespace App\Support;

/**
 * An image posted as a data URL — a drawn or uploaded signature, a company's
 * logo or stamp. It is stored and later rendered into PDFs, so it must really
 * be a small PNG or JPEG, not whatever string was posted.
 */
class ImageDataUrl
{
    public const MAX_BYTES = 512 * 1024;

    public const MAX_SIDE = 2000;

    public static function isValid(string $value): bool
    {
        if (! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $value, $m)) {
            return false;
        }

        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) > self::MAX_BYTES) {
            return false;
        }

        $info = @getimagesizefromstring($bytes);

        return $info !== false
            && in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
            && $info[0] <= self::MAX_SIDE && $info[1] <= self::MAX_SIDE;
    }

    /**
     * [width, height] to draw a data URL at so it fits `$maxW`×`$maxH`, its
     * shape kept and never enlarged; null when it is not a readable image.
     *
     * Documents set these as the <img>'s own width and height: DomPDF does
     * not reliably honour max-width/max-height, and fixed numbers draw the
     * same in a browser and in the PDF.
     */
    public static function fit(?string $value, int $maxW, int $maxH): ?array
    {
        if (! $value || ! preg_match('#^data:image/(?:png|jpeg);base64,(.+)$#', $value, $m)) {
            return null;
        }

        $info = @getimagesizefromstring((string) base64_decode($m[1], true));
        if (! $info || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        $scale = min(1, $maxW / $info[0], $maxH / $info[1]);

        return [max(1, (int) round($info[0] * $scale)), max(1, (int) round($info[1] * $scale))];
    }

    /** `['src', 'width', 'height']` for an <img> fitted to the box, or null. */
    public static function sized(?string $value, int $maxW, int $maxH): ?array
    {
        $size = self::fit($value, $maxW, $maxH);

        return $size ? ['src' => $value, 'width' => $size[0], 'height' => $size[1]] : null;
    }
}
