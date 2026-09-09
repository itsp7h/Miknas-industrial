<?php

namespace App\Events;

use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesOrderSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SalesOrder $salesOrder) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sales')];
    }

    public function broadcastAs(): string
    {
        return 'sales-order.saved';
    }

    public function broadcastWith(): array
    {
        return (new SalesOrderResource($this->salesOrder->loadMissing(['customer', 'items.item'])))->resolve();
    }
}
