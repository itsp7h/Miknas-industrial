# Purchase Pipeline Access Control Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add permission-based access control (pre-built profiles + per-person custom permission toggles) to the existing Purchase Request → GM Signature → RFQ → Quoting → Comparison/Award → LPO pipeline, plus an Admin-only User Management page to actually assign profiles and toggle permissions — the concrete deliverable the user wants to see working first.

**Architecture:** Ten granular Spatie permissions (`purchase-requests.*`) are the real unit of access; three Spatie roles (Requester, Purchase Manager, Procurement Officer) are repurposed as "profiles" — named bundles of those permissions. A `PurchaseRequestPolicy` checks permissions (via `$user->can(...)`) plus stage/ownership business rules, with `Gate::before()` making Admin bypass everything. The User Management page lets an Admin assign profiles (checkboxes) and flip individual permission toggles per person, both stored via Spatie's existing role/permission tables — no new database tables needed beyond what `spatie/laravel-permission` already created.

**Tech Stack:** Laravel 12 policies/gates, spatie/laravel-permission v6 (already installed, `HasRoles` already on `User`), existing AJAX+toast+modal conventions (no new frontend dependencies).

## Global Constraints

- No native `alert()`/`confirm()`/`prompt()` — use the existing global `confirmAction(title, body, onConfirm)` and `showToast(message, type)` helpers (already defined in `resources/views/layouts/app.blade.php`, available on every page extending that layout — do not redefine them).
- All data-entry pages use AJAX only (`fetch`, JSON request/response, no `<form>` submission, no page reload) — controllers return `response()->json(...)`.
- Search/filter UI is instant client-side (all rows loaded once, filtered in-browser, live "N of M" count) — no `?search=` params.
- Route parameters must never be named `{request}` (collides with `Illuminate\Http\Request $request` injection) — this plan's new routes use `{purchaseRequest}` and `{user}`, matching existing convention.
- Receiving (GRN) and payment pipeline stages are explicitly **out of scope** — do not add authorization to `GoodsReceiptNoteController` or `SupplierInvoiceController`/`SupplierPaymentController` in this plan.
- No department/project scoping — every permission holder sees/acts on the full shared queue for their permission level.

---

### Task 1: Permission/profile config, seeder, and PurchaseRequestFactory

**Files:**
- Create: `config/purchase_access.php`
- Create: `database/seeders/PurchaseAccessSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `tests/TestCase.php`
- Create: `database/factories/PurchaseRequestFactory.php`
- Test: `tests/Unit/PurchaseAccessSeederTest.php`

**Interfaces:**
- Produces: `config('purchase_access.permissions')` — associative array `['purchase-requests.create' => 'Create purchase requests', ...]` (10 entries, exact names below). `config('purchase_access.profiles')` — associative array `['Requester' => ['purchase-requests.create', ...], 'Purchase Manager' => [...], 'Procurement Officer' => [...]]`. `Database\Seeders\PurchaseAccessSeeder` — a `Seeder` whose `run()` creates all 10 `Permission` rows and all 3 `Role` rows (profiles), syncing each profile's permissions. `Database\Factories\PurchaseRequestFactory` — produces a valid minimal `PurchaseRequest` (fields: `request_number`, `date`, `project_name`, `requested_by_name`, `status='pending'`, `stage='draft'`, `requested_by` via `User::factory()`), used by every later task's tests.

- [ ] **Step 1: Write the failing test for the seeder**

Create `tests/Unit/PurchaseAccessSeederTest.php`:

```php
<?php

namespace Tests\Unit;

