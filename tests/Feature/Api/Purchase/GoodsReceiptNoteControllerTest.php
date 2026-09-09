<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\GrnDeleted;
use App\Events\GrnSaved;
use App\Models\GoodsReceiptNote;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GoodsReceiptNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Item $item;

    private Warehouse $warehouse;

    private PurchaseOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create();
        $this->item = Item::create([
            'item_code' => 'RM-1', 'item_name' => 'Steel Plate',
            'category' => 'raw_material', 'unit_of_measure' => 'KG', 'cost_price' => 10,
        ]);
        $this->warehouse = Warehouse::create(['name' => 'Main Store', 'code' => 'WH-1']);
        $this->order = PurchaseOrder::create([
            'po_number' => 'PO-00001',
            'supplier_id' => $this->supplier->id,
            'po_date' => now(),
            'total_amount' => 100,
            'status' => 'sent',
        ]);
        $this->order->items()->create([
            'item_id' => $this->item->id, 'quantity' => 10, 'rate' => 10,
            'total_amount' => 100, 'quantity_received' => 0,
        ]);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'purchase_order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [[
                'item_id' => $this->item->id,
                'purchase_order_item_id' => $this->order->items->first()->id,
                'quantity_received' => 4,
                'unit_cost' => 10,
                'type' => 'inventory',
            ]],
        ], $overrides);
    }

    private function makeGrn(string $status = 'draft'): GoodsReceiptNote
    {
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-'.random_int(10000, 99999),
            'purchase_order_id' => $this->order->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now(),
            'status' => $status,
        ]);
        $grn->items()->create([
            'purchase_order_item_id' => $this->order->items->first()->id,
            'item_id' => $this->item->id,
            'quantity_received' => 4,
            'unit_cost' => 10,
            'type' => 'inventory',
        ]);

        return $grn;
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/purchase/grns')->assertUnauthorized();
    }

    public function test_it_lists_grns_with_the_po_and_supplier(): void
    {
        $this->makeGrn();

        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns')
            ->assertOk()
            ->assertJsonPath('data.0.po_number', 'PO-00001')
            ->assertJsonPath('data.0.supplier_name', $this->supplier->name)
            ->assertJsonPath('data.0.warehouse_name', 'Main Store');
    }

    public function test_form_options_offers_receivable_orders_with_their_lines(): void
    {
        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns/form-options')
            ->assertOk()
            ->assertJsonPath('purchase_orders.0.po_number', 'PO-00001')
            ->assertJsonPath('purchase_orders.0.items.0.item_name', 'Steel Plate')
            ->assertJsonPath('purchase_orders.0.items.0.quantity', '10.00')
            ->assertJsonPath('warehouses.0.name', 'Main Store');
    }

    /** Only sent/partial orders are receivable, matching the Blade create page. */
    public function test_form_options_excludes_orders_that_are_not_receivable(): void
    {
        $this->order->update(['status' => 'draft']);

        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns/form-options')
            ->assertOk()
            ->assertJsonPath('purchase_orders', []);
    }

    public function test_it_creates_a_grn_as_draft_and_broadcasts(): void
    {
        Event::fake([GrnSaved::class]);

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.items.0.quantity_received', '4.00');

        Event::assertDispatched(GrnSaved::class);
    }

    /**
     * The Blade create form collected Notes but its controller omitted the field
     * from create(), so whatever the user typed was silently discarded.
     */
    public function test_it_persists_the_notes_the_blade_controller_dropped(): void
    {
        $response = $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload(['notes' => 'Two crates damaged.']))
            ->assertCreated()
            ->assertJsonPath('data.notes', 'Two crates damaged.');

        $this->assertSame('Two crates damaged.', GoodsReceiptNote::find($response->json('data.id'))->notes);
    }

    /**
     * The show page printed $item->quantity_ordered, which is not a column on
     * grn_items, so PO Qty always read 0.00. It comes from the PO line.
     */
    public function test_show_reports_the_ordered_quantity_from_the_purchase_order_line(): void
    {
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->getJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity_ordered', '10.00')
            ->assertJsonPath('data.items.0.quantity_received', '4.00');
    }

    public function test_it_rejects_a_grn_with_no_lines(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload(['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    // ------------------------------------------------------------------
    // Confirm — the action no Blade page ever linked to, so stock never moved.
    // ------------------------------------------------------------------

    public function test_confirming_raises_stock_and_records_a_movement(): void
    {
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(
            '4.00',
            (string) StockLevel::where('item_id', $this->item->id)
                ->where('warehouse_id', $this->warehouse->id)->value('quantity')
        );
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in',
            'reference_type' => 'GoodsReceiptNote',
            'reference_id' => $grn->id,
        ]);
    }

    public function test_confirming_advances_the_purchase_order_line(): void
    {
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertOk();

        $this->assertSame('4.00', (string) $this->order->items()->first()->quantity_received);
        // Only 4 of 10 received, so the order is not finished.
        $this->assertSame('sent', $this->order->fresh()->status);
    }

    public function test_the_order_becomes_received_once_every_line_is_met(): void
    {
        $grn = $this->makeGrn();
        $grn->items()->first()->update(['quantity_received' => 10]);

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertOk();

        $this->assertSame('received', $this->order->fresh()->status);
    }

    public function test_confirming_twice_is_refused(): void
    {
        $grn = $this->makeGrn('confirmed');

        $this->actingAs($this->user())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm")
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------

    public function test_it_deletes_a_draft_grn_and_its_lines(): void
    {
        Event::fake([GrnDeleted::class]);
        $grn = $this->makeGrn();

        $this->actingAs($this->user())
            ->deleteJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertOk();

        $this->assertDatabaseMissing('goods_receipt_notes', ['id' => $grn->id]);
        $this->assertDatabaseMissing('grn_items', ['goods_receipt_note_id' => $grn->id]);
        Event::assertDispatched(GrnDeleted::class);
    }

    /** A confirmed GRN has already moved stock; deleting it would orphan those movements. */
    public function test_it_refuses_to_delete_a_confirmed_grn(): void
    {
        $grn = $this->makeGrn('confirmed');

        $this->actingAs($this->user())
            ->deleteJson("/api/v1/purchase/grns/{$grn->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('goods_receipt_notes', ['id' => $grn->id]);
    }
}
