<?php

namespace Tests\Feature\Api\Inventory;

use App\Events\ItemDeleted;
use App\Events\ItemSaved;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Someone who may use the module under test.
     *
     * Admin, because these tests are about behaviour, not about who is allowed
     * to reach it — that is what the authorization tests are for, and they
     * grant single permissions explicitly.
     */
    private function actingUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
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
     * The list's Quantity column is on-hand everywhere, not per warehouse, so
     * an item stocked in two places has to read as the total of both.
     */
    public function test_it_totals_on_hand_quantity_across_every_warehouse(): void
    {
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Angle Bar', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $unstocked = Item::create(['item_code' => 'ITEM-2', 'item_name' => 'Zinc Sheet', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $main = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
        $yard = Warehouse::create(['code' => 'WH-2', 'name' => 'Yard']);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $main->id, 'quantity' => 12.5]);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $yard->id, 'quantity' => 7.25]);

        $response = $this->actingAs($this->actingUser())->getJson('/api/v1/inventory/items');

        $response->assertOk()
            ->assertJsonPath('data.0.quantity', 19.75)
            // Never stocked anywhere is 0, not null — the column formats a number.
            ->assertJsonPath('data.1.quantity', 0);
        $this->assertSame($unstocked->id, $response->json('data.1.id'));
    }

    /**
     * The list is one row per item, so the Warehouse column has to carry the
     * split the per-line stock summary would otherwise be the only place to
     * see — biggest holding first, and nothing at all for an unstocked item.
     */
    public function test_it_breaks_the_quantity_down_by_warehouse_biggest_first(): void
    {
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Angle Bar', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $unstocked = Item::create(['item_code' => 'ITEM-2', 'item_name' => 'Zinc Sheet', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $main = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
        $yard = Warehouse::create(['code' => 'WH-2', 'name' => 'Yard']);
        $assigned = Warehouse::create(['code' => 'WH-3', 'name' => 'Askar']);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $main->id, 'quantity' => 4]);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $yard->id, 'quantity' => 11]);
        // Assigned on the item form but nothing has arrived yet. It is still
        // somewhere the item lives, so the column must name it.
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $assigned->id, 'quantity' => 0]);

        $response = $this->actingAs($this->actingUser())->getJson('/api/v1/inventory/items');

        $response->assertOk()
            ->assertJsonPath('data.0.warehouses.0.name', 'Yard')
            ->assertJsonPath('data.0.warehouses.0.quantity', 11)
            ->assertJsonPath('data.0.warehouses.1.name', 'Main')
            ->assertJsonPath('data.0.warehouses.2.name', 'Askar')
            ->assertJsonPath('data.0.warehouses.2.quantity', 0)
            ->assertJsonPath('data.1.warehouses', []);
        $this->assertSame($unstocked->id, $response->json('data.1.id'));
    }

    /**
     * A save answers with one item and the list merges that row in place, so a
     * response missing the eager-loaded sum would blank the quantity on screen.
     */
    public function test_an_updated_item_still_carries_its_quantity(): void
    {
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Old', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 9.5]);

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/inventory/items/{$item->id}", $this->payload(['item_name' => 'New Name']))
            ->assertOk()
            ->assertJsonPath('data.quantity', 9.5)
            ->assertJsonPath('data.warehouses.0.name', 'Main');
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

    /**
     * The regression this guard exists for.
     *
     * An item on a purchase order but with no stock movement passed the old
     * check, reached the delete, and hit the RESTRICT foreign key — the client
     * got a 500 carrying a raw QueryException and the row stayed put with
     * nothing to explain why.
     */
    public function test_it_deactivates_an_item_used_only_by_a_purchase_order_rather_than_erroring(): void
    {
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Cotton Gloves', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $this->orderFor($item);
        $this->assertSame(0, $item->stockMovements()->count());

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('deactivated', true)
            ->assertJsonPath('message', 'Item is used by purchase orders, so it was deactivated rather than deleted.');

        $this->assertDatabaseHas('items', ['id' => $item->id]);
        $this->assertFalse($item->fresh()->is_active);
    }

    /** The message names everything in the way, not just the first thing found. */
    public function test_it_names_every_reason_the_item_could_not_be_deleted(): void
    {
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Steel', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
        $this->orderFor($item);
        StockMovement::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'type' => 'in', 'quantity' => 10]);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Item is used by purchase orders and stock movements, so it was deactivated rather than deleted.');
    }

    /**
     * Stock levels cascade, so the database would allow this delete and take
     * the record of what the warehouse is holding with it.
     */
    public function test_it_deactivates_an_item_that_still_holds_stock(): void
    {
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Wood', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 6]);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Item is used by stock on hand, so it was deactivated rather than deleted.');
    }

    /** An emptied shelf is not a reason to keep the item. */
    public function test_a_stock_level_of_zero_does_not_block_the_delete(): void
    {
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Scrap', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 0]);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    /**
     * The form shows one control reading "Raw Materials / Chemical Materials";
     * the index has to hand it every choice, each carrying the two columns it
     * stands for.
     */
    public function test_the_index_sends_the_options_for_the_single_category_dropdown(): void
    {
        $options = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/items')
            ->assertOk()
            ->json('meta.category_options');

        $labels = array_column($options, 'label');
        $this->assertContains('Raw Materials', $labels, 'A bare type must be offered: not everything is in a section.');
        $this->assertContains('Raw Materials / Chemical Materials', $labels);
        $this->assertContains('Finished Goods', $labels);

        $section = collect($options)->firstWhere('label', 'Raw Materials / Chemical Materials');
        $this->assertSame('raw_material', $section['category']);
        $this->assertNotNull($section['item_category_id']);
    }

    public function test_it_stores_and_returns_the_section_with_the_item(): void
    {
        $section = ItemCategory::where('name', 'Chemical Materials')->firstOrFail();

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload(['item_category_id' => $section->id]))
            ->assertCreated()
            ->assertJsonPath('data.item_category_id', $section->id)
            ->assertJsonPath('data.item_category_name', 'Chemical Materials')
            ->assertJsonPath('data.category_path', 'Raw Materials / Chemical Materials');
    }

    /** An item in no section still reads as something. */
    public function test_an_item_without_a_section_falls_back_to_its_type(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload(['category' => 'finished_good']))
            ->assertCreated()
            ->assertJsonPath('data.item_category_id', null)
            ->assertJsonPath('data.category_path', 'Finished Goods');
    }

    /**
     * A section belongs to exactly one type. Storing "Finished Goods" against a
     * raw-material section would render a path that contradicts itself.
     */
    public function test_it_rejects_a_section_that_belongs_to_another_type(): void
    {
        $section = ItemCategory::where('name', 'Bulk')->firstOrFail();

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload([
                'category' => 'finished_good',
                'item_category_id' => $section->id,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.item_category_id.0', 'That section belongs to Raw Materials.');
    }

    /**
     * The gap this closes: stock levels were only ever created by a stock
     * movement, which demands at least 0.01, so an item could not be said to
     * live anywhere until stock had been moved into it — and every imported
     * item showed no warehouse at all.
     */
    public function test_it_assigns_a_warehouse_with_no_stock_at_all(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);

        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload(['warehouse_id' => $warehouse->id]))
            ->assertCreated();

        $response->assertJsonPath('data.warehouse_id', $warehouse->id)
            ->assertJsonPath('data.warehouses.0.name', 'Askar')
            ->assertJsonPath('data.quantity', 0);

        $this->assertDatabaseHas('stock_levels', ['warehouse_id' => $warehouse->id, 'quantity' => 0]);
        // Nothing arrived, so nothing is claimed to have arrived.
        $this->assertSame(0, StockMovement::count());
    }

    /** An opening figure is a real receipt, so the ledger has to show one. */
    public function test_an_opening_quantity_goes_in_through_a_stock_movement(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/items', $this->payload([
                'warehouse_id' => $warehouse->id,
                'opening_quantity' => 25.5,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.quantity', 25.5);

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $warehouse->id, 'type' => 'in', 'quantity' => 25.5,
        ]);
    }

    public function test_editing_an_item_can_move_an_empty_assignment(): void
    {
        $from = Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);
        $to = Warehouse::create(['code' => 'WH-2', 'name' => 'Sitra']);
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Silica Sand', 'category' => 'raw_material', 'unit_of_measure' => 'KG']);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $from->id, 'quantity' => 0]);

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/inventory/items/{$item->id}", $this->payload(['warehouse_id' => $to->id]))
            ->assertOk()
            ->assertJsonPath('data.warehouse_id', $to->id);

        $this->assertDatabaseMissing('stock_levels', ['item_id' => $item->id, 'warehouse_id' => $from->id]);
    }

    /**
     * Physical stock must not move because someone edited a form. That is what
     * a transfer movement is for, and it leaves a trail.
     */
    public function test_it_refuses_to_move_an_item_that_still_holds_stock(): void
    {
        $from = Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);
        $to = Warehouse::create(['code' => 'WH-2', 'name' => 'Sitra']);
        $item = Item::create(['item_code' => 'ITEM-1', 'item_name' => 'Silica Sand', 'category' => 'raw_material', 'unit_of_measure' => 'KG']);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $from->id, 'quantity' => 40]);

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/inventory/items/{$item->id}", $this->payload(['warehouse_id' => $to->id]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.warehouse_id.0',
                'This item still holds stock in Askar. Record a stock movement to move it.'
            );

        $this->assertDatabaseHas('stock_levels', ['warehouse_id' => $from->id, 'quantity' => 40]);
    }

    /** The form's select needs the list it is choosing from. */
    public function test_the_index_sends_the_active_warehouses(): void
    {
        Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);
        Warehouse::create(['code' => 'WH-2', 'name' => 'Closed', 'is_active' => false]);

        $names = array_column(
            $this->actingAs($this->actingUser())->getJson('/api/v1/inventory/items')->json('meta.warehouses'),
            'name'
        );

        $this->assertSame(['Askar'], $names);
    }

    private function orderFor(Item $item): void
    {
        $order = PurchaseOrder::create([
            'po_number' => 'PO-00001',
            'supplier_id' => Supplier::factory()->create()->id,
            'po_date' => now(),
            'total_amount' => 20,
            'status' => 'sent',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id, 'item_id' => $item->id,
            'quantity' => 2, 'rate' => 10, 'total_amount' => 20,
        ]);
    }
}
