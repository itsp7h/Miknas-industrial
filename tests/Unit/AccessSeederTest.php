<?php

namespace Tests\Unit;

use App\Models\PurchaseRequest;
use App\Support\AccessCatalog;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Every square of the grid exists as a permission the moment it is seeded. */
    public function test_seeds_a_permission_for_every_square_of_the_grid(): void
    {
        (new AccessSeeder)->run();

        $expected = AccessCatalog::permissions();
        $this->assertSame(count($expected), Permission::count());
        $this->assertEqualsCanonicalizing($expected, Permission::pluck('name')->all());

        // A tab with CRUD, a ledger tab with only two, and a report with one.
        $this->assertTrue(Permission::where('name', 'raw-materials.delete')->exists());
        $this->assertTrue(Permission::where('name', 'stock-movements.create')->exists());
        $this->assertFalse(Permission::where('name', 'stock-movements.delete')->exists());
        $this->assertTrue(Permission::where('name', 'low-stock.view')->exists());
        $this->assertFalse(Permission::where('name', 'low-stock.create')->exists());
    }

    /** Users and Integrations are Admin's alone, so they have no square at all. */
    public function test_the_admin_only_tabs_have_no_permission(): void
    {
        (new AccessSeeder)->run();

        foreach (config('access.admin_only_tabs') as $tab) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $this->assertFalse(
                    Permission::where('name', "{$tab}.{$action}")->exists(),
                    "{$tab}.{$action} should not be grantable"
                );
            }
        }
    }

    public function test_seeds_the_four_profiles(): void
    {
        (new AccessSeeder)->run();

        $this->assertEqualsCanonicalizing(
            ['Admin', 'Operation Manager', 'GM', 'Finance'],
            Role::pluck('name')->all()
        );

        // Admin is granted nothing: Gate::before passes it through every
        // ability, and listing them here would invite one being removed.
        $this->assertCount(0, Role::where('name', 'Admin')->first()->permissions);

        // Operation Manager raises purchase requests, and that is all for now.
        $this->assertEqualsCanonicalizing(
            ['pipeline.view', 'pipeline.create', 'pipeline.view-own'],
            Role::where('name', 'Operation Manager')->first()->permissions->pluck('name')->all()
        );

        // The GM signs off; it does not raise or award what it approves.
        $gm = Role::where('name', 'GM')->first();
        $this->assertTrue($gm->hasPermissionTo('pipeline.approve'));
        $this->assertFalse($gm->hasPermissionTo('pipeline.create'));
        $this->assertFalse($gm->hasPermissionTo('raw-materials.edit'));

        // Finance owns both sides of the money and nothing else.
        $finance = Role::where('name', 'Finance')->first();
        $this->assertTrue($finance->hasPermissionTo('supplier-payments.create'));
        $this->assertTrue($finance->hasPermissionTo('supplier-invoices.delete'));
        $this->assertFalse($finance->hasPermissionTo('raw-materials.edit'));
    }

    /**
     * A profile may only grant squares that exist, or someone is handed a
     * permission no route will ever ask for.
     */
    public function test_every_profile_grants_only_real_permissions(): void
    {
        $known = AccessCatalog::permissions();

        foreach (AccessCatalog::profiles() as $profile) {
            foreach ($profile['permissions'] as $permission) {
                $this->assertContains($permission, $known, "{$profile['name']} grants unknown {$permission}");
            }
        }
    }

    /** Every profile says what it is for, so the access form can show it. */
    public function test_every_profile_carries_a_description(): void
    {
        foreach (config('access.profiles') as $name => $definition) {
            $this->assertNotEmpty($definition['description'] ?? '', "{$name} has no description");
        }
    }

    public function test_running_twice_does_not_duplicate_or_error(): void
    {
        (new AccessSeeder)->run();
        (new AccessSeeder)->run();

        $this->assertCount(count(AccessCatalog::permissions()), Permission::all());
        $this->assertCount(4, Role::all());
    }

    public function test_purchase_request_factory_produces_a_valid_draft_request(): void
    {
        $pr = PurchaseRequest::factory()->create();

        $this->assertSame('draft', $pr->stage);
        $this->assertSame('pending', $pr->status);
        $this->assertNotNull($pr->requested_by);
    }
}
