<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Company;
use App\Models\Settings\Department;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use Illuminate\Http\Request;

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
}
