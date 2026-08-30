<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\UrgencyLevel;
use Illuminate\Http\Request;

class UrgencyLevelController extends Controller
{
    public function index()
    {
        $urgencyLevels = UrgencyLevel::orderBy('sort_order')->get();

        return view('settings.urgency-levels.index', compact('urgencyLevels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:100|unique:settings_urgency_levels,label',
            'emoji' => 'required|string|max:10',
            'color_bg' => 'required|string|max:20',
            'color_text' => 'required|string|max:20',
            'subtitle' => 'nullable|string|max:100',
            'sort_order' => 'required|integer|min:0',
        ]);

        UrgencyLevel::create([
            'label' => $request->label,
            'emoji' => $request->emoji,
            'color_bg' => $request->color_bg,
            'color_text' => $request->color_text,
            'subtitle' => $request->subtitle,
            'sort_order' => $request->sort_order,
            'show_date_picker' => $request->boolean('show_date_picker'),
            'is_active' => true,
        ]);

        return redirect()->route('settings.urgency-levels.index')->with('success', 'Urgency level added.');
    }

    public function update(Request $request, UrgencyLevel $urgencyLevel)
    {
        $request->validate([
            'label' => 'required|string|max:100|unique:settings_urgency_levels,label,'.$urgencyLevel->id,
            'emoji' => 'required|string|max:10',
            'color_bg' => 'required|string|max:20',
            'color_text' => 'required|string|max:20',
            'subtitle' => 'nullable|string|max:100',
            'sort_order' => 'required|integer|min:0',
        ]);

        $urgencyLevel->update([
            'label' => $request->label,
            'emoji' => $request->emoji,
            'color_bg' => $request->color_bg,
            'color_text' => $request->color_text,
            'subtitle' => $request->subtitle,
            'sort_order' => $request->sort_order,
            'show_date_picker' => $request->boolean('show_date_picker'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('settings.urgency-levels.index')->with('success', 'Urgency level updated.');
    }

    public function destroy(UrgencyLevel $urgencyLevel)
    {
        $urgencyLevel->delete();

        return redirect()->route('settings.urgency-levels.index')->with('success', 'Urgency level deleted.');
    }
}
