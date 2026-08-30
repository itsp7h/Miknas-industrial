<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One event for both material issues and production outputs: each carries the
 * kind of record and its payload, so the two list pages can subscribe to the
 * same channel without a class per row type.
 */
class ProductionFlowRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $kind, public array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('production')];
    }

    public function broadcastAs(): string
    {
        return $this->kind.'.recorded';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
