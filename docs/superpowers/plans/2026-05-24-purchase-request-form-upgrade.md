# Purchase Request Form Upgrade — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add three admin-managed lookup tables (locations, projects, urgency levels), a Settings admin section in the sidebar, and upgrade the purchase request create/edit forms with searchable dropdowns and an interactive urgency popup.

**Architecture:** Three migrations + models + seeders for lookup tables. Three Settings controllers under `app/Http/Controllers/Settings/`. PurchaseRequestController passes lookup collections to create/edit views. The urgency picker is rendered as a floating popup using server-rendered JSON (URGENCY_LEVELS JS variable) and vanilla JS — no Alpine.js or Select2 needed. Dropdowns are native `<select>` elements. Strings (not IDs) are stored in existing `purchase_requests` columns for historical integrity.

**Tech Stack:** Laravel 12 / PHP 8.2, Blade, vanilla JS, inline Tailwind styles (per project convention), SQLite

---

## File Map

**Create:**
- `database/migrations/2026_05_24_000001_create_settings_locations_table.php`
- `database/migrations/2026_05_24_000002_create_settings_projects_table.php`
- `database/migrations/2026_05_24_000003_create_settings_urgency_levels_table.php`
- `database/seeders/UrgencyLevelSeeder.php`
- `app/Models/Settings/Location.php`
- `app/Models/Settings/ProjectSetting.php`
- `app/Models/Settings/UrgencyLevel.php`
- `app/Http/Controllers/Settings/LocationController.php`
- `app/Http/Controllers/Settings/ProjectSettingController.php`
- `app/Http/Controllers/Settings/UrgencyLevelController.php`
- `resources/views/settings/locations/index.blade.php`
- `resources/views/settings/projects/index.blade.php`
- `resources/views/settings/urgency-levels/index.blade.php`

**Modify:**
- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `app/Http/Controllers/Purchase/PurchaseRequestController.php`
- `resources/views/purchase/requests/create.blade.php`
- `resources/views/purchase/requests/edit.blade.php`
- `database/seeders/DatabaseSeeder.php`

---

## Task 1: Three Migrations

**Files:**
- Create: `database/migrations/2026_05_24_000001_create_settings_locations_table.php`
- Create: `database/migrations/2026_05_24_000002_create_settings_projects_table.php`
- Create: `database/migrations/2026_05_24_000003_create_settings_urgency_levels_table.php`

- [ ] **Step 1: Create the three migration files**

Run:
```bash
php artisan make:migration create_settings_locations_table
php artisan make:migration create_settings_projects_table
php artisan make:migration create_settings_urgency_levels_table
```

Expected: Three new files in `database/migrations/` with timestamps.

- [ ] **Step 2: Fill in the locations migration**

Open the `create_settings_locations_table` migration file and replace its `up()` / `down()` with:

```php
public function up(): void
{
    Schema::create('settings_locations', function (Blueprint $table) {
        $table->id();
        $table->string('name', 255);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('settings_locations');
}
```

- [ ] **Step 3: Fill in the projects migration**

Open the `create_settings_projects_table` migration file and replace its `up()` / `down()` with:

```php
public function up(): void
{
    Schema::create('settings_projects', function (Blueprint $table) {
        $table->id();
        $table->string('name', 255);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('settings_projects');
}
```

- [ ] **Step 4: Fill in the urgency levels migration**

Open the `create_settings_urgency_levels_table` migration file and replace its `up()` / `down()` with:

```php
public function up(): void
{
    Schema::create('settings_urgency_levels', function (Blueprint $table) {
        $table->id();
        $table->string('label', 100);
        $table->string('emoji', 10)->default('📋');
        $table->string('color_bg', 20)->default('#f8fafc');
        $table->string('color_text', 20)->default('#475569');
        $table->string('subtitle', 100)->nullable();
        $table->unsignedTinyInteger('sort_order')->default(99);
        $table->boolean('show_date_picker')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('settings_urgency_levels');
}
```

- [ ] **Step 5: Run migrations**

```bash
php artisan migrate
```

Expected output includes:
```
  Creating table: settings_locations
  Creating table: settings_projects
  Creating table: settings_urgency_levels
```

- [ ] **Step 6: Verify tables exist**

```bash
php artisan tinker --execute="DB::select(\"SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'settings_%'\")"
```

Expected: 3 rows — `settings_locations`, `settings_projects`, `settings_urgency_levels`.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/
git commit -m "feat: add migrations for settings_locations, settings_projects, settings_urgency_levels"
```

---

## Task 2: Three Models

**Files:**
- Create: `app/Models/Settings/Location.php`
- Create: `app/Models/Settings/ProjectSetting.php`
- Create: `app/Models/Settings/UrgencyLevel.php`

- [ ] **Step 1: Create `app/Models/Settings/Location.php`**

```php
<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'settings_locations';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 2: Create `app/Models/Settings/ProjectSetting.php`**

