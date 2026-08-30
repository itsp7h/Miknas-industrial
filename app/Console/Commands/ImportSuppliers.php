<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ImportSuppliers extends Command
{
    protected $signature = 'suppliers:import
                            {file : Full path to the Excel file (.xlsx or .xls)}
                            {--dry-run : Preview what would be imported without saving}';

    protected $description = 'Import suppliers from an Excel file. Supports both the MRF comparison format and the clean suppliers template. Duplicate names are skipped automatically.';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            $this->line('Tip: Use an absolute path, e.g.  php artisan suppliers:import "C:\\path\\to\\file.xlsx"');

            return Command::FAILURE;
        }

        $this->info('Reading: '.basename($filePath));

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            $this->error('Could not open file: '.$e->getMessage());

            return Command::FAILURE;
        }

        $format = $this->detectFormat($spreadsheet);
        $this->line("Detected format: <comment>{$format}</comment>");

        $suppliers = $format === 'mrf'
            ? $this->extractFromMrf($spreadsheet)
            : $this->extractFromTemplate($spreadsheet);

        if (empty($suppliers)) {
            $this->warn('No suppliers found in file.');

            return Command::FAILURE;
        }

        $this->newLine();
        $this->line(str_pad('', 60, '-'));
        $this->line(sprintf('  %-30s %-10s %s', 'Name', 'Status', 'Notes'));
        $this->line(str_pad('', 60, '-'));

        $imported = 0;
        $skipped = 0;

        foreach ($suppliers as $data) {
            $name = trim($data['name'] ?? '');

            if (empty($name)) {
                continue;
            }

            $exists = Supplier::whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();

            if ($exists) {
                $this->line(sprintf('  %-30s <fg=yellow>SKIP</>      already in database', substr($name, 0, 30)));
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                Supplier::create([
                    'name' => $name,
                    'contact_person' => $data['contact_person'] ?? null ?: null,
                    'email' => $data['email'] ?? null ?: null,
                    'phone' => $data['phone'] ?? null ?: null,
                    'address' => $data['address'] ?? null ?: null,
                    'tax_number' => $data['tax_number'] ?? null ?: null,
                    'is_active' => $this->parseBoolean($data['is_active'] ?? 'yes'),
                ]);
            }

            $this->line(sprintf('  %-30s <fg=green>%s</>    %s',
                substr($name, 0, 30),
                $dryRun ? 'PREVIEW' : 'IMPORTED',
                isset($data['email']) && $data['email'] ? $data['email'] : ''
            ));
            $imported++;
        }

        $this->line(str_pad('', 60, '-'));
        $this->newLine();

        if ($dryRun) {
            $this->info('DRY RUN complete — nothing was saved.');
            $this->line("Would import: {$imported}  |  Would skip: {$skipped}");
        } else {
            $this->info('Import complete!');
            $this->line("Imported: <fg=green>{$imported}</>  |  Skipped (duplicates): <fg=yellow>{$skipped}</>");
        }

        return Command::SUCCESS;
    }

    /**
     * Detect whether the file is an MRF comparison sheet or a clean supplier template.
     * MRF sheets have "S.No" in cell A4.
     */
    private function detectFormat(Spreadsheet $spreadsheet): string
    {
        $sheet = $spreadsheet->getActiveSheet();
        $a4 = strtolower(trim((string) $sheet->getCell('A4')->getValue()));

        return $a4 === 's.no' ? 'mrf' : 'template';
    }

    /**
     * Extract supplier names from a Material Purchase Request comparison sheet.
     * Suppliers appear as column headers in row 4, starting from column G.
     */
    private function extractFromMrf(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = 4;
        $startCol = 7; // Column G
        $stopWords = ['comments if any', 'lowest price', 'avg price', 'recommendation'];
        $suppliers = [];

        for ($col = $startCol; $col <= 50; $col++) {
            $cellCoord = Coordinate::stringFromColumnIndex($col).$headerRow;
            $value = trim((string) $sheet->getCell($cellCoord)->getValue());

            if (empty($value)) {
                break;
            }

            if (in_array(strtolower($value), $stopWords)) {
                break;
            }

            $suppliers[] = ['name' => $value];
        }

        return $suppliers;
    }

    /**
     * Extract supplier rows from the clean import template.
     * Row 1 must be headers matching: name, contact_person, email, phone, address, tax_number, is_active
     */
    private function extractFromTemplate(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $suppliers = [];

        if (empty($rows)) {
            return [];
        }

        // Normalise header row: strip asterisks/underscores, lowercase
        $headers = array_map(
            fn ($h) => strtolower(trim(str_replace(['*', '_'], [' ', ' '], (string) $h))),
            $rows[0]
        );

        // Aliases: normalised header text → internal field name (supports both old and new formats)
        $aliases = [
            'name' => 'name',
            'company name' => 'name',
            'contact person' => 'contact_person',
            'email' => 'email',
            'primary email' => 'email',
            'phone' => 'phone',
            'phone 1' => 'phone',
            'address' => 'address',
            'tax number' => 'tax_number',
            'is active' => 'is_active',
        ];

        $map = [];
        foreach ($headers as $idx => $header) {
            if (isset($aliases[$header]) && ! isset($map[$aliases[$header]])) {
                $map[$aliases[$header]] = $idx;
            }
        }

        if (! isset($map['name'])) {
            $this->error('Template is missing a recognised name column ("name", "Company Name") in row 1.');

            return [];
        }

        foreach (array_slice($rows, 1) as $row) {
            $name = trim((string) ($row[$map['name']] ?? ''));

            // Skip empty rows and the notes/instruction row
            if (empty($name) || str_starts_with($name, '*')) {
                continue;
            }

            $entry = ['name' => $name];

            foreach (['contact_person', 'email', 'phone', 'address', 'tax_number', 'is_active'] as $field) {
                if (isset($map[$field])) {
                    $entry[$field] = trim((string) ($row[$map[$field]] ?? ''));
                }
            }

            $suppliers[] = $entry;
        }

        return $suppliers;
    }

    private function parseBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['yes', 'true', '1', 'active', 'y']);
    }
}
