<?php

namespace Tests\Feature\Api\Inventory;

use App\Events\StockMovementRecorded;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockMovementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Item $item;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->item = Item::create([
            'item_code' => 'ITEM-1', 'item_name' => 'Rod',
            'category' => 'raw_material', 'unit_of_measure' => 'PCS',
            'minimum_stock_level' => 5,
        ]);
        $this->warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
    }

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'item_id' => $this->item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'quantity' => 10,
        ], $overrides);
    }

    private function level(): ?StockLevel
    {
        return StockLevel::where('item_id', $this->item->id)
            ->where('warehouse_id', $this->warehouse->id)->first();
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/inventory/movements')->assertUnauthorized();
    }

    public function test_an_inbound_movement_raises_the_stock_level(): void
    {
        Event::fake([StockMovementRecorded::class]);

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/movements', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'in');

        $this->assertEquals(10, $this->level()->quantity);
        Event::assertDispatched(StockMovementRecorded::class);
    }

    public function test_an_outbound_movement_lowers_the_stock_level(): void
    {
        $user = $this->actingUser();
        $this->actingAs($user)->postJson('/api/v1/inventory/movements', $this->payload(['quantity' => 10]));
        $this->actingAs($user)->postJson('/api/v1/inventory/movements', $this->payload(['type' => 'out', 'quantity' => 4]));

        $this->assertEquals(6, $this->level()->quantity);
    }

    /**
     * Stock must never go negative: an outbound movement larger than what is
     * held clamps the level at zero rather than wrapping below it.
     */
    public function test_an_outbound_movement_cannot_drive_stock_below_zero(): void
    {
        $user = $this->actingUser();
        $this->actingAs($user)->postJson('/api/v1/inventory/movements', $this->payload(['quantity' => 3]));
        $this->actingAs($user)->postJson('/api/v1/inventory/movements', $this->payload(['type' => 'out', 'quantity' => 99]))
            ->assertCreated();

        $this->assertEquals(0, $this->level()->quantity);
    }

    public function test_it_rejects_an_unknown_type(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/movements', $this->payload(['type' => 'teleport']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_it_rejects_a_zero_or_negative_quantity(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/movements', $this->payload(['quantity' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_it_rejects_an_item_or_warehouse_that_does_not_exist(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/movements', $this->payload(['item_id' => 9999, 'warehouse_id' => 9999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_id', 'warehouse_id']);
    }

    public function test_it_lists_movements_newest_first_with_item_and_warehouse_names(): void
    {
        $user = $this->actingUser();
        $this->actingAs($user)->postJson('/api/v1/inventory/movements', $this->payload(['quantity' => 1]));
        $this->actingAs($user)->postJson('/api/v1/inventory/movements', $this->payload(['quantity' => 2]));

        $response = $this->actingAs($user)->getJson('/api/v1/inventory/movements')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame('Rod', $response->json('data.0.item_name'));
        $this->assertSame('Main', $response->json('data.0.warehouse_name'));
    }

    public function test_form_options_returns_only_active_items_and_warehouses(): void
    {
        Item::create(['item_code' => 'ITEM-2', 'item_name' => 'Retired', 'category' => 'wip', 'unit_of_measure' => 'PCS', 'is_active' => false]);
        Warehouse::create(['code' => 'WH-OLD', 'name' => 'Closed', 'is_active' => false]);

        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/movements/form-options')->assertOk();

        $this->assertSame(['Rod'], array_column($response->json('items'), 'item_name'));
        $this->assertSame(['Main'], array_column($response->json('warehouses'), 'name'));
        $this->assertSame(['in', 'out', 'adjustment'], $response->json('types'));
    }
}