```php
<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class ProjectSetting extends Model
{
    protected $table = 'settings_projects';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 3: Create `app/Models/Settings/UrgencyLevel.php`**

```php
<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class UrgencyLevel extends Model
{
    protected $table = 'settings_urgency_levels';

    protected $fillable = [
        'label', 'emoji', 'color_bg', 'color_text',
        'subtitle', 'sort_order', 'show_date_picker', 'is_active',
    ];

    protected $casts = [
        'show_date_picker' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
```

- [ ] **Step 4: Verify models resolve**

```bash
php artisan tinker --execute="echo App\Models\Settings\Location::count();"
```

Expected: `0`

- [ ] **Step 5: Commit**

```bash
git add app/Models/Settings/
git commit -m "feat: add Settings models for Location, ProjectSetting, UrgencyLevel"
```

---

## Task 3: Seeder for Urgency Levels

**Files:**
- Create: `database/seeders/UrgencyLevelSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create `database/seeders/UrgencyLevelSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\Settings\UrgencyLevel;
use Illuminate\Database\Seeder;

class UrgencyLevelSeeder extends Seeder
{
    public function run(): void
    {
        if (UrgencyLevel::count() > 0) {
            return;
        }

        $levels = [
            ['label' => 'Critical', 'emoji' => '🚨', 'color_bg' => '#fee2e2', 'color_text' => '#dc2626', 'subtitle' => 'Today',      'sort_order' => 1, 'show_date_picker' => false],
            ['label' => 'Urgent',   'emoji' => '⚡', 'color_bg' => '#ffedd5', 'color_text' => '#ea580c', 'subtitle' => '1–3 days',   'sort_order' => 2, 'show_date_picker' => false],
            ['label' => 'Normal',   'emoji' => '📋', 'color_bg' => '#fef9c3', 'color_text' => '#ca8a04', 'subtitle' => 'This week',  'sort_order' => 3, 'show_date_picker' => false],
            ['label' => 'Planned',  'emoji' => '🗓️', 'color_bg' => '#f0fdf4', 'color_text' => '#16a34a', 'subtitle' => 'Pick date', 'sort_order' => 4, 'show_date_picker' => true],
        ];

        foreach ($levels as $level) {
            UrgencyLevel::create(array_merge($level, ['is_active' => true]));
        }
    }
}
```

- [ ] **Step 2: Register seeder in `database/seeders/DatabaseSeeder.php`**

Add `$this->call(UrgencyLevelSeeder::class);` inside the `run()` method, after the existing calls:

```php
public function run(): void
{
    // ... existing calls ...
    $this->call(UrgencyLevelSeeder::class);
}
```

- [ ] **Step 3: Run the seeder**

```bash
php artisan db:seed --class=UrgencyLevelSeeder
```

Expected: No errors.

- [ ] **Step 4: Verify seeded data**

```bash
php artisan tinker --execute="App\Models\Settings\UrgencyLevel::all(['label','emoji','sort_order'])->each(fn(\$l) => print(\$l->sort_order.' '.\$l->emoji.' '.\$l->label.PHP_EOL));"
```

Expected:
```
1 🚨 Critical
2 ⚡ Urgent
3 📋 Normal
4 🗓️ Planned
```

- [ ] **Step 5: Commit**

```bash
git add database/seeders/
git commit -m "feat: add UrgencyLevelSeeder with 4 default urgency levels"
```

---

## Task 4: Settings Controllers

**Files:**
- Create: `app/Http/Controllers/Settings/LocationController.php`
- Create: `app/Http/Controllers/Settings/ProjectSettingController.php`
- Create: `app/Http/Controllers/Settings/UrgencyLevelController.php`

- [ ] **Step 1: Create `app/Http/Controllers/Settings/LocationController.php`**

```php
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
```

- [ ] **Step 2: Create `app/Http/Controllers/Settings/ProjectSettingController.php`**

```php
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
```

- [ ] **Step 3: Create `app/Http/Controllers/Settings/UrgencyLevelController.php`**

```php
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
            'label'      => 'required|string|max:100|unique:settings_urgency_levels,label',
            'emoji'      => 'required|string|max:10',
            'color_bg'   => 'required|string|max:20',
            'color_text' => 'required|string|max:20',
            'subtitle'   => 'nullable|string|max:100',
            'sort_order' => 'required|integer|min:0',
        ]);

        UrgencyLevel::create([
            'label'            => $request->label,
            'emoji'            => $request->emoji,
            'color_bg'         => $request->color_bg,
            'color_text'       => $request->color_text,
            'subtitle'         => $request->subtitle,
            'sort_order'       => $request->sort_order,
            'show_date_picker' => $request->boolean('show_date_picker'),
            'is_active'        => true,
        ]);

        return redirect()->route('settings.urgency-levels.index')->with('success', 'Urgency level added.');
    }

    public function update(Request $request, UrgencyLevel $urgencyLevel)
    {
        $request->validate([
            'label'      => 'required|string|max:100|unique:settings_urgency_levels,label,' . $urgencyLevel->id,
            'emoji'      => 'required|string|max:10',
            'color_bg'   => 'required|string|max:20',
            'color_text' => 'required|string|max:20',
            'subtitle'   => 'nullable|string|max:100',
            'sort_order' => 'required|integer|min:0',
        ]);

        $urgencyLevel->update([
            'label'            => $request->label,
            'emoji'            => $request->emoji,
            'color_bg'         => $request->color_bg,
            'color_text'       => $request->color_text,
            'subtitle'         => $request->subtitle,
            'sort_order'       => $request->sort_order,
            'show_date_picker' => $request->boolean('show_date_picker'),
            'is_active'        => $request->boolean('is_active', true),
        ]);

        return redirect()->route('settings.urgency-levels.index')->with('success', 'Urgency level updated.');
    }

    public function destroy(UrgencyLevel $urgencyLevel)
    {
        $urgencyLevel->delete();
        return redirect()->route('settings.urgency-levels.index')->with('success', 'Urgency level deleted.');
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Settings/
git commit -m "feat: add Settings controllers for Location, ProjectSetting, UrgencyLevel"
```

---

## Task 5: Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add use-imports at top of `routes/web.php`**

After the existing `use App\Http\Controllers\SettingsController;` line, add:

```php
use App\Http\Controllers\Settings\LocationController;
use App\Http\Controllers\Settings\ProjectSettingController;
use App\Http\Controllers\Settings\UrgencyLevelController;
```

- [ ] **Step 2: Add routes inside the existing `role:Admin` middleware group**

Find the existing settings middleware group (lines ~119-123):
```php
Route::middleware('role:Admin')->group(function () {
    Route::get('settings/integrations', ...
    Route::post('settings/integrations/whatsapp', ...
    Route::post('settings/integrations/test-whatsapp', ...
});
```

Add the three new resource groups **inside** that same block:

```php
Route::middleware('role:Admin')->group(function () {
    Route::get('settings/integrations', [SettingsController::class, 'integrations'])->name('settings.integrations');
    Route::post('settings/integrations/whatsapp', [SettingsController::class, 'updateWhatsapp'])->name('settings.integrations.whatsapp');
    Route::post('settings/integrations/test-whatsapp', [SettingsController::class, 'testWhatsappConnection'])->name('settings.integrations.test-whatsapp');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::resource('locations', LocationController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['locations' => 'location']);

        Route::resource('projects', ProjectSettingController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['projects' => 'project']);

        Route::resource('urgency-levels', UrgencyLevelController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['urgency-levels' => 'urgencyLevel']);
    });
});
```

- [ ] **Step 3: Verify routes registered**

```bash
php artisan route:list --name=settings.locations
```

Expected output shows `settings.locations.index`, `settings.locations.store`, `settings.locations.update`, `settings.locations.destroy`.

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git commit -m "feat: register settings routes for locations, projects, urgency-levels"
```

---

## Task 6: Settings View — Locations

**Files:**
- Create: `resources/views/settings/locations/index.blade.php`

- [ ] **Step 1: Create the directory and view**

```bash
mkdir -p resources/views/settings/locations
```

Create `resources/views/settings/locations/index.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Locations')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="page-title">Locations</h1>
        <p class="page-subtitle">Manage location / site options used in purchase requests.</p>
    </div>
    <button onclick="document.getElementById('add-form').classList.toggle('hidden')"
            class="btn-primary">+ Add Location</button>
</div>

{{-- Add Form --}}
<div id="add-form" class="card card-body mb-6 hidden">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">New Location</h2>
    <form action="{{ route('settings.locations.store') }}" method="POST" class="flex items-end gap-3">
        @csrf
        <div class="flex-1">
            <label class="form-label">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="form-input" placeholder="e.g. BaFa - Hidd">
        </div>
        <button type="submit" class="btn-primary">Save</button>
        <button type="button" onclick="document.getElementById('add-form').classList.add('hidden')"
                class="btn-secondary">Cancel</button>
    </form>
    @error('name')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- List --}}
