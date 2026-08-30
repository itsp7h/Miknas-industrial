<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ProductionOrderDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $productionOrderId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('production')];
    }

    public function broadcastAs(): string
    {
        return 'production-order.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->productionOrderId];
    }
}
