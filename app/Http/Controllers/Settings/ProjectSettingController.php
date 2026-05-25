<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use Illuminate\Http\Request;

class ProjectSettingController extends Controller
{
    public function index()
    {
        $projects = ProjectSetting::with(['locations' => function ($q) {
            $q->orderBy('name');
        }])->orderBy('name')->get();

        $stats = [
            'total_projects'   => $projects->count(),
            'active_projects'  => $projects->where('is_active', true)->count(),
            'total_locations'  => $projects->sum(fn ($p) => $p->locations->count()),
            'active_locations' => $projects->sum(fn ($p) => $p->locations->where('is_active', true)->count()),
        ];

        return view('settings.projects.index', compact('projects', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:255|unique:settings_projects,name']);
        $project   = ProjectSetting::create(['name' => $validated['name'], 'is_active' => true]);
        return response()->json(['project' => [
            'id'        => $project->id,
            'name'      => $project->name,
            'is_active' => $project->is_active,
        ]]);
    }

    public function update(Request $request, ProjectSetting $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:settings_projects,name,' . $project->id,
        ]);
        $project->update([
            'name'      => $validated['name'],
            'is_active' => $request->boolean('is_active', true),
        ]);
        return response()->json(['project' => [
            'id'        => $project->id,
            'name'      => $project->name,
            'is_active' => $project->is_active,
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
}
