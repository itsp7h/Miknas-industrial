<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\PurchaseOrderDeleted;
use App\Events\PurchaseOrderSaved;
use App\Models\GoodsReceiptNote;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = Supplier::factory()->create();
        $this->item = Item::create([
            'item_code' => 'RM-1',
            'item_name' => 'Steel Plate',
            'category' => 'raw_material',
            'unit_of_measure' => 'KG',
            'cost_price' => 10,
            'is_active' => true,
        ]);
    }

    /** A user cleared to generate LPOs, which is what PO create/edit/delete gates on. */
    private function procurementUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Procurement Officer');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id,
            'po_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 3, 'rate' => 20],
            ],
        ], $overrides);
    }

    private function makeOrder(array $attributes = []): PurchaseOrder
    {
        return PurchaseOrder::create(array_merge([
            'po_number' => 'PO-'.random_int(10000, 99999),
            'supplier_id' => $this->supplier->id,
            'po_date' => now(),
            'total_amount' => 0,
            'status' => 'draft',
        ], $attributes));
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/purchase/orders')->assertUnauthorized();
    }

    public function test_it_lists_orders_with_the_supplier_name(): void
    {
        $this->makeOrder();

        $this->actingAs($this->procurementUser())
            ->getJson('/api/v1/purchase/orders')
            ->assertOk()
            ->assertJsonPath('data.0.supplier_name', $this->supplier->name);
    }

    public function test_it_creates_an_order_and_totals_its_lines(): void
    {
        Event::fake([PurchaseOrderSaved::class]);

        $this->actingAs($this->procurementUser())
            ->postJson('/api/v1/purchase/orders', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.total_amount', '60.00')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.items.0.total_amount', '60.00');

        $this->assertDatabaseHas('purchase_order_items', [
            'item_id' => $this->item->id,
            'quantity' => 3,
            'rate' => 20,
        ]);

        Event::assertDispatched(PurchaseOrderSaved::class);
    }

    public function test_it_rejects_an_order_with_no_lines(): void
    {
        $this->actingAs($this->procurementUser())
            ->postJson('/api/v1/purchase/orders', $this->payload(['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_it_rejects_a_delivery_date_before_the_po_date(): void
    {
        $this->actingAs($this->procurementUser())
            ->postJson('/api/v1/purchase/orders', $this->payload([
                'expected_delivery_date' => now()->subDay()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('expected_delivery_date');
    }

    /**
     * The Blade edit form collected these two fields but its controller saved
     * only supplier_id/po_date/status, so both were silently dropped.
     */
    public function test_it_persists_expected_delivery_date_and_notes_on_update(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->procurementUser())
            ->putJson("/api/v1/purchase/orders/{$order->id}", [
                'supplier_id' => $this->supplier->id,
                'po_date' => now()->toDateString(),
                'expected_delivery_date' => now()->addWeek()->toDateString(),
                'status' => 'sent',
                'notes' => 'Deliver to gate 3.',
            ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Deliver to gate 3.')
            ->assertJsonPath('data.status', 'sent');

        $this->assertSame('Deliver to gate 3.', $order->fresh()->notes);
        $this->assertSame(
            now()->addWeek()->toDateString(),
            $order->fresh()->expected_delivery_date->toDateString()
        );
    }

    public function test_it_deletes_an_order_and_its_lines(): void
    {
        Event::fake([PurchaseOrderDeleted::class]);

        $order = $this->makeOrder();
        $order->items()->create([
            'item_id' => $this->item->id, 'quantity' => 1, 'rate' => 5, 'total_amount' => 5,
        ]);

        $this->actingAs($this->procurementUser())
            ->deleteJson("/api/v1/purchase/orders/{$order->id}")
            ->assertOk();

        $this->assertDatabaseMissing('purchase_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('purchase_order_items', ['purchase_order_id' => $order->id]);

        Event::assertDispatched(PurchaseOrderDeleted::class);
    }

    public function test_it_refuses_to_delete_an_order_that_already_has_a_grn(): void
    {
        $order = $this->makeOrder();
        GoodsReceiptNote::create([
            'grn_number' => 'GRN-00001',
            'purchase_order_id' => $order->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => Warehouse::create(['name' => 'Main', 'code' => 'WH-1'])->id,
            'received_date' => now(),
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->procurementUser())
            ->deleteJson("/api/v1/purchase/orders/{$order->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    // ---------------------------------------------------------------------
    // Authorization — ported from the Blade PurchaseOrderAuthorizationTest so
    // moving these endpoints onto /api/v1 cannot quietly drop the guard.
    // ---------------------------------------------------------------------

    public function test_unauthorized_user_cannot_create_an_order_linked_to_a_request(): void
    {
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/purchase/orders', $this->payload(['purchase_request_id' => $atLpo->id]))
            ->assertForbidden();

        $this->assertDatabaseMissing('purchase_orders', ['supplier_id' => $this->supplier->id]);
    }

    public function test_unauthorized_user_cannot_create_an_order_with_no_request_context(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/purchase/orders', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('purchase_orders', ['supplier_id' => $this->supplier->id]);
    }

    public function test_procurement_officer_can_create_an_order_linked_to_a_request_at_lpo_stage(): void
    {
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs($this->procurementUser())
            ->postJson('/api/v1/purchase/orders', $this->payload(['purchase_request_id' => $atLpo->id]))
            ->assertCreated();
    }

    /** A request not yet at the lpo stage fails the instance-scoped policy check. */
    public function test_procurement_officer_cannot_create_an_order_for_a_request_before_lpo_stage(): void
    {
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->actingAs($this->procurementUser())
            ->postJson('/api/v1/purchase/orders', $this->payload(['purchase_request_id' => $atRfq->id]))
            ->assertForbidden();
    }

    public function test_unauthorized_user_cannot_update_an_order(): void
    {
        $order = $this->makeOrder();

        $this->actingAs(User::factory()->create())
            ->putJson("/api/v1/purchase/orders/{$order->id}", [
                'supplier_id' => $this->supplier->id,
                'po_date' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_unauthorized_user_cannot_delete_an_order(): void
    {
        $order = $this->makeOrder();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/v1/purchase/orders/{$order->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    public function test_show_returns_the_lines_and_linked_grns(): void
    {
        $order = $this->makeOrder();
        $order->items()->create([
            'item_id' => $this->item->id, 'quantity' => 2, 'rate' => 15, 'total_amount' => 30,
        ]);

        $this->actingAs($this->procurementUser())
            ->getJson("/api/v1/purchase/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.item_name', 'Steel Plate')
            ->assertJsonPath('data.items.0.unit_of_measure', 'KG')
            ->assertJsonPath('data.goods_receipt_notes', []);
    }
}