use Database\Seeders\PurchaseAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseAccessSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_all_ten_permissions(): void
    {
        (new PurchaseAccessSeeder())->run();

        $this->assertCount(10, Permission::all());
        $this->assertTrue(Permission::where('name', 'purchase-requests.create')->exists());
        $this->assertTrue(Permission::where('name', 'purchase-requests.generate-lpo')->exists());
    }

    public function test_seeds_three_profiles_with_correct_permissions(): void
    {
        (new PurchaseAccessSeeder())->run();

        $requester = Role::where('name', 'Requester')->first();
        $this->assertNotNull($requester);
        $this->assertEqualsCanonicalizing(
            ['purchase-requests.create', 'purchase-requests.edit', 'purchase-requests.view-own'],
            $requester->permissions->pluck('name')->all()
        );

        $manager = Role::where('name', 'Purchase Manager')->first();
        $this->assertEqualsCanonicalizing(
            ['purchase-requests.approve', 'purchase-requests.view-all'],
            $manager->permissions->pluck('name')->all()
        );

        $procurement = Role::where('name', 'Procurement Officer')->first();
        $this->assertEqualsCanonicalizing(
            [
                'purchase-requests.manage-rfq',
                'purchase-requests.manage-quotes',
                'purchase-requests.award',
                'purchase-requests.generate-lpo',
                'purchase-requests.view-active-pipeline',
            ],
            $procurement->permissions->pluck('name')->all()
        );
    }

    public function test_running_twice_does_not_duplicate_or_error(): void
    {
        (new PurchaseAccessSeeder())->run();
        (new PurchaseAccessSeeder())->run();

        $this->assertCount(10, Permission::all());
        $this->assertCount(3, Role::whereIn('name', ['Requester', 'Purchase Manager', 'Procurement Officer'])->get());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PurchaseAccessSeederTest`
Expected: FAIL — `Class "Database\Seeders\PurchaseAccessSeeder" not found`.

- [ ] **Step 3: Create the config file**

Create `config/purchase_access.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purchase pipeline permissions
    |--------------------------------------------------------------------------
    |
    | Each key is the exact Spatie permission name; the value is the
    | human-readable label shown as a toggle in the User Management page.
    |
    */
    'permissions' => [
        'purchase-requests.create'              => 'Create purchase requests',
        'purchase-requests.edit'                => 'Edit purchase requests',
        'purchase-requests.view-own'            => 'View own purchase requests',
        'purchase-requests.view-active-pipeline' => 'View active pipeline (RFQ onward)',
        'purchase-requests.view-all'            => 'View all purchase requests (monitoring)',
        'purchase-requests.approve'             => 'Approve/reject purchase requests (GM signature)',
        'purchase-requests.manage-rfq'          => 'Select suppliers and send RFQ',
        'purchase-requests.manage-quotes'       => 'View and manage supplier quotes',
        'purchase-requests.award'               => 'Award items to suppliers',
        'purchase-requests.generate-lpo'        => 'Generate LPO',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-built profiles
    |--------------------------------------------------------------------------
    |
    | Each profile is a Spatie role bundling a fixed set of the permissions
    | above. Assigning a profile to a person grants these as a starting
    | point; an Admin can still toggle individual permissions per person on
    | top of (or instead of) any profile via the User Management page.
    |
    */
    'profiles' => [
        'Requester' => [
            'purchase-requests.create',
            'purchase-requests.edit',
            'purchase-requests.view-own',
        ],
        'Purchase Manager' => [
            'purchase-requests.approve',
            'purchase-requests.view-all',
        ],
        'Procurement Officer' => [
            'purchase-requests.manage-rfq',
            'purchase-requests.manage-quotes',
            'purchase-requests.award',
            'purchase-requests.generate-lpo',
            'purchase-requests.view-active-pipeline',
        ],
    ],

];
```

- [ ] **Step 4: Create the seeder**

Create `database/seeders/PurchaseAccessSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PurchaseAccessSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('purchase_access');

        foreach (array_keys($config['permissions']) as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach ($config['profiles'] as $profile => $permissions) {
            $role = Role::firstOrCreate(['name' => $profile]);
            $role->syncPermissions($permissions);
        }
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=PurchaseAccessSeederTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Wire the seeder into DatabaseSeeder**

Edit `database/seeders/DatabaseSeeder.php` — add the call right after the existing roles loop:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Admin', 'Accounts', 'Store Manager', 'Production Manager', 'Sales Manager'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $this->call(PurchaseAccessSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@erp.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password')]
        );
        $admin->assignRole('Admin');

        $warehouses = [
            ['code' => 'WH-MAIN', 'name' => 'Main Warehouse', 'location' => 'Main Building'],
            ['code' => 'WH-PROD', 'name' => 'Production Warehouse', 'location' => 'Factory Floor'],
            ['code' => 'WH-FG',   'name' => 'Finished Goods Warehouse', 'location' => 'Dispatch Area'],
        ];
        foreach ($warehouses as $wh) {
            Warehouse::firstOrCreate(['code' => $wh['code']], $wh);
        }

        $this->call(UrgencyLevelSeeder::class);
    }
}
```

- [ ] **Step 7: Wire the seeder into the test base class**

Edit `tests/TestCase.php`:

```php
<?php

namespace Tests;

use Database\Seeders\PurchaseAccessSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        // Sanctum's stateful-request detection (EnsureFrontendRequestsAreStateful)
        // only starts a session for requests carrying a Referer/Origin matching
        // SANCTUM_STATEFUL_DOMAINS — a real browser SPA request always has one,
        // so tests hitting /api/* need it too rather than bypassing the check.
        $this->withHeader('Referer', config('app.url'));
    }

    protected function seedRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roles = ['Admin', 'Accounts', 'Store Manager', 'Production Manager', 'Sales Manager'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        if (Schema::hasTable('permissions')) {
            (new PurchaseAccessSeeder())->run();
        }
    }
}
```

- [ ] **Step 8: Create the PurchaseRequestFactory**

Create `database/factories/PurchaseRequestFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_number'    => 'MPR-' . $this->faker->unique()->numberBetween(1000, 9999),
            'date'              => now(),
            'project_name'      => $this->faker->word(),
            'requested_by_name' => $this->faker->name(),
            'status'            => 'pending',
            'stage'             => 'draft',
            'requested_by'      => User::factory(),
        ];
    }
}
```

- [ ] **Step 9: Write a failing test proving the factory works, then confirm it passes**

Add to `tests/Unit/PurchaseAccessSeederTest.php` (append a 4th test):

```php
    public function test_purchase_request_factory_produces_a_valid_draft_request(): void
    {
        $pr = \App\Models\PurchaseRequest::factory()->create();

        $this->assertSame('draft', $pr->stage);
        $this->assertSame('pending', $pr->status);
        $this->assertNotNull($pr->requested_by);
    }
```

Run: `php artisan test --filter=PurchaseAccessSeederTest`
Expected: PASS (4 tests) — this also proves `HasFactory` on `PurchaseRequest` correctly discovers the new factory by naming convention.

- [ ] **Step 10: Run the full backend suite**

Run: `php artisan test`
Expected: all prior tests still pass, plus the 4 new ones (baseline count + 4, 0 failures beyond the one pre-existing unrelated `ExampleTest` failure that predates this work).

- [ ] **Step 11: Commit**

```bash
git add config/purchase_access.php database/seeders/PurchaseAccessSeeder.php database/seeders/DatabaseSeeder.php tests/TestCase.php database/factories/PurchaseRequestFactory.php tests/Unit/PurchaseAccessSeederTest.php
git commit -m "feat: seed purchase-request permissions and profiles"
```

---

### Task 2: User Management backend (controller, routes)

**Files:**
- Create: `app/Http/Controllers/Settings/UserManagementController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Settings/UserManagementControllerTest.php`

**Interfaces:**
- Consumes: `config('purchase_access.permissions')`/`profiles` (Task 1), `User` model's `HasRoles` methods (`assignRole`, `removeRole`, `syncPermissions`, `givePermissionTo`, `revokePermissionTo`, `roles`, `permissions`, `getAllPermissions()` — all from spatie/laravel-permission, already available).
- Produces: `GET settings/users` → `index` view with all users + all roles (existing 5 + 3 new profiles) + all permissions (the 10 purchase ones) available to the Blade view as `$users`, `$roles`, `$permissions`. `PATCH settings/users/{user}` → `update`, accepting JSON body `{roles: string[], permissions: string[]}`, returns `response()->json(['message' => ..., 'roles' => [...], 'permissions' => [...]])` on success, 422 on validation failure, 403 if attempting to remove the acting Admin's own Admin role.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Settings/UserManagementControllerTest.php`:

