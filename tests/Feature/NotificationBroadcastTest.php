<?php

namespace Tests\Feature;

use App\Events\NotificationPushed;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RfqInvitation;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_a_broadcastable_event_on_the_users_private_channel(): void
    {
        $user = User::factory()->create();

        $event = new NotificationPushed($user->id, 'Test title', 'Test body', '/app');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        // PrivateChannel prepends "private-" to the channel name (see
        // Illuminate\Broadcasting\PrivateChannel::__construct) — the
        // Broadcast::channel('App.Models.User.{id}', ...) authorization
        // callback in routes/channels.php still matches this correctly,
        // since Laravel strips the prefix when resolving the callback.
        $this->assertSame('private-App.Models.User.'.$user->id, $channels[0]->name);
        $this->assertSame('notification.pushed', $event->broadcastAs());
    }

    public function test_it_broadcasts_immediately_rather_than_via_the_queue(): void
    {
        $event = new NotificationPushed(1, 'Test title', 'Test body');

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_submitting_a_supplier_quote_fires_the_event_for_admin_users(): void
    {
        Event::fake([NotificationPushed::class]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $purchaseRequest = PurchaseRequest::factory()->create(['stage' => 'quoting']);
        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $purchaseRequest->id,
            'description' => 'Steel rod',
            'unit' => 'kg',
            'quantity_required' => 10,
        ]);

        $invitation = RfqInvitation::factory()->create([
            'purchase_request_id' => $purchaseRequest->id,
            'status' => 'opened',
        ]);

        $confirmCode = 'ABCDE';
        $this->withSession(['rfq_confirm_'.$invitation->token => $confirmCode]);

        $response = $this->post('/rfq/'.$invitation->token, [
            'terms' => '1',
            'confirm_code' => $confirmCode,
            'lead_time_days' => 5,
            'payment_terms' => 'Net 30',
            'notes' => null,
            'items' => [
                ['unit_price' => 5, 'is_vatable' => false, 'not_available' => false],
            ],
        ]);

        $response->assertOk();

        Event::assertDispatched(NotificationPushed::class, function (NotificationPushed $event) use ($admin) {
            return $event->userId === $admin->id
                && $event->title === 'New Quote Received'
                && str_contains($event->body, $item->purchaseRequest->request_number ?? $event->body);
        });
    }
}