<div class="card card-body">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-gray-600 uppercase text-xs">
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-center w-24">Status</th>
                <th class="px-4 py-3 text-right w-28">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($locations as $location)
            <tr class="border-t border-gray-100 hover:bg-gray-50" id="row-{{ $location->id }}">
                <td class="px-4 py-3 font-medium text-gray-800" id="name-{{ $location->id }}">
                    {{ $location->name }}
                </td>
                <td class="px-4 py-3 text-center">
                    @if($location->is_active)
                        <span style="background:#dcfce7;color:#16a34a;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;">Active</span>
                    @else
                        <span style="background:#f1f5f9;color:#64748b;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;">Inactive</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <button onclick="openEditModal({{ $location->id }}, '{{ addslashes($location->name) }}', {{ $location->is_active ? 'true' : 'false' }})"
                            style="color:#2563eb;background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;margin-right:8px;">Edit</button>
                    <form method="POST" action="{{ route('settings.locations.destroy', $location) }}"
                          style="display:inline" onsubmit="event.preventDefault(); confirmDelete(this, 'Delete Location?', 'Remove {{ addslashes($location->name) }} from the list.')">
                        @csrf @method('DELETE')
                        <button type="submit" style="color:#dc2626;background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="px-4 py-8 text-center text-gray-400">No locations yet. Add one above.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:420px;box-shadow:0 20px 50px rgba(0,0,0,.2);">
        <div style="padding:20px 22px 0;">
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:16px;">Edit Location</h3>
            <form id="edit-form" method="POST" action="">
                @csrf @method('PATCH')
                <div class="mb-4">
                    <label class="form-label">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="edit-name" required class="form-input">
                </div>
                <div class="mb-4 flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="edit-active" value="1" style="width:16px;height:16px;">
                    <label for="edit-active" class="form-label mb-0">Active</label>
                </div>
        </div>
        <div style="padding:16px 22px 20px;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" onclick="closeEditModal()"
                    style="padding:9px 18px;border-radius:9px;border:1.5px solid #e2e8f0;background:#fff;font-size:13px;font-weight:600;color:#475569;cursor:pointer;">Cancel</button>
            <button type="submit"
                    style="padding:9px 18px;border-radius:9px;border:none;background:#2563eb;font-size:13px;font-weight:600;color:#fff;cursor:pointer;">Save</button>
        </div>
            </form>
    </div>
</div>

