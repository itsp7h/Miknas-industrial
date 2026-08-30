<?php

namespace Tests\Feature\Api\Inventory;

use App\Events\ItemDeleted;
use App\Events\ItemSaved;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'item_name' => 'Steel Rod 12mm',
            'category' => 'raw_material',
            'unit_of_measure' => 'PCS',
            'minimum_stock_level' => 5,
            'cost_price' => 10.5,
            'description' => 'Structural rebar',
        ], $overrides);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/inventory/items')->assertUnauthorized();
    }

    public function test_it_lists_items_ordered_by_name(): void
    {
        Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Zinc Sheet', 'category' => 'finished_good', 'unit_of_measure' => 'PCS']);
        Item::create(['item_code' => 'ITEM-2', 'item_name' => 'Angle Bar', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);

        $response = $this->actingAs($this->actingUser())->getJson('/api/v1/inventory/items');

        $response->assertOk();
        $this->assertSame(['Angle Bar', 'Zinc Sheet'], array_column($response->json('data'), 'item_name'));
    }

    /**
     * The Blade controller this replaces validated `name`/`type` — fields its
     * own form never posted and the model does not have — so creation was
     * impossible. These are the real column names.
     */
    public function test_it_creates_an_item_with_the_real_column_names(): void
    {
        Event::fake([ItemSaved::class]);

        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.item_name', 'Steel Rod 12mm')
            ->assertJsonPath('data.category', 'raw_material');

        $this->assertDatabaseHas('items', ['item_name' => 'Steel Rod 12mm', 'category' => 'raw_material']);
        Event::assertDispatched(ItemSaved::class);
    }

    public function test_it_generates_a_sequential_item_code_and_ignores_a_client_supplied_one(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload(['item_code' => 'HACKED']));

        $response->assertCreated();
        $this->assertNotSame('HACKED', $response->json('data.item_code'));
        $this->assertMatchesRegularExpression('/^ITEM-\d{5}$/', $response->json('data.item_code'));
    }

    public function test_it_rejects_an_item_with_no_name_or_unit(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_name', 'category', 'unit_of_measure']);
    }

    public function test_it_rejects_a_category_outside_the_enum(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload(['category' => 'Raw Material']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_it_defaults_new_items_to_active(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload());

        $this->assertTrue($response->json('data.is_active'));
    }

    public function test_it_updates_an_item_and_broadcasts(): void
    {
        Event::fake([ItemSaved::class]);
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Old', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/inventory/items/{$item->id}", $this->payload(['item_name' => 'New Name']))
            ->assertOk()
            ->assertJsonPath('data.item_name', 'New Name');

        $this->assertSame('New Name', $item->fresh()->item_name);
        Event::assertDispatched(ItemSaved::class);
    }

    public function test_it_deletes_an_item_that_has_no_stock_history(): void
    {
        Event::fake([ItemDeleted::class]);
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Scrap', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
        Event::assertDispatched(ItemDeleted::class);
    }

    /**
     * Deleting an item with movement history would orphan the stock ledger and
     * break every report that resolves an item name, so it deactivates instead.
     */
    public function test_it_deactivates_rather_than_deletes_an_item_with_stock_movements(): void
    {
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Used', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
        StockMovement::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => 10,
        ]);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('deactivated', true);

        $this->assertDatabaseHas('items', ['id' => $item->id]);
        $this->assertFalse($item->fresh()->is_active);
    }
}
