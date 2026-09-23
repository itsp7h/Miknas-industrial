<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Moving a spreadsheet in, or a list out, is its own square.
 *
 * It used to ride on `create` and `view`, so it could not be handed out or
 * taken back on its own. Now it can, and an Admin sees it on the access form
 * like any other square.
 */
class ImportExportPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function item(string $name, string $category): Item
    {
        return Item::create([
            'item_code' => 'ITEM-'.str_pad((string) (Item::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'item_name' => $name,
            'category' => $category,
            'unit_of_measure' => 'PCS',
            'minimum_stock_level' => 0,
            'cost_price' => 1,
        ]);
    }

    public static function importRoutes(): array
    {
        return [
            'suppliers' => ['get', '/api/v1/purchase/suppliers/template', 'suppliers.import', 'suppliers.create'],
            'items' => ['get', '/api/v1/inventory/items/template', 'raw-materials.import', 'raw-materials.create'],
            'projects' => ['get', '/api/v1/settings/projects/template', 'projects.import', 'projects.create'],
        ];
    }

    #[DataProvider('importRoutes')]
    public function test_importing_needs_its_own_square_not_create(
        string $method, string $url, string $needed, string $noLongerEnough
    ): void {
        // The permission that used to open this door no longer does.
        $this->actingAs($this->userWith([$noLongerEnough]))
            ->{$method.'Json'}($url)
            ->assertForbidden();

        $this->actingAs($this->userWith([$needed]))
            ->{$method.'Json'}($url)
            ->assertSuccessful();
    }

    public function test_exporting_needs_its_own_square_not_view(): void
    {
        $this->actingAs($this->userWith(['suppliers.view']))
            ->get('/api/v1/purchase/suppliers/export-pdf')
            ->assertForbidden();

        $this->actingAs($this->userWith(['suppliers.export']))
            ->get('/api/v1/purchase/suppliers/export-pdf')
            ->assertSuccessful();
    }

    /** Either inventory tab's square opens the shared items endpoints. */
    public function test_either_inventory_tab_may_export_items(): void
    {
        $this->actingAs($this->userWith(['finished-goods.export']))
            ->get('/api/v1/inventory/items/export-pdf')
            ->assertSuccessful();

        $this->actingAs($this->userWith(['raw-materials.view', 'finished-goods.view']))
            ->get('/api/v1/inventory/items/export-pdf')
            ->assertForbidden();
    }

    /**
     * Raw Materials and Finished Goods are two tabs with two squares but one
     * set of endpoints, so the route middleware can only ask for *either*. The
     * controller decides which rows, and this is what keeps the separation the
     * sidebar promises.
     */
    public function test_a_raw_materials_grant_does_not_reach_finished_goods(): void
    {
        $this->item('Steel Plate', 'raw_material');
        $this->item('Painted Frame', 'finished_good');

        $rawOnly = $this->userWith(['raw-materials.view']);

        $names = collect($this->actingAs($rawOnly)->getJson('/api/v1/inventory/items')->assertOk()->json('data'))
            ->pluck('item_name');

        $this->assertContains('Steel Plate', $names);
        $this->assertNotContains('Painted Frame', $names);
    }

    public function test_both_grants_see_both(): void
    {
        $this->item('Steel Plate', 'raw_material');
        $this->item('Painted Frame', 'finished_good');

        $names = collect(
            $this->actingAs($this->userWith(['raw-materials.view', 'finished-goods.view']))
                ->getJson('/api/v1/inventory/items')->assertOk()->json('data')
        )->pluck('item_name');

        $this->assertContains('Steel Plate', $names);
        $this->assertContains('Painted Frame', $names);
    }

    /** WIP is not its own tab — it belongs with the raw materials. */
    public function test_work_in_progress_travels_with_raw_materials(): void
    {
        $this->item('Half-built Frame', 'wip');

        $names = collect(
            $this->actingAs($this->userWith(['raw-materials.view']))
                ->getJson('/api/v1/inventory/items')->assertOk()->json('data')
        )->pluck('item_name');

        $this->assertContains('Half-built Frame', $names);
    }

    /** The whole point: an Admin can find them on the access form. */
    public function test_the_new_squares_appear_on_the_access_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $grid = collect($this->actingAs($admin)->getJson('/api/v1/settings/users')->assertOk()->json('grid'))
            ->keyBy('tab');

        $extras = fn (string $tab) => collect($grid[$tab]['extra'])->pluck('name')->all();

        $this->assertContains('suppliers.import', $extras('suppliers'));
        $this->assertContains('suppliers.export', $extras('suppliers'));
        $this->assertContains('raw-materials.import', $extras('raw-materials'));
        $this->assertContains('finished-goods.export', $extras('finished-goods'));
        // Projects have a template but nothing that writes a PDF.
        $this->assertContains('projects.import', $extras('projects'));
        $this->assertNotContains('projects.export', $extras('projects'));
    }

    public function test_the_catalogue_knows_them_so_the_seeder_creates_them(): void
    {
        $names = AccessCatalog::permissions();

        foreach ([
            'suppliers.import', 'suppliers.export',
            'raw-materials.import', 'raw-materials.export',
            'finished-goods.import', 'finished-goods.export',
            'projects.import',
        ] as $permission) {
            $this->assertContains($permission, $names);
        }
    }

    /** A profile that could export before still can, so nothing silently narrows. */
    public function test_the_read_only_profiles_keep_their_exports(): void
    {
        foreach (['GM', 'Finance'] as $profile) {
            $permissions = AccessCatalog::defaultPermissionsFor($profile);

            $this->assertContains('suppliers.export', $permissions, $profile);
            $this->assertContains('raw-materials.export', $permissions, $profile);
            $this->assertContains('finished-goods.export', $permissions, $profile);
            // Neither creates items, so neither imports them.
            $this->assertNotContains('raw-materials.import', $permissions, $profile);
        }
    }
}
