<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Settings\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A request names its company, so its goods have a yard already implied.
 *
 * The chain the receipt walks is GRN -> purchase order -> request ->
 * company -> warehouse, and every link of it is exercised here.
 */
class GrnCompanyWarehouseTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $askar;

    private Warehouse $hidd;

    private Supplier $supplier;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->askar = Warehouse::create(['name' => 'Askar', 'code' => 'WH-ASKAR']);
        $this->hidd = Warehouse::create(['name' => 'Hidd', 'code' => 'WH-HIDD']);
        $this->supplier = Supplier::factory()->create();
        $this->item = Item::create([
            'item_code' => 'RM-1', 'item_name' => 'Steel Plate',
            'category' => 'raw_material', 'unit_of_measure' => 'KG', 'cost_price' => 10,
        ]);
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    /** A sent order for $companyName, with one line of ten. */
    private function orderFor(?string $companyName): PurchaseOrder
    {
        $requestId = null;

        if ($companyName !== null) {
            $requestId = PurchaseRequest::create([
                'request_number' => 'MPR-1', 'date' => now()->toDateString(),
                'company_name' => $companyName, 'status' => 'approved', 'stage' => 'receiving',
                'requested_by' => User::factory()->create()->id,
            ])->id;
        }

        $order = PurchaseOrder::create([
            'po_number' => 'PO-00001', 'supplier_id' => $this->supplier->id,
            'purchase_request_id' => $requestId, 'po_date' => now(),
            'total_amount' => 100, 'status' => 'sent',
        ]);
        $order->items()->create([
            'item_id' => $this->item->id, 'quantity' => 10, 'rate' => 10,
            'total_amount' => 100, 'quantity_received' => 0,
        ]);

        return $order;
    }

    private function payload(PurchaseOrder $order, int $warehouseId): array
    {
        return [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $warehouseId,
            'received_date' => now()->toDateString(),
            'items' => [[
                'item_id' => $this->item->id,
                'purchase_order_item_id' => $order->items->first()->id,
                'quantity_received' => 4,
                'unit_cost' => 10,
                'type' => 'inventory',
            ]],
        ];
    }

    public function test_the_form_tells_each_order_which_warehouse_its_company_receives_into(): void
    {
        Company::create(['name' => 'Miknas Industrial', 'warehouse_id' => $this->askar->id, 'is_active' => true]);
        $this->orderFor('Miknas Industrial');

        $response = $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns/form-options')->assertOk();

        $this->assertSame($this->askar->id, $response->json('purchase_orders.0.warehouse_id'));
        $this->assertSame('Miknas Industrial', $response->json('purchase_orders.0.company_name'));
    }

    /** An unlinked company leaves the choice open, exactly as before the link existed. */
    public function test_an_unlinked_company_implies_no_warehouse(): void
    {
        Company::create(['name' => 'Matana', 'is_active' => true]);
        $this->orderFor('Matana');

        $response = $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns/form-options')->assertOk();

        $this->assertNull($response->json('purchase_orders.0.warehouse_id'));
    }

    public function test_an_order_with_no_request_behind_it_implies_no_warehouse(): void
    {
        $this->orderFor(null);

        $response = $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/grns/form-options')->assertOk();

        $this->assertNull($response->json('purchase_orders.0.warehouse_id'));
    }

    public function test_it_receives_into_the_company_warehouse(): void
    {
        Company::create(['name' => 'Miknas Industrial', 'warehouse_id' => $this->askar->id, 'is_active' => true]);
        $order = $this->orderFor('Miknas Industrial');

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload($order, $this->askar->id))
            ->assertCreated();

        $this->assertDatabaseHas('goods_receipt_notes', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->askar->id,
        ]);
    }

    /** The point of the whole feature: Miknas stock cannot be booked into Hidd. */
    public function test_it_refuses_a_receipt_into_another_companys_warehouse(): void
    {
        Company::create(['name' => 'Miknas Industrial', 'warehouse_id' => $this->askar->id, 'is_active' => true]);
        $order = $this->orderFor('Miknas Industrial');

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload($order, $this->hidd->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('warehouse_id');

        $this->assertDatabaseCount('goods_receipt_notes', 0);
    }

    /** Without a link there is nothing to enforce, so any warehouse is honoured. */
    public function test_it_allows_any_warehouse_when_the_company_is_unlinked(): void
    {
        Company::create(['name' => 'Matana', 'is_active' => true]);
        $order = $this->orderFor('Matana');

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/grns', $this->payload($order, $this->hidd->id))
            ->assertCreated();

        $this->assertDatabaseHas('goods_receipt_notes', ['warehouse_id' => $this->hidd->id]);
    }
}
