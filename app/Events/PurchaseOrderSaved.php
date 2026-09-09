<?php

namespace App\Events;

use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'purchase-order.saved';
    }

    public function broadcastWith(): array
    {
        return (new PurchaseOrderResource(
            $this->purchaseOrder->loadMissing(['supplier', 'items.item'])
        ))->resolve();
    }
}
