<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A stored (UTC) time worded for the people reading it: in
 * config('app.display_timezone'), Bahrain unless the box says otherwise. For
 * text the server writes itself: a printed document, a PDF, a "d M Y, H:i"
 * string in a payload. An ISO string the browser formats needs none of this.
 */
final class LocalTime
{
    /** "30 Sep 2026, 14:35" for a time stored as 11:35 UTC; null stays null. */
    public static function format(?CarbonInterface $time, string $format = 'd M Y, H:i'): ?string
    {
        return $time?->copy()->setTimezone(self::zone())->format($format);
    }

    /** Now, in the display timezone. */
    public static function now(): Carbon
    {
        return Carbon::now(self::zone());
    }

    private static function zone(): string
    {
        return config('app.display_timezone') ?: config('app.timezone');
    }
}
