<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Settings\Company;
use Illuminate\Support\Carbon;

/**
 * LPO numbers: `ST-LPO-26-0001`.
 *
 * The company's own code, the document, the two-digit year, and a sequence
 * that runs per company per year. Miknas Industrial's first LPO of 2026 is
 * MI-LPO-26-0001 whatever Steel Tech has issued.
 *
 * The sequence is read back from the numbers already issued under the same
 * prefix rather than kept in a counter, so it cannot drift out of step with
 * the orders themselves — and a deleted order's number is reused, which is
 * what you want for a numbered document series with a gap in it.
 */
class LpoNumberService
{
    /** A purchase order with no company behind it still needs a number. */
    public const HOUSE_PREFIX = 'LPO';

    public function next(?Company $company, ?Carbon $on = null): string
    {
        $prefix = $this->prefix($company, $on);

        return $prefix.str_pad((string) ($this->lastSequence($prefix) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Everything before the sequence, including the trailing dash. */
    public function prefix(?Company $company, ?Carbon $on = null): string
    {
        $year = ($on ?? now())->format('y');
        $code = trim((string) ($company?->lpo_code ?? ''));

        return $code !== ''
            ? "{$code}-".self::HOUSE_PREFIX."-{$year}-"
            : self::HOUSE_PREFIX."-{$year}-";
    }

    /** What this prefix has reached. 0 when it has issued nothing. */
    private function lastSequence(string $prefix): int
    {
        return PurchaseOrder::where('po_number', 'like', $prefix.'%')
            ->pluck('po_number')
            ->map(fn ($number) => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;
    }
}
