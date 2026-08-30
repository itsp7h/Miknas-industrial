<?php

namespace Tests\Feature\Api\Sales;

use App\Events\CustomerDeleted;
use App\Events\CustomerSaved;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Gulf Steel WLL',
            'contact_person' => 'A. Buyer',
            'email' => 'buyer@example.com',
            'phone' => '+97312345678',
            'credit_limit' => 5000,
        ], $overrides);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/sales/customers')->assertUnauthorized();
    }

    public function test_it_lists_customers_ordered_by_name(): void
    {
        Customer::create(['name' => 'Zenith Trading']);
        Customer::create(['name' => 'Alpha Contracting']);

        $response = $this->actingAs($this->actingUser())->getJson('/api/v1/sales/customers');

        $response->assertOk();
        $this->assertSame(['Alpha Contracting', 'Zenith Trading'], array_column($response->json('data'), 'name'));
    }

    public function test_it_creates_a_customer(): void
    {
        Event::fake([CustomerSaved::class]);

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/customers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Gulf Steel WLL');

        $this->assertDatabaseHas('customers', ['name' => 'Gulf Steel WLL']);
        Event::assertDispatched(CustomerSaved::class);
    }

    public function test_it_requires_a_name(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/customers', $this->payload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_rejects_a_malformed_email(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/customers', $this->payload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * credit_limit is NOT NULL with a 0 default — omitting it must not send a
     * null into the insert.
     */
    public function test_it_defaults_an_omitted_credit_limit_to_zero(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/customers', ['name' => 'No Credit Co'])
            ->assertCreated();

        $this->assertEquals(0, $response->json('data.credit_limit'));
    }

    public function test_it_updates_a_customer(): void
    {
        $customer = Customer::create(['name' => 'Old Name']);

        $this->actingAs($this->actingUser())
            ->putJson("/api/v1/sales/customers/{$customer->id}", $this->payload(['name' => 'New Name']))
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertSame('New Name', $customer->fresh()->name);
    }

    public function test_it_deletes_a_customer_with_no_sales_history(): void
    {
        Event::fake([CustomerDeleted::class]);
        $customer = Customer::create(['name' => 'Never Traded']);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/sales/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        Event::assertDispatched(CustomerDeleted::class);
    }

    public function test_it_deactivates_rather_than_deletes_a_customer_with_orders(): void
    {
        $customer = Customer::create(['name' => 'Has Orders']);
        SalesOrder::create([
            'order_number' => 'SO-1',
            'customer_id' => $customer->id,
            'order_date' => now(),
            'status' => 'draft',
        ]);

        $this->actingAs($this->actingUser())
            ->deleteJson("/api/v1/sales/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('deactivated', true);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertFalse($customer->fresh()->is_active);
    }
}