```php
<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_view_the_users_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.users.index'))->assertForbidden();
    }

    public function test_admin_can_view_the_users_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get(route('settings.users.index'))->assertOk();
    }

    public function test_admin_can_assign_a_profile_to_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->patchJson(route('settings.users.update', $target), [
            'roles'       => ['Requester'],
            'permissions' => [],
        ]);

        $response->assertOk();
        $this->assertTrue($target->fresh()->hasRole('Requester'));
    }

    public function test_admin_can_toggle_an_individual_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $this->actingAs($admin)->patchJson(route('settings.users.update', $target), [
            'roles'       => [],
            'permissions' => ['purchase-requests.view-all'],
        ]);

        $this->assertTrue($target->fresh()->hasPermissionTo('purchase-requests.view-all'));
    }

    public function test_changing_profile_does_not_clear_existing_custom_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();
        $target->givePermissionTo('purchase-requests.view-all');

        $this->actingAs($admin)->patchJson(route('settings.users.update', $target), [
            'roles'       => ['Requester'],
            'permissions' => ['purchase-requests.view-all'],
        ]);

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole('Requester'));
        $this->assertTrue($fresh->hasPermissionTo('purchase-requests.view-all'));
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->patchJson(route('settings.users.update', $admin), [
            'roles'       => [],
            'permissions' => [],
        ]);

        $response->assertStatus(403);
        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    public function test_non_admin_cannot_update_roles(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->patchJson(route('settings.users.update', $target), [
            'roles'       => ['Requester'],
            'permissions' => [],
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=UserManagementControllerTest`
Expected: FAIL — route `settings.users.index` not defined.

- [ ] **Step 3: Add the routes**

Edit `routes/web.php` — add inside the existing `Route::middleware('role:Admin')->group(function () { ... })` block (the same one containing `settings.vat`/`settings.projects`/etc.), right before its closing `});`:

```php
        // User management
        Route::get('settings/users', [UserManagementController::class, 'index'])->name('settings.users.index');
        Route::patch('settings/users/{user}', [UserManagementController::class, 'update'])->name('settings.users.update');
```

