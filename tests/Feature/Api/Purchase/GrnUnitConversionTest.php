<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\GrnSaved;
use App\Models\GoodsReceiptNote;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UnitConversion;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cement is kept in KG. The supplier quoted 5 BAG at 12.000 a bag and never
 * said what a bag holds, so the LPO line has no factor. The GRN counts the
 * bags that arrive and, before Confirm, someone with
 * `goods-receipts.convert-units` says 1 BAG = 25 KG: that is what puts
 * 125 KG on the shelf at 0.480 a KG.
 */
class GrnUnitConversionTest extends TestCase
{
    use RefreshDatabase;

    private Item $item;

    private Warehouse $warehouse;

    private PurchaseOrder $order;

    private PurchaseOrderItem $line;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $supplier = Supplier::factory()->create();
        $this->item = Item::create([
            'item_code' => 'RM-9', 'item_name' => 'Cement',
            'category' => 'raw_material', 'unit_of_measure' => 'KG', 'cost_price' => 0.5,
        ]);
        $this->warehouse = Warehouse::create(['name' => 'Main Store', 'code' => 'WH-1']);
        $this->order = PurchaseOrder::create([
            'po_number' => 'PO-00009', 'supplier_id' => $supplier->id,
            'po_date' => now(), 'total_amount' => 60, 'status' => 'sent',
        ]);
        $this->line = $this->order->items()->create([
            'item_id' => $this->item->id, 'quantity' => 125, 'rate' => 0.48, 'total_amount' => 60,
            'quantity_received' => 0, 'system_unit' => 'KG',
            'supplier_unit' => 'BAG', 'unit_factor' => null, 'supplier_quantity' => 5, 'supplier_rate' => 12,
        ]);
    }

    private function storeKeeper(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['goods-receipts.view', 'goods-receipts.create', 'goods-receipts.edit']);

        return $user;
    }

    private function converter(): User
    {
        $user = $this->storeKeeper();
        $user->givePermissionTo('goods-receipts.convert-units');

        return $user;
    }

    /** A draft GRN that counted $bags BAG, with its three documents. */
    private function receive(float $bags = 5): GoodsReceiptNote
    {
        $id = $this->actingAs($this->storeKeeper())->post('/api/v1/purchase/grns', [
            'purchase_order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [[
                'item_id' => $this->item->id,
                'purchase_order_item_id' => $this->line->id,
                'supplier_quantity' => $bags,
                'type' => 'inventory',
            ]],
            'lpo_document' => UploadedFile::fake()->create('lpo.pdf', 10, 'application/pdf'),
            'grn_document' => UploadedFile::fake()->create('grn.pdf', 10, 'application/pdf'),
            'tax_invoice_document' => UploadedFile::fake()->create('inv.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        return GoodsReceiptNote::findOrFail($id);
    }

    private function convertUrl(GoodsReceiptNote $grn): string
    {
        return "/api/v1/purchase/grns/{$grn->id}/items/{$grn->items()->first()->id}/conversion";
    }

    public function test_form_options_offer_the_line_in_the_suppliers_unit(): void
    {
        $this->actingAs($this->storeKeeper())
            ->getJson('/api/v1/purchase/grns/form-options')
            ->assertOk()
            ->assertJsonPath('purchase_orders.0.items.0.supplier_unit', 'BAG')
            ->assertJsonPath('purchase_orders.0.items.0.supplier_quantity', 5)
            ->assertJsonPath('purchase_orders.0.items.0.supplier_rate', 12);
    }

    public function test_a_line_ordered_in_their_unit_is_received_in_it_and_waits_for_a_conversion(): void
    {
        $grn = $this->receive(5);

        $line = $grn->items()->first();
        $this->assertSame('BAG', $line->supplier_unit);
        $this->assertEquals(5, $line->supplier_quantity);
        $this->assertEquals(12, $line->supplier_rate);
        $this->assertEquals(0, $line->quantity_received);
        $this->assertTrue($line->conversionPending());

        $this->actingAs($this->storeKeeper())
            ->getJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.conversion_pending', true)
            ->assertJsonPath('data.missing_conversions', ['Cement']);
    }

    public function test_it_must_say_how_many_of_their_unit_arrived(): void
    {
        $this->actingAs($this->storeKeeper())->postJson('/api/v1/purchase/grns', [
            'purchase_order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [['item_id' => $this->item->id, 'purchase_order_item_id' => $this->line->id, 'type' => 'inventory']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.supplier_quantity']);

        $this->assertDatabaseCount('goods_receipt_notes', 0);
    }

    public function test_confirm_is_refused_until_the_conversion_is_set(): void
    {
        $grn = $this->receive();

        $this->actingAs($this->storeKeeper())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Set the unit conversion for Cement before confirming this GRN.');

        $this->assertSame('draft', $grn->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_setting_a_conversion_needs_its_own_permission(): void
    {
        $grn = $this->receive();

        $this->actingAs($this->storeKeeper())
            ->patchJson($this->convertUrl($grn), ['unit_factor' => 25])
            ->assertForbidden();

        $this->assertTrue($grn->items()->first()->conversionPending());
    }

    public function test_an_admin_may_set_a_conversion(): void
    {
        $grn = $this->receive();
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->patchJson($this->convertUrl($grn), ['unit_factor' => 25])->assertOk();
    }

    public function test_the_conversion_turns_their_count_into_ours_and_is_remembered(): void
    {
        Event::fake([GrnSaved::class]);
        $grn = $this->receive(5);
        $converter = $this->converter();

        $this->actingAs($converter)
            ->patchJson($this->convertUrl($grn), ['unit_factor' => 25])
            ->assertOk()
            ->assertJsonPath('message', 'Cement: 1 BAG = 25 KG.')
            ->assertJsonPath('data.items.0.conversion_pending', false)
            ->assertJsonPath('data.items.0.converted_by_name', $converter->name)
            ->assertJsonPath('data.missing_conversions', []);

        $line = $grn->items()->first();
        $this->assertEquals(125, $line->quantity_received);
        $this->assertEquals(0.48, $line->unit_cost);
        $this->assertSame($converter->id, $line->converted_by);
        $this->assertEquals(25, UnitConversion::factorFor($this->item->id, 'BAG'));
        Event::assertDispatched(GrnSaved::class);
    }

    public function test_confirming_stocks_ours_and_counts_the_order_in_theirs(): void
    {
        $grn = $this->receive(5);
        $this->actingAs($this->converter())->patchJson($this->convertUrl($grn), ['unit_factor' => 25])->assertOk();

        $this->actingAs($this->storeKeeper())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals(125, StockLevel::where('item_id', $this->item->id)->value('quantity'));
        $this->assertEquals(125, StockMovement::firstOrFail()->quantity);

        $this->line->refresh();
        $this->assertEquals(125, $this->line->quantity_received);
        $this->assertEquals(5, $this->line->supplier_quantity_received);
        $this->assertSame('received', $this->order->fresh()->status);
    }

    /**
     * 125 KG was the estimate on the order. Bags of 20 make 100 KG, short of
     * it in ours, yet every bag ordered is in: the order is received.
     */
    public function test_an_order_in_their_unit_is_received_when_every_bag_is_in(): void
    {
        $grn = $this->receive(5);
        $this->actingAs($this->converter())->patchJson($this->convertUrl($grn), ['unit_factor' => 20])->assertOk();
        $this->actingAs($this->storeKeeper())->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")->assertOk();

        $this->assertSame('received', $this->order->fresh()->status);
        $this->assertEquals(100, StockLevel::where('item_id', $this->item->id)->value('quantity'));
    }

    public function test_a_partial_delivery_leaves_the_order_open(): void
    {
        $grn = $this->receive(2);
        $this->actingAs($this->converter())->patchJson($this->convertUrl($grn), ['unit_factor' => 25])->assertOk();
        $this->actingAs($this->storeKeeper())->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")->assertOk();

        $this->assertNotSame('received', $this->order->fresh()->status);
        $this->assertEquals(2, $this->line->fresh()->supplier_quantity_received);
    }

    public function test_the_next_grn_suggests_the_remembered_factor_which_can_be_changed(): void
    {
        UnitConversion::remember($this->item->id, 'BAG', 25);
        $grn = $this->receive();

        $this->actingAs($this->storeKeeper())
            ->getJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertJsonPath('data.items.0.suggested_factor', 25);

        $this->actingAs($this->converter())->patchJson($this->convertUrl($grn), ['unit_factor' => 50])->assertOk();

        $this->assertEquals(50, UnitConversion::factorFor($this->item->id, 'BAG'));
        $this->assertDatabaseCount('unit_conversions', 1);
    }

    public function test_a_factor_on_the_order_is_suggested_first(): void
    {
        UnitConversion::remember($this->item->id, 'BAG', 25);
        $this->line->update(['unit_factor' => 40]);
        $grn = $this->receive();

        $this->actingAs($this->storeKeeper())
            ->getJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertJsonPath('data.items.0.suggested_factor', 40);
    }

    public function test_a_conversion_must_be_more_than_zero(): void
    {
        $grn = $this->receive();

        $this->actingAs($this->converter())
            ->patchJson($this->convertUrl($grn), ['unit_factor' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_factor']);
    }

    public function test_a_confirmed_grn_cannot_be_reconverted(): void
    {
        $grn = $this->receive();
        $this->actingAs($this->converter())->patchJson($this->convertUrl($grn), ['unit_factor' => 25])->assertOk();
        $this->actingAs($this->storeKeeper())->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")->assertOk();

        $this->actingAs($this->converter())
            ->patchJson($this->convertUrl($grn), ['unit_factor' => 30])
            ->assertUnprocessable();

        $this->assertEquals(125, $grn->items()->first()->quantity_received);
    }

    public function test_a_line_in_our_own_unit_has_nothing_to_convert(): void
    {
        $this->line->update(['supplier_unit' => null, 'supplier_quantity' => null, 'supplier_rate' => null]);
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-1', 'purchase_order_id' => $this->order->id, 'supplier_id' => $this->order->supplier_id,
            'warehouse_id' => $this->warehouse->id, 'received_date' => now(), 'status' => 'draft',
        ]);
        $grn->items()->create(['purchase_order_item_id' => $this->line->id, 'item_id' => $this->item->id, 'quantity_received' => 10, 'unit_cost' => 0.5, 'type' => 'inventory']);

        $this->actingAs($this->converter())
            ->patchJson($this->convertUrl($grn), ['unit_factor' => 25])
            ->assertUnprocessable();
    }

    public function test_a_line_from_another_grn_is_not_found(): void
    {
        $grn = $this->receive();
        $other = $this->receive();

        $this->actingAs($this->converter())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/items/{$other->items()->first()->id}/conversion", ['unit_factor' => 25])
            ->assertNotFound();
    }
}
