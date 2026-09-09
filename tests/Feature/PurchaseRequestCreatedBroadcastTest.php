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

        // The create form is a React modal now, so the write is the API.
        $this->postJson('/api/v1/purchase/requests', [
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
        $event = new PurchaseRequestCreated(1, 'MPR26-0001', '2026-08-02', 'Test Project', 'Jane', 'Ops', 'draft', 42);

        $channels = $event->broadcastOn();

        $this->assertSame('private-purchase', $channels[0]->name);
        $this->assertSame('purchase-request.created', $event->broadcastAs());
    }

    public function test_broadcast_payload_includes_requester_id_and_plain_date(): void
    {
        $event = new PurchaseRequestCreated(1, 'MPR26-0001', '2026-08-02', 'Test Project', 'Jane', 'Ops', 'draft', 42);

        $payload = $event->broadcastWith();

        $this->assertSame([
            'id' => 1,
            'request_number' => 'MPR26-0001',
            'date' => '2026-08-02',
            'project_name' => 'Test Project',
            'requested_by_name' => 'Jane',
            'department' => 'Ops',
            'stage' => 'draft',
            'requested_by_id' => 42,
        ], $payload);
    }
}