<script>
function openEditModal(id, name, isActive) {
    document.getElementById('edit-form').action = '/settings/locations/' + id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-active').checked = isActive;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
@endsection
```

- [ ] **Step 2: Test the page loads**

Visit `http://localhost:8000/settings/locations` (logged in as Admin).
Expected: Empty table with "Add Location" button.

- [ ] **Step 3: Test add a location**

Click "Add Location", fill in "BaFa - Hidd", click Save.
Expected: Row appears, success toast shows.

- [ ] **Step 4: Commit**

```bash
git add resources/views/settings/locations/
git commit -m "feat: add Settings Locations management page"
```

---

## Task 7: Settings View — Projects

**Files:**
- Create: `resources/views/settings/projects/index.blade.php`

- [ ] **Step 1: Create the directory and view**

```bash
mkdir -p resources/views/settings/projects
```

Create `resources/views/settings/projects/index.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="page-title">Projects</h1>
        <p class="page-subtitle">Manage project / site names used in purchase requests.</p>
    </div>
    <button onclick="document.getElementById('add-form').classList.toggle('hidden')"
            class="btn-primary">+ Add Project</button>
</div>

{{-- Add Form --}}
<div id="add-form" class="card card-body mb-6 hidden">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">New Project</h2>
    <form action="{{ route('settings.projects.store') }}" method="POST" class="flex items-end gap-3">
        @csrf
        <div class="flex-1">
            <label class="form-label">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="form-input" placeholder="e.g. Steel Tech Co WLL">
        </div>
        <button type="submit" class="btn-primary">Save</button>
        <button type="button" onclick="document.getElementById('add-form').classList.add('hidden')"
                class="btn-secondary">Cancel</button>
    </form>
    @error('name')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- List --}}
<div class="card card-body">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-gray-600 uppercase text-xs">
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-center w-24">Status</th>
                <th class="px-4 py-3 text-right w-28">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($projects as $project)
            <tr class="border-t border-gray-100 hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-800">{{ $project->name }}</td>
                <td class="px-4 py-3 text-center">
                    @if($project->is_active)
                        <span style="background:#dcfce7;color:#16a34a;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;">Active</span>
                    @else
                        <span style="background:#f1f5f9;color:#64748b;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;">Inactive</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <button onclick="openEditModal({{ $project->id }}, '{{ addslashes($project->name) }}', {{ $project->is_active ? 'true' : 'false' }})"
                            style="color:#2563eb;background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;margin-right:8px;">Edit</button>
                    <form method="POST" action="{{ route('settings.projects.destroy', $project) }}"
                          style="display:inline" onsubmit="event.preventDefault(); confirmDelete(this, 'Delete Project?', 'Remove {{ addslashes($project->name) }} from the list.')">
                        @csrf @method('DELETE')
                        <button type="submit" style="color:#dc2626;background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="px-4 py-8 text-center text-gray-400">No projects yet. Add one above.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:420px;box-shadow:0 20px 50px rgba(0,0,0,.2);">
        <div style="padding:20px 22px 0;">
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:16px;">Edit Project</h3>
            <form id="edit-form" method="POST" action="">
                @csrf @method('PATCH')
                <div class="mb-4">
                    <label class="form-label">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="edit-name" required class="form-input">
                </div>
                <div class="mb-4 flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="edit-active" value="1" style="width:16px;height:16px;">
                    <label for="edit-active" class="form-label mb-0">Active</label>
                </div>
        </div>
        <div style="padding:16px 22px 20px;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" onclick="closeEditModal()"
                    style="padding:9px 18px;border-radius:9px;border:1.5px solid #e2e8f0;background:#fff;font-size:13px;font-weight:600;color:#475569;cursor:pointer;">Cancel</button>
            <button type="submit"
                    style="padding:9px 18px;border-radius:9px;border:none;background:#2563eb;font-size:13px;font-weight:600;color:#fff;cursor:pointer;">Save</button>
        </div>
            </form>
    </div>
</div>

<script>
function openEditModal(id, name, isActive) {
    document.getElementById('edit-form').action = '/settings/projects/' + id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-active').checked = isActive;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
@endsection
```

- [ ] **Step 2: Test the page loads**

Visit `http://localhost:8000/settings/projects` (logged in as Admin).
Expected: Empty table with "Add Project" button.

- [ ] **Step 3: Commit**

```bash
git add resources/views/settings/projects/
git commit -m "feat: add Settings Projects management page"
```

---

## Task 8: Settings View — Urgency Levels

**Files:**
- Create: `resources/views/settings/urgency-levels/index.blade.php`

- [ ] **Step 1: Create the directory and view**

```bash
mkdir -p "resources/views/settings/urgency-levels"
```

Create `resources/views/settings/urgency-levels/index.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Urgency Levels')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="page-title">Urgency Levels</h1>
        <p class="page-subtitle">Configure the urgency options shown in purchase request forms.</p>
    </div>
    <button onclick="document.getElementById('add-form').classList.toggle('hidden')"
            class="btn-primary">+ Add Level</button>
</div>

{{-- Live Preview --}}
<div class="card card-body mb-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Popup Preview</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;max-width:480px;">
        @foreach($urgencyLevels->where('is_active', true) as $level)
        <div style="border:1.5px solid {{ $level->color_text }}33;border-radius:10px;padding:10px;text-align:center;background:{{ $level->color_bg }};">
            <div style="font-size:22px;margin-bottom:2px;">{{ $level->emoji }}</div>
            <div style="font-size:11px;font-weight:700;color:{{ $level->color_text }};">{{ $level->label }}</div>
            <div style="font-size:10px;color:#94a3b8;">{{ $level->subtitle }}</div>
        </div>
        @endforeach
    </div>
</div>

{{-- Add Form --}}
<div id="add-form" class="card card-body mb-6 hidden">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">New Urgency Level</h2>
    <form action="{{ route('settings.urgency-levels.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <label class="form-label">Label <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label') }}" required class="form-input" placeholder="e.g. Urgent">
            </div>
            <div>
                <label class="form-label">Emoji <span class="text-red-500">*</span></label>
                <input type="text" name="emoji" value="{{ old('emoji') }}" required class="form-input" placeholder="⚡">
            </div>
            <div>
                <label class="form-label">Subtitle</label>
                <input type="text" name="subtitle" value="{{ old('subtitle') }}" class="form-input" placeholder="1–3 days">
            </div>
            <div>
                <label class="form-label">BG Color <span class="text-red-500">*</span></label>
                <input type="text" name="color_bg" value="{{ old('color_bg', '#f8fafc') }}" required class="form-input" placeholder="#ffedd5">
            </div>
            <div>
                <label class="form-label">Text Color <span class="text-red-500">*</span></label>
                <input type="text" name="color_text" value="{{ old('color_text', '#475569') }}" required class="form-input" placeholder="#ea580c">
            </div>
            <div>
                <label class="form-label">Sort Order <span class="text-red-500">*</span></label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 99) }}" required min="0" class="form-input">
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <input type="checkbox" name="show_date_picker" value="1" id="add-dp" style="width:16px;height:16px;" {{ old('show_date_picker') ? 'checked' : '' }}>
            <label for="add-dp" class="form-label mb-0">Show date picker when selected</label>
        </div>
        <div class="mt-4 flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <button type="button" onclick="document.getElementById('add-form').classList.add('hidden')" class="btn-secondary">Cancel</button>
        </div>
    </form>
</div>

{{-- List --}}
<div class="card card-body">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-gray-600 uppercase text-xs">
                <th class="px-4 py-3 text-left w-10">#</th>
                <th class="px-4 py-3 text-left">Label</th>
                <th class="px-4 py-3 text-left">Subtitle</th>
                <th class="px-4 py-3 text-left">Colors</th>
                <th class="px-4 py-3 text-center w-24">Status</th>
                <th class="px-4 py-3 text-right w-28">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($urgencyLevels as $level)
            <tr class="border-t border-gray-100 hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-400 text-xs">{{ $level->sort_order }}</td>
                <td class="px-4 py-3">
                    <span style="background:{{ $level->color_bg }};color:{{ $level->color_text }};border-radius:20px;padding:3px 10px;font-weight:600;font-size:12px;">
                        {{ $level->emoji }} {{ $level->label }}
                    </span>
                    @if($level->show_date_picker)
                        <span style="font-size:10px;color:#94a3b8;margin-left:4px;">📅 date picker</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ $level->subtitle }}</td>
                <td class="px-4 py-3">
                    <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:{{ $level->color_bg }};border:1px solid #e2e8f0;vertical-align:middle;"></span>
                    <span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:{{ $level->color_text }};border:1px solid #e2e8f0;vertical-align:middle;margin-left:2px;"></span>
                </td>
                <td class="px-4 py-3 text-center">
                    @if($level->is_active)
                        <span style="background:#dcfce7;color:#16a34a;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;">Active</span>
                    @else
                        <span style="background:#f1f5f9;color:#64748b;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;">Inactive</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <button onclick="openEditModal({{ $level->id }}, {{ $level->toJson() }})"
                            style="color:#2563eb;background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;margin-right:8px;">Edit</button>
                    <form method="POST" action="{{ route('settings.urgency-levels.destroy', $level) }}"
                          style="display:inline" onsubmit="event.preventDefault(); confirmDelete(this, 'Delete Urgency Level?', 'Remove {{ addslashes($level->label) }} from the list.')">
                        @csrf @method('DELETE')
                        <button type="submit" style="color:#dc2626;background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No urgency levels yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:500px;box-shadow:0 20px 50px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;">
        <div style="padding:20px 22px 0;">
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:16px;">Edit Urgency Level</h3>
            <form id="edit-form" method="POST" action="">
                @csrf @method('PATCH')
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Label <span class="text-red-500">*</span></label>
                        <input type="text" name="label" id="e-label" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Emoji <span class="text-red-500">*</span></label>
                        <input type="text" name="emoji" id="e-emoji" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Subtitle</label>
                        <input type="text" name="subtitle" id="e-subtitle" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="e-sort" min="0" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">BG Color <span class="text-red-500">*</span></label>
                        <input type="text" name="color_bg" id="e-bg" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Text Color <span class="text-red-500">*</span></label>
                        <input type="text" name="color_text" id="e-text" required class="form-input">
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <input type="checkbox" name="show_date_picker" id="e-dp" value="1" style="width:16px;height:16px;">
                    <label for="e-dp" class="form-label mb-0">Show date picker when selected</label>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="e-active" value="1" style="width:16px;height:16px;">
                    <label for="e-active" class="form-label mb-0">Active</label>
                </div>
        </div>
        <div style="padding:16px 22px 20px;display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" onclick="closeEditModal()"
                    style="padding:9px 18px;border-radius:9px;border:1.5px solid #e2e8f0;background:#fff;font-size:13px;font-weight:600;color:#475569;cursor:pointer;">Cancel</button>
            <button type="submit"
                    style="padding:9px 18px;border-radius:9px;border:none;background:#2563eb;font-size:13px;font-weight:600;color:#fff;cursor:pointer;">Save</button>
        </div>
            </form>
    </div>
</div>

<script>
function openEditModal(id, level) {
    document.getElementById('edit-form').action = '/settings/urgency-levels/' + id;
    document.getElementById('e-label').value   = level.label;
    document.getElementById('e-emoji').value   = level.emoji;
    document.getElementById('e-subtitle').value = level.subtitle || '';
    document.getElementById('e-sort').value    = level.sort_order;
    document.getElementById('e-bg').value      = level.color_bg;
    document.getElementById('e-text').value    = level.color_text;
    document.getElementById('e-dp').checked    = !!level.show_date_picker;
    document.getElementById('e-active').checked = !!level.is_active;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
@endsection
```

- [ ] **Step 2: Test the page loads**

Visit `http://localhost:8000/settings/urgency-levels` (logged in as Admin).
Expected: Live preview showing 4 urgency cards, table listing all 4 levels.

- [ ] **Step 3: Commit**

```bash
git add resources/views/settings/urgency-levels/
git commit -m "feat: add Settings Urgency Levels management page"
```

---

## Task 9: Sidebar Links

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Add Locations, Projects, and Urgency Levels links under the System section**

In `resources/views/layouts/app.blade.php`, find the existing "Integrations" sidebar link (around line 178). After the Integrations `<a>` tag and **before** `@endrole`, add:

```blade
<a href="{{ route('settings.locations.index') }}" style="
    display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
    font-size:13px; text-decoration:none;
    {{ request()->routeIs('settings.locations*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
" onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
    Locations
</a>
<a href="{{ route('settings.projects.index') }}" style="
    display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
    font-size:13px; text-decoration:none;
    {{ request()->routeIs('settings.projects*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
" onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
    Projects
</a>
<a href="{{ route('settings.urgency-levels.index') }}" style="
    display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
    font-size:13px; text-decoration:none;
    {{ request()->routeIs('settings.urgency-levels*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
" onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
    Urgency Levels
</a>
```

- [ ] **Step 2: Verify in browser**

Log in as Admin, check the sidebar — should show Integrations, Locations, Projects, Urgency Levels under the System section.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: add Settings sidebar links for Locations, Projects, Urgency Levels"
```

---

## Task 10: Update PurchaseRequestController

**Files:**
- Modify: `app/Http/Controllers/Purchase/PurchaseRequestController.php`

- [ ] **Step 1: Add use-imports at the top of the controller**

After the existing `use` statements, add:

```php
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Models\Settings\UrgencyLevel;
```

- [ ] **Step 2: Update `create()` to pass lookup data**

Replace:
```php
public function create()
{
    return view('purchase.requests.create');
}
```

With:
```php
public function create()
{
    $locations     = Location::active()->orderBy('name')->pluck('name');
    $projects      = ProjectSetting::active()->orderBy('name')->pluck('name');
    $urgencyLevels = UrgencyLevel::active()->get(['id', 'label', 'emoji', 'color_bg', 'color_text', 'subtitle', 'show_date_picker']);

    return view('purchase.requests.create', compact('locations', 'projects', 'urgencyLevels'));
}
```

- [ ] **Step 3: Update `edit()` to pass lookup data**

Replace:
```php
public function edit(PurchaseRequest $purchaseRequest)
{
    $purchaseRequest->load('items');

    return view('purchase.requests.edit', compact('purchaseRequest'));
}
```

With:
```php
public function edit(PurchaseRequest $purchaseRequest)
{
    $purchaseRequest->load('items');

    $locations     = Location::active()->orderBy('name')->pluck('name');
    $projects      = ProjectSetting::active()->orderBy('name')->pluck('name');
    $urgencyLevels = UrgencyLevel::active()->get(['id', 'label', 'emoji', 'color_bg', 'color_text', 'subtitle', 'show_date_picker']);

    return view('purchase.requests.edit', compact('purchaseRequest', 'locations', 'projects', 'urgencyLevels'));
}
```

- [ ] **Step 4: Verify no errors**

```bash
php artisan route:list --name=purchase.requests.create
```

Expected: Route listed with no errors.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Purchase/PurchaseRequestController.php
git commit -m "feat: pass locations, projects, urgencyLevels to purchase request create/edit"
```

---

## Task 11: Update `create.blade.php`

**Files:**
- Modify: `resources/views/purchase/requests/create.blade.php`

- [ ] **Step 1: Replace the entire file content**

Replace the full content of `resources/views/purchase/requests/create.blade.php` with:

```blade
@extends('layouts.app')

@section('title', 'New Material Purchase Request')

@section('content')
<div class="mb-6">
    <h1 class="page-title">New Material Purchase Request</h1>
    <p class="page-subtitle"><a href="{{ route('purchase.requests.index') }}" class="text-blue-600 hover:underline">Purchase Requests</a> / New</p>
</div>

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('purchase.requests.store') }}" method="POST" id="mpr-form">
    @csrf

    {{-- Header Details --}}
    <div class="card card-body mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-4">Project / Department Details</h2>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

            <div>
                <label class="form-label">Date <span class="text-red-500">*</span></label>
                <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required class="form-input">
            </div>

            {{-- Project / Site Name — dropdown --}}
            <div>
                <label class="form-label">Project / Site Name <span class="text-red-500">*</span></label>
                <select name="project_name" required class="form-input">
                    <option value="">Select project…</option>
                    @foreach($projects as $p)
                        <option value="{{ $p }}" {{ old('project_name') === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Requested By <span class="text-red-500">*</span></label>
                <input type="text" name="requested_by_name" value="{{ old('requested_by_name') }}" required class="form-input" placeholder="Person's name">
            </div>

            {{-- Required Date / Urgency — popup picker --}}
            <div style="position:relative;">
                <label class="form-label">Required Date / Urgency</label>
                <div id="urgency-trigger" onclick="toggleUrgencyPopup()" style="
                    border:1px solid #d1d5db;border-radius:8px;padding:9px 12px;
                    background:#fff;cursor:pointer;display:flex;align-items:center;
                    justify-content:space-between;min-height:42px;
                ">
                    <span id="urgency-display" style="font-size:14px;color:#9ca3af;">Select urgency…</span>
                    <svg width="14" height="14" fill="none" stroke="#9ca3af" viewBox="0 0 24 24" style="flex-shrink:0;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
                <input type="hidden" name="required_date_text" id="required_date_text" value="{{ old('required_date_text') }}">

                {{-- Urgency Popup --}}
                <div id="urgency-popup" style="
                    display:none;position:absolute;top:calc(100% + 6px);left:0;z-index:200;
                    background:#fff;border-radius:16px;border:1.5px solid #e2e8f0;
                    box-shadow:0 12px 32px rgba(0,0,0,.14);padding:16px;width:300px;
                ">
                    <div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;">
                        How soon is this needed?
                    </div>
                    <div id="urgency-cards" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"></div>
                    <div id="date-section" style="display:none;margin-top:12px;border-top:1px solid #f1f5f9;padding-top:12px;">
                        <label style="font-size:11px;color:#6b7280;font-weight:500;display:block;margin-bottom:4px;">Specify the required date:</label>
                        <input type="date" id="planned-date" style="width:100%;border:1.5px solid #3b82f6;border-radius:8px;padding:7px 10px;font-size:13px;" min="{{ date('Y-m-d') }}">
                    </div>
                </div>
            </div>

            {{-- Location / Site — dropdown --}}
            <div>
                <label class="form-label">Location / Site</label>
                <select name="location" class="form-input">
                    <option value="">Select location…</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc }}" {{ old('location') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Department</label>
                <input type="text" name="department" value="{{ old('department') }}" class="form-input" placeholder="e.g. Operations, Production">
            </div>

        </div>
    </div>

    {{-- Material Items --}}
    <div class="card card-body mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-700">Material Details</h2>
            <button type="button" id="add-row" class="btn-primary btn-sm">+ Add Item</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse" id="items-table">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <th class="border border-gray-300 px-3 py-2 text-left w-10">S.No</th>
                        <th class="border border-gray-300 px-3 py-2 text-left">Description of Material <span class="text-red-500">*</span></th>
                        <th class="border border-gray-300 px-3 py-2 text-left w-24">Unit</th>
                        <th class="border border-gray-300 px-3 py-2 text-left w-28">Qty Required <span class="text-red-500">*</span></th>
                        <th class="border border-gray-300 px-3 py-2 text-left w-40">Purpose / Use</th>
                        <th class="border border-gray-300 px-3 py-2 text-left w-36">Required Date</th>
                        <th class="border border-gray-300 px-2 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @php $oldItems = old('items', [[]]); @endphp
                    @foreach($oldItems as $idx => $oldItem)
                    <tr class="item-row">
                        <td class="border border-gray-300 px-3 py-2 text-center text-gray-500 row-num">{{ $idx + 1 }}</td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="text" name="items[{{ $idx }}][description]" value="{{ $oldItem['description'] ?? '' }}"
                                   class="w-full border-0 focus:ring-0 text-sm" placeholder="Material description" required>
                        </td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="text" name="items[{{ $idx }}][unit]" value="{{ $oldItem['unit'] ?? '' }}"
                                   class="w-full border-0 focus:ring-0 text-sm" placeholder="PCS, NOS, KG…">
                        </td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="number" name="items[{{ $idx }}][quantity_required]" value="{{ $oldItem['quantity_required'] ?? '' }}"
                                   min="0.01" step="0.01" class="w-full border-0 focus:ring-0 text-sm" placeholder="0" required>
                        </td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="text" name="items[{{ $idx }}][purpose_use]" value="{{ $oldItem['purpose_use'] ?? '' }}"
                                   class="w-full border-0 focus:ring-0 text-sm" placeholder="Purpose…">
                        </td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="date" name="items[{{ $idx }}][required_date]" value="{{ $oldItem['required_date'] ?? '' }}"
                                   class="w-full border-0 focus:ring-0 text-sm">
                        </td>
                        <td class="border border-gray-300 px-2 py-1 text-center">
                            <button type="button" class="remove-row text-red-500 hover:text-red-700 font-bold text-lg leading-none">×</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Remarks --}}
    <div class="card card-body mb-6">
        <label class="form-label">Remarks / Notes</label>
        <textarea name="remarks" rows="2" class="form-textarea">{{ old('remarks') }}</textarea>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary">Submit Request</button>
        <a href="{{ route('purchase.requests.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>

<script>
(function () {
    // ── Urgency Popup ────────────────────────────────────────
    var URGENCY_LEVELS = @json($urgencyLevels);
    var hiddenInput    = document.getElementById('required_date_text');
    var displayEl      = document.getElementById('urgency-display');
    var popup          = document.getElementById('urgency-popup');
    var cardsEl        = document.getElementById('urgency-cards');
    var dateSection    = document.getElementById('date-section');
    var plannedDate    = document.getElementById('planned-date');
    var activeLevelId  = null;

    function buildCards() {
        cardsEl.innerHTML = '';
        URGENCY_LEVELS.forEach(function (level) {
            var card = document.createElement('div');
            card.className = 'urg-card';
            card.setAttribute('data-id', level.id);
            card.style.cssText = 'border:1.5px solid #e2e8f0;border-radius:10px;padding:10px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;';
            card.innerHTML = '<div style="font-size:22px;margin-bottom:3px;">' + level.emoji + '</div>'
                + '<div style="font-size:11px;font-weight:700;color:' + level.color_text + ';">' + level.label + '</div>'
                + '<div style="font-size:10px;color:#94a3b8;margin-top:1px;">' + (level.subtitle || '') + '</div>';
            card.addEventListener('click', function () { pickLevel(level); });
            cardsEl.appendChild(card);
        });
        restoreSelection();
    }

    function pickLevel(level) {
        activeLevelId = level.id;
        document.querySelectorAll('.urg-card').forEach(function (c) {
            var isActive = parseInt(c.getAttribute('data-id')) === level.id;
            c.style.borderColor = isActive ? level.color_text : '#e2e8f0';
            c.style.background  = isActive ? level.color_bg   : '#fff';
        });

        if (level.show_date_picker) {
            dateSection.style.display = 'block';
            plannedDate.focus();
        } else {
            dateSection.style.display = 'none';
            commitSelection(level.label, level.emoji, level.color_bg, level.color_text);
            closePopup();
        }
    }

    function commitSelection(label, emoji, bg, textColor) {
        hiddenInput.value = label;
        displayEl.innerHTML = '<span style="background:' + bg + ';color:' + textColor
            + ';border-radius:20px;padding:3px 12px;font-size:13px;font-weight:600;">'
            + emoji + ' ' + label + '</span>';
    }

    plannedDate.addEventListener('change', function () {
        if (!this.value) return;
        var level = URGENCY_LEVELS.find(function (l) { return l.id === activeLevelId; });
        if (!level) return;
        var d = new Date(this.value + 'T00:00:00');
        var formatted = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        hiddenInput.value = level.label + ' — ' + this.value;
        displayEl.innerHTML = '<span style="background:' + level.color_bg + ';color:' + level.color_text
            + ';border-radius:20px;padding:3px 12px;font-size:13px;font-weight:600;">'
            + level.emoji + ' ' + formatted + '</span>';
        closePopup();
    });

    function restoreSelection() {
        var saved = hiddenInput.value;
        if (!saved) return;
        URGENCY_LEVELS.forEach(function (level) {
            if (saved === level.label || saved.indexOf(level.label + ' — ') === 0) {
                activeLevelId = level.id;
                var card = cardsEl.querySelector('[data-id="' + level.id + '"]');
                if (card) {
                    card.style.borderColor = level.color_text;
                    card.style.background  = level.color_bg;
                }
                if (level.show_date_picker && saved.indexOf(' — ') !== -1) {
                    var datePart = saved.split(' — ')[1];
                    plannedDate.value = datePart;
                    dateSection.style.display = 'block';
                }
                var emoji = level.emoji;
                var label = level.label;
                var displayText = saved.indexOf(' — ') !== -1
                    ? emoji + ' ' + new Date(saved.split(' — ')[1] + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                    : emoji + ' ' + label;
                displayEl.innerHTML = '<span style="background:' + level.color_bg + ';color:' + level.color_text
                    + ';border-radius:20px;padding:3px 12px;font-size:13px;font-weight:600;">'
                    + displayText + '</span>';
            }
        });
    }

    function closePopup() {
        popup.style.display = 'none';
    }

    window.toggleUrgencyPopup = function () {
        popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
    };

    document.addEventListener('click', function (e) {
        var trigger = document.getElementById('urgency-trigger');
        if (popup.style.display !== 'none' && !popup.contains(e.target) && !trigger.contains(e.target)) {
            closePopup();
        }
    });

    buildCards();

    // ── Items table ──────────────────────────────────────────
    let rowIndex = {{ count($oldItems ?? [[]]) }};

    function renumber() {
        document.querySelectorAll('#items-body .row-num').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    function newRow() {
        var idx = rowIndex++;
        var tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML =
            '<td class="border border-gray-300 px-3 py-2 text-center text-gray-500 row-num"></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="text" name="items[' + idx + '][description]" class="w-full border-0 focus:ring-0 text-sm" placeholder="Material description" required></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="text" name="items[' + idx + '][unit]" class="w-full border-0 focus:ring-0 text-sm" placeholder="PCS, NOS, KG…"></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="number" name="items[' + idx + '][quantity_required]" min="0.01" step="0.01" class="w-full border-0 focus:ring-0 text-sm" placeholder="0" required></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="text" name="items[' + idx + '][purpose_use]" class="w-full border-0 focus:ring-0 text-sm" placeholder="Purpose…"></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="date" name="items[' + idx + '][required_date]" class="w-full border-0 focus:ring-0 text-sm"></td>'
            + '<td class="border border-gray-300 px-2 py-1 text-center"><button type="button" class="remove-row text-red-500 hover:text-red-700 font-bold text-lg leading-none">×</button></td>';
        return tr;
    }

    document.getElementById('add-row').addEventListener('click', function () {
        document.getElementById('items-body').appendChild(newRow());
        renumber();
    });

    document.getElementById('items-body').addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row')) {
            var rows = document.querySelectorAll('#items-body .item-row');
            if (rows.length > 1) {
                e.target.closest('tr').remove();
                renumber();
            }
        }
    });

    renumber();
})();
</script>
@endsection
```

- [ ] **Step 2: Test form loads**

Visit `http://localhost:8000/purchase/requests/create`.
Expected: Project and Location fields show as dropdowns. Urgency field shows clickable trigger.

- [ ] **Step 3: Test urgency popup**

Click the "Select urgency…" field. Expected: Popup opens with 2×2 icon cards. Click "Critical" — popup closes, pill shows "🚨 Critical". Click field again, click "Planned" — date input appears. Pick a date — popup closes with "🗓️ 14 Jun 2026" pill.

- [ ] **Step 4: Test form submission**

Fill all required fields and submit. Expected: Redirects to index with success toast. Verify in tinker:
```bash
php artisan tinker --execute="echo App\Models\PurchaseRequest::latest()->first()->required_date_text;"
```
Expected: The urgency label you picked (e.g. "Critical" or "Planned — 2026-06-14").

- [ ] **Step 5: Commit**

```bash
git add resources/views/purchase/requests/create.blade.php
git commit -m "feat: upgrade purchase request create form with dropdowns and urgency popup"
```

---

## Task 12: Update `edit.blade.php`

**Files:**
- Modify: `resources/views/purchase/requests/edit.blade.php`

- [ ] **Step 1: Replace the Project / Site Name field**

Find:
```blade
<div>
    <label class="form-label">Project / Site Name <span class="text-red-500">*</span></label>
    <input type="text" name="project_name"
           value="{{ old('project_name', $purchaseRequest->project_name) }}"
           required class="form-input" placeholder="e.g. Steel Tech Co WLL">
</div>
```

Replace with:
```blade
<div>
    <label class="form-label">Project / Site Name <span class="text-red-500">*</span></label>
    <select name="project_name" required class="form-input">
        <option value="">Select project…</option>
        @foreach($projects as $p)
            @php $selected = old('project_name', $purchaseRequest->project_name) === $p ? 'selected' : '' @endphp
            <option value="{{ $p }}" {{ $selected }}>{{ $p }}</option>
        @endforeach
    </select>
</div>
```

- [ ] **Step 2: Replace the Required Date / Urgency field**

Find:
```blade
<div>
    <label class="form-label">Required Date / Urgency</label>
    <input type="text" name="required_date_text"
           value="{{ old('required_date_text', $purchaseRequest->required_date_text) }}"
           class="form-input" placeholder="e.g. Urgent, or 2026-06-01">
</div>
```

Replace with:
```blade
<div style="position:relative;">
    <label class="form-label">Required Date / Urgency</label>
    <div id="urgency-trigger" onclick="toggleUrgencyPopup()" style="
        border:1px solid #d1d5db;border-radius:8px;padding:9px 12px;
        background:#fff;cursor:pointer;display:flex;align-items:center;
        justify-content:space-between;min-height:42px;
    ">
        <span id="urgency-display" style="font-size:14px;color:#9ca3af;">Select urgency…</span>
        <svg width="14" height="14" fill="none" stroke="#9ca3af" viewBox="0 0 24 24" style="flex-shrink:0;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
    <input type="hidden" name="required_date_text" id="required_date_text"
           value="{{ old('required_date_text', $purchaseRequest->required_date_text) }}">

    <div id="urgency-popup" style="
        display:none;position:absolute;top:calc(100% + 6px);left:0;z-index:200;
        background:#fff;border-radius:16px;border:1.5px solid #e2e8f0;
        box-shadow:0 12px 32px rgba(0,0,0,.14);padding:16px;width:300px;
    ">
        <div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;">
            How soon is this needed?
        </div>
        <div id="urgency-cards" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"></div>
        <div id="date-section" style="display:none;margin-top:12px;border-top:1px solid #f1f5f9;padding-top:12px;">
            <label style="font-size:11px;color:#6b7280;font-weight:500;display:block;margin-bottom:4px;">Specify the required date:</label>
            <input type="date" id="planned-date" style="width:100%;border:1.5px solid #3b82f6;border-radius:8px;padding:7px 10px;font-size:13px;">
        </div>
    </div>
</div>
```

- [ ] **Step 3: Replace the Location / Site field**

Find:
```blade
<div>
    <label class="form-label">Location / Site</label>
    <input type="text" name="location"
           value="{{ old('location', $purchaseRequest->location) }}"
           class="form-input" placeholder="e.g. BaFa - Hidd">
</div>
```

Replace with:
```blade
<div>
    <label class="form-label">Location / Site</label>
    <select name="location" class="form-input">
        <option value="">Select location…</option>
        @foreach($locations as $loc)
            @php $selected = old('location', $purchaseRequest->location) === $loc ? 'selected' : '' @endphp
            <option value="{{ $loc }}" {{ $selected }}>{{ $loc }}</option>
        @endforeach
    </select>
</div>
```

- [ ] **Step 4: Add the urgency popup JS before `@endsection`**

At the very end of the file (before `@endsection`), add a `<script>` block with the exact same urgency JS from Task 11, replacing the `@json($urgencyLevels)` and the `rowIndex` initialization:

```blade
<script>
(function () {
    // ── Urgency Popup ─────────────────────────────────────────
    var URGENCY_LEVELS = @json($urgencyLevels);
    var hiddenInput    = document.getElementById('required_date_text');
    var displayEl      = document.getElementById('urgency-display');
    var popup          = document.getElementById('urgency-popup');
    var cardsEl        = document.getElementById('urgency-cards');
    var dateSection    = document.getElementById('date-section');
    var plannedDate    = document.getElementById('planned-date');
    var activeLevelId  = null;

    function buildCards() {
        cardsEl.innerHTML = '';
        URGENCY_LEVELS.forEach(function (level) {
            var card = document.createElement('div');
            card.className = 'urg-card';
            card.setAttribute('data-id', level.id);
            card.style.cssText = 'border:1.5px solid #e2e8f0;border-radius:10px;padding:10px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;';
            card.innerHTML = '<div style="font-size:22px;margin-bottom:3px;">' + level.emoji + '</div>'
                + '<div style="font-size:11px;font-weight:700;color:' + level.color_text + ';">' + level.label + '</div>'
                + '<div style="font-size:10px;color:#94a3b8;margin-top:1px;">' + (level.subtitle || '') + '</div>';
            card.addEventListener('click', function () { pickLevel(level); });
            cardsEl.appendChild(card);
        });
        restoreSelection();
    }

    function pickLevel(level) {
        activeLevelId = level.id;
        document.querySelectorAll('.urg-card').forEach(function (c) {
            var isActive = parseInt(c.getAttribute('data-id')) === level.id;
            c.style.borderColor = isActive ? level.color_text : '#e2e8f0';
            c.style.background  = isActive ? level.color_bg   : '#fff';
        });

        if (level.show_date_picker) {
            dateSection.style.display = 'block';
            plannedDate.focus();
        } else {
            dateSection.style.display = 'none';
            commitSelection(level.label, level.emoji, level.color_bg, level.color_text);
            closePopup();
        }
    }

    function commitSelection(label, emoji, bg, textColor) {
        hiddenInput.value = label;
        displayEl.innerHTML = '<span style="background:' + bg + ';color:' + textColor
            + ';border-radius:20px;padding:3px 12px;font-size:13px;font-weight:600;">'
            + emoji + ' ' + label + '</span>';
    }

    plannedDate.addEventListener('change', function () {
        if (!this.value) return;
        var level = URGENCY_LEVELS.find(function (l) { return l.id === activeLevelId; });
        if (!level) return;
        var d = new Date(this.value + 'T00:00:00');
        var formatted = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        hiddenInput.value = level.label + ' — ' + this.value;
        displayEl.innerHTML = '<span style="background:' + level.color_bg + ';color:' + level.color_text
            + ';border-radius:20px;padding:3px 12px;font-size:13px;font-weight:600;">'
            + level.emoji + ' ' + formatted + '</span>';
        closePopup();
    });

    function restoreSelection() {
        var saved = hiddenInput.value;
        if (!saved) return;
        URGENCY_LEVELS.forEach(function (level) {
            if (saved === level.label || saved.indexOf(level.label + ' — ') === 0) {
                activeLevelId = level.id;
                var card = cardsEl.querySelector('[data-id="' + level.id + '"]');
                if (card) {
                    card.style.borderColor = level.color_text;
                    card.style.background  = level.color_bg;
                }
                if (level.show_date_picker && saved.indexOf(' — ') !== -1) {
                    var datePart = saved.split(' — ')[1];
                    plannedDate.value = datePart;
                    dateSection.style.display = 'block';
                }
                var emoji = level.emoji;
                var label = level.label;
                var displayText = saved.indexOf(' — ') !== -1
                    ? emoji + ' ' + new Date(saved.split(' — ')[1] + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                    : emoji + ' ' + label;
                displayEl.innerHTML = '<span style="background:' + level.color_bg + ';color:' + level.color_text
                    + ';border-radius:20px;padding:3px 12px;font-size:13px;font-weight:600;">'
                    + displayText + '</span>';
            }
        });
    }

    function closePopup() {
        popup.style.display = 'none';
    }

    window.toggleUrgencyPopup = function () {
        popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
    };

    document.addEventListener('click', function (e) {
        var trigger = document.getElementById('urgency-trigger');
        if (popup.style.display !== 'none' && !popup.contains(e.target) && !trigger.contains(e.target)) {
            closePopup();
        }
    });

    buildCards();

    // ── Items table ───────────────────────────────────────────
    let rowIndex = {{ count(old('items', $purchaseRequest->items->toArray())) }};

    function renumber() {
        document.querySelectorAll('#items-body .row-num').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    function newRow() {
        var idx = rowIndex++;
        var tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML =
            '<td class="border border-gray-300 px-3 py-2 text-center text-gray-500 row-num"></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="text" name="items[' + idx + '][description]" class="w-full border-0 focus:ring-0 text-sm" placeholder="Material description" required></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="text" name="items[' + idx + '][unit]" class="w-full border-0 focus:ring-0 text-sm" placeholder="PCS, NOS, KG…"></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="number" name="items[' + idx + '][quantity_required]" min="0.01" step="0.01" class="w-full border-0 focus:ring-0 text-sm" placeholder="0" required></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="text" name="items[' + idx + '][purpose_use]" class="w-full border-0 focus:ring-0 text-sm" placeholder="Purpose…"></td>'
            + '<td class="border border-gray-300 px-2 py-1"><input type="date" name="items[' + idx + '][required_date]" class="w-full border-0 focus:ring-0 text-sm"></td>'
            + '<td class="border border-gray-300 px-2 py-1 text-center"><button type="button" class="remove-row text-red-500 hover:text-red-700 font-bold text-lg leading-none">×</button></td>';
        return tr;
    }

    document.getElementById('add-row').addEventListener('click', function () {
        document.getElementById('items-body').appendChild(newRow());
        renumber();
    });

    document.getElementById('items-body').addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row')) {
            var rows = document.querySelectorAll('#items-body .item-row');
            if (rows.length > 1) {
                e.target.closest('tr').remove();
                renumber();
            }
        }
    });

    renumber();
})();
</script>
```

- [ ] **Step 5: Test edit form with existing urgency**

Open an existing purchase request that has a `required_date_text` value (e.g. "Critical") and click Edit.
Expected: The urgency field pre-shows the "🚨 Critical" pill. Opening the popup shows the Critical card highlighted.

- [ ] **Step 6: Commit**

```bash
git add resources/views/purchase/requests/edit.blade.php
git commit -m "feat: upgrade purchase request edit form with dropdowns and urgency popup"
```

---

## Done

At this point:
- Three lookup tables exist and are seeded with default urgency levels
- Admin can manage Locations, Projects, and Urgency Levels from Settings in the sidebar
- Purchase request create and edit forms use dropdown selects for Project and Location
- The "Required Date / Urgency" field opens a polished popup with icon cards; "Planned" shows an inline date picker
- All values are stored as strings in existing `purchase_requests` columns — no schema changes to that table
