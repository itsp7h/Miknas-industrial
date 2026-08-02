<?php
// tests/Feature/SupplierBroadcastTest.php

namespace Tests\Feature;

use App\Events\SupplierSaved;
use App\Models\Supplier;
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
}
