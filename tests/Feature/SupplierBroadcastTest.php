<?php

namespace Tests\Feature;

use App\Events\SupplierSaved;
use App\Models\Supplier;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_broadcasts_on_the_shared_purchase_channel(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Acme Steel']);

        $event = new SupplierSaved($supplier);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        // PrivateChannel prepends "private-" to the channel name
        $this->assertSame('private-purchase', $channels[0]->name);
        $this->assertSame('supplier.saved', $event->broadcastAs());
        $this->assertSame('Acme Steel', $event->broadcastWith()['name']);
    }

    public function test_it_broadcasts_immediately_rather_than_via_the_queue(): void
    {
        $supplier = Supplier::factory()->create();

        $this->assertInstanceOf(ShouldBroadcastNow::class, new SupplierSaved($supplier));
    }

    public function test_broadcast_payload_contains_the_full_supplier_field_set(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Acme Steel',
            'supplier_code' => 'SUP-1',
            'category' => 'Raw Material',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'whatsapp_number' => '555-0101',
            'address' => '123 Main St',
            'tax_number' => 'TRN-999',
            'credit_days' => 45,
            'is_active' => true,
        ]);

        $payload = (new SupplierSaved($supplier))->broadcastWith();

        $this->assertSame([
            'id' => $supplier->id,
            'supplier_code' => 'SUP-1',
            'name' => 'Acme Steel',
            'category' => 'Raw Material',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'whatsapp_number' => '555-0101',
            'address' => '123 Main St',
            'tax_number' => 'TRN-999',
            'credit_days' => 45,
            'is_active' => true,
        ], $payload);
    }
}
