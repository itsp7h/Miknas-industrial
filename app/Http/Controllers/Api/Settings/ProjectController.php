<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectSettingResource;
use App\Models\Settings\Company;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Services\ProjectImportService;
use App\Services\ProjectTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = ProjectSetting::with([
            'company',
            'locations' => fn ($query) => $query->orderBy('name'),
        ])->orderBy('name')->get();

        return ProjectSettingResource::collection($projects)->additional([
            // The company list feeds the "Company" pickers on this page, so it
            // travels with the projects rather than needing a second request.
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'meta' => [
                'total_projects' => $projects->count(),
                'active_projects' => $projects->where('is_active', true)->count(),
                'total_locations' => $projects->sum(fn ($project) => $project->locations->count()),
                'total_companies' => Company::count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $project = ProjectSetting::create($request->validate([
            'name' => 'required|string|max:255|unique:settings_projects,name',
            'company_id' => 'nullable|exists:settings_companies,id',
        ]) + ['is_active' => true]);

        return (new ProjectSettingResource($this->loaded($project)))->response()->setStatusCode(201);
    }

    public function update(Request $request, ProjectSetting $project)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:settings_projects,name,'.$project->id,
            'company_id' => 'nullable|exists:settings_companies,id',
        ]);

        $project->update([
            'name' => $data['name'],
            // A project can genuinely be moved back to no company, so an
            // explicit null has to survive rather than falling back to the
            // current value the way the Blade controller did.
            'company_id' => $data['company_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new ProjectSettingResource($this->loaded($project));
    }

    /**
     * settings_locations.project_id is `on delete set null`, so the Blade page's
     * delete left every location behind with no project — invisible everywhere
     * and impossible to reach. A location belongs to its project, so they go too.
     */
    public function destroy(ProjectSetting $project)
    {
        $id = $project->id;

        DB::transaction(function () use ($project) {
            $project->locations()->delete();
            $project->delete();
        });

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    public function storeLocation(Request $request, ProjectSetting $project)
    {
        $project->locations()->create($this->validatedLocation($request) + ['is_active' => true]);

        return (new ProjectSettingResource($this->loaded($project)))->response()->setStatusCode(201);
    }

    public function updateLocation(Request $request, ProjectSetting $project, Location $location)
    {
        $this->abortUnlessOwned($project, $location);

        $location->update(
            $this->validatedLocation($request) + ['is_active' => $request->boolean('is_active', true)]
        );

        return new ProjectSettingResource($this->loaded($project));
    }

    public function destroyLocation(ProjectSetting $project, Location $location)
    {
        $this->abortUnlessOwned($project, $location);

        $location->delete();

        return new ProjectSettingResource($this->loaded($project));
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);

        try {
            $stats = app(ProjectImportService::class)->import($request->file('file')->getPathname());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $this->importMessage($stats), 'stats' => $stats]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $path = app(ProjectTemplateGenerator::class)->write(storage_path('app/projects_template.xlsx'));

        return response()->download($path, 'projects_template.xlsx');
    }

    private function importMessage(array $stats): string
    {
        $parts = array_filter([
            $stats['projects_created'] ? "{$stats['projects_created']} project(s)" : null,
            $stats['locations_created'] ? "{$stats['locations_created']} location(s)" : null,
            $stats['departments_created'] ? "{$stats['departments_created']} department(s)" : null,
            $stats['companies_created'] ? "{$stats['companies_created']} new company(s)" : null,
        ]);

        if ($parts) {
            return 'Imported: '.implode(', ', $parts)
                .($stats['skipped'] ? " — {$stats['skipped']} row(s) skipped" : '');
        }

        return 'Nothing new to import'.($stats['skipped'] ? " ({$stats['skipped']} rows already exist)" : '');
    }

    private function validatedLocation(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        return [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ];
    }

    private function abortUnlessOwned(ProjectSetting $project, Location $location): void
    {
        abort_unless($location->project_id === $project->id, 404);
    }

    /** Location writes return the whole project so the card re-renders in one go. */
    private function loaded(ProjectSetting $project): ProjectSetting
    {
        return $project->load(['company', 'locations' => fn ($query) => $query->orderBy('name')]);
    }
}
