<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Company;
use App\Models\Settings\Department;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Services\ProjectImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectSettingController extends Controller
{
    public function index()
    {
        $companies = Company::with([
            'projects.locations' => fn ($q) => $q->orderBy('name'),
            'departments'        => fn ($q) => $q->orderBy('name'),
        ])->orderBy('name')->get();

        $allProjects = $companies->flatMap(fn ($c) => $c->projects);

        $stats = [
            'total_companies'    => $companies->count(),
            'total_projects'     => $allProjects->count(),
            'active_projects'    => $allProjects->where('is_active', true)->count(),
            'total_locations'    => $allProjects->sum(fn ($p) => $p->locations->count()),
            'total_departments'  => $companies->sum(fn ($c) => $c->departments->count()),
        ];

        return view('settings.projects.index', compact('companies', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255|unique:settings_projects,name',
            'company_id' => 'required|exists:settings_companies,id',
        ]);
        $project = ProjectSetting::create(['name' => $validated['name'], 'company_id' => $validated['company_id'], 'is_active' => true]);
        return response()->json(['project' => [
            'id'         => $project->id,
            'name'       => $project->name,
            'is_active'  => $project->is_active,
            'company_id' => $project->company_id,
        ]]);
    }

    // ── Company CRUD ──────────────────────────────────────────────────────────
    public function storeCompany(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:255|unique:settings_companies,name']);
        $company   = Company::create(['name' => $validated['name'], 'is_active' => true]);
        return response()->json(['company' => ['id' => $company->id, 'name' => $company->name, 'is_active' => $company->is_active]]);
    }

    public function updateCompany(Request $request, Company $company)
    {
        $validated = $request->validate(['name' => 'required|string|max:255|unique:settings_companies,name,' . $company->id]);
        $company->update(['name' => $validated['name'], 'is_active' => $request->boolean('is_active', true)]);
        return response()->json(['company' => ['id' => $company->id, 'name' => $company->name, 'is_active' => $company->is_active]]);
    }

    public function destroyCompany(Company $company)
    {
        $company->delete();
        return response()->json(['ok' => true]);
    }

    public function update(Request $request, ProjectSetting $project)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255|unique:settings_projects,name,' . $project->id,
            'company_id' => 'nullable|exists:settings_companies,id',
        ]);
        $project->update([
            'name'       => $validated['name'],
            'company_id' => $validated['company_id'] ?? $project->company_id,
            'is_active'  => $request->boolean('is_active', true),
        ]);
        return response()->json(['project' => [
            'id'         => $project->id,
            'name'       => $project->name,
            'is_active'  => $project->is_active,
            'company_id' => $project->company_id,
        ]]);
    }

    public function destroy(ProjectSetting $project)
    {
        $project->delete();
        return response()->json(['ok' => true]);
    }

    public function storeLocation(Request $request, ProjectSetting $project)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string|max:500',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $location = $project->locations()->create([
            'name'      => $validated['name'],
            'address'   => $validated['address'] ?? null,
            'latitude'  => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'is_active' => true,
        ]);
        return response()->json(['location' => [
            'id'        => $location->id,
            'name'      => $location->name,
            'address'   => $location->address,
            'latitude'  => $location->latitude,
            'longitude' => $location->longitude,
            'is_active' => $location->is_active,
        ]]);
    }

    public function updateLocation(Request $request, ProjectSetting $project, Location $location)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string|max:500',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $location->update([
            'name'      => $validated['name'],
            'address'   => $validated['address'] ?? null,
            'latitude'  => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);
        return response()->json(['location' => [
            'id'        => $location->id,
            'name'      => $location->name,
            'address'   => $location->address,
            'latitude'  => $location->latitude,
            'longitude' => $location->longitude,
            'is_active' => $location->is_active,
        ]]);
    }

    public function destroyLocation(ProjectSetting $project, Location $location)
    {
        $location->delete();
        return response()->json(['ok' => true]);
    }

    public function storeDepartment(Request $request, Company $company)
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $dept = $company->departments()->create(['name' => $validated['name'], 'is_active' => true]);
        return response()->json(['department' => ['id' => $dept->id, 'name' => $dept->name, 'is_active' => $dept->is_active]]);
    }

    public function updateDepartment(Request $request, Company $company, Department $department)
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $department->update(['name' => $validated['name'], 'is_active' => $request->boolean('is_active', true)]);
        return response()->json(['department' => ['id' => $department->id, 'name' => $department->name, 'is_active' => $department->is_active]]);
    }

    public function destroyDepartment(Company $company, Department $department)
    {
        $department->delete();
        return response()->json(['ok' => true]);
    }

    // ── Import ────────────────────────────────────────────────────────────────
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);

        try {
            $stats = app(ProjectImportService::class)->import(
                $request->file('file')->getPathname()
            );

            $parts = [];
            if ($stats['projects_created'])    $parts[] = "{$stats['projects_created']} project(s)";
            if ($stats['departments_created']) $parts[] = "{$stats['departments_created']} department(s)";
            if ($stats['companies_created'])   $parts[] = "{$stats['companies_created']} new company(s)";

            $message = $parts
                ? 'Imported: ' . implode(', ', $parts) . ($stats['skipped'] ? " — {$stats['skipped']} row(s) skipped" : '')
                : 'Nothing new to import' . ($stats['skipped'] ? " ({$stats['skipped']} rows already exist)" : '');

            return response()->json(['success' => true, 'message' => $message, 'stats' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = storage_path('app/projects_template.xlsx');
        $this->buildTemplate($path);
        return response()->download($path, 'projects_template.xlsx');
    }

    private function buildTemplate(string $path): void
    {
        $spreadsheet = new Spreadsheet();

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '1e293b']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ];
        $noteStyle = [
            'font' => ['italic' => true, 'color' => ['rgb' => '64748b'], 'size' => 10],
        ];

        // ── Sheet 1: Projects ──────────────────────────────────────────────
        $s1 = $spreadsheet->getActiveSheet()->setTitle('Projects');
        $s1->setCellValue('A1', 'Company Name')
           ->setCellValue('B1', 'Project Name');
        $s1->getStyle('A1:B1')->applyFromArray($headerStyle);

        // Sample rows
        $samples = [
            ['Miknas Industrial', 'New Warehouse'],
            ['Steel tech', 'Factory Extension'],
            ['Steel tech', 'New Office Block'],
        ];
        foreach ($samples as $i => $row) {
            $s1->setCellValue('A' . ($i + 2), $row[0]);
            $s1->setCellValue('B' . ($i + 2), $row[1]);
        }

        $s1->setCellValue('A6', '* Delete sample rows before importing. Company will be created if it does not exist.');
        $s1->getStyle('A6')->applyFromArray($noteStyle);
        $s1->mergeCells('A6:B6');

        $s1->getColumnDimension('A')->setWidth(32);
        $s1->getColumnDimension('B')->setWidth(32);

        // ── Sheet 2: Departments ───────────────────────────────────────────
        $s2 = new Worksheet($spreadsheet, 'Departments');
        $spreadsheet->addSheet($s2);
        $s2->setCellValue('A1', 'Company Name')
           ->setCellValue('B1', 'Department Name');
        $s2->getStyle('A1:B1')->applyFromArray($headerStyle);

        $deptSamples = [
            ['Miknas Industrial', 'Finance'],
            ['Miknas Industrial', 'Operations'],
            ['Steel tech', 'Production'],
        ];
        foreach ($deptSamples as $i => $row) {
            $s2->setCellValue('A' . ($i + 2), $row[0]);
            $s2->setCellValue('B' . ($i + 2), $row[1]);
        }

        $s2->setCellValue('A6', '* Delete sample rows before importing. Company will be created if it does not exist.');
        $s2->getStyle('A6')->applyFromArray($noteStyle);
        $s2->mergeCells('A6:B6');

        $s2->getColumnDimension('A')->setWidth(32);
        $s2->getColumnDimension('B')->setWidth(32);

        (new Xlsx($spreadsheet))->save($path);
    }
}
