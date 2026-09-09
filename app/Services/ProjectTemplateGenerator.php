<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The projects import template. Extracted from the Blade settings controller so
 * the React page's API route and the surviving web route build the same file
 * rather than one delegating to the other.
 */
class ProjectTemplateGenerator
{
    public function write(string $path): string
    {
        $spreadsheet = new Spreadsheet;

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '1e293b']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ];
        $noteStyle = ['font' => ['italic' => true, 'color' => ['rgb' => '64748b'], 'size' => 10]];

        $sheet = $spreadsheet->getActiveSheet()->setTitle('Projects');
        $sheet->setCellValue('A1', 'Company Name')->setCellValue('B1', 'Project Name');
        $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);

        $samples = [
            ['Miknas Industrial', 'New Warehouse'],
            ['Miknas Industrial', 'Factory Extension'],
            ['Steel tech', 'New Office Block'],
            ['Steel tech', 'Site Expansion'],
        ];

        foreach ($samples as $i => $row) {
            $sheet->setCellValue('A'.($i + 2), $row[0]);
            $sheet->setCellValue('B'.($i + 2), $row[1]);
        }

        $sheet->setCellValue('A7', '* Company is created automatically if it does not exist. Duplicate project names are skipped.');
        $sheet->getStyle('A7')->applyFromArray($noteStyle);
        $sheet->mergeCells('A7:B7');
        $sheet->getColumnDimension('A')->setWidth(32);
        $sheet->getColumnDimension('B')->setWidth(32);

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
