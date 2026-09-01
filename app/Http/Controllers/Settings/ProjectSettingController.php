<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Company;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Services\ProjectImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectSettingController extends Controller
{
    public function projectsOverview()
    {
        $companies = Company::orderBy('name')->get();
        $projects = ProjectSetting::with('locations')
            ->orderBy('name')->get();

        $stats = [
            'total_projects' => $projects->count(),
            'active_projects' => $projects->where('is_active', true)->count(),
            'total_locations' => $projects->sum(fn ($p) => $p->locations->count()),
            'total_companies' => $companies->count(),
        ];

        return view('settings.projects.overview', compact('companies', 'projects', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:settings_projects,name',
            'company_id' => 'required|exists:settings_companies,id',
        ]);
        $project = ProjectSetting::create(['name' => $validated['name'], 'company_id' => $validated['company_id'], 'is_active' => true]);

        return response()->json(['project' => [
            'id' => $project->id,
            'name' => $project->name,
            'is_active' => $project->is_active,
            'company_id' => $project->company_id,
        ]]);
    }

    public function update(Request $request, ProjectSetting $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:settings_projects,name,'.$project->id,
            'company_id' => 'nullable|exists:settings_companies,id',
        ]);
        $project->update([
            'name' => $validated['name'],
            'company_id' => $validated['company_id'] ?? $project->company_id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['project' => [
            'id' => $project->id,
            'name' => $project->name,
            'is_active' => $project->is_active,
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
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $location = $project->locations()->create([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['location' => [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'is_active' => $location->is_active,
        ]]);
    }

    public function updateLocation(Request $request, ProjectSetting $project, Location $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $location->update([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['location' => [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'is_active' => $location->is_active,
        ]]);
    }

    public function destroyLocation(ProjectSetting $project, Location $location)
    {
        $location->delete();

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
            if ($stats['projects_created']) {
                $parts[] = "{$stats['projects_created']} project(s)";
            }
            if ($stats['locations_created']) {
                $parts[] = "{$stats['locations_created']} location(s)";
            }
            if ($stats['departments_created']) {
                $parts[] = "{$stats['departments_created']} department(s)";
            }
            if ($stats['companies_created']) {
                $parts[] = "{$stats['companies_created']} new company(s)";
            }

            $message = $parts
                ? 'Imported: '.implode(', ', $parts).($stats['skipped'] ? " — {$stats['skipped']} row(s) skipped" : '')
                : 'Nothing new to import'.($stats['skipped'] ? " ({$stats['skipped']} rows already exist)" : '');

            return response()->json(['success' => true, 'message' => $message, 'stats' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $path = storage_path('app/projects_template.xlsx');
        $this->buildTemplate($path);

        return response()->download($path, 'projects_template.xlsx');
    }

    private function buildTemplate(string $path): void
    {
        $spreadsheet = new Spreadsheet;

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '1e293b']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ];
        $noteStyle = [
            'font' => ['italic' => true, 'color' => ['rgb' => '64748b'], 'size' => 10],
        ];

        // ── Projects sheet ─────────────────────────────────────────────────
        $s1 = $spreadsheet->getActiveSheet()->setTitle('Projects');
        $s1->setCellValue('A1', 'Company Name')
            ->setCellValue('B1', 'Project Name');
        $s1->getStyle('A1:B1')->applyFromArray($headerStyle);

        $samples = [
            ['Miknas Industrial', 'New Warehouse'],
            ['Miknas Industrial', 'Factory Extension'],
            ['Steel tech',        'New Office Block'],
            ['Steel tech',        'Site Expansion'],
        ];
        foreach ($samples as $i => $row) {
            $s1->setCellValue('A'.($i + 2), $row[0]);
            $s1->setCellValue('B'.($i + 2), $row[1]);
        }

        $s1->setCellValue('A7', '* Company is created automatically if it does not exist. Duplicate project names are skipped.');
        $s1->getStyle('A7')->applyFromArray($noteStyle);
        $s1->mergeCells('A7:B7');

        $s1->getColumnDimension('A')->setWidth(32);
        $s1->getColumnDimension('B')->setWidth(32);

        (new Xlsx($spreadsheet))->save($path);
    }
}