Add the import near the other `Settings\` controller imports at the top of `routes/web.php`:

```php
use App\Http\Controllers\Settings\UserManagementController;
```

- [ ] **Step 4: Implement the controller**

Create `app/Http/Controllers/Settings/UserManagementController.php`:

```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'permissions'])->orderBy('name')->get();
        $roles = Role::orderBy('name')->pluck('name');
        $permissions = collect(config('purchase_access.permissions'))
            ->map(fn ($label, $name) => ['name' => $name, 'label' => $label])
            ->values();

        return view('settings.users.index', compact('users', 'roles', 'permissions'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles'         => ['array'],
            'roles.*'       => ['string', 'exists:roles,name'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (
            $request->user()->id === $user->id
            && $user->hasRole('Admin')
            && ! in_array('Admin', $validated['roles'] ?? [], true)
        ) {
            return response()->json(['message' => 'You cannot remove your own Admin role.'], 403);
        }

        $user->syncRoles($validated['roles'] ?? []);
        $user->syncPermissions($validated['permissions'] ?? []);

        return response()->json([
            'message'     => 'Access updated for ' . $user->name . '.',
            'roles'       => $user->roles->pluck('name'),
            'permissions' => $user->permissions->pluck('name'),
        ]);
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=UserManagementControllerTest`
Expected: PASS (7 tests)

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: baseline + 4 (Task 1) + 7 (this task), 0 new failures.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Settings/UserManagementController.php routes/web.php tests/Feature/Settings/UserManagementControllerTest.php
git commit -m "feat: add User Management backend (list users, assign profiles/permissions)"
```

---

### Task 3: User Management frontend (page + sidebar link)

**Files:**
- Create: `resources/views/settings/users/index.blade.php`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Consumes: `$users` (each with `->roles` and `->permissions` Collections of models with `->name`), `$roles` (Collection of role name strings), `$permissions` (Collection of `['name' => ..., 'label' => ...]`) — all passed from `UserManagementController::index` (Task 2). Posts to `route('settings.users.update', userId)` via `PATCH` with JSON body `{roles: string[], permissions: string[]}` (Task 2's contract).
- Produces: nothing consumed by later tasks — this is a leaf page.

- [ ] **Step 1: Implement the view**

Create `resources/views/settings/users/index.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Settings — Users')

@section('content')
<div class="mb-5">
    <h1 class="page-title">User Management</h1>
    <p class="page-subtitle">Assign profiles and toggle individual permissions for each employee.</p>
</div>

<div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
    <input type="text" id="user-search" placeholder="Search users…"
           style="width:100%;max-width:320px;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;"
           onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
    <span id="search-count" style="font-size:12px;color:#94a3b8;font-weight:500;">{{ $users->count() }}</span>
</div>

<div style="background:white;border:1px solid #e2e8f0;border-radius:0.875rem;overflow:hidden;">
    <table class="table-base" style="width:100%;">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Profiles</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr class="data-row" data-user-id="{{ $user->id }}">
                <td>{{ $user->name }}</td>
                <td class="text-gray-500">{{ $user->email }}</td>
                <td>
                    @forelse($user->roles as $role)
                        <span style="display:inline-block;background:#eff6ff;color:#2563eb;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;margin-right:4px;">{{ $role->name }}</span>
                    @empty
                        <span style="color:#cbd5e1;font-size:12px;">No profile</span>
                    @endforelse
                </td>
                <td class="text-right">
                    <button class="btn-secondary btn-sm"
                            onclick="openAccessModal({{ $user->id }}, '{{ $user->name }}', {{ $user->roles->pluck('name')->toJson() }}, {{ $user->permissions->pluck('name')->toJson() }})">
                        Edit Access
                    </button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p id="no-results" class="hidden-by-search" style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;">No users match your search.</p>
</div>

{{-- ── Edit access modal ── --}}
<div id="access-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;width:100%;max-width:520px;max-height:85vh;overflow-y:auto;">
        <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <h2 id="access-modal-title" style="font-size:15px;font-weight:700;color:#0f172a;">Edit Access</h2>
            <button onclick="closeAccessModal()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:20px 24px;">
            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Profiles</div>
            <div id="access-roles-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px;">
                @foreach($roles as $role)
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;">
                    <input type="checkbox" class="access-role-checkbox" value="{{ $role }}">
                    {{ $role }}
                </label>
                @endforeach
            </div>

            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Individual Permissions</div>
            <div id="access-permissions-list" style="display:flex;flex-direction:column;gap:10px;">
                @foreach($permissions as $permission)
                <label style="display:flex;align-items:center;justify-content:space-between;font-size:13px;color:#374151;">
                    <span>{{ $permission['label'] }}</span>
                    <span class="toggle-switch" style="position:relative;display:inline-block;width:38px;height:20px;">
                        <input type="checkbox" class="access-permission-checkbox" value="{{ $permission['name'] }}" style="opacity:0;width:0;height:0;">
                        <span class="toggle-slider" style="position:absolute;inset:0;background:#e2e8f0;border-radius:999px;transition:.15s;cursor:pointer;"></span>
                    </span>
                </label>
                @endforeach
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
            <button class="btn-secondary" onclick="closeAccessModal()">Cancel</button>
            <button class="btn-primary" onclick="saveAccess()">Save</button>
        </div>
    </div>
</div>

<style>
    .hidden-by-search { display: none; }
    .access-permission-checkbox:checked + .toggle-slider { background: #2563eb; }
    .access-permission-checkbox:checked + .toggle-slider::before { transform: translateX(18px); }
    .toggle-slider::before {
        content: ''; position: absolute; height: 16px; width: 16px; left: 2px; top: 2px;
        background: white; border-radius: 50%; transition: .15s;
    }
</style>

<script>
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
var currentUserId = null;

function openAccessModal(userId, userName, roles, permissions) {
    currentUserId = userId;
    document.getElementById('access-modal-title').textContent = 'Edit Access — ' + userName;

    document.querySelectorAll('.access-role-checkbox').forEach(function(cb) {
        cb.checked = roles.indexOf(cb.value) !== -1;
    });
    document.querySelectorAll('.access-permission-checkbox').forEach(function(cb) {
        cb.checked = permissions.indexOf(cb.value) !== -1;
    });

    document.getElementById('access-modal').style.display = 'flex';
}

function closeAccessModal() {
    document.getElementById('access-modal').style.display = 'none';
    currentUserId = null;
}

function saveAccess() {
    var roles = Array.prototype.slice.call(document.querySelectorAll('.access-role-checkbox:checked')).map(function(cb) { return cb.value; });
    var permissions = Array.prototype.slice.call(document.querySelectorAll('.access-permission-checkbox:checked')).map(function(cb) { return cb.value; });

    fetch('/settings/users/' + currentUserId, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ roles: roles, permissions: permissions })
    }).then(function(r) {
        return r.json().then(function(body) {
            if (!r.ok) return Promise.reject(body);
            return body;
        });
    }).then(function(body) {
        closeAccessModal();
        showToast(body.message, 'success');
        setTimeout(function() { window.location.reload(); }, 600);
    }).catch(function(err) {
        showToast(err.message || 'Failed to update access.', 'error');
    });
}

// ── Instant client-side search ──
var searchInput = document.getElementById('user-search');
var rows        = document.querySelectorAll('.data-row');
var countEl     = document.getElementById('search-count');
var noResults   = document.getElementById('no-results');
var total       = rows.length;

searchInput.addEventListener('input', function() {
    var q = this.value.trim().toLowerCase();
    var visible = 0;
    rows.forEach(function(row) {
        var match = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
        row.classList.toggle('hidden-by-search', !match);
        if (match) visible++;
    });
    countEl.textContent = q ? (visible + ' of ' + total) : total;
    noResults.classList.toggle('hidden-by-search', visible !== 0);
});
</script>
@endsection
```

- [ ] **Step 2: Add the sidebar link**

Edit `resources/views/layouts/app.blade.php` — inside the existing `@role('Admin')` "System" section, add a link after "Projects" (find the existing `<a href="{{ route('settings.projects.overview') }}" ...>Projects</a>` block and insert immediately after its closing `</a>`):

```blade
            <a href="{{ route('settings.users.index') }}" style="
                display:flex; align-items:center; gap:8px;
                padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->routeIs('settings.users.*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4"/>
                </svg>
                Users
            </a>
```

- [ ] **Step 3: Manually verify (no automated frontend test suite covers Blade views in this codebase)**

Run: `php artisan route:list --path=settings/users` — confirm both routes appear.
Run: `php artisan test` — confirm no regressions (this task adds no PHP logic, only Blade).

- [ ] **Step 4: Commit**

```bash
git add resources/views/settings/users/index.blade.php resources/views/layouts/app.blade.php
git commit -m "feat: add User Management page and sidebar link"
```

---

### Task 4: PurchaseRequestPolicy

**Files:**
- Create: `app/Policies/PurchaseRequestPolicy.php`
- Test: `tests/Unit/PurchaseRequestPolicyTest.php`

**Interfaces:**
- Consumes: `PurchaseRequest::factory()` (Task 1), `config('purchase_access')` permissions (Task 1) via Spatie's `$user->can('permission-name')`.
- Produces: `App\Policies\PurchaseRequestPolicy` with methods `view(User, PurchaseRequest): bool`, `create(User): bool`, `update(User, PurchaseRequest): bool`, `approve(User, PurchaseRequest): bool`, `manageRfq(User, PurchaseRequest): bool`, `manageQuotes(User, PurchaseRequest): bool`, `award(User, PurchaseRequest): bool`, `generateLpo(User, PurchaseRequest): bool` — auto-discovered by Laravel via the `PurchaseRequest`/`PurchaseRequestPolicy` naming convention (no manual registration needed in Laravel 12). `Gate::before` registered in `app/Providers/AppServiceProvider.php` makes every ability return `true` for users with the `Admin` role, checked before any policy method runs. Later tasks call `$this->authorize('<ability>', $purchaseRequest)` in controllers using these exact ability names.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/PurchaseRequestPolicyTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_view_own_request_but_not_others(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $own   = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);
        $other = PurchaseRequest::factory()->create();

        $this->assertTrue($requester->can('view', $own));
        $this->assertFalse($requester->can('view', $other));
    }

    public function test_requester_can_update_own_draft_but_not_after_draft_or_someone_elses(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $ownDraft = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);
        $ownRfq   = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'rfq']);
        $othersDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($requester->can('update', $ownDraft));
        $this->assertFalse($requester->can('update', $ownRfq));
        $this->assertFalse($requester->can('update', $othersDraft));
    }

    public function test_purchase_manager_can_approve_only_at_gm_approval_stage(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $atStage    = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);
        $notAtStage = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($manager->can('approve', $atStage));
        $this->assertFalse($manager->can('approve', $notAtStage));
    }

    public function test_purchase_manager_can_view_all_but_cannot_award(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $anyRequest = PurchaseRequest::factory()->create(['stage' => 'comparison']);

        $this->assertTrue($manager->can('view', $anyRequest));
        $this->assertFalse($manager->can('award', $anyRequest));
    }

    public function test_procurement_officer_cannot_see_draft_stage_requests(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $draft = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->assertFalse($procurement->can('view', $draft));
        $this->assertTrue($procurement->can('view', $atRfq));
    }

    public function test_procurement_officer_can_manage_rfq_only_at_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atRfq  = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $atDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($procurement->can('manageRfq', $atRfq));
        $this->assertFalse($procurement->can('manageRfq', $atDraft));
    }

    public function test_procurement_officer_can_award_at_comparison_or_lpo_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atComparison = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $atLpo        = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $atRfq        = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->assertTrue($procurement->can('award', $atComparison));
        $this->assertTrue($procurement->can('award', $atLpo));
        $this->assertFalse($procurement->can('award', $atRfq));
    }

    public function test_procurement_officer_can_generate_lpo_only_at_lpo_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $atComparison = PurchaseRequest::factory()->create(['stage' => 'comparison']);

        $this->assertTrue($procurement->can('generateLpo', $atLpo));
        $this->assertFalse($procurement->can('generateLpo', $atComparison));
    }

    public function test_admin_bypasses_every_ability_regardless_of_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $anyRequest = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($admin->can('view', $anyRequest));
        $this->assertTrue($admin->can('update', $anyRequest));
        $this->assertTrue($admin->can('approve', $anyRequest));
        $this->assertTrue($admin->can('manageRfq', $anyRequest));
        $this->assertTrue($admin->can('award', $anyRequest));
        $this->assertTrue($admin->can('generateLpo', $anyRequest));
    }

    public function test_custom_toggle_grants_access_beyond_profile(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $requester->givePermissionTo('purchase-requests.view-all');
        $othersRequest = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->assertTrue($requester->can('view', $othersRequest));
    }

    public function test_user_with_no_profile_or_permissions_can_do_nothing(): void
    {
        $bystander = User::factory()->create();
        $anyRequest = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertFalse($bystander->can('view', $anyRequest));
        $this->assertFalse($bystander->can('create', PurchaseRequest::class));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=PurchaseRequestPolicyTest`
Expected: FAIL — `Call to undefined method` or policy resolves to default `false`/error since the policy class doesn't exist yet (Laravel's auto-discovery finds nothing, so `can()` returns `false` for everything, failing the `assertTrue` cases).

- [ ] **Step 3: Implement the policy**

Create `app/Policies/PurchaseRequestPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    private const ACTIVE_PIPELINE_STAGES = [
        'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete',
    ];

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($user->can('purchase-requests.view-all')) {
            return true;
        }

        if ($user->can('purchase-requests.view-active-pipeline')) {
            return in_array($purchaseRequest->stage, self::ACTIVE_PIPELINE_STAGES, true);
        }

        if ($user->can('purchase-requests.view-own')) {
            return $purchaseRequest->requested_by === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-requests.create');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.edit')
            && $purchaseRequest->requested_by === $user->id
            && $purchaseRequest->stage === 'draft';
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.approve') && $purchaseRequest->stage === 'gm_approval';
    }

    public function manageRfq(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.manage-rfq') && $purchaseRequest->stage === 'rfq';
    }

    public function manageQuotes(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.manage-quotes')
            && in_array($purchaseRequest->stage, ['quoting', 'comparison'], true);
    }

    public function award(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.award')
            && in_array($purchaseRequest->stage, ['comparison', 'lpo'], true);
    }

    public function generateLpo(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.generate-lpo') && $purchaseRequest->stage === 'lpo';
    }
}
```

- [ ] **Step 4: Register the Admin bypass**

Read `app/Providers/AppServiceProvider.php` first to see its current `boot()` method content, then add the `Gate::before` call inside it (do not remove anything already there). If the file's `boot()` is currently empty (`public function boot(): void { }`), the result should look like:

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('Admin') ? true : null;
        });
    }
}
```

