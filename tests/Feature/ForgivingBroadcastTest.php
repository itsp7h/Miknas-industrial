<?php

namespace Tests\Feature;

use App\Broadcasting\ForgivingBroadcaster;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * A Reverb that is not answering must not turn a completed write into a 500.
 *
 * Every event in this app is `ShouldBroadcastNow`, so the publish rides inside
 * the request and its failure would otherwise be reported as the request's
 * failure — after the row had already been written or deleted.
 */
class ForgivingBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function broadcasterThatThrows(?Throwable $e = null): Broadcaster
    {
        return new class($e ?? new BroadcastException('Pusher error: cURL error 7')) implements Broadcaster
        {
            public function __construct(private readonly Throwable $e) {}

            public function broadcast(array $channels, $event, array $payload = [])
            {
                throw $this->e;
            }

            public function auth($request)
            {
                return ['auth' => 'ok'];
            }

            public function validAuthenticationResponse($request, $result)
            {
                return $result;
            }
        };
    }

    public function test_a_failed_publish_does_not_reach_the_caller(): void
    {
        Log::spy();

        $broadcaster = new ForgivingBroadcaster($this->broadcasterThatThrows());

        $broadcaster->broadcast(['private-purchase'], 'purchase-request.deleted', ['id' => 7]);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => str_contains($message, 'Broadcast failed')
                && $context['event'] === 'purchase-request.deleted'
                && $context['channels'] === ['private-purchase']
        );
    }

    /** Anything thrown, not just the one exception Pusher happens to use. */
    public function test_it_swallows_whatever_the_broadcaster_throws(): void
    {
        Log::spy();

        $broadcaster = new ForgivingBroadcaster($this->broadcasterThatThrows(new RuntimeException('boom')));

        $broadcaster->broadcast(['private-inventory'], 'item.deleted', ['id' => 1]);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_publish_that_works_is_passed_straight_through(): void
    {
        $inner = new class implements Broadcaster
        {
            public array $sent = [];

            public function broadcast(array $channels, $event, array $payload = [])
            {
                $this->sent[] = [$channels, $event, $payload];
            }

            public function auth($request)
            {
                return null;
            }

            public function validAuthenticationResponse($request, $result)
            {
                return $result;
            }
        };

        (new ForgivingBroadcaster($inner))->broadcast(['private-purchase'], 'x', ['id' => 1]);

        $this->assertSame([[['private-purchase'], 'x', ['id' => 1]]], $inner->sent);
    }

    /**
     * Authorization is a security answer, not an improvement on top of one.
     * Swallowing it would let anyone listen to anything.
     */
    public function test_channel_authorization_is_not_forgiving(): void
    {
        $broadcaster = new ForgivingBroadcaster($this->broadcasterThatThrows());

        $this->assertSame(['auth' => 'ok'], $broadcaster->auth(request()));
    }

    /**
     * The one that matters: the endpoint that reported a 500 on production
     * while the row went away regardless.
     */
    public function test_a_delete_still_answers_200_when_reverb_is_unreachable(): void
    {
        $this->pointBroadcastingAtNothing();

        $requester = User::factory()->create();
        $requester->givePermissionTo(['pipeline.view', 'pipeline.delete', 'pipeline.view-own']);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $requester->id,
            'stage' => 'draft',
            'request_number' => 'MI-MPR-26-0026',
        ]);

        $this->actingAs($requester)
            ->deleteJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertOk()
            ->assertJsonPath('message', 'MI-MPR-26-0026 deleted.');

        $this->assertDatabaseMissing('purchase_requests', ['id' => $pr->id]);
    }

    /** A Reverb that is not there: nothing listens on this port. */
    private function pointBroadcastingAtNothing(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'k',
                'secret' => 's',
                'app_id' => 'a',
                'options' => ['host' => '127.0.0.1', 'port' => 9, 'scheme' => 'http', 'useTLS' => false],
                'client_options' => ['connect_timeout' => 1, 'timeout' => 1],
            ],
        ]);
    }

    public function test_the_reverb_connection_is_wired_through_it(): void
    {
        config([
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'local-key',
                'secret' => 'local-secret',
                'app_id' => 'local-app',
                'options' => ['host' => '127.0.0.1', 'port' => 9999, 'scheme' => 'http', 'useTLS' => false],
            ],
        ]);

        $this->assertInstanceOf(ForgivingBroadcaster::class, Broadcast::connection('reverb'));
    }
}
