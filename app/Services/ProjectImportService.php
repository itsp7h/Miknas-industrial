<?php

namespace App\Services;

use App\Models\Settings\Company;
use App\Models\Settings\Department;
use App\Models\Settings\ProjectSetting;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProjectImportService
{
    private array $stats = [
        'companies_created'   => 0,
        'projects_created'    => 0,
        'departments_created' => 0,
        'skipped'             => 0,
    ];

    /** @var array<string,Company> */
    private array $companyCache = [];

    public function import(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);

        $projectSheet = $this->findSheet($spreadsheet, ['projects', 'project']);
        $deptSheet    = $this->findSheet($spreadsheet, ['departments', 'department', 'depts', 'dept']);

        if ($projectSheet) {
            $this->importProjects($projectSheet);
        }
        if ($deptSheet) {
            $this->importDepartments($deptSheet);
        }

        // Single-sheet file with no named tabs → treat as projects
        if (!$projectSheet && !$deptSheet) {
            $this->importProjects($spreadsheet->getActiveSheet());
        }

        return $this->stats;
    }

    private function findSheet($spreadsheet, array $names): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        foreach ($spreadsheet->getSheetNames() as $i => $sheetName) {
            if (in_array(strtolower(trim($sheetName)), $names)) {
                return $spreadsheet->getSheet($i);
            }
        }
        return null;
    }

    private function importProjects(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $rows    = $sheet->toArray(null, true, true, false);
        $headers = $this->normalizeHeaders((array) array_shift($rows));

        $coIdx   = $this->findCol($headers, ['company', 'company name', 'company name', 'companyname']);
        $projIdx = $this->findCol($headers, ['project', 'project name', 'project name', 'projectname']);

        if ($coIdx === null || $projIdx === null) {
            return;
        }

        foreach ($rows as $row) {
            $coName   = $this->str($row[$coIdx]   ?? null);
            $projName = $this->str($row[$projIdx] ?? null);

            if (!$coName || !$projName) {
                $this->stats['skipped']++;
                continue;
            }

            $company  = $this->findOrCreateCompany($coName);
            $existing = ProjectSetting::whereRaw('LOWER(name) = ?', [strtolower($projName)])
                ->where('company_id', $company->id)->first();

            if ($existing) {
                $this->stats['skipped']++;
                continue;
            }

            ProjectSetting::create(['name' => $projName, 'company_id' => $company->id, 'is_active' => true]);
            $this->stats['projects_created']++;
        }
    }

    private function importDepartments(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $rows    = $sheet->toArray(null, true, true, false);
        $headers = $this->normalizeHeaders((array) array_shift($rows));

        $coIdx   = $this->findCol($headers, ['company', 'company name', 'companyname']);
        $deptIdx = $this->findCol($headers, ['department', 'department name', 'departmentname', 'dept', 'dept name']);

        if ($coIdx === null || $deptIdx === null) {
            return;
        }

        foreach ($rows as $row) {
            $coName   = $this->str($row[$coIdx]   ?? null);
            $deptName = $this->str($row[$deptIdx] ?? null);

            if (!$coName || !$deptName) {
                $this->stats['skipped']++;
                continue;
            }

            $company  = $this->findOrCreateCompany($coName);
            $existing = Department::whereRaw('LOWER(name) = ?', [strtolower($deptName)])
                ->where('company_id', $company->id)->first();

            if ($existing) {
                $this->stats['skipped']++;
                continue;
            }

            Department::create(['name' => $deptName, 'company_id' => $company->id, 'is_active' => true]);
            $this->stats['departments_created']++;
        }
    }

    private function findOrCreateCompany(string $name): Company
    {
        $key = strtolower($name);
        if (isset($this->companyCache[$key])) {
            return $this->companyCache[$key];
        }

        $company = Company::whereRaw('LOWER(name) = ?', [$key])->first();
        if (!$company) {
            $company = Company::create(['name' => $name, 'is_active' => true]);
            $this->stats['companies_created']++;
        }

        $this->companyCache[$key] = $company;
        return $company;
    }

    private function normalizeHeaders(array $row): array
    {
        return array_map(
            fn ($h) => strtolower(trim(str_replace(['*', '_', '-'], ' ', (string) ($h ?? '')))),
            $row
        );
    }

    private function findCol(array $headers, array $aliases): ?int
    {
        foreach ($headers as $i => $h) {
            if (in_array($h, $aliases)) {
                return $i;
            }
        }
        return null;
    }

    private function str(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        return $v === '' ? null : $v;
    }
}
