<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/purchase/suppliers')->assertStatus(401);
    }

    public function test_index_returns_suppliers_ordered_by_name(): void
    {
        $user = User::factory()->create();
        Supplier::factory()->create(['name' => 'Zeta Steel']);
        Supplier::factory()->create(['name' => 'Acme Steel']);

        $response = $this->actingAs($user)->getJson('/api/v1/purchase/suppliers');

        $response->assertOk();
        $this->assertSame('Acme Steel', $response->json('data.0.name'));
        $this->assertSame('Zeta Steel', $response->json('data.1.name'));
    }

    public function test_show_returns_a_single_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/v1/purchase/suppliers/{$supplier->id}");

        $response->assertOk()->assertJsonPath('data.id', $supplier->id);
    }

    public function test_store_creates_a_supplier(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/purchase/suppliers', [
            'name' => 'New Supplier Co',
            'email' => 'contact@newsupplier.test',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'New Supplier Co');
        $this->assertDatabaseHas('suppliers', ['name' => 'New Supplier Co']);
    }

    public function test_store_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/purchase/suppliers', [])
            ->assertStatus(422);
    }

    public function test_update_modifies_a_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/v1/purchase/suppliers/{$supplier->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_destroy_deletes_a_supplier(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->actingAs($user)->deleteJson("/api/v1/purchase/suppliers/{$supplier->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
