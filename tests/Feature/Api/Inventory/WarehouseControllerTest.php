<?php

namespace Tests\Feature\Api\Inventory;

use App\Events\WarehouseDeleted;
use App\Events\WarehouseSaved;
use App\Models\GoodsReceiptNote;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WarehouseControllerTest extends TestCase
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

    /** A received GRN against the warehouse — the reference that used to 500. */
    private function grnFor(Warehouse $warehouse, string $number): GoodsReceiptNote
    {
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::create([
            'po_number' => 'PO-'.random_int(10000, 99999),
            'supplier_id' => $supplier->id,
            'po_date' => now(),
            'total_amount' => 100,
            'status' => 'sent',
        ]);

        return GoodsReceiptNote::create([
            'grn_number' => $number,
            'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'received_date' => now(),
            'status' => 'draft',
        ]);
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

    public function test_it_stores_the_pin_dropped_on_the_map(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/warehouses', $this->payload([
                'latitude' => 26.1421234,
                'longitude' => 50.5832456,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.latitude', 26.1421234)
            ->assertJsonPath('data.longitude', 50.5832456);

        $this->assertSame(26.1421234, Warehouse::first()->latitude);
    }

    /** A warehouse recorded before the picker existed simply has no pin. */
    public function test_coordinates_are_optional(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/warehouses', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.latitude', null)
            ->assertJsonPath('data.longitude', null);
    }

    public function test_it_rejects_coordinates_off_the_globe(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/warehouses', $this->payload([
                'latitude' => 95,
                'longitude' => 200,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /**
     * Half a pin is not a location: a lone latitude would put the marker on the
     * prime meridian rather than nowhere.
     */
    public function test_it_rejects_half_a_pin(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/inventory/warehouses', $this->payload(['latitude' => 26.1421]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_it_clears_a_pin_on_update(): void
    {
        $warehouse = Warehouse::create($this->payload([
            'latitude' => 26.1421,
            'longitude' => 50.5832,
        ]));

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/inventory/warehouses/{$warehouse->id}", $this->payload([
                'latitude' => null,
                'longitude' => null,
            ]))
            ->assertOk()
            ->assertJsonPath('data.latitude', null);

        $this->assertNull($warehouse->fresh()->latitude);
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

    /**
     * Goods receipt notes, material issues, delivery notes and production
     * outputs all reference a warehouse with onDelete('restrict'), and none of
     * them was checked. A warehouse holding only those passed the stock guard,
     * hit the constraint, and the user was shown the raw SQL — table names,
     * the query, and the path to the database file.
     */
    public function test_it_deactivates_a_warehouse_used_only_by_a_goods_receipt_note(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-PROD', 'name' => 'Production']);
        $this->grnFor($warehouse, 'GRN-0001');

        $response = $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/warehouses/{$warehouse->id}")
            ->assertOk()
            ->assertJsonPath('deactivated', true);

        // It says what is holding it, rather than an SQL exception.
        $this->assertSame(
            'Warehouse is used by 1 goods receipt note, so it was deactivated rather than deleted.',
            $response->json('message')
        );

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
        $this->assertFalse($warehouse->fresh()->is_active);
    }

    public function test_the_message_names_every_kind_of_record_holding_the_warehouse(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-MIX', 'name' => 'Mixed']);
        $item = Item::create(['item_code' => 'ITEM-9', 'item_name' => 'Bar', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);

        StockMovement::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'type' => 'in', 'quantity' => 1]);
        foreach (['GRN-0002', 'GRN-0003'] as $number) {
            $this->grnFor($warehouse, $number);
        }

        $message = $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/warehouses/{$warehouse->id}")
            ->assertOk()
            ->json('message');

        // Counted and pluralised, joined with "and".
        $this->assertSame(
            'Warehouse is used by 1 stock movement and 2 goods receipt notes, so it was deactivated rather than deleted.',
            $message
        );
    }

    /** Whatever happens, the database path and the SQL stay out of the response. */
    public function test_deleting_a_used_warehouse_never_returns_a_sql_error(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-X', 'name' => 'Busy']);
        $this->grnFor($warehouse, 'GRN-0004');

        $body = $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/inventory/warehouses/{$warehouse->id}")
            ->assertOk()
            ->content();

        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('database.sqlite', $body);
        $this->assertStringNotContainsString('FOREIGN KEY', $body);
    }

    /**
     * Clicking a warehouse has to answer "what is in here" — both what is
     * bought and consumed and what is made and sold, which the two item pages
     * deliberately keep apart.
     */
    public function test_it_shows_a_warehouses_stock_split_by_type(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);
        $section = ItemCategory::where('name', 'Chemical Materials')->firstOrFail();

        $raw = Item::create([
            'item_code' => 'ITEM-1', 'item_name' => 'Pentaproof 20 P', 'category' => 'raw_material',
            'item_category_id' => $section->id, 'unit_of_measure' => 'KG', 'cost_price' => 2.25,
        ]);
        $made = Item::create([
            'item_code' => 'ITEM-2', 'item_name' => 'SUPERBOND F5 White', 'category' => 'finished_good',
            'unit_of_measure' => 'BAG', 'cost_price' => 5,
        ]);
        StockLevel::create(['item_id' => $raw->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);
        StockLevel::create(['item_id' => $made->id, 'warehouse_id' => $warehouse->id, 'quantity' => 4]);

        $response = $this->actingAs($this->actingUser())
            ->getJson("/api/v1/inventory/warehouses/{$warehouse->id}")
            ->assertOk();

        $response->assertJsonPath('data.name', 'Askar')
            ->assertJsonPath('meta.total_items', 2)
            ->assertJsonPath('meta.raw_material_count', 1)
            ->assertJsonPath('meta.finished_good_count', 1)
            // 10 x 2.25 plus 4 x 5 — fractional on purpose, so the fils survive.
            ->assertJsonPath('meta.total_value', 42.5);

        $lines = collect($response->json('items'))->keyBy('item_name');
        $this->assertSame('Raw Materials / Chemical Materials', $lines['Pentaproof 20 P']['category_path']);
        $this->assertSame('Finished Goods', $lines['SUPERBOND F5 White']['category_path']);
        $this->assertSame(22.5, $lines['Pentaproof 20 P']['total_value']);
    }

    /**
     * An item assigned here but not yet delivered is still this warehouse's
     * responsibility, and is the normal state for everything just imported.
     */
    public function test_it_lists_an_item_assigned_here_with_no_stock_yet(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);
        $item = Item::create([
            'item_code' => 'ITEM-1', 'item_name' => 'Silica Sand', 'category' => 'raw_material',
            'unit_of_measure' => 'KG', 'cost_price' => 1,
        ]);
        StockLevel::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 0]);

        $this->actingAs($this->actingUser())
            ->getJson("/api/v1/inventory/warehouses/{$warehouse->id}")
            ->assertOk()
            ->assertJsonPath('meta.total_items', 1)
            ->assertJsonPath('items.0.item_name', 'Silica Sand')
            ->assertJsonPath('items.0.quantity', 0);
    }

    /** The header count leads with what needs attention, as the reports do. */
    public function test_it_counts_lines_below_their_minimum(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);
        $short = Item::create([
            'item_code' => 'ITEM-1', 'item_name' => 'Short', 'category' => 'raw_material',
            'unit_of_measure' => 'KG', 'minimum_stock_level' => 10,
        ]);
        $fine = Item::create([
            'item_code' => 'ITEM-2', 'item_name' => 'Fine', 'category' => 'raw_material',
            'unit_of_measure' => 'KG', 'minimum_stock_level' => 0,
        ]);
        StockLevel::create(['item_id' => $short->id, 'warehouse_id' => $warehouse->id, 'quantity' => 4]);
        // No minimum set, so an empty shelf is not a shortage.
        StockLevel::create(['item_id' => $fine->id, 'warehouse_id' => $warehouse->id, 'quantity' => 0]);

        $this->actingAs($this->actingUser())
            ->getJson("/api/v1/inventory/warehouses/{$warehouse->id}")
            ->assertOk()
            ->assertJsonPath('meta.below_minimum', 1);
    }

    public function test_the_detail_endpoint_requires_authentication(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Askar']);

        $this->getJson("/api/v1/inventory/warehouses/{$warehouse->id}")->assertUnauthorized();
    }
}
