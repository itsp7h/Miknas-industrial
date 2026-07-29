<?php

namespace Tests\Feature\Api;

use App\Events\DashboardPinged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DashboardPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_endpoint_broadcasts_dashboard_pinged_event(): void
    {
        Event::fake([DashboardPinged::class]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/dashboard/ping')->assertNoContent();

        Event::assertDispatched(DashboardPinged::class, function (DashboardPinged $event) use ($user) {
            return $event->userId === $user->id;
        });
    }
}
