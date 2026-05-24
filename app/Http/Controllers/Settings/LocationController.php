<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::orderBy('name')->get();
        return view('settings.locations.index', compact('locations'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255|unique:settings_locations,name']);
        Location::create(['name' => $request->name, 'is_active' => true]);
        return redirect()->route('settings.locations.index')->with('success', 'Location added.');
    }

    public function update(Request $request, Location $location)
    {
        $request->validate(['name' => 'required|string|max:255|unique:settings_locations,name,' . $location->id]);
        $location->update([
            'name'      => $request->name,
            'is_active' => $request->boolean('is_active', true),
        ]);
        return redirect()->route('settings.locations.index')->with('success', 'Location updated.');
    }

    public function destroy(Location $location)
    {
        $location->delete();
        return redirect()->route('settings.locations.index')->with('success', 'Location deleted.');
    }
}
