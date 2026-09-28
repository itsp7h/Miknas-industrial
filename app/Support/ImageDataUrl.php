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
}
