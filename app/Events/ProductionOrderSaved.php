<?php

namespace App\Events;

use App\Http\Resources\ProductionOrderResource;
use App\Models\ProductionOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductionOrderSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProductionOrder $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('production')];
    }

    public function broadcastAs(): string
    {
        return 'production-order.saved';
    }

    public function broadcastWith(): array
    {
        return (new ProductionOrderResource($this->order->loadMissing('product')))->resolve();
    }
}
