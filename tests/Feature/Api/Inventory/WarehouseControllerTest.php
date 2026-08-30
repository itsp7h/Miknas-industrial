<?php

namespace Tests\Feature\Api\Inventory;

use App\Events\WarehouseDeleted;
use App\Events\WarehouseSaved;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WarehouseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'WH-MAIN',
            'name' => 'Main Store',
            'location' => 'Sitra',
            'description' => 'Primary warehouse',
        ], $overrides);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/inventory/warehouses')->assertUnauthorized();
    }

    public function test_it_lists_warehouses_ordered_by_name(): void
    {
        Warehouse::create(['code' => 'WH-2', 'name' => 'Yard']);
        Warehouse::create(['code' => 'WH-1', 'name' => 'Annex']);

        $response = $this->actingAs($this->actingUser())->getJson('/api/v1/inventory/warehouses');

        $response->assertOk();
        $this->assertSame(['Annex', 'Yard'], array_column($response->json('data'), 'name'));
    }

    public function test_it_creates_a_warehouse(): void
    {
        Event::fake([WarehouseSaved::class]);

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/warehouses', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'WH-MAIN');

        $this->assertDatabaseHas('warehouses', ['code' => 'WH-MAIN', 'name' => 'Main Store']);
        Event::assertDispatched(WarehouseSaved::class);
    }

    /**
     * warehouses.code is NOT NULL with no default. The Blade controller
     * validated only name/location, so a blank code reached the insert and
     * surfaced as a database error instead of a validation message.
     */
    public function test_it_rejects_a_missing_code_with_a_validation_error_not_a_db_error(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/warehouses', $this->payload(['code' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_it_rejects_a_duplicate_code(): void
    {
        Warehouse::create(['code' => 'WH-MAIN', 'name' => 'Existing']);

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/warehouses', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_it_allows_a_warehouse_to_keep_its_own_code_on_update(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-MAIN', 'name' => 'Main']);

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/inventory/warehouses/{$warehouse->id}", $this->payload(['name' => 'Renamed']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_it_deletes_a_warehouse_with_no_stock_records(): void
    {
        Event::fake([WarehouseDeleted::class]);
        $warehouse = Warehouse::create(['code' => 'WH-TMP', 'name' => 'Temp']);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/warehouses/{$warehouse->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
        Event::assertDispatched(WarehouseDeleted::class);
    }

    public function test_it_deactivates_rather_than_deletes_a_warehouse_holding_stock(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-MAIN', 'name' => 'Main']);
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Rod', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        StockMovement::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => 5,
        ]);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/warehouses/{$warehouse->id}")
            ->assertOk()
            ->assertJsonPath('deactivated', true);

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
        $this->assertFalse($warehouse->fresh()->is_active);
    }
}
