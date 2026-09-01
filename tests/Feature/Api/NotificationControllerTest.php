<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Written straight into the notifications table rather than via a
     * Notification class: the only database-channel notification today
     * (QuoteReceived) needs a whole RfqInvitation graph, which this endpoint
     * does not care about.
     */
    private function giveUnread(User $user, string $message): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QuoteReceived',
            'data' => ['message' => $message, 'url' => '/app/purchase/pipeline'],
        ]);
    }

    public function test_mark_all_read_requires_authentication(): void
    {
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
    }

    /** Backs the topbar dropdown's "Mark all read" control. */
    public function test_it_marks_every_unread_notification_as_read(): void
    {
        $user = User::factory()->create();
        $this->giveUnread($user, 'A supplier sent a quote.');
        $this->giveUnread($user, 'Another quote arrived.');

        $this->assertSame(2, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('marked', true);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    /** One user marking their own notifications read must not touch anyone else's. */
    public function test_it_leaves_other_users_notifications_alone(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $this->giveUnread($mine, 'Mine.');
        $this->giveUnread($theirs, 'Theirs.');

        $this->actingAs($mine)->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertSame(1, $theirs->fresh()->unreadNotifications()->count());
    }

    public function test_unread_lists_the_users_notifications(): void
    {
        $user = User::factory()->create();
        $this->giveUnread($user, 'A supplier sent a quote.');

        $this->actingAs($user)
            ->getJson('/api/v1/notifications/unread')
            ->assertOk()
            ->assertJsonPath('notifications.0.body', 'A supplier sent a quote.');
    }
}