(Returning `null` — not `false` — for non-Admins lets normal policy methods still run; returning `false` would deny everyone outright.)

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=PurchaseRequestPolicyTest`
Expected: PASS (11 tests)

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: baseline + 4 (Task 1) + 7 (Task 2) + 11 (this task), 0 new failures.

- [ ] **Step 7: Commit**

```bash
git add app/Policies/PurchaseRequestPolicy.php app/Providers/AppServiceProvider.php tests/Unit/PurchaseRequestPolicyTest.php
git commit -m "feat: add PurchaseRequestPolicy with Admin bypass"
```

---

### Task 5: Enforce the policy on PurchaseRequestController and PurchaseSignatureController

**Files:**
- Modify: `app/Http/Controllers/Purchase/PurchaseRequestController.php`
- Modify: `app/Http/Controllers/Purchase/PurchaseSignatureController.php`
- Test: `tests/Feature/Purchase/PurchaseRequestControllerAuthorizationTest.php`

**Interfaces:**
- Consumes: `PurchaseRequestPolicy` abilities `create`, `update`, `approve` (Task 4).
- Produces: nothing new consumed by later tasks — this task only adds `authorize()` calls to existing controller methods; signatures/return types are unchanged.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Purchase/PurchaseRequestControllerAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        return [
            'date' => now()->format('Y-m-d'),
            'project_name' => 'Test Project',
            'requested_by_name' => 'Test Person',
            'items' => [
                ['description' => 'Widget', 'quantity_required' => 5],
            ],
        ];
    }

    public function test_user_without_create_permission_cannot_store_a_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('purchase.requests.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_requester_can_store_a_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');

        $this->actingAs($requester)
            ->post(route('purchase.requests.store'), $this->validPayload())
            ->assertRedirect(route('purchase.requests.index'));

        $this->assertDatabaseHas('purchase_requests', ['requested_by_name' => 'Test Person']);
    }

    public function test_requester_cannot_update_someone_elses_draft_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $othersRequest = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->actingAs($requester)
            ->put(route('purchase.requests.update', $othersRequest), $this->validPayload())
            ->assertForbidden();
    }

    public function test_requester_cannot_update_own_request_past_draft_stage(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $ownRequest = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'rfq']);

        $this->actingAs($requester)
            ->put(route('purchase.requests.update', $ownRequest), $this->validPayload())
            ->assertForbidden();
    }

    public function test_user_without_approve_permission_cannot_approve(): void
    {
        $user = User::factory()->create();
        $atStage = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);

        $this->actingAs($user)
            ->patch(route('purchase.requests.approve', $atStage))
            ->assertForbidden();
    }

    public function test_purchase_manager_can_approve_at_gm_approval_stage(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $atStage = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);

        $this->actingAs($manager)
            ->patch(route('purchase.requests.approve', $atStage))
            ->assertRedirect();

        $this->assertSame('approved', $atStage->fresh()->status);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=PurchaseRequestControllerAuthorizationTest`
