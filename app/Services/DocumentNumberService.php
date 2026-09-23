<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Settings\Company;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Company document numbers: `ST-LPO-26-0001`, `ST-MPR-26-0001`.
 *
 * The company's own code, the document, the two-digit year, and a sequence
 * that runs per company per document per year. Miknas Industrial's first LPO
 * of 2026 is MI-LPO-26-0001 whatever Steel Tech has issued, and its MPRs
 * count separately from its LPOs.
 *
 * A company has one code for both: it is the company's code, not the
 * document's. Only the middle token tells them apart — and that token is the
 * company's own word for the document, so Matana's material requests read
 * MSF-MRF-26-0001 (see `token()`).
 *
 * Each sequence is read back from the numbers already issued under the same
 * prefix rather than kept in a counter, so it cannot drift out of step with
 * the documents themselves.
 */
class DocumentNumberService
{
    public const LPO = 'LPO';

    public const MPR = 'MPR';

    /** Which column carries each document's number. */
    private const COLUMNS = [
        self::LPO => [PurchaseOrder::class, 'po_number'],
        self::MPR => [PurchaseRequest::class, 'request_number'],
    ];

    public function next(?Company $company, string $document = self::LPO, ?Carbon $on = null): string
    {
        $prefix = $this->prefix($company, $document, $on);

        return $prefix.str_pad((string) ($this->lastSequence($prefix, $document) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Everything before the sequence, including the trailing dash. */
    public function prefix(?Company $company, string $document = self::LPO, ?Carbon $on = null): string
    {
        $this->assertKnown($document);

        $year = ($on ?? now())->format('y');
        $code = trim((string) ($company?->lpo_code ?? ''));
        $token = $this->token($company, $document);

        // A document with no company behind it still needs a number, so it
        // falls to a house series without the leading code.
        return $code !== '' ? "{$code}-{$token}-{$year}-" : "{$token}-{$year}-";
    }

    /**
     * What the middle of the number reads.
     *
     * Usually the document itself, but a company may call its material
     * request something else on its own paperwork — Matana's are MRF, not
     * MPR — and a number that does not match the document it is written on
     * helps nobody. Only the material request is the company's to name; an
     * LPO is an LPO everywhere.
     */
    public function token(?Company $company, string $document = self::LPO): string
    {
        if ($document !== self::MPR) {
            return $document;
        }

        $own = strtoupper(trim((string) ($company?->mpr_code ?? '')));

        return $own !== '' ? $own : self::MPR;
    }

    /** What this prefix has reached. 0 when it has issued nothing. */
    private function lastSequence(string $prefix, string $document): int
    {
        [$model, $column] = self::COLUMNS[$document];

        return $model::where($column, 'like', $prefix.'%')
            ->pluck($column)
            ->map(fn ($number) => (int) substr((string) $number, strlen($prefix)))
            ->max() ?? 0;
    }

    private function assertKnown(string $document): void
    {
        if (! isset(self::COLUMNS[$document])) {
            throw new InvalidArgumentException("Unknown document type [{$document}].");
        }
    }
}
