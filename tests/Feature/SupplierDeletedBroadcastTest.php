<?php

namespace Tests\Feature;

use App\Events\SupplierDeleted;
use Tests\TestCase;

class SupplierDeletedBroadcastTest extends TestCase
{
    public function test_it_broadcasts_the_deleted_suppliers_id_on_the_purchase_channel(): void
    {
        $event = new SupplierDeleted(42);

        $channels = $event->broadcastOn();

        // PrivateChannel prepends "private-" to the channel name and exposes
        // it as a public property, not a method (see the SupplierSaved/
        // NotificationPushed broadcast tests for the same pattern).
        $this->assertSame('private-purchase', $channels[0]->name);
        $this->assertSame('supplier.deleted', $event->broadcastAs());
        $this->assertSame(['id' => 42], $event->broadcastWith());
    }
}
