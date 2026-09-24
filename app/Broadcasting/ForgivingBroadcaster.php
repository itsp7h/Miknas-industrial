<?php

namespace App\Broadcasting;

use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A broadcaster that will not take a completed write down with it.
 *
 * Every event in this app is `ShouldBroadcastNow`, so the publish happens
 * inside the request that caused it — and a Reverb that is not answering makes
 * `broadcast()` throw a `BroadcastException`. That exception surfaces as a 500
 * *after* the row has already been written or deleted, because the `event()`
 * call comes after the save and nothing wraps the two in a transaction. The
 * user is told the delete failed; the record is gone.
 *
 * Production showed exactly this: no reachable Reverb, so deleting an item or
 * a purchase request answered 500 while doing the delete.
 *
 * So a failed publish is logged and swallowed. The write is the thing the user
 * asked for and it succeeded; the live update is an improvement on top of it,
 * and the honest failure mode for an improvement is a stale screen someone can
 * refresh, not an error on work that was done.
 *
 * `auth()` is *not* forgiving. Channel authorization deciding who may listen is
 * a security answer, and swallowing it would let anyone subscribe to anything.
 */
class ForgivingBroadcaster implements Broadcaster
{
    public function __construct(private readonly Broadcaster $inner) {}

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        try {
            $this->inner->broadcast($channels, $event, $payload);
        } catch (Throwable $e) {
            // Warning, not error: nothing the user asked for has failed. What
            // is lost is that other open screens will not notice until they
            // are reloaded.
            Log::warning('Broadcast failed; the write it accompanied still stands.', [
                'event' => $event,
                'channels' => array_map('strval', $channels),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function auth($request)
    {
        return $this->inner->auth($request);
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $this->inner->validAuthenticationResponse($request, $result);
    }

    /**
     * Everything else the manager forwards — `Broadcast::channel()` above all.
     *
     * `BroadcastManager::__call()` hands any method it does not know to the
     * driver, and `routes/channels.php` calls `Broadcast::channel()` on every
     * boot. The contract names only the three methods above, so without this a
     * decorated driver takes the whole app down: it did, on staging, the moment
     * this class became the `reverb` driver. Tests run on the `null` driver and
     * never noticed.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->inner->{$method}(...$parameters);
    }
}
