<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateItemTemplate extends Command
{
    protected $signature   = 'items:template {--output= : Save path}';
    protected $description = 'Generate a clean Excel template for importing inventory items';

    private array $columns = [
        ['item_name *',          'SUPERBOND F5 White 25kg',  36],
        ['unit_of_measure',      'BAG',                      16],
        ['category',             'finished_good',            20],
        ['cost_price',           '0.00',                     14],
        ['minimum_stock_level',  '0',                        20],
        ['description',          '',                         36],
        ['is_active',            'yes',                      12],
    ];

    public function handle(): int
    {
        $outputPath = $this->option('output') ?? storage_path('app/items_template.xlsx');

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Items');

        // Headers
        foreach ($this->columns as $i => [$label]) {
            $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . '1')->setValue($label);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($this->columns));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0f172a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Example rows
        $examples = [
            ['SUPERBOND F5 White 25kg',  'BAG', 'finished_good', '0.00', '0', '',  'yes'],
            ['SUPERBOND F5 Grey 25kg',   'BAG', 'finished_good', '0.00', '0', '',  'yes'],
            ['Pentaproof 20 P',          'KG',  'raw_material',  '1.09', '0', '',  'yes'],
            ['JOLES YELLOW OCHRE',       'KG',  'raw_material',  '1.32', '0', '',  'yes'],
            ['Silica Sand',              'KG',  'raw_material',  '0.01', '0', '',  'yes'],
        ];

        foreach ($examples as $rowIdx => $values) {
            $excelRow = $rowIdx + 2;
            foreach ($values as $colIdx => $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colIdx + 1) . $excelRow)->setValue($value);
            }
        }

        $lastDataRow = count($examples) + 1;
        $sheet->getStyle("A2:{$lastCol}{$lastDataRow}")->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Notes
        $notesRow = $lastDataRow + 2;
        $sheet->getCell("A{$notesRow}")->setValue(
            '* Required. category must be: finished_good, raw_material, or wip. Items with "Not in Use" in the name are imported as inactive. Delete example rows before importing.'
        );
        $sheet->mergeCells("A{$notesRow}:{$lastCol}{$notesRow}");
        $sheet->getStyle("A{$notesRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF9C3']],
        ]);

        // Column widths
        foreach ($this->columns as $i => [, , $width]) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($width);
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        (new Xlsx($spreadsheet))->save($outputPath);

        $this->info("Template saved to: {$outputPath}");
        $this->line("Usage: php artisan items:import \"<path-to-file.xlsx>\"");

        return Command::SUCCESS;
    }
}
