<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The signature an LPO is issued under: the issuer's own, saved on their
 * profile. Issuing without one is refused, with a `code` the SPA recognises so
 * it can ask for the signature and try again rather than just showing an error.
 */
class IssuerSignature
{
    public const MISSING = 'signature_required';

    public static function require(?User $user): string
    {
        if (! $user?->signature_image) {
            throw new HttpResponseException(response()->json([
                'message' => 'Add your signature before issuing an LPO. It is saved to your profile and used on every LPO you issue.',
                'code' => self::MISSING,
            ], 422));
        }

        return $user->signature_image;
    }

    /**
     * A drawn or uploaded signature arrives as a data URL. It is stored and
     * later rendered into PDFs, so it must really be a small PNG or JPEG, not
     * whatever string was posted.
     */
    public static function isValidImage(string $value): bool
    {
        if (! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $value, $m)) {
            return false;
        }

        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) > 512 * 1024) {
            return false;
        }

        $info = @getimagesizefromstring($bytes);

        return $info !== false
            && in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
            && $info[0] <= 2000 && $info[1] <= 2000;
    }
}
