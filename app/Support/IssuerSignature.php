<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The signature an LPO is issued — and approved — under: the signer's own,
 * saved on their profile. Issuing without one is refused, with a `code` the SPA recognises so
 * it can ask for the signature and try again rather than just showing an error.
 */
class IssuerSignature
{
    public const MISSING = 'signature_required';

    public static function require(?User $user, ?string $message = null): string
    {
        if (! $user?->signature_image) {
            throw new HttpResponseException(response()->json([
                'message' => $message ?? 'Add your signature before issuing an LPO. It is saved to your profile and used on every LPO you issue.',
                'code' => self::MISSING,
            ], 422));
        }

        return $user->signature_image;
    }

    /** A drawn or uploaded signature: a small PNG or JPEG data URL (see ImageDataUrl). */
    public static function isValidImage(string $value): bool
    {
        return ImageDataUrl::isValid($value);
    }
}