Expected: FAIL — all assertions expecting 403 instead get success responses, since no `authorize()` calls exist yet.

- [ ] **Step 3: Add authorization to PurchaseRequestController**

Edit `app/Http/Controllers/Purchase/PurchaseRequestController.php` — add one `$this->authorize(...)` line at the top of `store`, `update`, `approve`, and `reject`:

```php
    public function store(Request $request)
    {
        $this->authorize('create', PurchaseRequest::class);

        $request->validate([
```

```php
    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorize('update', $purchaseRequest);

        $request->validate([
```

```php
    public function approve(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        $purchaseRequest->update([
```

```php
    public function reject(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        $purchaseRequest->update(['status' => 'rejected']);
```

- [ ] **Step 4: Add authorization to PurchaseSignatureController**

Edit `app/Http/Controllers/Purchase/PurchaseSignatureController.php` — add `$this->authorize('approve', $purchaseRequest);` as the first line of both `show` and `store`:

```php
    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        $purchaseRequest->load('signature.signedBy');
        return view('purchase.signature.show', ['request' => $purchaseRequest]);
    }

    public function store(Request $request, PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $this->authorize('approve', $purchaseRequest);

        $validated = $request->validate([
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=PurchaseRequestControllerAuthorizationTest`
Expected: PASS (6 tests)

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: baseline + 4 + 7 + 11 + 6, 0 new failures. Pay attention to any *existing* passing test that creates/updates a `PurchaseRequest` while `actingAs` a user with no role — those will now start failing with 403 since they previously relied on the total absence of authorization. If any such test exists, give the acting user the relevant role/permission in that test's setup (e.g. `$user->assignRole('Requester');`) rather than weakening the new authorization.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Purchase/PurchaseRequestController.php app/Http/Controllers/Purchase/PurchaseSignatureController.php tests/Feature/Purchase/PurchaseRequestControllerAuthorizationTest.php
git commit -m "feat: enforce PurchaseRequestPolicy on request creation, editing, and approval"
```

---

### Task 6: Enforce the policy on RfqController, SupplierQuoteController, PurchaseOrderController

**Files:**
- Modify: `app/Http/Controllers/Purchase/RfqController.php`
- Modify: `app/Http/Controllers/Purchase/SupplierQuoteController.php`
- Modify: `app/Http/Controllers/Purchase/PurchaseOrderController.php`
- Test: `tests/Feature/Purchase/ProcurementAuthorizationTest.php`

**Interfaces:**
- Consumes: `PurchaseRequestPolicy` abilities `manageRfq`, `manageQuotes`, `award`, `generateLpo` (Task 4).
- Produces: nothing new — adds `authorize()` calls only.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Purchase/ProcurementAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_manage_rfq_cannot_select_suppliers(): void
    {
        $user = User::factory()->create();
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($user)
            ->post(route('purchase.requests.rfq.select', $atRfq), ['supplier_ids' => [$supplier->id]])
            ->assertForbidden();
    }

    public function test_procurement_officer_can_select_suppliers_at_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($procurement)
            ->post(route('purchase.requests.rfq.select', $atRfq), ['supplier_ids' => [$supplier->id]])
            ->assertRedirect();
    }

    public function test_procurement_officer_cannot_select_suppliers_before_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($procurement)
            ->post(route('purchase.requests.rfq.select', $atDraft), ['supplier_ids' => [$supplier->id]])
            ->assertForbidden();
    }

    public function test_user_without_award_permission_cannot_award_an_item(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $quote = SupplierQuote::factory()->create(['purchase_request_id' => $pr->id]);
        $quoteItem = SupplierQuoteItem::factory()->create(['supplier_quote_id' => $quote->id]);

        $this->actingAs($user)
            ->post(route('purchase.requests.quotes.items.award', [$pr, $quoteItem]))
            ->assertForbidden();
    }

    public function test_user_without_generate_lpo_permission_cannot_generate_it(): void
    {
        $user = User::factory()->create();
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs($user)
            ->post(route('purchase.requests.generate-lpo', $atLpo))
            ->assertForbidden();
    }
}
```

