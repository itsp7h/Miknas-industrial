<?php

namespace App\Services;

use App\Models\Supplier;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SupplierImportService
{
    private array $mrfStopWords = ['comments if any', 'lowest price', 'avg price', 'recommendation'];

    public function import(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $format      = $this->detectFormat($spreadsheet);

        $rows = $format === 'mrf'
            ? $this->extractFromMrf($spreadsheet)
            : $this->extractFromTemplate($spreadsheet);

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($rows as $data) {
            $name = trim($data['name'] ?? '');
            if (empty($name)) {
                continue;
            }

            $existing = Supplier::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

            if ($existing) {
                // Update the existing record with any new/missing fields
                $existing->update($this->buildAttributes($data, true));
                $updated++;
            } else {
                Supplier::create($this->buildAttributes($data, false));
                $imported++;
            }
        }

        return [
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'format'   => $format,
        ];
    }

    private function buildAttributes(array $data, bool $isUpdate): array
    {
        $attrs = [
            'contact_person'  => $this->str($data['contact_person'] ?? ''),
            'email'           => $this->str($data['email'] ?? ''),
            'secondary_email' => $this->str($data['secondary_email'] ?? ''),
            'phone'           => $this->normalizePhone($data['phone'] ?? ''),
            'phone2'          => $this->normalizePhone($data['phone2'] ?? ''),
            'whatsapp'        => $this->normalizePhone($data['whatsapp'] ?? ''),
            'address'         => $this->str($data['address'] ?? ''),
            'website'         => $this->str($data['website'] ?? ''),
            'tax_number'      => $this->str($data['tax_number'] ?? ''),
            'credit_terms'    => $this->str($data['credit_terms'] ?? ''),
            'credit_days'     => $this->parseCreditDays($data['credit_days'] ?? ''),
            'remarks'         => $this->str($data['remarks'] ?? ''),
            'is_active'       => $this->parseBoolean($data['is_active'] ?? 'yes'),
        ];

        if (!$isUpdate) {
            $attrs['name']          = trim($data['name']);
            $attrs['supplier_code'] = $this->str($data['supplier_code'] ?? '');
            $attrs['category']      = $this->str($data['category'] ?? '');
        } else {
            // On update: only fill category/code if not already set
            if (!empty($data['supplier_code'])) {
                $attrs['supplier_code'] = $this->str($data['supplier_code']);
            }
            if (!empty($data['category'])) {
                $attrs['category'] = $this->str($data['category']);
            }
        }

        // Remove null-equivalent values so we don't overwrite real data with blanks
        return array_filter($attrs, fn($v) => $v !== null && $v !== '');
    }

    // ── Format detection ────────────────────────────────────────────────────

    private function detectFormat(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): string
    {
        $sheet = $spreadsheet->getActiveSheet();
        $a4    = strtolower(trim((string) $sheet->getCell('A4')->getValue()));
        return $a4 === 's.no' ? 'mrf' : 'template';
    }

    // ── MRF extraction ──────────────────────────────────────────────────────

    private function extractFromMrf(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        $sheet     = $spreadsheet->getActiveSheet();
        $suppliers = [];

        for ($col = 7; $col <= 50; $col++) {
            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '4';
            $value = trim((string) $sheet->getCell($coord)->getValue());

            if (empty($value) || in_array(strtolower($value), $this->mrfStopWords)) {
                break;
            }

            $suppliers[] = ['name' => $value];
        }

        return $suppliers;
    }

    // ── Template / Unified format extraction ────────────────────────────────

    private function extractFromTemplate(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        $rows    = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_map(
            fn($h) => strtolower(trim(str_replace(['*', '_'], [' ', ' '], (string) $h))),
            $rows[0] ?? []
        );

        // Map normalised header text → internal field name
        $aliases = [
            'supplier id'      => 'supplier_code',
            'name'             => 'name',
            'company name'     => 'name',
            'category'         => 'category',
            'contact person'   => 'contact_person',
            'primary email'    => 'email',
            'email'            => 'email',
            'secondary email'  => 'secondary_email',
            'phone 1'          => 'phone',
            'phone'            => 'phone',
            'phone 2'          => 'phone2',
            'whatsapp number'  => 'whatsapp',
            'whatsapp'         => 'whatsapp',
            'address'          => 'address',
            'website'          => 'website',
            'credit (y/n)'     => 'credit_terms',
            'credit days'      => 'credit_days',
            'tax number'       => 'tax_number',
            'is active'        => 'is_active',
            'remarks / key details' => 'remarks',
            'remarks'          => 'remarks',
        ];

        $map = [];
        foreach ($headers as $idx => $header) {
            if (isset($aliases[$header]) && !isset($map[$aliases[$header]])) {
                $map[$aliases[$header]] = $idx;
            }
        }

        if (!isset($map['name'])) {
            return [];
        }

        $suppliers = [];
        foreach (array_slice($rows, 1) as $row) {
            $name = trim((string) ($row[$map['name']] ?? ''));

            if (empty($name) || str_starts_with($name, '*')) {
                continue;
            }

            if (!$this->looksLikeSupplierName($name)) {
                continue;
            }

            $entry = ['name' => $name];
            foreach (array_keys($aliases) as $alias) {
                $field = $aliases[$alias];
                if ($field === 'name') continue;
                if (isset($map[$field]) && !isset($entry[$field])) {
                    $entry[$field] = trim((string) ($row[$map[$field]] ?? ''));
                }
            }

            $suppliers[] = $entry;
        }

        return $suppliers;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Reject rows that are specification text, not company names.
     * Patterns: starts with digit, measurement strings, known junk phrases.
     */
    private function looksLikeSupplierName(string $name): bool
    {
        // Starts with a digit → quantity/spec row
        if (preg_match('/^\d/', $name)) {
            return false;
        }

        $lower = strtolower($name);

        $junkPhrases = [
            'gdcd minimum', 'approx. quantity', 'approx quantity',
            'per 200 m', 'per 30 m', 'coverage mandatory',
            'hydrant within', 'smoke extraction', 'mandatory for',
            'required for buildings', '–', '—',
        ];

        foreach ($junkPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalise phone numbers to +COUNTRYCODELOCAL (digits only after +).
     * Examples: "+973 3318 8311" → "+97333188311"
     *           "39209304" (8-digit Bahrain local) → "+97339209304"
     */
    public function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Strip parenthetical extensions: (ext 118), (ext 201, 202)
        $val = preg_replace('/\(\s*ext[^)]*\)/i', '', $raw);

        // Strip trailing extensions: " Ext. 7438", "x 26", "- 26" (1-4 digits at end after dash/space)
        $val = preg_replace('/\s+(?:ext\.?|x)\s*[\d,\s]+$/i', '', $val);
        $val = preg_replace('/\s*[-–]\s*\d{1,4}\s*$/', '', $val);

        // Extract only digits
        $digits = preg_replace('/[^\d]/', '', $val);

        if ($digits === '') {
            return '';
        }

        // 8-digit Bahrain local number → prepend 973
        if (strlen($digits) === 8) {
            return '+973' . $digits;
        }

        // Already has country code as prefix
        return '+' . $digits;
    }

    private function parseCreditDays(string $val): ?int
    {
        $digits = preg_replace('/[^\d]/', '', $val);
        return $digits !== '' ? (int) $digits : null;
    }

    private function parseBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['yes', 'true', '1', 'active', 'y']);
    }

    private function str(string $val): ?string
    {
        $v = trim($val);
        return $v !== '' ? $v : null;
    }
}
