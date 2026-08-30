<?php

namespace Tests\Feature\Api\Sales;

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Item $widget;

    private Warehouse $warehouse;

    private SalesOrder $order;

    private SalesOrderItem $orderLine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['name' => 'Gulf Steel']);
        $this->widget = Item::create(['item_code' => 'FG-1', 'item_name' => 'Widget', 'category' => 'finished_good', 'unit_of_measure' => 'PCS']);
        $this->warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
        $this->order = SalesOrder::create([
            'order_number' => 'SO-00001', 'customer_id' => $this->customer->id,
            'order_date' => now(), 'status' => 'confirmed', 'total_amount' => 50,
        ]);
        $this->orderLine = SalesOrderItem::create([
            'sales_order_id' => $this->order->id, 'item_id' => $this->widget->id,
            'quantity' => 5, 'price' => 10, 'total_amount' => 50, 'quantity_delivered' => 0,
        ]);
        StockLevel::create(['item_id' => $this->widget->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 20]);
    }

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sales_order_id' => $this->order->id,
            'warehouse_id' => $this->warehouse->id,
            'delivery_date' => now()->toDateString(),
            'items' => [['item_id' => $this->widget->id, 'quantity' => 2]],
        ], $overrides);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/sales/delivery-notes')->assertUnauthorized();
    }

    /**
     * delivery_notes.customer_id is NOT NULL and the Blade controller never set
     * it, so every create failed with a 500. It belongs to the order.
     */
    public function test_it_creates_a_note_and_takes_the_customer_from_the_order(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/delivery-notes', $this->payload())
            ->assertCreated();

        $this->assertSame($this->customer->id, $response->json('data.customer_id'));
        $this->assertSame('draft', $response->json('data.status'));
        $this->assertMatchesRegularExpression('/^DN-\d{5}$/', $response->json('data.delivery_number'));
    }

    public function test_it_links_each_line_to_the_real_sales_order_line(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/delivery-notes', $this->payload())->assertCreated();

        $this->assertDatabaseHas('delivery_note_items', [
            'sales_order_item_id' => $this->orderLine->id,
            'item_id' => $this->widget->id,
        ]);
    }

    public function test_it_refuses_to_deliver_more_than_the_order_outstanding(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/delivery-notes', $this->payload([
                'items' => [['item_id' => $this->widget->id, 'quantity' => 99]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_it_refuses_an_item_that_is_not_on_the_order(): void
    {
        $other = Item::create(['item_code' => 'FG-2', 'item_name' => 'Other', 'category' => 'finished_good', 'unit_of_measure' => 'PCS']);

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/delivery-notes', $this->payload([
                'items' => [['item_id' => $other->id, 'quantity' => 1]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.item_id']);
    }

    public function test_dispatching_decrements_stock_and_records_a_movement(): void
    {
        $id = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/delivery-notes', $this->payload())->json('data.id');

        $this->actingAs($this->actingUser())
            ->patchJson("/api/v1/sales/delivery-notes/{$id}/dispatch")
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatched');

        $this->assertEquals(18, StockLevel::first()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->widget->id, 'type' => 'out', 'quantity' => 2,
            'reference_type' => 'DeliveryNote', 'reference_id' => $id,
        ]);
    }

    public function test_dispatching_advances_the_order_line_delivered_quantity(): void
    {
        $id = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/delivery-notes', $this->payload())->json('data.id');
        $this->actingAs($this->actingUser())->patchJson("/api/v1/sales/delivery-notes/{$id}/dispatch");

        $this->assertEquals(2, $this->orderLine->fresh()->quantity_delivered);
        // Partly delivered, so the order is not dispatched yet.
        $this->assertSame('confirmed', $this->order->fresh()->status);
    }

    public function test_the_order_becomes_dispatched_once_every_line_is_fully_delivered(): void
    {
        $id = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/delivery-notes', $this->payload([
                'items' => [['item_id' => $this->widget->id, 'quantity' => 5]],
            ]))->json('data.id');

        $this->actingAs($this->actingUser())->patchJson("/api/v1/sales/delivery-notes/{$id}/dispatch");

        $this->assertSame('dispatched', $this->order->fresh()->status);
    }

    public function test_a_note_cannot_be_dispatched_twice(): void
    {
        $id = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/delivery-notes', $this->payload())->json('data.id');
        $this->actingAs($this->actingUser())->patchJson("/api/v1/sales/delivery-notes/{$id}/dispatch")->assertOk();

        $this->actingAs($this->actingUser())
            ->patchJson("/api/v1/sales/delivery-notes/{$id}/dispatch")
            ->assertStatus(422);

        // Stock moved once, not twice.
        $this->assertEquals(18, StockLevel::first()->quantity);
    }

    public function test_form_options_reports_the_outstanding_quantity_per_line(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/sales/delivery-notes/form-options')->assertOk();

        $this->assertEquals(5, $response->json('orders.0.items.0.outstanding'));
        $this->assertSame('Main', $response->json('warehouses.0.name'));
    }

    public function test_form_options_excludes_draft_orders(): void
    {
        SalesOrder::create(['order_number' => 'SO-DRAFT', 'customer_id' => $this->customer->id, 'order_date' => now(), 'status' => 'draft']);

        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/sales/delivery-notes/form-options')->assertOk();

        $this->assertSame(['SO-00001'], array_column($response->json('orders'), 'order_number'));
    }
}
