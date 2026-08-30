<?php

namespace Tests\Feature\Purchase;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createItem(): Item
    {
        return Item::create([
            'item_code' => 'ITEM-'.random_int(1000, 9999),
            'item_name' => 'Test Item',
            'category' => 'raw_material',
            'unit_of_measure' => 'pcs',
            'minimum_stock_level' => 0,
            'cost_price' => 10,
            'is_active' => true,
        ]);
    }

    private function validPayload(Supplier $supplier, Item $item): array
    {
        return [
            'supplier_id' => $supplier->id,
            'po_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_id' => $item->id, 'quantity' => 2, 'rate' => 10],
            ],
        ];
    }

    public function test_unauthorized_user_cannot_create_a_purchase_order_linked_to_a_request(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $item = $this->createItem();
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs($user)
            ->post(route('purchase.orders.store'), $this->validPayload($supplier, $item) + ['purchase_request_id' => $atLpo->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('purchase_orders', ['supplier_id' => $supplier->id]);
    }

    public function test_unauthorized_user_cannot_create_a_purchase_order_with_no_request_context(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->post(route('purchase.orders.store'), $this->validPayload($supplier, $item))
            ->assertForbidden();

        $this->assertDatabaseMissing('purchase_orders', ['supplier_id' => $supplier->id]);
    }

    public function test_procurement_officer_can_create_a_purchase_order_linked_to_a_request_at_lpo_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $supplier = Supplier::factory()->create();
        $item = $this->createItem();
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs($procurement)
            ->post(route('purchase.orders.store'), $this->validPayload($supplier, $item) + ['purchase_request_id' => $atLpo->id])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier->id]);
    }

    public function test_procurement_officer_with_generate_lpo_permission_can_create_a_manual_order(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $supplier = Supplier::factory()->create();
        $item = $this->createItem();

        $this->actingAs($procurement)
            ->post(route('purchase.orders.store'), $this->validPayload($supplier, $item))
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier->id]);
    }

    public function test_unauthorized_user_cannot_update_an_order(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::create([
            'po_number' => 'PO-TEST-1',
            'supplier_id' => $supplier->id,
            'po_date' => now(),
            'total_amount' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->put(route('purchase.orders.update', $order), ['supplier_id' => $supplier->id, 'po_date' => now()->format('Y-m-d')])
            ->assertForbidden();
    }

    public function test_unauthorized_user_cannot_delete_an_order(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::create([
            'po_number' => 'PO-TEST-2',
            'supplier_id' => $supplier->id,
            'po_date' => now(),
            'total_amount' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->delete(route('purchase.orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    public function test_procurement_officer_can_update_and_delete_an_order_linked_to_a_request_at_lpo_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $supplier = Supplier::factory()->create();
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $order = PurchaseOrder::create([
            'po_number' => 'PO-TEST-3',
            'supplier_id' => $supplier->id,
            'purchase_request_id' => $atLpo->id,
            'po_date' => now(),
            'total_amount' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($procurement)
            ->put(route('purchase.orders.update', $order), ['supplier_id' => $supplier->id, 'po_date' => now()->format('Y-m-d')])
            ->assertRedirect();

        $this->actingAs($procurement)
            ->delete(route('purchase.orders.destroy', $order))
            ->assertRedirect();

        $this->assertDatabaseMissing('purchase_orders', ['id' => $order->id]);
    }
}
