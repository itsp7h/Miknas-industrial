<?php
// tests/Feature/Api/Purchase/SupplierControllerTest.php

namespace Tests\Feature\Api\Purchase;

use App\Events\SupplierSaved;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        return $user;
    }

    public function test_index_returns_all_suppliers(): void
    {
        $this->actingUser();
        Supplier::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/purchase/suppliers');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_store_creates_a_supplier_and_broadcasts(): void
    {
        Event::fake([SupplierSaved::class]);
        $this->actingUser();

        $response = $this->postJson('/api/v1/purchase/suppliers', [
            'name' => 'Acme Steel',
            'credit_days' => 30,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('suppliers', ['name' => 'Acme Steel']);
        Event::assertDispatched(SupplierSaved::class);
    }

    public function test_store_requires_a_name(): void
    {
        $this->actingUser();

        $response = $this->postJson('/api/v1/purchase/suppliers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_modifies_a_supplier_and_broadcasts(): void
    {
        Event::fake([SupplierSaved::class]);
        $this->actingUser();
        $supplier = Supplier::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/purchase/suppliers/{$supplier->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'New Name']);
        Event::assertDispatched(SupplierSaved::class);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/purchase/suppliers');

        $response->assertUnauthorized();
    }
}
