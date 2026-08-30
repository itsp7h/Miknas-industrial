<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ItemDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $itemId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventory')];
    }

    public function broadcastAs(): string
    {
        return 'item.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->itemId];
    }
}
