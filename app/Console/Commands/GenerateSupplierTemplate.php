<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateSupplierTemplate extends Command
{
    protected $signature = 'suppliers:template
                            {--output= : Save path (default: storage/app/suppliers_template.xlsx)}';

    protected $description = 'Generate a clean Excel template for importing suppliers';

    /** [header label, example value, width] */
    private array $columns = [
        ['Supplier ID',          'SUP-1006',                          16],
        ['Company Name *',       'SAFETY CHEMICAL TRADING W.L.L',     38],
        ['Category',             'Chemical',                           22],
        ['Contact Person',       'Ali Hassan',                         24],
        ['Primary Email',        'ali@safetychem.com',                 30],
        ['Secondary Email',      '',                                   26],
        ['Phone 1',              '+97333188311',                       18],
        ['Phone 2',              '',                                   18],
        ['WhatsApp Number',      '+97334214947',                       18],
        ['Address',              'Manama, Bahrain',                    32],
        ['Website',              'https://example.com',                28],
        ['Credit (Y/N)',         'Y',                                  14],
        ['Credit Days',          '30',                                 14],
        ['Tax Number',           'TRN100000000006',                    22],
        ['Is Active',            'Yes',                                12],
        ['Remarks / Key Details','',                                   38],
    ];

    public function handle(): int
    {
        $outputPath = $this->option('output')
            ?? storage_path('app/suppliers_template.xlsx');

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Suppliers');

        $lastCol = Coordinate::stringFromColumnIndex(count($this->columns));

        // ── Header row ────────────────────────────────────────────────────────
        foreach ($this->columns as $i => [$label]) {
            $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . '1')->setValue($label);
        }

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Example rows ──────────────────────────────────────────────────────
        $examples = [
            ['SUP-1006', 'SAFETY CHEMICAL TRADING W.L.L',      'Chemical',         'Ali Hassan',     'ali@safetychem.com',       '', '+973 3318 8311', '', '+973 3421 4947', 'Manama, Bahrain',   '',                          'Y', '30', 'TRN100000000006', 'Yes', ''],
            ['SUP-1007', 'The prosperity trading & Cont SPC',  'Chemical',         'Sara Al-Ali',    'sara@prosperity.bh',       '', '+973 17730001',  '', '',                'Riffa, Bahrain',    '',                          '',  '',   'TRN100000000007', 'Yes', ''],
            ['SUP-1008', 'Al Eradah chemicals',                 'Chemical',         'Mohammed Naser', 'info@aleradah.com',         '', '+973 1741 3720', '', '',                'Hamad Town, Bahrain','',                         '',  '',   '',                'Yes', ''],
        ];

        foreach ($examples as $rowIdx => $values) {
            $excelRow = $rowIdx + 2;
            foreach ($values as $colIdx => $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colIdx + 1) . $excelRow)->setValue($value);
            }
        }

        $lastDataRow = count($examples) + 1;
        $sheet->getStyle("A2:{$lastCol}{$lastDataRow}")->applyFromArray([
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B2:B{$lastDataRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
        ]);
        for ($r = 2; $r <= $lastDataRow; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(18);
        }

        // ── Notes row ─────────────────────────────────────────────────────────
        $notesRow = $lastDataRow + 2;
        $sheet->getCell("A{$notesRow}")->setValue(
            '* Required. Delete example rows before importing. Duplicate Company Names are skipped. ' .
            'Supplier ID is optional — leave blank and one will be auto-assigned. ' .
            'Category, Secondary Email, Phone 2, WhatsApp, Website, Credit, Remarks are informational only.'
        );
        $sheet->mergeCells("A{$notesRow}:{$lastCol}{$notesRow}");
        $sheet->getStyle("A{$notesRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '6B7280'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF9C3']],
        ]);
        $sheet->getRowDimension($notesRow)->setRowHeight(30);
        $sheet->getStyle("A{$notesRow}")->getAlignment()->setWrapText(true);

        // ── Column widths ─────────────────────────────────────────────────────
        foreach ($this->columns as $i => [, , $width]) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($width);
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        (new Xlsx($spreadsheet))->save($outputPath);

        $this->info("Template saved to: {$outputPath}");

        return Command::SUCCESS;
    }
}
