<?php

namespace Tests\Feature\Api\Sales;

use App\Events\SalesOrderSaved;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SalesOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Item $widget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['name' => 'Gulf Steel']);
        $this->widget = Item::create([
            'item_code' => 'FG-1', 'item_name' => 'Widget',
            'category' => 'finished_good', 'unit_of_measure' => 'PCS',
        ]);
    }

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $this->widget->id, 'quantity' => 2, 'price' => 50],
            ],
        ], $overrides);
    }

    /**
     * The detail page carries a Customer card — name, contact, email, phone —
     * which the React port had dropped along with the fields that feed it.
     */
    public function test_the_detail_endpoint_carries_the_customers_contact_details(): void
    {
        $this->customer->update([
            'contact_person' => 'A. Buyer', 'email' => 'a@gulf.example', 'phone' => '111',
        ]);
        $order = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/orders', $this->payload())->assertCreated();

        $response = $this->actingAs($this->actingUser())
            ->getJson("/api/v1/sales/orders/{$order->json('data.id')}")->assertOk();

        $this->assertSame('Gulf Steel', $response->json('data.customer.name'));
        $this->assertSame('A. Buyer', $response->json('data.customer.contact_person'));
        $this->assertSame('a@gulf.example', $response->json('data.customer.email'));
        $this->assertSame('111', $response->json('data.customer.phone'));
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/sales/orders')->assertUnauthorized();
    }

    /**
     * The Blade controller validated items.*.unit_price — a field its own form
     * never posted — and then wrote unit_price/amount, which are not fillable
     * on SalesOrderItem. Creating an order was impossible. These are the real
     * column names.
     */
    public function test_it_creates_an_order_with_line_items_priced_correctly(): void
    {
        Event::fake([SalesOrderSaved::class]);

        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/orders', $this->payload())
            ->assertCreated();

        $this->assertSame('draft', $response->json('data.status'));
        $this->assertEquals(100, $response->json('data.total_amount'));
        $this->assertDatabaseHas('sales_order_items', [
            'item_id' => $this->widget->id, 'quantity' => 2, 'price' => 50, 'total_amount' => 100,
        ]);
        Event::assertDispatched(SalesOrderSaved::class);
    }

    public function test_it_generates_a_sequential_order_number(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/orders', $this->payload())->assertCreated();

        $this->assertMatchesRegularExpression('/^SO-\d{5}$/', $response->json('data.order_number'));
    }

    public function test_it_totals_multiple_lines(): void
    {
        $second = Item::create(['item_code' => 'FG-2', 'item_name' => 'Gadget', 'category' => 'finished_good', 'unit_of_measure' => 'PCS']);

        $response = $this->actingAs($this->actingUser())->postJson('/api/v1/sales/orders', $this->payload([
            'items' => [
                ['item_id' => $this->widget->id, 'quantity' => 2, 'price' => 50],
                ['item_id' => $second->id, 'quantity' => 3, 'price' => 10],
            ],
        ]))->assertCreated();

        $this->assertEquals(130, $response->json('data.total_amount'));
    }

    public function test_it_requires_at_least_one_line_item(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/orders', $this->payload(['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_it_rejects_a_delivery_date_before_the_order_date(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/orders', $this->payload([
                'delivery_date' => now()->subWeek()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_date']);
    }

    public function test_confirming_moves_a_draft_to_confirmed(): void
    {
        $id = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/orders', $this->payload())->json('data.id');

        $this->actingAs($this->actingUser())
            ->patchJson("/api/v1/sales/orders/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_an_already_confirmed_order_cannot_be_confirmed_again(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'SO-9', 'customer_id' => $this->customer->id,
            'order_date' => now(), 'status' => 'confirmed',
        ]);

        $this->actingAs($this->actingUser())
            ->patchJson("/api/v1/sales/orders/{$order->id}/confirm")
            ->assertStatus(422);
    }

    /**
     * Editing an order after it has been confirmed would silently change what
     * the customer already agreed to.
     */
    public function test_a_confirmed_order_cannot_be_edited(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'SO-9', 'customer_id' => $this->customer->id,
            'order_date' => now(), 'status' => 'confirmed',
        ]);

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/sales/orders/{$order->id}", $this->payload())
            ->assertStatus(422);
    }

    public function test_updating_a_draft_replaces_its_lines_and_retotals(): void
    {
        $id = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/orders', $this->payload())->json('data.id');

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/sales/orders/{$id}", $this->payload([
                'items' => [['item_id' => $this->widget->id, 'quantity' => 5, 'price' => 20]],
            ]))
            ->assertOk()
            ->assertJsonPath('data.total_amount', '100.00');

        $this->assertDatabaseCount('sales_order_items', 1);
    }

    public function test_form_options_offers_only_active_customers_and_finished_goods(): void
    {
        Item::create(['item_code' => 'RM-1', 'item_name' => 'Raw Bar', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);
        Customer::create(['name' => 'Dormant', 'is_active' => false]);

        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/sales/orders/form-options')->assertOk();

        $this->assertSame(['Gulf Steel'], array_column($response->json('customers'), 'name'));
        $this->assertSame(['Widget'], array_column($response->json('items'), 'item_name'));
    }

    public function test_it_deletes_a_draft_order_and_its_lines(): void
    {
        $id = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/orders', $this->payload())->json('data.id');

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/sales/orders/{$id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseCount('sales_order_items', 0);
    }
}
