<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\ProjectSetting;
use Illuminate\Http\Request;

class ProjectSettingController extends Controller
{
    public function index()
    {
        $projects = ProjectSetting::orderBy('name')->get();
        return view('settings.projects.index', compact('projects'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255|unique:settings_projects,name']);
        ProjectSetting::create(['name' => $request->name, 'is_active' => true]);
        return redirect()->route('settings.projects.index')->with('success', 'Project added.');
    }

    public function update(Request $request, ProjectSetting $project)
    {
        $request->validate(['name' => 'required|string|max:255|unique:settings_projects,name,' . $project->id]);
        $project->update([
            'name'      => $request->name,
            'is_active' => $request->boolean('is_active', true),
        ]);
        return redirect()->route('settings.projects.index')->with('success', 'Project updated.');
    }

    public function destroy(ProjectSetting $project)
    {
        $project->delete();
        return redirect()->route('settings.projects.index')->with('success', 'Project deleted.');
    }
}
