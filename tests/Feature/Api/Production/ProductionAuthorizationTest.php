<?php

namespace Tests\Feature\Api\Production;

use App\Models\BillOfMaterial;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\AccessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production used to be open to anyone signed in: its routes checked nothing
 * past `auth`, and the module was kept out of reach only by being hidden from
 * the sidebar. Each tab is now its own permission, the way Inventory's are.
 */
class ProductionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private ProductionOrder $order;

    private BillOfMaterial $bom;

    protected function setUp(): void
    {
        parent::setUp();

        $product = Item::create(['item_code' => 'FG-1', 'item_name' => 'Frame', 'category' => 'finished_good', 'unit_of_measure' => 'PCS']);
        $material = Item::create(['item_code' => 'RM-1', 'item_name' => 'Steel Bar', 'category' => 'raw_material', 'unit_of_measure' => 'KG']);
        Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);

        $this->order = ProductionOrder::create([
            'order_number' => 'PO-00001',
            'product_id' => $product->id,
            'quantity_to_produce' => 10,
            'quantity_produced' => 0,
            'production_date' => now(),
            'status' => 'planned',
        ]);
        $this->bom = BillOfMaterial::create([
            'product_id' => $product->id,
            'raw_material_id' => $material->id,
            'quantity_required' => 2,
            'unit_of_measure' => 'KG',
        ]);
    }

    private function userWith(array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    /** Every Production endpoint, with the square that opens it. */
    private function endpoints(): array
    {
        $order = $this->order->id;
        $bom = $this->bom->id;

        return [
            ['get', '/api/v1/production/orders', 'production-orders.view'],
            ['get', "/api/v1/production/orders/{$order}", 'production-orders.view'],
            ['get', '/api/v1/production/orders/form-options', 'production-orders.create'],
            ['post', '/api/v1/production/orders', 'production-orders.create'],
            ['put', "/api/v1/production/orders/{$order}", 'production-orders.edit'],
            ['patch', "/api/v1/production/orders/{$order}/start", 'production-orders.run'],
            ['patch', "/api/v1/production/orders/{$order}/complete", 'production-orders.run'],
            ['delete', "/api/v1/production/orders/{$order}", 'production-orders.delete'],
            ['get', '/api/v1/production/bom', 'bom.view'],
            ['get', '/api/v1/production/bom/form-options', 'bom.create'],
            ['post', '/api/v1/production/bom', 'bom.create'],
            ['put', "/api/v1/production/bom/{$bom}", 'bom.edit'],
            ['delete', "/api/v1/production/bom/{$bom}", 'bom.delete'],
            ['get', '/api/v1/production/material-issues', 'material-issues.view'],
            ['get', '/api/v1/production/material-issues/form-options', 'material-issues.create'],
            ['post', '/api/v1/production/material-issues', 'material-issues.create'],
            ['get', '/api/v1/production/outputs', 'production-outputs.view'],
            ['get', '/api/v1/production/outputs/form-options', 'production-outputs.create'],
            ['post', '/api/v1/production/outputs', 'production-outputs.create'],
        ];
    }

    public function test_someone_with_no_production_square_is_refused_everywhere(): void
    {
        $user = $this->userWith(['raw-materials.view']);

        foreach ($this->endpoints() as [$method, $url]) {
            $this->actingAs($user)->{$method.'Json'}($url, [])
                ->assertForbidden();
        }
    }

    /**
     * Holding the square gets past the gate. What the endpoint then says about
     * an empty body (a 422) is its own business; anything but a 403 will do.
     */
    public function test_each_endpoint_opens_to_its_own_square(): void
    {
        foreach ($this->endpoints() as [$method, $url, $permission]) {
            $status = $this->actingAs($this->userWith([$permission]))
                ->{$method.'Json'}($url, [])
                ->status();

            $this->assertNotSame(403, $status, "{$method} {$url} refused {$permission}");
        }
    }

    public function test_viewing_an_order_does_not_let_you_run_or_change_it(): void
    {
        $viewer = $this->userWith(['production-orders.view']);

        $this->actingAs($viewer)->getJson("/api/v1/production/orders/{$this->order->id}")->assertOk();
        $this->actingAs($viewer)->patchJson("/api/v1/production/orders/{$this->order->id}/start")->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/v1/production/orders/{$this->order->id}")->assertForbidden();

        $this->assertSame('planned', $this->order->fresh()->status);
    }

    /** The order form is also the edit form, so either square loads its options. */
    public function test_editing_alone_loads_the_order_form_options(): void
    {
        $this->actingAs($this->userWith(['production-orders.edit']))
            ->getJson('/api/v1/production/orders/form-options')
            ->assertOk();
    }

    public function test_an_admin_reaches_production_without_holding_a_square(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->getJson('/api/v1/production/orders')->assertOk();
    }

    public function test_the_production_squares_are_on_the_users_page_grid(): void
    {
        $tabs = collect(AccessCatalog::grid())->where('group', 'Production')->pluck('tab')->all();

        $this->assertSame(['production-orders', 'bom', 'material-issues', 'production-outputs'], $tabs);
    }
}
