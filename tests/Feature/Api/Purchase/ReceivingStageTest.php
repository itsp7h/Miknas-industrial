<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\GoodsReceiptNote;
use App\Models\GrnItem;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The Receiving stage had no way out of itself.
 *
 * Nothing advanced a request past 'receiving' — not recording a GRN, not
 * confirming one — so the pipeline kept offering "Record GRN" for ever with no
 * sign that anything had been recorded. Worse, the two steps look alike from
 * the pipeline: `store` writes a *draft* GRN that moves no stock, and only
 * `confirm` raises it. A user recording goods had no way to tell which of those
 * had happened.
 */
class ReceivingStageTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseRequest $pr;

    private Warehouse $warehouse;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->pr = PurchaseRequest::factory()->create(['stage' => 'receiving']);
        $this->warehouse = Warehouse::create(['code' => 'WH-MAIN', 'name' => 'Main Store']);
        $this->item = Item::create([
            'item_code' => 'ITEM-00001', 'item_name' => 'Steel plate',
            'category' => 'raw_material', 'unit_of_measure' => 'PCS', 'cost_price' => 10,
        ]);
    }

    /** Whoever counts what arrived. Named by the square, not by a profile. */
    private function storeManager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['goods-receipts.view', 'goods-receipts.edit']);

        return $user;
    }

    /** Confirming is the Store Manager's job; reading the pipeline is not. */
    private function officer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'pipeline.view', 'pipeline.manage-rfq', 'pipeline.manage-quotes',
            'pipeline.award', 'pipeline.generate-lpo', 'pipeline.view-active-pipeline',
            'purchase-orders.view', 'purchase-orders.create',
            'purchase-orders.edit', 'purchase-orders.delete',
            'goods-receipts.view', 'goods-receipts.create',
            'goods-receipts.edit', 'goods-receipts.delete',
        ]);

        return $user;
    }

    private function order(float $quantity = 2, string $status = 'sent'): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'po_number' => 'PO-'.str_pad((string) (PurchaseOrder::max('id') + 1), 5, '0', STR_PAD_LEFT),
            'supplier_id' => Supplier::factory()->create()->id,
            'purchase_request_id' => $this->pr->id,
            'po_date' => now(), 'total_amount' => 20, 'status' => $status,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id, 'item_id' => $this->item->id,
            'quantity' => $quantity, 'rate' => 10, 'total_amount' => $quantity * 10,
            'quantity_received' => 0,
        ]);

        return $order;
    }

    private function grnFor(PurchaseOrder $order, float $quantity): GoodsReceiptNote
    {
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-'.str_pad((string) (GoodsReceiptNote::max('id') + 1), 5, '0', STR_PAD_LEFT),
            'purchase_order_id' => $order->id,
            'supplier_id' => $order->supplier_id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now(),
            'status' => 'draft',
        ]);

        GrnItem::create([
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => $order->items->first()->id,
            'item_id' => $this->item->id,
            'quantity_received' => $quantity,
            'unit_cost' => 10,
            'type' => 'inventory',
        ]);

        return $grn;
    }

    private function confirm(GoodsReceiptNote $grn)
    {
        return $this->actingAs($this->storeManager())
            ->patchJson("/api/v1/purchase/grns/{$grn->id}/confirm");
    }

    public function test_confirming_the_last_receipt_completes_the_request(): void
    {
        $order = $this->order(2);

        $this->confirm($this->grnFor($order, 2))->assertOk();

        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame('complete', $this->pr->fresh()->stage);
    }

    /**
     * A draft GRN has raised no stock. It must not move the request on, which
     * is exactly the confusion the pipeline was showing: goods recorded,
     * nothing received.
     */
    public function test_recording_a_receipt_without_confirming_it_leaves_the_stage_alone(): void
    {
        $order = $this->order(2);
        $this->grnFor($order, 2);

        $this->assertSame('receiving', $this->pr->fresh()->stage);
        $this->assertSame('sent', $order->fresh()->status);
    }

    public function test_a_partial_receipt_keeps_the_request_at_receiving(): void
    {
        $order = $this->order(5);

        $this->confirm($this->grnFor($order, 2))->assertOk();

        $this->assertSame('sent', $order->fresh()->status);
        $this->assertSame('receiving', $this->pr->fresh()->stage);
    }

    /** Two LPOs on one request: the first receipt is not the last. */
    public function test_it_waits_for_every_lpo_on_the_request(): void
    {
        $first = $this->order(2);
        $second = $this->order(3);

        $this->confirm($this->grnFor($first, 2))->assertOk();
        $this->assertSame('receiving', $this->pr->fresh()->stage);

        $this->confirm($this->grnFor($second, 3))->assertOk();
        $this->assertSame('complete', $this->pr->fresh()->stage);
    }

    /**
     * A re-issue leaves the superseded LPO on the request. It will never be
     * received, so waiting on it would strand the request at Receiving for
     * ever.
     */
    public function test_a_cancelled_lpo_does_not_hold_the_request_back(): void
    {
        $this->order(2, 'cancelled');
        $live = $this->order(2);

        $this->confirm($this->grnFor($live, 2))->assertOk();

        $this->assertSame('complete', $this->pr->fresh()->stage);
    }

    /** The pipeline can only show a receipt if the payload carries it. */
    public function test_the_pipeline_payload_lists_the_receipts_against_the_request(): void
    {
        $order = $this->order(2);
        $grn = $this->grnFor($order, 2);

        $response = $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/pipeline/{$this->pr->id}")
            ->assertOk();

        $this->assertSame($grn->grn_number, $response->json('data.goods_receipt_notes.0.grn_number'));
        $this->assertSame('draft', $response->json('data.goods_receipt_notes.0.status'));
        $this->assertSame('Main Store', $response->json('data.goods_receipt_notes.0.warehouse_name'));
        $this->assertSame($order->po_number, $response->json('data.goods_receipt_notes.0.po_number'));
    }
}
