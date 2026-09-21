<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The money settings: the VAT rate and the currency amounts are shown in.
 *
 * These were the VAT page on its own. They are one page because they are one
 * subject, and because the next settings of this kind — payment terms, a
 * fiscal year, rounding — belong beside them rather than in a menu entry each.
 */
class FinanceController extends Controller
{
    /**
     * Code to the symbol amounts are printed with. Bahrain first: it is the
     * default, and BHD is written "BD" rather than with its ISO code.
     */
    public const CURRENCIES = [
        'BHD' => 'BD',
        'SAR' => 'SR',
        'AED' => 'AED',
        'KWD' => 'KD',
        'OMR' => 'OMR',
        'QAR' => 'QR',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    public const DEFAULT_CURRENCY = 'BHD';

    public function show(): JsonResponse
    {
        $code = self::currencyCode();

        return response()->json([
            'vat_rate' => (float) Setting::get('vat_rate', '0'),
            'currency_code' => $code,
            'currency_symbol' => self::CURRENCIES[$code],
            'currencies' => collect(self::CURRENCIES)
                ->map(fn ($symbol, $currency) => [
                    'code' => $currency,
                    'symbol' => $symbol,
                    'label' => "{$currency} ({$symbol})",
                ])
                ->values(),
        ]);
    }

    /**
     * Each card saves on its own, so a field that was not sent is left alone
     * rather than reset to a default.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vat_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'currency_code' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_keys(self::CURRENCIES))],
        ]);

        // Stored as strings, which is what Setting holds and what the sales
        // invoice controller reads back.
        if (array_key_exists('vat_rate', $validated)) {
            Setting::set('vat_rate', (string) $validated['vat_rate']);
        }

        if (array_key_exists('currency_code', $validated)) {
            Setting::set('currency_code', $validated['currency_code']);
        }

        $code = self::currencyCode();

        return response()->json([
            'message' => array_key_exists('currency_code', $validated) && ! array_key_exists('vat_rate', $validated)
                ? 'Currency saved.'
                : 'VAT rate saved.',
            'vat_rate' => (float) Setting::get('vat_rate', '0'),
            'currency_code' => $code,
            'currency_symbol' => self::CURRENCIES[$code],
        ]);
    }

    /** Falls back to Bahrain, and to Bahrain again if the stored code is unknown. */
    public static function currencyCode(): string
    {
        $code = (string) Setting::get('currency_code', self::DEFAULT_CURRENCY);

        return isset(self::CURRENCIES[$code]) ? $code : self::DEFAULT_CURRENCY;
    }
}
