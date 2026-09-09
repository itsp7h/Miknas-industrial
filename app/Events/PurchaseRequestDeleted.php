<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Only the id: there is nothing left to describe, and every board that shows
 * the request needs to drop its row.
 */
class PurchaseRequestDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $purchaseRequestId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'purchase-request.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->purchaseRequestId];
    }
}
