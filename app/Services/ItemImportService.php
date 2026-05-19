<?php

namespace App\Services;

use App\Models\Item;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ItemImportService
{
    /** Section-header text → category value */
    private array $sectionMap = [
        'material inventory for sale' => 'finished_good',
        'chemical materials'          => 'raw_material',
        'natural pigments'            => 'raw_material',
        'raw materials'               => 'raw_material',
        'forkoll bags'                => 'raw_material',
        'others'                      => 'raw_material',
    ];

    /** Rows whose column-A value should be skipped entirely */
    private array $skipWords = ['sub total', 'subtotal', 'total', 'si. no', 'si no', 'sl. no', 'sl no', 's.no'];

    /** Col-B values that indicate a header row, not data */
    private array $headerWords = ['description', 'item name', 'item_name', 'material'];

    public function import(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $format      = $this->detectFormat($spreadsheet);

        $rows = $format === 'forkoll'
            ? $this->extractFromForkoll($spreadsheet)
            : $this->extractFromTemplate($spreadsheet);

        $imported = 0;
        $skipped  = 0;
        $codeSeq  = (Item::max('id') ?? 0) + 1;

        foreach ($rows as $data) {
            $name = trim($data['item_name'] ?? '');
            if (empty($name)) {
                continue;
            }

            $exists = Item::whereRaw('LOWER(item_name) = ?', [strtolower($name)])->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $active   = !str_contains(strtolower($name), 'not in use');
            $itemCode = $data['item_code'] ?? ('ITEM-' . str_pad($codeSeq, 5, '0', STR_PAD_LEFT));
            $codeSeq++;

            Item::create([
                'item_code'           => $itemCode,
                'item_name'           => $name,
                'category'            => $data['category']        ?? 'finished_good',
                'unit_of_measure'     => $data['unit_of_measure'] ?? 'EA',
                'cost_price'          => $data['cost_price']      ?? 0,
                'minimum_stock_level' => $data['minimum_stock_level'] ?? 0,
                'description'         => $data['description']     ?? null,
                'is_active'           => $data['is_active']       ?? $active,
            ]);

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'format' => $format];
    }

    private function detectFormat(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): string
    {
        $sheet = $spreadsheet->getActiveSheet();
        // Forkoll sheets have "MATERIAL INVENTORY FOR SALE" somewhere in col A around row 3
        for ($r = 1; $r <= 5; $r++) {
            $val = strtolower(trim((string) $sheet->getCell('A' . $r)->getValue()));
            if (str_contains($val, 'material inventory for sale') || str_contains($val, 'forkoll')) {
                return 'forkoll';
            }
        }
        return 'template';
    }

    private function extractFromForkoll(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        $sheet   = $spreadsheet->getActiveSheet();
        $maxRow  = $sheet->getHighestRow();
        $items   = [];
        $category = 'finished_good';

        for ($r = 1; $r <= $maxRow; $r++) {
            $colA = trim((string) $sheet->getCell('A' . $r)->getValue());
            $colB = trim((string) $sheet->getCell('B' . $r)->getValue());
            $colC = trim((string) $sheet->getCell('C' . $r)->getValue());
            $colD = trim((string) $sheet->getCell('D' . $r)->getValue());

            // Detect section header (text in col A, empty col B)
            if ($colA !== '' && $colB === '') {
                $lower = strtolower($colA);

                // Skip SUB TOTAL rows
                foreach ($this->skipWords as $skip) {
                    if (str_contains($lower, $skip)) {
                        continue 2;
                    }
                }

                // Update current category from section map
                foreach ($this->sectionMap as $keyword => $cat) {
                    if (str_contains($lower, $keyword)) {
                        $category = $cat;
                        break;
                    }
                }
                continue;
            }

            // Skip rows with no description or a formula
            if ($colB === '' || str_starts_with($colB, '=')) {
                continue;
            }

            // Skip header rows (col B says "DESCRIPTION", "Item Name", etc.)
            if (in_array(strtolower($colB), $this->headerWords)) {
                continue;
            }

            $price = is_numeric($colD) ? (float) $colD : 0.0;

            $items[] = [
                'item_name'       => $colB,
                'unit_of_measure' => $colC ?: 'EA',
                'category'        => $category,
                'cost_price'      => $price,
                'is_active'       => !str_contains(strtolower($colB), 'not in use'),
            ];
        }

        return $items;
    }

    private function extractFromTemplate(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        $rows    = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_map(
            fn($h) => strtolower(trim(str_replace('*', '', (string) $h))),
            $rows[0] ?? []
        );

        $fields = ['item_name', 'unit_of_measure', 'category', 'cost_price', 'minimum_stock_level', 'description', 'is_active'];
        $map    = [];

        foreach ($fields as $field) {
            $idx = array_search($field, $headers);
            if ($idx !== false) {
                $map[$field] = $idx;
            }
        }

        if (!isset($map['item_name'])) {
            return [];
        }

        $items = [];
        foreach (array_slice($rows, 1) as $row) {
            $name = trim((string) ($row[$map['item_name']] ?? ''));
            if (empty($name) || str_starts_with($name, '*')) {
                continue;
            }

            $entry = ['item_name' => $name];
            foreach (array_diff($fields, ['item_name']) as $field) {
                if (isset($map[$field])) {
                    $val = trim((string) ($row[$map[$field]] ?? ''));
                    $entry[$field] = $val !== '' ? $val : null;
                }
            }

            if (isset($entry['cost_price'])) {
                $entry['cost_price'] = is_numeric($entry['cost_price']) ? (float) $entry['cost_price'] : 0;
            }
            if (isset($entry['minimum_stock_level'])) {
                $entry['minimum_stock_level'] = is_numeric($entry['minimum_stock_level']) ? (float) $entry['minimum_stock_level'] : 0;
            }
            if (isset($entry['is_active'])) {
                $entry['is_active'] = in_array(strtolower($entry['is_active'] ?? ''), ['yes', 'true', '1', 'active', 'y']);
            }

            $items[] = $entry;
        }

        return $items;
    }

}
