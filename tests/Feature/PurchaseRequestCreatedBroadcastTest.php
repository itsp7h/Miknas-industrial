<?php

namespace Tests\Feature;

use App\Events\PurchaseRequestCreated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PurchaseRequestCreatedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_a_request_broadcasts_it(): void
    {
        Event::fake([PurchaseRequestCreated::class]);
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.create');
        $this->actingAs($user);

        $this->post(route('purchase.requests.store'), [
            'date' => now()->toDateString(),
            'project_name' => 'Test Project',
            'requested_by_name' => $user->name,
            'items' => [
                ['description' => 'Steel bars', 'quantity_required' => 10],
            ],
        ]);

        Event::assertDispatched(PurchaseRequestCreated::class);
    }

    public function test_event_broadcasts_on_the_shared_purchase_channel(): void
    {
        $event = new PurchaseRequestCreated(1, 'MPR26-0001', '2026-08-02', 'Test Project', 'Jane', 'Ops', 'draft');

        $channels = $event->broadcastOn();

        $this->assertSame('private-purchase', $channels[0]->name);
        $this->assertSame('purchase-request.created', $event->broadcastAs());
    }
}