None of `RfqInvitationFactory`, `SupplierQuoteFactory`, or `SupplierQuoteItemFactory` exist yet — create all three now.

Create `database/factories/RfqInvitationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class RfqInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'supplier_id'         => Supplier::factory(),
            'token'               => $this->faker->unique()->uuid(),
            'channel'             => 'email',
            'status'              => 'submitted',
        ];
    }
}
```

Create `database/factories/SupplierQuoteFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierQuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rfq_invitation_id'   => RfqInvitation::factory(),
            'purchase_request_id' => PurchaseRequest::factory(),
            'supplier_id'         => Supplier::factory(),
            'submitted_at'        => now(),
        ];
    }
}
```

Create `database/factories/SupplierQuoteItemFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\SupplierQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierQuoteItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_quote_id' => SupplierQuote::factory(),
            'description'       => $this->faker->sentence(3),
            'quantity'          => 1,
            'unit_price'        => 10,
            'total_price'       => 10,
        ];
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=ProcurementAuthorizationTest`
Expected: FAIL — actions succeed (redirect/no error) where a 403 was expected.

- [ ] **Step 3: Add authorization to RfqController**

Edit `app/Http/Controllers/Purchase/RfqController.php` — add `$this->authorize('manageRfq', $purchaseRequest);` as the first line of `selectSuppliers`, `sendAll`, and `store`:

```php
    public function selectSuppliers(Request $request, PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $mode = $request->input('mode', 'global');
```

```php
    public function sendAll(PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $pending = $purchaseRequest->rfqInvitations()->where('status', 'pending')->with('supplier')->get();
```

```php
    public function store(Request $request, PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $validated = $request->validate([
```

- [ ] **Step 4: Add authorization to SupplierQuoteController**

Edit `app/Http/Controllers/Purchase/SupplierQuoteController.php`. Both `index` and `compare` delegate to a private `workspace()` method — add the check there once, so it covers both entry points:

```php
    private function workspace(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('manageQuotes', $purchaseRequest);

        $quotes = $this->loadQuotes($purchaseRequest);
```

Add `$this->authorize('award', $purchaseRequest);` as the first line of `awardItem`:

```php
    public function awardItem(Request $request, PurchaseRequest $purchaseRequest, SupplierQuoteItem $quoteItem, PurchaseStageService $stages)
    {
        $this->authorize('award', $purchaseRequest);

        abort_unless($quoteItem->quote->purchase_request_id === $purchaseRequest->id, 404);
```

And as the first line of `unawardItem`:

```php
    public function unawardItem(PurchaseRequest $purchaseRequest, SupplierQuoteItem $quoteItem, PurchaseStageService $stages)
    {
        $this->authorize('award', $purchaseRequest);

        abort_unless($quoteItem->quote->purchase_request_id === $purchaseRequest->id, 404);
```

- [ ] **Step 5: Add authorization to PurchaseOrderController**

Edit `app/Http/Controllers/Purchase/PurchaseOrderController.php` — add `$this->authorize('generateLpo', $purchaseRequest);` as the first line of `generateFromRequest`:

```php
    public function generateFromRequest(PurchaseRequest $purchaseRequest, LpoGenerationService $service, PurchaseStageService $stages)
    {
        $this->authorize('generateLpo', $purchaseRequest);

        try {
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=ProcurementAuthorizationTest`
Expected: PASS (5 tests)

- [ ] **Step 7: Run the full backend suite**

Run: `php artisan test`
Expected: baseline + 4 + 7 + 11 + 6 + 5, 0 new failures. As in Task 5 Step 6, fix any existing test that now 403s by granting the acting user the correct role/permission in its setup, not by removing the new `authorize()` call.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Purchase/RfqController.php app/Http/Controllers/Purchase/SupplierQuoteController.php app/Http/Controllers/Purchase/PurchaseOrderController.php tests/Feature/Purchase/ProcurementAuthorizationTest.php database/factories/RfqInvitationFactory.php database/factories/SupplierQuoteFactory.php database/factories/SupplierQuoteItemFactory.php
git commit -m "feat: enforce PurchaseRequestPolicy on RFQ, quoting, award, and LPO generation"
```

---

### Task 7: List-view scoping

**Files:**
- Modify: `app/Http/Controllers/Purchase/PurchasePipelineController.php`
- Test: `tests/Feature/Purchase/PurchasePipelineScopingTest.php`

**Interfaces:**
- Consumes: `purchase-requests.view-all` / `view-active-pipeline` / `view-own` permission checks (Task 1/4), the `view` policy ability (Task 4).
- Produces: nothing new consumed elsewhere.

Current exact content of `app/Http/Controllers/Purchase/PurchasePipelineController.php` (for reference — Step 4 shows the full replacement):

```php
<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\PurchaseStageService;

class PurchasePipelineController extends Controller
{
    private function withRelations()
    {
        return PurchaseRequest::with([
            'requestedBy',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes.items',
        ]);
    }

    public function index(PurchaseStageService $stages)
    {
        $active    = $this->withRelations()->where('stage', '!=', 'complete')->latest()->get();
        $completed = $this->withRelations()->where('stage', 'complete')->latest()->get();

        return view('purchase.pipeline.index', compact('active', 'completed', 'stages'));
    }

    public function show(PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $purchaseRequest->load([
            'requestedBy',
            'items',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes.supplier',
            'supplierQuotes.items',
            'purchaseOrders.supplier',
        ]);

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('purchase.pipeline.show', [
            'pr'        => $purchaseRequest,
            'stages'    => $stages,
            'suppliers' => $suppliers,
        ]);
    }
}
```

`index()` builds `$active` and `$completed` from the same `withRelations()` starting point — both need the same scoping applied. `show()` currently has no authorization at all — add the `view` check there too.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Purchase/PurchasePipelineScopingTest.php`:

```php
<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePipelineScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_sees_only_their_own_requests(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $own   = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);
        $other = PurchaseRequest::factory()->create();

        $response = $this->actingAs($requester)->get(route('purchase.pipeline.index'));

        $response->assertOk();
        $response->assertSee($own->request_number);
        $response->assertDontSee($other->request_number);
    }

    public function test_procurement_officer_sees_only_rfq_stage_or_later(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $draft = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $response = $this->actingAs($procurement)->get(route('purchase.pipeline.index'));

        $response->assertOk();
        $response->assertDontSee($draft->request_number);
        $response->assertSee($atRfq->request_number);
    }

    public function test_purchase_manager_sees_all_requests(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $a = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $b = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $response = $this->actingAs($manager)->get(route('purchase.pipeline.index'));

        $response->assertOk();
        $response->assertSee($a->request_number);
        $response->assertSee($b->request_number);
    }

    public function test_user_without_view_permission_cannot_open_a_single_request(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create();

        $this->actingAs($user)->get(route('purchase.pipeline.show', $pr))->assertForbidden();
    }

    public function test_requester_can_open_their_own_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);

        $this->actingAs($requester)->get(route('purchase.pipeline.show', $pr))->assertOk();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=PurchasePipelineScopingTest`
Expected: FAIL — the Requester and Procurement Officer index tests see requests they shouldn't, and the "without view permission" test gets 200 instead of 403, since no scoping or authorization exists yet.

- [ ] **Step 3: Apply the scoping and authorization**

Replace the full content of `app/Http/Controllers/Purchase/PurchasePipelineController.php` with:

```php
<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\PurchaseStageService;

class PurchasePipelineController extends Controller
{
    private function withRelations()
    {
        $query = PurchaseRequest::with([
            'requestedBy',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes.items',
        ]);

        $user = auth()->user();

        if (! $user->can('purchase-requests.view-all')) {
            if ($user->can('purchase-requests.view-active-pipeline')) {
                $query->whereIn('stage', [
                    'rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete',
                ]);
            } elseif ($user->can('purchase-requests.view-own')) {
                $query->where('requested_by', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public function index(PurchaseStageService $stages)
    {
        $active    = $this->withRelations()->where('stage', '!=', 'complete')->latest()->get();
        $completed = $this->withRelations()->where('stage', 'complete')->latest()->get();

        return view('purchase.pipeline.index', compact('active', 'completed', 'stages'));
    }

    public function show(PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'requestedBy',
            'items',
            'signature.signedBy',
            'rfqInvitations.supplier',
            'supplierQuotes.supplier',
            'supplierQuotes.items',
            'purchaseOrders.supplier',
        ]);

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('purchase.pipeline.show', [
            'pr'        => $purchaseRequest,
            'stages'    => $stages,
            'suppliers' => $suppliers,
        ]);
    }
}
```

The only changes from the original: `withRelations()` now applies the same permission-based scoping used by the policy's `view` ability (both `index`'s `$active`/`$completed` queries go through it, since both call `withRelations()`), and `show()` gains the `$this->authorize('view', $purchaseRequest);` call it was missing entirely before. Nothing else in either method changed.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=PurchasePipelineScopingTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Run the full backend suite**

Run: `php artisan test`
Expected: baseline + 4 + 7 + 11 + 6 + 5 + 5, 0 new failures.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Purchase/PurchasePipelineController.php tests/Feature/Purchase/PurchasePipelineScopingTest.php
git commit -m "feat: scope purchase pipeline list and authorize single-request view"
```

---

### Task 8: Hide unauthorized action buttons in the pipeline view

**Files:**
- Modify: `resources/views/purchase/pipeline/show.blade.php`

**Interfaces:**
- Consumes: `@can('approve', $pr)`, `@can('manageRfq', $pr)`, `@can('manageQuotes', $pr)`, `@can('award', $pr)`, `@can('generateLpo', $pr)`, `@can('update', $pr)` (Task 4's policy abilities, via Blade's built-in `@can` directive — no new code needed for this to work, since the policy is auto-discovered).
- Produces: nothing — this is the final, UI-polish task.

- [ ] **Step 1: Read the current view**

Read `resources/views/purchase/pipeline/show.blade.php` in full (951 lines) and locate each stage-specific action button described in the file (sign button, select/send RFQ button, compare & award button, generate/reissue LPO button, edit link for draft-stage requests).

- [ ] **Step 2: Wrap each action in the matching `@can` check**

For each action button found, wrap it in the corresponding directive without changing its markup or behavior otherwise:

- The "Sign" / GM-signature button/link → `@can('approve', $pr) ... @endcan`
- The "Select Suppliers" / "Send RFQ" buttons → `@can('manageRfq', $pr) ... @endcan`
- The "View Quotes" / "Compare & Award" button/link → `@can('manageQuotes', $pr) ... @endcan` (or `@can('award', $pr)` specifically for an award-triggering button, if the compare/award actions are on separate buttons — inspect the file to decide which ability matches which specific button)
- The "Generate LPO" / "Reissue LPO" button → `@can('generateLpo', $pr) ... @endcan`
- Any "Edit" link/button for the draft-stage request → `@can('update', $pr) ... @endcan`

- [ ] **Step 3: Manually verify**

Run: `php artisan test` — confirm no regressions (this task changes Blade conditionals only, no PHP logic covered by existing tests should be affected).
Manually load `/purchase/pipeline/{id}` in a browser (or via `curl` with an authenticated session) as a user with no purchase permissions and confirm no action buttons render, only read-only content.

- [ ] **Step 4: Commit**

```bash
git add resources/views/purchase/pipeline/show.blade.php
git commit -m "feat: hide purchase pipeline action buttons the viewer isn't authorized to use"
```

## Definition of Done for this plan

- `php artisan test` passes in full (baseline + all new tests from Tasks 1–7), with the User Management page (Tasks 2–3) working end-to-end: an Admin can log in, open Settings → Users, search, open a user's Edit Access modal, check profile boxes and flip permission toggles, save via AJAX, and see a success toast — all without a page reload except the deliberate reload after save to refresh the profile badges.
- Every action in the Purchase pipeline (create, edit, approve/reject, RFQ management, quote management, award, LPO generation) is authorized against the new policy, and list views are scoped per viewer.
- Receiving and payment stages remain unauthorized (unchanged), per the spec's explicit non-goal.
